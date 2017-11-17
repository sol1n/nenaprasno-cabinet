<?php

namespace App\Traits\Controllers;

use App\Schema;

trait FieldsOrder
{
	private function sortFields(Schema $schema)
    {
        $weights = [
            'title' => 10,
            'Title' => 10,
            'lastName' => 10,
            'firstName' => 15,
            'middleName' => 20,
            'company' => 75,
            'Group' => 30,
            'Category' => 30,
            'position' => 35,
            'email' => 40,
            'phoneNumber' => 40,
            'biography' => 50,
            'team' => 30,
            'status' => 25,
            'lectures' => 25,
            'companies' => 75,
            'footballTeam' => 80,
            'KVNTeam' => 80,
            'userId' => 100
        ];

        $sort = function($a, $b) use ($weights)
        {
            $weightA = isset($weights[$a['name']]) ? $weights[$a['name']] : 9999;
            $weightB = isset($weights[$b['name']]) ? $weights[$b['name']] : 9999;
            if ($weightA == $weightB)
            {
                return 0;
            }
            else
            {
                return ($weightA < $weightB) ? -1 : 1;
            }
        };
        usort($schema->fields, $sort);
    }
}