<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
//use App\Http\Requests;
//use App\Http\Response;
//use \App\Clases\RouterosAPI;
use \RouterOS\Client;
use \RouterOS\Query;
use Exception;


class MikrotikPPPOEController extends Controller
{

    function __construct($ip = null, $nodos = null, $redes = null)
    {
        $this-> ip = '';
        $this-> user = 'usr_mkt';
        $this-> pass = 'usr_mkt';
        $this-> nodos = $nodos != null ? $nodos : array();
        $this-> redes = $redes != null ? $redes : array();
        $this-> middleware('auth');
    }

    function connection($ip)
    {
        $client = new Client([
            'host' => $ip,
            'user' => $this->user,
            'pass' => $this->pass,
            'port' => 8728
        ]);

        return $client;
    }

    // ---------------------- Disconnect PPPoE Client ----------------------------
    // ----------------------- HTTP Method = [POST] ------------------------------
    // ------------------------ /pppoe/disconnect --------------------------------
    // 
    /* Function: Disconnect the client from the PPPoE Server
    /* Params: The Mikrotik's IP  -> "ip_router":
    //         Client IP          -> "ip_cliente":  */

    public function disconnectClient (Request $request) 
    {      
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip_router']);
            $query = (new Query("/ppp/active/print"))
                ->where('address', $data['ip_cliente'] );
            $response = $connection->query($query)->read();

            if (isset($response[0]['.id'])){
                $client_pppoe = $response[0]['.id'];
                $query = (new Query("/ppp/active/remove"))
                    ->equal('.id', $client_pppoe);
                $response = $connection->query($query)->read(); 
                $return = response('¡Operacion realizada con exito!', 200);
            }
            else{
                $return = response('No se encontro la ip en el servidor', 404);
            }
            
        } catch (\Exception $e) {
            $return = response('Ha ocurrido un error: ' . $e->getMessage() , 400);    
            } 
            return $return;
    }

}