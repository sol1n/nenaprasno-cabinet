<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Exceptions\User\WrongCredentialsException;

class AuthController extends Controller
{
    const REDIRECT_KEY = 'redirect-after';
    
    public function ShowAuthForm()
    {
        return view('auth/login', [
        'selected' => 'login',
        'message' => session('login-error')
      ]);
    }

    public function ProcessLogin(Backend $backend, Request $request)
    {
        try {
            $user = User::login($backend, $request->all());
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Wrong сredentials data');
            return redirect('/login/');
        }

        if ($request->session()->has(self::REDIRECT_KEY)) {
            $redirectTo = session(self::REDIRECT_KEY);
            $request->session()->forget(self::REDIRECT_KEY);
            return redirect($redirectTo);
        }
        return redirect('/');
    }

    public function logout(Backend $backend)
    {
        $backend->logout();
        return redirect('/');
    }
}
