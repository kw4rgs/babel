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

$router->get('/v1/contracts', 'MikrotikAPIController@getContract');

$router->post('/v1/contracts','MikrotikAPIController@createContract');
$router->put('/v1/contracts','MikrotikAPIController@updateContract');
$router->delete('/v1/contracts','MikrotikAPIController@deleteContract');
$router->get('/v1/contracts', 'MikrotikAPIController@backupContracts');
$router->put('/v1/contracts','MikrotikAPIController@migrateContract');
$router->post('/v1/contracts','MikrotikAPIController@cleanContracts');
$router->delete('/v1/contracts','MikrotikAPIController@wipeContracts');





