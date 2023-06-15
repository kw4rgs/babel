<?php

namespace App\Http\Middleware;

use Closure;

class TimeoutMiddleware
{
    public function handle($request, Closure $next)
    {
        // Get the configured timeout value from the configuration file
        $timeout = config('timeout.timeout');

        // Set the timeout for the request
        $request->server->set('REQUEST_TIME_OUT', $timeout);

        return $next($request);
    }
}
