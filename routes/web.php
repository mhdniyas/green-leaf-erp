<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Finance\AccountController;
use App\Http\Controllers\Web\Finance\ExpenseController;
use App\Http\Controllers\Web\Finance\FinancialReportController;
use App\Http\Controllers\Web\Finance\LedgerController;
use App\Http\Controllers\Web\Inventory\BatchController;
use App\Http\Controllers\Web\Inventory\ProductController;
use App\Http\Controllers\Web\Inventory\StockController;
use App\Http\Controllers\Web\Inventory\WastageController;
use App\Http\Controllers\Web\Purchasing\GoodsReceivedController;
use App\Http\Controllers\Web\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Web\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Web\Purchasing\SupplierController;
use App\Http\Controllers\Web\RequisitionController;
use App\Http\Controllers\Web\Sales\CustomerController;
use App\Http\Controllers\Web\Sales\PaymentController;
use App\Http\Controllers\Web\Sales\SalesInvoiceController;
use App\Http\Controllers\Web\Sales\SalesOrderController;
use App\Http\Controllers\Web\ShopPresetController;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', fn () => redirect()->route('login'));

// Guest routes (unauthenticated only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.submit');
});

// Stub: password reset (required by blade for the link to work)
Route::get('/forgot-password', fn () => redirect()->route('login'))->name('password.request');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // ── Inventory ──────────────────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->group(function () {

        // Products
        Route::resource('products', ProductController::class);

        // Stock levels
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');

        // Batches + Sorting
        Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
        Route::get('batches/create', [BatchController::class, 'create'])->name('batches.create');
        Route::post('batches', [BatchController::class, 'store'])->name('batches.store');
        Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
        Route::get('batches/{batch}/sort', [BatchController::class, 'sort'])->name('batches.sort');
        Route::post('batches/{batch}/sort', [BatchController::class, 'processSort'])->name('batches.sort.process');
        Route::delete('batches/{batch}', [BatchController::class, 'destroy'])->name('batches.destroy');

        // Wastage
        Route::get('wastage', [WastageController::class, 'index'])->name('wastage.index');
        Route::get('wastage/create', [WastageController::class, 'create'])->name('wastage.create');
        Route::post('wastage', [WastageController::class, 'store'])->name('wastage.store');
    });

    // ── Purchasing ─────────────────────────────────────────────────────────
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        // Suppliers
        Route::resource('suppliers', SupplierController::class);

        // Purchase Orders
        Route::resource('orders', PurchaseOrderController::class);
        Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');

        // Goods Receipts
        Route::resource('grns', GoodsReceivedController::class)->only(['index', 'create', 'store', 'show']);

        // Invoices
        Route::resource('invoices', PurchaseInvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('invoices/{invoice}/status', [PurchaseInvoiceController::class, 'updateStatus'])->name('invoices.update-status');
    });

    // ── Sales ──────────────────────────────────────────────────────────────
    Route::prefix('sales')->name('sales.')->group(function () {
        // Customers
        Route::resource('customers', CustomerController::class);

        // Sales Orders
        Route::resource('orders', SalesOrderController::class);
        Route::post('orders/{order}/confirm', [SalesOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/dispatch', [SalesOrderController::class, 'dispatch'])->name('orders.dispatch');
        Route::post('orders/{order}/cancel', [SalesOrderController::class, 'cancel'])->name('orders.cancel');

        // Sales Invoices
        Route::resource('invoices', SalesInvoiceController::class)->only(['index', 'create', 'store', 'show']);

        // Payments
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    });

    // ── Finance & Accounting ────────────────────────────────────────────────
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::resource('expenses', ExpenseController::class);

        // Reports
        Route::get('reports/pnl', [FinancialReportController::class, 'pnl'])->name('reports.pnl');
        Route::get('reports/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('reports/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('reports.cash-flow');
    });

    // ── Requisition Presets ────────────────────────────────────────────────
    Route::resource('requisitions/presets', ShopPresetController::class)->names('requisitions.presets');

    // ── Requisitions ───────────────────────────────────────────────────────
    Route::get('/requisitions/{order_number}', [RequisitionController::class, 'show'])->name('requisitions.show');
    Route::get('/requisitions/{order_number}/edit', [RequisitionController::class, 'edit'])->name('requisitions.edit');
    Route::post('/requisitions/{order_number}/edit', [RequisitionController::class, 'update'])->name('requisitions.update');
    Route::post('/requisitions/{order_number}/update-request', [RequisitionController::class, 'requestUpdate'])->name('requisitions.update-request');
    Route::get('/requisitions/{order_number}/export/csv', [RequisitionController::class, 'exportCsv'])->name('requisitions.export.csv');
    Route::get('/requisitions/{order_number}/export/pdf', [RequisitionController::class, 'exportPdf'])->name('requisitions.export.pdf');
    Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');

    // ── Admin ──────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class);
    });
});
