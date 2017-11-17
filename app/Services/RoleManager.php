<?php

namespace App\Services;

use App\Backend;
use App\Role;
use App\Traits\Services\CacheableList;

class RoleManager
{
    use CacheableList;

    private $backend;

    protected $model = Role::class;
    protected $cacheLifetime = 5;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
        $this->initList();
    }
}
