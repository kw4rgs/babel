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
use Knuckles\Scribe\Attributes\Group;


#[Group("FTTH Controller", "API for managing FTTH connections")]

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
                        'status' => false,
                        'message' => 'Error getting customer connection. Customer not found in Radius.',
                        'data' => [
                            'radius' => $customer_in_radius
                        ]
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);

                } else {
                    // Check if ONU is available in SmartOLT 
                    $customer_in_smartolt = $this->smartoltController->getOnuStatus($request);

                    if (($customer_in_smartolt->getStatusCode() !== 200)) {

                        return response()->json([
                            'status' => true,
                            'message' => 'Error getting ONU status, but customer connection was found in Radius.',
                            'data' => [
                                'radius' => $customer_in_radius->original
                            ]
                        ], Response::HTTP_PARTIAL_CONTENT);

                    } else {
                        // If the radius response is 200 and the smartolt response is 200, return the response
                        return response()->json([
                            'status' => true,
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
                'status' => false,
                'message' => 'Error getting customer connection.',
                'data' => [
                    'error' => $e->getMessage()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Update the customer connection in Radius and reboot onu in SmartOLT
     *
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
                    'status' => true,
                    'message' => 'Customer connection updated in Radius and ONU rebooted in SmartOLT.',
                    'data' => [
                        'radius' => $radius_response->original,
                        'smartolt' => $smartolt_response->original
                    ]
                ], Response::HTTP_OK);

            } elseif (($customer_connection->getStatusCode() === 206)) {

                $radius_response = $this->radiusController->updateUser($request);

                return response()->json([
                    'status' => true,
                    'message' => 'Customer connection updated in Radius, but ONU is not available in SmartOLT.',
                    'data' => [
                        'radius' => $radius_response->original
                    ]
                ], Response::HTTP_PARTIAL_CONTENT);

            } else {

                return response()->json([
                    'status' => false,
                    'message' => 'Error updating customer connection.',
                    'data' => [
                        'radius' => $customer_connection->original
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        } catch (HttpException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Error updating customer connection.',
                'data' => [
                    'error' => $e->getMessage()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
