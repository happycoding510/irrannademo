<?php

/*
|--------------------------------------------------------------------------
| Provider Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'ProviderController@index')->name('index');
Route::get('/trips', 'ProviderResources\TripController@history')->name('trips');

Route::get('/incoming', 'ProviderController@incoming')->name('incoming');
Route::post('/request/{id}', 'ProviderController@accept')->name('accept');
Route::patch('/request/{id}', 'ProviderController@update')->name('update');
Route::delete('/request/{id}', 'ProviderController@reject')->name('reject');

Route::get('/earnings', 'ProviderController@earnings')->name('earnings');

Route::resource('documents', 'ProviderResources\DocumentController');

Route::get('/profile', 'ProviderResources\ProfileController@show')->name('profile.index');
Route::post('/profile', 'ProviderResources\ProfileController@store')->name('profile.update');

Route::get('/profile/password', 'ProviderController@index')->name('password');