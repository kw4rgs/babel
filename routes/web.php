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

$router->post('/v1/contracts','MikrotikAPIController@createContract');
$router->get('/v1/contracts', 'MikrotikAPIController@getContract');
$router->put('/v1/contracts','MikrotikAPIController@updateContract');
$router->delete('/v1/contracts','MikrotikAPIController@deleteContract');

$router->put('/v1/router','MikrotikAPIController@migrateContract');
$router->post('/v1/router','MikrotikAPIController@cleanRouter');
$router->delete('/v1/router','MikrotikAPIController@wipeRouter');

$router->get('/v1/router/backup', 'MikrotikAPIController@backupRouter');
$router->post('/v1/router/restore', 'MikrotikAPIController@restoreRouter');

$router->get('/v1/disconnect', 'MikrotikAPIController@ClientPPPOEDisconnect');


