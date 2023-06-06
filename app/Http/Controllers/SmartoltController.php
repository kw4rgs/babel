<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class SmartoltController extends Controller
{
    
    public function getOnuStatus(Request $request)
    {
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
    
        return response()->json([
            'code' => $httpCode,
            'response' => json_decode($response, true)
        ]);
    }

    public function rebootOnu(Request $request)
    {
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
    
        return response()->json([
            'code' => $httpCode,
            'response' => json_decode($response, true)
        ]);
    }
}
