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
     * It returns the customer status from Radius and SmartOLT
     *
     * @param Request $request
     * @return void
     */  

    public function getCustomerStatus(Request $request)
    {
        $data = $request->all();

        // Call the getUser function from the RadiusController
        $radius_response = $this->radiusController->getUser($request);

        // If the radius response is not 200, return an error
        if ($radius_response->getStatusCode() != 200) {
            return response()->json([
                'status' => false,
                'message' => 'Error getting customer status or customer not found in Radius',
                'data' => $data 
            ], 500);
        }

        // Call the getOnuStatus function from the SmartoltController
        $smartolt_response = $this->smartoltController->getOnuStatus($request);


        if ($smartolt_response['status'] != True) {
            return response()->json([
                'status' => false,
                'message' => 'Error getting ONU status or ONU not available in SmartOLT',
                'data' => $data 
            ], 500);
        }

        // If the radius response is 200 and the smartolt response is 200, return the response
        return response()->json([
            'status' => true,
            'message' => 'Customer status retrieved successfully',
            'data' => [
                'radius' => $radius_response->original,
                'smartolt' => $smartolt_response
            ]
        ], 200);
    }

    /**
     * Update the customer connection in Radius and reboot onu in SmartOLT
     *
     * @param Request $request
     * @return void
     */

    public function updateCustomerConnection(Request $request)
    {
        $data = $request->all();

        // Verify customer exists in both Radius and SmartOLT
        $customer_status = $this->getCustomerStatus($request);

        // To do: If there is a mismatch between username and ip, it should seek the username by the framedipaddress in radius
        if ($customer_status->getStatusCode() != 200) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating customer connection. Customer not found in Radius or SmartOLT due username-ip mismatch',
                'data' => $data 
            ], 500);
        }

        // Call the updateUser function from the RadiusController
        $radius_response = $this->radiusController->updateUser($request);

        // If the radius response is not 200, return an error
        if ($radius_response->getStatusCode() != 200) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating customer connection or customer not found in Radius',
                'data' => $data 
            ], 500);
        }

        // Call the rebootOnu function from the SmartoltController
        $smartolt_response = $this->smartoltController->rebootOnu($request);

        if ($smartolt_response['status'] != True) {
            return response()->json([
                'status' => false,
                'message' => 'Error rebooting ONU or ONU not available in SmartOLT, but customer connection updated in Radius',
                'data' => $data 
            ], 206);
        }

        // If the radius response is 200 and the smartolt response is 200, return the response
        return response()->json([
            'status' => true,
            'message' => 'Customer connection updated successfully',
            'data' => [
                'radius' => $radius_response->original,
                'smartolt' => $smartolt_response
            ]
        ], 200);
    }

}
