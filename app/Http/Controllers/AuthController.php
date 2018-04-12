<?php

namespace App\Http\Controllers;

use App\Exceptions\Object\ObjectCreateException;
use App\Helpers\AjaxResponse;
use App\Services\UserManager;
use Cookie;
use App\User;
use App\Backend;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use Illuminate\Support\Collection;
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
        'message' => session('login-error'),
        'vkApp' => env('VK_APP'),
        'fbApp' => env('FB_APP')
      ]);
    }

    public function ShowRegistrationForm()
    {
        return view('auth/registration', [
        'selected' => 'login',
        'message' => session('login-error'),
        'vkApp' => env('VK_APP'),
        'fbApp' => env('FB_APP')
      ]);
    }

    public function ShowRestoringForm()
    {
        return view('auth/restore', [
            'selected' => 'login',
            'message' => session('restore-error')
        ]);
    }

    private function shareSession(Request $request, $user)
    {
        $userInfo = json_encode([
            'id' => $user->id,
            'token' => $user->token(),
            'refreshToken' => $user->refreshToken(),
            'userName' => $user->getProfileName()
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
            $errors = new MessageBag();
            $errors->add('email', 'Неверный логин или пароль');
            return redirect('/login/')->withErrors($errors);
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
            'same' => 'Пароль и подтверждения пароля должны совпадать'
        ];

        $this->validate($request, $rules, $messages);

        try {
            $user = User::create([
                'username' => $request->input('login'),
                'password' => $request->input('password')
            ], $backend);
        } catch (UserCreateException $e) {
            $errors = new MessageBag();
            if ($e->getMessage() == 'Conflict when user creation') {
                $errors->add('registration', 'Пользователь с email: ' . $request->input('login') . ' уже зарегистрирован в системе
                                        <p class="error-info">Если это ваш e-mail, <a href="'.route('restore').'">восстановите пароль</a></p>');
            }
            else {
                $errors->add('registration', $e->getMessage());
            }
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
        $headers['Access-Control-Allow-Origin'] =  env('MAIN_SITE');

        try {
            $user = new User;
            $backend = app(Backend::class);
            $user->setRefreshToken($request->input('token'));
            $user->regenerate($backend, true);

            return response()->json(['success' => true, 'user' => $user])->withHeaders($headers);

        } catch (WrongCredentialsException $e) {
            return response('', 401)->withHeaders($headers);
        }
    }

    public function LoginByTokenOptions()
    {
        $response = response('');
        $response->header('Access-Control-Allow-Origin', env('MAIN_SITE'));
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Allow-Headers', 'Content-Type');
        $response->header('Access-Control-Allow-Methods', 'POST, OPTIONS');
        return $response;
    }

    public function LoginBySocial(Backend $backend, Request $request) {
        $response = new AjaxResponse();
        $errors = new MessageBag();
        if (!$userId = $request->input('userId')) {
            $errors->add('registration', 'Не передан идентификатор пользователя');
        }

        if (!$networkName = $request->input('networkName')) {
            $errors->add('registration', 'Не передан название социальной сети');
        }

        $sessionId = $request->input('sessionId');
        $refreshToken = $request->input('refreshToken');

        if (!$errors->count()) {
            $login = $networkName . 'user' . $userId;
            $password = sha1($networkName . $userId . env('PASSWORD_SALT'));

            $isNew = false;
            try {
                $user = User::create([
                    'username' => $login,
                    'password' => $password,
                ], $backend);

                $isNew = true;

            } catch (ClientException $e) {
                if ($e->getMessage()->getStatus() != 409) {
                    $response->setResponseError($e->getMessage());
                }
            }

            if ($response->type != AjaxResponse::ERROR) {

                if ($sessionId or $refreshToken) {
                    try {
                        $user = User::loginAndMerge($backend, $sessionId, [
                            'username' => $login,
                            'password' => $password
                        ]);
                    } catch (ClientException $e) {
                        $response->setResponseError($e->getMessage());
                    }
                }
                else {
                    try {
                        $user = User::login($backend, [
                            'login' => $login,
                            'password' => $password
                        ]);
                    }
                    catch (RequestException $e) {
                        $response->setResponseError($e->getMessage());
                    }
                }

                $this->shareSession($request, $user);

                if ($isNew and $request->get('data')) {
                    $data= $request->get('data');
                    try {
                        $objectManager = app(ObjectManager::Class);
                        $schemaManager = app(SchemaManager::Class);
                        $objectManager->create($schemaManager->find(self::PROFILE_SCHEMA_NAME), [
                            'userId' => $user->id,
                            'email' => $data['email'] ?? '',
                            'sex' => (int)$data['gender'] ?? null,
                            'firstName' => $data['fio'] ?? '',
                            'birthdate' => $data['birthday'] ?? null,
                            'isSocial' => true
                        ]);
                    }
                    catch (ObjectCreateException $exception) {
                        $response->setResponseError($e->getMessage());
                    }
                }

                $response->data = route('settings');
            }
        }
        else {
            $response->setResponseError($errors->toArray());
        }

        $headers = ['Access-Control-Allow-Credentials' => 'true'];
        $headers['Access-Control-Allow-Origin'] =  env('MAIN_SITE');

        return response()->json($response)->withHeaders($headers);
    }

    public function createRecoveryCode(Request $request, Backend $backend)
    {
        $rules = [
            'email' => 'email|restoringFields',
            'username' => 'restoringFields'
        ];

        $messages = [
            'restoringFields' => 'Для восстановление пароля требуется ввести email и/или имя пользователя',
            'email' => 'Значение введенное в поле email не соотвествует формату электронной почты'
        ];

        $this->validate($request, $rules, $messages);

        $response = new AjaxResponse();

        $email = $request->input('email');
        $username = $request->input('username');

        $errors = [];

        if ($email or $username) {
            try {
                $result = User::createRecoverCode($backend, ['username' => $username, 'email' => $email]);
            }
            catch (ClientException $exception) {
                if ($exception->getResponse()->getStatusCode() == 404) {
                    $errors[] = 'Пользователь не найден';
                }
                else{
                    $errors[] = 'Ошибка во время выполнения запроса ('.$exception->getResponse()->getStatusCode().')';
                }
            }
        }
        else {
            $errors[] = 'Для восстановление пароля требуется ввести email и/или имя пользователя';
        }

        if (!empty($errors)) {
            $response->setResponseError($errors);
        }

        return response()->json($response);
    }

    public function RestorePswd(Backend $backend, Request $request)
    {
        $rules = [
            'username' => 'required',
            'password' => 'required',
            'recoveryCode' => 'required'
        ];

        $messages = [
            'required' => 'Поле :attribute является обязательным'
        ];

        $this->validate($request, $rules, $messages);

        $response = new AjaxResponse();

        $username = $request->input('username');
        $recoveryCode = $request->input('recoveryCode');
        $password = $request->input('password');

        $errors = [];

        try {
            $result = User::restorePassword($backend, ['username' => $username, 'recoveryCode' => $recoveryCode, 'newPassword' => $password ]);
        } catch (ClientException $exception) {
            if ($exception->getResponse()->getStatusCode() == 404) {
                $errors[] = 'Данный код восстановления не найден';
            }
            else{
                $errors[] = 'Ошибка во время выполнения запроса ('.$exception->getResponse()->getStatusCode().')';
            }
        }

        if (!empty($errors)) {
            $response->setResponseError($errors);
        }

        return response()->json($response);
    }
}
