<?php

namespace App\Traits\Services;

use App\Backend;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait CacheableList
{
    private $list;

    private function getCacheTag(): String
    {
        return app(Backend::Class)->code . '-' . $this->model;
    }

    private function getFromCache()
    {
        if (Cache::has($this->getCacheTag()) && (env('APPERCODE_ENABLE_CACHING') == 1)) {
            return Cache::get($this->getCacheTag());
        } else {
            return null;
        }
    }

    private function saveToCache(Collection $data)
    {
        if (env('APPERCODE_ENABLE_CACHING') == 1)
        {
            Cache::put($this->getCacheTag(), $data, $this->cacheLifetime);
        }
    }

    private function initList()
    {
        $cache = $this->getFromCache();
        if (is_null($cache) && $this->backend->authorized()) {
            $cache = $this->model::list($this->backend);
            $this->saveToCache($cache);
        }
        $this->list = $cache;
    }

    public function find(String $id)
    {
        if ($this->list) {
            foreach ($this->list as $element) {
                if ($element->id == $id) {
                    return $element;
                }
            }
        }
        
        $element = $this->model::get($id, $this->backend);
        $this->list->push($element);
        $this->saveToCache($this->list);

        return $element;
    }

    public function create(array $data)
    {
        $element = $this->model::create($data, $this->backend);
        if ($this->list)
        {
            $this->list->push($element);
            $this->saveToCache($this->list);
        }

        return $element;
    }

    public function save(String $id, array $fields)
    {
        $element = $this->find($id)->save($fields, $this->backend);

        if (isset($this->list))
        {
            $index = $this->list->search(function ($item, $key) use ($element) {
                return $item->id == $element->id;
            });

            $this->list->put($index, $element);
            $this->saveToCache($this->list);
        }

        return $element;
    }

    public function delete(String $id)
    {
        $element = $this->find($id);

        if (isset($this->list))
        {
            $index = $this->list->search(function ($item, $key) use ($element) {
                return $item->id == $element->id;
            });
            $element = $this->list->get($index)->delete($this->backend);
            $this->list->forget($index);
            $this->saveToCache($this->list);
        }
        else
        {
            $element = $this->find($id);
            $element->delete($this->backend);
        }
        return $element;
    }

    public function all(): Collection
    {
        return $this->list;
    }
}
