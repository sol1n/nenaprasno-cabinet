<?php

namespace App\Services;

use App\Backend;
use App\Object;
use App\Schema;
use App\Traits\Models\SchemaSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ObjectManager
{

    private $backend;
    private $lists;

    protected $model = Object::class;
    protected $cacheLifetime = 10;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
        $this->lists = new Collection;
    }

    private function initList(Schema $schema, $query = [])
    {
        if (! $this->lists->has($schema->id))
        {
            if (! $objects = $this->getFromCache($schema)) 
            {
                try {
                    $objects = $this->model::list($schema, $this->backend, $query);
                } catch (\Exception $e) {
                    $objects = collect([]);
                }
                $this->saveToCache($schema, $objects);
            }

            $this->lists->put($schema->id, $objects);
        }
    }

    private function getCacheTag(Schema $schema): String
    {
        return app(Backend::Class)->code . '-'. $this->model . '-' . $schema->id;
    }

    private function saveToCache(Schema $schema, Collection $data)
    {
        if (env('APPERCODE_ENABLE_CACHING') == 1)
        {
            Cache::put($this->getCacheTag($schema), $data, $this->cacheLifetime);
        }
    }

    private function getFromCache(Schema $schema)
    {
        if (Cache::has($this->getCacheTag($schema)) && (env('APPERCODE_ENABLE_CACHING') == 1)) {
            return Cache::get($this->getCacheTag($schema));
        } else {
            return null;
        }
    }

    public function find(Schema $schema, $id): Object
    {
        try {
            $this->initList($schema);
            $object = $this->lists->get($schema->id)->where('id', $id)->first();
            if (! is_null($object)) {
                return $object;
            }
        } catch (\Exception $e) {

        }
        
        return $this->model::get($schema, $id, $this->backend);
    }

    public function all(Schema $schema, $query = []): Collection
    {
        $this->initList($schema, $query);
        return $this->lists->get($schema->id);
    }

    public function allByPage(Schema $schema, $query = [])
    {
        $params = [];
        if (isset($query['page'])) {
            $params['take'] = config('objects.objects_per_page');
            $params['skip'] = ($query['page'] - 1) * config('objects.objects_per_page');
        }
        if (isset($query['search'])) {
            $params['search'] = $query['search'];
        }

        if (isset($query['order'])) {
            $params['order'] = $query['order'];
        }

        $list = $this->model::list($schema, $this->backend, $params);
        foreach ($list as $key => $item) {
            $list[$key] = $item;
        }
        return $list;
    }

    public function allWithLang(Schema $schema, $query = [], $language): Collection
    {
        return $this->model::listWithLangs($schema, $this->backend, $query, $language);
    }

    public function save(Schema $schema, $id, array $fields, string $language = null): Object
    {
        $this->initList($schema);
        $list = $this->lists->get($schema->id);

        $index = $list->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $findField = function ($name) use ($schema) {
            $result = [];
            foreach ($schema->fields as $field) {
                if ($field['name'] == $name) {
                    $result = $field;
                    break;
                }
            }
            return $result;
        };

        foreach ($fields as $key => $field) {
            $schemField = $findField($key);
            if (is_null($field)) {
                $fields[$key] = isset($schemField['multiple']) && $schemField['multiple'] ? [] : null;
            }
        }

        if ($index)
        {
            $object = $list->get($index);
        }
        else
        {
            $object = $this->model::get($schema, $id, $this->backend);
        }
        
        $object->save($fields, $this->backend, $language);
        $list->put($index, $object);

        $this->saveToCache($schema, $list);
        $this->lists->put($schema->id, $list);

        return $object;
    }

    public function create(Schema $schema, array $fields): Object
    {
        $object = $this->model::create($schema, $fields, $this->backend);
        //$this->initList($schema);
        //$this->lists->get($schema->id)->push($object);

        //$this->saveToCache($schema, $this->lists->get($schema->id));

        return $object;
    }

    public function delete(Schema $schema, $id): Object
    {
        $this->initList($schema);
        $object = $this->find($schema, $id)->delete($this->backend);

        $index = $this->lists->get($schema->id)->search(function ($item, $key) use ($id) {
            return $item->id == $id;
        });

        $this->lists->get($schema->id)->forget($index);
        $this->saveToCache($schema, $this->lists->get($schema->id));

        return $object;
    }

    public function count(Schema $schema, $query = []) {
        return Object::count($schema, $this->backend, $query);
    }

    public function search(Schema $schema, $query = []) {
        $result = new Collection();
        if ($query) {
            $result = Object::list($schema, $this->backend, $query);
        }
        return $result;
    }

    public function makeSearchQuery($schema) {
        $searchQuery = Object::makeSearchQuery($schema);
//        if ($searchQuery) {
//            $searchQuery = ['where' => json_encode($searchQuery)];
//        }
        return $searchQuery;
    }
}
