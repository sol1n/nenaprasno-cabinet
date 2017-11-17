<?php

namespace Tests\Unit;

use App\User;
use Tests\TestCase;
use App\Settings;


class SettingsTest extends TestCase
{
    public $settings;

    public function setUp()
    {
        parent::setUp();

        $user = User::Login([
            'login' => env('TEST_LOGIN'),
            'password' => env('TEST_PASSWORD')
        ], false);

        $this->withSession(['session-token' => $user->token()]);

        $this->settings = app(Settings::class);
    }

    public function test_can_create_settings()
    {
        $this->assertInstanceOf(Settings::class, $this->settings);
    }

    public function test_can_save_settings()
    {
        $oldTitle = $this->settings->title;
        $this->settings->save(['title' => 'SettingsTestTitle']);
        $this->assertEquals($this->settings->title, 'SettingsTestTitle');
        $this->settings->save(['title' => $oldTitle]);
    }
}
