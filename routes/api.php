<?php

use Illuminate\Support\Facades\Route;


Route::post('register', 'Auth\RegisterController@register');
Route::post('login', 'Auth\LoginController@login');
Route::post('tokenIsValid', function () {
    return response()->json(auth('api')->user());
});
Route::post('logout', 'Auth\LoginController@logout');
Route::get('providerImg/{imgName}', 'ProviderController@downloadImage');
Route::get('serviceImg/{imgName}', 'ServiceController@downloadImage');
Route::get('getUserByEmail/{email}', 'UserController@getUserByEmail');


//USER AUTHENTIFICATED
Route::group(['middleware' => 'auth:api'], function () {
    //users
    Route::get('users/{text}', 'UserController@getSuggestions');
    Route::put('updateAccount', 'UserController@update');

    //providers
    Route::get('getUserProvider', 'ProviderController@getUserProvider');
    Route::get('providers', 'ProviderController@index');
    Route::get('providers/{provider}', 'ProviderController@show');
    Route::post('providers', 'ProviderController@store');
    Route::post('providers/{provider}', 'ProviderController@update');
    Route::delete('providers/{provider}', 'ProviderController@delete');
    //services
    Route::get('services', 'ServiceController@index');
    Route::get('getServiceByRespo', 'ServiceController@getServiceByRespo');
    Route::get('getServicesByAdmin', 'ServiceController@getServicesByAdmin');
    Route::get('getServiceById/{id}', 'ServiceController@getServiceById');
    Route::get('getServiceTickets/{id}', 'ServiceController@getServiceTickets');
    Route::post('services', 'ServiceController@store');
    Route::post('services/{service}', 'ServiceController@update');
    Route::put('services/{service}', 'ServiceController@updateCounter');
    Route::delete('services/{service}', 'ServiceController@delete');
    //requests
    Route::get('requests/sended', 'RequestController@getSendedRequests');
    Route::get('requests/received', 'RequestController@getReceivedRequests');
    Route::put('requests/refuse/{request}', 'RequestController@refuseRequest');
    Route::put('requests/accept/{request}', 'RequestController@acceptRequest');
    Route::delete('requests/{request}', 'RequestController@delete');
    //tickets
    Route::get('tickets', 'TicketController@index');
    Route::put('validate/{ticketId}/{service}', 'TicketController@validateTicket');
    Route::get('ticketsBySerice/{service}', 'TicketController@ticketsBySerice');
    Route::get('tickets/{date}/{service}', 'TicketController@getAvailableTicketsByDate');
    Route::post('tickets', 'TicketController@store');
    Route::post('addTicketToService/{service}', 'TicketController@addTicketToService');
    Route::put('reschudle/{ticket}', 'TicketController@reschudleTicket');
    Route::delete('tickets/{ticket}', 'TicketController@delete');
});
