<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use App\Exceptions\Role\RoleNotFoundException;
use App\Exceptions\Role\RoleGetListException;
use App\Exceptions\Role\RoleCreateException;
use App\Exceptions\Role\RoleSaveException;
use Illuminate\Support\Collection;
use App\Traits\Controllers\ModelActions;
use App\Traits\Models\AppercodeRequest;

class Role
{
    use ModelActions, AppercodeRequest;

    const ADMIN = 'Administrator';

    protected function baseUrl(): String
    {
        return 'roles';
    } 

    private function __construct(Array $data)
    {
        $this->id = $data['id'];
        $this->baseRoleId = $data['baseRoleId'];
        $this->rights = $data['rights'];
        $this->createdAt = new Carbon($data['createdAt']);
        $this->updatedAt = new Carbon($data['updatedAt']);
        $this->isDeleted = $data['isDeleted'];

        return $this;
    }

    private static function getAdmin() {
        return new Role([
            'id' => static::ADMIN,
            'baseRoleId' => null,
            'rights' => [
                  "adds" => [],
                  "total" => []
            ],
            "createdAt" => "2018-02-20T07:12:08.830Z",
            "updatedAt" => "2018-02-20T07:12:08.831Z",
            "isDeleted" => false
        ]);
    }

    private static function fetch(Backend $backend): Array
    {
        return self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'roles'
        ]);
    }

    public static function get(String $id, Backend $backend): Role
    {
        if ($id == static::ADMIN) {
            return static::getAdmin();
        }
        $data = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'roles/' . $id
        ]);

        return new Role($data);
    }

    public static function list(Backend $backend): Collection
    {
        $result = new Collection;

        $result->push(static::getAdmin());

        foreach (static::fetch($backend) as $raw) {
            $result->push(new Role($raw));
        }

        return $result;
    }

    public function delete(Backend $backend): Role
    {
        self::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'roles/' . $this->id
        ]);

        return $this;
    }

    public static function create(Array $fields, Backend $backend): Role
    {
        $data = self::jsonRequest([
            'method' => 'POST',
            'json' => $fields,
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'roles'
        ]);

        return new Role($data);
    }

    public function save(Array $fields, Backend $backend): Role
    {
        $data = self::jsonRequest([
            'method' => 'PUT',
            'json' => array_merge($fields, ['id' => $this->id]),
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'roles'
        ]);
        
        return new Role($data);
    }
}
