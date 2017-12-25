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
            $view->with('backend', app(Backend::Class));
            $view->with('MAIN_SITE', env('MAIN_SITE'));
        });

        Validator::extend('restoringFields', function ($attribute, $value, $parameters, $validator) {
            $email = array_get($validator->getData(),'email');
            $username = array_get($validator->getData(),'username');
            return ($username or $email);
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
