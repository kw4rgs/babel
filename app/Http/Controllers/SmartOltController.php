<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class SmartOltController extends Controller
{
    
        /*
        *   Validate External ID
        *   @param Request $request
        *   @return array
        */

        private function validateExternalId (Request $request)
        {
            $data = $request->input();

            $validator = Validator::make($request->all(), [
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
    *   Get ONU Status
    *   @param Request $request
    *   @return Illuminate\Http\Response
    */

    public function getOnuStatus(Request $request)
    {
        try {
            $validate = $this->validateExternalId($request);
                
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid data.',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {

                $onu_external_id = $request->onu_sn;
            
                $curl = curl_init();
            
                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('SMARTOLT_URL') . '/api/onu/get_onu_status/' . $onu_external_id,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'X-Token: ' . env('SMARTOLT_APIKEY')
                    ),
                ));
            
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Get the HTTP code
            
                curl_close($curl);

                if ($httpCode != 200) {

                    return response()->json([
                        'status' => 'error',
                        'message' => 'ONU Status not found',
                    ], Response::HTTP_NOT_FOUND);
                } else {

                return response()->json([
                    'status' => 'success',
                    'message' => 'ONU Status retrieved successfully',
                    'detail' => json_decode($response, true)
                ], Response::HTTP_OK);
            }

            }

        } catch (HttpException $e) {
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrieving ONU Status',
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    /* 
    *   Reboot ONU
    *   @param Request $request 
    *   @return Illuminate\Http\Response
    */

    public function rebootOnu(Request $request)
    {
        try {
            $validate = $this->validateExternalId($request);
                
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid data.',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {

                $onu_external_id = $request->onu_sn;

                $curl = curl_init();
            
                curl_setopt_array($curl, array(
                    CURLOPT_URL => env('SMARTOLT_URL') . '/api/onu/reboot/' . $onu_external_id,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_HTTPHEADER => array(
                        'X-Token: ' . env('SMARTOLT_APIKEY')
                    ),
                ));
            
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Get the HTTP code
            
                curl_close($curl);

                if ($httpCode != 200) {

                    return response()->json([
                        'status' => 'error',
                        'message' => 'ONU Status not found',
                    ], Response::HTTP_NOT_FOUND);
                } else {

                return response()->json([
                    'status' => 'success',
                    'message' => 'ONU Status rebooted successfully',
                    'detail' => json_decode($response, true)
                ], Response::HTTP_OK);
            }

            }

        } catch (HttpException $e) {
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while rebooting ONU',
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

}