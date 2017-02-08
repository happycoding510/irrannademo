<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/signup' , 'UserApiController@signup');

Route::group(['middleware' => ['auth:api']], function () {

	// user profile

	Route::post('/change/password' , 'UserApiController@change_password');

	Route::post('/update/location' , 'UserApiController@update_location');

	Route::get('/details' , 'UserApiController@details');

	Route::post('/update/profile' , 'UserApiController@update_profile');

	// services

	Route::get('/services' , 'UserApiController@services');

	// provider

	Route::post('/rate/provider' , 'UserApiController@rate_provider');

	// request

	Route::post('/send/request' , 'UserApiController@send_request');

	Route::post('/cancel/request' , 'UserApiController@cancel_request');
	
	Route::get('/request/check' , 'UserApiController@request_status_check');

	// history

	Route::get('/trips' , 'UserApiController@trips');
	
	Route::get('/trip/details' , 'UserApiController@trip_details');

	// payment

	Route::post('/payment' , 'PaymentController@payment');

	// chat

	Route::get('/message' , 'UserApiController@message');

	// estimated

	Route::get('/estimated/fare' , 'UserApiController@estimated_fare');

	// card payment

    Route::resource('card', 'Resource\CardResource');

});
