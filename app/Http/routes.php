<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return "";
});


/**
 * REST for Project Model
 */
Route::resource('/api/projects', 'ProjectController');

Route::post('/api/projects/{id}/clean', 'ProjectController@clean');


/**
 * REST for Media Model
 */
Route::resource('/api/media', 'MediaController');

Route::get('/api/media/{id}/matches', 'MediaController@getMediaMatches');

/**
 * REST for Task Model
 */
Route::resource('/api/tasks', 'TaskController');
