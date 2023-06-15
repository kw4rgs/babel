<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Controllers\SmartOltController;
use App\Http\Controllers\RadiusController;


/**
 * @group FTTH Controller
 *
 * API for managing FTTH connections.
 */

class FTTHController extends Controller
{
    /* Attributes */
    protected $smartoltController;
    protected $radiusController;

    /* Constructor */
    public function __construct(SmartOltController $smartoltController, RadiusController $radiusController)
    {
        $this->smartoltController = $smartoltController;
        $this->radiusController = $radiusController;
    }

        /* 
        *   Validate Input
        *   @param Request $request
        *   @return array
        */

        private function validateInput (Request $request)
        {
            $data = $request->input();

            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'onu_sn' => 'required|string',
            ]);
        
            if ($validator->fails()) {

                $response = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Invalid data.',
                    'detail' => $validator->errors(),
                ];

            } else {
                $response = [
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'Data is valid.',
                ];
            }

            return $response;
        }


    /**
     * Get Customer Connection
     * 
     * This endpoint allows you to get the customer connection params from Radius and status from SmartOLT.
     * 
     * @authenticated
     * 
     * @bodyParam username string required The username in radius Example: 10.132.139.232
     * @bodyParam onu_sn string required The ONU serial number Example: HWTC31E0CJSD
     * 
     * @response 200 {"status":true,"message":"Customer connection found in Radius and ONU is available in SmartOLT.","data":{"radius":{"status":"success","message":"User found","detail":{"id":29881,"name":"Gustavo   Ivan   Escalon CastroESCALON CASTRO","username":"10.112.239.132","password":"password","bandwith_plan":"61440k/61440k","main_ip":"10.192.310.114","node":"CASTROL FTTH","created_at":"0000-00-00","updated_at":"2023-06-08"}},"smartolt":{"status":"success","message":"ONU Status retrieved successfully","detail":{"status":true,"onu_status":"Online"}}}}
     * @response 206 {"status":"success","message":"Error getting ONU status, but customer connection was found in Radius.","data":{"radius":{"status":"success","message":"User found","detail":{"id":29881,"name":"Gustavo   Ivan   Escalon CastroESCALON CASTRO","username":"10.112.239.232","password":"b3afs82","bandwith_plan":"61440k/61440k","main_ip":"10.192.510.114","node":"CASTROL FTTH","created_at":"0000-00-00","updated_at":"2023-06-08"}}}}
     * @response 400 {"status":"error","message":"Invalid data.","detail":{"username":["The username field is required."],"onu_sn":["The onu sn field is required."]}}
     * @response 404 {"status":"error","message":"Error getting customer connection. Customer not found in Radius.","data":{"radius":{"status":"error","message":"User does not exist."}}}
     * @response 500 {"status": "error", "message": "Error getting customer connection."}
     * 
    */

    /*
    *   Gets the customer connection params from Radius and status from SmartOLT
    *   @param Request $request
    *   @param string $username
    *   @param string $onu_sn
    *   @return Illuminate\Http\Response
    */

    public function getCustomerConnection(Request $request)
    {
        try {

            $validate = $this->validateInput($request);
                
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid data.',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else { 
                $data = $request->all();
                // Check if customer exists in Radius
                $customer_in_radius = $this->radiusController->getUser($request);

                if (($customer_in_radius->getStatusCode() !== 200)) {

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Error getting customer connection. Customer not found in Radius.',
                        'data' => [
                            'radius' => $customer_in_radius->original
                        ]
                    ], Response::HTTP_NOT_FOUND);

                } else {
                    // Check if ONU is available in SmartOLT 
                    $customer_in_smartolt = $this->smartoltController->getOnuStatus($request);

                    if (($customer_in_smartolt->getStatusCode() !== 200)) {

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Error getting ONU status, but customer connection was found in Radius.',
                            'data' => [
                                'radius' => $customer_in_radius->original
                            ]
                        ], Response::HTTP_PARTIAL_CONTENT);

                    } else {
                        // If the radius response is 200 and the smartolt response is 200, return the response
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Customer connection found in Radius and ONU is available in SmartOLT.',
                            'data' => [
                                'radius' => $customer_in_radius->original,
                                'smartolt' => $customer_in_smartolt->original
                            ]
                        ], Response::HTTP_OK);
                    }
                }
            }
        } catch (HttpException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error getting customer connection.',
                'data' => [
                    'error' => $e->getMessage()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Update Customer Connection
     *
     * This endpoint allows you to update the customer connection params in Radius and reboot the ONU via SmartOLT.
     *
     * @authenticated
     *
     * @bodyParam name string required The customer name. Example: Kwargs
     * @bodyParam username string required The username in radius. Example: 1.1.1.1
     * @bodyParam bandwith_plan string required The bandwidth plan. Example: 61440k/61440k
     * @bodyParam node string required The node. Example: CASTROL FTTH
     * @bodyParam main_ip string required The main IP. Example: 10.192.310.11
     * @bodyParam onu_sn string required The ONU serial number. Example: HWTC31E0CJSF
     *
     * @response 200 {"status": true, "message": "Customer connection updated in Radius and ONU rebooted in SmartOLT.", "data": {"radius": {"status": "success", "message": "Bandwidth updated successfully", "detail": {"status": "success", "message": "User found", "detail": {"id": 29881, "name": "Kwargs", "username": "10.112.239.232", "password": "b3a7fw82", "bandwith_plan": "61440k/61440k", "main_ip": "10.192.210.114", "node": "CASTROL FTTH", "created_at": "0000-00-00", "updated_at": "2023-06-08"}}}, "smartolt": {"status": "success", "message": "ONU Status rebooted successfully", "detail": {"status": true, "response": "Device reboot command sent"}}}}
     * @response 206 {"status": "success", "message": "Customer connection updated in Radius, but ONU is not available in SmartOLT.", "data": {"radius": {"status": "success", "message": "User found", "detail": {"id": 29881, "name": "Kwargs", "username": "10.112.239.232", "password": "b3afswe82", "bandwith_plan": "61440k/61440k", "main_ip": "10.192.510.114", "node": "CASTROL FTTH", "created_at": "0000-00-00", "updated_at": "2023-06-08"}}}}
     * @response 422 {"status": "error", "message": "Error updating customer connection.", "data": {"radius": {"status": "error", "message": "User does not exist."}}}
     * @response 500 {"status": "error", "message": "Error getting customer connection."}
     *
     */

    /*
     * Update the customer connection in Radius and reboot ONU in SmartOLT.
     * @param Request $request
     * @param string $name
     * @param string $username
     * @param string $bandwidth_plan
     * @param string $node
     * @param string $main_ip
     * @param string $onu_sn
     * @return Illuminate\Http\Response
     */

    public function updateCustomerConnection (Request $request)
    {
        try {
            // Gets the customer connection response from getCustomerConnection
            $customer_connection = $this->getCustomerConnection($request);

            // Checks if the customer connection response is 200, if so customer connection in Radius is updated and ONU is rebooted, 
            // If the customer connection response is 206, the customer connection in Radius is updated but ONU is not rebooted
            if (($customer_connection->getStatusCode() === 200)) {

                $radius_response = $this->radiusController->updateUser($request);
                $smartolt_response = $this->smartoltController->rebootOnu($request);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Customer connection updated in Radius and ONU rebooted in SmartOLT.',
                    'data' => [
                        'radius' => $radius_response->original,
                        'smartolt' => $smartolt_response->original
                    ]
                ], Response::HTTP_OK);

            } elseif (($customer_connection->getStatusCode() === 206)) {

                $radius_response = $this->radiusController->updateUser($request);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Customer connection updated in Radius, but ONU is not available in SmartOLT.',
                    'data' => [
                        'radius' => $radius_response->original
                    ]
                ], Response::HTTP_PARTIAL_CONTENT);

            } else {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error updating customer connection.',
                    'data' => [
                        'radius' => $customer_connection->original
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        } catch (HttpException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error updating customer connection.',
                'data' => [
                    'error' => $e->getMessage()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function updateCustomersConnection(Request $request)
    {
        try {
            $requests = $request->input('customer_connections', []);
            $response_data = [];
            $overallStatus = Response::HTTP_OK;
    
            foreach ($requests as $requestData) {
                // Create a new Request instance
                $connectionRequest = Request::createFromBase(new \Symfony\Component\HttpFoundation\Request());
    
                // Set the converted array as the request data
                $connectionRequest->merge($requestData);
    
                $customer_connection = $this->getCustomerConnection($connectionRequest);
                $response = [
                    'username' => $requestData['username'],
                    'status' => $customer_connection->getStatusCode(),
                ];
    
                if ($customer_connection->getStatusCode() === Response::HTTP_OK) {
                    $radius_response = $this->radiusController->updateUser($connectionRequest);
                    $smartolt_response = $this->smartoltController->rebootOnu($connectionRequest);
    
                    $response['message'] = 'Customer connection updated in Radius and ONU rebooted in SmartOLT.';
                    $response['data'] = [
                        'radius' => $radius_response->original,
                        'smartolt' => $smartolt_response->original
                    ];
                } elseif ($customer_connection->getStatusCode() === Response::HTTP_PARTIAL_CONTENT) {
                    $radius_response = $this->radiusController->updateUser($connectionRequest);
    
                    $response['message'] = 'Customer connection updated in Radius, but ONU is not available in SmartOLT.';
                    $response['data'] = [
                        'radius' => $radius_response->original
                    ];
                } else {
                    $response['message'] = 'Error updating customer connection.';
                    $response['data'] = [
                        'radius' => $customer_connection->original
                    ];
                    $overallStatus = Response::HTTP_PARTIAL_CONTENT; // Set overall status to 206 (Partial Content)
                }
    
                $response_data[] = $response;
            }
    
            return response()->json($response_data, $overallStatus);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating customer connection.',
                'data' => [
                    'error' => $e->getMessage()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateCustomersConnectionBulk (Request $request)
    {
        try {
            $bulkCustomer = $request->input('bulk', []);
            $response_data = [];
    
            foreach ($bulkCustomer as $customer) {
                $framed_ip_address = $customer['framed_ip'];
                $bandwidth_plan = $customer['bandwidth_plan'];
                $onu_sn = $customer['onu_sn'];
    
                // Step 1: Search for the username based on framed IP in the radius database
                $username = $this->radiusController->searchUsernameByFramedIP($framed_ip_address);
    
                if (!$username) {
                    $response_data[] = [
                        'framed_ip' => $framed_ip_address,
                        'status' => Response::HTTP_NOT_FOUND,
                        'message' => 'Username not found for framed IP',
                    ];
                    continue;
                }
                
                $username = isset($username->original['detail']['username']) ? $username->original['detail']['username'] : $customer['framed_ip'];

                // Step 2: Update the mikrokit-rate-limit using the bandwidth plan
                $radius_response = $this->radiusController->updateUserBandwidthByIP($username, $bandwidth_plan);
 
                if ($radius_response->getStatusCode() !== Response::HTTP_OK) {
                    $response_data[] = [
                        'framed_ip' => $framed_ip_address,
                        'status' => $radius_response->getStatusCode(),
                        'message' => 'Error updating mikrokit-rate-limit',
                        'data' => [
                            'radius' => $radius_response->original,
                        ],
                    ];
                    continue;
                }
    
                // Step 3: Reboot the ONU using external ID (onu_sn)
                if ($onu_sn) {
                    $smartolt_response = $this->smartoltController->rebootOnuByExternalId($onu_sn);
    
                    if ($smartolt_response->getStatusCode() !== Response::HTTP_OK) {
                        $response_data[] = [
                            'framed_ip' => $framed_ip_address,
                            'status' => $smartolt_response->getStatusCode(),
                            'message' => 'Error rebooting ONU',
                            'data' => [
                                'radius' => $radius_response->original,
                                'smartolt' => $smartolt_response->original,
                            ],
                        ];
                        continue;
                    }
                }
    
                // All operations successful
                $response_data[] = [
                    'framed_ip' => $framed_ip_address,
                    'status' => Response::HTTP_OK,
                    'message' => 'Operations completed successfully',
                    'data' => [
                        'radius' => $radius_response->original,
                        'smartolt' => $smartolt_response->original ?? null,
                    ],
                ];
            }
    
            return response()->json($response_data, Response::HTTP_OK);
        } catch (HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating customer connection.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
}
