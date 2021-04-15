<?php

use Illuminate\Support\Facades\Route;


Route::post('register', 'Auth\RegisterController@register');
Route::post('login', 'Auth\LoginController@login');
Route::post('tokenIsValid', function(){
    return response()->json(auth('api')->user());
});
Route::post('logout', 'Auth\LoginController@logout');
Route::get('providerImg/{imgName}', 'ProviderController@downloadImage');
Route::get('serviceImg/{imgName}', 'ServiceController@downloadImage');

//USER AUTHENTIFICATED
Route::group(['middleware' => 'auth:api'], function() {
    //users
    Route::get('users', 'UserController@index');    
    Route::put('updateAccount', 'UserController@update');    
    Route::get('getUser/{username}', 'UserController@getUserByUsername');
    //providers
    Route::get('getUserProvider', 'ProviderController@getUserProvider');
    Route::get('providers', 'ProviderController@index');
    Route::get('providers/{provider}', 'ProviderController@show');
    Route::post('providers', 'ProviderController@store');
    Route::post('providers/{provider}', 'ProviderController@update');
    Route::delete('providers/{provider}', 'ProviderController@delete');
    //services
    Route::get('services', 'ServiceController@index');
    Route::get('services/{service}', 'ServiceController@show');
    Route::post('services', 'ServiceController@store');
    Route::put('services/{service}', 'ServiceController@update');
    Route::delete('services/{service}', 'ServiceController@delete');
    //tickets
    Route::get('tikcets', 'TicketController@index');
    Route::get('tikcets/{tikcet}', 'TicketController@show');
    Route::post('tikcets', 'TicketController@store');
    Route::put('tikcets/{tikcet}', 'TicketController@update');
    Route::delete('tikcets/{tikcet}', 'TicketController@delete');
    //requests
    Route::get('requests/{date}/{service_id}', 'RequestController@getTicketsByDate');
    Route::get('requests/sended', 'RequestController@getSendedRequests');
    Route::get('requests/received', 'RequestController@getReceivedRequests');
    Route::post('requests', 'RequestController@store');
    Route::put('requests/{request}', 'RequestController@update');
    Route::delete('requests/{request}', 'RequestController@delete');

});