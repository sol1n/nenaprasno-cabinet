<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use App\Services\RoleManager;
use App\Services\UserManager;
use App\Settings;
use App\Backend;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('*', function($view){
            $backend = app(Backend::Class);
            $view->with('backend', $backend);
            $view->with('profileName', Cookie::get($backend->code . '-profileName'));
            $view->with('MAIN_SITE', env('MAIN_SITE', 'https://nenaprasno.ru'));
            $view->with('MEDIA_SITE', env('MEDIA_SITE', 'https://media.nenaprasno.ru'));
        });

        Validator::extend('restoringFields', function ($attribute, $value, $parameters, $validator) {
            $email = array_get($validator->getData(),'email');
            $username = array_get($validator->getData(),'username');
            return ($username or $email);
        });

        Validator::extend('password_repeat_validator', function($attribute, $value, $parameters, $validator) {
            $password = array_get($validator->getData(),'password');
            $confirm = array_get($validator->getData(),'confirm');
            return ($password == $confirm);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('App\Backend', function($app){
            return new Backend();
        });

        $this->app->singleton('App\Settings', function($app){
            return new Settings();
        });

        $this->app->singleton('App\Services\SchemaManager', function($app){
            return new SchemaManager();
        });

        $this->app->singleton('App\Services\ObjectManager', function($app){
            return new ObjectManager();
        });

        $this->app->singleton('App\Services\RoleManager', function($app){
            return new RoleManager();
        });

        $this->app->singleton('App\Services\UserManager', function($app){
            return new UserManager();
        });
    }
}
