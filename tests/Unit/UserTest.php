<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\User;
use App\Backend;
use App\Services\UserManager;
use App\Exceptions\User\WrongCredentialsException;
use App\Exceptions\User\UnAuthorizedException;
use App\Exceptions\User\UserNotFoundException;

class UserTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $backend = new Backend;

        $user = User::Login($backend, [
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->withSession(['session-token' => $user->token()]);
        $this->manager = app(UserManager::class);
    }

    public function test_user_can_login()
    {
        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->assertTrue(! empty($user->token()));
    }

    public function test_user_cant_login_with_bad_credentials()
    {
        $this->expectException(WrongCredentialsException::class);

        $user = User::Login([
            'login' => 'wrongLogin',
            'password' => 'wrongPassword'
        ], false);
    }

    public function test_can_regenerate_token()
    {
        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $token = $user->token();
        $user->regenerate(false);
        $this->assertNotEquals($token, $user->token());
    }

    public function test_can_create_user()
    {
        $fields = ['username' => 'testtempuser', 'password' => 'testtemppassword'];
        $user = $this->manager->create($fields);

        $this->assertInstanceOf(User::Class, $user);
        $this->assertEquals($user->username, $fields['username']);

        $this->manager->delete($user->id);
    }

    public function test_created_user_can_login()
    {
        $fields = ['username' => 'testtempuser', 'password' => 'testtemppassword'];
        $this->manager->create($fields);

        $user = User::Login(['login' => $fields['username'], 'password' => $fields['password']], false);

        $this->assertFalse(empty($user->token()));

        $this->manager->delete($user->id);
    }

    public function test_can_delete_user()
    {
        $fields = ['username' => 'testtempuser', 'password' => 'testtemppassword'];
        $user = $this->manager->create($fields);
        $this->manager->delete($user->id);

        $this->expectException(UserNotFoundException::class);

        $this->manager->find($user->id);
    }

    public function test_can_update_user()
    {
        $fields = ['username' => 'testtempuser', 'password' => 'testtemppassword'];
        $user = $this->manager->create($fields);
        $user = $this->manager->save($user->id, ['username' => 'newtesttempuser']);

        $this->assertEquals($user->username, 'newtesttempuser');

        $this->manager->delete($user->id);
    }
}
