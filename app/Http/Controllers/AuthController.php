<?php

namespace App\Http\Controllers;

use Cookie;
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
    const COOKIE_NAME = 'userInfo';
    
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

    private function shareSession(Request $request, $user)
    {
        $userInfo = json_encode([
            'id' => $user->id,
            'token' => $user->token(),
            'refreshToken' => $user->refreshToken()
        ]);
        Cookie::queue(Cookie::make(self::COOKIE_NAME, $userInfo, 60*96, '/', env('MAIN_SITE_SHARE_COOKIE'), false, false));
    }

    private function clearSession()
    {
        Cookie::queue(Cookie::make(self::COOKIE_NAME, null, -2628000, '/', env('MAIN_SITE_SHARE_COOKIE'), false, false));
    }

    public function ProcessLogin(Backend $backend, Request $request)
    {
        try {
            $user = User::login($backend, $request->all());
        } catch (WrongCredentialsException $e) {
            $request->session()->flash('login-error', 'Wrong сredentials data');
            return redirect('/login/');
        }

        $this->shareSession($request, $user);

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

        $user = User::login($backend, [
            'login' => $request->input('login'),
            'password' => $request->input('password')
        ]);

        $this->shareSession($request, $user);

        $objectManager->create($schemaManager->find(self::PROFILE_SCHEMA_NAME), [
            'userId' => $user->id,
            'email' => $request->input('login')
        ]);

        return redirect()->route('settings');
    }

    public function logout(Backend $backend)
    {
        try {
            $backend->logout();
        } catch (\Exception $e) {
            //
        }
        $this->clearSession();
        return redirect('/');
    }

    public function LoginByToken(Request $request)
    {
        $headers = ['Access-Control-Allow-Credentials' => 'true'];
        if (env('APP_DEBUG', false)) {
            $headers['Access-Control-Allow-Origin'] =  '*';
        } else {
            $headers['Access-Control-Allow-Origin'] =  env('MAIN_SITE');
        }

        try {
            $user = new User;
            $backend = app(Backend::class);
            $user->setRefreshToken($request->input('token'));
            $user->regenerate($backend, true);

            return response()->json(['success' => true, 'user' => $user])->withHeaders($headers);

        } catch (WrongCredentialsException $e) {
            return response()->json(['success' => false])->withHeaders($headers);
        }
    }

    public function LoginByTokenOptions()
    {
        $response = response('');
        if (env('APP_DEBUG', false)) {
            $response->header('Access-Control-Allow-Origin', '*');
        } else {
            $response->header('Access-Control-Allow-Origin', env('MAIN_SITE'));
        }
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Methods-Allow-Methods', 'POST, OPTIONS');
        return $response;
    }
}
