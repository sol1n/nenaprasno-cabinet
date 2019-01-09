<?php


namespace App\Http\Controllers\ExternalApi;

use Validator;

use App\Backend;
use GuzzleHttp\Exception\ClientException;
use App\Exceptions\User\UserCreateException;
use App\Exceptions\User\WrongCredentialsException;
use App\User;
use Illuminate\Http\Request;

class AuthController
{
    public function signIn(Backend $backend, Request $request)
    {
        try {
            $credentials = json_decode($request->getContent(), true);

            $login = $credentials['login'];
            $password = $credentials['password'];

            if (!$login || !$password) {
                throw new WrongCredentialsException();
            }

            $user = User::login($backend, [
                'login' => $login,
                'password' => $password,
            ]);

            $userId = $user->id;
        } catch (WrongCredentialsException $e) {
            $userId = null;
        }

        return json_encode($userId);
    }

    public function signUp(Backend $backend, Request $request)
    {
        $rules = [
            'login' => 'required|email',
            'password' => 'required',
            'confirm' => 'required|same:password',
        ];

        $validator = Validator::make($request->all(), $rules);

        try {
            if ($validator->fails()) {
                throw new UserCreateException();
            }

            $user = User::create([
                'username' => $request->input('login'),
                'password' => $request->input('password'),
            ], $backend);

            $userId = $user->id;
        } catch (ClientException $e) {
            $userId = null;
        } catch (UserCreateException $e) {
            $userId = null;
        }

        return json_encode($userId);
    }
}