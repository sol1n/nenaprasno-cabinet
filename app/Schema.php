<?php

namespace App;

use App\Backend;
use App\Services\ObjectManager;
use App\Services\UserManager;
use App\Traits\Models\ViewData;
use App\Helpers\Schema\ViewData as ViewDataHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Exceptions\Schema\SchemaSaveException;
use App\Exceptions\Schema\SchemaCreateException;
use App\Exceptions\Schema\SchemaDeleteException;
use App\Exceptions\Schema\SchemaListGetException;
use App\Exceptions\Schema\SchemaNotFoundException;
use App\Traits\Controllers\ModelActions;
use App\Traits\Models\AppercodeRequest;
use Illuminate\Support\Facades\Cookie;

class Schema
{
    use ModelActions, AppercodeRequest, ViewData;

    public $id;
    public $title;
    public $fields;
    public $isDeferredDeletion;
    public $isLogged;
    /**
     * Used as a filter for get user relation
     * @var array
     */
    public $filterUsers;
    public $viewDataHelper;

    const COLLECTION_TYPES = [
        'areaCatalogItem',
        'baseItem',
        'eventCatalogItem',
        'feedbackMessage',
        'htmlPage',
        'newsCatalogItem',
        'photoCatalogItem',
        'tag',
        'userProfile',
        'videoCatalogItem'
    ];

    CONST PARENT_FIELD_NAME = 'parentId';

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
//                var_dump(($fieldData));die();
                $fieldData = $this->prepareField($fieldData);

                foreach ($this->fields as $key => $value){
                    if ($fieldName == $value['name']){
                        $field = $value;
                    }
                }

                if ($field and $field['multiple'])
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
            "title" => (String)$data['title'] ?? '',
            "isLogged" => $data['isLogged'] ?? false,
            "isDeferredDeletion" => $data['isDeferredDeletion'] ?? false,
            "viewData" => $data['viewData'] ?? [],
            "fields" => []
        ];

        foreach ($data['newFields'] as $field)
        {
            $type = (String)$field['type'];
            if (isset($field['multiple']) and $field['multiple'] == 'true')
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



        $json = self::jsonRequest([
            'method' => 'POST',
            'json' => $fields,
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'schemas',
        ]);

        return self::build($json);
    }

    public function __construct()
    {
        $this->viewDataHelper = new ViewDataHelper($this);
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
        $result = new Collection;

        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'schemas/?take=-1',
        ]);

        foreach ($json as $raw) {
            $result->push(static::build($raw));
        }

        return $result;
    }

    public static function get(String $id, Backend $backend): Schema
    {
        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'schemas/' . $id,
        ]);

        return static::build($json);
    }

    public function save(Array $data, Backend $backend): Schema
    {
        $changes = $this->getChanges($data);

        self::request([
            'method' => 'PUT',
            'json' => $changes,
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'schemas',
        ]);

        return self::get($this->id, $backend);
    }

    public function delete(Backend $backend): Schema
    {
        self::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'schemas/' . $this->id,
        ]);

        return $this;
    }

    private function getRelationUserCount() {
        return app(UserManager::class)->count();
    }

    private function getUserRelation()
    {
        if (! isset($this->relations['ref Users']))
        {
            $query = [];
            if ($this->filterUsers) {
                $query['where'] = json_encode(['id' => ['$in' => $this->filterUsers]]);
            } else {
                $query['take'] = config('objects.ref_count_for_select');
            }

            $profileSchemas = app(\App\Settings::class)->getProfileSchemas();

            $this->relations['ref Users'] = app(UserManager::class)->allWithProfiles($query);
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
            $this->getUserRelation();
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

    public function getLocalizedFields() : array
    {
        $result = [];
        foreach ($this->fields as $field) {
            if (isset($field['localized']) and $field['localized']) {
                $result[$field['name']] = $field;
            }
        }
        return $result;
    }

    public function getViewData(): array
    {
        return isset($this->viewData) ? (array) $this->viewData : [];
    }

    public function getViewDataItem($key)
    {
        return isset($this->viewData[$key]) ? $this->viewData[$key] : '';
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

    public static function getUsrProfileSchema(Backend $backend)
    {
        return static::get('UserProfiles', $backend);
    }

    public function hideInLeftMenu() : bool
    {
        $result = false;
        if (isset($this->viewData['hideInLeftMenu'])) {
            $result = $this->viewData['hideInLeftMenu'];
        }
        return $result;
    }

    public function collectionType() : string
    {
        $result = '';
        if (isset($this->viewData['collectionType'])) {
            $result = $this->viewData['collectionType'];
        }
        return $result;
    }

    public function isHierarchical()
    {
        $result = false;
        foreach ($this->fields as $field) {
            if ($field['name'] == static::PARENT_FIELD_NAME) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    public static function getFieldOptions()
    {
        return config('viewdata.schema_field_options');
    }

    public static function getLocale()
    {
        $locale = __('schema');
        $locale['delete'] = __('common.delete');
        $locale['close'] = __('common.close');
        return $locale;
    }

    public static function templates($name = false)
    {
        $result = [];
        $getContent = function($fileName, $withRaw = true) {
            $content = [];
            $rawData = file_get_contents($fileName);
            $content = json_decode($rawData, 1);
            if ($withRaw) {
                $content['raw_data'] = $rawData;
            }
            return $content;
        };
        $dirName = resource_path() . '/schema_templates';
        if (is_dir($dirName)) {
            if (!$name) {
                $files = scandir($dirName);
                foreach ($files as $file) {
                    if ($file != '.' and $file != '..') {
                        $content = $getContent($dirName . '/' . $file);
                        if ($content and isset($content['viewData']['shortView'])) {
                            $result[$file] = $content;
                        }
                    }
                }
            }
            else {
                $result = $getContent($dirName . '/' . $name, false);
            }
        }
        return $result;
    }

    /**
     * Returns true if field is a parent link field
     * @param $field
     * @return bool
     */
    public function isParentLinkField($field) {
        return $field['name'] == static::PARENT_FIELD_NAME;
    }

    public function getTimezoneForField($fieldName)
    {
        $timezone = Cookie::get('appercode-timezone') ?? 'UTC';
        $useUTC = $this->viewDataHelper->getFieldSettingValue($fieldName, 'useUTC');
        if (!$useUTC) {
            $timezone = 'UTC';
        }
        return $timezone;
    }

}
