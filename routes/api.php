<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Inventory\CategoryController;
use App\Http\Controllers\Api\Inventory\ProductController;
use App\Http\Controllers\Api\Inventory\SortBatchController;
use App\Http\Controllers\Api\Inventory\StockBatchController;
use App\Http\Controllers\Api\Inventory\StockController;
use App\Http\Controllers\Api\Inventory\WastageController;
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
        // Phase 1B: POST /auth/login, /auth/logout, /auth/me — to be added
    });

    // Protected API routes
    Route::middleware('auth:sanctum')->group(function () {

        // ── Inventory ─────────────────────────────────────────────────────────
        Route::prefix('inventory')->name('inventory.')->group(function () {

            // Categories
            Route::apiResource('categories', CategoryController::class);

            // Products
            Route::apiResource('products', ProductController::class);

            // Stock Batches + Sorting
            Route::apiResource('batches', StockBatchController::class)->except(['update']);
            Route::post('batches/{batch}/sort', SortBatchController::class)->name('batches.sort');

            // Stock levels + movements (read-only)
            Route::get('stock', [StockController::class, 'index'])->name('stock.index');
            Route::get('movements', [StockController::class, 'movements'])->name('movements.index');

            // Wastage
            Route::get('wastage', [WastageController::class, 'index'])->name('wastage.index');
            Route::post('wastage', [WastageController::class, 'store'])->name('wastage.store');
        });
    });
});
