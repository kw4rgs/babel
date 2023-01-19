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

Route::group([

    'prefix' => 'v1'

], function ($router) {
    Route::post('login', 'AuthController@login');
    Route::post('logout', 'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::post('user-profile', 'AuthController@me');
});

    Route::get('/v1/', function () use ($router) {return "<h1>". env('APP_TITLE') ."<h1>";});

    Route::group(['middleware' => 'auth:api'], function() {
    
        Route::get('/api/v1/test','MikrotikAPIController@testRouterOS');

        /* RUTAS CRUD PARA QUEUES */
        Route::get('/api/v1/contracts', 'MikrotikAPIController@getContract');
        Route::post('/api/v1/contracts','MikrotikAPIController@createContract');
        Route::put('/api/v1/contracts','MikrotikAPIController@updateContract');
        Route::delete('/api/v1/contracts','MikrotikAPIController@deleteContract');

    /* RUTAS MASIVAS PARA ROUTER */
    //$router->put('/v1/router','MikrotikAPIController@migrateContract');
    //$router->post('/v1/router','MikrotikAPIController@cleanRouter');
    //$router->delete('/v1/router','MikrotikAPIController@wipeRouter');
    //$router->delete('/v1/router','MikrotikAPIController@wipeRouterOnlyActives');

    //$router->get('/v1/router/backup', 'MikrotikAPIController@backupRouter');
    //$router->post('/v1/router/restore', 'MikrotikAPIController@restoreRouter');

    /* RUTAS RESETEO DE ADDRESS-LIST */
    /* Habilitar conexión */
    Route::patch('/api/v1/connection/enableConnection','MikrotikAPIController@enableConnection');
    /* Deshabilitar conexión */
    Route::patch('/api/v1/connection/disableConnection','MikrotikAPIController@disableConnection');
    /* Restaura los cambios aplicados en la address-list , pasa todos los cortados a activos */
    //$router->patch('/v1/connection/revertChanges','MikrotikAPIController@revertChanges');

    /* RUTAS PARA TRAER INFO */
    Route::get('/api/v1/router/getDataMikrotik', 'MikrotikAPIController@getDataMikrotik');
    /* Trae toda la info de un mikrotik */
    #$router->get('/v1/router/getDataMikrotik', 'MikrotikAPIController@getDataMikrotik');
    /* Encuentra al cliente en los equipos mikrotik */
    #$router->get('/v1/connection/findConn','MikrotikAPIController@findConn');
});