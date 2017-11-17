<?php

namespace App\Services;

use App\User;
use App\Schema;
use App\Backend;
use App\Traits\Services\CacheableList;

class SchemaManager
{
    use CacheableList;

    private $token;

    protected $model = Schema::class;
    protected $cacheLifetime = 5;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
        $this->initList();
    }

    public function fieldTypes(): array
    {
        $result = [
            'basic' => ['String', 'Integer', 'Double', 'Money', 'DateTime', 'Boolean', 'Text', 'Uuid', 'Json'],
            'refs' => ['ref Users', 'ref Files']
        ];

        foreach ($this->list as $schema)
        {
            $result['refs'][] = 'ref ' . $schema->id;
        }

        return $result;
    }
}
