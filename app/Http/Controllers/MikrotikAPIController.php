<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Response;
use \App\Clases\RouterosAPI;
use \RouterOS\Client;
use \RouterOS\Query;


use App\ClienteLista;
use Exception;

class MikrotikAPIController extends Controller
{
    private $ip;
    private $user;
    private $pass;
    private $nodos;
    private $redes;

    function __construct($ip = null, $nodos = null, $redes = null)
    {
        $this->ip = '192.168.2.100';
        $this->user = 'admin';
        $this->pass = '';
        $this->nodos = $nodos != null ? $nodos : array();
        $this->redes = $redes != null ? $redes : array();
    }

    function connection($ip)
    {
        $client = new Client([
            'host' => $ip,
            'user' => $this->user,
            'pass' => $this->pass
        ]);

        return $client;
    }

    // ------------------------ Metodo Prueba -----------------------------------
    // ------------------------ metodo = [GET] ----------------------------------
    // ------------------------ /testRouterOS ----------------------------------

    /* Function: Permite chequear a que router pertenece el nodo */
    /* Parametros:
            Nodo
        Retorna:
            Router
    */

    public function testRouterOS()
    {
        $connection = $this->connection('192.168.2.100');
        $query =
            (new Query('/system/identity/print'));
        $response = $connection->query($query)->read();
        return $response;
    }

    // ------------------------ Metodo Obtener Info ------------------------------
    // ------------------------ metodo = [GET] ----------------------------------
    // ------------------------- /getQueues ------------------------------------

    /* Function: Permite chequear a que router pertenece el nodo */
    /* Parametros:
            Nodo
        Retorna:
            Router
    */

    public function getQueues()
    {
        try {
            $connection = $this->connection('192.168.2.100');

            $query =
                (new Query('/queue/simple/print'));
            $response = $connection->query($query)->read();
            count($response);
            return $response;
        } catch (Exception $e) {
            $return = response('Ha ocurrido al extraer la informacion', 400);
        }
            return $return;
    }

    // ------------------------ Metodo Obtener Info ------------------------------
    // ------------------------ metodo = [GET] ----------------------------------
    // ------------------------- /getRouterByNode ------------------------------------

    /* Function: Permite chequear a que router pertenece el nodo */
    /* Parametros:
            Nodo
        Retorna:
            Router
    */

    public function getRouterByNode($nodo)
    {
        $this->nodos[] = $nodo;
        return 'nodo';
    }

    // --------------------- Metodo Creacion de Cola ----------------------------
    // --------------------------- metodo = [POST] ------------------------------
    // -----------------------/createContract -----------------------------

    /* Function: Permite chequear a que router pertenece el nodo */
    /* Parametros:
            Nodo
        Retorna:
            Router
    */

    function createContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $colas = new MikrotikAPIController();
            $colas->createClientQueue($connection, $data['clientes']);
            $return = response('¡Cola creada con éxito!', 200);
        } catch (Exception $e) {
            $return = response('Ha ocurrido un error al actualizar el contrato', 400);
        }
        return $return;
    }

    function createClientQueue($connection, $clientes)
    {
        foreach ($clientes as $cliente) {
            //Transformo los kbps a bytes
            $cliente['download'] = (int) filter_var($cliente['download'], FILTER_SANITIZE_NUMBER_INT) * 1000;
            $cliente['upload'] = (int) filter_var($cliente['upload'], FILTER_SANITIZE_NUMBER_INT) * 1000;

            $query = (new Query("/queue/simple/add"))
                ->equal('name', $cliente["cliente_ip"])
                ->equal('target', $cliente["cliente_ip"])
                ->equal('max-limit', $cliente['upload'] . "/" . $cliente['download'])
                ->equal('queue', "pcq-upload-default/pcq-download-default");
            $response = $connection->query($query)->read();

            if ($cliente["estado"] === "activo") {
                $this->addAddressList($connection, $cliente["cliente_ip"]);
            } else {
                $this->removeAddressList($connection, $cliente["cliente_ip"]);
            }
        }
    }

    function addAddressList($connection, $ip)
    {
        $query = (new Query("/ip/firewall/address-list/add"))
            ->equal('list', $ip)
            ->equal('address', $ip);
        $response = $connection->query($query)->read();
    }

    // -------------------- Metodo Eliminacion de Cola --------------------------
    // --------------------------- metodo = [DEL] -------------------------------
    // -----------------------/deleteContract -----------------------------

    /* Function: Permite chequear a que router pertenece el nodo */
    /* Parametros:
            Nodo
        Retorna:
            Router
    */

    public function deleteContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $colas = new MikrotikAPIController();
            $colas->removeClientQueue($connection, $data['clientes']);
            $return = response('¡Cola eliminada con éxito!', 200);
        } catch (Exception $e) {
            $return = response('Ha ocurrido un error al eliminar la cola', 400);
        }
        return $return;
    }

    function removeClientQueue($connection, $clientes)
    {
        foreach ($clientes as $cliente) {
            $query = (new Query("/queue/simple/print"))
                ->where('name', $cliente["cliente_ip"]);
            $response = $connection->query($query)->read();

            if (isset($response[0])) {
                $query = (new Query("/queue/simple/remove"))
                    ->equal('.id', $response[0][".id"]);
                $response = $connection->query($query)->read();
            }
            $this->removeAddressList($connection, $cliente["cliente_ip"]);
        }
    }

    function removeAddressList($connection, $ip)
    {
        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('list', $ip)
            ->where('address', $ip);
        $response = $connection->query($query)->read();

        if (isset($response[0])) {

            $query = (new Query("/ip/firewall/address-list/remove"))
                ->equal('.id', $response[0]['.id']);
            $response = $connection->query($query)->read();
        }
    }

    // ------------------- Metodo Actualizacion de Cola -------------------------
    // --------------------------- metodo = [PUT] -------------------------------
    // -----------------------/updateContract -----------------------------

    /* Function: Permite chequear a que router pertenece el nodo */
    /* Parametros:
            Nodo
        Retorna:
            Router
    */

    function updateContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $colas = new MikrotikAPIController();
            $colas->updateClientQueue($connection, $data['clientes']);
            $return = response('¡Cola actualizada con éxito!', 200);
        } catch (Exception $e) {
            $return = response('Ha ocurrido un error al actualizar el contrato', 400);
        }
        return $return;
    }

    function updateClientQueue($connection, $clientes)
    {
        foreach ($clientes as $cliente) {

            //Transformo los kbps a bytes
            $cliente['download'] = (int) filter_var($cliente['download'], FILTER_SANITIZE_NUMBER_INT) * 1000;
            $cliente['upload'] = (int) filter_var($cliente['upload'], FILTER_SANITIZE_NUMBER_INT) * 1000;

            $query = (new Query("/queue/simple/print"))
                ->where('name', $cliente["cliente_ip"]);

            $response = $connection->query($query)->read();

            if (isset($response[0])) {

                $query = (new Query("/queue/simple/set"))
                    ->equal('.id', $response[0]['.id'])
                    ->equal('max-limit', $cliente['upload'] . "/" . $cliente['download'])
                    ->equal('parent', 'none');

                $response = $connection->query($query)->read();

                if ($cliente["estado"] === "activo") {
                    $this->addAddressList($connection, $cliente["cliente_ip"]);
                } else {
                    $this->removeAddressList($connection, $cliente["cliente_ip"]);
                }
            } else {
                $this->createClientQueue($connection, $clientes);
            }
        }
    }

    // ------------------- Metodo Actualizacion de Cola -------------------------
    // --------------------------- metodo = [PUT] -------------------------------
    // -----------------------/updateContract -----------------------------

    /* Function: Permite migrar clientes de un nodo a otro*/
    /* Parametros:
                    Array de clientes del nuevo nodo
                    Nombre del nodo
                    Subnet (es el node_id de la tabla node)
    */

    function migrateNewNode(Request $request)
    {
        try {
            $data = $request->all();

            $connection = $this->connection($data['ip_router_viejo']);
            $this->removeClientQueue($connection,$data['clientes']);

            $connection = $this->connection($data['ip_router_nuevo']);
            $this->createClientQueue($connection,$data['clientes']);

            $return = response('Clientes migrados con éxito!', 200);
        } catch (Exception $e) {
            $return = response('Ha ocurrido un error al migrar los clientes.', 400);
        }

        return $return;

    }

}
