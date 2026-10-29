<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Printing\PrintV1DashboardController;
use App\Http\Controllers\Web\Printing\PrintV1OutputController;
use App\Http\Controllers\Web\Printing\PrintV1SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('print')->name('print.')->middleware(['auth', 'can:sort.sheet.view'])->group(function () {
    // Dashboard
    Route::get('/', [PrintV1DashboardController::class, 'index'])->name('dashboard');

    // Four Clean Print Sections
    Route::get('/sort-sheet', [PrintV1OutputController::class, 'sortSheet'])->name('sort-sheet');
    Route::get('/selection', [PrintV1OutputController::class, 'selection'])->name('selection');
    Route::get('/segregation-grid', [PrintV1OutputController::class, 'segregationGrid'])->name('segregation-grid');
    Route::get('/shop-wise-portrait', [PrintV1OutputController::class, 'shopWisePortrait'])->name('shop-wise-portrait');
    Route::get('/batch-print', [PrintV1OutputController::class, 'batchPrint'])->name('batch-print');

    // Central Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [PrintV1SettingsController::class, 'index'])->name('index');
        Route::put('/section/{section}', [PrintV1SettingsController::class, 'updateSection'])->name('section.update');
        Route::put('/shops', [PrintV1SettingsController::class, 'updateShops'])->name('shops.update');

        Route::post('/presets', [PrintV1SettingsController::class, 'storePreset'])->name('presets.store');
        Route::put('/presets/{preset}', [PrintV1SettingsController::class, 'updatePreset'])->name('presets.update');
        Route::delete('/presets/{preset}', [PrintV1SettingsController::class, 'destroyPreset'])->name('presets.destroy');
        Route::post('/presets/reorder', [PrintV1SettingsController::class, 'reorderPresets'])->name('presets.reorder');
        Route::post('/presets/copy-legacy', [PrintV1SettingsController::class, 'copyLegacyPresets'])->name('presets.copy-legacy');

        Route::post('/batches', [PrintV1SettingsController::class, 'storePresetBatch'])->name('batches.store');
        Route::delete('/batches/{batch}', [PrintV1SettingsController::class, 'destroyPresetBatch'])->name('batches.destroy');
    });
});
