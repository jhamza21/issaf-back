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
    Route::get('getUserByUsername/{username}', 'UserController@getUserByUsername');
    Route::get('getUserById/{id}', 'UserController@getUserById');

    //providers
    Route::get('getUserProvider', 'ProviderController@getUserProvider');
    Route::get('providers', 'ProviderController@index');
    Route::get('providers/{provider}', 'ProviderController@show');
    Route::post('providers', 'ProviderController@store');
    Route::post('providers/{provider}', 'ProviderController@update');
    Route::delete('providers/{provider}', 'ProviderController@delete');
    //services
    Route::get('services', 'ServiceController@index');
    Route::get('getServiceByAdmin', 'ServiceController@getServiceByAdmin');
    Route::get('getServiceById/{id}', 'ServiceController@getServiceById');
    Route::post('services', 'ServiceController@store');
    Route::post('services/{service}', 'ServiceController@update');
    Route::delete('services/{service}', 'ServiceController@delete');
    //tickets
    Route::get('requests/{date}/{service_id}', 'TicketController@getAvailableTicketsByDate');
    Route::get('requests', 'TicketController@index');
    Route::get('requests/{tikcet}', 'TicketController@show');
    Route::post('requests', 'TicketController@store');
    Route::put('requests/{tikcet}', 'TicketController@update');
    Route::delete('requests/{tikcet}', 'TicketController@delete');
   // Route::get('requests/{date}/{service_id}', 'RequestController@getTicketsByDate');
    //requests
    Route::get('requests', 'RequestController@index');
    Route::get('requests/sended', 'RequestController@getSendedRequests');
    Route::get('requests/received', 'RequestController@getReceivedRequests');
    Route::get('requests/{service}', 'RequestController@getRequestByService');
    Route::put('requests/refuse/{request}', 'RequestController@refuseRequest');
    Route::put('requests/accept/{request}', 'RequestController@acceptRequest');
    Route::delete('requests/{request}', 'RequestController@delete');

});