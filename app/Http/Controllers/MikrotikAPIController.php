<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Response;
use \App\Clases\RouterosAPI;
use \RouterOS\Client;
use \RouterOS\Query;
use Exception;
use function PHPUnit\Framework\isEmpty;

class MikrotikAPIController extends Controller
{

    function __construct($ip = null)
    {
        $this-> ip = '';
        $this-> user = env("BABEL_USER");
        $this-> pass = env("BABEL_PASS");
	    $this-> port = env("BABEL_PORT");
        $this-> middleware('auth');

    }

    function connection($ip)
    {
        $client = new Client([
            'host' => $ip,
            'user' => $this->user,
            'pass' => $this->pass,
            'port' => intval($this->port)
        ]);

        return $client;
    }

    // ------------------------ Test RouterOS ---------------------------------
    // ---------------------- HTTP Method = [GET] -----------------------------
    // --------------------------- /v1/test -----------------------------------
    // 
    /* Function: test the Mikrotik server connection.
    /* Params: 
    //      Server IP  */

    public function testRouterOS(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $query =
                (new Query('/system/identity/print'));
            $response = $connection->query($query)->read();
            return $response;
        } catch (Exception $e) {
            $error = $e->getMessage(); 
            $return = response('BABEL: ' . $error, 500);
        }
            return $return;
    }   

    // ------------------------ Get Contracts ---------------------------------
    // ---------------------- HTTP Method = [GET] -----------------------------
    // --------------------------- /contract ----------------------------------
    // 
    /* Function: Get contract info from a Mikrotik server.
    /* Params: 
    //      Server IP  */

    public function getContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $query =
                (new Query('/queue/simple/print'));
            $response = $connection->query($query)->read();
            $quantity = count ($response);

            $info =
            (new Query('/log/info'))
                ->equal('message', 'BABEL: Obteniendo informacion de queues...');
            $log_msg = $connection->query($info)->read();

            foreach ($response as $key => $queue) {
                $colas[$key]['cliente_ip'] = (explode("/", $queue['target']))[0];
                $ancho = explode("/", $queue['max-limit']);
                $colas[$key]['download'] = strval ($ancho[1] / 1000) . " Kbps"; 
                $colas[$key]['upload'] = strval ($ancho[0] / 1000) . " Kbps"; 
                $colas[$key]['estado'] = "activo";
            };

            if ($response == null) {
                $return = response ('BABEL: No hay clientes en el equipo', 404);
            } else {

            $queues = array
                (
                    'ip_server' => $request['ip'],
                    'clientes_activos' => $quantity,
                    'clientes_details' => $colas
                );
            return $queues;
            }
        } catch (Exception $e) {
            $error = $e->getMessage(); 
            $return = response('BABEL: ' . $error, 500);
        }
            return $return;
    }

    // ------------------------ Create Contracts ------------------------------
    // ---------------------- HTTP Method = [POST] ----------------------------
    // --------------------------- /contract ----------------------------------
    // 
    /* Function: Create a 'contract' in a Mikrotik server.
    /* Params: 
    //      Server IP 
    //      Contract/s IP */

    public function createContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);

            $colas = $this->createClientQueue($connection, $data['clientes']);
            $return = response('BABEL : ¡Cola/s creada/s con éxito!', 200);
        } catch (Exception $e) {
            $error = $e->getMessage(); 
            $return = response('BABEL: ' . $error, 500);
        }
        return $return;
    }

        /* Function: Creates a queue in a Mikrotik server. */
        function createClientQueue($connection, $clientes)
        {
            foreach ($clientes as $cliente) {
                $info =
                (new Query('/log/info'))
                    ->equal('message', 'BABEL: Se procede a crear la queue: ' . $cliente["cliente_ip"]);
                $response = $connection->query($info)->read();

                //Transform kbps a bytes
                $cliente['download'] = (int) filter_var($cliente['download'], FILTER_SANITIZE_NUMBER_INT) * 1000;
                $cliente['upload'] = (int) filter_var($cliente['upload'], FILTER_SANITIZE_NUMBER_INT) * 1000;

                $query = (new Query("/queue/simple/add"))
                    ->equal('name', $cliente["cliente_ip"])
                    ->equal('target', $cliente["cliente_ip"])
                    ->equal('max-limit', $cliente['upload'] . "/" . $cliente['download'])
                    ->equal('queue', "pcq-upload-default/pcq-download-default");
                $response = $connection->query($query)->read();

                if ($cliente["estado"] === "activo") {
                    $info =
                    (new Query('/log/info'))
                        ->equal('message', 'BABEL: Se procede a crear la address-list de: ' . $cliente["cliente_ip"]);
                    $log_msg = $connection->query($info)->read();
                    $this->addAddressList($connection, $cliente["cliente_ip"]);
                } else {
                    $info =
                    (new Query('/log/info'))
                        ->equal('warning', 'BABEL: Se procede a ELIMINAR la address-list de: ' . $cliente["cliente_ip"]);
                    $log_msg = $connection->query($info)->read();
                    $this->removeAddressList($connection, $cliente["cliente_ip"]);
                }
            }
        }

        /* Function: Creates an address-list for the queue in the Mikrotik server. */
        function addAddressList($connection, $ip)
        {
            $query = (new Query("/ip/firewall/address-list/add"))
                ->equal('list', 'clientes_activos')
                ->equal('address', $ip);
            $response = $connection->query($query)->read();
        }

    
    // ------------------------ Delete Contracts ------------------------------
    // ---------------------- HTTP Method = [DEL] -----------------------------
    // --------------------------- /contract ----------------------------------
    // 
    /* Function: Delete the contract from a Mikrotik server.
    /* Params: 
    //      Server IP 
    //      Contract/s IP */

    public function deleteContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $colas = $this->removeClientQueue($connection, $data['clientes']);
            $return = response('¡Cola/s eliminada/s con éxito!', 200);
        } catch (Exception $e) {
            $error = $e->getMessage(); 
            $return = response('BABEL: ' . $error, 500);
        }
        return $return;
    }

        /* Function: Removes a queue in a Mikrotik server. */
        function removeClientQueue($connection, $clientes)
        {
            foreach ($clientes as $cliente) {
                $info =
                    (new Query('/log/warning'))
                        ->equal('message', 'BABEL: Se procede a ELIMINAR la queue: ' . $cliente["cliente_ip"]);
                $log_msg = $connection->query($info)->read();

                $query = (new Query("/queue/simple/print"))
                    ->where('name', $cliente["cliente_ip"]);
                $response = $connection->query($query)->read();

                if (isset($response[0])) {
                    $query = (new Query("/queue/simple/remove"))
                        ->equal('.id', $response[0][".id"]);
                    $response = $connection->query($query)->read();
                }
                $info =
                (new Query('/log/warning'))
                    ->equal('message', 'BABEL: Se procede a ELIMINAR la address-list de: ' . $cliente["cliente_ip"]);
                $log_msg = $connection->query($info)->read();

                $this->removeAddressList($connection, $cliente["cliente_ip"]);
            }
        }

        /* Function: Removes the address-list of a queue in the Mikrotik server. */
        function removeAddressList($connection, $ip)
        {
            $query = (new Query("/ip/firewall/address-list/print"))
                ->where('list', 'clientes_activos')
                ->where('address', $ip);
            $response = $connection->query($query)->read();

            if (isset($response[0])) {
                $query = (new Query("/ip/firewall/address-list/remove"))
                    ->equal('.id', $response[0]['.id']);
                $response = $connection->query($query)->read();
            }
        }

    // ------------------------ Update Contracts ------------------------------
    // ---------------------- HTTP Method = [PUT] -----------------------------
    // --------------------------- /contract ----------------------------------
    // 
    /* Function: Update upload/download params of a contract in a Mikrotik server.
    /* Params: 
    //      Server IP 
    //      Contract/s IP */

    function updateContract(Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            $colas = self::updateClientQueue($connection, $data['clientes']);
            $return = response('¡Cola actualizada con éxito!', 200);
        } catch (Exception $e) {
            $error = $e->getMessage(); 
            $return = response('BABEL: ' . $error, 500);
        }
        return $return;
    }

        /* Function: Update the queue' params in the Mikrotik server. */
        function updateClientQueue($connection, $clientes)
        {
            foreach ($clientes as $cliente) {    
            /* Transform kbps to bytes */
            $cliente['download'] = (int) filter_var($cliente['download'], FILTER_SANITIZE_NUMBER_INT) * 1000;
            $cliente['upload'] = (int) filter_var($cliente['upload'], FILTER_SANITIZE_NUMBER_INT) * 1000;
            /* List the queue */
            $query = (new Query("/queue/simple/print"))
                ->where('name', $cliente["cliente_ip"]);
            $response = $connection->query($query)->read();
            /* Add queue */
            if (isset($response[0])) {
                $query = (new Query("/queue/simple/set"))
                    ->equal('.id', $response[0]['.id'])
                    ->equal('max-limit', $cliente['upload'] . "/" . $cliente['download'])
                    ->equal('parent', 'none');
                $response = $connection->query($query)->read();
                /* Only if contract is "active" adds it, otherwise it doesn't */
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

    // ------------------------ Migrate Contracts ---------------------------
    // ---------------------- HTTP Method = [PUT] ---------------------------
    // --------------------------- /router ----------------------------------
    // 
    /* Function: Migrate the contracts from a Mikrotik to another one within 
    // an entered IP. 
    /* Params: The origin Mikrotik's IP and the destination one  */

    public function migrateContract(Request $request)
    {
        try {
            $data = $request->all();    
            if ($request ['ip_router_viejo'] == $request ['ip_router_nuevo']) {
                $return = response ('La IP origen y destino son iguales', 400);
                return $return;
            } 
            else {
                $connection = $this->connection($data['ip_router_nuevo']);
                $this->createClientQueue($connection,$data['clientes']);        
                if (http_response_code($return = 200)) {
                    $connection = $this->connection($data['ip_router_viejo']);
                    $this->removeClientQueue($connection,$data['clientes']);
                    $return = response('¡Clientes migrados con éxito!', 200);
                    return $return;
                }       
            }
        } catch (Exception $e) {
            $error = $e->getMessage(); 
            $return = response('BABEL: ' . $error, 500);
        }
    } 

    // ------------------------ Clean Contracts ---------------------------
    // ---------------------- HTTP Method = [POST] ------------------------
    // --------------------------- /router --------------------------------
    // 
    /* Function: Gets all the contracts from a Mikrotik within an entered IP. 
    // Wipes out all the contracts from the server and then it creates them again
    // as a "simple queue"  */
    /* Params: The Mikrotik's IP  */

    public function cleanRouter (Request $request)
    {
        try {
            /* Gets the IP and then gets all the contracts within that IP */
            $data = $request->all();    
            $clientes = $this->getContract($request);
            
            /* It makes a backup file before applying */
            $backup = $this->backupRouter($request);

            /* Gets the IP and then wipe out the contracts from the server */
            $connection = $this->connection($data['ip']);
            $this->wipeRouterOnlyActives($request);

            /* Gets the IP and then delete the contracts from the server */
            $connection = $this->connection($clientes['ip']);
            $this->createClientQueue($connection,$clientes['clientes']);

            $return = response('¡Operación realizada con éxito!', 200);
            return $return;  

        } catch (Exception $e) {
            $return = response('Ha ocurrido un error al limpiar los contratos', 400);
        }
    } 

    // --------------------------- Wipe Router ------------------------------------
    // ---------------------- HTTP Method = [DEL] ---------------------------------
    // --------------------------- /router ----------------------------------------
    // 
    /* Function: Wipes out all the contracts on both queue list and address list 
    /* CAUTION IT WILL WIPE OUT ALL! USE AT YOUR OWN RISK (Luckily it makes a back-up file before applying, god isn't?)
    /* Params: The Mikrotik's IP  */

    public function wipeRouter (Request $request)
    {   
        try {   
            /* Gets the IP and then gets the contracts within that IP */
            $data = $request->all();    
            $connection = $this->connection($data['ip']);
            $clientes = $this->getContract($request);
            
            /* It makes a backup file before applying */
            $backup = $this->backupRouter($request);
            
            /* It search for all contracts in the address list */
            $query = 
                (new Query('/ip/firewall/address-list/find'))
                    ->where('list'); 
            $ips = $connection->query($query)->read(); 

            $ips=$ips["after"]["ret"];
            $ips=str_replace(';', ',', $ips);

            /* Then removes them */
            $query = 
                (new Query('/ip/firewall/address-list/remove'))
                    ->equal('.id',$ips);
            $ret = $connection->query($query)->read();

            /* It search for all contracts in the queue list */
            $query = 
                (new Query('/queue/simple/find'))
                    ->where('list'); 
            $ips = $connection->query($query)->read();
            $ips=$ips["after"]["ret"];
            $ips=str_replace(';', ',', $ips);

            /* Then removes them */
            $query = 
                (new Query('/queue/simple/remove'))
                    ->equal('.id',$ips);
            $ret = $connection->query($query)->read();   

            $response = response('¡Operación realizada con éxito!', 200);
            return $response;

        } catch (Exception $e) {
            $response = response('Ha ocurrido un error al eliminar los contratos', 400);    
            return $response;
        }
    }

    // ------------------------ Backup Router ------------------------------------
    // ---------------------- HTTP Method = [GET] --------------------------------
    // --------------------------- /router/backup --------------------------------
    // 
    /* Function: Creates a "backup file" of all the contracts, then puts it 
    // into the files directory on the Mikrotik router.
    /* Params: The Mikrotik's IP  */

    public function backupRouter (Request $request) 
    {      
        try {
            /* Gets the IP and then it connects to the server */
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            /* Creates a "backup file" with all the configs, with the name/date/time and stores it into the files directory */
            $query = 
                new Query('/system/backup/save');
            $response = $connection->query($query)->read();
            
            $return = response('¡Operación realizada con éxito!<br>
            Verifique el archivo creado en el directorio <b>"Files"</b> en el Mikrotik', 200);
            return $return;

        } catch (Exception $e) {
            $response = response('Ha ocurrido un error al realizar el back up', 400);    
            return $response;
            }
    }

    // ------------------------- Restore Router ----------------------------------
    // ---------------------- HTTP Method = [POST] -------------------------------
    // --------------------------- /router/restore -------------------------------
    // 
    /* Function: Restores the server to the previous state before applying changes using API
    /* Params: The Mikrotik's IP  */

    public function restoreRouter (Request $request) 
    {      
        try {
            /* Gets the IP and then it connects to the server */
            $data = $request->all();
            $connection = $this->connection($data['ip']);
            /* Gets the name of the generated file */
            $query = 
                (new Query("/file/print"))
                    ->where('type', 'backup');
            $backup = $connection->query($query)->read();              
            $index= array_key_last($backup);
            $namef = $backup[$index]['name'];

            /* Restores the Mikrotik using the backup file */
            $query = 
                (new Query('/system/backup/load'))
                    ->equal('name', $namef);
            $response = $connection->query($query)->read();

            $return = response('¡Equipo restaurado con éxito!', 200);
            return $return; 

        } catch (Exception $e) {
            $response = response('Ha ocurrido un error al restaurar el equipo', 400);    
            return $response;
        }
    }


    // ------------------------ Wipe Router Only Actives --------------------------
    // ---------------------- HTTP Method = [DEL] ---------------------------------
    // --------------------------- /router ----------------------------------------
    // 
    /* Function: Wipes out all the contracts on both queue list and address list of "clientes_activos"
    /* Params: The Mikrotik's IP  */

    public function wipeRouterOnlyActives (Request $request)
    {   
        try {   
            /* Gets the IP and then gets the contracts within that IP */
            $data = $request->all();    
            $connection = $this->connection($data['ip']);
            $clientes = $this->getContract($request);
            
            /* It makes a backup file before applying */
            $backup = $this->backupRouter($request);

            /* It search for all contracts in the queue list */
            $query = 
                (new Query('/queue/simple/find'))
                    ->where('list'); 
            $ips = $connection->query($query)->read();
            $ips=$ips["after"]["ret"];
            $ips=str_replace(';', ',', $ips);
        
            /* Then removes them */
            $query = 
                (new Query('/queue/simple/remove'))
                    ->equal('.id',$ips);
            $del = $connection->query($query)->read();   

            /* Gets all the contracts's ids on the address-list */
            $query = (new Query("/ip/firewall/address-list/print"))
                ->where('list', 'clientes_activos');
            $response = $connection->query($query)->read();

            /* Then removes them */
            foreach ($response as $row) {
                $row = current($row);
                    $query = 
                        (new Query('/ip/firewall/address-list/remove'))
                            ->equal('.id',$row);
                    $del = $connection->query($query)->read();
            }

            $response = response('¡Operación realizada con éxito!', 200);
            return $response;

        } catch (Exception $e) {
            $response = response('Ha ocurrido un error al eliminar los contratos', 400);    
            return $response;
        }
    }

    // ------------------------ Enable Connections on Mikrotik ---------------------
    // ---------------------- HTTP Method = [GET] --------------------------------
    // --------------------------- /connection -----------------------------------------
    // 
    /* Function: Enable connections on Mikrotik. Gets ips in the address-list "cortados", then it
    /* puts them back in "clientes_activos" address-list 
    /* Params: The Mikrotik's IP and clients IP */

    public function findConn (Request $request)
    {
            $data = $request->all();
            $connection = $this->connection($data['ip']);         
            $client_ip = $data['clientes'][0]['cliente_ip'];
            $queue = self::findQueueWithIP($connection,$client_ip);
            if (!empty($queue)) {
                $http_response = [
                    'status' => true,
                    'message' => 'BABEL: Queue ' . $client_ip . ' encontrada'
                ];
                $return = response($http_response, 200);
            
            } else {
                $http_response = [
                    'status' => false,
                    'message' => 'BABEL: Queue ' . $client_ip . ' inexistente'
                ];
                $return = response($http_response, 404);
            }
            return $return;
    }

        function findQueueWithIP($connection, $client_ip)
        {
            $response = false;
            $query =
                (new Query('/queue/simple/print', ['?target=' . $client_ip . '/32']));
            $queue = $connection->query($query)->read();
            if(!empty($queue)){
                $response = $queue;
            }
            return $response;
        }
        // ------------------------ Enable Connections on Mikrotik ---------------------
    // ---------------------- HTTP Method = [PATCH] --------------------------------
    // --------------------------- /connection -----------------------------------------
    // 
    /* Function: Enable connections on Mikrotik. Gets ips in the address-list "cortados", then it
    /* puts them back in "clientes_activos" address-list 
    /* Params: The Mikrotik's IP and clients IP */

    function findConnAddress ($connection,$client_ip)
    {
        

            /* $query = (new Query('/ip/firewall/address-list/print', ['?Address=' . $client_ip])); */

            $query = (new Query('/ip/firewall/address-list/print'))
                ->where('address', $client_ip);
            $response = $connection->query($query)->read();

            if (empty($response)) {
                $http_response = [
                    'status' => false,
                    'message' => 'BABEL: Queue ' . $client_ip . ' sin adress-list'
                ];
                $return = response($http_response, 404);
            } else {
                $address = $response[0]['list'];
                $http_response = [
                    'status' => true,
                    'message' => 'BABEL: Queue ' . $client_ip . ' encontrada en ' . $address
                ];
                $return = response($http_response, 200);
            }
            return $return;
    }
    
    // ------------------------ Enable Connections on Mikrotik ---------------------
    // ---------------------- HTTP Method = [POST] ---------------------------------
    // --------------------------- /v2/connection ----------------------------------
    // 
    /* Function: Enable connections on Mikrotik. Gets ips in the address-list "cortados", then it
    /* puts them back in "clientes_activos" address-list 
    /* Params: The Mikrotik's IP and clients IP */

    public function enableConnection (Request $request)
    {
        try {
            $data = $request->all();
            $connection = $this->connection($data['ip']); 
            $clientes = $data['clientes'];

            foreach ($clientes as $cliente) {
                $client_ip = $cliente['cliente_ip'];
                $queue = $this->findQueueWithIP($connection,$client_ip);    

                # Tries to find the queue
                if (!$queue) {
                    $http_response = [
                        'status' => false,
                        'message' => 'BABEL: Queue ' . $client_ip . ' inexistente'
                    ];
                    #return response($http_response, 404);
                };
                
                $query = (new Query('/ip/firewall/address-list/print', ['?list=clientes_cortados', '?address='. $client_ip]));
                $client = $connection->query($query)->read();

                $query = new Query('/ip/firewall/address-list/set', [
                    '=list=clientes_activos',
                    '=.id='. $client[0]['.id'],
                    ]);
                $response = $connection->query($query)->read();

                # Tries to find that queue in the address list
                /* $address = $this->findConnAddress($connection,$client_ip); */
                
/*                 if(!($address->getStatusCode() == 404)) {
                    $rem_address = $this->removeAddressListCutted($connection,$client_ip);
                }
                
                $add_address = self::addAddressList($connection,$client_ip); */

            }
            $http_response = [
                'status' => true,
                'message' => 'BABEL: Operación realizada con éxito. Cliente habilitado'
                #'results' => $this->getDataMikrotik($request)
            ];
            return response($http_response, 200);

        } catch (\Throwable $th) {
            return response ("BABEL: Ha ocurrido un error", 400);
        }
    }

        function removeAddressListCutted($connection, $ip)
        {
            $query = (new Query("/ip/firewall/address-list/print"))
                ->where('list', 'clientes_cortados')
                ->where('address', $ip);
            $response = $connection->query($query)->read();

            if (isset($response[0])) {
                $query = (new Query("/ip/firewall/address-list/remove"))
                    ->equal('.id', $response[0]['.id']);
                $response = $connection->query($query)->read();
            }
        }

    // ------------------------ Disable Connections on Mikrotik ---------------------
    // ---------------------- HTTP Method = [POST] ---------------------------------
    // --------------------------- /v2/connection ----------------------------------
    // 
    /* Function: Enable connections on Mikrotik. Gets ips in the address-list "cortados", then it
    /* puts them back in "clientes_activos" address-list 
    /* Params: The Mikrotik's IP and clients IP */

    public function disableConnection (Request $request)
    {
        try {
        $data = $request->all();
        $connection = $this->connection($data['ip']);         
        $clientes = $data['clientes'];

        foreach ($clientes as $cliente) {
            $client_ip = $cliente['cliente_ip'];

            $queue = self::findQueueWithIP($connection,$client_ip);    

            # Tries to find the queue
            if (!$queue) {
                $http_response = [
                    'status' => false,
                    'message' => 'BABEL: Queue ' . $client_ip . ' inexistente'
                ];
                #return response($http_response, 404);
            };
            
            # Tries to find that queue in the address list
            $address = self::findConnAddress($connection,$client_ip);

            if(!($address->getStatusCode() == 404)) {
                $rem_address = self::removeAddressListActive($connection,$client_ip);
            }
            
            $add_address = self::addAddressListCutted($connection,$client_ip);

        }

        $http_response = [
            'status' => true,
            'message' => 'BABEL: Operación realizada con éxito. Cliente deshabilitado'
            #'results' => $this->getDataMikrotik($request),
        ];
        
        return response($http_response, 200);

        } catch (\Throwable $th) {
            return response ("BABEL: Ha ocurrido un error", 400);
        }
    }

    function removeAddressListActive($connection, $ip)
    {
        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('list', 'clientes_activos')
            ->where('address', $ip);
        $response = $connection->query($query)->read();

        if (isset($response[0])) {
            $query = (new Query("/ip/firewall/address-list/remove"))
                ->equal('.id', $response[0]['.id']);
            $response = $connection->query($query)->read();
        }
    }

    function addAddressListCutted($connection, $ip)
    {
        $query = (new Query("/ip/firewall/address-list/add"))
            ->equal('list', 'clientes_cortados')
            ->equal('address', $ip);
        $response = $connection->query($query)->read();
    }
}
