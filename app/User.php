<?php

namespace App;

use App\Role;
use App\Backend;
use App\Settings;
use App\Language;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Traits\Models\SchemaSearch;
use function GuzzleHttp\Psr7\build_query;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
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
use Illuminate\Support\Facades\Cookie;

class User
{
    use ModelActions, SchemaSearch;

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
        $client = new Client;

        try {
            $r = $client->get(
                $backend->url . 'users/' . $this->id . '/profiles',
                [
                'headers' => ['X-Appercode-Session-Token' => $backend->token]]
            );
        } catch (RequestException $e) {
            throw new UserGetProfilesException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $profiles = new Collection;

        if (count($json)) {
            foreach ($json as $profile) {
                $schema = app(SchemaManager::class)->find($profile['schemaId'])->withRelations();
                $object = app(ObjectManager::class)->find($schema, $profile['itemId']);
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
        } else {
            $this->profiles = $profiles;
        }

        return $this;
    }

    public static function build(array $data): User
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
        foreach ($languages as $long => $short) {
            if ($data['language'] == $short) {
                $user->language = collect(['short' => $short, 'long' => $long]);
            }
        }

        return $user;
    }

    public static function login(Backend $backend, array $credentials, Bool $storeSession = true): User
    {
        $client = new Client;

        try {
            $r = $client->post($backend->url . 'login', ['json' => [
              'username' => $credentials['login'],
              'password' => $credentials['password'],
              'installId' => '',
              'generateRefreshToken' => true
            ]]);
        } catch (RequestException $e) {
            throw new WrongCredentialsException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $user = new self();
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

    public function storeSession(Backend $backend, $language = ''): User
    {
        $lifetime = env('COOKIE_LIFETIME');
        if (!$lifetime) {
            $lifetime = config('auth.cookieLifetime');
        }
        Cookie::queue($backend->code . '-session-token', $this->token, $lifetime, '/', env('MAIN_SITE_SHARE_COOKIE'), false);
        Cookie::queue($backend->code . '-refresh-token', $this->refreshToken, $lifetime, '/', env('MAIN_SITE_SHARE_COOKIE'), false);
        Cookie::queue($backend->code . '-id', $this->id, $lifetime, '/', env('MAIN_SITE_SHARE_COOKIE'), false);
        Cookie::queue($backend->code . '-language', $language, $lifetime, '/', env('MAIN_SITE_SHARE_COOKIE'), false);
        return $this;
    }

    public function regenerate(Backend $backend, Bool $storeSession = true): User
    {
        $client = new Client;

        try {
            $r = $client->post(
                $backend->url . 'login/byToken',
                [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => '"' . $this->refreshToken . '"']
            );
        } catch (RequestException $e) {
            throw new WrongCredentialsException;
        }

        $json = json_decode($r->getBody()->getContents(), 1);

        $this->token = $json['sessionId'];
        $this->refreshToken = $json['refreshToken'];
        $this->id = $json['userId'];
        $language = Cookie::get($backend->code . '-language');

        if ($storeSession) {
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

    public static function forgetSession($backend)
    {
        Cookie::queue(Cookie::make($backend->code . '-session-token', null, -2628000, '/', env('MAIN_SITE_SHARE_COOKIE'), false));
        Cookie::queue(Cookie::make($backend->code . '-refresh-token', null, -2628000, '/', env('MAIN_SITE_SHARE_COOKIE'), false));
        Cookie::queue(Cookie::make($backend->code . '-id', null, -2628000, '/', env('MAIN_SITE_SHARE_COOKIE'), false));
        Cookie::queue(Cookie::make($backend->code . '-language', null, -2628000, '/', env('MAIN_SITE_SHARE_COOKIE'), false));
    }

    public static function list(Backend $backend, $params = []): Collection
    {
        $client = new Client;

        if (!$params) {
            $params['take'] = -1;
        }

        // $filter = static::getSearchFilter($backend);
        // if ($filter) {
        //     $params['where'] = json_encode($filter);
        //     $params['take'] = 10;
        // }

        $getParams = http_build_query($params);

        try {
            $r = $client->get($backend->url  . 'users/?' . $getParams, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new UsersListGetException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);
        
        $result = new Collection;
        foreach ($json as $raw) {
            $result->push(self::build($raw));
        }

        return $result;
    }

    public static function findMultiple(Backend $backend, $params) : Collection
    {
    }

    public static function getUsersAmount($backend, $params = [])
    {
        $result = 0;
        $client = new Client;
        $params['take'] = 0;
        $params['count'] = 'true';

        $query = http_build_query($params);

        try {
            $r = $client->get($backend->url  . 'users/?' . $query, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new UsersListGetException;
        };
        if ($r->getHeader('x-appercode-totalitems')) {
            $result = $r->getHeader('x-appercode-totalitems')[0];
        }

        return $result;
    }

    public static function get(String $id, Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->get($backend->url  . 'users/' . $id, ['headers' => [
                'X-Appercode-Session-Token' => $backend->token
            ]]);
        } catch (RequestException $e) {
            throw new UserNotFoundException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public function save(array $fields, Backend $backend): User
    {
        $client = new Client;
        $r = $client->put($backend->url  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'json' => $fields
            ]);
        try {
            $r = $client->put($backend->url  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
                'json' => $fields
            ]);
        } catch (RequestException $e) {
            throw new UserSaveException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public static function create(array $fields, Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->post($backend->url  . 'users/', [
                'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
                'json' => $fields
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                if ($e->getResponse()->getStatusCode() == 409) {
                    throw new UserCreateException('Conflict when user creation');
                }
            }
            throw new UserCreateException;
        };

        $json = json_decode($r->getBody()->getContents(), 1);

        return self::build($json);
    }

    public function delete(Backend $backend): User
    {
        $client = new Client;
        try {
            $r = $client->delete($backend->url  . 'users/' . $this->id, [
                'headers' => ['X-Appercode-Session-Token' => $backend->token],
            ]);
        } catch (RequestException $e) {
            throw new UserDeleteException;
        };

        return $this;
    }

    public function shortView(): String
    {
        if (isset(app(\App\Settings::class)->properties['usersShortView'])) {
            $template = app(\App\Settings::class)->properties['usersShortView'];
            if (isset($this->profiles) && (!$this->profiles->isEmpty())) {
                foreach ($this->profiles as $schema => $profile) {
                    if (isset($profile['object'])) {
                        foreach ($profile['object']->fields as $code => $value) {
                            if ((is_string($value) || is_numeric($value)) && mb_strpos($template, ":$schema.$code:") !== false) {
                                $template = str_replace(":$schema.$code:", $value, $template);
                            }
                        }
                    }
                }
                $template = str_replace(":id:", $this->id, $template);
                $template = str_replace(":username:", $this->username, $template);
                return $template ?? '';
            } else {
                return $this->username ?? '';
            }
        } else {
            return $this->username ?? '';
        }
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
        $client = new Client;
        $r = $client->put($backend->url  . 'users/' . $userId . '/changePassword', [
            'headers' => ['X-Appercode-Session-Token' => $backend->token],
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
            $fields['email'] = $data['email'];
        }

        $client = new Client;

        $r = $client->post($backend->url  . 'recover/sendRecoveryCode', [
            'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
            'json' => $fields
        ]);

        $json = json_decode($r->getBody()->getContents(), 1);

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
        $client = new Client;

        $r = $client->put($backend->url  . '/recover/changePassword', [
            'headers' => ['X-Appercode-Session-Token' => $backend->token ?? null],
            'json' => $data
        ]);

        $json = json_decode($r->getBody()->getContents(), 1);

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
        $client = new Client;

        try {
            $r = $client->post($backend->url . '/users/loginAndMerge', [
                'headers' => ['X-Appercode-Session-Token' => $sessionId ?? null],
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

        $json = json_decode($r->getBody()->getContents(), 1);

        $user = new self();
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
