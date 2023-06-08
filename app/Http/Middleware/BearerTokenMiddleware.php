<?php

namespace App\Http\Middleware;

use Closure;

class BearerTokenMiddleware
{
    public function handle($request, Closure $next)
    {
        // Retrieve the bearer token from the request headers
        $token = $request->header('Authorization');

        // Perform bearer token validation
        if (!$this->isValidToken($token)) {
            return response('Unauthorized.', 401);
        }

        return $next($request);
    }

    private function isValidToken($token)
    {
        // Retrieve the valid bearer token from the .env file
        $validToken = env('BEARER_TOKEN');

        // Validate the token against the valid token
        return $token === "Bearer $validToken";
    }
}
