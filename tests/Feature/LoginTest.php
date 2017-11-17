<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class LoginTest extends TestCase
{

    public function testRedirectWithoutSession()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login/');
    }

    public function testNoRedirectWithSession()
    {
        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->withSession(['session-token' => $user->token()]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
