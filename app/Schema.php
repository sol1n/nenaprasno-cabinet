<?php

namespace App;

use App\Backend;
use App\Services\UserManager;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Collection;
use App\Exceptions\Schema\SchemaSaveException;
use App\Exceptions\Schema\SchemaCreateException;
use App\Exceptions\Schema\SchemaDeleteException;
use App\Exceptions\Schema\SchemaListGetException;
use App\Exceptions\Schema\SchemaNotFoundException;
use App\Exceptions\Backend\TokenExpiredException;
use App\Traits\Controllers\ModelActions;


class Schema
{
    use ModelActions;

    public $id;
    public $title;
    public $fields;
    public $isDeferredDeletion;
    public $isLogged;

    protected function baseUrl(): String
    {
        return 'schemas';
    }

    public function getSingleUrl(): String
    {
        return '/' . app(Backend::Class)->code . '/' . $this->baseUrl() . '/' . $this->id . '/edit/';
    }

    private function prepareField(Array $field): Array
    {
        $field['localized'] = $field['localized'] == 'true';
        $field['multiple'] = isset($field['multiple']) && $field['multiple'] == 'true';
        $field['title'] = (String) $field['title'];

        if (isset($field['deleted'])){
            unset($field['deleted']);
        }
        if ($field['multiple'])
        {
            $field['type'] = "[" . $field['type'] . "]";
        }
        return $field;
    }

    private function getChanges(Array $data): Array
    {
        $changes = [];

        if (isset($data['viewData']))
        {
            $viewData = $data['viewData'];
            unset($data['viewData']);

            $this->viewData = $this->viewData ? (array) $this->viewData : [];

            foreach ($viewData as $key => $field)
            {
                $this->viewData[$key] = $field;
            }

            $changes[] = [
                'action' => 'Change',
                'key' => $this->id . '.viewData',
                'value' => $this->viewData,
            ];
        }

        if (isset($data['deletedFields']))
        {
            $deletedFields = $data['deletedFields'];
            unset($data['deletedFields']);

            foreach ($deletedFields as $fieldName => $fieldData)
            {
                $changes[] = [
                    'action' => 'Delete',
                    'key' => $this->id . '.' . $fieldName,
                ];
            }
        }

        if (isset($data['fields'])){
            $fields = $data['fields'];
            unset($data['fields']);
            foreach ($fields as $fieldName => &$fieldData){
                $field = [];
                $fieldData = $this->prepareField($fieldData);

                foreach ($this->fields as $key => $value){
                    if ($fieldName == $value['name']){
                        $field = $value;
                    }
                }

                if ($field['multiple'])
                {
                    $field['type'] = '[' . $field['type'] . ']';
                }

                foreach ($fieldData as $key => $value){
                    if ($field && $value != $field[$key])
                    {
                        if ($key == 'name')
                        {
                            $changes[] = [
                                'action' => 'Change',
                                'key' => $this->id . '.' . $fieldName ,
                                'value' => $value,
                            ];  
                        }                        
                        elseif ($key == 'multiple')
                        {
                            $newValue = $value ? '[' . $field['type'] . ']' : $field['type'];
                            $newFieldDate = $fieldData;
                            unset($newFieldDate['multiple']);
                            $newFieldDate['type'] = $newValue;
                            $changes[] = [
                                'action' => 'Delete',
                                'key' => $this->id . '.' . $fieldName ,
                            ];
                            $changes[] = [
                                'action' => 'New',
                                'key' => $this->id,
                                'value' => $newFieldDate
                            ];
                        }
                        elseif ($key == 'type')
                        {
                            $changes[] = [
                                'action' => 'Delete',
                                'key' => $this->id . '.' . $fieldName ,
                            ];
                            $changes[] = [
                                'action' => 'New',
                                'key' => $this->id,
                                'value' => $fieldData
                            ];
                        }
                        else{
                            $changes[] = [
                                'action' => 'Change',
                                'key' => $this->id . '.' . $fieldName . '.' . $key,
                                'value' => $value,
                            ];
                        }
                        
                    }
                }
            }
        }

        if (isset($data['newFields']))
        {
            $newFields = $data['newFields'];
            unset($data['newFields']);

            foreach ($newFields as $fieldName => $fieldData){
                $changes[] = [
                    'action' => 'New',
                    'key' => $this->id,
                    'value' => $this->prepareField($fieldData)
                ];
            }
        }
        
        foreach ($data as $name => $value){
            if ($value != $this->{$name}){
                $changes[] = [
                    'action' => 'Change',
                    'key' => $this->id . '.' . $name,
                    'value' => $value
                ];
            }
        }

        return $changes;
    }

    public static function create(Array $data, Backend $backend): Schema
    {
        $fields = [
            "id" => (String)$data['name'],
            "title" => (String)$data['title'],
            "isLogged" => $data['isLogged'],
            "isDeferredDeletion" => $data['isDeferredDeletion'],
            "viewData" => $data['viewData'],
            "fields" => []
        ];

        foreach ($data['fields'] as $field)
        {
            $type = (String)$field['type'];
            if ($field['multiple'] == 'true')
            {
                $type = "[$type]";
            }
            $fields['fields'][] = [
                "localized" => $field['localized'] == "true",
                "name" => (String)$field['name'],
                "type" => $type,
                "title" => (String)$field['title']
            ];
        }

        $client = new Client;
        try {
            $r = $client->post($backend->url  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $fields]);
        } catch (RequestException $e) {
            dd(json_encode($backend));
            throw new SchemaCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        return self::build($json);
    }

    public static function build(Array $data): Schema
    {
        $schema = new static();
        $schema->id = $data['id'];
        $schema->title = $data['title'] ? $data['title'] : $data['id'];
        $schema->fields = $data['fields'];
        $schema->createdAt = new Carbon($data['createdAt']);
        $schema->updatedAt = new Carbon($data['updatedAt']);
        $schema->isDeferredDeletion = $data['isDeferredDeletion'];
        $schema->isLogged = $data['isLogged'];
        $schema->viewData = is_array($data['viewData']) ? $data['viewData'] : json_decode($data['viewData']);

        foreach ($schema->fields as &$field)
        {
            if (mb_strpos($field['type'], '[') !== false)
            {
                $field['multiple'] = true;
                $field['type'] = preg_replace('/\[(.+)\]/', '\1', $field['type']);
            }
            else
            {
                $field['multiple'] = false;
            }
        }

        return $schema;
    }

    public static function list(Backend $backend): Collection
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'schemas/?take=-1', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        }
        catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new TokenExpiredException;
                }
            }
            throw new SchemaListGetException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        $result = new Collection;

        foreach ($json as $raw) {
            $result->push(static::build($raw));
        }

        return $result;
    }

    public static function get(String $id, Backend $backend): Schema
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'schemas/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new TokenExpiredException;
                }
            }
            throw new SchemaNotFoundException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return static::build($json);
    }

    public function save(Array $data, Backend $backend): Schema
    {
        $changes = $this->getChanges($data);

        $client = new Client;
        try {
            $r = $client->put($backend->url  . 'schemas', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $changes]);
        } catch (RequestException $e) {
            throw new SchemaSaveException;
        };

        return self::get($this->id, $backend);
    }

    public function delete(Backend $backend): Schema
    {
        $client = new Client;
        try {
            $r = $client->delete($backend->url  . 'schemas/' . $this->id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new SchemaDeleteException;
        };

        return $this;
    }

    private function getRelationUserCount() {
        return app(UserManager::class)->count();
    }

    private function getUserRelation($field)
    {
        if (! isset($this->relations['ref Users']))
        {
            $count = $this->getRelationUserCount();
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
                                $userIds[] = $obj->id;
                            }
                        }
                        $userIds = $this->fields[$field['name']];
                    }
                    elseif (is_object($this->fields[$field['name']])) {
                        $userIds = [$this->fields[$field['name']]->id];
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

    private function getFileRelation()
    {
        if (! isset($this->relations['ref Files']))
        {
            $this->relations['ref Files'] = [];    
        }
    }

    private function getObjectRelation(Schema $schema)
    {
        $index = 'ref ' . $schema->id;
        if (! isset($this->relations[$index]))
        {
            $elements = app(\App\Services\ObjectManager::Class)->search($schema, ['take' => -1]);
            $this->relations[$index] = $elements;    
        }
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
            $this->getFileRelation();
        }
        else
        {
            $schema = app(\App\Services\SchemaManager::Class)->find($code);
            $this->getObjectRelation($schema);
        }
    }

    public function withRelations()
    {
        $this->relations = [];

        foreach ($this->fields as $key => $field)
        {
            if (mb_strpos($field['type'], 'ref ') !== false)
            {
                $this->getRelation($field);
            }
        }

        return $this;
    }

    public function isFieldRef($field) {
        return mb_strpos($field['type'], 'ref ') !== false;
    }

    public function getRefName($field)
    {
        $result = '';
        if ($this->isFieldRef($field)){
            $result = str_replace('ref ', '', $field['type']);
        }
        return $result;
    }

    public function hasLocalizedFields() : bool {
        $result = false;
        foreach ($this->fields as $field) {
            if (isset($field['localized']) and $field['localized']) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    public function getViewData(): array
    {
        return isset($this->viewData) ? (array) $this->viewData : [];
    }

    public function getShortViewTemplate()
    {
        return isset($this->getViewData()['shortView']) ? $this->getViewData()['shortView'] : null;
    }

    public function getShortViewFields()
    {
        $viewFields = [];
        if ($template = $this->getShortViewTemplate())
        {
            foreach ($this->fields as $field) {
                if (
                    (is_string($field['name']) || is_numeric($field['name'])) && 
                    mb_strpos($template, ":".$field['name'].":") !== false
                ) {
                    $viewFields[] = $field['name'];
                }
            }
        }
        else {
            return ['id'];
        }
        return $viewFields;
    }

    public function isFieldMultiple($field) {
        $result = false;
        if (isset($field['multiple'])) {
            $result = $field['multiple'] ? true : false;
        }
        return $result;
    }

    public function getUserLinkField()
    {
        $userField = null;
        foreach ($this->fields as $field)
        {
            if ($field["type"] == 'ref Users')
            {
                $userField = $field;
            }
        }
        return $userField;
    }
}
