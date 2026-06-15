<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\ActivityLogController;
use App\Http\Controllers\Web\Admin\AdminOverviewController;
use App\Http\Controllers\Web\Admin\DailyPriceApprovalController;
use App\Http\Controllers\Web\Admin\DailyProgressController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\WarehouseController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Finance\AccountController;
use App\Http\Controllers\Web\Finance\ExpenseController;
use App\Http\Controllers\Web\Finance\FinanceController;
use App\Http\Controllers\Web\Finance\FinancialReportController;
use App\Http\Controllers\Web\Finance\LedgerController;
use App\Http\Controllers\Web\Inventory\BatchController;
use App\Http\Controllers\Web\Inventory\DeliveryDashboardController;
use App\Http\Controllers\Web\Inventory\FulfillmentReportController;
use App\Http\Controllers\Web\Inventory\ProductController;
use App\Http\Controllers\Web\Inventory\StockController;
use App\Http\Controllers\Web\Inventory\WarehouseSortingController;
use App\Http\Controllers\Web\Inventory\WastageController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\Purchasing\DailyPriceBoardController;
use App\Http\Controllers\Web\Purchasing\GoodsReceivedController;
use App\Http\Controllers\Web\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Web\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Web\Purchasing\PurchaserDashboardController;
use App\Http\Controllers\Web\Purchasing\ShopInvoiceController;
use App\Http\Controllers\Web\Purchasing\ShopPriceGroupController;
use App\Http\Controllers\Web\Purchasing\SupplierController;
use App\Http\Controllers\Web\RequisitionController;
use App\Http\Controllers\Web\Sales\CustomerController;
use App\Http\Controllers\Web\Sales\PaymentController;
use App\Http\Controllers\Web\Sales\SalesInvoiceController;
use App\Http\Controllers\Web\Sales\SalesOrderController;
use App\Http\Controllers\Web\ShopOwnerController;
use App\Http\Controllers\Web\ShopPresetController;
use App\Http\Controllers\Web\SortSheetController;
use App\Http\Controllers\Web\Warehouse\WarehouseReceiverController;
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
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('shop-owner')->name('shop-owner.')->group(function () {
        Route::get('/dashboard', [ShopOwnerController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [ShopOwnerController::class, 'ordersIndex'])->name('orders.index');
        Route::get('/orders/create', [ShopOwnerController::class, 'ordersCreate'])->name('orders.create');
        Route::get('/orders/history', [ShopOwnerController::class, 'ordersHistory'])->name('orders.history');
        Route::get('/orders/{order_number}', [ShopOwnerController::class, 'ordersShow'])->name('orders.show');
        Route::get('/deliveries', [ShopOwnerController::class, 'deliveriesIndex'])->name('deliveries.index');
        Route::get('/deliveries/{order_number}', [ShopOwnerController::class, 'deliveriesShow'])->name('deliveries.show');
        Route::get('/finance', [ShopOwnerController::class, 'financeIndex'])->name('finance.index');
        Route::get('/finance/{invoice}', [ShopOwnerController::class, 'financeShow'])->name('finance.show');
        Route::get('/finance/{invoice}/pdf', [ShopOwnerController::class, 'financePdf'])->name('finance.pdf');
    });

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

        // Warehouse Sorting Checklist
        Route::get('sorting-checklist', [WarehouseSortingController::class, 'index'])->name('sorting.checklist');
        Route::get('sorting-checklist/shop-orders', [WarehouseSortingController::class, 'shopOrders'])->name('sorting.shop-orders');
        Route::get('sorting-checklist/shop-sorting', [WarehouseSortingController::class, 'shopSortingIndex'])->name('sorting.shop-sorting');
        Route::get('sorting-checklist/shop-sorting/{order:order_number}', [WarehouseSortingController::class, 'shopSortingShow'])->name('sorting.shop-sorting.show');
        Route::patch('sorting-checklist/shops/{shop:code}/tag', [WarehouseSortingController::class, 'updateShopTag'])->name('sorting.shops.tag');
        Route::post('sorting-checklist/toggle/{item}', [WarehouseSortingController::class, 'toggle'])->name('sorting.checklist.toggle');
        Route::post('sorting-checklist/grn', [WarehouseSortingController::class, 'storeGrn'])->name('sorting.checklist.grn');
        Route::post('sorting-checklist/carry-over/{batch}', [WarehouseSortingController::class, 'carryOver'])->name('sorting.checklist.carry-over');
        Route::post('sorting-checklist/wastage/{batch}', [WarehouseSortingController::class, 'recordWastage'])->name('sorting.checklist.wastage');
        Route::post('sorting-checklist/complete-order/{order}', [WarehouseSortingController::class, 'completeAllocation'])->name('sorting.checklist.complete-order');
        Route::get('deliveries/dashboard', DeliveryDashboardController::class)->name('deliveries.dashboard');
        Route::get('reports/fulfillment', FulfillmentReportController::class)->name('reports.fulfillment');
    });

    // ── Purchasing ─────────────────────────────────────────────────────────
    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        // Suppliers
        Route::resource('suppliers', SupplierController::class);
        Route::post('suppliers/{supplier}/credit-request', [SupplierController::class, 'requestCreditApproval'])->name('suppliers.credit-request');
        Route::post('suppliers/{supplier}/credit-approve', [SupplierController::class, 'approveCreditApproval'])->name('suppliers.credit-approve');
        Route::get('prices', [DailyPriceBoardController::class, 'index'])->name('prices.index');
        Route::post('prices', [DailyPriceBoardController::class, 'update'])->name('prices.update');
        Route::post('price-groups/assign-shops', [ShopPriceGroupController::class, 'assignShops'])->name('price-groups.assign-shops');
        Route::resource('price-groups', ShopPriceGroupController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('shop-invoices', [ShopInvoiceController::class, 'index'])->name('shop-invoices.index');
        Route::get('shop-invoices/{invoice}', [ShopInvoiceController::class, 'show'])->name('shop-invoices.show');
        Route::get('shop-invoices/{invoice}/pdf', [ShopInvoiceController::class, 'pdf'])->name('shop-invoices.pdf');
        Route::patch('shop-invoices/{invoice}/payment-approval', [ShopInvoiceController::class, 'approvePayment'])->name('shop-invoices.payment-approval');
        Route::patch('shop-invoices/{invoice}/reprice', [ShopInvoiceController::class, 'reprice'])->name('shop-invoices.reprice');

        // Purchase Orders
        Route::resource('orders', PurchaseOrderController::class);
        Route::post('orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');
        Route::post('orders/{order}/reject', [PurchaseOrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/send', [PurchaseOrderController::class, 'send'])->name('orders.send');
        Route::put('orders/{order}/items', [PurchaseOrderController::class, 'updateItems'])->name('orders.items.update');

        Route::resource('grns', GoodsReceivedController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::post('grns/approve-submitted', [GoodsReceivedController::class, 'approveSubmitted'])->name('grns.approve-submitted');
        Route::patch('grns/proposed-prices/update', [GoodsReceivedController::class, 'updateProposedPrices'])->name('grns.proposed-prices.update');
        Route::post('grns/{grn}/recheck', [GoodsReceivedController::class, 'markForRecheck'])->name('grns.recheck');

        // Invoices
        Route::resource('invoices', PurchaseInvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('invoices/{invoice}/pdf', [PurchaseInvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/status', [PurchaseInvoiceController::class, 'updateStatus'])->name('invoices.update-status');
        Route::patch('invoices/{invoice}/payment', [PurchaseInvoiceController::class, 'updatePayment'])->name('invoices.update-payment');
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
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('/statement/export/csv', [FinanceController::class, 'exportCsv'])->name('statement.export.csv');
        Route::get('/statement/export/pdf', [FinanceController::class, 'exportPdf'])->name('statement.export.pdf');
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
    Route::post('/requisitions/{order_number}/review', [RequisitionController::class, 'review'])->name('requisitions.review');
    Route::post('/requisitions/{order_number}/accept-late', [RequisitionController::class, 'acceptLateRequisition'])->name('requisitions.accept-late');
    Route::post('/requisitions/{order_number}/reject-late', [RequisitionController::class, 'rejectLateRequisition'])->name('requisitions.reject-late');
    Route::post('/requisitions/{order_number}/approve-update', [RequisitionController::class, 'approveUpdate'])->name('requisitions.approve-update');
    Route::post('/requisitions/{order_number}/reject-update', [RequisitionController::class, 'rejectUpdate'])->name('requisitions.reject-update');
    Route::get('/requisitions/{order_number}/delivery', [RequisitionController::class, 'showDelivery'])->name('requisitions.delivery.show');
    Route::post('/requisitions/{order_number}/delivery', [RequisitionController::class, 'recordDelivery'])->name('requisitions.delivery.record');
    Route::post('/requisitions/{order_number}/approve-delivery', [RequisitionController::class, 'approveDeliveryDiscrepancy'])->name('requisitions.delivery.approve');
    Route::post('/requisitions/{order_number}/reject-delivery', [RequisitionController::class, 'rejectDeliveryDiscrepancy'])->name('requisitions.delivery.reject');
    Route::get('/requisitions-board', [RequisitionController::class, 'board'])->name('requisitions.board');
    Route::post('/requisitions-board', [RequisitionController::class, 'saveBoard'])->name('requisitions.board.save');
    Route::get('/requisitions-board/export/csv', [RequisitionController::class, 'exportBoardCsv'])->name('requisitions.board.export.csv');
    Route::get('/requisitions-board/export/pdf', [RequisitionController::class, 'exportBoardPdf'])->name('requisitions.board.export.pdf');
    Route::get('/approved-board', [RequisitionController::class, 'approvedBoard'])->name('requisitions.approved_board');
    Route::post('/approved-board', [RequisitionController::class, 'saveApprovedBoard'])->name('requisitions.approved_board.save');
    Route::get('/approved-board/export/csv', [RequisitionController::class, 'exportApprovedBoardCsv'])->name('requisitions.approved_board.export.csv');
    Route::get('/approved-board/export/pdf', [RequisitionController::class, 'exportApprovedBoardPdf'])->name('requisitions.approved_board.export.pdf');
    Route::get('/requisitions/{order_number}/export/csv', [RequisitionController::class, 'exportCsv'])->name('requisitions.export.csv');
    Route::get('/requisitions/{order_number}/export/pdf', [RequisitionController::class, 'exportPdf'])->name('requisitions.export.pdf');
    Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');

    // ── Purchaser Dashboard ────────────────────────────────────────────────
    Route::get('/purchaser/dashboard', [PurchaserDashboardController::class, 'index'])->name('purchaser.dashboard');
    Route::get('/purchaser/daily', [PurchaserDashboardController::class, 'daily'])->name('purchaser.daily');
    Route::get('/purchaser/cart', [PurchaserDashboardController::class, 'cart'])->name('purchaser.cart');
    Route::get('/purchaser/vendors', [PurchaserDashboardController::class, 'vendors'])->name('purchaser.vendors');
    Route::get('/purchaser/finance', [PurchaserDashboardController::class, 'finance'])->name('purchaser.finance');
    Route::get('/purchaser/cart/{cart}/bill', [PurchaserDashboardController::class, 'bill'])->name('purchaser.bill');
    Route::get('/purchaser/history', [PurchaserDashboardController::class, 'history'])->name('purchaser.history');
    Route::post('/purchaser/carts', [PurchaserDashboardController::class, 'storeCart'])->name('purchaser.carts.store');
    Route::post('/purchaser/carts/bulk-store', [PurchaserDashboardController::class, 'bulkStoreCart'])->name('purchaser.carts.bulk-store');
    Route::post('/purchaser/carts/{cart}/merge-drafts', [PurchaserDashboardController::class, 'mergeDraftCarts'])->name('purchaser.carts.merge-drafts');
    Route::post('/purchaser/carts/{cart}/send', [PurchaserDashboardController::class, 'markCartSent'])->name('purchaser.carts.send');
    Route::patch('/purchaser/carts/{cart}/supplier', [PurchaserDashboardController::class, 'updateCartSupplier'])->name('purchaser.carts.update-supplier');
    Route::post('/purchaser/cart-items', [PurchaserDashboardController::class, 'storeCartItem'])->name('purchaser.cart-items.store');
    Route::patch('/purchaser/cart-items/{item}', [PurchaserDashboardController::class, 'updateCartItem'])->name('purchaser.cart-items.update');
    Route::delete('/purchaser/cart-items/{item}', [PurchaserDashboardController::class, 'destroyCartItem'])->name('purchaser.cart-items.destroy');
    Route::post('/purchaser/carts/submit', [PurchaserDashboardController::class, 'submitCart'])->name('purchaser.carts.submit');
    Route::patch('/purchaser/carts/{cart}/status', [PurchaserDashboardController::class, 'updateOperationalStatus'])->name('purchaser.carts.status');
    Route::get('/purchaser/invoices/{invoice}', [PurchaserDashboardController::class, 'invoiceShow'])->name('purchaser.invoices.show');
    Route::get('/purchaser/invoices/{invoice}/pdf', [PurchaserDashboardController::class, 'invoicePdf'])->name('purchaser.invoices.pdf');
    Route::patch('/purchaser/invoices/{invoice}/payment', [PurchaserDashboardController::class, 'updateInvoicePayment'])->name('purchaser.invoices.payment');
    Route::post('/purchaser/corrections', [PurchaserDashboardController::class, 'storeCorrectionRequest'])->name('purchaser.corrections.store');
    Route::post('/purchaser/corrections/{correctionRequest}/approve', [PurchaserDashboardController::class, 'approveCorrectionRequest'])->name('purchaser.corrections.approve');
    Route::post('/purchaser/corrections/{correctionRequest}/reject', [PurchaserDashboardController::class, 'rejectCorrectionRequest'])->name('purchaser.corrections.reject');

    // ── Warehouse Receiver ─────────────────────────────────────────────────
    Route::prefix('warehouse-receiver')->name('warehouse.receiver.')->group(function () {
        Route::get('/checklist', [WarehouseReceiverController::class, 'index'])->name('checklist');
        Route::post('/confirm/{batch}', [WarehouseReceiverController::class, 'confirm'])->name('confirm');
        Route::post('/confirm-all', [WarehouseReceiverController::class, 'confirmAll'])->name('confirm-all');
        Route::get('/loadout/{order}', [WarehouseReceiverController::class, 'loadoutDetails'])->name('loadout.show');
        Route::post('/loadout/item/{item}', [WarehouseReceiverController::class, 'loadoutItem'])->name('loadout.item');
        Route::post('/loadout/order/{order}/all', [WarehouseReceiverController::class, 'loadoutOrderAll'])->name('loadout.order-all');
    });

    // ── Admin ──────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminOverviewController::class)->name('overview');
        Route::resource('users', UserController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::get('daily-progress', DailyProgressController::class)->name('daily-progress');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('price-approvals', [DailyPriceApprovalController::class, 'index'])->name('price-approvals.index');
        Route::post('price-approvals/approve', [DailyPriceApprovalController::class, 'approve'])->name('price-approvals.approve');
    });

    // ── Sort Sheet ──────────────────────────────────────────────────────────────
    Route::prefix('sort-sheet')->name('sort-sheet.')->group(function () {
        Route::get('/', [SortSheetController::class, 'index'])->name('index');
        Route::get('/generate', [SortSheetController::class, 'generate'])->name('generate');
        Route::get('/export/excel', [SortSheetController::class, 'exportExcel'])->name('export.excel');
        Route::get('/export/pdf', [SortSheetController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/print', [SortSheetController::class, 'print'])->name('print');
    });
});
