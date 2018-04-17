<?php

namespace App;

use Exception;
use App\Role;
use App\Backend;
use App\Settings;
use App\Language;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Traits\Models\SchemaSearch;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Exception\RequestException;
use App\Exceptions\User\UnAuthorizedException;
use App\Exceptions\User\WrongCredentialsException;
use App\Exceptions\User\UsersListGetException;
use App\Exceptions\User\UserNotFoundException;
use App\Exceptions\User\UserSaveException;
use App\Exceptions\User\UserCreateException;
use App\Exceptions\User\UserGetProfilesException;
use App\Traits\Controllers\ModelActions;
use Illuminate\Support\Facades\App;
use App\Traits\Models\AppercodeRequest;
use Illuminate\Support\Facades\Cookie;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class User
{
    use ModelActions, SchemaSearch, AppercodeRequest;

    private $token;
    private $refreshToken;

    protected function baseUrl(): String
    {
        return 'users';
    } 

    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        } else {
            throw new UnAuthorizedException;
        }
    }

    public function refreshToken(): string
    {
        if ($this->refreshToken !== null) { 
            return $this->refreshToken; 
        } else { 
            throw new UnAuthorizedException; 
        } 
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function setRefreshToken($token)
    {
        $this->refreshToken = $token;
    }

    public function isAdmin(): bool
    {
        return isset($user->roleId) && $user->roleId == Role::ADMIN;
    }

    public function __construct()
    {
        $backend = app(Backend::Class);
        if (Cookie::get($backend->code . '-session-token')) {
            $this->token = Cookie::get($backend->code . '-session-token');
        }
        if (Cookie::get($backend->code . '-refresh-token')) {
            $this->refreshToken = Cookie::get($backend->code .'-refresh-token');
        }
    }

    public function getProfiles(Backend $backend)
    {
        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url . 'users/' . $this->id . '/profiles'
        ]);

        $profiles = new Collection;

        if (count($json))
        {
            foreach($json as $profile)
            {
                $schema = app(SchemaManager::Class)->find($profile['schemaId'])->withRelations();
                $object = app(ObjectManager::Class)->find($schema, $profile['itemId']);
                $profiles->put($schema->id, ['object' => $object->withRelations(), 'code' => $schema->id]);
            }
        }

        $profileSchemas = app(Settings::class)->getProfileSchemas();

        if ($profileSchemas) {
            foreach ($profileSchemas as $key => $schema) {
                $id = $schema->id;
                $index = $profiles->search(function ($item, $key) use ($id) {
                    return isset($item['object']) && $item['object']->schema->id == $id;
                });

                if ($index === false) {
                    $schema->link = explode('.', $key)[1];
                    $profiles->put($schema->id, ['schema' => $schema->withRelations(), 'code' => $schema->id]);
                }
            }

            $this->profiles = $profiles->sortBy('code');
        }
        else{
            $this->profiles = $profiles;
        }

        return $this;
    }

    public static function build(Array $data): User
    {
        $user = new self();
        $user->token = null;
        $user->refreshToken = null;

        $user->id = $data['id'];
        $user->username = $data['username'];
        $user->roleId = $data['roleId'];
        $user->isAnonymous = $data['isAnonymous'];
        $user->createdAt = new Carbon($data['createdAt']);
        $user->updatedAt = new Carbon($data['updatedAt']);

        $user->language = null;
        $languages = Language::list();
        foreach ($languages as $long => $short)
        {
            if ($data['language'] == $short)
            {
                $user->language = collect(['short' => $short, 'long' => $long]);
            }
        }

        return $user;
    }

    public static function login(Backend $backend, Array $credentials, Bool $storeSession = true): User
    {
        $data = [
          'username' => $credentials['login'],
          'password' => $credentials['password'],
          'installId' => '',
          'generateRefreshToken' => true
        ];

        $timezone = $credentials['timezone'] ?? '';
        if ($timezone) {
            $timezone = timezone_name_from_abbr('', intval($timezone * 60), false);
        }

        try {
            $json = self::jsonRequest([
                'method' => 'POST',
                'json' => $data,
                'url' => $backend->url . 'login',
            ], false);
        } catch (Exception $e) {
            if ($e instanceof ClientException && self::checkTokenExpiration($e)) {
                throw new WrongCredentialsException;
            }
        }

        $user = new self();
        $user->id = $json['userId'];
        $user->roleId = $json['roleId'];
        $user->token = $json['sessionId'];
        $user->username = $credentials['login'];
        $user->refreshToken = $json['refreshToken'];

        $backend->token = $json['sessionId'];

        if ($storeSession)
        {
            $user->storeSession($backend);
        }

        return $user;
    }

    public function storeSession(Backend $backend, $language = '', $timezone = ''): User
    {
        $lifetime = env('COOKIE_LIFETIME');
        if (!$lifetime) {
            $lifetime = config('auth.cookieLifetime');
        }
        Cookie::queue($backend->code . '-session-token', $this->token, $lifetime);
        Cookie::queue($backend->code . '-refresh-token', $this->refreshToken, $lifetime);
        Cookie::queue($backend->code . '-id', $this->id, $lifetime);
        Cookie::queue($backend->code . '-language', $language, $lifetime);
        return $this;
    }

    public static function forgetSession($backend)
    {
        Cookie::forget($backend->code . '-session-token');
        Cookie::forget($backend->code . '-refresh-token');
        Cookie::forget($backend->code . '-id');
        Cookie::forget($backend->code . '-language');
    }

    public function regenerate(Backend $backend, Bool $storeSession = true)
    {
        $regenerationToken = isset($backend->refreshToken) ? $backend->refreshToken : $this->refreshToken;

        $json = self::jsonRequest([
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '"' . $regenerationToken . '"',
            'url' => $backend->url . 'login/byToken'
        ], false);

        $this->id = $json['userId'];
        $this->token = $json['sessionId'];
        $this->refreshToken = $json['refreshToken'];

        $language = Cookie::get($backend->code . '-language');

        if ($storeSession)
        {
            $this->storeSession($backend, $language);
        }

        return $this;
    }

    public static function getSearchFilter(Backend $backend)
    {
        $result = '';
        $schemas = app(Settings::class)->getProfileSchemas();
        $schemasData = [];
        $getUsers = function ($list) {
            $result = [];
            foreach ($list as $item) {
                if (isset($item->fields['userId'])) {
                    $result[] = $item->fields['userId'];
                }
            }
            return $result;
        };
        foreach ($schemas as $schema) {
            $schemasData = array_merge($schemasData, $getUsers(Object::list($schema, $backend)));
        }

        if ($schemasData) {
            $result = ['id' => [
                '$in' => $schemasData
            ]];
        }

        return $result;

    }

    public static function list(Backend $backend, $params = ['take' => -1]): Collection
    {
        $result = new Collection;

        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'users/?' . http_build_query($params)
        ]);
        
        foreach ($json as $raw)
        {
            $result->push(self::build($raw));
        }

        return $result;
    }

    public static function findMultiple(Backend $backend, $params) : Collection
    {

    }

    public static function getUsersAmount($backend, $params = []) {
        if (!$params) {
            $params['take'] = 0;
        }

        $params['count'] = 'true';

        return self::countRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'users/?' . http_build_query($params)
        ]);
    }

    public static function get(String $id, Backend $backend): User
    {
        $json = self::jsonRequest([
            'method' => 'GET',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'users/' . $id
        ]);

        return self::build($json);
    }

    public function save(Array $fields, Backend $backend): User
    {
        $json = self::jsonRequest([
            'method' => 'PUT',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'json' => $fields,
            'url' => $backend->url  . 'users/' . $this->id
        ]);

        return self::build($json);
    }

    public static function create(Array $fields, Backend $backend): User
    {
        $json = self::jsonRequest([
            'method' => 'POST',
            'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
            'json' => $fields,
            'url' => $backend->url  . 'users/'
        ]);

        return self::build($json);
    }

    public function delete(Backend $backend): User
    {
        self::request([
            'method' => 'DELETE',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'users/' . $this->id
        ]);

        return $this;
    }

    public function shortView(): String
    {
        if (isset(app(\App\Settings::Class)->properties['usersShortView']))
        {
            $template = app(\App\Settings::Class)->properties['usersShortView'];

            if (isset($this->profiles) && (!$this->profiles->isEmpty()))
            {
                foreach ($this->profiles as $schema => $profile){

                    if (!$this->profiles->isEmpty() and isset($this->profiles[$schema]['object']))
                    {
                        $profile = $this->profiles[$schema]['object'];

                        foreach ($profile->fields as $code => $value)
                        {
                            $replace = is_null($value) ? '' : $value;
                            if ((is_string($replace) || is_numeric($replace)) && mb_strpos($template, ":$schema.$code:") !== false)
                            {
                                $template = str_replace(":$schema.$code:", $replace, $template);
                            }
                        }
                    }
                }
                $template = str_replace(":id:", $this->id, $template);
                $template = str_replace(":username:", $this->username, $template);
                return $template;
            }
            else
            {
                return $this->username;
            }
        }
        else
        {
            if (isset($this->profiles) && (!$this->profiles->isEmpty())) {
                $shortView = '';
                foreach ($this->profiles as $schema => $profile) {
                    $profile = $this->profiles[$schema]['object'];
                    $shortView = $profile->shortView();
                    if ($shortView) {
                        break;
                    }
                }
                return $shortView ? $shortView : $this->username;
            }
            else {
                return $this->username;
            }
        }
    }

    public static function searchByProfile($backend, $query, $schemaId = 'UserProfiles')
    {
        $result = [];
        $condition = [];
        if (is_array($query)) {
            $condition = $query;
        }
        else {
            $condition[] = ['firstName' => ['$regex' => "(?i).*$query.*"]];
            $condition[] = ['lastName' => ['$regex' => "(?i).*$query.*"]];
            $condition[] = ['position' => ['$regex' => "(?i).*$query.*"]];
            $condition[] = ['company' => ['$regex' => "(?i).*$query.*"]];
            $condition[] = ['email' => ['$regex' => "(?i).*$query.*"]];
            $condition[] = ['phoneNumber' => ['$regex' => "(?i).*$query.*"]];
        }
        if (count($condition) > 1) {
            $param['search'] = ['$or' => $condition];
        }
        else {
            $param['search'] = $condition;
        }
        $schema = Schema::get($schemaId, $backend);
        $profiles = Object::list($schema, $backend, $param);
        foreach ($profiles as $profile) {
            if (!in_array($profile->fields['userId'], $result)) {
                $result[] = $profile->fields['userId'];
            }
        }
        return $result;
    }


    public static function getLanguage(backend $backend)
    {
        $defaultLanguage = env('DEFAULT_LANGUAGE');
        $language = Cookie::get($backend->code . '-language');
        return $language ? $language : $defaultLanguage;
    }

    public function getUserProfile() {
        $result = [];
        if (isset($this->profiles['UserProfiles']['object'])) {
            $result = $this->profiles['UserProfiles']['object'];
        }
        return $result;
    }

    private function getProfile()
    {
        $profiles = self::jsonRequest([
            'url' => app(Backend::Class)->url . "/users/{$this->id}/profiles",
            'method' => 'GET',
            'headers' => [
                'X-Appercode-Session-Token' => app(Backend::Class)->token
            ]
        ]);
        if (is_array($profiles) && count($profiles) && isset($profiles[0])) {
            try {
                $schema = app(SchemaManager::class)->find($profiles[0]['schemaId']);
                return app(ObjectManager::class)->find($schema, $profiles[0]['itemId']);
            } catch (ClientException $e) {
                Log::debug('Schema list exception');
                Log::debug('User: ' . print_r($this, 1));
                return null;
            }
            
        } else {
            return null;
        }
    }

    public function getProfileName()
    {
        $profileName = '';

        if (isset($this->username)) {
            $profileName = $this->username;
        }
        $profile = $this->getProfile();
        if (!is_null($profile)) {
            if (isset($profile->fields['email']) && !empty($profile->fields['email'])) {
                $profileName = $profile->fields['email'];
            }
            if (isset($profile->fields['firstName']) && !empty($profile->fields['email'])) {
                $profileName = $profile->fields['firstName'];
            }
        }

        return $profileName;
    }   

    /** 
     * Change current user`s password via non-administrative session 
     * @param  Backend $backend 
     * @param  $userId 
     * @param  array $data contains "oldPassword" & "newPassword" values 
     * @return 
     */ 
    public static function changePassword(Backend $backend, $userId, $data) 
    { 
        self::request([
            'method' => 'PUT',
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
            'url' => $backend->url  . 'users/' . $userId . '/changePassword',
            'json' => $data 
        ]);
 
        return true; 
    }

    public static function createRecoverCode(Backend $backend, array $data)
    {
        $fields = [];

        if (isset($data['username']) and $data['username']) {
            $fields['username'] = $data['username'];
        }
        if (isset($data['email']) and $data['email']) {
            $fields['username'] = $data['email'];
        }

        self::request([
            'method' => 'POST',
            'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
            'url' => $backend->url  . 'recover/sendRecoveryCode',
            'json' => $fields
        ]);

        return true;
    }

    /**
     * Restore password
     * $data has to contain username, password ad recoveryCode
     * @param \App\Backend $backend
     * @param $data
     * @return bool
     */
    public static function restorePassword(Backend $backend, $data)
    {
        self::request([
            'method' => 'PUT',
            'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
            'url' => $backend->url  . '/recover/changePassword',
            'json' => $data
        ]);

        return true;
    }

    /**
     * @param \App\Backend $backend
     * @param $sessionId
     * @param $data
     * @param bool $storeSession
     * @return User
     * @throws WrongCredentialsException
     */
    public static function loginAndMerge(Backend $backend, $sessionId, array $data, $storeSession = true)
    {
        try {
            $json = self::jsonRequest([
                'method' => 'POST',
                'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
                'url' => $backend->url . '/users/loginAndMerge',
                'json' => [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'installId' => '',
                    'generateRefreshToken' => true
                ]
            ]);
        } catch (RequestException $e) {
            throw new WrongCredentialsException;
        }

        $user = new self();
        $user->username = $data['username'];
        $user->id = $json['userId'];
        $user->roleId = $json['roleId'];
        $user->token = $json['sessionId'];
        $user->refreshToken = $json['refreshToken'];

        $backend->token = $json['sessionId'];

        if ($storeSession) {
            $user->storeSession($backend);
        }

        return $user;
    }

}
