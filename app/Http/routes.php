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
    $docker = new Docker\Docker(Docker\Http\DockerClient::createWithEnv());

    $container = new Docker\Container(
        [
            'Image' => 'docker/whalesay:latest',
            'Entrypoint' => ['cowsay', 'boo']
        ]
    );

    $manager = $docker->getContainerManager();
    $manager->create($container);
    $manager->run($container, function($output, $type) {
        echo($output);
    });

    printf('Container\'s id is %s', $container->getId());
    printf('Container\'s name is %s', $container->getName());
    printf('Container\'s exit code is %d', $container->getExitCode());

    return "What you have just witnessed is a test of Docker integration";
});


/**
 * REST for Project Model
 */
Route::get('/api/projects', function () {
    return "hello world";
});
Route::post('/api/projects', function () {
    return "hello world";
});
Route::put('/api/projects/{id}', function ($id) {
    return "hello world";
});
Route::delete('/api/projects', function () {
    return "hello world";
});


/**
 * REST for Task Model
 */
Route::get('/api/tasks', function () {
    return get_loaded_extensions();
});
Route::post('/api/tasks', function () {
    return "hello world";
});
Route::put('/api/tasks/{id}', function ($id) {
    return "hello world";
});
Route::delete('/api/tasks', function () {
    return "hello world";
});
