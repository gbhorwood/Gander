<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

/*
|--------------------------------------------------------------------------
| Options responses. Avoids requiring middleware to handle CORS 
|--------------------------------------------------------------------------
*/
Route::options('gander/requests/{number}', '\Gbhorwood\Gander\Controllers\GanderController@options');
Route::options('gander/requests/stats/{number}/{units}/ago', '\Gbhorwood\Gander\Controllers\GanderController@options');
Route::options('gander/requests/logs/{number}/{units}/ago', '\Gbhorwood\Gander\Controllers\GanderController@options');

/*
|--------------------------------------------------------------------------
| Stats and logs
|--------------------------------------------------------------------------
*/
Route::get('gander/requests/{number}', '\Gbhorwood\Gander\Controllers\GanderController@getRequest');
Route::get('gander/requests/stats/{number}/{units}/ago', '\Gbhorwood\Gander\Controllers\GanderController@getRequestsStats');
Route::get('gander/requests/logs/{number}/{units}/ago', '\Gbhorwood\Gander\Controllers\GanderController@getRequestsLogs');

