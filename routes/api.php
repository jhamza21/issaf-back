<?php

use Illuminate\Support\Facades\Route;

//all next routes can be accessed without authentification
//register new user
Route::post('register', 'Auth\RegisterController@register');
//login user
Route::post('login', 'Auth\LoginController@login');
//logged out user
Route::post('logout', 'Auth\LoginController@logout');
//return provider image
Route::get('providerImg/{imgName}', 'ProviderController@downloadImage');
//return service image
Route::get('serviceImg/{imgName}', 'ServiceController@downloadImage');
//return user by email(used to connect user by GOOGLE ACCOUNT)
Route::get('getUserByEmail/{email}', 'UserController@getUserByEmail');


//all next routes require user authentification
Route::group(['middleware' => 'auth:api'], function () {
    //users controller
    //return users (suggestions of users) based on given string
    Route::get('users/{text}', 'UserController@getSuggestions');
    //update an user
    Route::put('users', 'UserController@update');
    //delete an user
    Route::delete('users', 'UserController@delete');
    //check if user token is valid
    Route::post('tokenIsValid','UserController@tokenIsValid');

    //providers controller
    //get all providers
    Route::get('providers', 'ProviderController@index');
    //get coonected user provider
    Route::get('getProviderByUser', 'ProviderController@getProviderByUser');
    //get provider by id
    Route::get('providers/{provider}', 'ProviderController@getProviderById');
    //store new provider
    Route::post('providers', 'ProviderController@store');
    //update provider
    Route::post('providers/{provider}', 'ProviderController@update');
    //delete provider
    Route::delete('providers/{provider}', 'ProviderController@delete');

    //services
    //return service handled by connected operator
    Route::get('getServiceByRespo', 'ServiceController@getServiceByRespo');
    //return admin services
    Route::get('getServicesByAdmin', 'ServiceController@getServicesByAdmin');
    //return service by id
    Route::get('getServiceById/{id}', 'ServiceController@getServiceById');
    //store new service
    Route::post('services', 'ServiceController@store');
    //update service
    Route::post('services/{service}', 'ServiceController@update');
    //update service counter
    Route::put('services/{service}', 'ServiceController@updateCounter');
    //delete service
    Route::delete('services/{service}', 'ServiceController@delete');

    //requests
    //return sended request of connected user
    Route::get('getSendedRequests', 'RequestController@getSendedRequests');
    //return received requests of connected user
    Route::get('getReceivedRequests', 'RequestController@getReceivedRequests');
    //refuse a request
    Route::put('refuseRequest/{request}', 'RequestController@refuseRequest');
    //accept a request
    Route::put('acceptRequest/{request}', 'RequestController@acceptRequest');
    //delete a request
    Route::delete('requests/{request}', 'RequestController@delete');

    //tickets
    //return tickets of connected user
    Route::get('tickets', 'TicketController@index');
    //validate ticket 
    Route::put('validate/{service}', 'TicketController@validateTicket');
    //return tickets reserved by operator
    Route::get('getTicketsByOperator/{service}', 'TicketController@ticketsByOperator');
    //return all tickets related to service
    Route::get('getTicketsByService/{service}', 'TicketController@getServiceTickets');
    //return available tickets/times in service based on given date
    Route::get('tickets/{date}/{service}', 'TicketController@getAvailableTicketsByDate');
    //store a new ticket 
    Route::post('tickets', 'TicketController@store');
    //reschudle old ticket
    Route::put('reschudle/{ticket}', 'TicketController@reschudleTicket');
    //delete a ticket
    Route::delete('tickets/{ticket}', 'TicketController@delete');
});
