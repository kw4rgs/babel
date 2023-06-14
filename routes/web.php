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


$router->get('/v1/', function () use ($router) {return "<h1>". env('APP_TITLE') ."<h1>";});

$router->get('/v1/test','MikrotikAPIController@testRouterOS');

/* RUTAS CRUD PARA QUEUES */
$router->get('/v1/contracts', 'MikrotikAPIController@getContract');
$router->post('/v1/contracts','MikrotikAPIController@createContract');
$router->put('/v1/contracts','MikrotikAPIController@updateContract');
$router->delete('/v1/contracts','MikrotikAPIController@deleteContract');

/* RUTAS CRUD PARA CONTRATOS ACTUALIZADOS*/
$router->post('/v1.1/contracts','MikrotikAPIController@createQueueAddress');
$router->put('/v1.1/contracts','MikrotikAPIController@updateQueueAddress');


/* RUTAS MASIVAS PARA ROUTER */
//$router->put('/v1/router','MikrotikAPIController@migrateContract');
//$router->post('/v1/router','MikrotikAPIController@cleanRouter');
//$router->delete('/v1/router','MikrotikAPIController@wipeRouter');
//$router->delete('/v1/router','MikrotikAPIController@wipeRouterOnlyActives');

//$router->get('/v1/router/backup', 'MikrotikAPIController@backupRouter');
//$router->post('/v1/router/restore', 'MikrotikAPIController@restoreRouter');

/* RUTAS RESETEO DE ADDRESS-LIST */
/* Habilitar conexión */
$router->patch('/v1/connection/enableConnection','MikrotikAPIController@enableConnection');
/* Deshabilitar conexión */
$router->patch('/v1/connection/disableConnection','MikrotikAPIController@disableConnection');
/* Restaura los cambios aplicados en la address-list , pasa todos los cortados a activos */
$router->patch('/v1/connection/revertChanges','MikrotikAPIController@revertChanges');

/* RUTAS PARA TRAER INFO */
/* Trae toda la info de un mikrotik */
$router->get('/v1/router/getDataMikrotik', 'MikrotikAPIController@getDataMikrotik');
/* Encuentra al cliente en los equipos mikrotik */
#$router->get('/v1/connection/findConn','MikrotikAPIController@findConn');

/* RUTAS PARA TESTEO */
#$router->get('/v1.1/queues', 'MikrotikAPIController@findQueue');



$router->group(['middleware' => 'bearer-token'], function ($router) {
    /* RUTAS PARA RADIUS CONTROLLER */

    /* Trae todos los usuarios del radius */
    $router->get('/v1/radius/users_data', ['uses' => 'RadiusController@getAllUsers']);

    /* RADIUS CRUD */

    /* Crear un usuario en el radius */
    $router->post('/v1/radius/users', ['uses' => 'RadiusController@createUser']);

    /* Trae un usuario del radius */
    $router->get('/v1/radius/users', ['uses' => 'RadiusController@getUser']);

    /* Actualiza un usuario del radius */
    $router->put('/v1/radius/users', ['uses' => 'RadiusController@updateUser']);

    /* Elimina un usuario del radius */ 
    $router->delete('/v1/radius/users', ['uses' => 'RadiusController@deleteUser']);

    /* Encuentra un usuario del radius */
    $router->get('/v1/radius/users/find', ['uses' => 'RadiusController@findUser']);

    /* RUTAS SMARTOLT CONTROLLER */

    /* Trae el estado de una ONU */
    $router->get('/v1/smartolt/onu/status', ['uses' => 'SmartOltController@getOnuStatus']);

    /* Reinicia una ONU */
    $router->post('/v1/smartolt/onu/reboot', ['uses' => 'SmartOltController@rebootOnu']);


    /* RUTAS FTTH CONTROLLER */

    /* Trae el estado de un customer */
    $router->get('/v1/ftth/customer', ['uses' => 'FTTHController@getCustomerConnection']);

    /* Actualiza un customer y reinicia la ONU */
    $router->put('/v1/ftth/customer', ['uses' => 'FTTHController@updateCustomerConnection']);


});









