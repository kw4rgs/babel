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


    //             // Call the getUser function from the RadiusController
    //             if (($customer_on_radius->getStatusCode() !== 200)) {

    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Error getting customer connection. Customer not found in Radius.',
    //                     'data' => [
    //                         'radius' => $customer_on_radius
    //                     ]
    //                 ], Response::HTTP_UNPROCESSABLE_ENTITY);

    //             } else {
    //                 $radius_response = $this->radiusController->getUser($request);
    //             }
                
    //             // Check if ONU serial number is provided
    //             if (!isset($data['onu_sn'])) {

    //                     return response()->json([
    //                     'status' => true,
    //                     'message' => 'Error getting ONU status. ONU serial number not provided, but customer connection was found in Radius.',
    //                     'data' => [
    //                         'radius' => $radius_response->original
    //                     ]
    //                 ], Response::HTTP_PARTIAL_CONTENT);
                
    //             } else {
    //                 // Call the getOnuStatus function from the SmartoltController
    //                 $customer_on_smartolt = $this->smartoltController->getOnuStatus($request);
    //                 $get_customer_smartolt = json_decode($customer_on_smartolt->getContent(), true);

    //                 // Check if customer is not available in SmartOLT
    //                 if ($get_customer_smartolt['code'] !== 200) {

    //                     return response()->json([
    //                         'status' => true,
    //                         'message' => 'ONU not available in SmartOLT, but customer connection was found in Radius',
    //                         'data' => [
    //                             'radius' => $radius_response->original
    //                         ]
    //                     ], Response::HTTP_PARTIAL_CONTENT);

    //                 }
    //             }
                
    //             // If the radius response is 200 and the smartolt response is 200, return the response
    //             return response()->json;
        
    //         }
    //     }
    
    // }


    /**
     * Update the customer connection in Radius and reboot onu in SmartOLT
     *
     * @param Request $request
     * @return Illuminate\Http\Response
     */

    /* public function updateCustomerConnection(Request $request)
    {
        try {
            $data = $request->all();

            // Verify customer is provided
            if (!isset($data['username'])) {
                
                return response()->json([
                    'status' => false,
                    'message' => 'Error updating customer connection. Username not provided.',
                    'data' => [
                        'radius' => $radius_response->original
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);

            } else {
                $customer_on_radius = $this->radiusController->getUser($request);
            }

            // Call the updateUser function from the RadiusController
            if (($customer_on_radius->getStatusCode() !== 200)) {

                return response()->json([
                    'status' => false,
                    'message' => 'Error updating customer connection. Customer not found in Radius.',
                    'data' => [
                        'radius' => $customer_on_radius
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);

            } else {
                $radius_response = $this->radiusController->updateUser($request);
            }
            
            // Check if ONU serial number is provided
            if (!isset($data['onu_sn'])) {

                    return response()->json([
                    'status' => true,
                    'message' => 'Error rebooting ONU. ONU serial number not provided, but customer connection was updated in Radius.',
                    'data' => [
                        'radius' => $radius_response->original
                    ]
                ], Response::HTTP_PARTIAL_CONTENT);
            
            } else {
                // Call the getOnuStatus function from the SmartoltController
                $customer_on_smartolt = $this->smartoltController->getOnuStatus($request);
                $get_customer_smartolt = json_decode($customer_on_smartolt->getContent(), true);

                // Check if customer is not available in SmartOLT
                if ($get_customer_smartolt['code'] !== 200) {

                    return response()->json([
                        'status' => true,
                        'message' => 'ONU not available in SmartOLT, but customer connection was updated in Radius',
                        'data' => [
                            'radius' => $radius_response->original
                        ]
                    ], Response::HTTP_PARTIAL_CONTENT);

                }
            }
            // Call the rebootOnu function from the SmartoltController
            $smartolt_response = $this->smartoltController->rebootOnu($request);
            $smartolt_reboot = json_decode($smartolt_response->getContent(), true);

            // Check if customer is not available in SmartOLT
            if ($smartolt_reboot['code'] !== 200) {

                return response()->json([
                    'status' => true,
                    'message' => 'Error rebooting ONU, but customer connection was updated in Radius',
                    'data' => [
                        'radius' => $radius_response->original,
                        'smartolt' => $smartolt_response->original
                    ]
                ], Response::HTTP_PARTIAL_CONTENT);

            }
            
            // If the radius response is 200 and the smartolt response is 200, return the response
            return response()->json([

                'status' => true,
                'message' => 'Customer connection updated successfully and ONU rebooted',
                'data' => [
                    'radius' => $radius_response->original,
                    'smartolt' => $smartolt_response->original
                ]
            ], Response::HTTP_OK);

        } catch (Exception $e) {
            return response()->json([

                'status' => false,
                'message' => 'Error updating customer connection',
                'data' => [
                    'radius' => $radius_response->original,
                    'smartolt' => $smartolt_response->original
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        }
    } */

}
