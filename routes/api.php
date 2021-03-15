<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('register', 'Auth\RegisterController@register');
Route::post('login', 'Auth\LoginController@login');
Route::post('tokenIsValid', function(){
    return response()->json([ 'valid' => auth('api')->check() ]);
});
Route::post('logout', 'Auth\LoginController@logout');

Route::group(['middleware' => 'auth:api'], function() {
    Route::get('providers', 'ProviderController@index');
    Route::get('providers/{provider}', 'ProviderController@show');
    Route::post('providers', 'ProviderController@store');
    Route::put('providers/{provider}', 'ProviderController@update');
    Route::delete('providers/{provider}', 'ProviderController@delete');
});