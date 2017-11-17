<?php

namespace App;

use App\Backend;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\Role\RoleNotFoundException;
use App\Exceptions\Role\RoleGetListException;
use App\Exceptions\Role\RoleCreateException;
use App\Exceptions\Role\RoleSaveException;
use App\Exceptions\Backend\TokenExpiredException;
use Illuminate\Support\Collection;
use App\Traits\Controllers\ModelActions;

class Role
{
    use ModelActions;

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

    private static function fetch(Backend $backend): Array
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'roles', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 401) {
                    throw new TokenExpiredException;
                }
            }
            throw new RoleGetListException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        return $data;
    }

    public static function get(String $id, Backend $backend): Role
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url . 'roles/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new RoleNotFoundException;
        };

        $data = json_decode($r->getBody()->getContents(), 1);

        return new Role($data);
    }

    public static function list(Backend $backend): Collection
    {
        $result = new Collection;

        foreach (static::fetch($backend) as $raw) {
            $result->push(new Role($raw));
        }

        return $result;
    }

    public function delete(Backend $backend): Role
    {
        $client = new Client;
        try {
            $r = $client->delete($backend->url  . 'roles/' . $this->id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new RoleDeleteException;
        };

        return $this;
    }

    public static function create(Array $fields, Backend $backend): Role
    {
        $client = new Client;
        try {
            $r = $client->post($backend->url  . 'roles', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $fields]);
        } catch (RequestException $e) {
            throw new RoleCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return new Role($json);
    }

    public function save(Array $fields, Backend $backend): Role
    {
        $fields['id'] = $this->id;

        $client = new Client;
        try {
            $r = $client->put($backend->url  . 'roles', ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ], 'json' => $fields]);
        } catch (RequestException $e) {
            throw new RoleSaveException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        
        return new Role($json);
    }
}
