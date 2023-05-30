<?php

use PHPUnit\Framework\TestCase;
use \RouterOS\Client;
use \RouterOS\Query;
use Illuminate\Http\Request;
use GuzzleHttp\GuzzleClient;

class BabelTest extends TestCase
{
    
    //══════════════════════════════════════════════════════════════════════════════════════════
    //                             TESTS: SERVER CONNECTIONS 
    //══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Test server status
     *
     * @return void
     */

    public function test_BabelServer()
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get(env("SERVER"));
        $this->assertEquals(200, $response->getStatusCode());
    }


    // ══════════════════════════════════════════════════════════════════════════════════════════
    //                              TESTS: SERVER CONNECTIONS 
    // ══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Test connection to RouterOS device.
     *
     * @return void
     */

    public function test_Connection_MikrotikPruebas()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device');
    }

    public function test_Connection_ISPMikrotik01()
    {
        $host = env("ISPMikrotik01");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device');
    }

    public function test_Connection_ISPMikrotik02()
    {
        $host = env("ISPMikrotik02");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device');  
    }

    public function test_Connection_ISPMikrotik03()
    {
        $host = env("ISPMikrotik03");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device'); 
    }

    public function test_Connection_ISPMikrotik04()
    {
        $host = env("ISPMikrotik04");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device'); 
    }

    public function test_Connection_ISPMikrotik05()
    {
        $host = env("ISPMikrotik05");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device'); 
    }

    public function test_Connection_ISPMikrotikx86()
    {
        $host = env("ISPMikrotikx86");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device'); 
    }

    public function test_Connection_ISPMikrotikx862()
    {
        $host = env("ISPMikrotikx862");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);
        $this->assertTrue($client->connect(), 'Failed to connect to RouterOS device'); 
    }

    // ══════════════════════════════════════════════════════════════════════════════════════════
    //                                  TESTS: CRUD OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Test server status
     *
     * @return void
     */

    public function test_CreateContracts()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $query = (new Query("/queue/simple/add"))
            ->equal('name', 'TEST')
            ->equal('target', '1.1.1.1')
            ->equal('max-limit', '3072k/102400k')
            ->equal('queue', "pcq-upload-default/pcq-download-default");
        $response_queue = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/add"))
                ->equal('list', 'clientes_activos')
                ->equal('address', '1.1.1.1');
        $response_address = $client->query($query)->read();

        /* Testing if there are any response */
        $this->assertIsArray($response_queue);
        /* Testing if there is elements in the response */
        $this->assertNotEmpty($response_queue);

        /* Testing if there are any response */
        $this->assertIsArray($response_address);
        /* Testing if there is elements in the response */
        $this->assertNotEmpty($response_address);
    }


    public function test_UpdateContracts()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $query = (new Query("/queue/simple/print"))
            ->where('name', 'TEST');
        $response_print = $client->query($query)->read();

        $query = (new Query("/queue/simple/set"))
            ->equal('.id',  $response_print[0]['.id'])
            ->equal('max-limit', '5072k/202400k')
            ->equal('parent', 'none');
        $response_update = $client->query($query)->read();

        $query = (new Query("/queue/simple/print"))
            ->where('name', 'TEST');
        $response_updated = $client->query($query)->read();

        /* Testing if there are any response */
        $this->assertIsArray($response_print);
        /* Testing if there is elements in the response */
        $this->assertNotEmpty($response_print);

        /* Testing if there are any response */
        $this->assertIsArray($response_update);
        /* Testing if there is elements in the response */
        $this->assertEquals($response_updated[0]['max-limit'], '5072000/202400000');
    }

    public function test_GetContracts()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $query  = new Query('/queue/simple/print');
        $response = $client->query($query)->read();
        /* Testing if there are any response */
        $this->assertIsArray($response);
        /* Testing if there is elements in the response */
        $this->assertArrayHasKey('0', $response);
    }

    public function test_DisableConnection()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $query = (new Query("/queue/simple/print"))
            ->where('name', 'TEST');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('list', 'clientes_activos')
            ->where('address', '1.1.1.1');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/remove"))
            ->equal('.id', $response[0]['.id']);
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/add"))
            ->equal('list', 'clientes_cortados')
            ->equal('address', '1.1.1.1');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('address', '1.1.1.1');
        $result = $client->query($query)->read();

        /* Testing if there is elements in the response */
        $this->assertEquals($result[0]['list'], 'clientes_cortados');
    }

    public function test_EnableConnection()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $query = (new Query("/queue/simple/print"))
            ->where('name', 'TEST');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('list', 'clientes_cortados')
            ->where('address', '1.1.1.1');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/remove"))
            ->equal('.id', $response[0]['.id']);
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/add"))
            ->equal('list', 'clientes_activos')
            ->equal('address', '1.1.1.1');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('address', '1.1.1.1');
        $result = $client->query($query)->read();

        /* Testing if there is elements in the response */
        $this->assertEquals($result[0]['list'], 'clientes_activos');
    }

    public function test_DeleteContracts()
    {
        $host = env("ISPMikrotikPruebas");
        $user = env("USER");
        $pass = env("PASS");
        $port = intval(env("PORT"));
        
        $client = new Client([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port
        ]);

        $query = (new Query("/queue/simple/print"))
            ->where('name', 'TEST');
        $response = $client->query($query)->read();

        $query = (new Query("/queue/simple/remove"))
            ->equal('.id', $response[0][".id"]);
        $response = $client->query($query)->read();

        $query = (new Query("/queue/simple/print"))
        ->where('name', 'TEST');
        $result = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/print"))
            ->where('address', '1.1.1.1');
        $response = $client->query($query)->read();

        $query = (new Query("/ip/firewall/address-list/remove"))
            ->equal('.id', $response[0]['.id']);
        $response = $client->query($query)->read();

        /* Testing if there is elements in the response */
        $this->assertEmpty($result);
    }
}