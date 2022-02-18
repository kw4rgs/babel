<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

/* $router->get('/', function () use ($router) {
    return $router->app->version();
}); */

$router->get('/', function () use ($router) {return "<h1>API MIKROTIK EN LUMEN<h1>";});

$router->get('/v1/test','MikrotikAPIController@testRouterOS');
$router->get('/v1/infoQueue', 'MikrotikAPIController@getQueues');
$router->get('/v1/infoNodes', 'MikrotikAPIController@getRouterByNode');
$router->post('/v1/contracts','MikrotikAPIController@createContract');
$router->put('/v1/contracts','MikrotikAPIController@updateContract');
$router->delete('/v1/contracts','MikrotikAPIController@deleteContract');
$router->post('/v1/nodes','MikrotikAPIController@migrateNewNode');
$router->put('/v1/nodes','MikrotikAPIController@updateRouter');





