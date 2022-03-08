<?php

namespace App\Traits;

use Illuminate\Http\Response;

trait ApiResponser
{

    /**
     * Crea una respuesta de exito
     * @param string|array $data 
     * @param int $code
     * @return Illuminate\Http\JsonResponse
     */
    public function successResponse($data, $code = Response::HTTP_OK)
    {
        return response()->json(
            [
            'data' => $data
            ], $code);
    }

    /**
     * Crea una respuesta de error
     * @param string $message 
     * @param int $code
     * @return Illuminate\Http\JsonResponse
     */
    public function errorResponse($message, $code)
    {
        return response()->json(
            [
            'error' => $message, 
            'code' => $code
            ], $code);
    }

}

