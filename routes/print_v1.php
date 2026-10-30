<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Printing\PrintV1SegregationGridController;
use App\Http\Controllers\Web\Printing\PrintV1SelectionController;
use App\Http\Controllers\Web\Printing\PrintV1ShopOrderController;
use App\Http\Controllers\Web\Printing\PrintV1SortSheetController;
use Illuminate\Support\Facades\Route;

Route::prefix('print')->name('print.')->middleware(['auth', 'can:sort.sheet.view'])->group(function () {
    // Sort Sheet V1 as default entry point
    Route::get('/', fn () => redirect()->route('print.sort-sheet'))->name('dashboard');

    // Sort Sheet V1 Web Page (Filters + Preview on same page)
    Route::get('/sort-sheet', [PrintV1SortSheetController::class, 'index'])->name('sort-sheet');

    // Clean Printable Sort Sheet View
    Route::get('/sort-sheet/print', [PrintV1SortSheetController::class, 'print'])->name('sort-sheet.print');

    // Selection V1 Web Page (Filters + Preview on same page)
    Route::get('/selection', [PrintV1SelectionController::class, 'index'])->name('selection');

    // Clean Printable Selection View
    Route::get('/selection/print', [PrintV1SelectionController::class, 'print'])->name('selection.print');

    // Segregation Grid V1 Web Page (Filters + Preview on same page)
    Route::get('/segregation-grid', [PrintV1SegregationGridController::class, 'index'])->name('segregation-grid');

    // Clean Printable Segregation Grid View
    Route::get('/segregation-grid/print', [PrintV1SegregationGridController::class, 'print'])->name('segregation-grid.print');

    // Shop Order V1 Web Page (Formerly Shop Wise Portrait)
    Route::get('/shop-order', [PrintV1ShopOrderController::class, 'index'])->name('shop-order');

    // Clean Printable Shop Order View (A4 Portrait, 1 Shop per page)
    Route::get('/shop-order/print', [PrintV1ShopOrderController::class, 'print'])->name('shop-order.print');

    // Safe backwards compatibility redirect from shop-wise-portrait to shop-order
    Route::get('/shop-wise-portrait', [PrintV1ShopOrderController::class, 'legacyRedirect'])->name('shop-wise-portrait');
});
