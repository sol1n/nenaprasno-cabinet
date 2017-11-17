<?php

namespace App\Helpers;

use App\Backend;
use App\Schema;
use App\Services\FileManager;
use App\Services\ObjectManager;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SchemaFilterBuilder
{

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var Backend
     */
    private $backend;

    public $filterFields;

    public function __construct(Schema $schema, Backend $backend)
    {
        $this->schema = $schema;
        $this->backend = $backend;
        $this->buildFields();
    }

    private function buildFields() {
        $fields = [
            [
                'name' => 'id',
                'title' => 'id',
                'values' => false,
                'type' => ''
            ]
        ];
        foreach ($this->schema->fields as $field) {
            $values = false;
            $code = $this->schema->getRefName($field);
            if ($this->schema->isFieldRef($field)){
                if ($code == 'Files') {
//                    $files = app(FileManager::class)->all();
//                    foreach ($files as $file) {
//                        $values[$file->id] = $file->name ?? $file->id;
//                    }
                    $values = '';
                }
                elseif ($code == 'Users') {

                }
                else {
                    $schema = app(\App\Services\SchemaManager::Class)->find($code);
                    $refCount = app(ObjectManager::class)->count($schema);
                    if ($refCount <= config('objects.ref_count_for_select')) {
                        $objects = app(ObjectManager::class)->all($schema);
                        foreach ($objects as $object) {
                            $values[$object->id] = $object->shortView();
                        }
                    }
                }
            }
            if ($code != 'Files') {
                $fields[] = [
                    'name' => $field['name'],
                    'title' => $field['title'] ? $field['title'] : $field['name'],
                    'values' => $values,
                    'type' => $field['type']
                ];
            }
        }
        $this->filterFields = $fields;
        return true;
    }

    public function getFilterQuery(Request $request)
    {
        $result = [];
        $fields = collect($this->schema->fields)->mapWithKeys(function($item) {
            return [$item['name'] => $item];
        });

        $getFilter = function($field, $value) {
          $result = [];
          if (in_array($field['type'], ['Text',  'String', 'Json'])) {
              $result = ['$regex' => "(?i).*$value.*"];
          }
          elseif($field['type'] == 'DateTime'){
             $date = new Carbon($value);
             $result = $date->toAtomString();
          }
          else {
              $result = $value;
          }
          return $result;
        };

        if ($request->has('filter')) {
            $filters = $request->input('filter');
            foreach ($filters as $filterName => $filter) {
                if ($filter) {
                    if ($filterName == 'id') {
                        $result['id'] = $filter;
                    }
                    else {
                        if ($field = $fields->get($filterName)) {
                            if ($field['multiple']) {
                                $result[$filterName] = [
                                    '$contains' => [$filter]
                                ];
                            } else {
                                $result[$filterName] = $getFilter($field, $filter);
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }
}