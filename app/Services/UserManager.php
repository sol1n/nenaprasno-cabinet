<?php

namespace App\Services;

use App\File;
use App\Settings;
use App\User;
use App\Backend;
use Illuminate\Support\Collection;
use App\Traits\Services\CacheableList;

class UserManager
{
    use CacheableList;

    private $token;

    protected $model = User::class;
    protected $cacheLifetime = 10;

    CONST USERS_PER_PAGE = 100;

    public function __construct()
    {
        $this->backend = app(Backend::Class);
        //$this->initList();
    }

    public function findWithProfiles(String $id): User
    {
        return $this->find($id)->getProfiles($this->backend);
    }

    public function count() {
        return User::getUsersAmount($this->backend);
    }


    public function all($query) {
        $params = [];
        if (isset($query['page'])) {
            $params['take'] = static::USERS_PER_PAGE;
            $params['skip'] = ($query['page'] - 1) * static::USERS_PER_PAGE;
        }
        if (isset($query['order'])) {
            $params['order'] = $query['order'];
        }
        $this->list = User::list($this->backend, $params);
        return $this->list;
    }

    /**
     * Redefine  CacheableList find, don't use cache
     * @param String $id
     * @return mixed
     */
    public function find(String $id)
    {
        $element = $this->model::get($id, $this->backend);
        return $element;
    }

    private function setProfiles($users) {
        $elements = new Collection;
        $profileSchemas = app(\App\Settings::class)->getProfileSchemas();
        if ($profileSchemas && count($users))
        {
            $query = ['where' => json_encode(['userId' => ['$in' => $users->pluck('id')]])];//
            foreach ($profileSchemas as $key => $schema)
            {
                $elements->put($key, app(ObjectManager::class)->all($schema, $query));
            }

            $users = $users->each(function($user) use ($elements){
                $user->profiles = new Collection;
                foreach ($elements as $key => $profiles)
                {
                    $fieldName = explode('.', $key)[1];
                    $schemaName = explode('.', $key)[0];
                    $index = $profiles->search(function($profile, $i) use ($fieldName, $user) {

                        if (isset($profile->fields[$fieldName]) && is_object($profile->fields[$fieldName]))
                        {
                            return isset($profile->fields[$fieldName]) && 
                                $profile->fields[$fieldName]->id == $user->id;
                        }
                        else
                        {
                            return isset($profile->fields[$fieldName]) && 
                                $profile->fields[$fieldName] == $user->id;
                        }
                    });

                    if ($index !== false)
                    {
                        $user->profiles->put($schemaName, ['object' => $profiles->get($index)]);
                    }
                }

            });
        }
        return $users;
    }

    public function allWithProfiles($query = []): Collection
    {
        $users = $this->all($query);
        $users = $this->setProfiles($users);
        
        return $users;
    }

    public function findMultipleWithProfiles($userIds = []) : Collection {
        $users = new Collection();
        $users = User::list($this->backend, ['where' => json_encode(['id' => ['$in' => $userIds]])]);
        if ($users) {
            $users = $this->setProfiles($users);
        }
        return $users;
    }

    public function search($query = []) {
        $result = new Collection();
        if ($query){
            $result = User::list($this->backend, $query);
        }
        return $result;
    }

    public function saveProfiles(String $id, array $profiles, string $language = null)
    {
        $result = [];
        $schemaManager = app(SchemaManager::class);
        $objectManager = app(ObjectManager::class);
        $linkFields = app(Settings::class)->getProfileSchemasLinkField();
        foreach ($profiles as $schemaCode => $objects) {
            $schema = $schemaManager->find($schemaCode);

            if (isset($objects['new'])) {
                $needCreate = false;
                $fields = $objects['new'];
                foreach ($fields as &$field) {
                    if ($field != null) {
                        $needCreate = true;
                    }
                }
                if ($needCreate) {
                    if (!isset($objects['link'])) {
                        $objects['link'] = isset($linkFields[$schemaCode]) ? $linkFields[$schemaCode] : '';
                    }
                    $fields[$objects['link']] = $id;

                    $object = $objectManager->create($schema, $fields);
                    $result[$schemaCode] = $object;
                }
            } else {
                foreach ($objects as $objectCode => $fields) {
                    $object = $objectManager->find($schema, $objectCode);
                    $object = $objectManager->save($schema, $object->id, $fields, $language);
                }
            }
        }
        return $result;
    }

    public function getMappedUsers(String $keyField = 'id', String $valueField = 'username'): Collection
    {
        $result = $this->all([])->mapWithKeys(function ($item) use($keyField, $valueField) {
            return [$item->{$keyField} => $item->{$valueField}];
        });
        return $result;
    }

}
