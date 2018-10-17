<?php

namespace App;

use App\Backend;
use App\Services\FileManager;
use App\Services\ObjectManager;
use App\Services\UserManager;
use App\Traits\Models\SchemaSearch;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Exceptions\Object\ObjectSaveException;
use App\Exceptions\Object\ObjectCreateException;
use App\Exceptions\Object\ObjectNotFoundException;
use App\Exceptions\User\UserNotFoundException;
use App\Traits\Controllers\ModelActions;
use App\Traits\Models\FieldsFormats;
use Mockery\Exception;
use Monolog\Handler\SyslogHandler;
use App\Traits\Models\AppercodeRequest;
use PhpParser\Node\Stmt\Catch_;


class AppObject
{
    use ModelActions, FieldsFormats, SchemaSearch, AppercodeRequest;

    public $fields;
    public $schema;

    protected function baseUrl(): String
    {
        return $this->schema->id;
    }

    public function save($data, Backend $backend, $language = null): AppObject
    {
        $this->fields = static::prepareRawData($data, $this->schema, true);

        $headers = [
            'X-Appercode-Session-Token' => $backend->token
        ];

        if ($language !== null)
        {
            $headers['X-Appercode-Language'] = $language;
        }

        self::request([
            'method' => 'PUT',
            'headers' => $headers,
            'json' => $this->fields,
            'url' => $backend->url . 'objects/' . $this->schema->id . '/' . $this->id,
        ]);

        $newTime = $this->requestUpdatedAt($backend);
        $this->updatedAt = new Carbon($newTime);

        return $this;
    }

    public function requestUpdatedAt(Backend $backend): String
    {
        $query = http_build_query(['include' => json_encode(['updatedAt'])]);
        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/' . $this->schema->id . '/' . $this->id . '?' . $query,
        ]);

        return $json['updatedAt'] ?? '';
    }

    public static function get(Schema $schema, $id, Backend $backend): AppObject
    {
        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/' . $schema->id . '/' . $id,
        ]);

        return static::build($schema, $json);
    }

    public static function create(Schema $schema, $fields, Backend $backend): AppObject
    {
        $json = self::jsonRequest([
            'method' => 'POST',
            'json' => self::prepareRawData($fields, $schema),
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/' . $schema->id,
        ]);

        return static::build($schema, $json);
    }

    public function delete(Backend $backend): AppObject
    {
        self::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/' . $this->schema->id . '/' . $this->id,
        ]);

        return $this;
    }

    public static function list(Schema $schema, Backend $backend, $query = [], $order = ''): Collection
    {
        $list = new Collection;

        if (isset($query['search'])) {

            $searchQuery = ['where' => json_encode($query['search'])];
            unset($query['search']);
            $query = array_merge($query, $searchQuery);
        }

        if ($order) {
            $query['order'] = $order;
        }

        if (!isset($query['take'])) {
            $query['take'] = -1;
        }

        $query = http_build_query($query);

        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/' . $schema->id . ($query ? '?' . $query : ''),
        ]);

        foreach ($json as $rawData) {
            $list->push(AppObject::build($schema, $rawData));
        }

        return $list;
    }

    public static function listWithLangs(Schema $schema, Backend $backend, $query = null, $language): Collection
    {
        $list = new Collection;

        if ($query) {
            $query = http_build_query($query);
        }
        else {
            $query = http_build_query(['take' => 200]);
        }

        $headers = ['X-Appercode-Session-Token' => $backend->token];
        $url = $backend->url . 'objects/' . $schema->id . ($query ? '?' . $query : '');

        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => $headers,
            'url' => $url,
        ]);

        $tempData = [];

        foreach ($json as $rawData) {
            $tempData[$rawData['id']] = $rawData;
        }

        $headers['X-Appercode-Language'] = $language;

        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => $headers,
            'url' => $url,
        ]);

        foreach ($json as $localizedRawData) {
            $id = $localizedRawData['id'];
            $localizedData = [
                $language => $localizedRawData
            ];
            $list->push(AppObject::build($schema, $tempData[$id], $localizedData));
        }

        return $list;
    }

    public static function count(Schema $schema, Backend $backend, $query = []) 
    {
        $searchQuery = [];
        if (isset($query['search'])) {
            $searchQuery = ['where' => json_encode($query['search'])];
        }

        $query = http_build_query(array_merge(['take' => 0, 'count' => 'true'], $searchQuery));

        return self::countRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'objects/' . $schema->id . '?' . $query,
        ]);
    }

    public static function build(Schema $schema, $data, $localizedData = null): AppObject
    {
        $object = new static();

        $object->id = $data['id'];
        $object->createdAt = new Carbon($data['createdAt']);
        $object->updatedAt = new Carbon($data['updatedAt']);

        $object->fields = self::prepareRawData($data, $schema);

        if (! is_null($localizedData))
        {
           $object->languages = [];
            foreach ($localizedData as $language => $data)
            {
                $object->languages[$language] = self::prepareRawData($data, $schema);
            } 
        }
        
        $object->schema = $schema;

        return $object;
    }

    private function getUserRelation($field)
    {
        if (! isset($this->relations['ref Users']))
        {
            $count = $this->getRelationUserCount($field);
            $users = [];
            if ($count > config('objects.ref_count_for_select')) {
                $userIds = [];
                if (isset($this->fields[$field['name']])) {
                    if (is_array($this->fields[$field['name']])) {
                        if (
                            isset($this->fields[$field['name']][0]) && 
                            is_object($this->fields[$field['name']][0]))
                        {
                            foreach ($this->fields[$field['name']] as $obj)
                            {
                                $userIds[] = (int)$obj->id;
                            }
                        }
                        $userIds = $this->fields[$field['name']];
                    }
                    elseif (is_object($this->fields[$field['name']])) {
                        $userIds = [(int)$this->fields[$field['name']]->id];
                    } else {
                        $userIds = [$this->fields[$field['name']]];
                    }
                }

                $users = $userIds ? app(\App\Services\UserManager::Class)->findMultipleWithProfiles($userIds) : [];
            }
            else {
                $users = app(\App\Services\UserManager::Class)->allWithProfiles();
            }
            $this->relations['ref Users'] = $users;
        }
    }

    private function getObjectRelation($field, Schema $schema)
    {
        $index = 'ref ' . $schema->id;
        if (! isset($this->relations[$index]))
        {
            $count = $this->getRelationObjectCount($field, $schema);
            if ($count > config('objects.ref_count_for_select')) {
                $objectIds = [];

                if (isset($this->fields[$field['name']]) && is_array($this->fields[$field['name']])) 
                {
                    $objectIds = $this->fields[$field['name']];
                } 
                elseif (isset($this->fields[$field['name']])) 
                {
                    $objectIds = [$this->fields[$field['name']]];
                }
                $elements = $objectIds ? app(\App\Services\ObjectManager::Class)->search($schema, ['take' => -1, 'where' => json_encode(['id' => ['$in' => $objectIds]])]) : [];
            }
            else 
            {
                $elements = app(\App\Services\ObjectManager::Class)->search($schema, ['take' => -1]);
            }
            $this->relations[$index] = $elements;
        }
    }

    private function getFileRelation($field)
    {
        $relation = [];
        if (isset($this->fields[$field['name']]) and $this->fields[$field['name']])  {
            $filesIds = [];
            if (is_array($this->fields[$field['name']])) {
                $filesIds = $this->fields[$field['name']];
            } else {
                $filesIds = [$this->fields[$field['name']]];
            }
            $files = File::tree(app(Backend::class));
            foreach ($filesIds as $filesId) {
                if ($file = $files->get($filesId, false)) {
                    $relation[] = $file;
                }
            }
            if ($relation and !$field['multiple']) {
                $relation = $relation[0];
            }
        }
        $this->relations['ref Files'][$field['name']] = $relation;
    }

    private function getRelation($field)
    {
        $code = str_replace('ref ', '', $field['type']);
        if ($code == 'Users')
        {
            $this->getUserRelation($field);
        }
        elseif ($code == 'Files')
        {
            $this->getFileRelation($field);
        }
        else
        {
            $schema = app(\App\Services\SchemaManager::Class)->find($code);
            $this->getObjectRelation($field, $schema);
        }
    }

    private function getRelationUserCount($field) {
        return app(UserManager::class)->count();
    }

    private function getRelationObjectCount($field, Schema $schema) {
        return app(ObjectManager::class)->count($schema);
    }

    public function getRelationCount($field) {
        if (mb_strpos($field['type'], 'ref') !== false) {
            $code = str_replace('ref ', '', $field['type']);
            if ($code == 'Users') {
                return $this->getRelationUserCount($field);
            }
            elseif ($code == 'Files') {
                return 1;
            }
            else {
                $schema = app(\App\Services\SchemaManager::Class)->find($code);
                return $this->getRelationObjectCount($field, $schema);
            }
        }
        return 0;
    }

    public function getFileField($field)
    {
        return isset($this->relations[$field['type']][$field['name']]) ?? ($field->multiple ? [] : null);
    }

    public function withRelations(): AppObject
    {
        $this->relations = [];

        foreach ($this->schema->fields as $field)
        {
            if (mb_strpos($field['type'], 'ref ') !== false)
            {
                $this->getRelation($field);
            }
        }
        return $this;
    }

    public function shortView(): String
    {
        if ($template = $this->schema->getShortViewTemplate())
        {
            $isFilled = false;
            foreach ($this->fields as $key => $field){
                if ((is_string($field) || is_numeric($field)) && mb_strpos($template, ":$key:") !== false)
                {
                    $template = str_replace(":$key:", $field, $template);
                    if (!$isFilled) $isFilled = true;
                }
            }
            if ($isFilled) {
                $template = str_replace(":id:", $this->id, $template);
            }
            else{
                $template = '';
            }

            return $template ? $template : $this->id;
        }
        else
        {
            return $this->id;
        }
    }

    public function hasLocalizedFields() : bool {
        $result = false;
        foreach ($this->schema->fields as $field) {
            if (isset($field['localized']) and $field['localized']) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    public static function getUserProfiles(Backend $backend, Schema $schema, $users = [])
    {
        $result = [];
        if ($users) {
            $query = http_build_query(['where' => json_encode(['userId' => ['$in' => $users]])]);

            $json = self::jsonRequest([
                'method' => 'GET',
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'url' => $backend->url . 'objects/' . $schema->id . '?' . $query,
            ]);

            foreach ($json as $item) {
                $result[] = static::build($schema, $item);
            }

        }
        return collect($result);
    }

    public function hasChildren()
    {
        return boolval(app(ObjectManager::class)->count($this->schema, ['search' => ['parentId' => $this->id]]));
    }

    public static function nessecaryFields($isHierarchy = false)
    {
        $fields = ['id', 'createdAt', 'updatedAt'];
        if ($isHierarchy) {
            $fields[] = 'parentId';
        }
        return $fields;
    }

    /**
     * Returns updated at in original format
     * @return mixed
     */
    public function updatedAtRaw() {
        return $this->updatedAt->format("Y-m-d\TH:i:s.uP");
    }
}
