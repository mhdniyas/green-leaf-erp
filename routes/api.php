<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\ApiAuthController;
use App\Http\Controllers\Api\Inventory\CategoryController;
use App\Http\Controllers\Api\Inventory\ProductController;
use App\Http\Controllers\Api\Inventory\SortBatchController;
use App\Http\Controllers\Api\Inventory\StockBatchController;
use App\Http\Controllers\Api\Inventory\StockController;
use App\Http\Controllers\Api\Inventory\WastageController;
use App\Http\Controllers\Api\Purchaser\BillPriceApiController;
use App\Http\Controllers\Api\Purchaser\PurchaserReportController;
use App\Http\Controllers\Api\Purchaser\PurchaserSettingsController;
use App\Http\Controllers\Api\Purchasing\GoodsReceivedController;
use App\Http\Controllers\Api\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Api\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Api\Purchasing\SupplierController;
use App\Http\Controllers\Api\Purchasing\VendorAdvanceController;
use App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutController;
use App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutSettingsController;
use App\Http\Controllers\Api\Warehouse\WarehouseHomeSummaryController;
use App\Http\Controllers\Api\Warehouse\WarehouseScopedLoadoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api')->name('api.v1.')->group(function () {

    // Health check
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'timestamp' => now()->toIso8601String(),
    ]));

    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [ApiAuthController::class, 'login'])->name('auth.login');
    });

    // Protected API routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('auth')->group(function () {
            Route::get('/me', [ApiAuthController::class, 'me'])->name('auth.me');
            Route::post('/logout', [ApiAuthController::class, 'logout'])->name('auth.logout');
        });

        // ── Purchaser Bill Price API ─────────────────────────────────────────
        Route::prefix('purchaser')->name('purchaser.')->group(function () {
            Route::post('/special-price/approve', [BillPriceApiController::class, 'approveSpecialPrice'])->name('special-price.approve');
            Route::post('/bill-prices/update', [BillPriceApiController::class, 'updateBillPrice'])->name('bill-prices.update');
            Route::get('/reports/sales-summary', [PurchaserReportController::class, 'salesSummary'])
                ->middleware('can:purchaser.reports.sales.view')
                ->name('reports.sales-summary');
            Route::get('/reports/item-summary', [PurchaserReportController::class, 'itemSummary'])
                ->middleware('can:purchaser.reports.items.view')
                ->name('reports.item-summary');
            Route::get('/settings', [PurchaserSettingsController::class, 'show'])
                ->name('settings.show');
            Route::post('/settings', [PurchaserSettingsController::class, 'update'])
                ->name('settings.update');
        });

        // ── Warehouse Operations Summary & Loadout API ────────────────────────
        Route::get('warehouse/home-summary', [WarehouseHomeSummaryController::class, 'show'])->name('warehouse.home-summary');
        Route::prefix('warehouse/loadout')->name('warehouse.loadout.')->group(function () {
            Route::get('/', [ApiWarehouseLoadoutController::class, 'index'])->name('index');
            Route::get('/settings', [ApiWarehouseLoadoutSettingsController::class, 'show'])->name('settings.show');
            Route::get('/{shopOrder}', [ApiWarehouseLoadoutController::class, 'show'])->name('show');
            Route::post('/{shopOrder}/initialize', [ApiWarehouseLoadoutController::class, 'initialize'])->name('initialize');
            Route::post('/{shopOrder}/save', [ApiWarehouseLoadoutController::class, 'save'])->name('save');
            Route::post('/{shopOrder}/move-to-delivery', [ApiWarehouseLoadoutController::class, 'moveToDelivery'])->name('move-to-delivery');
            Route::post('/{shopOrder}/move-to-partial-delivery', [ApiWarehouseLoadoutController::class, 'moveToPartialDelivery'])->name('move-to-partial-delivery');
            Route::post('/{shopOrder}/move-to-loadout', [ApiWarehouseLoadoutController::class, 'moveToLoadout'])->name('move-to-loadout');
            Route::post('/{shopOrder}/load-all', [ApiWarehouseLoadoutController::class, 'loadAll'])->name('load-all');
            Route::get('/{shopOrder}/addons', [ApiWarehouseLoadoutController::class, 'addonProducts'])->name('addons');
            Route::post('/{shopOrder}/addon', [ApiWarehouseLoadoutController::class, 'storeAddon'])->name('addon.store');
        });

        Route::prefix('warehouse-loadout/{warehouse}')->name('warehouse-scoped-loadout.')->group(function () {
            Route::get('/orders', [WarehouseScopedLoadoutController::class, 'index'])->name('orders.index');
            Route::get('/orders/{shopOrder}', [WarehouseScopedLoadoutController::class, 'show'])->name('orders.show');
            Route::patch('/orders/{shopOrder}/items', [WarehouseScopedLoadoutController::class, 'updateItems'])->name('orders.items.update');
            Route::post('/orders/{shopOrder}/complete', [WarehouseScopedLoadoutController::class, 'complete'])->name('orders.complete');
        });

        // ── Inventory ─────────────────────────────────────────────────────────
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::apiResource('categories', CategoryController::class);
            Route::get('products/sync', [ProductController::class, 'sync'])->name('products.sync');
            Route::apiResource('products', ProductController::class);
            Route::apiResource('batches', StockBatchController::class)->except(['update']);
            Route::post('batches/{batch}/sort', SortBatchController::class)->name('batches.sort');
            Route::get('stock', [StockController::class, 'index'])->name('stock.index');
            Route::get('movements', [StockController::class, 'movements'])->name('movements.index');
            Route::get('wastage', [WastageController::class, 'index'])->name('wastage.index');
            Route::post('wastage', [WastageController::class, 'store'])->name('wastage.store');
        });

        // ── Purchasing ────────────────────────────────────────────────────────
        Route::prefix('purchasing')->name('purchasing.')->group(function () {
            Route::apiResource('suppliers', SupplierController::class);
            Route::apiResource('orders', PurchaseOrderController::class);
            Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');
            Route::get('grns/pending-suggestions', [GoodsReceivedController::class, 'pendingSuggestions'])->name('grns.pending-suggestions');
            Route::get('grns/advance-match-suggestions', [GoodsReceivedController::class, 'advanceMatchSuggestions'])->name('grns.advance-match-suggestions');
            Route::get('grns/advance-match-candidates', [GoodsReceivedController::class, 'advanceMatchCandidates'])->name('grns.advance-match-candidates');
            Route::apiResource('grns', GoodsReceivedController::class)->only(['index', 'store', 'show']);
            Route::post('grns/{grn}/link-bill', [GoodsReceivedController::class, 'linkBill'])->name('grns.link-bill');
            Route::post('grns/{grn}/match-bill', [GoodsReceivedController::class, 'matchBill'])->name('grns.match-bill');
            Route::put('grns/{grn}/items', [GoodsReceivedController::class, 'updateItems'])->name('grns.items.update');
            Route::apiResource('invoices', PurchaseInvoiceController::class)->only(['index', 'store', 'show']);
            Route::patch('invoices/{invoice}/status', [PurchaseInvoiceController::class, 'updateStatus'])->name('invoices.update-status');
            Route::apiResource('vendor-advances', VendorAdvanceController::class)->only(['index', 'store', 'show']);
        });
    });
});

// Fallback un-prefixed API routes for production compatibility
Route::get('/health', fn () => response()->json([
    'success' => true,
    'message' => 'API is healthy',
    'timestamp' => now()->toIso8601String(),
]));
Route::post('/auth/login', [ApiAuthController::class, 'login']);
