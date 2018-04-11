<?php

namespace App;

use App\User;
use Illuminate\Http\Request;
use App\Exceptions\Backend\BackendNotExists;
use App\Exceptions\Backend\BackendNotSelected;
use App\Exceptions\Backend\BackendNoServerProvided;
use App\Exceptions\Backend\LogoutException;
use Illuminate\Support\Facades\Cookie;
use App\Traits\Models\AppercodeRequest;

class Backend
{
    use AppercodeRequest;

    public $base;
    public $code;
    public $url;

    private $user;

    private function check()
    {
      $response = self::request([
        'method' => 'GET',
        'url' => $this->url . 'app/appropriateConfiguration'
      ])->getBody()->getContents();

      if ($response !== 'null' && !is_array(json_decode($response, 1)))
      {
        throw new BackendNotExists;
      }
    }

    private function getBackendCode(): string
    {
      if (env('APPERCODE_DEFAULT_BACKEND', false)) {
        return env('APPERCODE_DEFAULT_BACKEND', false);
      }
      elseif (request()->path() != '/') {
        return explode('/', request()->path())[0];
      }
      else {
        throw new BackendNotSelected;
      }
    }

    private function getBackendServer(): string
    {
      if (env('APPERCODE_SERVER', false))
      {
        return env('APPERCODE_SERVER', false);
      }
      else
      {
        throw new BackendNoServerProvided;
      }
    }

    public function __construct(string $code = '', string $server = '')
    {
        $this->code = empty($code) ? $this->getBackendCode() : $code;
        $this->base = empty($server) ? $this->getBackendServer() : $server;
        $this->url = $this->base . $this->code . '/';

        $this->check();

        if (Cookie::get($this->code . '-session-token')) {
          $this->token = Cookie::get($this->code . '-session-token');
        }
    }

    public function logout()
    {
      if (isset($this->token))
      {
        self::request([
          'method' => 'GET',
          'headers' => ['X-Appercode-Session-Token' => $this->token],
          'url' => $this->url . 'logout',
        ]);

        User::forgetSession($this);
        $this->token = null;
      }
    
      return $this;
    }

    public function user()
    {
      return Cookie::get($this->code . '-id');
    }

    public function refreshToken()
    {
        return Cookie::get($this->code . '-refresh-token');
    }

    public function token()
    {
        return Cookie::get($this->code . '-session-token');
    }

    public function authorized()
    {
      return isset($this->token);
    }
}