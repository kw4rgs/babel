<?php

/** @var \Laravel\Lumen\Routing\Router $router */
use Illuminate\Support\Facades\Route;

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

$router->get('/', function () use ($router) {return "<h1>". env('APP_TITLE') ."<h1>";});

$router->get('/v1/test','MikrotikAPIController@testRouterOS');

/* RUTAS PARA QUEUES */
$router->post('/v1/contracts','MikrotikAPIController@createContract');
$router->get('/v1/contracts', 'MikrotikAPIController@getContract');
$router->put('/v1/contracts','MikrotikAPIController@updateContract');
$router->delete('/v1/contracts','MikrotikAPIController@deleteContract');

//$router->put('/v1/router','MikrotikAPIController@migrateContract');
//$router->post('/v1/router','MikrotikAPIController@cleanRouter');
//$router->delete('/v1/router','MikrotikAPIController@wipeRouter');
//$router->delete('/v1/router','MikrotikAPIController@wipeRouterOnlyActives');

$router->get('/v1/router/backup', 'MikrotikAPIController@backupRouter');
$router->post('/v1/router/restore', 'MikrotikAPIController@restoreRouter');

/* RUTAS PARA PPPOE */
$router->post('/v1/pppoe/client', 'MikrotikPPPOEController@disconnectClient');
$router->get('/v1/pppoe/client', 'MikrotikPPPOEController@findclient');


/* RUTAS PARA WIRELES */
$router->get('/v1/router/getDataMikrotik', 'MikrotikAPIController@getDataMikrotik');
$router->patch('/v1/router/revertChanges','MikrotikAPIController@revertChanges');

    /* Habilitar conexión */
#$router->patch('/v1/connection/enable','MikrotikAPIController@enableConnection');
#$router->post('/v2/connection/enable','MikrotikAPIController@enableConn');
    /* Deshabilitar conexión */
#$router->patch('/v1/connection/disable','MikrotikAPIController@disableConnection');


$router->get('/v2/connection/findConn','MikrotikAPIController@findConn');
#$router->get('/v2/connection/findConnAddress','MikrotikAPIController@findConnAddress');
/* Habilitar conexión */
$router->patch('/v2/connection/enableConnection','MikrotikAPIController@enableConnection');
/* Deshabilitar conexión */
$router->patch('/v2/connection/disableConnection','MikrotikAPIController@disableConnection');



