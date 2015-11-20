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
    // $docker = new Docker\Docker(Docker\Http\DockerClient::createWithEnv());

    // $container = new Docker\Container(
    //     [
    //         'Image' => env('DOCKER_FPRINT_IMAGE'),
    //         'Cmd' => ['precompute', '/var/audfprint/music.mp3'],
    //         'HostConfig' => [
    //             'Binds' => [env('FPRINT_STORE').':/var/audfprint']
    //         ]
    //     ]
    // );

    // $manager = $docker->getContainerManager();
    // $manager->create($container);
    // $manager->run($container, function($output, $type) {
    //     echo($output);
    // });

    // printf('Container\'s id is %s', $container->getId());
    // printf('Container\'s name is %s', $container->getName());
    // printf('Container\'s exit code is %d', $container->getExitCode());
    //



    return "What you have just witnessed is a test of Docker integration... or HAVE YOU!!??!?! (you haven't, I deleted that code.)";
});


/**
 * REST for Project Model
 */
Route::resource('/api/projects', 'ProjectController');


/**
 * REST for Media Model
 */
Route::resource('/api/media', 'MediaController');

Route::get('/api/media/{id}/matches', 'MediaController@getMediaMatches');

/**
 * REST for Task Model
 */
Route::resource('/api/tasks', 'TaskController');
