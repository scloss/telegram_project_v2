<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/checkBot', 'botController@checkBot');
Route::get('/check_redis_inbox', 'botController@check_redis_inbox');
Route::get('/archive_db', 'botController@archive_db');
Route::get('/chatup', 'botController@chatup');

//-----------------------------------------------------------------------
Route::get('/insert_user', 'botController@insert_user');
Route::get('/refresh_redis_user_list', 'botController@refresh_redis_user_list');

//------------------------------------------------------------------------
Route::post('/send_msg', 'botController@send_msg');