<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-API-Version', 'v1');
        } else {
            $response->headers->set('X-API-Version', 'v1');
        }

        return $response;
    }
}
