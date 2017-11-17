<?php

namespace App\Traits\Models;

use App\Object;
use App\Schema;

trait SchemaSearch
{
    public static function makeSearchQuery(Schema $schema, $search = '') {
        $requestValue = '';
        $result = $types = [];
        foreach ($schema->fields as $field) {
            $types[$field['name']] = $field['type'];
        }
        $requestValue = $search;
        if (request()->has('search')){
            $requestValue = request()->get('search');
        }

        if ($requestValue) {
            $queryArray = [];
            foreach ($schema->getShortViewFields() as $field)
            {
                if ($field != 'id') {
                    if (!in_array($types[$field], ['String', 'Text', 'JSON'])) {
                        $queryArray[] = [$field => $requestValue];
                    } else {
                        $queryArray[] = [$field => ['$regex' => "(?i).*$requestValue.*"]];
                    }
                }
            }
            if (count($queryArray) > 1) {
                $queryArray = ['$or' => $queryArray];
            }
            elseif (count($queryArray) == 1){
                $queryArray = $queryArray[0];
            }
            $result = $queryArray;
        }
        return $result;
    }
}