<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\ActivityLogController;
use App\Http\Controllers\Web\Admin\AdminAccountingController;
use App\Http\Controllers\Web\Admin\AdminAutoLoadAllController;
use App\Http\Controllers\Web\Admin\AdminCashbookReportsController;
use App\Http\Controllers\Web\Admin\AdminOverviewController;
use App\Http\Controllers\Web\Admin\CashbookController;
use App\Http\Controllers\Web\Admin\CompanySettingsController;
use App\Http\Controllers\Web\Admin\DailyProgressController;
use App\Http\Controllers\Web\Admin\DeliveryReviewController;
use App\Http\Controllers\Web\Admin\DiscrepancyReportController;
use App\Http\Controllers\Web\Admin\EmptyInventoryController;
use App\Http\Controllers\Web\Admin\EnquiryController;
use App\Http\Controllers\Web\Admin\FinanceV2Controller;
use App\Http\Controllers\Web\Admin\FinanceV2PaymentsController;
use App\Http\Controllers\Web\Admin\StaffManagementController;
use App\Http\Controllers\Web\Admin\UserAccessController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\WarehouseController;
use App\Http\Controllers\Api\Warehouse\ApiWarehouseLoadoutController;
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
use App\Http\Controllers\Web\Purchasing\PurchaserDashboardController;
use App\Http\Controllers\Web\Purchasing\PurchaserReportController;
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
        Route::get('/orders', [ShopOwnerController::class, 'ordersIndex'])->name('orders.index');
        Route::get('/orders/create', [ShopOwnerController::class, 'ordersCreate'])->name('orders.create');
        Route::delete('/orders/create/clear', [ShopOwnerController::class, 'clearTomorrowOrder'])->name('orders.clear');
        Route::get('/orders/history', [ShopOwnerController::class, 'ordersHistory'])->name('orders.history');
        Route::get('/orders/{order_number}', [ShopOwnerController::class, 'ordersShow'])->name('orders.show');
        Route::get('/deliveries', [ShopOwnerController::class, 'deliveriesIndex'])->name('deliveries.index');
        Route::get('/deliveries/{order_number}', [ShopOwnerController::class, 'deliveriesShow'])->name('deliveries.show');
        Route::get('/deliveries/{order_number}/pdf', [ShopOwnerController::class, 'deliveriesPdf'])->name('deliveries.pdf');
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
        Route::get('/cashbook', [ShopOwnerController::class, 'cashbookShow'])->name('cashbook.show');
        Route::get('/cashbook/create', [ShopOwnerController::class, 'cashbookCreate'])->name('cashbook.create');
        Route::get('/cashbook/settings', [ShopOwnerController::class, 'cashbookSettings'])->name('cashbook.settings');
        Route::get('/cashbook/reports', [ShopOwnerController::class, 'cashbookReports'])->name('cashbook.reports');
            Route::prefix('/cashbook/api')->name('cashbook.api.')->group(function () {
                Route::get('/shop-data', [ShopOwnerController::class, 'cashbookData'])->name('shop-data');
                Route::post('/record-entry', [ShopOwnerController::class, 'cashbookRecordEntry'])->name('record-entry');
                Route::post('/bulk-record-entries', [ShopOwnerController::class, 'cashbookBulkRecordEntries'])->name('bulk-record-entries');
                Route::post('/update-entry', [ShopOwnerController::class, 'cashbookUpdateEntry'])->name('update-entry');
                Route::post('/delete-entry', [ShopOwnerController::class, 'cashbookDeleteEntry'])->name('delete-entry');
                Route::post('/delete-collection', [ShopOwnerController::class, 'cashbookDeleteCollection'])->name('delete-collection');
            });
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
        Route::get('products/export/csv', [ProductController::class, 'exportCsv'])->name('products.export.csv');
        Route::get('products/export/pdf', [ProductController::class, 'exportPdf'])->name('products.export.pdf');
        Route::get('products/export/whatsapp', [ProductController::class, 'exportWhatsApp'])->name('products.export.whatsapp');
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
        Route::post('shop-invoices/{invoice}/revert-approval', [ShopInvoiceController::class, 'revertApproval'])->name('shop-invoices.revert-approval');
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
        Route::post('invoices/{invoice}/fix-calculation', [PurchaseInvoiceController::class, 'fixCalculation'])->name('invoices.fix-calculation');
        Route::post('invoices/fix-all-calculations', [PurchaseInvoiceController::class, 'fixAllCalculations'])->name('invoices.fix-all-calculations');
        Route::post('invoices/{invoice}/supplier', [PurchaseInvoiceController::class, 'changeSupplier'])->name('invoices.change-supplier');
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
    Route::get('/purchaser/cart', [PurchaserDashboardController::class, 'cart'])->name('purchaser.cart');
    Route::get('/purchaser/vendors', [PurchaserDashboardController::class, 'vendors'])->name('purchaser.vendors');
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
    Route::get('/purchaser/invoices/{invoice}', [PurchaserDashboardController::class, 'invoiceShow'])->name('purchaser.invoices.show');
    Route::get('/purchaser/invoices/{invoice}/pdf', [PurchaserDashboardController::class, 'invoicePdf'])->name('purchaser.invoices.pdf');
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

    // ── Admin ──────────────────────────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('inventory/empty', [EmptyInventoryController::class, 'index'])->name('inventory-empty.index');
        Route::post('inventory/empty', [EmptyInventoryController::class, 'store'])->name('inventory-empty.store');
        Route::get('inventory/empty/{process}/progress', [EmptyInventoryController::class, 'progress'])->name('inventory-empty.progress');
        Route::post('inventory/empty/{process}/retry', [EmptyInventoryController::class, 'retry'])->name('inventory-empty.retry');
        Route::get('/', AdminOverviewController::class)->name('overview');
        Route::get('company-settings', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
        Route::patch('company-settings', [CompanySettingsController::class, 'update'])->name('company-settings.update');
        Route::get('auto-load-all', [AdminAutoLoadAllController::class, 'create'])->name('auto-load-all.create');
        Route::prefix('auto-load-all/api')->name('auto-load-all.api.')->group(function () {
            Route::get('/manifest', [ApiWarehouseLoadoutController::class, 'index'])->name('manifest');
            Route::get('/orders/{shopOrder}', [ApiWarehouseLoadoutController::class, 'show'])->name('show');
            Route::post('/orders/{shopOrder}/save', [ApiWarehouseLoadoutController::class, 'save'])->name('save');
        });
        // Cashbook admin dashboard — full port of the standalone ledger-app.
        // Completely isolated from the ShopOwner accounting screens.
        // All routes are guarded at controller level by ensureMainAdmin().
        Route::prefix('cashbook')->name('cashbook.')->group(function () {
            // ── Page routes ─────────────────────────────────────────────────
            Route::get('/', [CashbookController::class, 'index'])->name('index');
            Route::get('all-shops', [CashbookController::class, 'allShops'])->name('all-shops');
            Route::get('overview-cards', [AdminCashbookReportsController::class, 'hub'])->name('reports.hub');
            Route::get('reports', [CashbookController::class, 'reports'])->name('reports');
            Route::get('reports/shop/{shop}', [AdminCashbookReportsController::class, 'detail'])->name('reports.shop');
            Route::get('reports/charts', [AdminCashbookReportsController::class, 'charts'])->name('reports.charts');
            Route::get('reports/analytics', [AdminCashbookReportsController::class, 'analytics'])->name('reports.analytics');
            Route::get('reports/gl-bills', [AdminCashbookReportsController::class, 'glBills'])->name('reports.gl-bills');
            Route::get('reports/api/hub', [AdminCashbookReportsController::class, 'apiHubData'])->name('reports.api.hub');
            Route::get('mobile/ledger/{shop}', [AdminCashbookReportsController::class, 'mobileLedger'])->name('reports.mobile-ledger');
            Route::get('reports/export/csv', [CashbookController::class, 'exportReportsCsv'])->name('reports.export.csv');
            Route::get('reports/export/excel', [CashbookController::class, 'exportReportsExcel'])->name('reports.export.excel');
            Route::get('payables', [CashbookController::class, 'payables'])->name('payables');
            Route::get('accept-payment', [CashbookController::class, 'acceptPaymentPage'])->name('accept-payment');
            Route::get('income-expenses', [CashbookController::class, 'incomeExpenses'])->name('income-expenses');
            Route::get('post-entry', [CashbookController::class, 'postEntryPage'])->name('post-entry');
            Route::get('post-entry/{shop}', [CashbookController::class, 'postEntryPageForShop'])->name('post-entry.shop');
            Route::get('shops/{shop}', [CashbookController::class, 'showShop'])->name('shop.show');
            Route::get('shops/{shop}/export', [CashbookController::class, 'exportShopData'])->name('shop.export');
            Route::get('shops/{shop}/settlement', [CashbookController::class, 'shopSettlementPage'])->name('shop.settlement');
            Route::get('shops/{shop}/accept-payment', [CashbookController::class, 'shopSettlementPage'])->name('shop.accept-payment');
            Route::get('shops/{shop}/post-entry', [CashbookController::class, 'postEntryPageForShop'])->name('shop.post-entry');
            Route::get('rules-config', [CashbookController::class, 'rulesPage'])->name('rules-config');
            Route::get('settings', [CashbookController::class, 'settingsPage'])->name('settings');
            Route::get('settings/shops/{shop}', [CashbookController::class, 'shopSettingsPage'])->name('settings.shop');
            Route::get('settings/presets', [CashbookController::class, 'presetsPage'])->name('settings.presets');
            Route::get('settings/collections', [CashbookController::class, 'collectionGroupsPage'])->name('settings.collections');
            Route::get('bank-accounts/create', [CashbookController::class, 'createBankAccountPage'])->name('bank-accounts.create');
            Route::post('bank-accounts', [CashbookController::class, 'storeBankAccount'])->name('bank-accounts.store');
            Route::put('bank-accounts/{account}', [CashbookController::class, 'updateBankAccount'])->name('bank-accounts.update');
            Route::delete('bank-accounts/{account}', [CashbookController::class, 'deleteBankAccount'])->name('bank-accounts.delete');

            // ── JSON API routes (rate limited & throttled) ─────────────────────
            Route::prefix('api')->middleware('throttle:60,1')->name('api.')->group(function () {
                Route::get('shop-data', [CashbookController::class, 'getShopData'])->name('shop-data');
                Route::get('all-shops-overview', [CashbookController::class, 'getAllShopsOverview'])->name('all-shops-overview');
                Route::get('payables-pendings', [CashbookController::class, 'getPayablesAndPendings'])->name('payables-pendings');
                Route::get('rules', [CashbookController::class, 'getRules'])->name('rules');
                Route::get('company-accounts', [CashbookController::class, 'getCompanyAccounts'])->name('company-accounts');
                Route::get('client-summary', [CashbookController::class, 'getClientSummary'])->name('client-summary');
                Route::get('report-bills', [CashbookController::class, 'getReportBills'])->name('report-bills');
                Route::get('presets', [CashbookController::class, 'getPresets'])->name('presets');

                Route::post('record-entry', [CashbookController::class, 'recordEntry'])->name('record-entry');
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
                Route::post('shop-settings/custom-row', [CashbookController::class, 'createShopCustomRow'])->name('shop-settings.custom-row');
                Route::post('shop-settings/collection', [CashbookController::class, 'saveShopCollectionSettings'])->name('shop-settings.collection');
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
});
