<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Controllers\SmartoltController;
use App\Http\Controllers\RadiusController;



class FtthController extends Controller
{
    /* Attributes */
    protected $smartoltController;
    protected $radiusController;

    /* Constructor */
    public function __construct(SmartoltController $smartoltController, RadiusController $radiusController)
    {
        $this->smartoltController = $smartoltController;
        $this->radiusController = $radiusController;
    }

    /**
     * Update the customer connection in Radius and reboot onu in SmartOLT
     *
     * @param Request $request
     * @return void
     */


    public function updateCustomerConnection(Request $request)
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
    }

}
