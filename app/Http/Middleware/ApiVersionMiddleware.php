<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiVersionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure API version is set in response headers
        $response = $next($request);
        $response->header('X-API-Version', 'v1');
        $response->header('Content-Type', 'application/json');

        return $response;
    }
}
