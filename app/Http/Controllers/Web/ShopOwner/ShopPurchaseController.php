<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Purchasing\ShopPurchaseService;
use App\Services\Purchasing\ShopVendorReportService;
use App\Support\ShopOwner\ActiveShopResolver;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ShopPurchaseController extends Controller
{
    public function __construct(
        private readonly ShopPurchaseService $purchaseService,
        private readonly ShopVendorReportService $reportService,
        private readonly ActiveShopResolver $activeShopResolver,
        private readonly DailyLedgerService $dailyLedgerService,
    ) {}

    public function index(Request $request): View
    {
        return $this->vendorPurchasesPage($request);
    }

    /**
     * Dedicated Shop Owner Vendor Purchases operational page.
     */
    public function vendorPurchasesPage(Request $request): View
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $now = now('Asia/Kolkata');
        $requestedDate = $request->string('date', '')->toString();
        $activeBusinessDate = $this->dailyLedgerService->resolveActiveBusinessDate($shop, $requestedDate ?: null);
        $selectedDate = Carbon::parse($activeBusinessDate);

        $period = $request->string('period', '')->toString();
        if ($period === '') {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $period = 'custom';
            } elseif ($requestedDate !== '' || $request->has('date')) {
                $period = 'exact';
            } else {
                $period = 'today';
            }
        }

        if (! in_array($period, ['today', 'yesterday', 'exact', '7days', '7_days', '30days', '30_days', 'month', 'this_month', 'custom', 'all'], true)) {
            $period = 'today';
        }

        $startDate = null;
        $endDate = null;

        if ($period === 'today' || $period === 'exact') {
            $startDate = $selectedDate->toDateString();
            $endDate = $selectedDate->toDateString();
        } elseif ($period === 'yesterday') {
            $startDate = $selectedDate->copy()->subDay()->toDateString();
            $endDate = $selectedDate->copy()->subDay()->toDateString();
        } elseif ($period === '7days' || $period === '7_days') {
            $startDate = $selectedDate->copy()->subDays(6)->toDateString();
            $endDate = $selectedDate->toDateString();
        } elseif ($period === '30days' || $period === '30_days') {
            $startDate = $selectedDate->copy()->subDays(29)->toDateString();
            $endDate = $selectedDate->toDateString();
        } elseif ($period === 'month' || $period === 'this_month') {
            $startDate = $selectedDate->copy()->startOfMonth()->toDateString();
            $endDate = $selectedDate->copy()->endOfMonth()->toDateString();
        } elseif ($period === 'custom') {
            $startDate = $request->string('start_date', '')->toString() ?: ($requestedDate ?: $selectedDate->toDateString());
            $endDate = $request->string('end_date', '')->toString() ?: $startDate;
        }

        $supplierId = $request->integer('supplier_id') ?: null;
        $categoryId = $request->integer('category_id') ?: ($request->integer('shop_ledger_entry_setting_id') ?: null);
        $paymentMethod = $request->string('payment_method', 'all')->toString();
        $search = trim($request->string('search', '')->toString());

        $query = PurchaseInvoice::query()
            ->with([
                'supplier',
                'shopLedgerEntrySetting.headerGroup',
                'purchaserCart.items.product',
                'shopVendorPayable',
            ])
            ->where(function ($q) use ($shop): void {
                $q->where('shop_id', $shop->id)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('destination_shop_id', $shop->id));
            })
            ->where('purchase_source', 'shop')
            ->notCancelled();

        if ($startDate && $endDate) {
            $query->where(function ($dq) use ($startDate, $endDate): void {
                $dq->whereBetween('original_business_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate): void {
                        $sub->whereNull('original_business_date')
                            ->whereHas('purchaserCart', fn ($pq) => $pq->whereBetween('business_date', [$startDate, $endDate]));
                    });
            });
        }

        if ($supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        if ($categoryId !== null) {
            $catSetting = ShopLedgerEntrySetting::find($categoryId);
            if ($catSetting) {
                if ($catSetting->isVendorPurchaseCash()) {
                    $query->where(function ($sq) use ($categoryId): void {
                        $sq->where('shop_ledger_entry_setting_id', $categoryId)
                            ->orWhere(function ($subQ): void {
                                $subQ->whereNull('shop_ledger_entry_setting_id')
                                    ->where('payment_method', 'Cash');
                            });
                    });
                } elseif ($catSetting->isVendorPurchaseCredit()) {
                    $query->where(function ($sq) use ($categoryId): void {
                        $sq->where('shop_ledger_entry_setting_id', $categoryId)
                            ->orWhere(function ($subQ): void {
                                $subQ->whereNull('shop_ledger_entry_setting_id')
                                    ->where('payment_method', 'Credit');
                            });
                    });
                } else {
                    $query->where('shop_ledger_entry_setting_id', $categoryId);
                }
            } else {
                $query->where('shop_ledger_entry_setting_id', $categoryId);
            }
        }

        if ($paymentMethod !== 'all' && $paymentMethod !== '') {
            $query->where('payment_method', ucfirst(strtolower($paymentMethod)));
        }

        if ($search !== '') {
            $query->where(function ($sq) use ($search): void {
                $sq->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supQ) => $supQ->where('name', 'like', "%{$search}%")->orWhere('mobile_number', 'like', "%{$search}%"))
                    ->orWhereHas('purchaserCart.items.product', fn ($prodQ) => $prodQ->where('name', 'like', "%{$search}%"));
            });
        }

        $allInvoicesForSummary = (clone $query)->get();

        $summary = [
            'total_invoices' => $allInvoicesForSummary->count(),
            'total_amount' => round((float) $allInvoicesForSummary->sum(fn ($inv) => (float) ($inv->amount - $inv->discount_amount)), 2),
            'cash_amount' => round((float) $allInvoicesForSummary->filter(fn ($inv) => strcasecmp((string) $inv->payment_method, 'Cash') === 0)->sum(fn ($inv) => (float) ($inv->amount - $inv->discount_amount)), 2),
            'credit_amount' => round((float) $allInvoicesForSummary->filter(fn ($inv) => strcasecmp((string) $inv->payment_method, 'Credit') === 0)->sum(fn ($inv) => (float) ($inv->amount - $inv->discount_amount)), 2),
            'credit_outstanding' => round((float) $allInvoicesForSummary->sum(fn ($inv) => (float) ($inv->shopVendorPayable?->outstanding_amount ?? ($inv->payment_method === 'Credit' ? max(0.0, ($inv->amount - $inv->discount_amount) - $inv->paid_amount) : 0))), 2),
        ];

        $invoices = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $suppliers = $shop->suppliers()
            ->wherePivot('is_active', true)
            ->orderBy('suppliers.name')
            ->get();

        $categories = ShopLedgerEntrySetting::query()
            ->with(['headerGroup', 'definedShopSuppliers.supplier'])
            ->where('shop_id', (int) $shop->id)
            ->where('is_vendor_purchase', true)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get()
            ->each(function (ShopLedgerEntrySetting $setting) {
                $setting->setAttribute(
                    'defined_supplier_ids',
                    $setting->definedShopSuppliers
                        ->filter(fn ($ss) => (bool) $ss->is_active && $ss->supplier)
                        ->pluck('supplier_id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all()
                );
            });

        $products = Product::query()
            ->active()
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'sku', 'unit']);

        $filters = [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'supplier_id' => $supplierId,
            'category_id' => $categoryId,
            'payment_method' => $paymentMethod,
            'search' => $search,
        ];

        return view('shop-owner.cashbook.vendor-purchases.index', [
            'activeShop' => $shop,
            'shop' => $shop,
            'invoices' => $invoices,
            'summary' => $summary,
            'filters' => $filters,
            'suppliers' => $suppliers,
            'linkedVendors' => $suppliers,
            'selectedDate' => $selectedDate,
            'categories' => $categories,
            'products' => $products,
            'purchasableProducts' => $products,
            'period' => $period,
            'isAdmin' => $this->isAdminUser($request->user()),
            'cutoffDate' => $this->purchaseService->getCutoffDateString($shop),
            'editWindow' => $this->purchaseService->getEditWindowConfig($shop),
            'editWindowDisplayText' => $this->purchaseService->getEditWindowDisplayText($shop),
        ]);
    }

    public function create(Request $request): View
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $requestedDate = $request->string('date', '')->toString();
        $businessDate = $this->dailyLedgerService->resolveActiveBusinessDate($shop, $requestedDate ?: null);
        $products = Product::query()
            ->active()
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'sku', 'unit']);

        $suppliers = $shop->suppliers()
            ->wherePivot('is_active', true)
            ->orderBy('suppliers.name')
            ->get();

        return view('shop-owner.purchasing.create', [
            'activeShop' => $shop,
            'shop' => $shop,
            'products' => $products,
            'suppliers' => $suppliers,
            'businessDate' => $businessDate,
        ]);
    }

    public function showPurchase(Request $request, PurchaseInvoice $invoice): JsonResponse
    {
        $shop = $this->resolveShop($request);
        if (! $this->isAdminUser($request->user())) {
            abort_unless((int) $invoice->shop_id === (int) $shop->id, 403, 'Unauthorized access to purchase.');
        }

        $invoice->load([
            'supplier',
            'shopLedgerEntrySetting.headerGroup',
            'purchaserCart.items.product',
            'shopVendorPayable',
        ]);

        $cart = $invoice->purchaserCart;
        $items = ($cart?->items ?? collect())->map(function ($item) {
            $qty = (float) $item->quantity;
            $lineTotal = (float) $item->line_total;
            $avgBuy = $qty > 0 ? round($lineTotal / $qty, 2) : 0.0;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? 'Product #'.$item->product_id,
                'sku' => $item->product?->sku ?? '',
                'quantity' => $qty,
                'unit' => $item->unit ?: ($item->product?->unit ?? 'kg'),
                'grade' => $item->grade ?? 'A',
                'unit_price' => (float) $item->unit_price,
                'line_total' => $lineTotal,
                'avg_buy' => $avgBuy,
            ];
        });

        $dateStr = $cart?->business_date?->format('Y-m-d') ?? $invoice->original_business_date?->format('Y-m-d') ?? $invoice->created_at->format('Y-m-d');
        $setting = $invoice->shopLedgerEntrySetting;

        $isEditAllowed = $this->isActionAllowedForUser($invoice, $request->user());
        if ($isEditAllowed) {
            try {
                if ($setting?->entry_type_id) {
                    $this->dailyLedgerService->assertEditAllowed((int) $shop->id, $dateStr, (int) $setting->entry_type_id);
                }
            } catch (Throwable) {
                $isEditAllowed = false;
            }
        }

        return response()->json([
            'success' => true,
            'purchase' => [
                'id' => $invoice->id,
                'uuid' => $invoice->public_uuid,
                'invoice_number' => $invoice->invoice_number,
                'business_date' => $dateStr,
                'supplier_id' => $invoice->supplier_id,
                'supplier_name' => $invoice->supplier?->name ?? 'Vendor #'.$invoice->supplier_id,
                'supplier_mobile' => $invoice->supplier?->mobile_number ?: ($invoice->supplier?->contact ?: ''),
                'shop_ledger_entry_setting_id' => $invoice->shop_ledger_entry_setting_id,
                'category_name' => $setting?->displayName() ?? 'Vendor Purchase',
                'header_name' => $setting?->headerGroup?->name ?? 'Expenses',
                'payment_method' => $invoice->payment_method,
                'notes' => $invoice->notes ?: ($cart?->notes ?: ''),
                'gross_amount' => (float) $invoice->amount,
                'discount_amount' => (float) $invoice->discount_amount,
                'net_amount' => round((float) ($invoice->amount - $invoice->discount_amount), 2),
                'paid_amount' => (float) $invoice->paid_amount,
                'outstanding_amount' => (float) ($invoice->shopVendorPayable?->outstanding_amount ?? 0),
                'status' => $invoice->status?->value ?? 'paid',
                'is_edit_allowed' => $isEditAllowed,
                'items' => $items,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $this->normalizeItemInputs($request);

        $validated = $request->validate([
            'shop_ledger_entry_setting_id' => ['nullable', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'new_supplier_name' => ['nullable', 'string', 'max:255'],
            'new_supplier_mobile' => ['nullable', 'string', 'max:20'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:Cash,Credit,cash,credit'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.grade' => ['nullable', 'string', 'in:A,B,a,b'],
        ]);

        $businessDateStr = $validated['business_date'] ?? null;
        if (! $businessDateStr) {
            $businessDateStr = $this->dailyLedgerService->resolveActiveBusinessDate($shop)->toDateString();
            $validated['business_date'] = $businessDateStr;
        }

        if (! $this->purchaseService->isDateActionAllowed($businessDateStr, $request->user(), $shop)) {
            $windowText = $this->purchaseService->getEditWindowDisplayText($shop);
            $msg = "Purchases for date {$businessDateStr} cannot be created because it is outside the allowed edit window ({$windowText}).";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'errors' => ['business_date' => [$msg]],
                ], 422);
            }

            return back()->withInput()->withErrors(['business_date' => $msg]);
        }

        if (empty($validated['supplier_id']) && empty($validated['new_supplier_name'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an existing vendor or enter a new vendor name.',
                ], 422);
            }

            return back()->withInput()->withErrors(['supplier_id' => 'Please select or create a vendor.']);
        }

        $this->validateCategoryAndSupplierAccess($shop, $validated, $request);

        if (strcasecmp((string) ($validated['payment_method'] ?? ''), 'Credit') === 0 && ! empty($validated['supplier_id'])) {
            $linkedSupplier = $shop->suppliers()->where('suppliers.id', (int) $validated['supplier_id'])->first();
            /** @var Supplier|null $supplier */
            $supplier = $linkedSupplier ?? Supplier::query()->find($validated['supplier_id']);
            $creditApproved = (bool) ($linkedSupplier?->pivot?->credit_approved ?? $supplier?->credit_approved ?? false);

            if ($supplier && ! $creditApproved) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Credit purchases are not enabled for vendor '{$supplier->name}'.",
                        'errors' => ['payment_method' => ["Credit is not enabled for vendor '{$supplier->name}'."]],
                    ], 422);
                }

                return back()->withInput()->withErrors(['payment_method' => "Credit is not enabled for vendor '{$supplier->name}'."]);
            }
        }

        $validated['is_shop_owner_flow'] = true;
        $invoice = $this->purchaseService->recordPurchase($shop, $validated, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Purchase {$invoice->invoice_number} recorded successfully.",
                'invoice' => $invoice,
            ]);
        }

        return redirect()->route('shop-owner.cashbook.vendor-purchases', ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')])
            ->with('success', "Purchase bill {$invoice->invoice_number} recorded successfully.");
    }

    public function updatePurchase(Request $request, PurchaseInvoice $invoice): JsonResponse|RedirectResponse
    {
        $shop = $this->resolveShop($request);
        if (! $this->isAdminUser($request->user())) {
            abort_unless((int) $invoice->shop_id === (int) $shop->id, 403, 'Unauthorized to edit this purchase.');
        }

        if (! $this->isActionAllowedForUser($invoice, $request->user())) {
            $windowText = $this->purchaseService->getEditWindowDisplayText($shop);
            $msg = "Actions on vendor purchases older than {$windowText} are restricted to administrators.";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            return back()->withErrors(['error' => $msg]);
        }

        $this->normalizeItemInputs($request);

        $validated = $request->validate([
            'shop_ledger_entry_setting_id' => ['nullable', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'new_supplier_name' => ['nullable', 'string', 'max:255'],
            'new_supplier_mobile' => ['nullable', 'string', 'max:20'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:Cash,Credit,cash,credit'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.grade' => ['nullable', 'string', 'in:A,B,a,b'],
        ]);

        if (empty($validated['supplier_id']) && empty($validated['new_supplier_name'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an existing vendor or enter a new vendor name.',
                ], 422);
            }

            return back()->withInput()->withErrors(['supplier_id' => 'Please select or create a vendor.']);
        }

        $validated['shop_ledger_entry_setting_id'] = $validated['shop_ledger_entry_setting_id'] ?? $invoice->shop_ledger_entry_setting_id;

        $this->validateCategoryAndSupplierAccess($shop, $validated, $request);

        if (strcasecmp((string) ($validated['payment_method'] ?? ''), 'Credit') === 0 && ! empty($validated['supplier_id'])) {
            $linkedSupplier = $shop->suppliers()->where('suppliers.id', (int) $validated['supplier_id'])->first();
            /** @var Supplier|null $supplier */
            $supplier = $linkedSupplier ?? Supplier::query()->find($validated['supplier_id']);
            $creditApproved = (bool) ($linkedSupplier?->pivot?->credit_approved ?? $supplier?->credit_approved ?? false);

            if ($supplier && ! $creditApproved) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Credit purchases are not enabled for vendor '{$supplier->name}'.",
                        'errors' => ['payment_method' => ["Credit is not enabled for vendor '{$supplier->name}'."]],
                    ], 422);
                }

                return back()->withInput()->withErrors(['payment_method' => "Credit is not enabled for vendor '{$supplier->name}'."]);
            }
        }

        try {
            $updated = $this->purchaseService->updatePurchase($invoice, $validated, $request->user());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase {$updated->invoice_number} updated successfully.",
                    'invoice' => $updated,
                ]);
            }

            return redirect()->route('shop-owner.cashbook.vendor-purchases', ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')])
                ->with('success', "Purchase {$updated->invoice_number} updated successfully.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroyPurchase(Request $request, PurchaseInvoice $invoice): JsonResponse|RedirectResponse
    {
        $shop = $this->resolveShop($request);
        if (! $this->isAdminUser($request->user())) {
            abort_unless((int) $invoice->shop_id === (int) $shop->id, 403, 'Unauthorized to delete this purchase.');
        }

        if (! $this->isActionAllowedForUser($invoice, $request->user())) {
            $windowText = $this->purchaseService->getEditWindowDisplayText($shop);
            $msg = "Actions on vendor purchases older than {$windowText} are restricted to administrators.";
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            return back()->withErrors(['error' => $msg]);
        }

        try {
            $reason = $request->string('reason', 'Cancelled by user')->toString();
            $this->purchaseService->cancelPurchase($invoice, $request->user(), $reason);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase {$invoice->invoice_number} cancelled successfully.",
                ]);
            }

            return redirect()->route('shop-owner.cashbook.vendor-purchases', ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')])
                ->with('success', "Purchase {$invoice->invoice_number} cancelled successfully.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reports(Request $request): View
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $date = $request->string('date', '')->toString();
        $startDate = $request->string('start_date', '')->toString();
        $endDate = $request->string('end_date', '')->toString();
        $paymentType = $request->string('payment_type', 'all')->toString();
        $supplierId = $request->integer('supplier_id') ?: null;
        $search = $request->string('search', '')->toString();

        $filters = array_filter([
            'date' => $date,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_type' => $paymentType,
            'supplier_id' => $supplierId,
            'search' => $search,
        ]);

        $reportData = $this->reportService->getShopVendorSummary($shop, $filters);
        $allSuppliers = Supplier::query()->orderBy('name')->get(['id', 'name']);

        return view('shop-owner.purchasing.report', [
            'shop' => $shop,
            'summary' => $reportData['summary'],
            'vendorRows' => $reportData['vendor_rows'],
            'suppliers' => $allSuppliers,
            'filters' => $filters,
            'selectedDate' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'paymentType' => $paymentType,
            'selectedSupplierId' => $supplierId,
            'search' => $search,
        ]);
    }

    public function vendorDetail(Request $request, Supplier $supplier): View
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $date = $request->string('date', '')->toString();
        $startDate = $request->string('start_date', '')->toString();
        $endDate = $request->string('end_date', '')->toString();
        $paymentType = $request->string('payment_type', 'all')->toString();

        $filters = array_filter([
            'date' => $date,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_type' => $paymentType,
        ]);

        $detail = $this->reportService->getShopVendorDetail($shop, $supplier, $filters);

        return view('shop-owner.purchasing.vendor-detail', [
            'shop' => $shop,
            'supplier' => $supplier,
            'summary' => $detail['summary'],
            'invoices' => $detail['invoices'],
            'filters' => $filters,
        ]);
    }

    public function storeVendor(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        abort_unless($shop->isVendorCreationAllowed(), 403, 'Vendor creation is not permitted for this shop.');

        $categoryId = $request->integer('shop_ledger_entry_setting_id') ?: $request->integer('category_id');
        if ($categoryId) {
            $categorySetting = ShopLedgerEntrySetting::query()->where('shop_id', (int) $shop->id)->find($categoryId);
            if ($categorySetting && $categorySetting->is_vendor_purchase && in_array($categorySetting->vendor_access_mode, ['linked_only', 'defined_only'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor creation is not permitted for this category.',
                ], 422);
            }
        }

        $validated = $request->validate([
            'shop_ledger_entry_setting_id' => ['nullable', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'category_id' => ['nullable', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = trim($validated['name']);
        $mobile = trim((string) ($validated['mobile_number'] ?? ''));

        /** @var Supplier $supplier */
        $supplier = Supplier::query()->firstOrCreate(
            ['name' => $name],
            [
                'mobile_number' => $mobile ?: null,
                'contact' => $validated['contact'] ?? null,
                'type' => 'local',
                'category' => 'shop_vendor',
                'credit_approved' => false,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($shop->suppliers()->where('supplier_id', $supplier->id)->exists()) {
            $shop->suppliers()->updateExistingPivot($supplier->id, [
                'is_active' => true,
            ]);
        } else {
            $shop->suppliers()->attach($supplier->id, [
                'is_active' => true,
                'credit_approved' => false,
            ]);
        }

        $freshPivot = $shop->suppliers()->where('supplier_id', $supplier->id)->first();
        $isCreditApproved = (bool) ($freshPivot?->pivot?->credit_approved ?? false);

        return response()->json([
            'success' => true,
            'message' => "Vendor '{$supplier->name}' added successfully.",
            'vendor' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'mobile_number' => $supplier->mobile_number,
                'contact' => $supplier->contact,
                'credit_approved' => $isCreditApproved,
            ],
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'mobile_number' => $supplier->mobile_number,
                'contact' => $supplier->contact,
                'credit_approved' => $isCreditApproved,
            ],
        ]);
    }

    public function searchVendors(Request $request): JsonResponse
    {
        return $this->suppliers($request);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $search = trim((string) ($request->input('q') ?? $request->input('search') ?? ''));
        $suppliers = $shop->suppliers()
            ->wherePivot('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('suppliers.name', 'like', "%{$search}%")
                    ->orWhere('suppliers.mobile_number', 'like', "%{$search}%");
            }))
            ->orderBy('suppliers.name')
            ->limit(30)
            ->get(['suppliers.id', 'suppliers.name', 'suppliers.mobile_number', 'suppliers.contact']);

        return response()->json([
            'success' => true,
            'suppliers' => $suppliers,
            'vendors' => $suppliers,
        ]);
    }

    private function normalizeItemInputs(Request $request): void
    {
        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            foreach ($items as $idx => $item) {
                if (is_array($item)) {
                    $qty = isset($item['quantity']) ? (float) $item['quantity'] : (isset($item['qty']) ? (float) $item['qty'] : 0.0);
                    if (isset($item['total_price'])) {
                        $totalPrice = (float) $item['total_price'];
                        $items[$idx]['unit_price'] = $qty > 0 ? round($totalPrice / $qty, 4) : 0.0;
                    } elseif (isset($item['rate']) && ! isset($item['unit_price'])) {
                        $items[$idx]['unit_price'] = $item['rate'];
                    }
                    if (isset($item['qty']) && ! isset($item['quantity'])) {
                        $items[$idx]['quantity'] = $item['qty'];
                    }
                }
            }
            $request->merge(['items' => $items]);
        }
    }

    private function rejectAccess(Request $request, string $field, string $message): void
    {
        if ($request->wantsJson()) {
            throw new HttpResponseException(
                response()->json(['success' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422)
            );
        }

        abort(422, $message);
    }

    private function validateCategoryAndSupplierAccess(Shop $shop, array &$validated, Request $request): void
    {
        $paymentMethod = (string) ($validated['payment_method'] ?? 'Cash');
        $explicitSettingId = ! empty($validated['shop_ledger_entry_setting_id']) ? (int) $validated['shop_ledger_entry_setting_id'] : null;

        $explicitSetting = null;
        if ($explicitSettingId) {
            $explicitSetting = ShopLedgerEntrySetting::query()->with('definedShopSuppliers')->find($explicitSettingId);
            if (! $explicitSetting || (int) $explicitSetting->shop_id !== (int) $shop->id) {
                $this->rejectAccess($request, 'shop_ledger_entry_setting_id', 'The selected category does not belong to this shop.');
            }
            if (! $explicitSetting->enabled) {
                $this->rejectAccess($request, 'shop_ledger_entry_setting_id', 'The selected category is disabled.');
            }
            if (! $explicitSetting->is_vendor_purchase) {
                $this->rejectAccess($request, 'shop_ledger_entry_setting_id', 'Vendor purchasing is not enabled for this category.');
            }
        }

        /** @var ShopLedgerEntrySetting|null $categorySetting */
        $categorySetting = $this->purchaseService->resolveVendorPurchaseCategorySetting($shop, $paymentMethod, $explicitSettingId);
        if ($categorySetting) {
            $validated['shop_ledger_entry_setting_id'] = $categorySetting->id;
            $categorySetting->loadMissing('definedShopSuppliers');
        }

        $validationTarget = $explicitSetting ?? $categorySetting;

        if ($validationTarget) {
            if (! $validationTarget->enabled) {
                $this->rejectAccess($request, 'shop_ledger_entry_setting_id', 'The selected category is disabled.');
            }

            if (! $validationTarget->is_vendor_purchase) {
                $this->rejectAccess($request, 'shop_ledger_entry_setting_id', 'Vendor purchasing is not enabled for this category.');
            }

            $mode = $validationTarget->vendor_access_mode ?: 'linked_create';

            if ($mode === 'defined_only') {
                if (! empty($validated['new_supplier_name'])) {
                    $this->rejectAccess($request, 'new_supplier_name', 'Vendor creation is not permitted for this category.');
                }

                if (! empty($validated['supplier_id'])) {
                    $shopSupplier = ShopSupplier::query()
                        ->where('shop_id', (int) $shop->id)
                        ->where('supplier_id', (int) $validated['supplier_id'])
                        ->where('is_active', true)
                        ->first();

                    if (! $shopSupplier || ! $validationTarget->definedShopSuppliers->contains('id', $shopSupplier->id)) {
                        $this->rejectAccess($request, 'supplier_id', "The selected vendor is not permitted for category '{$validationTarget->displayName()}'.");
                    }
                }
            } elseif ($mode === 'linked_only') {
                if (! empty($validated['new_supplier_name'])) {
                    $this->rejectAccess($request, 'new_supplier_name', 'Vendor creation is not permitted for this category.');
                }

                if (! empty($validated['supplier_id'])) {
                    $isActiveLinked = ShopSupplier::query()
                        ->where('shop_id', (int) $shop->id)
                        ->where('supplier_id', (int) $validated['supplier_id'])
                        ->where('is_active', true)
                        ->exists();

                    if (! $isActiveLinked) {
                        $this->rejectAccess($request, 'supplier_id', 'The selected vendor is not active for this shop.');
                    }
                }
            } else {
                if (! empty($validated['new_supplier_name']) && ! $shop->isVendorCreationAllowed()) {
                    $this->rejectAccess($request, 'new_supplier_name', 'Vendor creation is not permitted for this shop.');
                }

                if (! empty($validated['supplier_id'])) {
                    $isActiveLinked = ShopSupplier::query()
                        ->where('shop_id', (int) $shop->id)
                        ->where('supplier_id', (int) $validated['supplier_id'])
                        ->where('is_active', true)
                        ->exists();

                    if (! $isActiveLinked) {
                        $this->rejectAccess($request, 'supplier_id', 'The selected vendor is not active for this shop.');
                    }
                }
            }
        } else {
            if (! empty($validated['new_supplier_name']) && ! $shop->isVendorCreationAllowed()) {
                $this->rejectAccess($request, 'new_supplier_name', 'Vendor creation is not permitted for this shop.');
            }

            if (! empty($validated['supplier_id'])) {
                $isLinked = ShopSupplier::query()
                    ->where('shop_id', (int) $shop->id)
                    ->where('supplier_id', (int) $validated['supplier_id'])
                    ->where('is_active', true)
                    ->exists();

                if (! $isLinked) {
                    $this->rejectAccess($request, 'supplier_id', 'The selected vendor is not active for this shop.');
                }
            }
        }
    }

    private function resolveShop(Request $request): Shop
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('main_admin') || $user->hasRole('super_admin'))) {
            $shopId = $request->integer('shop_id') ?: $request->session()->get('active_shop_id');
            if ($shopId) {
                $shop = Shop::find($shopId);
                if ($shop) {
                    return $shop;
                }
            }

            $fallbackShop = Shop::query()->where('is_active', true)->where('shop_purchasing_enabled', true)->first()
                ?? Shop::query()->where('is_active', true)->first()
                ?? Shop::query()->first();

            if ($fallbackShop) {
                return $fallbackShop;
            }
        }

        $authorizedShops = $this->activeShopResolver->authorizedShops($request->user());
        if ($authorizedShops->isNotEmpty()) {
            return $this->activeShopResolver->resolve($request);
        }

        /** @var Shop $shop */
        $shop = $request->user()?->shop;
        abort_unless($shop instanceof Shop, 403, 'No shop assigned.');

        return $shop;
    }

    private function ensurePurchasingEnabled(Shop $shop): void
    {
        abort_unless($shop->isPurchasingEnabled(), 403, 'Shop Purchasing is not enabled for this shop.');
    }

    private function isAdminUser(?User $user): bool
    {
        return $this->purchaseService->isAdminUser($user);
    }

    private function isActionAllowedForUser(PurchaseInvoice $invoice, ?User $user): bool
    {
        return $this->purchaseService->isInvoiceActionAllowed($invoice, $user);
    }
}
