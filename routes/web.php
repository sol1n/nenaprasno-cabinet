<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', 'AuthController@ShowAuthForm')->name('login');
Route::get('/registration', 'AuthController@ShowRegistrationForm')->name('registration');
Route::post('/login', 'AuthController@ProcessLogin');
Route::post('/registration', 'AuthController@ProcessRegistration');
Route::post('/loginByToken', 'AuthController@LoginByToken');
Route::options('/loginByToken', 'AuthController@LoginByTokenOptions');
Route::post('/loginBySocial', 'AuthController@LoginBySocial');


Route::group(['middleware' => ['appercodeAuth']], function () {
  Route::get('/', 'CabinetController@dashboard')->name('cabinet');
  Route::get('/settings', 'CabinetController@settings')->name('settings');
  Route::post('/profile', 'CabinetController@saveProfile')->name('save-profile');
  Route::post('/subscribes', 'CabinetController@subscribes')->name('save-subscribes');
  Route::post('/password', 'CabinetController@changePassword')->name('change-password');
  Route::post('/declineSubscribe', 'CabinetController@declineSubscribe')->name('decline-subscribe');
  Route::post('/procedure', 'CabinetController@procedure');
  Route::get('/logout', 'AuthController@logout');
});




