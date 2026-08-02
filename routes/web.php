<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\ActivityLogController;
use App\Http\Controllers\Web\Admin\AdminAccountingController;
use App\Http\Controllers\Web\Admin\AdminOverviewController;
use App\Http\Controllers\Web\Admin\CompanySettingsController;
use App\Http\Controllers\Web\Admin\DailyProgressController;
use App\Http\Controllers\Web\Admin\DeliveryReviewController;
use App\Http\Controllers\Web\Admin\DiscrepancyReportController;
use App\Http\Controllers\Web\Admin\EnquiryController;
use App\Http\Controllers\Web\Admin\FinanceV2Controller;
use App\Http\Controllers\Web\Admin\FinanceV2PaymentsController;
use App\Http\Controllers\Web\Admin\StaffManagementController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\WarehouseController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\ShopOwnerRegistrationController;
use App\Http\Controllers\Web\BusinessDaySettingsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Finance\FinanceController;
use App\Http\Controllers\Web\Inventory\BatchController;
use App\Http\Controllers\Web\Inventory\CategoryController;
use App\Http\Controllers\Web\Inventory\DailyInventoryCloseController;
use App\Http\Controllers\Web\Inventory\DeliveryDashboardController;
use App\Http\Controllers\Web\Inventory\FulfillmentReportController;
use App\Http\Controllers\Web\Inventory\ProductController;
use App\Http\Controllers\Web\Inventory\ShopOrderQuantityCorrectionController;
use App\Http\Controllers\Web\Inventory\StockController;
use App\Http\Controllers\Web\Inventory\WarehouseSortingController;
use App\Http\Controllers\Web\Inventory\WastageController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\Purchasing\AdminShopOrderController;
use App\Http\Controllers\Web\Purchasing\DailyPriceBoardController;
use App\Http\Controllers\Web\Purchasing\GoodsReceivedController;
use App\Http\Controllers\Web\Purchasing\OtherExpenseController;
use App\Http\Controllers\Web\Purchasing\ProcurementExpenseController;
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
use App\Http\Controllers\Web\ShopOwnerController;
use App\Http\Controllers\Web\ShopOwnerStaffController;
use App\Http\Controllers\Web\ShopPresetController;
use App\Http\Controllers\Web\SortSheetController;
use App\Http\Controllers\Web\Warehouse\WarehouseLoadoutController;
use App\Http\Controllers\Web\Warehouse\WarehouseReceiverController;
use App\Http\Controllers\Web\WebsiteEnquiryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public website
Route::view('/', 'welcome')->name('home');
Route::view('/products', 'products.index')->name('products.index');
Route::post('/enquiries', [WebsiteEnquiryController::class, 'store'])->middleware('throttle:public-form')->name('website-enquiries.store');

// Guest routes (unauthenticated only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::get('/shop-owner/register', [ShopOwnerRegistrationController::class, 'create'])->name('shop-owner.register');
    Route::post('/shop-owner/register', [ShopOwnerRegistrationController::class, 'store'])->middleware('throttle:public-form')->name('shop-owner.register.store');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.submit');
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

    Route::get('/shop/dashboard', [ShopOwnerController::class, 'dashboard'])
        ->middleware('can:sales.order.create')
        ->name('shop.dashboard');

    Route::prefix('shop-owner')->name('shop-owner.')->middleware('can:sales.order.create')->group(function () {
        Route::get('/dashboard', fn () => redirect()->route('shop.dashboard'))->name('dashboard');
        Route::get('/orders', [ShopOwnerController::class, 'ordersIndex'])->name('orders.index');
        Route::get('/orders/create', [ShopOwnerController::class, 'ordersCreate'])->name('orders.create');
        Route::delete('/orders/create/clear', [ShopOwnerController::class, 'clearTomorrowOrder'])->name('orders.clear');
        Route::get('/orders/history', [ShopOwnerController::class, 'ordersHistory'])->name('orders.history');
        Route::get('/orders/{order_number}', [ShopOwnerController::class, 'ordersShow'])->name('orders.show');
        Route::get('/deliveries', [ShopOwnerController::class, 'deliveriesIndex'])->name('deliveries.index');
        Route::get('/deliveries/{order_number}', [ShopOwnerController::class, 'deliveriesShow'])->name('deliveries.show');
        Route::post('/deliveries/{order_number}/items/{item}/verify', [ShopOwnerController::class, 'verifyDeliveryItem'])->name('deliveries.items.verify');
        Route::get('/accounting', [ShopOwnerController::class, 'accountingIndex'])->name('accounting.index');
        Route::get('/accounting/cashbook/pdf', [ShopOwnerController::class, 'accountingCashbookPdf'])->name('accounting.cashbook.pdf');
        Route::get('/accounting/daily-report', [ShopOwnerController::class, 'accountingDailyReport'])->name('accounting.daily-report');
        Route::get('/accounting/history', [ShopOwnerController::class, 'accountingHistory'])->name('accounting.history');
        Route::post('/accounting/entries', [ShopOwnerController::class, 'storeAccountingEntry'])->name('accounting.entries.store');
        Route::post('/accounting/payment-requests', [ShopOwnerController::class, 'storePaymentRequest'])->name('accounting.payment-requests.store');
        Route::get('/payments', [ShopOwnerController::class, 'paymentsIndex'])->name('payments.index');
        Route::get('/finance', [ShopOwnerController::class, 'financeIndex'])->name('finance.index');
        Route::get('/finance/{invoice}', [ShopOwnerController::class, 'financeShow'])->name('finance.show');
        Route::get('/finance/{invoice}/pdf', [ShopOwnerController::class, 'financePdf'])->name('finance.pdf');
        Route::get('/staff', [ShopOwnerStaffController::class, 'index'])->name('staff.index');
        Route::post('/staff/attendance', [ShopOwnerStaffController::class, 'storeAttendance'])->name('staff.attendance.store');
        Route::post('/staff/salary-payments', [ShopOwnerStaffController::class, 'storeSalaryPayment'])->name('staff.salary-payments.store');
        Route::post('/staff/advance-requests', [ShopOwnerStaffController::class, 'storeAdvanceRequest'])->name('staff.advance-requests.store');
        Route::post('/staff/leave-requests', [ShopOwnerStaffController::class, 'storeLeave'])->name('staff.leave-requests.store');
    });

    // ── Inventory ──────────────────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->middleware('can:inventory.product.view')->group(function () {
        Route::get('dashboard', function (Request $request) {
            return redirect()->route(
                'inventory.deliveries.dashboard',
                $request->filled('date') ? ['date' => $request->string('date')->toString()] : []
            );
        })->name('dashboard');

        // Products
        Route::patch('products/status-permissions', [ProductController::class, 'updateStatusPermissions'])->name('products.status-permissions.update');
        Route::get('products/measures/bulk', [ProductController::class, 'bulkMeasures'])->name('products.measures.bulk');
        Route::put('products/measures/bulk', [ProductController::class, 'updateBulkMeasures'])->name('products.measures.bulk.update');
        Route::get('products/measures/bulk/export-json', [ProductController::class, 'exportBulkMeasures'])->name('products.measures.bulk.export-json');
        Route::post('products/measures/bulk/import-json', [ProductController::class, 'importBulkMeasures'])->name('products.measures.bulk.import-json');
        Route::patch('products/{product}/status', [ProductController::class, 'updateStatus'])->name('products.status.update');
        Route::get('products-trash', [ProductController::class, 'trash'])->name('products.trash');
        Route::patch('products-trash/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::delete('products-trash/{product}/force-delete', [ProductController::class, 'forceDelete'])->name('products.force-delete');
        Route::resource('products', ProductController::class);
        Route::get('categories/export/pdf', [CategoryController::class, 'exportPdf'])->name('categories.export-pdf');
        Route::get('categories/{category}/products', [CategoryController::class, 'products'])->name('categories.products');
        Route::post('categories/{category}/products', [CategoryController::class, 'updateProducts'])->name('categories.products.update');
        Route::resource('categories', CategoryController::class);

        // Stock levels
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('daily-close', [DailyInventoryCloseController::class, 'index'])->name('daily-close.index');
        Route::post('daily-close', [DailyInventoryCloseController::class, 'store'])->name('daily-close.store');

        // Quantity Corrections (Admin Fix)
        Route::get('quantity-corrections', [ShopOrderQuantityCorrectionController::class, 'index'])->name('quantity-corrections.index');
        Route::patch('quantity-corrections/{item}', [ShopOrderQuantityCorrectionController::class, 'update'])->name('quantity-corrections.update');
        Route::post('quantity-corrections/{item}/recalculate', [ShopOrderQuantityCorrectionController::class, 'recalculate'])->name('quantity-corrections.recalculate');
        Route::post('quantity-corrections/{item}/copy-loaded', [ShopOrderQuantityCorrectionController::class, 'copyLoaded'])->name('quantity-corrections.copy-loaded');
        Route::delete('quantity-corrections/{item}/soft-delete', [ShopOrderQuantityCorrectionController::class, 'softDeleteDuplicate'])->name('quantity-corrections.soft-delete');

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
    Route::prefix('purchasing')->name('purchasing.')->middleware('can:purchasing.order.view')->group(function () {
        Route::get('dashboard', fn () => redirect()->route('purchasing.orders.index'))->name('dashboard');

        // Suppliers
        Route::resource('suppliers', SupplierController::class);
        Route::post('suppliers/{supplier}/credit-request', [SupplierController::class, 'requestCreditApproval'])->name('suppliers.credit-request');
        Route::post('suppliers/{supplier}/credit-approve', [SupplierController::class, 'approveCreditApproval'])->name('suppliers.credit-approve');
        Route::get('prices', [DailyPriceBoardController::class, 'index'])->name('prices.index');
        Route::post('prices', [DailyPriceBoardController::class, 'update'])->name('prices.update');
        Route::post('prices/{approval}/save-row', [DailyPriceBoardController::class, 'saveRow'])->name('prices.save-row');
        Route::post('prices/products', [DailyPriceBoardController::class, 'storeProduct'])->name('prices.products.store');
        Route::post('prices/settings', [DailyPriceBoardController::class, 'updateSettings'])->name('prices.settings.update');
        Route::post('prices/refresh', [DailyPriceBoardController::class, 'recalculatePrices'])->name('prices.refresh');
        Route::post('price-groups/assign-shops', [ShopPriceGroupController::class, 'assignShops'])->name('price-groups.assign-shops');
        Route::resource('price-groups', ShopPriceGroupController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('shop-invoices', [ShopInvoiceController::class, 'index'])->name('shop-invoices.index');
        Route::get('shop-invoices/{invoice}', [ShopInvoiceController::class, 'show'])->name('shop-invoices.show');
        Route::get('shop-invoices/{invoice}/pdf', [ShopInvoiceController::class, 'pdf'])->name('shop-invoices.pdf');
        Route::patch('shop-invoices/{invoice}/reprice', [ShopInvoiceController::class, 'reprice'])->name('shop-invoices.reprice');

        // Daily shop orders (admin marketplace editor)
        Route::get('shop-orders', [AdminShopOrderController::class, 'index'])->name('shop-orders.index');
        Route::get('shop-orders/{shop:code}/edit', [AdminShopOrderController::class, 'edit'])->name('shop-orders.edit');
        Route::post('shop-orders/{shop:code}', [AdminShopOrderController::class, 'store'])->name('shop-orders.store');

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
        Route::get('invoices/vendors/{supplier}', [PurchaseInvoiceController::class, 'vendorReport'])->name('invoices.vendor-report');
        Route::get('invoices/{invoice}/pdf', [PurchaseInvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/status', [PurchaseInvoiceController::class, 'updateStatus'])->name('invoices.update-status');
        Route::patch('invoices/{invoice}/payment', [PurchaseInvoiceController::class, 'updatePayment'])->name('invoices.update-payment');
    });

    // ── Sales ──────────────────────────────────────────────────────────────
    Route::prefix('sales')->name('sales.')->middleware('can:sales.customer.view')->group(function () {
        // Customers
        Route::post('customers/shops', [CustomerController::class, 'storeShop'])->name('customers.shops.store');
        Route::patch('customers/shops/{shop:code}', [CustomerController::class, 'updateShop'])->name('customers.shops.update');
        Route::resource('customers', CustomerController::class)->only(['index']);

        // Sales Invoices
        Route::resource('invoices', SalesInvoiceController::class)->only(['index', 'create', 'store', 'show']);

        // Payments
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    });

    // ── Finance & Accounting ────────────────────────────────────────────────
    Route::prefix('finance')->name('finance.')->middleware('can:accounting.report.view')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::get('/vendors', [FinanceController::class, 'vendors'])->name('vendors.index');
        Route::get('/vendors/excel', [FinanceController::class, 'vendorsExcel'])->name('vendors.excel');
        Route::get('/vendors/pdf', [FinanceController::class, 'vendorsPdf'])->name('vendors.pdf');
        Route::get('/sales', [FinanceController::class, 'sales'])->name('sales.index');
        Route::get('/sales/excel', [FinanceController::class, 'salesExcel'])->name('sales.excel');
        Route::get('/sales/pdf', [FinanceController::class, 'salesPdf'])->name('sales.pdf');
        Route::get('/vendor-daily', [FinanceController::class, 'vendorDaily'])->name('vendor-daily');
        Route::get('/sales-daily', [FinanceController::class, 'salesDaily'])->name('sales-daily');
        Route::get('/statement/export/csv', [FinanceController::class, 'legacyExportRedirect'])->name('statement.export.csv');
        Route::get('/statement/export/pdf', [FinanceController::class, 'legacyExportRedirect'])->name('statement.export.pdf');
        Route::get('accounts', [FinanceController::class, 'legacyRedirect'])->name('accounts.index');
        Route::get('ledger', [FinanceController::class, 'legacyRedirect'])->name('ledger.index');
        Route::get('expenses', [FinanceController::class, 'legacyRedirect'])->name('expenses.index');
        Route::get('expenses/create', [FinanceController::class, 'legacyRedirect'])->name('expenses.create');
        Route::get('reports/pnl', [FinanceController::class, 'legacyRedirect'])->name('reports.pnl');
        Route::get('reports/balance-sheet', [FinanceController::class, 'legacyRedirect'])->name('reports.balance-sheet');
        Route::get('reports/cash-flow', [FinanceController::class, 'legacyRedirect'])->name('reports.cash-flow');
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
    Route::post('/requisitions-board/approve-all', [RequisitionController::class, 'approveAllForDate'])->name('requisitions.board.approve-all');
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
    Route::post('/business-day-settings/cutoff', [BusinessDaySettingsController::class, 'updateCutoff'])->name('business-day-settings.cutoff.update');
    Route::post('/business-day-settings/auto-approve', [BusinessDaySettingsController::class, 'updateAutoApprove'])->name('business-day-settings.auto-approve.update');
    Route::get('/requisitions/{order_number}/export/csv', [RequisitionController::class, 'exportCsv'])->name('requisitions.export.csv');
    Route::get('/requisitions/{order_number}/export/pdf', [RequisitionController::class, 'exportPdf'])->name('requisitions.export.pdf');
    Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');

    // ── Purchaser Dashboard ────────────────────────────────────────────────
    Route::get('/purchaser/dashboard', [PurchaserDashboardController::class, 'index'])->name('purchaser.dashboard');
    Route::get('/purchaser/daily', [PurchaserDashboardController::class, 'daily'])->name('purchaser.daily');
    Route::get('/purchaser/daily/share', [PurchaserDashboardController::class, 'dailyShare'])->name('purchaser.daily.share');
    Route::get('/purchaser/shop-orders', [PurchaserDashboardController::class, 'shopOrders'])->name('purchaser.shop-orders.index');
    Route::get('/purchaser/shop-orders/{order_number}', [PurchaserDashboardController::class, 'shopOrderShow'])->name('purchaser.shop-orders.show');
    Route::get('/purchaser/add-ons/create', [RequisitionController::class, 'createPurchaserDirectPurchase'])->name('purchaser.add-ons.create');
    Route::get('/purchaser/bulk-buy', [PurchaserDashboardController::class, 'bulkBuy'])->name('purchaser.bulk-buy');
    Route::get('/purchaser/bulk-buy/details', [PurchaserDashboardController::class, 'bulkBuyDetails'])->name('purchaser.bulk-buy.details');
    Route::get('/purchaser/cart', [PurchaserDashboardController::class, 'cart'])->name('purchaser.cart');
    Route::get('/purchaser/vendors', [PurchaserDashboardController::class, 'vendors'])->name('purchaser.vendors');
    Route::get('/purchaser/suppliers', [PurchaserDashboardController::class, 'supplierHub'])->name('purchaser.suppliers');
    Route::get('/purchaser/suppliers/{supplier}', [PurchaserDashboardController::class, 'supplierShow'])->name('purchaser.suppliers.show');
    Route::get('/purchaser/finance', [PurchaserDashboardController::class, 'finance'])->name('purchaser.finance');
    Route::get('/purchaser/cash', [PurchaserDashboardController::class, 'cash'])->name('purchaser.cash');
    Route::get('/purchaser/procurement-expenses', [ProcurementExpenseController::class, 'index'])->name('purchaser.procurement-expenses.index');
    Route::get('/purchaser/other-expenses', [OtherExpenseController::class, 'index'])->name('purchaser.other-expenses.index');
    Route::get('/purchaser/cart/{cart}/bill', [PurchaserDashboardController::class, 'bill'])->name('purchaser.bill');
    Route::get('/purchaser/history', [PurchaserDashboardController::class, 'history'])->name('purchaser.history');
    Route::get('/purchaser/settings', [PurchaserDashboardController::class, 'settings'])->name('purchaser.settings');
    Route::post('/purchaser/settings', [PurchaserDashboardController::class, 'updateSettings'])->name('purchaser.settings.update');
    Route::post('/purchaser/carts', [PurchaserDashboardController::class, 'storeCart'])->name('purchaser.carts.store');
    Route::post('/purchaser/add-ons', [RequisitionController::class, 'storePurchaserDirectPurchase'])->name('purchaser.add-ons.store');
    Route::post('/purchaser/procurement-expenses', [ProcurementExpenseController::class, 'store'])->name('purchaser.procurement-expenses.store');
    Route::patch('/purchaser/procurement-expenses/{expense}', [ProcurementExpenseController::class, 'update'])->name('purchaser.procurement-expenses.update');
    Route::delete('/purchaser/procurement-expenses/{expense}', [ProcurementExpenseController::class, 'destroy'])->name('purchaser.procurement-expenses.destroy');
    Route::post('/purchaser/other-expenses', [OtherExpenseController::class, 'store'])->name('purchaser.other-expenses.store');
    Route::patch('/purchaser/other-expenses/{expense}', [OtherExpenseController::class, 'update'])->name('purchaser.other-expenses.update');
    Route::delete('/purchaser/other-expenses/{expense}', [OtherExpenseController::class, 'destroy'])->name('purchaser.other-expenses.destroy');
    Route::post('/purchaser/carts/bulk-store', [PurchaserDashboardController::class, 'bulkStoreCart'])->name('purchaser.carts.bulk-store');
    Route::post('/purchaser/carts/{cart}/merge-drafts', [PurchaserDashboardController::class, 'mergeDraftCarts'])->name('purchaser.carts.merge-drafts');
    Route::post('/purchaser/carts/{cart}/send', [PurchaserDashboardController::class, 'markCartSent'])->name('purchaser.carts.send');
    Route::patch('/purchaser/carts/{cart}/supplier', [PurchaserDashboardController::class, 'updateCartSupplier'])->name('purchaser.carts.update-supplier');
    Route::post('/purchaser/cart-items', [PurchaserDashboardController::class, 'storeCartItem'])->name('purchaser.cart-items.store');
    Route::patch('/purchaser/cart-items/{item}', [PurchaserDashboardController::class, 'updateCartItem'])->name('purchaser.cart-items.update');
    Route::patch('/purchaser/carts/{cart}/items', [PurchaserDashboardController::class, 'updateCartItems'])->name('purchaser.carts.items.update-all');
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
    Route::prefix('warehouse-receiver')->name('warehouse.receiver.')->middleware('can:warehouse.receive.view')->group(function () {
        Route::get('/products', [ProductController::class, 'receiverIndex'])->name('products.index');
        Route::get('/checklist', [WarehouseReceiverController::class, 'index'])->name('checklist');
        Route::post('/confirm/{batch}', [WarehouseReceiverController::class, 'confirm'])->name('confirm');
        Route::post('/confirm-all', [WarehouseReceiverController::class, 'confirmAll'])->name('confirm-all');
        Route::post('/receive-grns/all', [WarehouseReceiverController::class, 'processReceiveAllGrns'])->name('process-receive-grns.all');
        Route::get('/receive-grn/{grn}', [WarehouseReceiverController::class, 'receiveGrnForm'])->name('receive-grn');
        Route::post('/receive-grn/{grn}', [WarehouseReceiverController::class, 'processReceiveGrn'])->name('process-receive-grn');
        Route::post('/direct-purchase/{order}/receive', [WarehouseReceiverController::class, 'receiveDirectPurchase'])->name('direct-purchase.receive');
        Route::get('/loadout/{order}', [WarehouseReceiverController::class, 'loadoutDetails'])->name('loadout.show');
        Route::post('/loadout/item/{item}', [WarehouseReceiverController::class, 'loadoutItem'])->name('loadout.item');
        Route::post('/loadout/order/{order}/all', [WarehouseReceiverController::class, 'loadoutOrderAll'])->name('loadout.order-all');
        Route::post('/loadout/order/{order}/dispatch', [WarehouseReceiverController::class, 'dispatchOrder'])->name('loadout.order.dispatch');
        Route::post('/loadout/order/{order}/dispatch-partial', [WarehouseReceiverController::class, 'dispatchPartialOrder'])->name('loadout.order.dispatch-partial');
        Route::post('/loadout/order/{order}/ship', [WarehouseReceiverController::class, 'shipOrder'])->name('loadout.order.ship');
        Route::prefix('sort-sheet')->name('sort-sheet.')->middleware('can:sort.sheet.view')->group(function () {
            Route::get('/', [SortSheetController::class, 'index'])->name('index');
            Route::get('/generate', [SortSheetController::class, 'generate'])->name('generate');
            Route::get('/export/excel', [SortSheetController::class, 'exportExcel'])->name('export.excel');
            Route::get('/export/pdf', [SortSheetController::class, 'exportPdf'])->name('export.pdf');
            Route::get('/segregation/pdf', [SortSheetController::class, 'segregationPdf'])->name('segregation.pdf');
            Route::get('/segregation/matrix-print', [SortSheetController::class, 'segregationMatrixPrint'])->name('segregation.matrix-print');
            Route::get('/segregation/grid-print', [SortSheetController::class, 'segregationGridPrint'])->name('segregation.grid-print');
            Route::get('/print', [SortSheetController::class, 'print'])->name('print');
        });
    });

    // ── Warehouse Loadout (PRD v2) ─────────────────────────────────────────
    Route::prefix('warehouse/loadout')->name('warehouse.loadout.')->middleware('can:warehouse.receive.view')->group(function () {
        Route::get('/', [WarehouseLoadoutController::class, 'index'])->name('index');
        Route::get('/{shopOrder}/addon', [WarehouseLoadoutController::class, 'createAddon'])->name('addon.create');
        Route::post('/{shopOrder}/addon', [WarehouseLoadoutController::class, 'storeAddon'])->name('addon.store');
        Route::get('/{shopOrder}', [WarehouseLoadoutController::class, 'show'])->name('show');
        Route::post('/{shopOrder}/save', [WarehouseLoadoutController::class, 'save'])->name('save');
        Route::post('/{shopOrder}/move-to-delivery', [WarehouseLoadoutController::class, 'moveToDelivery'])->name('move-to-delivery');
        Route::post('/{shopOrder}/move-to-partial-delivery', [WarehouseLoadoutController::class, 'moveToPartialDelivery'])->name('move-to-partial-delivery');
        Route::post('/{shopOrder}/move-to-loadout', [WarehouseLoadoutController::class, 'moveToLoadout'])->name('move-to-loadout');
    });

    // ── Admin ──────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminOverviewController::class)->name('overview');
        Route::get('company-settings', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
        Route::patch('company-settings', [CompanySettingsController::class, 'update'])->name('company-settings.update');
        Route::prefix('finance-v2')->name('finance-v2.')->group(function () {
            Route::get('/', [FinanceV2Controller::class, 'dashboard'])->name('dashboard');
            Route::get('green-leaf/{section}', [FinanceV2Controller::class, 'greenLeaf'])->name('green-leaf.section');
            Route::get('clients', [FinanceV2Controller::class, 'clientsIndex'])->name('clients.index');
            Route::get('clients/{client}', [FinanceV2Controller::class, 'clientShow'])->name('clients.show');
            Route::get('clients/{client}/{section}', [FinanceV2Controller::class, 'clientSection'])->name('clients.section');
            Route::get('aishwarya-veg', [FinanceV2Controller::class, 'aishwaryaVeg'])->name('aishwarya-veg');
            Route::get('aishwarya-veg/{section}', [FinanceV2Controller::class, 'aishwaryaVegSection'])->name('aishwarya-veg.section');
            Route::get('reports', [FinanceV2Controller::class, 'reports'])->name('reports');
            Route::get('payments', [FinanceV2Controller::class, 'payments'])->name('payments.index');
            Route::get('payments/create', [FinanceV2Controller::class, 'createPayment'])->name('payments.create');
            Route::get('payments/shop-context/{shop}', [FinanceV2Controller::class, 'shopPaymentContext'])->name('payments.shop-context');
            Route::post('payments', [FinanceV2Controller::class, 'storePayment'])->name('payments.store');
            Route::get('payments/{paymentRequest}', [FinanceV2Controller::class, 'showPayment'])->name('payments.show');
            Route::patch('payments/{paymentRequest}/approve', [FinanceV2Controller::class, 'approvePayment'])->name('payments.approve');
            Route::patch('payments/{paymentRequest}/reject', [FinanceV2Controller::class, 'rejectPayment'])->name('payments.reject');
            Route::patch('payments/{paymentRequest}/cheque', [FinanceV2Controller::class, 'updateCheque'])->name('payments.cheque');

            Route::get('client-payments', [FinanceV2PaymentsController::class, 'clientPaymentsIndex'])->name('client-payments.index');
            Route::get('client-payments/{client}/shops/{shop:code}', [FinanceV2PaymentsController::class, 'clientShopShow'])->name('client-payments.shop');
            Route::get('company-payables', [FinanceV2PaymentsController::class, 'companyPayablesIndex'])->name('company-payables.index');
            Route::get('company-payables/{line}', [FinanceV2PaymentsController::class, 'companyPayableShow'])->name('company-payables.show');
            Route::patch('company-payables/{line}/approve', [FinanceV2PaymentsController::class, 'approveCompanyPayable'])->name('company-payables.approve');
            Route::patch('company-payables/{line}/reject', [FinanceV2PaymentsController::class, 'rejectCompanyPayable'])->name('company-payables.reject');
            Route::post('company-payables/{line}/settle-adjust', [FinanceV2PaymentsController::class, 'settleAdjust'])->name('company-payables.settle-adjust');
            Route::post('company-payables/{line}/settle-direct', [FinanceV2PaymentsController::class, 'settleDirect'])->name('company-payables.settle-direct');
            Route::get('direct-payments', [FinanceV2PaymentsController::class, 'directPaymentsIndex'])->name('direct-payments.index');
            Route::get('direct-payments/{invoice}', [FinanceV2PaymentsController::class, 'directPaymentsCreate'])->name('direct-payments.create');
            Route::post('direct-payments/{invoice}', [FinanceV2PaymentsController::class, 'directPaymentsStore'])->name('direct-payments.store');

            Route::get('shops/{shop:code}', [FinanceV2Controller::class, 'shop'])->name('shops.show');
        });
        Route::prefix('accounting')->name('accounting.')->middleware('can:accounting.dashboard.view')->group(function () {
            Route::get('/', [AdminAccountingController::class, 'index'])->name('index');
            Route::get('daily-sales', [AdminAccountingController::class, 'dailySalesReport'])->name('daily-sales');
            Route::get('main-account', [AdminAccountingController::class, 'mainAccount'])->name('main-account.index');
            Route::post('main-account/categories', [AdminAccountingController::class, 'storeMainAccountCategory'])->name('main-account.categories.store');
            Route::post('main-account/entries', [AdminAccountingController::class, 'storeMainAccountEntry'])->name('main-account.entries.store');
            Route::patch('main-account/entries/{entry}/reverse', [AdminAccountingController::class, 'reverseMainAccountEntry'])->name('main-account.entries.reverse');
            Route::patch('shop-invoices/{invoice}/discount', [AdminAccountingController::class, 'applyShopInvoiceDiscount'])->name('shop-invoices.discount');
            Route::patch('shop-invoices/{invoice}/payment', [AdminAccountingController::class, 'updateShopInvoicePayment'])->name('shop-invoices.payment');
            Route::patch('shop-invoice-payment-requests/{paymentRequest}/review', [AdminAccountingController::class, 'reviewShopInvoicePaymentRequest'])->name('shop-invoice-payment-requests.review');
            Route::get('company-summary', [AdminAccountingController::class, 'companySummary'])->name('company-summary');
            Route::get('cash-flow', [AdminAccountingController::class, 'cashFlowReport'])->name('cash-flow');
            Route::get('loans', [AdminAccountingController::class, 'loans'])->name('loans');
            Route::patch('loans/{shop:code}/categories', [AdminAccountingController::class, 'updateLoanCategorySettings'])->name('loans.categories.update');
            Route::post('loans/{shop:code}/entries', [AdminAccountingController::class, 'storeLoanEntry'])->name('loans.entries.store');
            Route::get('cash-flow/calendar', [AdminAccountingController::class, 'cashFlowCalendar'])->name('cash-flow.calendar');
            Route::get('cash-flow/export/excel', [AdminAccountingController::class, 'exportCashFlowDayJournalExcel'])->name('cash-flow.export.excel');
            Route::get('cash-flow/export/pdf', [AdminAccountingController::class, 'exportCashFlowDayJournalPdf'])->name('cash-flow.export.pdf');
            Route::get('vendor-reports', [AdminAccountingController::class, 'vendorReports'])->name('vendor-reports');
            Route::post('daily-workflow/invoices', [AdminAccountingController::class, 'generateDailyWorkflowInvoices'])->name('daily-workflow.invoices');
            Route::get('clients/report', [AdminAccountingController::class, 'clientsReport'])->name('clients.report');
            Route::get('clients/category-report', [AdminAccountingController::class, 'clientsCategoryReport'])->name('clients.category-report');
            Route::get('clients/{client}', [AdminAccountingController::class, 'clientDashboard'])->name('clients.show');
            Route::get('owned-shops', [AdminAccountingController::class, 'ownedShopsIndex'])->name('owned-shops.index');
            Route::post('owned-shops', [AdminAccountingController::class, 'storeOwnedShop'])->name('owned-shops.store');
            Route::patch('owned-shops/{shop:code}', [AdminAccountingController::class, 'updateOwnedShop'])->name('owned-shops.update');
            Route::delete('owned-shops/{shop:code}', [AdminAccountingController::class, 'destroyOwnedShop'])->name('owned-shops.destroy');
            Route::get('owned-shops/{shop:code}', [AdminAccountingController::class, 'ownedShopShow'])->name('owned-shops.show');
            Route::get('owned-shops/{shop:code}/categories', [AdminAccountingController::class, 'ownedShopCategories'])->name('owned-shops.categories.index');
            Route::patch('owned-shops/{shop:code}/reserve-amount', [AdminAccountingController::class, 'updateReserveAmount'])->name('owned-shops.reserve-amount.update');
            Route::patch('owned-shops/{shop:code}/petty-cash-settings', [AdminAccountingController::class, 'updatePettyCashSettings'])->name('owned-shops.petty-cash-settings.update');
            Route::post('owned-shops/{shop:code}/categories', [AdminAccountingController::class, 'storeCategory'])->name('owned-shops.categories.store');
            Route::patch('owned-shops/{shop:code}/categories/{category}', [AdminAccountingController::class, 'updateCategory'])->name('owned-shops.categories.update');
            Route::delete('owned-shops/{shop:code}/categories/{category}', [AdminAccountingController::class, 'destroyCategory'])->name('owned-shops.categories.destroy');
            Route::post('owned-shops/{shop:code}/entries', [AdminAccountingController::class, 'storeEntry'])->name('owned-shops.entries.store');
            Route::patch('owned-shops/{shop:code}/entries/{entry}', [AdminAccountingController::class, 'updateEntry'])->name('owned-shops.entries.update');
            Route::patch('owned-shops/{shop:code}/entries/{entry}/lines/{line}', [AdminAccountingController::class, 'updateEntryLine'])->name('owned-shops.entries.lines.update');
            Route::delete('owned-shops/{shop:code}/entries/{entry}/clear', [AdminAccountingController::class, 'clearEntry'])->name('owned-shops.entries.clear');
            Route::patch('owned-shops/{shop:code}/entries/{entry}/review', [AdminAccountingController::class, 'reviewEntry'])->name('owned-shops.entries.review');
            Route::post('owned-shops/{shop:code}/period-closures', [AdminAccountingController::class, 'closePeriod'])->name('owned-shops.period-closures.store');
            Route::patch('owned-shops/{shop:code}/daily-bills/{invoice}/payment', [AdminAccountingController::class, 'updateDailyBillPayment'])->name('owned-shops.daily-bills.payment');
            Route::patch('owned-shops/{shop:code}/payment-requests/{paymentRequest}/review', [AdminAccountingController::class, 'reviewOwnedShopPaymentRequest'])->name('owned-shops.payment-requests.review');
            Route::get('purchasers', [AdminAccountingController::class, 'purchasersIndex'])->name('purchasers.index');
            Route::get('purchasers/direct-purchase/create', [RequisitionController::class, 'createAdminDirectPurchase'])->name('purchasers.direct-purchase.create');
            Route::post('purchasers/direct-purchase', [RequisitionController::class, 'storeAdminDirectPurchase'])->name('purchasers.direct-purchase.store');
            Route::get('purchasers/{user:public_uuid}', [AdminAccountingController::class, 'purchaserShow'])->name('purchasers.show');
            Route::post('purchasers/{user:public_uuid}/credits', [AdminAccountingController::class, 'storePurchaserCredit'])->name('purchasers.credits.store');
            Route::post('purchasers/{user:public_uuid}/buy', [AdminAccountingController::class, 'buyAsPurchaser'])->name('purchasers.buy');
        });
        Route::post('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
        Route::resource('users', UserController::class)->middleware('can:admin.user.view');
        Route::resource('warehouses', WarehouseController::class)->middleware('can:inventory.stock.adjust');
        Route::middleware('can:hr.employee.view')->group(function () {
            Route::get('staff', [StaffManagementController::class, 'index'])->name('staff.index');
            Route::get('staff/employees', [StaffManagementController::class, 'employeesIndex'])->name('staff.employees.index');
            Route::get('staff/assignments', [StaffManagementController::class, 'assignmentsIndex'])->name('staff.assignments.index');
            Route::post('staff', [StaffManagementController::class, 'store'])->name('staff.store');
            Route::post('staff/shop-assignments', [StaffManagementController::class, 'storeShopEmployeeAssignment'])->name('staff.shop-assignments.store');
            Route::post('staff/sync-users', [StaffManagementController::class, 'syncLinkedUsers'])->name('staff.sync-users');
            Route::put('staff/{employee:employee_code}', [StaffManagementController::class, 'update'])->name('staff.update');
            Route::patch('staff/{employee:employee_code}/employment-status', [StaffManagementController::class, 'updateEmploymentStatus'])->name('staff.employment-status.update');
            Route::get('staff/categories', [StaffManagementController::class, 'categoriesIndex'])->name('staff.categories.index');
            Route::post('staff/categories', [StaffManagementController::class, 'storeCategory'])->name('staff.categories.store');
            Route::put('staff/categories/{employeeCategory}', [StaffManagementController::class, 'updateCategory'])->name('staff.categories.update');
            Route::put('staff/categories/{employeeCategory}/leave-rules', [StaffManagementController::class, 'updateCategoryLeaveRules'])->name('staff.categories.leave-rules.update');
            Route::get('staff/attendance', [StaffManagementController::class, 'attendanceIndex'])->name('staff.attendance');
            Route::post('staff/attendance', [StaffManagementController::class, 'storeAttendance'])->name('staff.attendance.store');
            Route::get('staff/leaves', [StaffManagementController::class, 'leavesIndex'])->name('staff.leaves.index');
            Route::post('staff/leaves', [StaffManagementController::class, 'storeLeave'])->name('staff.leaves.store');
            Route::patch('staff/leaves/{leaveRequest}', [StaffManagementController::class, 'reviewLeave'])->name('staff.leaves.review');
            Route::get('staff/payments', [StaffManagementController::class, 'paymentsIndex'])->name('staff.payments.index');
            Route::get('staff/advance-payments', [StaffManagementController::class, 'advancePaymentsIndex'])->name('staff.advance-payments.index');
            Route::post('staff/payments', [StaffManagementController::class, 'storePayrollPayment'])->name('staff.payments.store');
            Route::post('staff/shop-staff-payments', [StaffManagementController::class, 'storeShopStaffPayment'])->name('staff.shop-staff-payments.store');
            Route::post('staff/contract-worker-payments', [StaffManagementController::class, 'storeContractWorkerPayment'])->name('staff.contract-worker-payments.store');
            Route::patch('staff/advance-requests/{advanceRequest}', [StaffManagementController::class, 'reviewEmployeeAdvance'])->name('staff.advance-requests.review');
            Route::get('staff/payroll', [StaffManagementController::class, 'payrollIndex'])->name('staff.payroll.index');
            Route::get('staff/payroll/export/excel', [StaffManagementController::class, 'exportPayrollExcel'])->name('staff.payroll.export.excel');
            Route::get('staff/payroll/export/pdf', [StaffManagementController::class, 'exportPayrollPdf'])->name('staff.payroll.export.pdf');
            Route::post('staff/payroll', [StaffManagementController::class, 'storePayroll'])->name('staff.payroll.store');
            Route::patch('staff/payroll/{payrollRun}/items/{payrollRunItem}', [StaffManagementController::class, 'updatePayrollItem'])->name('staff.payroll.items.update');
            Route::post('staff/payroll/{payrollRun}/finalize', [StaffManagementController::class, 'finalizePayroll'])->name('staff.payroll.finalize');
            Route::get('staff/{employee:employee_code}', [StaffManagementController::class, 'show'])->name('staff.show');
        });
        Route::get('daily-progress', DailyProgressController::class)->name('daily-progress');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('price-approvals', function (Request $request) {
            return redirect()->route('purchasing.prices.index', [
                'date' => $request->input('date'),
            ]);
        })->name('price-approvals.index');
        Route::get('delivery-reviews', DeliveryReviewController::class)->name('delivery-reviews.index');
        Route::get('discrepancies', DiscrepancyReportController::class)->name('discrepancies.index');
    });

    // ── Sort Sheet ──────────────────────────────────────────────────────────────
    Route::prefix('sort-sheet')->name('sort-sheet.')->middleware('can:sort.sheet.view')->group(function () {
        Route::get('/', [SortSheetController::class, 'index'])->name('index');
        Route::get('/generate', [SortSheetController::class, 'generate'])->name('generate');
        Route::get('/export/excel', [SortSheetController::class, 'exportExcel'])->name('export.excel');
        Route::get('/export/pdf', [SortSheetController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/segregation/pdf', [SortSheetController::class, 'segregationPdf'])->name('segregation.pdf');
        Route::get('/segregation/matrix-print', [SortSheetController::class, 'segregationMatrixPrint'])->name('segregation.matrix-print');
        Route::get('/segregation/grid-print', [SortSheetController::class, 'segregationGridPrint'])->name('segregation.grid-print');
        Route::get('/print', [SortSheetController::class, 'print'])->name('print');
    });

    Route::prefix('segregation')->name('segregation.')->middleware('can:sort.sheet.view')->group(function () {
        Route::get('/', [SortSheetController::class, 'segregationIndex'])->name('index');
        Route::get('/generate', [SortSheetController::class, 'segregationGenerate'])->name('generate');
        Route::get('/export/excel', [SortSheetController::class, 'exportExcel'])->name('export.excel');
        Route::get('/matrix-print', [SortSheetController::class, 'segregationMatrixPrint'])->name('matrix-print');
        Route::get('/grid-print', [SortSheetController::class, 'segregationGridPrint'])->name('grid-print');
        Route::get('/print', [SortSheetController::class, 'segregationPdf'])->name('print');
    });
});
