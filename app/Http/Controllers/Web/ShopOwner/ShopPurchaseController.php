<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Purchasing\ShopPurchaseService;
use App\Services\Purchasing\ShopVendorReportService;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $requestedDate = $request->string('date', '')->toString();
        $businessDate = $this->dailyLedgerService->resolveActiveBusinessDate($shop, $requestedDate ?: null);
        $startDate = $request->string('start_date', '')->toString();
        $endDate = $request->string('end_date', '')->toString();
        $supplierId = $request->integer('supplier_id') ?: null;
        $paymentMethod = $request->string('payment_method', '')->toString();

        $query = PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart.items.product', 'shopVendorPayable'])
            ->where(function ($q) use ($shop): void {
                $q->where('shop_id', $shop->id)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('destination_shop_id', $shop->id));
            })
            ->notCancelled();

        if ($startDate !== '') {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate !== '') {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }
        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        $allInvoicesForSummary = (clone $query)->get();

        $summary = [
            'total_invoices' => $allInvoicesForSummary->count(),
            'total_amount' => round((float) $allInvoicesForSummary->sum('amount'), 2),
            'cash_amount' => round((float) $allInvoicesForSummary->where('payment_method', 'Cash')->sum('amount'), 2),
            'credit_amount' => round((float) $allInvoicesForSummary->where('payment_method', 'Credit')->sum('amount'), 2),
            'credit_outstanding' => round((float) $allInvoicesForSummary->sum(fn ($inv) => (float) ($inv->shopVendorPayable?->outstanding_amount ?? 0)), 2),
        ];

        $invoices = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $suppliers = $shop->suppliers()->where('is_active', true)->orderBy('name')->get();
        if ($suppliers->isEmpty()) {
            $suppliers = Supplier::query()->orderBy('name')->limit(50)->get();
        }

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'supplier_id' => $supplierId,
            'payment_method' => $paymentMethod,
        ];

        return view('shop-owner.purchasing.index', [
            'activeShop' => $shop,
            'shop' => $shop,
            'invoices' => $invoices,
            'summary' => $summary,
            'filters' => $filters,
            'suppliers' => $suppliers,
            'businessDate' => $businessDate,
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

        $suppliers = $shop->suppliers()->where('is_active', true)->orderBy('name')->get();
        if ($suppliers->isEmpty()) {
            $suppliers = Supplier::query()->orderBy('name')->limit(50)->get();
        }

        return view('shop-owner.purchasing.create', [
            'activeShop' => $shop,
            'shop' => $shop,
            'products' => $products,
            'suppliers' => $suppliers,
            'businessDate' => $businessDate,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

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

        $validated = $request->validate([
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

        if (empty($validated['supplier_id']) && empty($validated['new_supplier_name'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select an existing vendor or enter a new vendor name.',
                ], 422);
            }

            return back()->withInput()->withErrors(['supplier_id' => 'Please select or create a vendor.']);
        }

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

        $invoice = $this->purchaseService->recordPurchase($shop, $validated, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Purchase {$invoice->invoice_number} recorded successfully.",
                'invoice' => $invoice,
            ]);
        }

        return redirect()->route('shop-owner.purchasing.index', ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')])
            ->with('success', "Purchase bill {$invoice->invoice_number} recorded successfully.");
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

        $validated = $request->validate([
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

    public function suppliers(Request $request): JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $search = trim((string) $request->input('q', ''));
        $suppliers = Supplier::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('mobile_number', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'mobile_number', 'contact']);

        return response()->json(['suppliers' => $suppliers]);
    }

    private function resolveShop(Request $request): Shop
    {
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
}
