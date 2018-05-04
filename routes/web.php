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
Route::get('/restore', 'AuthController@ShowRestoringForm')->name('restore');
Route::post('/recover-code', 'AuthController@createRecoveryCode')->name('recoverCode');
Route::post('/restore-pswd', 'AuthController@RestorePswd')->name('restorePswd');
Route::post('/login', 'AuthController@ProcessLogin');
Route::post('/registration', 'AuthController@ProcessRegistration');
Route::post('/loginByToken', 'AuthController@LoginByToken');
Route::options('/loginByToken', 'AuthController@LoginByTokenOptions');
Route::post('/loginBySocial', 'AuthController@LoginBySocial');
Route::options('/loginBySocial', 'AuthController@LoginByTokenOptions');
Route::get('/users', 'CounterController@index')->name('counter');
Route::post('/subscribe', 'UserController@subscribe');


Route::group(['middleware' => ['appercodeAuth']], function () {
  Route::get('/', 'CabinetController@dashboard')->name('cabinet');
  Route::get('/settings', 'CabinetController@settings')->name('settings');
  Route::post('/profile', 'CabinetController@saveProfile')->name('save-profile');
  Route::post('/subscribes', 'CabinetController@subscribes')->name('save-subscribes');
  Route::post('/password', 'CabinetController@changePassword')->name('change-password');
  Route::post('/declineSubscribe', 'CabinetController@declineSubscribe')->name('decline-subscribe');
  Route::post('/procedure', 'CabinetController@procedure');
  Route::get('/logout', 'AuthController@logout')->name('logout');
});




