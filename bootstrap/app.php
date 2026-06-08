<?php

use App\Http\Middleware\ApiVersionMiddleware;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ApiVersionMiddleware::class,
        ]);

        $middleware->web(append: [
            SecureHeaders::class,
            UpdateLastSeen::class,
        ]);

        $middleware->append(SecureHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
