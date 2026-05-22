<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api')->name('api.v1.')->group(function () {
    // Health check
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'timestamp' => now()->toIso8601String(),
    ]));

    // Authentication routes (public)
    Route::prefix('auth')->withoutMiddleware('api')->group(function () {
        // Register, Login, etc.
    });

    // Protected API routes
    Route::middleware('auth:sanctum')->group(function () {
        // Your authenticated routes here
    });
});
