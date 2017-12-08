<?php

namespace App\Http\Controllers;

use App\User;
use App\Backend;
use Illuminate\Http\Request;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use Illuminate\Support\MessageBag;
use App\Exceptions\User\UserCreateException;
use App\Exceptions\User\WrongCredentialsException;

class AuthController extends Controller
{
    const REDIRECT_KEY = 'redirect-after';
    const PROFILE_SCHEMA_NAME = 'UserProfiles';
    
    public function ShowAuthForm()
    {
        return view('auth/login', [
        'selected' => 'login',
        'message' => session('login-error')
      ]);
    }

    public function ShowRegistrationForm()
    {
        return view('auth/registration', [
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

    public function ProcessRegistration(Backend $backend, Request $request, ObjectManager $objectManager, SchemaManager $schemaManager)
    {
        $rules = [
            'login' => 'required',
            'password' => 'required',
            'confirm' => 'required|same:password',
        ];

        $messages = [
            'required' => 'Поле :attribute является обязательным',
            'same' => 'Значение поля :attribute должно совпадать со значением поля :other'
        ];

        $this->validate($request, $rules, $messages);

        try {
            $user = User::create([
                'username' => $request->input('login'),
                'password' => $request->input('password')
            ], $backend);
        } catch (UserCreateException $e) {
            $errors = new MessageBag();
            $errors->add('registration', $e->getMessage());
            return redirect()->route('registration')->withErrors($errors);
        }

        User::login($backend, [
            'login' => $request->input('login'),
            'password' => $request->input('password')
        ]);

        $objectManager->create($schemaManager->find(self::PROFILE_SCHEMA_NAME), [
            'userId' => $user->id,
            'email' => $request->input('login')
        ]);

        return redirect()->route('settings');
    }

    public function logout(Backend $backend)
    {
        $backend->logout();
        return redirect('/');
    }
}
