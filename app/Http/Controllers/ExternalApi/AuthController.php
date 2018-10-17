<?php


namespace App\Http\Controllers\ExternalApi;


use App\Backend;
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
}