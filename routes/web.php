<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutController;
use App\Http\Controllers\Web\Admin\AccountBalanceReportController;
use App\Http\Controllers\Web\Admin\ActivityLogController;
use App\Http\Controllers\Web\Admin\AdminAccountingController;
use App\Http\Controllers\Web\Admin\AdminAutoLoadAllController;
use App\Http\Controllers\Web\Admin\AdminCashbookReportsController;
use App\Http\Controllers\Web\Admin\AdminDailyAutoMatchController;
use App\Http\Controllers\Web\Admin\AdminOverviewController;
use App\Http\Controllers\Web\Admin\AdminProductPurchaserAllotmentController;
use App\Http\Controllers\Web\Admin\AdminPurchaserBusinessDayController;
use App\Http\Controllers\Web\Admin\AdminShopPurchasingVerificationController;
use App\Http\Controllers\Web\Admin\Cashbook\AdminTrayAssetController;
use App\Http\Controllers\Web\Admin\CashbookCategoryController;
use App\Http\Controllers\Web\Admin\CashbookController;
use App\Http\Controllers\Web\Admin\CashbookSalaryController;
use App\Http\Controllers\Web\Admin\CashbookSettlementController;
use App\Http\Controllers\Web\Admin\CashbookVendorController;
use App\Http\Controllers\Web\Admin\CashFlowTreeController;
use App\Http\Controllers\Web\Admin\CompanySettingsController;
use App\Http\Controllers\Web\Admin\DailyProgressController;
use App\Http\Controllers\Web\Admin\DatabaseBackupController;
use App\Http\Controllers\Web\Admin\DeliveryReviewController;
use App\Http\Controllers\Web\Admin\DiscrepancyReportController;
use App\Http\Controllers\Web\Admin\EmptyInventoryController;
use App\Http\Controllers\Web\Admin\EnquiryController;
use App\Http\Controllers\Web\Admin\FinalReportSettingsController;
use App\Http\Controllers\Web\Admin\FinanceV2Controller;
use App\Http\Controllers\Web\Admin\FinanceV2PaymentsController;
use App\Http\Controllers\Web\Admin\GreenLeafMonthlyReportController;
use App\Http\Controllers\Web\Admin\GreenLeafMonthlyReportExportController;
use App\Http\Controllers\Web\Admin\Integrations\ZohoBooksIntegrationController;
use App\Http\Controllers\Web\Admin\MonthlyClosingSummaryController;
use App\Http\Controllers\Web\Admin\PurchaseProductFilterController;
use App\Http\Controllers\Web\Admin\PurchaserMonthlySummaryController;
use App\Http\Controllers\Web\Admin\StaffManagementController;
use App\Http\Controllers\Web\Admin\StaffSyncFlagController;
use App\Http\Controllers\Web\Admin\UserAccessController;
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
use App\Http\Controllers\Web\Inventory\DeliveryDashboardOperationController;
use App\Http\Controllers\Web\Inventory\FulfillmentReportController;
use App\Http\Controllers\Web\Inventory\InventorySettingsController;
use App\Http\Controllers\Web\Inventory\ProductController;
use App\Http\Controllers\Web\Inventory\ShopOrderQuantityCorrectionController;
use App\Http\Controllers\Web\Inventory\StockAdjustmentController;
use App\Http\Controllers\Web\Inventory\StockController;
use App\Http\Controllers\Web\Inventory\WarehouseSortingController;
use App\Http\Controllers\Web\Inventory\WastageController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\Purchasing\AdminShopOrderController;
use App\Http\Controllers\Web\Purchasing\BillPriceApprovalController;
use App\Http\Controllers\Web\Purchasing\DailyPriceBoardController;
use App\Http\Controllers\Web\Purchasing\DailyPriceMatrixController;
use App\Http\Controllers\Web\Purchasing\DirectSaleController;
use App\Http\Controllers\Web\Purchasing\GoodsReceivedController;
use App\Http\Controllers\Web\Purchasing\OtherExpenseController;
use App\Http\Controllers\Web\Purchasing\ProcurementExpenseController;
use App\Http\Controllers\Web\Purchasing\PurchaseGradePriceController;
use App\Http\Controllers\Web\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Web\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Web\Purchasing\PurchaserBusinessDayCloseController;
use App\Http\Controllers\Web\Purchasing\PurchaserBusinessDaySubmissionController;
use App\Http\Controllers\Web\Purchasing\PurchaserDashboardController;
use App\Http\Controllers\Web\Purchasing\PurchaserReportController;
use App\Http\Controllers\Web\Purchasing\PurchasingBusinessDayController;
use App\Http\Controllers\Web\Purchasing\ShopInvoiceController;
use App\Http\Controllers\Web\Purchasing\ShopPriceGroupController;
use App\Http\Controllers\Web\Purchasing\SupplierController;
use App\Http\Controllers\Web\Purchasing\V2\PurchaserV2BuyController;
use App\Http\Controllers\Web\Purchasing\V2\PurchaserV2CartController;
use App\Http\Controllers\Web\Purchasing\V2\PurchaserV2DailyController;
use App\Http\Controllers\Web\Purchasing\V2\PurchaserV2DashboardController;
use App\Http\Controllers\Web\Purchasing\V2\PurchaserV2ReportController;
use App\Http\Controllers\Web\Purchasing\V2\PurchaserV2SearchController;
use App\Http\Controllers\Web\RequisitionController;
use App\Http\Controllers\Web\Sales\CustomerController;
use App\Http\Controllers\Web\Sales\PaymentController;
use App\Http\Controllers\Web\Sales\SalesInvoiceController;
use App\Http\Controllers\Web\ShopOwner\ShopPurchaseController;
use App\Http\Controllers\Web\ShopOwner\ShopPurchaserDailyVerificationController;
use App\Http\Controllers\Web\ShopOwnerController;
use App\Http\Controllers\Web\ShopOwnerStaffController;
use App\Http\Controllers\Web\ShopPresetController;
use App\Http\Controllers\Web\SortSheetController;
use App\Http\Controllers\Web\Warehouse\WarehouseLoadoutController;
use App\Http\Controllers\Web\Warehouse\WarehouseReceiverController;
use App\Http\Controllers\Web\Warehouse\WarehouseSalesController;
use App\Http\Controllers\Web\WebsiteEnquiryController;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public website
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $priceDate = DailyPriceApproval::query()
        ->where('status', 'approved')
        ->whereDate('business_date', now()->toDateString())
        ->exists()
            ? now()->toDateString()
            : DailyPriceApproval::query()
                ->where('status', 'approved')
                ->max('business_date');

    $marketPrices = collect();

    if ($priceDate !== null) {
        $marketPrices = DailyPriceApproval::query()
            ->with(['product.category'])
            ->where('status', 'approved')
            ->whereDate('business_date', $priceDate)
            ->where(function ($query): void {
                $query->where('price_a', '>', 0)
                    ->orWhere('purchase_price', '>', 0);
            })
            ->whereHas('product', fn ($query) => $query->active())
            ->orderByDesc('approved_at')
            ->limit(12)
            ->get()
            ->map(function (DailyPriceApproval $approval): array {
                $product = $approval->product;
                $price = $approval->price_a !== null && (float) $approval->price_a > 0
                    ? (float) $approval->price_a
                    : (float) $approval->purchase_price;

                return [
                    'name' => $product?->name ?? 'Fresh produce',
                    'category' => $product?->category?->name ?? 'Daily market',
                    'unit' => strtoupper((string) ($approval->price_unit ?: $product?->unit ?: 'kg')),
                    'price' => $price,
                    'image' => $product?->getImageUrl() ?? asset('images/header.png'),
                ];
            });
    }

    return view('welcome', [
        'marketPrices' => $marketPrices,
        'marketPriceDate' => $priceDate,
    ]);
})->name('home');
Route::get('/marketplace', function (Request $request) {
    $filters = $request->validate([
        'q' => ['nullable', 'string', 'max:80'],
        'category' => ['nullable', 'integer', 'exists:categories,id'],
        'sort' => ['nullable', 'string', 'in:featured,price_low,price_high,name'],
    ]);

    $priceDate = DailyPriceApproval::query()
        ->where('status', 'approved')
        ->whereDate('business_date', now()->toDateString())
        ->exists()
            ? now()->toDateString()
            : DailyPriceApproval::query()
                ->where('status', 'approved')
                ->max('business_date');

    $sort = $filters['sort'] ?? 'featured';
    $search = trim((string) ($filters['q'] ?? ''));
    $categoryId = isset($filters['category']) ? (int) $filters['category'] : null;

    $marketProducts = collect();

    if ($priceDate !== null) {
        $marketProducts = DailyPriceApproval::query()
            ->with(['product.category'])
            ->where('status', 'approved')
            ->whereDate('business_date', $priceDate)
            ->where(function ($query): void {
                $query->where('price_a', '>', 0)
                    ->orWhere('purchase_price', '>', 0);
            })
            ->whereHas('product', function ($query) use ($categoryId, $search): void {
                $query->active()
                    ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
                    ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
            })
            ->get()
            ->map(function (DailyPriceApproval $approval): array {
                $product = $approval->product;
                $price = $approval->price_a !== null && (float) $approval->price_a > 0
                    ? (float) $approval->price_a
                    : (float) $approval->purchase_price;

                return [
                    'id' => (int) $approval->product_id,
                    'name' => $product?->name ?? 'Fresh produce',
                    'category_id' => (int) ($product?->category_id ?? 0),
                    'category' => $product?->category?->name ?? 'Daily market',
                    'unit' => strtoupper((string) ($approval->price_unit ?: $product?->unit ?: 'kg')),
                    'price' => $price,
                    'image' => $product?->getImageUrl() ?? asset('images/header.png'),
                ];
            })
            ->when($sort === 'price_low', fn ($items) => $items->sortBy('price'))
            ->when($sort === 'price_high', fn ($items) => $items->sortByDesc('price'))
            ->when($sort === 'name', fn ($items) => $items->sortBy('name'))
            ->when($sort === 'featured', fn ($items) => $items->sortBy('category')->sortByDesc('price'))
            ->values();
    }

    $categories = Category::query()
        ->active()
        ->whereHas('products', fn ($query) => $query->active())
        ->orderBy('name')
        ->get(['id', 'name']);

    return view('marketplace.index', [
        'categories' => $categories,
        'filters' => [
            'q' => $search,
            'category' => $categoryId,
            'sort' => $sort,
        ],
        'marketProducts' => $marketProducts,
        'marketPriceDate' => $priceDate,
    ]);
})->name('marketplace.index');
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
        Route::get('/products', [ShopOwnerController::class, 'productsIndex'])->name('products.index');
        Route::get('/orders', [ShopOwnerController::class, 'ordersIndex'])->name('orders.index');
        Route::get('/orders/create', [ShopOwnerController::class, 'ordersCreate'])->name('orders.create');
        Route::delete('/orders/create/clear', [ShopOwnerController::class, 'clearTomorrowOrder'])->name('orders.clear');
        Route::get('/orders/history', [ShopOwnerController::class, 'ordersHistory'])->name('orders.history');
        Route::get('/orders/{order_number}', [ShopOwnerController::class, 'ordersShow'])->name('orders.show');
        Route::get('/deliveries', [ShopOwnerController::class, 'deliveriesIndex'])->name('deliveries.index');
        Route::get('/deliveries/{order_number}', [ShopOwnerController::class, 'deliveriesShow'])->name('deliveries.show');
        Route::get('/deliveries/{order_number}/pdf', [ShopOwnerController::class, 'deliveriesPdf'])->name('deliveries.pdf');
        Route::post('/deliveries/{order_number}/items/{item}/verify', [ShopOwnerController::class, 'verifyDeliveryItem'])->name('deliveries.items.verify');
        Route::post('/deliveries/{order_number}/verify-changes', [ShopOwnerController::class, 'verifyInvoiceChanges'])->name('deliveries.verify-changes');
        Route::get('/accounting', [ShopOwnerController::class, 'accountingIndex'])->name('accounting.index');
        Route::get('/accounting/cashbook/pdf', [ShopOwnerController::class, 'accountingCashbookPdf'])->name('accounting.cashbook.pdf');
        Route::get('/accounting/daily-report', [ShopOwnerController::class, 'accountingDailyReport'])->name('accounting.daily-report');
        Route::get('/accounting/history', [ShopOwnerController::class, 'accountingHistory'])->name('accounting.history');
        Route::post('/accounting/entries', [ShopOwnerController::class, 'storeAccountingEntry'])->name('accounting.entries.store');
        Route::post('/accounting/payment-requests', [ShopOwnerController::class, 'storePaymentRequest'])->name('accounting.payment-requests.store');
        Route::get('/payments', [ShopOwnerController::class, 'paymentsIndex'])->name('payments.index');
        Route::post('/payments/pay-expense', [ShopOwnerController::class, 'payExpense'])->name('payments.pay-expense');
        Route::get('/finance', [ShopOwnerController::class, 'financeIndex'])->name('finance.index');
        Route::get('/finance/{invoice}', [ShopOwnerController::class, 'financeShow'])->name('finance.show');
        Route::get('/finance/{invoice}/pdf', [ShopOwnerController::class, 'financePdf'])->name('finance.pdf');
        Route::get('/cashbook', [ShopOwnerController::class, 'cashbookShow'])->name('cashbook.show');
        Route::get('/cashbook/create', [ShopOwnerController::class, 'cashbookCreate'])->name('cashbook.create');
        Route::get('/cashbook/settings', [ShopOwnerController::class, 'cashbookSettings'])->name('cashbook.settings');
        Route::get('/cashbook/vendors', [CashbookVendorController::class, 'shopOwnerIndex'])->name('cashbook.vendors');
        Route::get('/cashbook/vendor-purchases', [ShopPurchaseController::class, 'vendorPurchasesPage'])->name('cashbook.vendor-purchases');
        Route::get('/cashbook/vendor-purchases/{invoice}', [ShopPurchaseController::class, 'showPurchase'])->name('cashbook.vendor-purchases.show');
        Route::put('/cashbook/vendor-purchases/{invoice}', [ShopPurchaseController::class, 'updatePurchase'])->name('cashbook.vendor-purchases.update');
        Route::delete('/cashbook/vendor-purchases/{invoice}', [ShopPurchaseController::class, 'destroyPurchase'])->name('cashbook.vendor-purchases.destroy');
        Route::get('/cashbook/reports', [ShopOwnerController::class, 'cashbookReports'])->name('cashbook.reports');
        Route::prefix('/cashbook/api')->name('cashbook.api.')->group(function () {
            Route::get('/shop-data', [ShopOwnerController::class, 'cashbookData'])->name('shop-data');
            Route::get('/products/search', [ShopOwnerController::class, 'cashbookSearchProducts'])->name('products.search');
            Route::post('/record-entry', [ShopOwnerController::class, 'cashbookRecordEntry'])->name('record-entry');
            Route::post('/bulk-record-entries', [ShopOwnerController::class, 'cashbookBulkRecordEntries'])->name('bulk-record-entries');
            Route::post('/update-entry', [ShopOwnerController::class, 'cashbookUpdateEntry'])->name('update-entry');
            Route::post('/delete-entry', [ShopOwnerController::class, 'cashbookDeleteEntry'])->name('delete-entry');
            Route::post('/delete-collection', [ShopOwnerController::class, 'cashbookDeleteCollection'])->name('delete-collection');
        });
        Route::get('/staff', [ShopOwnerStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [ShopOwnerStaffController::class, 'createEmployee'])->name('staff.create');
        Route::post('/staff/employees', [ShopOwnerStaffController::class, 'storeEmployee'])->name('staff.employees.store');
        Route::get('/staff/employees/{employee:employee_code}/edit-submission', [ShopOwnerStaffController::class, 'editEmployeeSubmission'])->name('staff.employees.edit-submission');
        Route::put('/staff/employees/{employee:employee_code}/resubmit', [ShopOwnerStaffController::class, 'resubmitEmployee'])->name('staff.employees.resubmit');
        Route::post('/staff/attendance', [ShopOwnerStaffController::class, 'storeAttendance'])->name('staff.attendance.store');
        Route::post('/staff/salary-payments', [ShopOwnerStaffController::class, 'storeSalaryPayment'])->name('staff.salary-payments.store');
        Route::post('/staff/advance-requests', [ShopOwnerStaffController::class, 'storeAdvanceRequest'])->name('staff.advance-requests.store');
        Route::post('/staff/leave-requests', [ShopOwnerStaffController::class, 'storeLeave'])->name('staff.leave-requests.store');
        Route::post('/staff/sync-cashbook', [ShopOwnerStaffController::class, 'syncCashbook'])->name('staff.sync-cashbook');
        Route::post('/staff/delete-cashbook-orphan', [ShopOwnerStaffController::class, 'deleteCashbookOrphan'])->name('staff.delete-cashbook-orphan');
        Route::put('/staff/payments/{payment}', [ShopOwnerStaffController::class, 'updateStaffPayment'])->name('staff.payments.update');
        Route::delete('/staff/payments/{payment}', [ShopOwnerStaffController::class, 'destroyStaffPayment'])->name('staff.payments.destroy');

        Route::prefix('purchasing')->name('purchasing.')->group(function () {
            Route::get('/', [ShopPurchaseController::class, 'index'])->name('index');
            Route::get('/create', [ShopPurchaseController::class, 'create'])->name('create');
            Route::post('/', [ShopPurchaseController::class, 'store'])->name('store');
            Route::post('/vendors', [ShopPurchaseController::class, 'storeVendor'])->name('vendors.store');
            Route::get('/vendors/search', [ShopPurchaseController::class, 'searchVendors'])->name('vendors.search');
            Route::get('/reports/vendors', [ShopPurchaseController::class, 'vendorReport'])->name('reports.vendors');
            Route::get('/reports/vendors/{supplier}', [ShopPurchaseController::class, 'vendorDetail'])->name('reports.vendor-detail');
            Route::get('/daily-verification', [ShopPurchaserDailyVerificationController::class, 'show'])->name('verification');
            Route::post('/daily-verification/verify', [ShopPurchaserDailyVerificationController::class, 'verify'])->name('verification.verify');
            Route::post('/daily-verification/second-verify', [ShopPurchaserDailyVerificationController::class, 'secondVerify'])->name('verification.second-verify');
            Route::post('/daily-verification/finalize', [ShopPurchaserDailyVerificationController::class, 'finalize'])->name('verification.finalize');
            Route::post('/daily-verification/carry-forward', [ShopPurchaserDailyVerificationController::class, 'carryForward'])->name('verification.carry-forward');
        });
    });

    // ── Inventory ──────────────────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::patch('products/{product}/status', [ProductController::class, 'updateStatus'])->name('products.status.update');

        Route::middleware('can:inventory.product.view')->group(function () {
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
            Route::get('products/export/csv', [ProductController::class, 'exportCsv'])->name('products.export.csv');
            Route::get('products/export/pdf', [ProductController::class, 'exportPdf'])->name('products.export.pdf');
            Route::get('products/export/whatsapp', [ProductController::class, 'exportWhatsApp'])->name('products.export.whatsapp');
            Route::get('products/flags', [ProductController::class, 'flags'])->name('products.flags');
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
            Route::post('stock/adjustments/{product}', [StockAdjustmentController::class, 'store'])
                ->middleware('can:inventory.stock.adjust')
                ->name('stock.adjustments.store');
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

            Route::get('settings', [InventorySettingsController::class, 'edit'])->name('settings.edit');
            Route::patch('settings', [InventorySettingsController::class, 'update'])->name('settings.update');

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
            Route::post('deliveries/dashboard/{shopOrder}/lock-invoice', [DeliveryDashboardOperationController::class, 'lockInvoice'])->name('deliveries.dashboard.lock-invoice');
            Route::get('reports/fulfillment', FulfillmentReportController::class)->name('reports.fulfillment');
        });
    });

    // ── Purchasing ─────────────────────────────────────────────────────────
    Route::prefix('purchasing')->name('purchasing.')->middleware('can:purchasing.order.view')->group(function () {
        Route::get('dashboard', fn () => redirect()->route('purchasing.orders.index'))->name('dashboard');

        Route::get('direct-sales/create', [DirectSaleController::class, 'create'])->name('direct-sales.create');
        Route::post('direct-sales', [DirectSaleController::class, 'store'])->name('direct-sales.store');

        // Suppliers
        Route::resource('suppliers', SupplierController::class);
        Route::post('suppliers/{supplier}/credit-request', [SupplierController::class, 'requestCreditApproval'])->name('suppliers.credit-request');
        Route::post('suppliers/{supplier}/credit-approve', [SupplierController::class, 'approveCreditApproval'])->name('suppliers.credit-approve');
        Route::get('prices', [DailyPriceBoardController::class, 'index'])->name('prices.index');
        Route::post('prices/toggle-publish', [DailyPriceBoardController::class, 'togglePublish'])->name('prices.toggle-publish');
        Route::post('prices/update', [DailyPriceBoardController::class, 'updatePrices'])->name('prices.update');
        Route::get('prices/matrix', [DailyPriceMatrixController::class, 'index'])->name('prices.matrix.index');
        Route::get('prices/matrix/export/excel', [DailyPriceMatrixController::class, 'exportExcel'])->name('prices.matrix.export.excel');
        Route::get('prices/matrix/export/pdf', [DailyPriceMatrixController::class, 'exportPdf'])->name('prices.matrix.export.pdf');
        Route::get('prices/matrix/export/whatsapp', [DailyPriceMatrixController::class, 'exportWhatsApp'])->name('prices.matrix.export.whatsapp');
        Route::post('prices/matrix/cell', [DailyPriceMatrixController::class, 'updateCell'])->name('prices.matrix.cell.update');
        Route::post('prices/matrix/fill-forward', [DailyPriceMatrixController::class, 'fillForward'])->name('prices.matrix.fill-forward');
        Route::post('prices/matrix/remove-future', [DailyPriceMatrixController::class, 'removeFuturePrices'])->name('prices.matrix.remove-future');
        Route::post('prices/matrix', [DailyPriceMatrixController::class, 'updateMatrix'])->name('prices.matrix.update');
        Route::post('price-groups/assign-shops', [ShopPriceGroupController::class, 'assignShops'])->name('price-groups.assign-shops');
        Route::resource('price-groups', ShopPriceGroupController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('shop-invoices', [ShopInvoiceController::class, 'index'])->name('shop-invoices.index');
        Route::get('shop-invoices/{invoice}', [ShopInvoiceController::class, 'show'])->name('shop-invoices.show');
        Route::get('shop-invoices/{invoice}/pdf', [ShopInvoiceController::class, 'pdf'])->name('shop-invoices.pdf');
        Route::patch('shop-invoices/{invoice}/items/{item}', [ShopInvoiceController::class, 'updateItem'])->name('shop-invoices.items.update');
        Route::post('shop-invoices/{invoice}/finalize-on-behalf', [ShopInvoiceController::class, 'finalizeOnBehalf'])->name('shop-invoices.finalize-on-behalf');
        Route::post('shop-invoices/{invoice}/revert-approval', [ShopInvoiceController::class, 'revertApproval'])->name('shop-invoices.revert-approval');
        Route::post('shop-invoices/{invoice}/reopen-for-edit', [ShopInvoiceController::class, 'reopenForEdit'])->name('shop-invoices.reopen-for-edit');

        Route::patch('shop-invoices/{invoice}/reprice', [ShopInvoiceController::class, 'reprice'])->name('shop-invoices.reprice');
        Route::get('bill-prices', [BillPriceApprovalController::class, 'index'])->name('bill-prices.index');
        Route::post('bill-prices', [BillPriceApprovalController::class, 'store'])->name('bill-prices.store');
        Route::get('bill-prices/{invoice}', [BillPriceApprovalController::class, 'show'])->name('bill-prices.show');
        Route::post('bill-prices/{invoice}/special-prices', [BillPriceApprovalController::class, 'updateInvoicePrices'])->name('bill-prices.invoice-prices.update');
        Route::post('bill-prices/copy-previous-day', [BillPriceApprovalController::class, 'copyPreviousDay'])->name('bill-prices.copy-previous-day');
        Route::patch('bill-prices/{specialPrice}/approve', [BillPriceApprovalController::class, 'approve'])->name('bill-prices.approve');
        Route::delete('bill-prices/{specialPrice}', [BillPriceApprovalController::class, 'destroy'])->name('bill-prices.destroy');

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
        Route::get('invoices/flagged', [PurchaseInvoiceController::class, 'flagged'])->name('invoices.flagged');
        Route::resource('invoices', PurchaseInvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('invoices/vendors/{supplier}', [PurchaseInvoiceController::class, 'vendorReport'])->name('invoices.vendor-report');
        Route::get('invoices/{invoice}/pdf', [PurchaseInvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/status', [PurchaseInvoiceController::class, 'updateStatus'])->name('invoices.update-status');
        Route::patch('invoices/{invoice}/payment', [PurchaseInvoiceController::class, 'updatePayment'])->name('invoices.update-payment');
        Route::post('invoices/{invoice}/cancel', [PurchaseInvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/fix-calculation', [PurchaseInvoiceController::class, 'fixCalculation'])->name('invoices.fix-calculation');
        Route::post('invoices/fix-all-calculations', [PurchaseInvoiceController::class, 'fixAllCalculations'])->name('invoices.fix-all-calculations');
        Route::post('invoices/{invoice}/supplier', [PurchaseInvoiceController::class, 'changeSupplier'])->name('invoices.change-supplier');

        // Purchaser Business Days
        Route::get('business-days', [PurchasingBusinessDayController::class, 'index'])->name('business-days.index');
        Route::post('business-days/open', [PurchasingBusinessDayController::class, 'open'])->name('business-days.open');
        Route::get('business-days/{uuid}', [PurchasingBusinessDayController::class, 'show'])->name('business-days.show');
        Route::post('business-days/{uuid}/verify-close', [PurchasingBusinessDayController::class, 'verifyAndClose'])->name('business-days.verify-close');
        Route::post('business-days/{uuid}/reopen', [PurchasingBusinessDayController::class, 'reopen'])->name('business-days.reopen');
        Route::get('business-days/{uuid}/bills/create', [PurchasingBusinessDayController::class, 'createBill'])->name('business-days.bills.create');
        Route::post('business-days/{uuid}/bills', [PurchasingBusinessDayController::class, 'storeBill'])->name('business-days.bills.store');
        Route::get('business-days/{uuid}/bills/{grn}/edit', [PurchasingBusinessDayController::class, 'editBill'])->name('business-days.bills.edit');
        Route::put('business-days/{uuid}/bills/{grn}', [PurchasingBusinessDayController::class, 'updateBill'])->name('business-days.bills.update');

        // Purchaser Business Days Close Submodule
        Route::prefix('business-days/{uuid}/close')->name('business-days.close.')->group(function () {
            Route::get('/', [PurchaserBusinessDayCloseController::class, 'index'])->name('index');
            Route::get('/bills', [PurchaserBusinessDayCloseController::class, 'bills'])->name('bills');
            Route::get('/advances', [PurchaserBusinessDayCloseController::class, 'advances'])->name('advances');
            Route::get('/pending', [PurchaserBusinessDayCloseController::class, 'pending'])->name('pending');
            Route::get('/issues', [PurchaserBusinessDayCloseController::class, 'issues'])->name('issues');
            Route::get('/inventory', [PurchaserBusinessDayCloseController::class, 'inventory'])->name('inventory');
            Route::get('/cancelled', [PurchaserBusinessDayCloseController::class, 'cancelled'])->name('cancelled');
            Route::post('/', [PurchaserBusinessDayCloseController::class, 'close'])->name('store');
        });
    });

    // ── Purchaser Business Days Close Submodule (Direct Purchaser Prefix) ───
    Route::prefix('purchaser/business-days/{uuid}/close')->name('purchaser.business-days.close.')->group(function () {
        Route::get('/', [PurchaserBusinessDayCloseController::class, 'index'])->name('index');
        Route::get('/bills', [PurchaserBusinessDayCloseController::class, 'bills'])->name('bills');
        Route::get('/advances', [PurchaserBusinessDayCloseController::class, 'advances'])->name('advances');
        Route::get('/pending', [PurchaserBusinessDayCloseController::class, 'pending'])->name('pending');
        Route::get('/issues', [PurchaserBusinessDayCloseController::class, 'issues'])->name('issues');
        Route::get('/inventory', [PurchaserBusinessDayCloseController::class, 'inventory'])->name('inventory');
        Route::get('/cancelled', [PurchaserBusinessDayCloseController::class, 'cancelled'])->name('cancelled');
        Route::post('/', [PurchaserBusinessDayCloseController::class, 'close'])->name('store');
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
        Route::get('/vendor-daily/excel', [FinanceController::class, 'vendorDailyExcel'])->name('vendor-daily.excel');
        Route::get('/vendor-daily/pdf', [FinanceController::class, 'vendorDailyPdf'])->name('vendor-daily.pdf');
        Route::get('/sales-daily', [FinanceController::class, 'salesDaily'])->name('sales-daily');
        Route::get('/sales-daily/excel', [FinanceController::class, 'salesDailyExcel'])->name('sales-daily.excel');
        Route::get('/sales-daily/pdf', [FinanceController::class, 'salesDailyPdf'])->name('sales-daily.pdf');
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
    Route::get('/purchaser/business-day', [PurchaserBusinessDaySubmissionController::class, 'show'])->name('purchaser.business-day.show');
    Route::post('/purchaser/business-day/submit', [PurchaserBusinessDaySubmissionController::class, 'store'])->name('purchaser.business-day.submit');
    Route::get('/purchaser/dashboard', [PurchaserDashboardController::class, 'index'])->name('purchaser.dashboard');
    Route::get('/purchaser/daily', [PurchaserDashboardController::class, 'daily'])->name('purchaser.daily');
    Route::get('/purchaser/daily/products/{product}/demand', [PurchaserDashboardController::class, 'dailyProductDemand'])->name('purchaser.daily.product-demand');
    Route::get('/purchaser/daily/products/{product}/purchase-options', [PurchaserDashboardController::class, 'dailyProductPurchaseOptions'])->name('purchaser.daily.product-purchase-options');
    Route::get('/purchaser/b-grade', [PurchaserDashboardController::class, 'bGrade'])->name('purchaser.b-grade');
    Route::get('/purchaser/daily/share', [PurchaserDashboardController::class, 'dailyShare'])->name('purchaser.daily.share');
    Route::get('/purchaser/daily/share/presets', [PurchaserDashboardController::class, 'dailySharePresets'])->name('purchaser.daily.share.presets');
    Route::post('/purchaser/daily/share/presets', [PurchaserDashboardController::class, 'dailySharePresetStore'])->name('purchaser.daily.share.presets.store');
    Route::get('/purchaser/products', [PurchaserDashboardController::class, 'products'])->name('purchaser.products');
    Route::get('/purchaser/daily-prices', [PurchaserDashboardController::class, 'dailyPrices'])->name('purchaser.daily-prices');
    Route::post('/purchaser/daily-prices', [PurchaserDashboardController::class, 'updateDailyPrices'])->name('purchaser.daily-prices.update');
    Route::get('/purchaser/purchase-grade-prices', [PurchaseGradePriceController::class, 'index'])->name('purchaser.purchase-grade-prices.index');
    Route::post('/purchaser/purchase-grade-prices', [PurchaseGradePriceController::class, 'update'])->name('purchaser.purchase-grade-prices.update');
    Route::post('/purchaser/purchase-grade-prices/copy-a-to-b', [PurchaseGradePriceController::class, 'copyGradeAToB'])->name('purchaser.purchase-grade-prices.copy-a-to-b');
    Route::get('/purchaser/shop-orders', [PurchaserDashboardController::class, 'shopOrders'])->name('purchaser.shop-orders.index');
    Route::get('/purchaser/shop-orders/{order_number}', [PurchaserDashboardController::class, 'shopOrderShow'])->name('purchaser.shop-orders.show');
    Route::get('/purchaser/add-ons/create', [RequisitionController::class, 'createPurchaserDirectPurchase'])->name('purchaser.add-ons.create');
    Route::get('/purchaser/bulk-buy', [PurchaserDashboardController::class, 'bulkBuy'])->name('purchaser.bulk-buy');
    Route::get('/purchaser/bulk-buy/details', [PurchaserDashboardController::class, 'bulkBuyDetails'])->name('purchaser.bulk-buy.details');
    Route::get('/purchaser/bulk-buy/tabs/fulfilled', [PurchaserDashboardController::class, 'bulkBuyFulfilled'])->name('purchaser.bulk-buy.tabs.fulfilled');
    Route::get('/purchaser/bulk-buy/product-search', [PurchaserDashboardController::class, 'bulkBuyProductSearch'])->name('purchaser.bulk-buy.product-search');
    Route::post('/purchaser/bulk-buy/add-ons', [PurchaserDashboardController::class, 'storeBulkBuyAddons'])->name('purchaser.bulk-buy.add-ons.store');
    Route::post('/purchaser/bulk-buy/add-ons-to-cart', [PurchaserDashboardController::class, 'storeAddonsToCart'])->name('purchaser.bulk-buy.add-ons-to-cart.store');
    Route::get('/purchaser/cart', [PurchaserDashboardController::class, 'cart'])->name('purchaser.cart');
    Route::get('/purchaser/vendors', [PurchaserDashboardController::class, 'vendors'])->name('purchaser.vendors');
    Route::get('/purchaser/vendors/tabs/pending', [PurchaserDashboardController::class, 'vendorsPendingTab'])->name('purchaser.vendors.tabs.pending');
    Route::get('/purchaser/vendors/tabs/completed', [PurchaserDashboardController::class, 'vendorsCompletedTab'])->name('purchaser.vendors.tabs.completed');
    Route::get('/purchaser/vendors/tabs/cancelled', [PurchaserDashboardController::class, 'vendorsCancelledTab'])->name('purchaser.vendors.tabs.cancelled');
    Route::get('/purchaser/suppliers', [PurchaserDashboardController::class, 'supplierHub'])->name('purchaser.suppliers');
    Route::get('/purchaser/suppliers/{supplier}', [PurchaserDashboardController::class, 'supplierShow'])->name('purchaser.suppliers.show');
    Route::get('/purchaser/suppliers/{supplier}/bulk-payment', [PurchaserDashboardController::class, 'showBulkPayment'])->name('purchaser.suppliers.bulk-payment.show');
    Route::get('/purchaser/finance', [PurchaserDashboardController::class, 'finance'])->name('purchaser.finance');
    Route::get('/purchaser/cash', [PurchaserDashboardController::class, 'cash'])->name('purchaser.cash');
    Route::get('/purchaser/procurement-expenses', [ProcurementExpenseController::class, 'index'])->name('purchaser.procurement-expenses.index');
    Route::get('/purchaser/other-expenses', [OtherExpenseController::class, 'index'])->name('purchaser.other-expenses.index');
    Route::get('/purchaser/bill-prices', [BillPriceApprovalController::class, 'index'])->name('purchaser.bill-prices.index');
    Route::post('/purchaser/bill-prices', [BillPriceApprovalController::class, 'store'])->name('purchaser.bill-prices.store');
    Route::get('/purchaser/bill-prices/{invoice}', [BillPriceApprovalController::class, 'show'])->name('purchaser.bill-prices.show');
    Route::get('/purchaser/bill-prices/{invoice}/discount', [BillPriceApprovalController::class, 'discount'])->name('purchaser.bill-prices.discount');
    Route::post('/purchaser/bill-prices/{invoice}/discount', [BillPriceApprovalController::class, 'applyDiscount'])->name('purchaser.bill-prices.discount.apply');
    Route::post('/purchaser/bill-prices/{invoice}/special-prices', [BillPriceApprovalController::class, 'updateInvoicePrices'])->name('purchaser.bill-prices.invoice-prices.update');
    Route::post('/purchaser/bill-prices/copy-previous-day', [BillPriceApprovalController::class, 'copyPreviousDay'])->name('purchaser.bill-prices.copy-previous-day');
    Route::patch('/purchaser/bill-prices/{specialPrice}/approve', [BillPriceApprovalController::class, 'approve'])->name('purchaser.bill-prices.approve');
    Route::delete('/purchaser/bill-prices/{specialPrice}', [BillPriceApprovalController::class, 'destroy'])->name('purchaser.bill-prices.destroy');
    Route::get('/purchaser/cart/{cart}/bill', [PurchaserDashboardController::class, 'bill'])->name('purchaser.bill');
    Route::get('/purchaser/history', [PurchaserDashboardController::class, 'history'])->name('purchaser.history');
    Route::get('/purchaser/history/{cart}/details', [PurchaserDashboardController::class, 'historyCartDetails'])->name('purchaser.history.details');
    Route::get('/purchaser/reports/sales-summary', [PurchaserReportController::class, 'salesSummary'])
        ->middleware('can:purchaser.reports.sales.view')
        ->name('purchaser.reports.sales-summary');
    Route::get('/purchaser/reports/sales-summary/csv', [PurchaserReportController::class, 'salesSummaryCsv'])
        ->middleware('can:purchaser.reports.sales.view')
        ->name('purchaser.reports.sales-summary.csv');
    Route::get('/purchaser/reports/sales-summary/excel', [PurchaserReportController::class, 'salesSummaryExcel'])
        ->middleware('can:purchaser.reports.sales.view')
        ->name('purchaser.reports.sales-summary.excel');
    Route::get('/purchaser/reports/sales-summary/pdf', [PurchaserReportController::class, 'salesSummaryPdf'])
        ->middleware('can:purchaser.reports.sales.view')
        ->name('purchaser.reports.sales-summary.pdf');
    Route::get('/purchaser/reports/item-summary', [PurchaserReportController::class, 'itemSummary'])
        ->middleware('can:purchaser.reports.items.view')
        ->name('purchaser.reports.item-summary');
    Route::get('/purchaser/reports/item-summary/csv', [PurchaserReportController::class, 'itemSummaryCsv'])
        ->middleware('can:purchaser.reports.items.view')
        ->name('purchaser.reports.item-summary.csv');
    Route::get('/purchaser/reports/item-summary/excel', [PurchaserReportController::class, 'itemSummaryExcel'])
        ->middleware('can:purchaser.reports.items.view')
        ->name('purchaser.reports.item-summary.excel');
    Route::get('/purchaser/reports/item-summary/pdf', [PurchaserReportController::class, 'itemSummaryPdf'])
        ->middleware('can:purchaser.reports.items.view')
        ->name('purchaser.reports.item-summary.pdf');
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
    Route::get('/purchaser/invoices/{invoice}', [PurchaserDashboardController::class, 'invoiceShow'])->name('purchaser.invoices.show')->withTrashed();
    Route::delete('/purchaser/invoices/{invoice}', [PurchaserDashboardController::class, 'destroyInvoice'])->name('purchaser.invoices.destroy');
    Route::get('/purchaser/invoices/{invoice}/pdf', [PurchaserDashboardController::class, 'invoicePdf'])->name('purchaser.invoices.pdf')->withTrashed();
    Route::patch('/purchaser/invoices/{invoice}/payment', [PurchaserDashboardController::class, 'updateInvoicePayment'])->name('purchaser.invoices.payment');
    Route::post('/purchaser/suppliers/{supplier}/bulk-payment', [PurchaserDashboardController::class, 'bulkPayment'])->name('purchaser.suppliers.bulk-payment');
    Route::post('/purchaser/corrections', [PurchaserDashboardController::class, 'storeCorrectionRequest'])->name('purchaser.corrections.store');
    Route::post('/purchaser/corrections/{correctionRequest}/approve', [PurchaserDashboardController::class, 'approveCorrectionRequest'])->name('purchaser.corrections.approve');
    Route::post('/purchaser/corrections/{correctionRequest}/reject', [PurchaserDashboardController::class, 'rejectCorrectionRequest'])->name('purchaser.corrections.reject');
    Route::post('/purchaser/exit-admin-view', [AdminAccountingController::class, 'stopPurchaserViewAsAdmin'])->name('purchaser.exit-admin-view');
    Route::post('/admin/user-access/stop', [UserAccessController::class, 'stop'])->name('admin.user-access.stop');

    // ── Warehouse Receiver ─────────────────────────────────────────────────
    Route::prefix('warehouse-receiver')->name('warehouse.receiver.')->middleware('can:warehouse.receive.view')->group(function () {
        Route::get('/products', [ProductController::class, 'receiverIndex'])->name('products.index');
        Route::get('/checklist', [WarehouseReceiverController::class, 'index'])->name('checklist');
        Route::post('/confirm/{batch}', [WarehouseReceiverController::class, 'confirm'])->name('confirm');
        Route::post('/confirm-all', [WarehouseReceiverController::class, 'confirmAll'])->name('confirm-all');
        Route::post('/receive-grns/all', [WarehouseReceiverController::class, 'processReceiveAllGrns'])->name('process-receive-grns.all');
        Route::get('/receive-grn/{grn}', [WarehouseReceiverController::class, 'receiveGrnForm'])->name('receive-grn');
        Route::post('/receive-grn/{grn}', [WarehouseReceiverController::class, 'processReceiveGrn'])->name('process-receive-grn');
        Route::get('/loadout/{order}', [WarehouseReceiverController::class, 'loadoutDetails'])->name('loadout.show');
        Route::post('/loadout/item/{item}', [WarehouseReceiverController::class, 'loadoutItem'])->name('loadout.item');
        Route::post('/loadout/order/{order}/all', [WarehouseReceiverController::class, 'loadoutOrderAll'])->name('loadout.order-all');
        Route::post('/loadout/order/{order}/dispatch', [WarehouseReceiverController::class, 'dispatchOrder'])->name('loadout.order.dispatch');
        Route::post('/loadout/order/{order}/dispatch-partial', [WarehouseReceiverController::class, 'dispatchPartialOrder'])->name('loadout.order.dispatch-partial');
        Route::post('/loadout/order/{order}/ship', [WarehouseReceiverController::class, 'shipOrder'])->name('loadout.order.ship');
        // ── Tab JSON endpoints (lazy-loaded by checklist.blade.php via fetch()) ──
        Route::get('/tab/pending', [WarehouseReceiverController::class, 'tabPending'])->name('tab.pending');
        Route::get('/tab/inventory', [WarehouseReceiverController::class, 'tabInventory'])->name('tab.inventory');
        Route::get('/tab/loadout', [WarehouseReceiverController::class, 'tabLoadout'])->name('tab.loadout');
        Route::get('/tab/deliveries', [WarehouseReceiverController::class, 'tabDeliveries'])->name('tab.deliveries');
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
    Route::prefix('warehouse/loadout')->name('warehouse.loadout.')->group(function () {
        Route::get('/', [WarehouseLoadoutController::class, 'index'])->name('index');
        Route::get('/{shopOrder}/addon', [WarehouseLoadoutController::class, 'createAddon'])->name('addon.create');
        Route::post('/{shopOrder}/addon', [WarehouseLoadoutController::class, 'storeAddon'])->name('addon.store');
        Route::get('/{shopOrder}/slip', [WarehouseLoadoutController::class, 'slip'])->name('slip');
        Route::get('/{shopOrder}', [WarehouseLoadoutController::class, 'show'])->name('show');
        Route::post('/{shopOrder}/save', [WarehouseLoadoutController::class, 'save'])->name('save');
        Route::post('/{shopOrder}/merge-duplicates', [WarehouseLoadoutController::class, 'mergeDuplicates'])->name('merge-duplicates');
        Route::post('/{shopOrder}/merge-duplicates/all', [WarehouseLoadoutController::class, 'mergeAllDuplicates'])->name('merge-duplicates.all');
        Route::post('/{shopOrder}/move-to-delivery', [WarehouseLoadoutController::class, 'moveToDelivery'])->name('move-to-delivery');
        Route::post('/{shopOrder}/move-to-partial-delivery', [WarehouseLoadoutController::class, 'moveToPartialDelivery'])->name('move-to-partial-delivery');
        Route::post('/{shopOrder}/move-to-loadout', [WarehouseLoadoutController::class, 'moveToLoadout'])->name('move-to-loadout');
        Route::post('/{shopOrder}/remove-unpriced-items', [WarehouseLoadoutController::class, 'removeUnpricedItems'])->name('remove-unpriced-items');
    });

    // ── Operational Warehouse Sales ─────────────────────────────────────────
    Route::prefix('warehouse/sales')->name('warehouse.sales.')->group(function () {
        Route::get('/', [WarehouseSalesController::class, 'index'])->name('index');
        Route::get('/create', [WarehouseSalesController::class, 'create'])->name('create');
        Route::post('/', [WarehouseSalesController::class, 'store'])->name('store');
        Route::get('/search-products', [WarehouseSalesController::class, 'searchProducts'])->name('search-products');
        Route::get('/search-customers', [WarehouseSalesController::class, 'searchCustomers'])->name('search-customers');
        Route::post('/customers', [WarehouseSalesController::class, 'storeCustomer'])->name('customers.store');
        Route::get('/{warehouseSale}', [WarehouseSalesController::class, 'show'])->name('show');
        Route::post('/{warehouseSale}/cancel', [WarehouseSalesController::class, 'cancel'])->name('cancel');
    });

    // ── Admin ──────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('inventory/empty', [EmptyInventoryController::class, 'index'])->name('inventory-empty.index');
        Route::post('inventory/empty', [EmptyInventoryController::class, 'store'])->name('inventory-empty.store');
        Route::get('inventory/empty/{process}/progress', [EmptyInventoryController::class, 'progress'])->name('inventory-empty.progress');
        Route::post('inventory/empty/{process}/retry', [EmptyInventoryController::class, 'retry'])->name('inventory-empty.retry');
        Route::get('/', AdminOverviewController::class)->name('overview');
        Route::get('company-settings', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
        Route::patch('company-settings', [CompanySettingsController::class, 'update'])->name('company-settings.update');

        // ── Zoho Books Integration ─────────────────────────────────────────
        Route::prefix('integrations/zoho-books')->name('integrations.zoho-books.')->group(function () {
            Route::get('/', [ZohoBooksIntegrationController::class, 'index'])->name('index');
            Route::get('/connect', [ZohoBooksIntegrationController::class, 'connect'])->name('connect');
            Route::get('/callback', [ZohoBooksIntegrationController::class, 'callback'])->name('callback');
            Route::post('/disconnect', [ZohoBooksIntegrationController::class, 'disconnect'])->name('disconnect');
            Route::match(['GET', 'POST'], '/test', [ZohoBooksIntegrationController::class, 'test'])->name('test');
            Route::post('/select-organization', [ZohoBooksIntegrationController::class, 'selectOrganization'])->name('select-organization');
            Route::post('/refresh-accounts', [ZohoBooksIntegrationController::class, 'refreshAccounts'])->name('refresh-accounts');
            Route::post('/mappings', [ZohoBooksIntegrationController::class, 'saveMapping'])->name('mappings.save');
            Route::delete('/mappings/{ledgerEntryTypeId}', [ZohoBooksIntegrationController::class, 'removeMapping'])->name('mappings.remove');
        });
        Route::get('integrations/zoho/callback', [ZohoBooksIntegrationController::class, 'callback'])->name('integrations.zoho.callback');
        Route::post('business-day/override', [BusinessDaySettingsController::class, 'overrideToday'])->name('business-day.override');
        Route::post('business-day/reset-override', [BusinessDaySettingsController::class, 'resetOverride'])->name('business-day.reset-override');
        Route::resource('users', UserController::class);
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{user}/force', [UserController::class, 'forceDelete'])->name('users.force-delete');
        Route::post('users/{user}/login-as', [UserAccessController::class, 'loginAs'])->name('users.login-as');
        Route::get('staff-management', [StaffManagementController::class, 'index'])->name('staff-management.index');
        Route::post('staff-management/sync-access', [StaffManagementController::class, 'syncAccess'])->name('staff-management.sync-access');
        Route::post('auto-load-all/runs/summary', [AdminAutoLoadAllController::class, 'storeRunSummary'])->name('auto-load-all.runs.summary');
        Route::get('auto-load-all', [AdminAutoLoadAllController::class, 'create'])->name('auto-load-all.create');
        Route::prefix('auto-load-all/api')->name('auto-load-all.api.')->group(function () {
            Route::get('/manifest', [ApiWarehouseLoadoutController::class, 'index'])->name('manifest');
            Route::get('/orders/{shopOrder}', [ApiWarehouseLoadoutController::class, 'show'])->name('show');
            Route::post('/orders/{shopOrder}/save', [ApiWarehouseLoadoutController::class, 'save'])->name('save');
            Route::post('/runs', [AdminAutoLoadAllController::class, 'storeRunSummary'])->name('runs.store');
        });

        Route::prefix('purchasing')->name('purchasing.')->group(function () {
            Route::get('daily-verifications', [AdminShopPurchasingVerificationController::class, 'index'])->name('daily-verifications.index');
            Route::post('daily-verifications/{verification}/reopen', [AdminShopPurchasingVerificationController::class, 'reopen'])->name('daily-verifications.reopen');
        });
        // Cashbook admin dashboard — full port of the standalone ledger-app.
        // Completely isolated from the ShopOwner accounting screens.
        // All routes are guarded at controller level by ensureMainAdmin().
        Route::prefix('cashbook')->name('cashbook.')->group(function () {
            // ── Categories Unified Management ─────────────────────────────
            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/', [CashbookCategoryController::class, 'index'])->name('index');
                Route::get('/create', [CashbookCategoryController::class, 'create'])->name('create');
                Route::post('/', [CashbookCategoryController::class, 'store'])->name('store');
                Route::get('/{category}', [CashbookCategoryController::class, 'show'])->name('show');
                Route::post('/{category}/update-global', [CashbookCategoryController::class, 'updateGlobal'])->name('update-global');
                Route::post('/{category}/assign-shops', [CashbookCategoryController::class, 'assignShops'])->name('assign-shops');
                Route::post('/{category}/shops/{shop}/basic-header', [CashbookCategoryController::class, 'updateShopBasicHeader'])->name('shop.basic-header');
                Route::post('/{category}/shops/{shop}/settlement', [CashbookCategoryController::class, 'updateShopSettlement'])->name('shop.settlement');
                Route::post('/{category}/shops/{shop}/company-relation', [CashbookCategoryController::class, 'updateShopCompanyRelation'])->name('shop.company-relation');
                Route::post('/{category}/shops/{shop}/vendor-relation', [CashbookCategoryController::class, 'updateShopVendorRelation'])->name('shop.vendor-relation');
                Route::post('/{category}/shops/{shop}/reports', [CashbookCategoryController::class, 'updateShopReports'])->name('shop.reports');
                Route::post('/{category}/shops/{shop}/advanced', [CashbookCategoryController::class, 'updateShopAdvanced'])->name('shop.advanced');
            });

            // ── Page routes ─────────────────────────────────────────────────
            Route::get('/', [CashbookController::class, 'index'])->name('index');
            Route::get('cash-flow-tree', [CashFlowTreeController::class, 'index'])->name('cash-flow-tree.index');
            Route::get('cash-flow-tree/drilldown', [CashFlowTreeController::class, 'drilldown'])->name('cash-flow-tree.drilldown');
            Route::get('cash-flow-tree/edge-drilldown', [CashFlowTreeController::class, 'edgeDrilldown'])->name('cash-flow-tree.edge-drilldown');
            Route::get('all-shops', [CashbookController::class, 'allShops'])->name('all-shops');
            Route::get('account-balance', [AccountBalanceReportController::class, 'index'])->name('account-balance');
            Route::delete('account-balance/statements/{entry}', [AccountBalanceReportController::class, 'destroyStatementEntry'])->name('account-balance.statements.delete');
            Route::delete('account-balance/payment-requests/{paymentRequest}', [AccountBalanceReportController::class, 'destroyPaymentRequest'])->name('account-balance.payment-requests.delete');
            Route::get('monthly-closing-summary', [MonthlyClosingSummaryController::class, 'index'])->name('monthly-closing-summary.index');
            Route::get('monthly-closing-summary/shop/{shop}', [MonthlyClosingSummaryController::class, 'show'])->name('monthly-closing-summary.show');
            Route::get('overview-cards', [AdminCashbookReportsController::class, 'hub'])->name('reports.hub');
            Route::get('reports', [CashbookController::class, 'reports'])->name('reports');
            Route::get('reports/shop/{shop}', [AdminCashbookReportsController::class, 'detail'])->name('reports.shop');
            Route::get('reports/charts', [AdminCashbookReportsController::class, 'charts'])->name('reports.charts');
            Route::get('reports/analytics', [AdminCashbookReportsController::class, 'analytics'])->name('reports.analytics');
            Route::get('reports/gl-bills', [AdminCashbookReportsController::class, 'glBills'])->name('reports.gl-bills');
            Route::get('reports/gl-bills/export/csv', [AdminCashbookReportsController::class, 'glBillsExportCsv'])->name('reports.gl-bills.export.csv');
            Route::get('reports/gl-bills/export/pdf', [AdminCashbookReportsController::class, 'glBillsExportPdf'])->name('reports.gl-bills.export.pdf');
            Route::get('warehouse-sales', [AdminCashbookReportsController::class, 'warehouseSales'])->name('warehouse-sales');

            // ── Green Leaf Monthly Reports ────────────────────────────────────
            Route::prefix('reports/monthly')->name('monthly-report.')->group(function () {
                Route::get('/', [GreenLeafMonthlyReportController::class, 'overview'])->name('overview');
                Route::get('/sale-split', [GreenLeafMonthlyReportController::class, 'saleSplit'])->name('sale-split');
                Route::get('/other-expenses', [GreenLeafMonthlyReportController::class, 'otherExpenses'])->name('other-expenses');
                Route::get('/expense-report', [GreenLeafMonthlyReportController::class, 'expenseReport'])->name('expense-report');
                Route::get('/drilldown', [GreenLeafMonthlyReportController::class, 'drilldown'])->name('drilldown');
                Route::get('/{report}/export/csv', [GreenLeafMonthlyReportExportController::class, 'exportCsv'])->name('export.csv');
                Route::get('/{report}/export/excel', [GreenLeafMonthlyReportExportController::class, 'exportExcel'])->name('export.excel');
                Route::get('/{report}/export/pdf', [GreenLeafMonthlyReportExportController::class, 'exportPdf'])->name('export.pdf');
            });

            // ── Assets → Trays ────────────────────────────────────────────────
            Route::prefix('assets/trays')->name('assets.trays.')->group(function () {
                Route::get('/', [AdminTrayAssetController::class, 'index'])->name('index');
                Route::post('/', [AdminTrayAssetController::class, 'store'])->name('store');
                Route::get('/{trayType}', [AdminTrayAssetController::class, 'show'])->name('show');
                Route::get('/{trayType}/edit', [AdminTrayAssetController::class, 'edit'])->name('edit');
                Route::put('/{trayType}', [AdminTrayAssetController::class, 'update'])->name('update');
                Route::get('/shop/{shop}/date/{date}/edit', [AdminTrayAssetController::class, 'editShopDate'])->name('shop-date.edit');
                Route::put('/shop/{shop}/date/{date}', [AdminTrayAssetController::class, 'updateShopDate'])->name('shop-date.update');
            });

            // ── Final Report Settings ─────────────────────────────────────────
            Route::prefix('settings/final-report')->name('settings.final-report.')->group(function () {
                Route::get('/', [FinalReportSettingsController::class, 'index'])->name('index');
                Route::match(['POST', 'PUT'], '/shop-headings', [FinalReportSettingsController::class, 'updateShopHeadings'])->name('shop-headings');
                Route::match(['POST', 'PUT'], '/product-groups', [FinalReportSettingsController::class, 'updateProductGroups'])->name('product-groups');
                Route::match(['POST', 'PUT'], '/expense-mappings', [FinalReportSettingsController::class, 'updateExpenseMappings'])->name('expense-mappings');
                Route::get('/readiness', [FinalReportSettingsController::class, 'readiness'])->name('readiness');
            });
            // Purchaser Business Days (Admin Cashbook Oversight)
            Route::prefix('purchaser-business-days')->name('purchaser-business-days.')->group(function () {
                Route::get('/', [AdminPurchaserBusinessDayController::class, 'index'])->name('index');
                Route::get('reports', [AdminPurchaserBusinessDayController::class, 'reports'])->name('reports');
                Route::get('reports/pending-export/pdf', [AdminPurchaserBusinessDayController::class, 'exportPendingPdf'])->name('reports.pending-pdf');
                Route::post('allotments/preview', [AdminPurchaserBusinessDayController::class, 'previewAllotment'])->name('allotments.preview');
                Route::post('allotments/assign', [AdminPurchaserBusinessDayController::class, 'assignAllotment'])->name('allotments.assign');
                Route::get('allotments/{product}/history', [AdminPurchaserBusinessDayController::class, 'productHistory'])->name('allotments.history');
                Route::get('{uuid}', [AdminPurchaserBusinessDayController::class, 'show'])->name('show');
                Route::post('{uuid}/reopen', [AdminPurchaserBusinessDayController::class, 'reopen'])->name('reopen');
            });

            Route::get('auto-match', [AdminDailyAutoMatchController::class, 'index'])->name('auto-match');
            Route::get('auto-match/preview', [AdminDailyAutoMatchController::class, 'preview'])->name('auto-match.preview');
            Route::post('auto-match/execute', [AdminDailyAutoMatchController::class, 'execute'])->name('auto-match.execute');
            Route::get('inventory', [AdminCashbookReportsController::class, 'inventory'])->name('inventory');
            Route::get('inventory/print-pending', [AdminCashbookReportsController::class, 'printPendingBills'])->name('inventory.print-pending');
            Route::get('inventory/print-unmatched', [AdminCashbookReportsController::class, 'printUnmatchedInventory'])->name('inventory.print-unmatched');
            Route::get('inventory/share/unmatched-advances/whatsapp', [AdminCashbookReportsController::class, 'shareUnmatchedAdvancesWhatsApp'])->name('inventory.share.unmatched-advances.whatsapp');
            Route::get('inventory/pending-bills-days', [AdminCashbookReportsController::class, 'pendingBillsDaysSummary'])->name('inventory.pending-bills-days');
            Route::get('inventory/pending-bills-day-details', [AdminCashbookReportsController::class, 'pendingBillsDayDetails'])->name('inventory.pending-bills-day-details');
            Route::post('inventory/accept-pending-bills', [AdminCashbookReportsController::class, 'acceptPendingBills'])->name('inventory.accept-pending-bills');
            Route::post('inventory/match-bill/{goodsReceived}', [AdminCashbookReportsController::class, 'matchBill'])->name('inventory.match-bill');
            Route::get('inventory/auto-clear-plan', [AdminCashbookReportsController::class, 'autoClearPlan'])->name('inventory.auto-clear-plan');
            Route::post('inventory/auto-clear-execute', [AdminCashbookReportsController::class, 'autoClearExecute'])->name('inventory.auto-clear-execute');
            Route::get('inventory/manual-match-suggestions/{order}', [AdminCashbookReportsController::class, 'manualMatchSuggestions'])->name('inventory.manual-match-suggestions');
            Route::post('inventory/manual-match/{order}', [AdminCashbookReportsController::class, 'manualMatchExecute'])->name('inventory.manual-match');
            Route::post('inventory/resolve-unit-difference', [AdminCashbookReportsController::class, 'resolveUnitDifference'])->name('inventory.resolve-unit-difference');
            Route::post('inventory/fix-advance-units', [AdminCashbookReportsController::class, 'fixAdvanceUnits'])->name('inventory.fix-advance-units');
            Route::post('inventory/update-item-unit', [AdminCashbookReportsController::class, 'updateItemUnit'])->name('inventory.update-item-unit');
            Route::post('inventory/match-day', [AdminCashbookReportsController::class, 'matchDayInventory'])->name('inventory.match-day');
            Route::post('inventory/match-all-day', [AdminCashbookReportsController::class, 'matchAllDayInventory'])->name('inventory.match-all-day');
            Route::post('inventory/receive-all-pending-bills', [AdminCashbookReportsController::class, 'receiveAllPendingBills'])->name('inventory.receive-all-pending-bills');
            Route::post('inventory/receive-single-bill', [AdminCashbookReportsController::class, 'receiveSingleBill'])->name('inventory.receive-single-bill');
            Route::post('inventory/move-to-damage', [AdminCashbookReportsController::class, 'moveToDamage'])->name('inventory.move-to-damage');
            Route::post('inventory/clear-advances', [AdminCashbookReportsController::class, 'clearAdvances'])->name('inventory.clear-advances');
            Route::get('bill-changes', [AdminCashbookReportsController::class, 'billChanges'])->name('bill-changes');
            Route::get('bill-changes/api/shop-day', [AdminCashbookReportsController::class, 'billChangesShopDay'])->name('bill-changes.shop-day');
            Route::get('reports/products', [AdminCashbookReportsController::class, 'products'])->name('reports.products');
            Route::get('products', [AdminCashbookReportsController::class, 'products'])->name('products');
            Route::get('reports/api/hub', [AdminCashbookReportsController::class, 'apiHubData'])->name('reports.api.hub');
            Route::get('mobile/ledger/{shop}', [AdminCashbookReportsController::class, 'mobileLedger'])->name('reports.mobile-ledger');
            Route::get('reports/export/csv', [CashbookController::class, 'exportReportsCsv'])->name('reports.export.csv');
            Route::get('reports/export/excel', [CashbookController::class, 'exportReportsExcel'])->name('reports.export.excel');
            Route::get('reports/export/pdf', [CashbookController::class, 'exportReportsPdf'])->name('reports.export.pdf');
            Route::get('payables', [CashbookController::class, 'payables'])->name('payables');
            Route::get('money-flow', [CashbookController::class, 'moneyFlow'])->name('money-flow');
            Route::get('transactions/{transaction}', [CashbookController::class, 'showTransaction'])->name('transaction.show');
            Route::get('transactions/{transaction}/edit', [CashbookController::class, 'editTransaction'])->name('transaction.edit');
            Route::put('transactions/{transaction}', [CashbookController::class, 'updateTransaction'])->name('transaction.update');
            Route::post('transactions/{transaction}/reverse', [CashbookController::class, 'reverseTransaction'])->name('transaction.reverse');
            Route::post('transactions/{transaction}/revert-approval', [CashbookController::class, 'revertTransactionApproval'])->name('transaction.revert-approval');
            Route::delete('transactions/{transaction}', [CashbookController::class, 'deleteTransaction'])->name('transaction.delete');
            Route::post('transactions/{transaction}/approve', [CashbookController::class, 'approveTransaction'])->name('transaction.approve');
            Route::post('transactions/{transaction}/verify', [CashbookController::class, 'verifyTransaction'])->name('transaction.verify');
            Route::get('finance', [CashbookController::class, 'companyFinancePage'])->name('finance');
            Route::get('finance/cheque-submission', [CashbookController::class, 'companyFinanceChequeSubmission'])->name('finance.cheque-submission');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/company-accounting', [CashbookController::class, 'classifyCompanyAccountingStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-company-accounting');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/shop-petty', [CashbookController::class, 'classifyShopPettyStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-shop-petty');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/company-payable', [CashbookController::class, 'classifyCompanyPayableStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-company-payable');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/vendor-payment', [CashbookController::class, 'classifyVendorPaymentStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-vendor-payment');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/purchaser-funding', [CashbookController::class, 'classifyPurchaserFundingStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-purchaser-funding');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/shop-payment', [CashbookController::class, 'classifyShopPaymentStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-shop-payment');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/verify-shop-collection', [CashbookController::class, 'verifyShopCollectionStatement'])->whereUuid('statement')->name('finance.reconciliation.verify-shop-collection');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/match-existing', [CashbookController::class, 'matchExistingStatement'])->whereUuid('statement')->name('finance.reconciliation.match-existing');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/confirm-suggestion', [CashbookController::class, 'confirmSuggestedStatement'])->whereUuid('statement')->name('finance.reconciliation.confirm-suggestion');
            Route::post('finance/reconciliation/confirm-suggestions', [CashbookController::class, 'confirmSuggestedStatements'])->name('finance.reconciliation.confirm-suggestions');
            Route::post('finance/reconciliation/auto-match-shop-collections/preview', [CashbookController::class, 'previewAutoMatchShopCollections'])->name('finance.reconciliation.auto-match-shop-collections.preview');
            Route::post('finance/reconciliation/auto-match-shop-collections/execute', [CashbookController::class, 'executeAutoMatchShopCollections'])->name('finance.reconciliation.auto-match-shop-collections.execute');
            Route::post('finance/reconciliation/auto-match-shop-collections/reassign-bank-mapping', [CashbookController::class, 'reassignAutoMatchBankMapping'])->name('finance.reconciliation.auto-match-shop-collections.reassign');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/salary-payment', [CashbookController::class, 'classifySalaryPaymentStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-salary-payment');
            Route::post('finance/reconciliation/statements/{statement:public_uuid}/salary-advance', [CashbookController::class, 'classifySalaryAdvanceStatement'])->whereUuid('statement')->name('finance.reconciliation.classify-salary-advance');
            Route::get('finance/reconciliation/statements/{statement:public_uuid}/create', [CashbookController::class, 'createCompanyFinanceReconciliationTransaction'])->whereUuid('statement')->name('finance.reconciliation.create-transaction');
            Route::post('finance/reconciliation/reset-month', [CashbookController::class, 'resetMonthReconciliation'])->name('finance.reconciliation.reset-month');
            Route::get('finance/reconciliation/pending-candidates', [CashbookController::class, 'pendingReconciliationCandidates'])->name('finance.reconciliation.pending-candidates');
            Route::get('finance/reconciliation/{statementRef?}', [CashbookController::class, 'companyFinanceReconciliation'])->name('finance.reconciliation');
            Route::post('finance/reconciliation/{statementRef}/match', [CashbookController::class, 'matchStatementReconciliation'])->name('finance.reconciliation.match');
            Route::post('finance/reconciliation/{statementRef}/match-journal', [CashbookController::class, 'matchStatementJournalReconciliation'])->name('finance.reconciliation.match-journal');
            Route::get('finance/journal', [CashbookController::class, 'companyFinanceJournal'])->name('finance.journal');
            Route::get('finance/income-expense', [CashbookController::class, 'companyIncomeExpense'])->name('finance.income-expense');
            Route::post('finance/income-expense', [CashbookController::class, 'storeCompanyIncomeExpense'])->name('finance.income-expense.store');
            Route::get('finance/income-expense/{entry:public_uuid}', [CashbookController::class, 'showCompanyIncomeExpense'])->name('finance.income-expense.show');
            Route::patch('finance/income-expense/{entry:public_uuid}', [CashbookController::class, 'updateCompanyIncomeExpense'])->name('finance.income-expense.update');
            Route::delete('finance/income-expense/{entry:public_uuid}', [CashbookController::class, 'destroyCompanyIncomeExpense'])->name('finance.income-expense.destroy');
            Route::get('finance/direct-sales', [CashbookController::class, 'directCompanySales'])->name('finance.direct-sales');
            Route::post('finance/direct-sales', [CashbookController::class, 'storeDirectCompanySale'])->name('finance.direct-sales.store');
            Route::get('finance/direct-sales/{directCompanySale:public_uuid}', [CashbookController::class, 'showDirectCompanySale'])->name('finance.direct-sales.show');
            Route::get('finance/direct-sales/{directCompanySale:public_uuid}/bill', [CashbookController::class, 'directCompanySaleBill'])->name('finance.direct-sales.bill');
            Route::get('finance/gl-bills', [AdminCashbookReportsController::class, 'glBills'])->name('finance.gl-bills');
            Route::get('finance/purchase/product-filters', [PurchaseProductFilterController::class, 'index'])->name('finance.purchase.product-filters.index');
            Route::get('finance/purchase/product-filters/create', [PurchaseProductFilterController::class, 'create'])->name('finance.purchase.product-filters.create');
            Route::post('finance/purchase/product-filters', [PurchaseProductFilterController::class, 'store'])->name('finance.purchase.product-filters.store');
            Route::get('finance/purchase/product-filters/{productFilter:uuid}/edit', [PurchaseProductFilterController::class, 'edit'])->name('finance.purchase.product-filters.edit');
            Route::put('finance/purchase/product-filters/{productFilter:uuid}', [PurchaseProductFilterController::class, 'update'])->name('finance.purchase.product-filters.update');
            Route::delete('finance/purchase/product-filters/{productFilter:uuid}', [PurchaseProductFilterController::class, 'destroy'])->name('finance.purchase.product-filters.destroy');
            Route::get('finance/purchase/product-allotments', [AdminProductPurchaserAllotmentController::class, 'index'])->name('finance.purchase.product-allotments.index');
            Route::post('finance/purchase/product-allotments', [AdminProductPurchaserAllotmentController::class, 'store'])->name('finance.purchase.product-allotments.store');
            Route::get('finance/purchase/product-allotments/{product}/history', [AdminProductPurchaserAllotmentController::class, 'history'])->name('finance.purchase.product-allotments.history');
            Route::get('finance/purchase', [CashbookController::class, 'companyFinancePurchaseDashboard'])->name('finance.purchase');
            Route::get('finance/purchase/monthly-summary', [PurchaserMonthlySummaryController::class, 'index'])->name('finance.purchase.monthly-summary.index');
            Route::get('finance/purchase/monthly-summary/{purchaser:public_uuid}', [PurchaserMonthlySummaryController::class, 'show'])->name('finance.purchase.monthly-summary.show');
            Route::get('finance/purchase/purchasers', [CashbookController::class, 'companyFinancePurchaseSection'])->defaults('section', 'purchasers')->name('finance.purchase.purchasers');
            Route::get('finance/purchase/purchasers/{purchaser:public_uuid}', [CashbookController::class, 'companyFinancePurchasePurchaser'])->name('finance.purchase.purchasers.show');
            Route::get('finance/purchase/purchasers/{purchaser:public_uuid}/vendors/{supplier:public_uuid}', [CashbookController::class, 'companyFinancePurchasePurchaserVendorDetail'])->withoutScopedBindings()->name('finance.purchase.purchasers.vendors.show');
            Route::get('finance/purchase/vendors', [CashbookController::class, 'companyFinancePurchaseSection'])->defaults('section', 'vendors')->name('finance.purchase.vendors');
            Route::get('finance/purchase/vendors/{supplier:public_uuid}', [CashbookController::class, 'companyFinancePurchaseVendor'])->name('finance.purchase.vendors.show');
            Route::get('finance/purchase/categories', [CashbookController::class, 'companyFinancePurchaseSection'])->defaults('section', 'categories')->name('finance.purchase.categories');
            Route::get('finance/purchase/categories/{category}', [CashbookController::class, 'companyFinancePurchaseCategory'])->name('finance.purchase.categories.show');
            Route::get('finance/purchase/invoices', [CashbookController::class, 'companyFinancePurchaseSection'])->defaults('section', 'invoices')->name('finance.purchase.invoices');
            Route::get('finance/purchase/reports', [CashbookController::class, 'companyFinancePurchaseReports'])->name('finance.purchase.reports');
            Route::get('finance/purchase/purchaser-expenses', [CashbookController::class, 'companyFinancePurchaserExpenseReport'])->name('finance.purchase.purchaser-expenses');
            Route::get('finance/purchase/purchaser-expenses/export/pdf', [CashbookController::class, 'companyFinancePurchaserExpenseReportPdf'])->name('finance.purchase.purchaser-expenses.export.pdf');
            Route::get('finance/purchase/purchaser-expenses/export/csv', [CashbookController::class, 'companyFinancePurchaserExpenseReportCsv'])->name('finance.purchase.purchaser-expenses.export.csv');
            Route::get('finance/purchase/purchaser-expenses/export/excel', [CashbookController::class, 'companyFinancePurchaserExpenseReportExcel'])->name('finance.purchase.purchaser-expenses.export.excel');
            Route::get('finance/purchase/reports/daily', [CashbookController::class, 'companyFinancePurchaseDailyReport'])->name('finance.purchase.reports.daily');
            Route::get('finance/purchase/reports/credit-purchases', [CashbookController::class, 'companyFinancePurchaseCreditReport'])->name('finance.purchase.reports.credit-purchases');
            Route::get('finance/purchase/reports/purchasers', [CashbookController::class, 'companyFinancePurchasePurchaserReport'])->name('finance.purchase.reports.purchasers');
            Route::get('finance/purchase/reports/prices', [CashbookController::class, 'companyFinancePurchasePriceReport'])->name('finance.purchase.reports.prices');
            Route::get('finance/purchase/reports/prices/export/pdf', [CashbookController::class, 'companyFinancePurchasePriceReportPdf'])->name('finance.purchase.reports.prices.export.pdf');
            Route::get('finance/purchase/reports/prices/{product}', [CashbookController::class, 'companyFinancePurchasePriceProduct'])->name('finance.purchase.reports.prices.product');
            Route::get('finance/purchase/reports/changed-items', [CashbookController::class, 'companyFinancePurchaseChangedItems'])->name('finance.purchase.reports.changed-items');
            Route::get('finance/purchase/reports/changed-items/whatsapp', [CashbookController::class, 'companyFinancePurchaseChangedItemsWhatsApp'])->name('finance.purchase.reports.changed-items.whatsapp');
            Route::get('finance/purchase/reports/purchaser-prices', [CashbookController::class, 'companyFinancePurchaserPriceReport'])->name('finance.purchase.reports.purchaser-prices');
            Route::get('finance/purchase/reports/purchaser-prices/whatsapp', [CashbookController::class, 'companyFinancePurchaserPriceWhatsApp'])->name('finance.purchase.reports.purchaser-prices.whatsapp');
            Route::get('finance/purchase/report', [CashbookController::class, 'companyFinancePurchaseReport'])->name('finance.purchase.report');
            Route::get('finance/journal-entry/{journalEntry}', [CashbookController::class, 'companyFinanceJournalEntryShow'])->name('finance.journal.entry-show');
            Route::post('finance/journal-entry/{journalEntry}', [CashbookController::class, 'companyFinanceJournalEntryUpdate'])->name('finance.journal.entry-update');
            Route::put('finance/journal-entry/{journalEntry}', [CashbookController::class, 'companyFinanceJournalEntryUpdate']);
            Route::get('finance/journal-ref/{paymentRef}', [CashbookController::class, 'companyFinanceJournalShowSecure'])->name('finance.journal.secure-show');
            Route::get('finance/journal/{paymentRequest}', [CashbookController::class, 'companyFinanceJournalShow'])->name('finance.journal.show');
            Route::get('finance/purchasers', [CashbookController::class, 'companyFinancePurchasers'])->name('finance.purchasers');
            Route::get('finance/purchasers/{purchaser:public_uuid}/details', [CashbookController::class, 'companyFinancePurchaserDetails'])->name('finance.purchasers.details');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding', [CashbookController::class, 'storePurchaserFunding'])->name('finance.purchasers.funding.store');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/update', [CashbookController::class, 'updatePurchaserFunding'])->name('finance.purchasers.funding.update');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/delete', [CashbookController::class, 'deletePurchaserFunding'])->name('finance.purchasers.funding.delete');
            Route::delete('finance/purchasers/{purchaser:public_uuid}/funding/{credit}', [CashbookController::class, 'deletePurchaserFunding']);
            Route::get('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/candidates', [CashbookController::class, 'purchaserFundingCandidates'])->name('finance.purchasers.funding.candidates');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/match-statement', [CashbookController::class, 'matchStatementPurchaserFunding'])->name('finance.purchasers.funding.match-statement');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/replace-match', [CashbookController::class, 'replaceMatchPurchaserFunding'])->name('finance.purchasers.funding.replace-match');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/match-manual', [CashbookController::class, 'matchManualPurchaserFunding'])->name('finance.purchasers.funding.match-manual');
            Route::get('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/trace', [CashbookController::class, 'tracePurchaserFunding'])->name('finance.purchasers.funding.trace');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/unmatch', [CashbookController::class, 'unmatchPurchaserFunding'])->name('finance.purchasers.funding.unmatch');
            Route::post('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/correct', [CashbookController::class, 'correctPurchaserFunding'])->name('finance.purchasers.funding.correct');
            Route::get('finance/purchasers/{purchaser:public_uuid}/funding/{credit}/reversal-preview', [CashbookController::class, 'fundingReversalPreview'])->name('finance.purchasers.funding.reversal-preview');

            // Direct aliases under /finance/purchase/purchasers/
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/update', [CashbookController::class, 'updatePurchaserFunding']);
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/delete', [CashbookController::class, 'deletePurchaserFunding']);
            Route::delete('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}', [CashbookController::class, 'deletePurchaserFunding']);
            Route::get('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/reversal-preview', [CashbookController::class, 'fundingReversalPreview']);
            Route::get('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/candidates', [CashbookController::class, 'purchaserFundingCandidates']);
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/match-statement', [CashbookController::class, 'matchStatementPurchaserFunding']);
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/replace-match', [CashbookController::class, 'replaceMatchPurchaserFunding']);
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/match-manual', [CashbookController::class, 'matchManualPurchaserFunding']);
            Route::get('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/trace', [CashbookController::class, 'tracePurchaserFunding']);
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/unmatch', [CashbookController::class, 'unmatchPurchaserFunding']);
            Route::post('finance/purchase/purchasers/{purchaser:public_uuid}/funding/{credit}/correct', [CashbookController::class, 'correctPurchaserFunding']);
            Route::get('finance/vendor-credit', [CashbookController::class, 'companyFinanceVendorCredit'])->name('finance.vendor-credit');
            Route::get('finance/vendor-credit/settlements', [CashbookController::class, 'companyFinanceVendorSettlementHistory'])->name('finance.vendor-credit.settlements');
            Route::get('finance/vendor-credit/settlements/{vendorSettlement:public_uuid}', [CashbookController::class, 'companyFinanceVendorSettlementDetails'])->whereUuid('vendorSettlement')->name('finance.vendor-credit.settlements.show');
            Route::post('finance/vendor-credit/settlements/{vendorSettlement:public_uuid}/reconcile', [CashbookController::class, 'reconcileVendorSettlement'])->whereUuid('vendorSettlement')->name('finance.vendor-credit.settlements.reconcile');
            Route::post('finance/vendor-credit/settlements/{vendorSettlement:public_uuid}/update', [CashbookController::class, 'updateVendorSettlement'])->whereUuid('vendorSettlement')->name('finance.vendor-credit.settlements.update');
            Route::post('finance/vendor-credit/settlements/{vendorSettlement:public_uuid}/delete', [CashbookController::class, 'deleteVendorSettlement'])->whereUuid('vendorSettlement')->name('finance.vendor-credit.settlements.delete');
            Route::get('finance/vendor-credit/settlements/{vendorSettlement:public_uuid}/reversal-preview', [CashbookController::class, 'vendorSettlementReversalPreview'])->whereUuid('vendorSettlement')->name('finance.vendor-credit.settlements.reversal-preview');
            Route::get('finance/vendor-credit/{supplier:public_uuid}', [CashbookController::class, 'companyFinanceVendorCreditShow'])->whereUuid('supplier')->name('finance.vendor-credit.show');
            Route::post('finance/vendor-credit/{invoice:public_uuid}/pay', [CashbookController::class, 'settleVendorCreditInvoice'])->whereUuid('invoice')->name('finance.vendor-credit.pay');
            Route::post('finance/vendor-credit/{supplier:public_uuid}/settle', [CashbookController::class, 'settleVendorCredit'])->whereUuid('supplier')->name('finance.vendor-credit.settle');
            Route::post('finance/statement-entries', [CashbookController::class, 'storeCompanyStatementEntry'])->name('finance.statement-entries.store');
            Route::post('finance/payments/{paymentRequest}/reconcile', [CashbookController::class, 'reconcileCompanyPayment'])->name('finance.payments.reconcile');
            Route::get('accept-payment', [CashbookController::class, 'acceptPaymentPage'])->name('accept-payment');
            Route::get('income-expenses', [CashbookController::class, 'incomeExpenses'])->name('income-expenses');
            Route::get('post-entry', [CashbookController::class, 'postEntryPage'])->name('post-entry');
            Route::get('post-entry/{shop}', [CashbookController::class, 'postEntryPageForShop'])->name('post-entry.shop');
            Route::get('shops/{shop}', [CashbookController::class, 'salesReport'])->name('shop.show');
            Route::get('shops/{shop}/sales-report', [CashbookController::class, 'salesReport'])->name('shop.sales-report');
            Route::get('shops/{shop}/overview', [CashbookController::class, 'showShop'])->name('shop.overview');
            Route::get('shops/{shop}/sales-report/pdf', [CashbookController::class, 'exportSalesReportPdf'])->name('shop.sales-report.pdf');
            Route::get('shops/{shop}/sales-report/excel', [CashbookController::class, 'exportSalesReportExcel'])->name('shop.sales-report.excel');
            Route::get('shops/{shop}/sales-report/csv', [CashbookController::class, 'exportSalesReportCsv'])->name('shop.sales-report.csv');
            Route::post('shops/{shop}/recalculate-month', [CashbookController::class, 'recalculateMonth'])->name('shop.recalculate-month');
            Route::get('shops/{shop}/settlement-details', [CashbookController::class, 'showSettlementDetails'])->name('shop.settlement-details');
            Route::get('shops/{shop}/purchases/vendors', [CashbookController::class, 'shopVendorPurchasesReport'])->name('shop.purchases.vendors');
            Route::prefix('shops/{shop}/history')->name('shop.history.')->group(function () {
                Route::get('payments', [CashbookController::class, 'shopPaymentsHistory'])->name('payments');
                Route::get('allocations', [CashbookController::class, 'shopAllocationsHistory'])->name('allocations');
                Route::get('cheques', [CashbookController::class, 'shopChequesHistory'])->name('cheques');
                Route::get('petty', [CashbookController::class, 'shopPettyHistory'])->name('petty');
                Route::get('adjustments', [CashbookController::class, 'shopAdjustmentsHistory'])->name('adjustments');
                Route::get('banking', [CashbookController::class, 'shopBankingHistory'])->name('banking');
            });
            Route::post('shops/{shop}/day/accept-selected', [CashbookController::class, 'acceptSelectedDayEntries'])->name('shop.day.accept-selected');
            Route::post('shops/{shop}/day/verify-selected', [CashbookController::class, 'verifySelectedDayEntries'])->name('shop.day.verify-selected');
            Route::post('shops/{shop}/day/adjustments', [CashbookController::class, 'storeDayAdjustment'])->name('shop.day.adjustments.store');
            Route::post('shops/{shop}/day/adjustments/reverse', [CashbookController::class, 'reverseDayAdjustment'])->name('shop.day.adjustments.reverse');
            Route::get('shops/{shop}/export', [CashbookController::class, 'exportShopData'])->name('shop.export');
            Route::get('shops/{shop}/settlement', [CashbookController::class, 'redirectShopSettlement'])->name('shop.settlement');
            Route::get('shops/{shop}/accept-payment', [CashbookController::class, 'shopSettlementPage'])->name('shop.accept-payment');
            Route::post('shops/{shop}/accept-payment', [CashbookController::class, 'recordShopPayment'])->name('shop.accept-payment.store');
            Route::post('shops/{shop}/accept-payment/reconcile', [CashbookController::class, 'reconcileShopPaymentLedger'])->name('shop.accept-payment.reconcile');
            Route::post('shops/{shop}/receive-payment', [CashbookController::class, 'receiveShopPayment'])->name('shop.receive-payment');
            Route::post('shops/{shop}/allocate-payment', [CashbookController::class, 'allocateShopPayment'])->name('shop.allocate-payment');
            Route::post('shops/{shop}/allocate-payment/clear', [CashbookController::class, 'clearShopPaymentAllocations'])->name('shop.allocate-payment.clear');
            Route::delete('shops/{shop}/payments', [CashbookController::class, 'destroyShopPayment'])->name('shop.payments.destroy');
            Route::post('shops/{shop}/allocate-payment/clear-reallocate', [CashbookController::class, 'clearAndReallocateShopPayment'])->name('shop.allocate-payment.clear-reallocate');
            Route::post('shops/{shop}/allocate-payments/bulk', [CashbookController::class, 'allocateAllShopPayments'])->name('shop.allocate-payments.bulk');
            Route::post('shops/{shop}/allocate-payments/clear-all', [CashbookController::class, 'clearAllShopPaymentAllocationsWeb'])->name('shop.allocate-payments.clear-all');
            Route::post('shops/{shop}/allocations/{allocation}/remove', [CashbookController::class, 'removeShopAllocation'])->name('shop.allocations.remove');
            Route::post('shops/{shop}/company-payments', [CashbookController::class, 'storeCompanyPayment'])->name('shop.company-payments.store');
            Route::post('shops/{shop}/allocations', [CashbookController::class, 'storeCompanyExpenseAllocation'])->name('shop.allocations.store');
            Route::post('shops/{shop}/allocations/{allocation}/reverse', [CashbookController::class, 'reverseCompanyExpenseAllocation'])->name('shop.allocations.reverse');
            Route::post('shops/{shop}/petty/fund', [CashbookController::class, 'fundShopPetty'])->name('shop.petty.fund');
            Route::get('reports/expense-audit', [CashbookController::class, 'expenseAuditReport'])->name('reports.expense-audit');
            Route::get('shops/{shop}/post-entry', [CashbookController::class, 'postEntryPageForShop'])->name('shop.post-entry');
            Route::get('rules-config', [CashbookController::class, 'rulesPage'])->name('rules-config');
            Route::get('settings', [CashbookController::class, 'settingsPage'])->name('settings');
            Route::post('settings/staff', [CashbookController::class, 'updateStaffSettings'])->name('settings.staff');
            Route::post('settings/vendor-purchase-edit-window', [CashbookController::class, 'updateVendorPurchaseEditWindow'])->name('settings.vendor-purchase-edit-window');
            Route::get('settings/shops/{shop}', [CashbookController::class, 'shopSettingsPage'])->name('settings.shop');
            Route::post('settings/shops/{shop}/toggle-purchasing', [CashbookController::class, 'toggleShopPurchasing'])->name('settings.shop.toggle-purchasing');
            Route::get('settings/shops/{shop}/vendors', [CashbookVendorController::class, 'index'])->name('settings.shop.vendors.index');
            Route::post('settings/shops/{shop}/vendors/routing', [CashbookVendorController::class, 'updateRouting'])->name('settings.shop.vendors.update-routing');
            Route::post('settings/shops/{shop}/vendors/purchase-settings', [CashbookVendorController::class, 'updatePurchaseSettings'])->name('settings.shop.vendors.update-purchase-settings');
            Route::post('settings/shops/{shop}/vendors/toggle-creation-permission', [CashbookVendorController::class, 'toggleCreationPermission'])->name('settings.shop.vendors.toggle-creation-permission');
            Route::post('settings/shops/{shop}/vendors/link', [CashbookVendorController::class, 'link'])->name('settings.shop.vendors.link');
            Route::post('settings/shops/{shop}/vendors/create', [CashbookVendorController::class, 'storeNew'])->name('settings.shop.vendors.create');
            Route::put('settings/shops/{shop}/vendors/{supplier}', [CashbookVendorController::class, 'update'])->name('settings.shop.vendors.update');
            Route::post('settings/shops/{shop}/vendors/{supplier}/toggle-status', [CashbookVendorController::class, 'toggleStatus'])->name('settings.shop.vendors.toggle-status');
            Route::delete('settings/shops/{shop}/vendors/{supplier}/unlink', [CashbookVendorController::class, 'unlink'])->name('settings.shop.vendors.unlink');
            Route::get('settings/shops/{shop}/vendors/search-global', [CashbookVendorController::class, 'searchGlobalSuppliers'])->name('settings.shop.vendors.search-global');
            Route::get('settings/shops/{shop}/settlements', [CashbookSettlementController::class, 'index'])->name('settings.shop.settlements.index');
            Route::post('settings/shops/{shop}/settlements/reorder', [CashbookSettlementController::class, 'reorder'])->name('settings.shop.settlements.reorder');
            Route::get('settings/shops/{shop}/settlements/create', [CashbookSettlementController::class, 'create'])->name('settings.shop.settlements.create');
            Route::post('settings/shops/{shop}/settlements', [CashbookSettlementController::class, 'store'])->name('settings.shop.settlements.store');
            Route::get('settings/shops/{shop}/settlements/{settlement}/edit', [CashbookSettlementController::class, 'edit'])->name('settings.shop.settlements.edit');
            Route::put('settings/shops/{shop}/settlements/{settlement}', [CashbookSettlementController::class, 'update'])->name('settings.shop.settlements.update');
            Route::delete('settings/shops/{shop}/settlements/{settlement}', [CashbookSettlementController::class, 'destroy'])->name('settings.shop.settlements.destroy');
            Route::post('settings/shops/{shop}/settlements/{settlement}/copy', [CashbookSettlementController::class, 'copy'])->name('settings.shop.settlements.copy');
            Route::post('settings/shops/{shop}/settlements/{settlement}/set-net-balance', [CashbookSettlementController::class, 'setNetBalance'])->name('settings.shop.settlements.set-net-balance');
            Route::post('settings/shops/{shop}/settlements/{settlement}/set-payment-payable', [CashbookSettlementController::class, 'setDefaultPaymentPayable'])->name('settings.shop.settlements.set-payment-payable');
            Route::post('settings/shops/{shop}/settlements/{settlement}/set-payment-paid', [CashbookSettlementController::class, 'setDefaultPaymentPaid'])->name('settings.shop.settlements.set-payment-paid');
            Route::get('settings/shops/{shop}/payments', [CashbookSettlementController::class, 'paymentsIndex'])->name('settings.shop.payments.index');
            Route::post('settings/shops/{shop}/payments-configuration', [CashbookSettlementController::class, 'savePaymentsConfiguration'])->name('settings.shop.payments-configuration.save');
            Route::post('settings/shops/{shop}/payments/company-collections', [CashbookSettlementController::class, 'saveCompanyCollections'])->name('settings.shop.payments.company-collections.save');
            Route::post('settings/shops/{shop}/payments/shop-to-company', [CashbookSettlementController::class, 'saveShopToCompany'])->name('settings.shop.payments.shop-to-company.save');
            Route::post('settings/shops/{shop}/payments/petty', [CashbookSettlementController::class, 'savePetty'])->name('settings.shop.payments.petty.save');
            Route::post('settings/shops/{shop}/payments/settlement', [CashbookSettlementController::class, 'saveSettlement'])->name('settings.shop.payments.settlement.save');
            Route::post('settings/shops/{shop}/payments/allocation', [CashbookSettlementController::class, 'saveAllocation'])->name('settings.shop.payments.allocation.save');
            Route::post('settings/shops/{shop}/payments/report-headings', [CashbookSettlementController::class, 'saveReportHeadings'])->name('settings.shop.payments.report-headings.save');
            Route::post('settings/shops/{shop}/payments/advanced', [CashbookSettlementController::class, 'saveAdvanced'])->name('settings.shop.payments.advanced.save');
            Route::get('settings/salary', [CashbookSalaryController::class, 'rootIndex'])->name('settings.salary');
            Route::get('settings/shops/{shop}/salary', [CashbookSalaryController::class, 'index'])->name('settings.shop.salary.index');
            Route::post('settings/shops/{shop}/salary', [CashbookSalaryController::class, 'update'])->name('settings.shop.salary.update');
            Route::post('settings/shops/{shop}/salary/reset', [CashbookSalaryController::class, 'resetToDefault'])->name('settings.shop.salary.reset');
            Route::get('settings/shops/{shop}/demo', [CashbookController::class, 'shopDemoPage'])->name('settings.shop.demo');
            Route::get('settings/shops/{shop}/demo/real-data', [CashbookController::class, 'shopDemoRealData'])->name('settings.shop.demo.real-data');
            Route::get('settings/presets', [CashbookController::class, 'presetsPage'])->name('settings.presets');
            Route::get('settings/collections', [CashbookController::class, 'collectionGroupsPage'])->name('settings.collections');
            Route::get('bank-accounts/create', [CashbookController::class, 'createBankAccountPage'])->name('bank-accounts.create');
            Route::post('bank-accounts', [CashbookController::class, 'storeBankAccount'])->name('bank-accounts.store');
            Route::get('bank-accounts/{account}', [CashbookController::class, 'showBankAccount'])->name('bank-accounts.show');
            Route::get('bank-accounts/{account}/statement', [CashbookController::class, 'showBankAccountStatement'])->name('bank-accounts.statement');
            Route::post('bank-accounts/{account}/statement/import', [CashbookController::class, 'importBankAccountStatement'])->name('bank-accounts.statement.import');
            Route::post('bank-accounts/{account}/statement/{statementRef}/verify', [CashbookController::class, 'verifyPendingStatement'])->name('bank-accounts.statement.verify');
            Route::patch('bank-accounts/{account}/statement/duplicates/{statementRef}/clear', [CashbookController::class, 'clearStatementDuplicateFlag'])->name('bank-accounts.statement.duplicates.clear');
            Route::put('bank-accounts/{account}', [CashbookController::class, 'updateBankAccount'])->name('bank-accounts.update');
            Route::delete('bank-accounts/{account}', [CashbookController::class, 'deleteBankAccount'])->name('bank-accounts.delete');

            // ── JSON API routes (rate limited & throttled) ─────────────────────
            Route::prefix('api')->middleware('throttle:60,1')->name('api.')->group(function () {
                Route::get('shop-data', [CashbookController::class, 'getShopData'])->name('shop-data');
                Route::get('shop-settlement-summary', [CashbookController::class, 'getShopSettlementSummary'])->name('shop-settlement-summary');
                Route::get('all-shops-overview', [CashbookController::class, 'getAllShopsOverview'])->name('all-shops-overview');
                Route::get('payables-pendings', [CashbookController::class, 'getPayablesAndPendings'])->name('payables-pendings');
                Route::get('rules', [CashbookController::class, 'getRules'])->name('rules');
                Route::get('company-accounts', [CashbookController::class, 'getCompanyAccounts'])->name('company-accounts');
                Route::get('client-summary', [CashbookController::class, 'getClientSummary'])->name('client-summary');
                Route::get('report-bills', [CashbookController::class, 'getReportBills'])->name('report-bills');
                Route::get('presets', [CashbookController::class, 'getPresets'])->name('presets');

                Route::post('record-entry', [CashbookController::class, 'recordEntry'])->name('record-entry');
                Route::post('bulk-record-entries', [CashbookController::class, 'bulkRecordEntries'])->name('bulk-record-entries');
                Route::post('update-entry', [CashbookController::class, 'updateEntry'])->name('update-entry');
                Route::post('delete-entry', [CashbookController::class, 'deleteEntry'])->name('delete-entry');
                Route::post('void-entry', [CashbookController::class, 'voidEntry'])->name('void-entry');
                Route::post('approve-entry', [CashbookController::class, 'approveEntry'])->name('approve-entry');
                Route::post('approve-day', [CashbookController::class, 'approveDay'])->name('approve-day');
                Route::post('accept-payment', [CashbookController::class, 'acceptPayment'])->name('accept-payment');
                Route::post('pay-shop', [CashbookController::class, 'payShop'])->name('pay-shop');
                Route::post('add-shop', [CashbookController::class, 'addShop'])->name('add-shop');
                Route::post('update-rule', [CashbookController::class, 'updateRule'])->name('update-rule');
                Route::post('create-rule-config', [CashbookController::class, 'createRuleConfig'])->name('create-rule-config');
                Route::post('toggle-day', [CashbookController::class, 'toggleDay'])->name('toggle-day');
                Route::post('presets/create', [CashbookController::class, 'createPreset'])->name('presets.create');
                Route::post('presets/delete', [CashbookController::class, 'deletePreset'])->name('presets.delete');
                Route::post('presets/create-entry-rule', [CashbookController::class, 'createEntryRule'])->name('presets.create-entry-rule');
                Route::post('presets/collection-group', [CashbookController::class, 'saveCollectionGroup'])->name('presets.collection-group');
                Route::post('presets/update-setting', [CashbookController::class, 'updatePresetSetting'])->name('presets.update-setting');
                Route::post('shop-settings/update', [CashbookController::class, 'updateShopSetting'])->name('shop-settings.update');
                Route::post('shop-settings/toggle-status', [CashbookController::class, 'toggleShopSettingStatus'])->name('shop-settings.toggle-status');
                Route::post('shop-settings/custom-row', [CashbookController::class, 'createShopCustomRow'])->name('shop-settings.custom-row');
                Route::post('shop-settings/headers/create', [CashbookController::class, 'createShopHeaderGroup'])->name('shop-settings.headers.create');
                Route::post('shop-settings/headers/update', [CashbookController::class, 'updateShopHeaderGroup'])->name('shop-settings.headers.update');
                Route::post('shop-settings/headers/delete', [CashbookController::class, 'deleteShopHeaderGroup'])->name('shop-settings.headers.delete');
                Route::post('shop-settings/headers/reorder', [CashbookController::class, 'reorderShopHeaderGroups'])->name('shop-settings.headers.reorder');
                Route::get('products/search', [CashbookController::class, 'searchProducts'])->name('products.search');
                Route::post('shop-settings/assign-header', [CashbookController::class, 'assignSettingToHeaderGroup'])->name('shop-settings.assign-header');
                Route::post('shop-settings/cards/reorder', [CashbookController::class, 'reorderShopHeaderCards'])->name('shop-settings.cards.reorder');
                Route::post('shop-settings/relations/create', [CashbookController::class, 'createShopRelation'])->name('shop-settings.relations.create');
                Route::post('shop-settings/relations/add-item', [CashbookController::class, 'addShopRelationItem'])->name('shop-settings.relations.add-item');
                Route::post('shop-settings/relations/update-item-role', [CashbookController::class, 'updateShopRelationItemRole'])->name('shop-settings.relations.update-item-role');
                Route::post('shop-settings/relations/delete-item', [CashbookController::class, 'deleteShopRelationItem'])->name('shop-settings.relations.delete-item');
                Route::post('shop-settings/relations/delete', [CashbookController::class, 'deleteShopRelation'])->name('shop-settings.relations.delete');
                Route::post('shop-settings/relations/update-settings', [CashbookController::class, 'updateShopRelationSettings'])->name('shop-settings.relations.update-settings');
                Route::post('shop-settings/bank-adjustment-rules', [CashbookController::class, 'saveShopBankAdjustmentRule'])->name('shop-settings.bank-adjustment-rules.save');
                Route::delete('shop-settings/bank-adjustment-rules/{rule}', [CashbookController::class, 'deleteShopBankAdjustmentRule'])->name('shop-settings.bank-adjustment-rules.delete');
                Route::post('shops/{shop}/bank-settlement-adjustments', [CashbookController::class, 'saveShopDailyBankAdjustments'])->name('shops.bank-settlement-adjustments.save');
                Route::post('historical-bank-collections/preview', [CashbookController::class, 'previewHistoricalBankCollections'])->name('historical-bank-collections.preview');
                Route::post('historical-bank-collections/fetch', [CashbookController::class, 'fetchHistoricalBankCollections'])->name('historical-bank-collections.fetch');
                Route::post('assign-preset', [CashbookController::class, 'assignShopPreset'])->name('assign-preset');
            });
        });
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
            Route::patch('main-account/categories/{category}', [AdminAccountingController::class, 'updateMainAccountCategoryStatus'])->name('main-account.categories.update');
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
            Route::post('purchasers/{user:public_uuid}/login-as', [AdminAccountingController::class, 'loginAsPurchaser'])->name('purchasers.login-as');
            Route::post('purchasers/{user:public_uuid}/buy', [AdminAccountingController::class, 'buyAsPurchaser'])->name('purchasers.buy');
        });
        Route::post('users/{user:public_uuid}/approve', [UserController::class, 'approve'])
            ->whereUuid('user')
            ->name('users.approve');
        Route::get('user-access', [UserAccessController::class, 'index'])->name('user-access.index');
        Route::post('user-access/{user:public_uuid}', [UserAccessController::class, 'store'])->name('user-access.store');
        Route::resource('users', UserController::class)
            ->scoped(['user' => 'public_uuid'])
            ->where(['user' => '[0-9a-fA-F-]{36}'])
            ->middleware('can:admin.user.view');
        Route::post('warehouses/allocate-product', [WarehouseController::class, 'allocateProduct'])->name('warehouses.allocate-product');
        Route::post('warehouses/bulk-allocate', [WarehouseController::class, 'bulkAllocate'])->name('warehouses.bulk-allocate');
        Route::post('warehouses/assign-recommended', [WarehouseController::class, 'assignRecommended'])->name('warehouses.assign-recommended');
        Route::resource('warehouses', WarehouseController::class)->middleware('can:inventory.stock.adjust');
        Route::middleware('can:hr.employee.view')->group(function () {
            Route::get('staff', [StaffManagementController::class, 'index'])->name('staff.index');
            Route::get('staff/approvals', [StaffManagementController::class, 'approvalsIndex'])->name('staff.approvals.index');
            Route::get('staff/approvals/{employee:employee_code}', [StaffManagementController::class, 'approvalShow'])->name('staff.approvals.show');
            Route::get('staff/employees', [StaffManagementController::class, 'employeesIndex'])->name('staff.employees.index');
            Route::get('staff/create', fn () => redirect()->route('admin.staff.employees.index'))->name('staff.create');
            Route::get('staff/assignments', [StaffManagementController::class, 'assignmentsIndex'])->name('staff.assignments.index');
            Route::get('staff/assignments/{employee:employee_code}', [StaffManagementController::class, 'assignmentShow'])->name('staff.assignments.show');
            Route::post('staff', [StaffManagementController::class, 'store'])->name('staff.store');
            Route::post('staff/shop-assignments', [StaffManagementController::class, 'storeShopEmployeeAssignment'])->name('staff.shop-assignments.store');
            Route::put('staff/{employee:employee_code}', [StaffManagementController::class, 'update'])->name('staff.update');
            Route::post('staff/{employee:employee_code}/approve', [StaffManagementController::class, 'approveEmployee'])->name('staff.approve');
            Route::post('staff/{employee:employee_code}/reject', [StaffManagementController::class, 'rejectEmployee'])->name('staff.reject');
            Route::delete('staff/{employee:employee_code}/duplicate', [StaffManagementController::class, 'destroyDuplicateEmployee'])->name('staff.duplicate.destroy');
            Route::patch('staff/{employee:employee_code}/employment-status', [StaffManagementController::class, 'updateEmploymentStatus'])->name('staff.employment-status.update');
            Route::get('staff/categories', [StaffManagementController::class, 'categoriesIndex'])->name('staff.categories.index');
            Route::patch('staff/settings/check-in-time', [StaffManagementController::class, 'updateCheckInTime'])->name('staff.settings.check-in-time.update');
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
            Route::post('staff/{employee:employee_code}/payments', [StaffManagementController::class, 'storeEmployeePayment'])->name('staff.employee-payments.store');
            Route::put('staff/payments/{payment}', [StaffManagementController::class, 'updateShopStaffPayment'])->name('staff.shop-staff-payments.update');
            Route::delete('staff/payments/{payment}', [StaffManagementController::class, 'destroyShopStaffPayment'])->name('staff.shop-staff-payments.destroy');
            Route::post('staff/contract-worker-payments', [StaffManagementController::class, 'storeContractWorkerPayment'])->name('staff.contract-worker-payments.store');
            Route::patch('staff/advance-requests/{advanceRequest}', [StaffManagementController::class, 'reviewEmployeeAdvance'])->name('staff.advance-requests.review');
            Route::put('staff/advance-requests/{advanceRequest}', [StaffManagementController::class, 'updateEmployeeAdvance'])->name('staff.advance-requests.update');
            Route::delete('staff/advance-requests/{advanceRequest}', [StaffManagementController::class, 'destroyEmployeeAdvance'])->name('staff.advance-requests.destroy');
            Route::get('staff/payroll', [StaffManagementController::class, 'payrollIndex'])->name('staff.payroll.index');
            Route::get('staff/payroll/export/excel', [StaffManagementController::class, 'exportPayrollExcel'])->name('staff.payroll.export.excel');
            Route::get('staff/payroll/export/pdf', [StaffManagementController::class, 'exportPayrollPdf'])->name('staff.payroll.export.pdf');
            Route::post('staff/payroll', [StaffManagementController::class, 'storePayroll'])->name('staff.payroll.store');
            Route::patch('staff/payroll/{payrollRun}/items/{payrollRunItem}', [StaffManagementController::class, 'updatePayrollItem'])->name('staff.payroll.items.update');
            Route::post('staff/payroll/{payrollRun}/finalize', [StaffManagementController::class, 'finalizePayroll'])->name('staff.payroll.finalize');
            Route::get('staff/sync-flags', [StaffSyncFlagController::class, 'index'])->name('staff.sync-flags.index');
            Route::get('hr/staff-sync-flags', [StaffSyncFlagController::class, 'index'])->name('hr.staff-sync-flags');
            Route::get('hr/staff-sync-flags-list', [StaffSyncFlagController::class, 'index'])->name('hr.staff-sync-flags.index');
            Route::post('staff/sync-flags/scan', [StaffSyncFlagController::class, 'scan'])->name('staff.sync-flags.scan');
            Route::get('staff/sync-flags/{flag}/review', [StaffSyncFlagController::class, 'review'])->name('staff.sync-flags.review');
            Route::post('staff/sync-flags/{flag}/fix', [StaffSyncFlagController::class, 'fixSync'])->name('staff.sync-flags.fix');
            Route::post('staff/sync-flags/{flag}/fix-sync', [StaffSyncFlagController::class, 'fixSync'])->name('staff.sync-flags.fix-sync');
            Route::post('staff/sync-flags/{flag}/apply-fixes', [StaffSyncFlagController::class, 'applyFixes'])->name('staff.sync-flags.apply-fixes');
            Route::post('staff/sync-flags/{flag}/apply-final-value', [StaffSyncFlagController::class, 'applyFixes'])->name('staff.sync-flags.apply-final-value');
            Route::match(['put', 'post'], 'staff/sync-flags/{flag}/save-and-sync', [StaffSyncFlagController::class, 'saveAndSyncAll'])->name('staff.sync-flags.save-and-sync');
            Route::match(['put', 'post'], 'staff/sync-flags/{flag}/save-and-sync-all', [StaffSyncFlagController::class, 'saveAndSyncAll'])->name('staff.sync-flags.save-and-sync-all');
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
        Route::get('backup', [DatabaseBackupController::class, 'index'])->name('backup.index');
        Route::post('backup/email', [DatabaseBackupController::class, 'sendEmail'])->name('backup.email');
        Route::get('backup/download', [DatabaseBackupController::class, 'download'])->name('backup.download');
        Route::delete('backup/{filename}', [DatabaseBackupController::class, 'destroy'])->name('backup.delete');
    });

    // ── Sort Sheet ──────────────────────────────────────────────────────────────
    Route::prefix('sort-sheet')->name('sort-sheet.')->middleware('can:sort.sheet.view')->group(function () {
        Route::get('/', [SortSheetController::class, 'index'])->name('index');
        Route::get('/generate', [SortSheetController::class, 'generate'])->name('generate');
        Route::get('/presets', [SortSheetController::class, 'presetsIndex'])->name('presets.index');
        Route::post('/presets', [SortSheetController::class, 'storePreset'])->name('presets.store');
        Route::get('/presets/batch-print', [SortSheetController::class, 'batchPrintPresets'])->name('presets.batch-print');
        Route::post('/preset-batches', [SortSheetController::class, 'storePresetBatch'])->name('presets.batches.store');
        Route::delete('/preset-batches/{batch:uuid}', [SortSheetController::class, 'destroyPresetBatch'])->name('presets.batches.destroy');
        Route::get('/presets/{preset:uuid}/edit', [SortSheetController::class, 'editPreset'])->name('presets.edit');
        Route::put('/presets/{preset:uuid}', [SortSheetController::class, 'updatePreset'])->name('presets.update');
        Route::post('/presets/reorder', [SortSheetController::class, 'reorderPresets'])->name('presets.reorder');
        Route::post('/presets/{preset:uuid}/move-up', [SortSheetController::class, 'movePresetUp'])->name('presets.move-up');
        Route::post('/presets/{preset:uuid}/move-down', [SortSheetController::class, 'movePresetDown'])->name('presets.move-down');
        Route::delete('/presets/{preset:uuid}', [SortSheetController::class, 'destroyPreset'])->name('presets.destroy');
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
        Route::get('/portrait', [SortSheetController::class, 'portraitIndex'])->name('shop-wise-portrait');
        Route::get('/portrait/generate', [SortSheetController::class, 'portraitGenerate'])->name('shop-wise-portrait.generate');
        Route::get('/wide', [SortSheetController::class, 'wideIndex'])->name('shop-wise-wide');
        Route::get('/wide/generate', [SortSheetController::class, 'wideGenerate'])->name('shop-wise-wide.generate');
        Route::get('/grid', [SortSheetController::class, 'gridIndex'])->name('grid');
        Route::get('/grid/generate', [SortSheetController::class, 'gridGenerate'])->name('grid.generate');
        Route::get('/export/excel', [SortSheetController::class, 'exportExcel'])->name('export.excel');
        Route::get('/matrix-print', [SortSheetController::class, 'segregationMatrixPrint'])->name('matrix-print');
        Route::get('/grid-print', [SortSheetController::class, 'segregationGridPrint'])->name('grid-print');
        Route::get('/print', [SortSheetController::class, 'segregationPdf'])->name('print');
    });

    // ── Purchaser V2 (Isolated High-Performance Architecture) ──────────────
    Route::prefix('purchaser-v2')->name('purchaser-v2.')->group(function () {
        Route::get('/', [PurchaserV2DashboardController::class, 'index'])->name('index');
        Route::get('/dashboard', [PurchaserV2DashboardController::class, 'index'])->name('dashboard');
        Route::get('/daily', [PurchaserV2DailyController::class, 'index'])->name('daily');
        Route::get('/daily/products', [PurchaserV2DailyController::class, 'products'])->name('daily.products');
        Route::get('/daily/products/{product}/detail', [PurchaserV2DailyController::class, 'productDetail'])->name('daily.product-detail');
        Route::get('/products/search', [PurchaserV2SearchController::class, 'products'])->name('products.search');
        Route::get('/buy', [PurchaserV2BuyController::class, 'index'])->name('buy');
        Route::get('/buy/product-details', [PurchaserV2BuyController::class, 'productDetails'])->name('buy.product-details');
        Route::post('/cart/store', [PurchaserV2BuyController::class, 'storeCart'])->name('cart.store');
        Route::get('/cart', [PurchaserV2CartController::class, 'index'])->name('cart.index');
        Route::get('/cart/{cart}/items', [PurchaserV2CartController::class, 'items'])->name('cart.items');
        Route::patch('/cart/{cart}/items', [PurchaserV2CartController::class, 'updateItems'])->name('cart.items.update');
        Route::delete('/cart/{cart}/items/{item}', [PurchaserV2CartController::class, 'destroyItem'])->name('cart.items.destroy');
        Route::delete('/cart/{cart}', [PurchaserV2CartController::class, 'destroyCart'])->name('cart.destroy');
        Route::get('/suppliers/search', [PurchaserV2CartController::class, 'searchSuppliers'])->name('suppliers.search');
        Route::post('/suppliers', [PurchaserV2CartController::class, 'storeSupplier'])->name('suppliers.store');
        Route::patch('/cart/{cart}/supplier', [PurchaserV2CartController::class, 'updateSupplier'])->name('cart.update-supplier');
        Route::get('/cart/{cart}/price-hints', [PurchaserV2CartController::class, 'priceHints'])->name('cart.price-hints');
        Route::post('/cart/{cart}/merge-drafts', [PurchaserV2CartController::class, 'mergeDrafts'])->name('cart.merge-drafts');
        Route::get('/report', [PurchaserV2ReportController::class, 'index'])->name('report');
    });
});
