<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaserCartItemRequest;
use App\Http\Requests\Web\Purchasing\StorePurchaserCorrectionRequest;
use App\Http\Requests\Web\Purchasing\SubmitPurchaserCartRequest;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCorrectionRequest;
use App\Models\PurchaserCredit;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\VendorPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaserDashboardController extends Controller
{
    private const array QUICK_FILTERS = [
        'Frequent',
        'All',
        'Supply',
        'VEG',
        'Leaf',
        'English',
        'Kolkata',
        'Banana',
        'Onion',
        'C',
        'Frut',
        'Stationory',
    ];

    public function __construct(
        private readonly VendorPriceService $vendorPriceService,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('purchaser.daily');
    }

    public function daily(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $selectedChip = $this->resolveQuickFilter($request->string('chip')->toString());
        $search = trim($request->string('search')->toString());
        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);

        $dailySummary = $this->buildDailySummary($date, $frequentProductIds);
        $filteredDailySummary = $this->filterProductsForChip($dailySummary, $selectedChip, $search, $frequentProductIds);

        $draftCarts = $this->draftCartsForDate((int) $user->id, $date);

        $productCatalog = Product::query()
            ->with('category')
            ->active()
            ->ordered()
            ->get();

        return view('purchasing.purchaser.daily', [
            'date' => $date->format('Y-m-d'),
            'quickFilters' => self::QUICK_FILTERS,
            'selectedChip' => $selectedChip,
            'search' => $search,
            'dailySummary' => $filteredDailySummary,
            'draftCarts' => $draftCarts,
            'buyOtherProducts' => $this->filterProductsForChip($productCatalog, $selectedChip, $search, $frequentProductIds),
            'dailySummaryShareUrl' => 'https://api.whatsapp.com/send?text='.rawurlencode($this->buildDailySummaryShareText($dailySummary, $date)),
            'dailyFulfillment' => [
                'products' => $dailySummary->count(),
                'approved_qty' => (float) $dailySummary->sum('total_approved_qty'),
                'bought_qty' => (float) $dailySummary->sum('bought_qty'),
                'remaining_qty' => (float) $dailySummary->sum('remaining_qty'),
                'draft_carts' => $draftCarts->count(),
            ],
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function dailyShare(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);
        $dailySummary = $this->buildDailySummary($date, $frequentProductIds);

        $availableTags = $dailySummary
            ->pluck('category_name')
            ->filter(fn (?string $categoryName): bool => filled($categoryName))
            ->unique()
            ->sort()
            ->values();

        $shareMode = $this->resolveDailyShareMode($request->string('share_mode')->toString());
        $availableProductIds = $dailySummary
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        $selectedTags = collect($request->input('tags', []))
            ->filter(fn ($tag): bool => is_string($tag) && $availableTags->contains($tag))
            ->values()
            ->all();
        $selectedProductIds = collect($request->input('product_ids', []))
            ->map(fn ($productId): int => (int) $productId)
            ->filter(fn (int $productId): bool => in_array($productId, $availableProductIds, true))
            ->unique()
            ->values()
            ->all();

        $selectedProductId = $request->integer('product_id');
        if (! $dailySummary->contains(fn (array $summary): bool => (int) $summary['product_id'] === $selectedProductId)) {
            $selectedProductId = 0;
        }

        $shareSummary = $this->filterDailySummaryForShare(
            dailySummary: $dailySummary,
            shareMode: $shareMode,
            selectedTags: $selectedTags,
            selectedProductIds: $selectedProductIds,
            selectedProductId: $selectedProductId,
        );

        $sharePreviewText = $this->buildDailySummaryShareText($shareSummary, $date);
        $shareUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($sharePreviewText);

        return view('purchasing.purchaser.daily_share', [
            'date' => $date->format('Y-m-d'),
            'shareMode' => $shareMode,
            'selectedTags' => $selectedTags,
            'selectedProductIds' => $selectedProductIds,
            'selectedProductId' => $selectedProductId,
            'availableTags' => $availableTags,
            'availableProducts' => $dailySummary
                ->sortBy(fn (array $summary): string => Product::sortableSku((string) ($summary['sku'] ?? '')))
                ->map(fn (array $summary): array => [
                    'product_id' => (int) $summary['product_id'],
                    'product_name' => (string) $summary['product_name'],
                    'category_name' => (string) $summary['category_name'],
                    'remaining_qty' => (float) $summary['remaining_qty'],
                    'unit' => (string) $summary['unit'],
                    'search_index' => strtolower(trim(implode(' ', [
                        (string) $summary['product_name'],
                        (string) $summary['category_name'],
                        (string) ($summary['sku'] ?? ''),
                    ]))),
                ])
                ->values(),
            'shareSummary' => $shareSummary,
            'sharePreviewText' => $sharePreviewText,
            'shareUrl' => $shareUrl,
        ]);
    }

    public function bulkBuy(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);
        $dailySummary = $this->buildDailySummary($date, $frequentProductIds);

        return view('purchasing.purchaser.bulk_buy', [
            'date' => $date->format('Y-m-d'),
            'quickFilters' => self::QUICK_FILTERS,
            'dailySummary' => $dailySummary,
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function bulkBuyDetails(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $productIds = $request->input('product_ids');
        if (empty($productIds) || ! is_array($productIds)) {
            return redirect()
                ->route('purchaser.bulk-buy', ['date' => $date->format('Y-m-d')])
                ->with('error', 'Please select at least one product.');
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);

        $dailySummary = $this->buildDailySummary($date, $frequentProductIds)
            ->filter(fn ($item) => in_array((int) $item['product_id'], array_map('intval', $productIds), true))
            ->values();

        if ($dailySummary->isEmpty()) {
            return redirect()
                ->route('purchaser.bulk-buy', ['date' => $date->format('Y-m-d')])
                ->with('error', 'Selected products do not have approved demand.');
        }

        $draftCarts = $this->draftCartsForDate((int) $user->id, $date);

        $bulkPriceHintsByCart = $draftCarts->mapWithKeys(fn (PurchaserCart $cart): array => [
            $cart->id => $this->vendorPriceService->previousPricesForSupplier(
                $cart->supplier_id,
                $dailySummary->pluck('product_id')->all(),
            ),
        ])->all();

        return view('purchasing.purchaser.bulk_buy_details', [
            'date' => $date->format('Y-m-d'),
            'dailySummary' => $dailySummary,
            'draftCarts' => $draftCarts,
            'bulkPriceHintsByCart' => $bulkPriceHintsByCart,
            'bulkFallbackPriceHints' => $this->vendorPriceService->previousPricesForSupplier(
                null,
                $dailySummary->pluck('product_id')->all(),
            ),
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function cart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        return redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')]);
    }

    public function bill(Request $request, PurchaserCart $cart): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft']);

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->withErrors(['The selected cart is empty.']);
        }

        return view('purchasing.purchaser.bill', [
            'date' => $cart->business_date->format('Y-m-d'),
            'cart' => $cart,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'subtotal' => (float) $cart->items->sum('line_total'),
            'vendorPriceHints' => $this->vendorPriceService->previousPricesForSupplier(
                $cart->supplier_id,
                $cart->items->pluck('product_id')->all(),
            ),
            'deadlineAlert' => $this->buildDeadlineAlert((int) $request->user()->id, $cart->business_date),
        ]);
    }

    public function history(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $todayCarts = PurchaserCart::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('business_date', $date)
            ->with([
                'supplier',
                'items.product.category',
                'purchaseOrder',
                'goodsReceived',
                'purchaseInvoice',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $historyCarts = PurchaserCart::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('business_date', '<', $date)
            ->with([
                'supplier',
                'items.product.category',
                'purchaseOrder',
                'goodsReceived',
                'purchaseInvoice',
            ])
            ->orderByDesc('business_date')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $overdueCarts = $this->overdueCartsForUser((int) $request->user()->id);

        $historyCarts = $historyCarts
            ->merge($overdueCarts)
            ->unique('id')
            ->values();

        $allCarts = $todayCarts->merge($historyCarts)->unique('id')->values();

        $relatedBatchState = $this->relatedBatchStateForCarts($allCarts);

        $groupedCarts = collect([
            'today' => $todayCarts->sortByDesc(fn (PurchaserCart $cart) => $cart->purchaseInvoice?->updated_at ?? $cart->updated_at)->values(),
            'history' => $historyCarts->sortByDesc(fn (PurchaserCart $cart) => $cart->purchaseInvoice?->updated_at ?? $cart->updated_at)->values(),
        ]);

        return view('purchasing.purchaser.history', [
            'date' => $date->format('Y-m-d'),
            'groupedCarts' => $groupedCarts,
            'statusBadges' => $this->statusBadgesForCarts($allCarts, $relatedBatchState),
            'relatedBatchState' => $relatedBatchState,
            'relatedReceiptNotes' => $this->relatedReceiptNotesForCarts($allCarts),
            'deadlineAlert' => $this->buildDeadlineAlert((int) $request->user()->id, $date),
        ]);
    }

    public function vendors(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $focusCartId = $request->integer('focus_cart');

        $carts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->with([
                'supplier',
                'items.product.category',
                'goodsReceived.items.product',
                'goodsReceived.items.purchaseOrderItem.product',
                'purchaseOrder',
                'purchaseInvoice',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $relatedBatchState = $this->relatedBatchStateForCarts($carts);
        $relatedReceiptNotes = $this->relatedReceiptNotesForCarts($carts);
        $relatedReceiptDiscrepancies = $carts->mapWithKeys(fn (PurchaserCart $cart): array => [
            (int) $cart->id => $this->buildReceiptDiscrepancySummary($cart->goodsReceived),
        ])->all();
        $draftCarts = $carts->where('status', 'draft')->values();
        $submittedCarts = $carts->where('status', 'submitted')->values();
        $pendingCarts = $submittedCarts
            ->filter(fn (PurchaserCart $cart): bool => ! $this->isWarehouseConfirmed($relatedBatchState[(int) $cart->id] ?? []) || $this->cartHasPaymentPending($cart))
            ->values();
        $completedCarts = $submittedCarts
            ->filter(fn (PurchaserCart $cart): bool => $this->isWarehouseConfirmed($relatedBatchState[(int) $cart->id] ?? []) && ! $this->cartHasPaymentPending($cart))
            ->values();
        $activeTab = $request->string('tab')->toString();

        if (! in_array($activeTab, ['draft', 'pending', 'completed'], true)) {
            $activeTab = match (true) {
                $completedCarts->contains('id', $focusCartId) => 'completed',
                $pendingCarts->contains('id', $focusCartId) => 'pending',
                default => 'draft',
            };
        }

        $mergeSuggestions = $this->buildDraftMergeSuggestions($draftCarts);
        $mergeableDraftCounts = $mergeSuggestions
            ->mapWithKeys(fn (array $suggestion): array => [
                (int) $suggestion['target_cart']->id => (int) $suggestion['count'] - 1,
            ])
            ->all();

        $productCatalog = Product::query()
            ->with('category')
            ->active()
            ->ordered()
            ->get();

        $suppliers = Supplier::query()->orderBy('name')->get();

        return view('purchasing.purchaser.vendors', [
            'date' => $date->format('Y-m-d'),
            'draftCarts' => $draftCarts,
            'pendingCarts' => $pendingCarts,
            'completedCarts' => $completedCarts,
            'mergeSuggestions' => $mergeSuggestions,
            'mergeableDraftCounts' => $mergeableDraftCounts,
            'productCatalog' => $productCatalog,
            'suppliers' => $suppliers,
            'vendorPriceHintsByCart' => $carts->mapWithKeys(fn (PurchaserCart $cart): array => [
                $cart->id => $this->vendorPriceService->previousPricesForSupplier(
                    $cart->supplier_id,
                    $cart->items->pluck('product_id')->all(),
                ),
            ])->all(),
            'activeTab' => $activeTab,
            'focusCartId' => $focusCartId,
            'relatedBatchState' => $relatedBatchState,
            'relatedReceiptNotes' => $relatedReceiptNotes,
            'relatedReceiptDiscrepancies' => $relatedReceiptDiscrepancies,
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function finance(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $selectedTab = $request->string('tab')->toString();
        if (! in_array($selectedTab, ['today', 'old'], true)) {
            $selectedTab = 'today';
        }
        $search = trim($request->string('search')->toString());

        $baseInvoiceQuery = PurchaseInvoice::query()
            ->with(['supplier', 'goodsReceived', 'purchaserCart'])
            ->whereHas('purchaserCart', function ($query) use ($request, $date): void {
                $query
                    ->where('user_id', $request->user()->id)
                    ->whereDate('business_date', '<=', $date);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('payment_status', 'like', '%'.$search.'%')
                        ->orWhere('payment_method', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('mobile_number', 'like', '%'.$search.'%')
                                ->orWhere('location', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('purchaserCart', function ($cartQuery) use ($search): void {
                            $cartQuery
                                ->where('cart_number', 'like', '%'.$search.'%')
                                ->orWhere('bill_number', 'like', '%'.$search.'%');
                        });
                });
            });

        $invoices = (clone $baseInvoiceQuery)
            ->whereHas('purchaserCart', function ($query) use ($selectedTab, $date): void {
                $query->when(
                    $selectedTab === 'old',
                    fn ($cartQuery) => $cartQuery->whereDate('business_date', '<', $date),
                    fn ($cartQuery) => $cartQuery->whereDate('business_date', $date),
                );
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('purchasing.purchaser.finance', [
            'date' => $date->format('Y-m-d'),
            'invoices' => $invoices,
            'search' => $search,
            'selectedTab' => $selectedTab,
            'financeTabs' => [
                'today' => (clone $baseInvoiceQuery)
                    ->whereHas('purchaserCart', fn ($query) => $query->whereDate('business_date', $date))
                    ->count(),
                'old' => (clone $baseInvoiceQuery)
                    ->whereHas('purchaserCart', fn ($query) => $query->whereDate('business_date', '<', $date))
                    ->count(),
            ],
            'financeAudience' => 'purchaser',
            'canManageSuppliers' => true,
            'deadlineAlert' => $this->buildDeadlineAlert((int) $request->user()->id, $date),
        ]);
    }

    public function cash(Request $request): View
    {
        $this->ensurePurchaser($request);

        $user = $request->user();
        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $user->id)
            ->with(['purchaseInvoice', 'creator'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $totalIn = round((float) $credits->where('type', 'in')->sum('amount'), 2);
        $totalOut = round((float) $credits->where('type', 'out')->sum('amount'), 2);
        $balance = round($totalIn - $totalOut, 2);

        return view('purchasing.purchaser.cash', [
            'credits' => $credits,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'balance' => $balance,
        ]);
    }

    public function supplierHub(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $search = trim($request->string('search')->toString());
        $normalizedSearch = mb_strtolower($search);
        $selectedTab = $request->string('tab')->toString();
        if (! in_array($selectedTab, ['pending', 'credit'], true)) {
            $selectedTab = 'pending';
        }
        $userId = (int) $request->user()->id;
        $suppliers = Supplier::query()
            ->where(function ($query) use ($userId): void {
                $query
                    ->whereHas('purchaserCarts', fn ($cartQuery) => $cartQuery->where('user_id', $userId))
                    ->orWhereHas('purchaseInvoices', fn ($invoiceQuery) => $invoiceQuery
                        ->whereHas('purchaserCart', fn ($cartQuery) => $cartQuery->where('user_id', $userId)));
            })
            ->when($search !== '', function ($query) use ($search, $normalizedSearch): void {
                $query->where(function ($innerQuery) use ($search, $normalizedSearch): void {
                    $innerQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('mobile_number', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhereHas('purchaseInvoices', function ($invoiceQuery) use ($search): void {
                            $invoiceQuery
                                ->where('payment_status', 'like', '%'.$search.'%')
                                ->orWhere('payment_method', 'like', '%'.$search.'%')
                                ->orWhere('invoice_number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('purchaserCarts', function ($cartQuery) use ($search): void {
                            $cartQuery
                                ->where('cart_number', 'like', '%'.$search.'%')
                                ->orWhere('status', 'like', '%'.$search.'%');
                        });

                    if (str_contains($normalizedSearch, 'pending')) {
                        $innerQuery->orWhereHas('purchaseInvoices', function ($invoiceQuery): void {
                            $invoiceQuery->whereIn('payment_status', ['unpaid', 'partial', 'credit_pending_approval']);
                        });
                    }

                    if (str_contains($normalizedSearch, 'credit')) {
                        $innerQuery->orWhere('credit_approved', true);
                    }

                    if (str_contains($normalizedSearch, 'paid')) {
                        $innerQuery->orWhereHas('purchaseInvoices', function ($invoiceQuery): void {
                            $invoiceQuery->where('payment_status', 'paid');
                        });
                    }
                });
            })
            ->with([
                'purchaseInvoices' => fn ($query) => $query
                    ->whereHas('purchaserCart', fn ($cartQuery) => $cartQuery->where('user_id', $userId))
                    ->with('purchaserCart')
                    ->latest('updated_at'),
                'purchaserCarts' => fn ($query) => $query
                    ->where('user_id', $userId)
                    ->with('purchaseInvoice')
                    ->latest('business_date'),
            ])
            ->orderBy('name')
            ->get();

        $sameDayAssignedDrafts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->whereNotNull('supplier_id')
            ->with('supplier')
            ->get()
            ->filter(fn (PurchaserCart $cart): bool => $cart->items()->exists());

        $overdueCarts = $this->overdueCartsForUser($userId)->loadMissing(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived']);
        $overdueBatchState = $this->relatedBatchStateForCarts($overdueCarts);

        $tabCounts = [
            'pending' => $suppliers->filter(fn (Supplier $supplier): bool => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, 'pending') > 0)->count(),
            'credit' => $suppliers->filter(fn (Supplier $supplier): bool => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, 'credit') > 0)->count(),
        ];

        $issueSections = $this->buildSupplierIssueSections(
            userId: $userId,
            selectedDate: $date,
            suppliers: $suppliers,
            selectedTab: $selectedTab,
        );

        $filteredSuppliers = $suppliers->filter(fn (Supplier $supplier): bool => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab) > 0)->values();

        $supplierRows = $suppliers->map(function (Supplier $supplier) use ($date, $selectedTab, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState): array {
            $relevantInvoices = $this->linkedInvoicesForSupplier($supplier);
            $recentInvoice = $relevantInvoices
                ->sortByDesc(fn (PurchaseInvoice $invoice): int => $invoice->updated_at?->getTimestamp() ?? 0)
                ->first();
            $recentCart = $supplier->purchaserCarts->first();
            $totalAmount = round((float) $relevantInvoices->sum('amount'), 2);
            $discountAmount = round((float) $relevantInvoices->sum('discount_amount'), 2);
            $paidAmount = round((float) $relevantInvoices->sum('paid_amount'), 2);
            $balanceAmount = round((float) $relevantInvoices->sum(fn (PurchaseInvoice $invoice): float => $this->invoiceRemainingBalance($invoice)), 2);
            $recentBusinessDate = $recentInvoice?->purchaserCart?->business_date ?? $recentCart?->business_date;

            return [
                'supplier' => $supplier,
                'pending_count' => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab),
                'recent_invoice_number' => $recentInvoice?->invoice_number ?: 'None yet',
                'recent_cart_number' => $recentCart?->cart_number ?: 'No cart yet',
                'recent_business_date' => $recentBusinessDate?->format('d M Y') ?: '—',
                'recent_updated_label' => $recentInvoice?->updated_at?->format('d M Y') ?: '—',
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'history_route' => route('purchaser.suppliers.show', ['supplier' => $supplier, 'date' => $date->format('Y-m-d')]),
            ];
        });

        return view('purchasing.purchaser.suppliers.index', [
            'date' => $date->format('Y-m-d'),
            'search' => $search,
            'suppliers' => $filteredSuppliers,
            'supplierRows' => $supplierRows,
            'issueSections' => $issueSections,
            'selectedTab' => $selectedTab,
            'tabCounts' => $tabCounts,
            'deadlineAlert' => $this->buildDeadlineAlert($userId, $date),
        ]);
    }

    public function supplierShow(Request $request, Supplier $supplier): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $userId = (int) $request->user()->id;
        $supplier = Supplier::query()
            ->whereKey($supplier->id)
            ->where(function ($query) use ($userId): void {
                $query
                    ->whereHas('purchaserCarts', fn ($cartQuery) => $cartQuery->where('user_id', $userId))
                    ->orWhereHas('purchaseInvoices', fn ($invoiceQuery) => $invoiceQuery
                        ->whereHas('purchaserCart', fn ($cartQuery) => $cartQuery->where('user_id', $userId)));
            })
            ->with([
                'purchaseInvoices' => fn ($query) => $query
                    ->whereHas('purchaserCart', fn ($cartQuery) => $cartQuery->where('user_id', $userId))
                    ->with(['purchaserCart.goodsReceived.items.product', 'purchaserCart.goodsReceived.items.purchaseOrderItem.product', 'goodsReceived.items.product', 'goodsReceived.items.purchaseOrderItem.product'])
                    ->latest('updated_at'),
                'purchaserCarts' => fn ($query) => $query
                    ->where('user_id', $userId)
                    ->with(['items.product', 'purchaseInvoice', 'goodsReceived.items.product', 'goodsReceived.items.purchaseOrderItem.product'])
                    ->latest('business_date'),
            ])
            ->firstOrFail();

        $relatedBatchState = $this->relatedBatchStateForCarts($supplier->purchaserCarts);
        $vendorHistoryEntries = $supplier->purchaserCarts
            ->map(function (PurchaserCart $cart) use ($relatedBatchState): array {
                $invoice = $cart->purchaseInvoice;
                $batchState = $relatedBatchState[(int) $cart->id] ?? [];
                $operationalState = $this->cartOperationalState($cart, $batchState);
                $receiptNotes = $cart->goodsReceived?->notes;
                $discrepancySummary = $this->buildReceiptDiscrepancySummary($cart->goodsReceived);
                $itemCount = (int) $cart->items->count();
                $totalQuantity = round((float) $cart->items->sum('quantity'), 2);
                $itemSummary = $cart->items
                    ->map(function ($item): string {
                        $productName = $item->product?->name ?? 'Product';
                        $quantity = $this->trimTrailingZeros((float) $item->quantity);
                        $unit = $item->product?->unit ?? '';

                        return trim("{$productName} {$quantity} {$unit}");
                    })
                    ->values()
                    ->all();

                return [
                    'date_key' => $cart->business_date->format('Y-m-d'),
                    'date_label' => $cart->business_date->format('d M Y'),
                    'cart_number' => $cart->cart_number,
                    'invoice_number' => $invoice?->invoice_number,
                    'amount' => (float) ($invoice?->amount ?? max(0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount)),
                    'updated_at' => $invoice?->updated_at ?? $cart->updated_at,
                    'updated_label' => ($invoice?->updated_at ?? $cart->updated_at)?->format('d M Y h:i A'),
                    'payment_status' => str($invoice?->payment_status ?: $cart->payment_status ?: 'unpaid')->replace('_', ' ')->title()->toString(),
                    'payment_method' => $invoice?->payment_method ?: $cart->payment_method ?: 'Cash',
                    'paid_amount' => (float) ($invoice?->paid_amount ?? $cart->paid_amount ?? 0),
                    'balance_amount' => $invoice ? $this->invoiceRemainingBalance($invoice) : 0.0,
                    'item_count' => $itemCount,
                    'total_quantity' => $totalQuantity,
                    'item_summary' => $itemSummary,
                    'receipt_notes' => $receiptNotes,
                    'discrepancy_summary' => $discrepancySummary,
                    'status_label' => $operationalState['label'],
                    'status_tone' => $operationalState['tone'],
                    'is_operationally_unresolved' => $operationalState['unresolved'],
                    'is_payment_pending' => $operationalState['payment_pending'],
                    'payment_route' => $operationalState['payment_pending'] && $invoice ? route('purchaser.invoices.payment', $invoice) : null,
                    'payment_modal' => $operationalState['payment_pending'] && $invoice ? [
                        'number' => $invoice->invoice_number,
                        'supplier' => $cart->supplier?->name,
                        'amount' => round((float) $invoice->amount, 2),
                        'discountAmount' => round((float) $invoice->discount_amount, 2),
                        'paidAmount' => round((float) $invoice->paid_amount, 2),
                        'paymentMethod' => $invoice->payment_method ?: 'Cash',
                        'paymentNote' => $invoice->payment_note,
                        'paymentDetails' => $invoice->payment_details,
                        'creditApproved' => (bool) $cart->supplier?->credit_approved,
                    ] : null,
                ];
            });

        $vendorHistory = $vendorHistoryEntries
            ->groupBy('date_key')
            ->map(function ($entries, $historyDate): array {
                $entries = collect($entries)->values();
                $firstEntry = $entries->first();

                return [
                    'date_key' => (string) $historyDate,
                    'date_label' => $firstEntry['date_label'],
                    'record_count' => $entries->count(),
                    'item_count' => (int) $entries->sum('item_count'),
                    'total_quantity' => round((float) $entries->sum('total_quantity'), 2),
                    'total_amount' => round((float) $entries->sum('amount'), 2),
                    'paid_amount' => round((float) $entries->sum('paid_amount'), 2),
                    'balance_amount' => round((float) $entries->sum('balance_amount'), 2),
                    'pending_count' => $entries->where('is_operationally_unresolved', true)->count(),
                    'completed_count' => $entries->where('is_operationally_unresolved', false)->count(),
                    'entries' => $entries,
                ];
            })
            ->sortByDesc('date_key')
            ->values();

        $historyTotals = [
            'pending_amount' => round((float) $vendorHistoryEntries->sum('balance_amount'), 2),
            'paid_amount' => round((float) $vendorHistoryEntries->sum('paid_amount'), 2),
            'total_amount' => round((float) $vendorHistoryEntries->sum('amount'), 2),
            'discount_amount' => round((float) $vendorHistoryEntries->sum(fn (array $entry): float => max(0, $entry['amount'] - $entry['paid_amount'] - $entry['balance_amount'])), 2),
            'item_count' => (int) $vendorHistoryEntries->sum('item_count'),
            'record_count' => (int) $vendorHistoryEntries->count(),
        ];

        $pendingEntries = $vendorHistoryEntries->where('is_operationally_unresolved', true)->values();
        $completedEntries = $vendorHistoryEntries->where('is_operationally_unresolved', false)->values();

        return view('purchasing.purchaser.suppliers.show', [
            'date' => $date->format('Y-m-d'),
            'supplier' => $supplier,
            'pendingInvoices' => $pendingEntries,
            'completedInvoices' => $completedEntries,
            'vendorHistory' => $vendorHistory,
            'historyTotals' => $historyTotals,
            'deadlineAlert' => $this->buildDeadlineAlert($userId, $date),
        ]);
    }

    public function invoicePdf(Request $request, PurchaseInvoice $invoice): View
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with([
                'supplier',
                'purchaserCart.items.product',
                'goodsReceived.items.product',
                'goodsReceived.purchaseOrder',
            ])
            ->firstOrFail();

        return view('purchasing.invoices.pdf', compact('invoice'));
    }

    public function invoiceShow(Request $request, PurchaseInvoice $invoice): View
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with([
                'supplier',
                'purchaserCart.items.product',
                'goodsReceived.items.product',
                'goodsReceived.purchaseOrder',
                'purchaserCart',
            ])
            ->firstOrFail();

        return view('purchasing.invoices.show', [
            'invoice' => $invoice,
            'paymentUpdateRouteName' => 'purchaser.invoices.payment',
            'billPdfRouteName' => 'purchaser.invoices.pdf',
            'backRouteName' => 'purchaser.finance',
            'backRouteParameters' => ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')],
            'financeAudience' => 'purchaser',
        ]);
    }

    public function mergeDraftCarts(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft']);
        $mergeGroup = $this->mergeGroupDraftCarts($cart)->values();

        if ($mergeGroup->count() <= 1) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->with('error', 'No other draft carts are available to merge.');
        }

        /** @var PurchaserCart $targetCart */
        $targetCart = $mergeGroup->sortByDesc('updated_at')->first();

        foreach ($mergeGroup as $sourceCart) {
            if ($sourceCart->is($targetCart)) {
                continue;
            }

            $this->mergeDraftCartIntoTarget($sourceCart, $targetCart);
            $targetCart = $targetCart->fresh(['supplier', 'items.product.category', 'goodsReceived']);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $targetCart->business_date->format('Y-m-d')])
            ->with('success', 'Draft carts merged into one cart.');
    }

    public function bulkStoreCart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->businessDayService->isSelectableDate((string) $value)) {
                        $fail('The selected business date is not available for purchaser flow.');
                    }
                },
            ],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['required', 'exists:products,id'],
            'cart_id' => ['nullable', 'integer'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $date = Carbon::parse($validated['business_date']);
        $user = $request->user();
        $cartId = filled($validated['cart_id'] ?? null) ? (int) $validated['cart_id'] : null;

        $cart = $cartId
            ? PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->where('status', 'draft')
                ->firstOrFail()
            : PurchaserCart::query()
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->first();

        if (! $cart) {
            $cart = PurchaserCart::query()->create([
                'user_id' => $user->id,
                'business_date' => $date,
                'cart_number' => PurchaserCart::generateCartNumber($date),
                'status' => 'draft',
            ]);
        }

        $addedCount = 0;
        foreach ($validated['product_ids'] as $productId) {
            $productId = (int) $productId;
            $product = Product::query()->findOrFail($productId);
            $itemData = $validated['items'][$productId] ?? null;

            if (! is_array($itemData)) {
                continue;
            }

            $remainingApproved = $this->remainingApprovedQuantityForProduct($date, $productId, (int) $cart->id);
            $quantity = (float) $itemData['quantity'];
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);

            $existingItem = $cart->items()->where('product_id', $productId)->first();
            $newQuantity = $existingItem instanceof PurchaserCartItem
                ? (float) $existingItem->quantity + $quantity
                : $quantity;

            if ($existingItem instanceof PurchaserCartItem) {
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($newQuantity * $unitPrice, 2),
                    'is_extra_purchase' => $newQuantity > $remainingApproved,
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'is_extra_purchase' => $quantity > $remainingApproved,
                ]);
                $addedCount++;
            }
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
            ->with('success', "Added {$addedCount} products to vendor cart.");
    }

    public function storeCart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->businessDayService->isSelectableDate((string) $value)) {
                        $fail('The selected business date is not available for purchaser flow.');
                    }
                },
            ],
        ]);

        $date = Carbon::parse($validated['business_date']);
        $cart = $this->findReusableDraftCart(
            userId: (int) $request->user()->id,
            date: $date,
            supplierId: null,
        ) ?? PurchaserCart::query()->create([
            'user_id' => $request->user()->id,
            'business_date' => $date,
            'cart_number' => PurchaserCart::generateCartNumber($date),
            'status' => 'draft',
        ]);

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
            ->with('success', 'Draft cart ready.');
    }

    public function storeCartItem(StorePurchaserCartItemRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('business_date'));
        $user = $request->user();
        $cartId = $request->integer('cart_id');

        $cart = $cartId > 0
            ? PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->where('status', 'draft')
                ->with(['items.product', 'goodsReceived'])
                ->firstOrFail()
            : (PurchaserCart::query()
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->first()
              ?? PurchaserCart::query()->create([
                  'user_id' => $user->id,
                  'business_date' => $date,
                  'cart_number' => PurchaserCart::generateCartNumber($date),
                  'status' => 'draft',
              ]));

        $product = Product::query()->with('category')->findOrFail($request->integer('product_id'));
        $quantity = (float) $request->validated('quantity');
        $unitPrice = (float) $request->input('unit_price', 0);
        $existingItem = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = $existingItem instanceof PurchaserCartItem
            ? (float) $existingItem->quantity + $quantity
            : $quantity;

        $remainingApproved = $this->remainingApprovedQuantityForProduct($date, (int) $product->id, (int) $cart->id);
        $isExtraPurchase = $newQuantity > $remainingApproved;

        if ($existingItem instanceof PurchaserCartItem) {
            $existingItem->update([
                'quantity' => $newQuantity,
                'unit_price' => $unitPrice,
                'line_total' => round($newQuantity * $unitPrice, 2),
                'is_extra_purchase' => $isExtraPurchase,
                'notes' => $request->validated('notes'),
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
                'is_extra_purchase' => $isExtraPurchase,
                'notes' => $request->validated('notes'),
            ]);
        }

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $date,
            $cart,
            $isExtraPurchase
                ? "{$product->name} added to cart. Over-demand quantity will be flagged as extra purchase."
                : "{$product->name} added to cart."
        );
    }

    public function updateCartItem(Request $request, PurchaserCartItem $item): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $item->cart()
            ->where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->with('items')
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quantity = (float) $validated['quantity'];
        $unitPrice = (float) ($validated['unit_price'] ?? 0);
        $remainingApproved = $this->remainingApprovedQuantityForProduct($cart->business_date, (int) $item->product_id, (int) $cart->id);

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'is_extra_purchase' => $quantity > $remainingApproved,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $cart->business_date,
            $cart,
            'Vendor cart item updated.'
        );
    }

    public function destroyCartItem(Request $request, PurchaserCartItem $item): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $item->cart()->where('user_id', $request->user()->id)->where('status', 'draft')->firstOrFail();
        $item->delete();

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $cart->business_date,
            $cart,
            'Vendor cart item removed.'
        );
    }

    public function markCartSent(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $returnTo = $request->input('return_to', 'cart');

        if ($cart->items->isEmpty()) {
            return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart, '')
                ->withErrors(['The selected cart is empty.']);
        }

        $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
            'vendor_type' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'share_mode' => ['nullable', 'string', 'in:saved,custom,any'],
            'show_price' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->string('share_mode')->toString() === 'custom') {
            $digits = preg_replace('/\D+/', '', (string) $request->input('vendor_mobile_number'));

            if ($digits === null || strlen($digits) !== 10) {
                return $this->redirectAfterMutation($request->input('return_to', 'cart'), $cart->business_date, $cart, '')
                    ->withErrors(['Enter a valid 10 digit India mobile number.']);
            }
        }

        $supplier = $this->resolveSubmissionSupplier($request);
        $cart = $this->assignSupplierToCart($cart, $supplier);

        $cart->update([
            'supplier_id' => $supplier->id,
            'whatsapp_sent_at' => now(),
        ]);

        if ($cart->status === 'submitted') {
            if ($cart->purchaseOrder) {
                $cart->purchaseOrder->update(['supplier_id' => $supplier->id]);
            }
            if ($cart->purchaseInvoice) {
                $cart->purchaseInvoice->update(['supplier_id' => $supplier->id]);
            }
        }

        $discountAmount = round((float) $request->input('discount_amount', 0), 2);
        $showPrice = $request->boolean('show_price', false) || $discountAmount > 0;
        $message = $this->buildCartShareText($cart->fresh(['items.product', 'supplier']), $showPrice, $discountAmount);

        $shareMode = $request->string('share_mode')->toString() ?: 'saved';
        $customMobile = $request->input('vendor_mobile_number');

        if ($shareMode === 'any') {
            $whatsAppUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($message);
        } elseif ($shareMode === 'custom') {
            $digits = preg_replace('/\D+/', '', (string) $customMobile);
            if ($digits !== null && strlen($digits) === 10) {
                $digits = '91'.$digits;
            }
            $whatsAppUrl = $digits ? 'https://api.whatsapp.com/send?phone='.$digits.'&text='.rawurlencode($message) : null;
        } else {
            $whatsAppUrl = $this->buildSupplierWhatsAppUrl($supplier, $message);
        }

        if ($whatsAppUrl === null) {
            return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart, '')
                ->withErrors(['Selected vendor does not have a mobile number for WhatsApp.']);
        }

        return redirect()->away($whatsAppUrl);
    }

    public function updateCartSupplier(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $returnTo = $request->input('return_to', 'vendors');

        $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
        ]);

        $supplier = $this->resolveSubmissionSupplier($request);
        $cart = $this->assignSupplierToCart($cart, $supplier);

        $cart->update([
            'supplier_id' => $supplier->id,
        ]);

        if ($cart->status === 'submitted') {
            if ($cart->purchaseOrder) {
                $cart->purchaseOrder->update(['supplier_id' => $supplier->id]);
            }
            if ($cart->purchaseInvoice) {
                $cart->purchaseInvoice->update(['supplier_id' => $supplier->id]);
            }
        }

        return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart, 'Vendor updated successfully.');
    }

    public function submitCart(SubmitPurchaserCartRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('business_date'));
        $user = $request->user();

        /** @var PurchaserCart $cart */
        $cart = PurchaserCart::query()
            ->whereKey($request->integer('cart_id'))
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->with(['items.product', 'supplier'])
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
                ->withErrors(['The selected cart is empty.']);
        }

        $supplier = $this->resolveSubmissionSupplier($request);
        $paymentMethod = $request->validated('payment_method');

        if (strcasecmp($paymentMethod, 'Credit') === 0 && ! $supplier->credit_approved) {
            return redirect()
                ->route('purchaser.bill', ['cart' => $cart, 'date' => $date->format('Y-m-d')])
                ->withErrors(['This vendor is not approved for credit. Change payment method or contact your purchase manager.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $cart, $user, $date, $supplier, $paymentMethod): void {
            $cartItemsData = collect($request->input('items', []));
            foreach ($cart->items as $cartItem) {
                $itemInput = $cartItemsData->get((string) $cartItem->id, []);
                $unitPrice = (float) ($itemInput['unit_price'] ?? $cartItem->unit_price ?? 0);

                $cartItem->update([
                    'unit_price' => $unitPrice,
                    'line_total' => round((float) $cartItem->quantity * $unitPrice, 2),
                ]);
            }

            $cart->refresh()->load('items.product');

            $subtotalAmount = 0.0;
            $discountAmount = (float) $request->input('discount_amount', 0);
            $paidAmountInput = (float) $request->input('paid_amount', 0);
            $regularLines = [];
            $addOnLines = [];

            // Pre-fetch approved and already submitted quantities in a single query for N+1 query optimization
            $productIds = $cart->items->pluck('product_id')->unique()->all();

            $approvedQuantities = ShopOrderItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('order', function ($query) use ($date): void {
                    $query->whereDate('business_date', $date)->where('state', 'approved');
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(approved_qty) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            $alreadySubmittedQuantities = PurchaserCartItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('cart', function ($query) use ($date, $cart): void {
                    $query->whereDate('business_date', $date)
                        ->where('status', 'submitted')
                        ->whereKeyNot($cart->id);
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            foreach ($cart->items as $cartItem) {
                $unitPrice = (float) $cartItem->unit_price;
                $quantity = (float) $cartItem->quantity;
                $subtotalAmount += round($quantity * $unitPrice, 2);

                $approvedQty = (float) ($approvedQuantities[$cartItem->product_id] ?? 0);
                $submittedQty = (float) ($alreadySubmittedQuantities[$cartItem->product_id] ?? 0);
                $remainingApproved = max(0.0, $approvedQty - $submittedQty);

                $regularQuantity = min($quantity, $remainingApproved);
                $addOnQuantity = max(0, $quantity - $regularQuantity);

                if ($regularQuantity > 0) {
                    $regularLines[] = [
                        'cart_item' => $cartItem,
                        'quantity' => $regularQuantity,
                        'unit_price' => $unitPrice,
                    ];
                }

                if ($addOnQuantity > 0) {
                    $addOnLines[] = [
                        'cart_item' => $cartItem,
                        'quantity' => $addOnQuantity,
                        'unit_price' => $unitPrice,
                    ];
                }
            }

            $regularDocuments = $regularLines === []
                ? null
                : $this->createPurchaseDocumentsFromLines(
                    supplier: $supplier,
                    date: $date,
                    userId: (int) $user->id,
                    lines: $regularLines,
                    isExtra: false,
                    notes: $this->buildPurchaseDocumentNotes($cart, $request->string('notes')->toString()),
                    cartId: (int) $cart->id
                );

            $addOnDocuments = $addOnLines === []
                ? null
                : $this->createPurchaseDocumentsFromLines(
                    supplier: $supplier,
                    date: $date,
                    userId: (int) $user->id,
                    lines: $addOnLines,
                    isExtra: true,
                    notes: $this->buildPurchaseDocumentNotes(
                        $cart,
                        trim(($request->string('notes')->toString() ?: '')."\nAdd-on quantity from purchaser vendor cart.")
                    ),
                    cartId: (int) $cart->id
                );

            $primaryDocuments = $regularDocuments ?? $addOnDocuments;
            $invoiceAmount = max(0, round($subtotalAmount - $discountAmount, 2));
            $paidAmount = min($invoiceAmount, round($paidAmountInput, 2));
            $paymentStatus = $this->resolvePaymentStatus($paymentMethod, $invoiceAmount, $paidAmount);

            $invoice = PurchaseInvoice::query()->create([
                'goods_received_id' => $primaryDocuments['grn']->id,
                'supplier_id' => $supplier->id,
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => $request->validated('bill_number') ?: 'PENDING-BILL-'.$cart->cart_number,
                'amount' => $invoiceAmount,
                'discount_amount' => round($discountAmount, 2),
                'status' => InvoiceStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $request->validated('payment_note'),
                'payment_details' => $request->validated('payment_details'),
                'purchaser_submitted_by' => $user->id,
                'purchaser_submitted_at' => now(),
                'notes' => $request->validated('notes'),
            ]);

            $cart->update([
                'supplier_id' => $supplier->id,
                'bill_number' => $request->validated('bill_number'),
                'discount_amount' => round($discountAmount, 2),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $request->validated('payment_note'),
                'payment_details' => $request->validated('payment_details'),
                'notes' => $request->validated('notes'),
                'status' => 'submitted',
                'purchase_order_id' => $primaryDocuments['purchase_order']->id,
                'goods_received_id' => $primaryDocuments['grn']->id,
                'purchase_invoice_id' => $invoice->id,
                'submitted_at' => now(),
                'bill_received_at' => now(),
                'goods_received_at' => null,
                'payment_made_at' => $paymentStatus === 'paid' ? now() : null,
            ]);

            $this->vendorPriceService->syncMany(
                $supplier->id,
                $cart->items->map(fn (PurchaserCartItem $item): array => [
                    'product_id' => (int) $item->product_id,
                    'unit_price' => (float) $item->unit_price,
                ])->all(),
            );

            PurchaserCredit::create([
                'purchaser_id' => $user->id,
                'type' => 'out',
                'amount' => $invoice->amount,
                'description' => "Debit for invoice: {$invoice->invoice_number}",
                'purchase_invoice_id' => $invoice->id,
                'created_by' => $user->id,
                'business_date' => $date,
            ]);
        });

        if ($request->string('return_to')->toString() === 'suppliers') {
            return redirect()
                ->route('purchaser.suppliers', ['date' => $request->string('date', $date->format('Y-m-d'))->toString()])
                ->with('success', 'Cart submitted successfully.');
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d'), 'tab' => 'pending'])
            ->with('success', 'Cart submitted successfully.');
    }

    public function updateOperationalStatus(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['submitted']);

        $validated = $request->validate([
            'flag' => ['required', 'string', 'in:goods_received'],
        ]);

        $column = match ($validated['flag']) {
            'goods_received' => 'goods_received_at',
        };

        $cart->update([
            $column => $cart->{$column} ? null : now(),
        ]);

        if ($request->string('return_to')->toString() === 'suppliers') {
            return redirect()
                ->route('purchaser.suppliers', ['date' => $request->string('date', $cart->business_date->format('Y-m-d'))->toString()])
                ->with('success', 'Purchase status updated.');
        }

        return redirect()
            ->route('purchaser.history', ['date' => $cart->business_date->format('Y-m-d')])
            ->with('success', 'Purchase status updated.');
    }

    public function updateInvoicePayment(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['supplier', 'purchaserCart'])
            ->firstOrFail();

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:Cash,Online,GPay,Credit'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'additional_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_details' => ['nullable', 'string', 'max:1000'],
            'bill_number' => ['nullable', 'string', 'max:255'],
        ]);

        $existingPaidAmount = (float) ($invoice->paid_amount ?? 0);
        $hasAdditionalPaidAmount = $request->filled('additional_paid_amount');
        $resolvedPaidAmount = $hasAdditionalPaidAmount
            ? round($existingPaidAmount + (float) ($validated['additional_paid_amount'] ?? 0), 2)
            : (float) ($validated['paid_amount'] ?? $existingPaidAmount);

        $updatedInvoice = app(PurchaseInvoiceService::class)->updatePayment($invoice, [
            'payment_method' => $validated['payment_method'],
            'discount_amount' => (float) ($validated['discount_amount'] ?? $invoice->discount_amount ?? 0),
            'paid_amount' => $resolvedPaidAmount,
            'payment_note' => $validated['payment_note'] ?? null,
            'payment_details' => $validated['payment_details'] ?? null,
            'bill_number' => $validated['bill_number'] ?? null,
        ]);

        $remainingBalance = max(
            0,
            round(((float) $updatedInvoice->amount - (float) $updatedInvoice->discount_amount) - (float) $updatedInvoice->paid_amount, 2)
        );
        $message = $remainingBalance > 0 || $updatedInvoice->payment_method === 'Credit'
            ? 'Payment updated. Remaining balance or credit is still pending.'
            : 'Payment completed successfully.';

        if ($request->string('return_to')->toString() === 'vendors') {
            return redirect()
                ->route('purchaser.vendors', ['date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString()])
                ->with('success', $message);
        }

        if ($request->string('return_to')->toString() === 'suppliers') {
            return redirect()
                ->route('purchaser.suppliers', ['date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString()])
                ->with('success', $message);
        }

        if ($request->string('return_to')->toString() === 'supplier_detail' && $request->filled('supplier_id')) {
            $redirectSupplier = $updatedInvoice->supplier;

            if (! $redirectSupplier || (int) $redirectSupplier->id !== (int) $request->integer('supplier_id')) {
                $redirectSupplier = Supplier::query()->find($request->integer('supplier_id'));
            }

            return redirect()
                ->route('purchaser.suppliers.show', [
                    'supplier' => $redirectSupplier ?? (int) $request->integer('supplier_id'),
                    'date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString(),
                ])
                ->with('success', $message);
        }

        return redirect()
            ->route('purchaser.finance', array_filter([
                'date' => $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'tab' => $request->string('tab')->toString(),
            ]))
            ->with('success', $message);
    }

    public function storeCorrectionRequest(StorePurchaserCorrectionRequest $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $shopOrderItem = ShopOrderItem::query()
            ->with('order')
            ->findOrFail($request->integer('shop_order_item_id'));

        PurchaserCorrectionRequest::query()->create([
            'business_date' => $request->validated('business_date'),
            'shop_order_item_id' => $shopOrderItem->id,
            'current_approved_qty' => (float) $shopOrderItem->approved_qty,
            'proposed_corrected_qty' => (float) $request->validated('proposed_corrected_qty'),
            'purchaser_note' => $request->validated('purchaser_note'),
            'requester_user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('purchaser.daily', ['date' => Carbon::parse($request->validated('business_date'))->format('Y-m-d')])
            ->with('success', 'Correction request sent to purchase manager.');
    }

    public function approveCorrectionRequest(Request $request, PurchaserCorrectionRequest $correctionRequest): RedirectResponse
    {
        $this->ensurePurchaseManager($request);

        abort_unless($correctionRequest->status === 'pending', 400, 'This correction request is no longer pending.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $correctionRequest, $validated): void {
            $shopOrderItem = $correctionRequest->shopOrderItem()->lockForUpdate()->firstOrFail();

            $shopOrderItem->update([
                'approved_qty' => $correctionRequest->proposed_corrected_qty,
                'notes' => trim(implode("\n", array_filter([
                    $shopOrderItem->notes,
                    'Purchaser correction approved: '.($validated['review_note'] ?? 'No note'),
                ]))),
            ]);

            $correctionRequest->update([
                'status' => 'approved',
                'review_note' => $validated['review_note'] ?? null,
                'reviewer_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Correction request approved and approved qty updated.');
    }

    public function rejectCorrectionRequest(Request $request, PurchaserCorrectionRequest $correctionRequest): RedirectResponse
    {
        $this->ensurePurchaseManager($request);

        abort_unless($correctionRequest->status === 'pending', 400, 'This correction request is no longer pending.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $correctionRequest->update([
            'status' => 'rejected',
            'review_note' => $validated['review_note'] ?? null,
            'reviewer_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Correction request rejected.');
    }

    private function draftCartsForDate(int $userId, Carbon $date): Collection
    {
        return PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function findReusableDraftCart(int $userId, Carbon $date, ?int $supplierId, ?int $exceptCartId = null): ?PurchaserCart
    {
        return PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->when(
                $supplierId !== null,
                fn ($query) => $query->where('supplier_id', $supplierId),
                fn ($query) => $query->whereNull('supplier_id'),
            )
            ->when($exceptCartId !== null, fn ($query) => $query->whereKeyNot($exceptCartId))
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function assignSupplierToCart(PurchaserCart $cart, Supplier $supplier): PurchaserCart
    {
        if ($cart->status !== 'draft') {
            return $cart;
        }

        $targetCart = $this->findReusableDraftCart(
            userId: (int) $cart->user_id,
            date: $cart->business_date,
            supplierId: (int) $supplier->id,
            exceptCartId: (int) $cart->id,
        );

        if (! $targetCart instanceof PurchaserCart) {
            return $cart;
        }

        $targetCart->update(['supplier_id' => $supplier->id]);

        return $this->mergeDraftCartIntoTarget($cart, $targetCart);
    }

    private function mergeDraftCartIntoTarget(PurchaserCart $sourceCart, PurchaserCart $targetCart): PurchaserCart
    {
        if ($sourceCart->is($targetCart)) {
            return $targetCart;
        }

        return DB::transaction(function () use ($sourceCart, $targetCart): PurchaserCart {
            $sourceCart->loadMissing('items.product');
            $targetCart->loadMissing('items.product');

            $productIds = $sourceCart->items->pluck('product_id')->unique()->all();

            $approvedQuantities = ShopOrderItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('order', function ($query) use ($sourceCart): void {
                    $query->whereDate('business_date', $sourceCart->business_date)->where('state', 'approved');
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(approved_qty) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            $alreadySubmittedQuantities = PurchaserCartItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('cart', function ($query) use ($sourceCart, $targetCart): void {
                    $query->whereDate('business_date', $sourceCart->business_date)
                        ->where('status', 'submitted')
                        ->whereKeyNot($targetCart->id);
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            foreach ($sourceCart->items as $sourceItem) {
                $targetItem = $targetCart->items()->where('product_id', $sourceItem->product_id)->first();
                $mergedQuantity = $sourceItem->quantity + (float) ($targetItem?->quantity ?? 0);

                $approvedQty = (float) ($approvedQuantities[$sourceItem->product_id] ?? 0);
                $submittedQty = (float) ($alreadySubmittedQuantities[$sourceItem->product_id] ?? 0);
                $remainingApproved = max(0.0, $approvedQty - $submittedQty);

                if ($targetItem instanceof PurchaserCartItem) {
                    $unitPrice = (float) ($sourceItem->unit_price > 0 ? $sourceItem->unit_price : $targetItem->unit_price);

                    $targetItem->update([
                        'quantity' => $mergedQuantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($mergedQuantity * $unitPrice, 2),
                        'is_extra_purchase' => $mergedQuantity > $remainingApproved,
                        'notes' => $targetItem->notes ?: $sourceItem->notes,
                    ]);

                    $sourceItem->delete();

                    continue;
                }

                $sourceItem->update([
                    'purchaser_cart_id' => $targetCart->id,
                    'quantity' => $mergedQuantity,
                    'line_total' => round($mergedQuantity * (float) $sourceItem->unit_price, 2),
                    'is_extra_purchase' => $mergedQuantity > $remainingApproved,
                ]);
            }

            $targetCart->touch();
            $sourceCart->delete();

            return $targetCart->fresh(['supplier', 'items.product.category', 'goodsReceived']);
        });
    }

    private function mergeGroupDraftCarts(PurchaserCart $cart): Collection
    {
        return PurchaserCart::query()
            ->where('user_id', $cart->user_id)
            ->whereDate('business_date', $cart->business_date)
            ->where('status', 'draft')
            ->when(
                $cart->supplier_id !== null,
                fn ($query) => $query->where('supplier_id', $cart->supplier_id),
                fn ($query) => $query->whereNull('supplier_id'),
            )
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function buildDraftMergeSuggestions(Collection $draftOrders): Collection
    {
        return $draftOrders
            ->groupBy(fn (PurchaserCart $cart): string => $cart->supplier_id !== null ? 'supplier:'.$cart->supplier_id : 'pending')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group): array {
                /** @var PurchaserCart $targetCart */
                $targetCart = $group->sortByDesc('updated_at')->first();

                return [
                    'target_cart' => $targetCart,
                    'count' => $group->count(),
                    'label' => $targetCart->supplier?->name ?: 'draft carts',
                ];
            })
            ->values();
    }

    private function ownedCart(Request $request, PurchaserCart $cart, array $statuses): PurchaserCart
    {
        return PurchaserCart::query()
            ->whereKey($cart->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', $statuses)
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->firstOrFail();
    }

    private function redirectAfterMutation(string $returnTo, Carbon $date, PurchaserCart $cart, string $message): RedirectResponse
    {
        return match ($returnTo) {
            'bill' => redirect()->route('purchaser.bill', ['cart' => $cart, 'date' => $date->format('Y-m-d')])->with('success', $message),
            'cart' => redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])->with('success', $message),
            'vendors' => redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])->with('success', $message),
            default => redirect()->route('purchaser.daily', array_filter([
                'date' => $date->format('Y-m-d'),
                'chip' => request()->input('chip'),
                'search' => request()->input('search'),
            ]))->with('success', $message),
        };
    }

    private function buildDailySummary(Carbon $date, array $frequentProductIds): Collection
    {
        $approvedItems = ShopOrderItem::query()
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)->where('state', 'approved');
            })
            ->with(['product.category', 'order.shop', 'order'])
            ->get();

        $draftCartItems = PurchaserCartItem::query()
            ->whereHas('cart', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)->where('status', 'draft');
            })
            ->with('cart.user')
            ->get()
            ->groupBy(fn ($item) => $item->product_id.'_'.$item->cart->business_date->timezone(config('app.timezone'))->format('Y-m-d'));

        $submittedQuantities = PurchaserCartItem::query()
            ->whereHas('cart', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)->where('status', 'submitted');
            })
            ->with('cart')
            ->get()
            ->groupBy(fn ($item) => $item->product_id.'_'.$item->cart->business_date->timezone(config('app.timezone'))->format('Y-m-d'))
            ->map(fn ($group) => (float) $group->sum('quantity'));

        return $approvedItems
            ->groupBy(fn (ShopOrderItem $item) => $item->product_id.'_'.$item->order->business_date->timezone(config('app.timezone'))->format('Y-m-d'))
            ->map(function (Collection $items, string $key) use ($draftCartItems, $submittedQuantities, $frequentProductIds, $date): ?array {
                [$productId, $itemDateStr] = explode('_', $key);
                $itemDate = Carbon::parse($itemDateStr);

                /** @var ShopOrderItem $firstItem */
                $firstItem = $items->first();
                $product = $firstItem->product;

                $productDraftItems = $draftCartItems->get($key) ?? collect();
                $draftQty = (float) $productDraftItems->sum('quantity');
                $draftPurchasers = $productDraftItems
                    ->groupBy('cart.user_id')
                    ->map(function ($itemsByPurchaser) use ($product) {
                        $user = $itemsByPurchaser->first()->cart->user;
                        $purchaserQty = (float) $itemsByPurchaser->sum('quantity');
                        $formattedQty = $product->unit === 'kg' ? number_format($purchaserQty, 1) : number_format($purchaserQty, 0);

                        return $user ? "{$user->name} ({$formattedQty} {$product->unit})" : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                $boughtQty = (float) ($submittedQuantities->get($key) ?? 0);
                $totalApprovedQty = (float) $items->sum('approved_qty');
                $remainingQty = max(0, $totalApprovedQty - $boughtQty);

                if ($itemDate->lt($date) && $remainingQty <= 0) {
                    return null;
                }

                $categoryName = (string) ($product->category?->name ?? '');

                $quantityBuckets = $items
                    ->groupBy(fn (ShopOrderItem $item): string => $this->normalizeBucketKey((float) $item->approved_qty))
                    ->map(function (Collection $bucketItems, string $bucketKey) use ($firstItem): array {
                        $bucketQuantity = (float) $bucketItems->first()->approved_qty;

                        return [
                            'quantity' => $bucketQuantity,
                            'formatted' => $this->formatBucketLabel($bucketQuantity, $firstItem->unit),
                            'count' => $bucketItems->count(),
                        ];
                    })
                    ->sortBy('quantity')
                    ->values()
                    ->all();

                return [
                    'product_id' => (int) $productId,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'category_name' => $categoryName,
                    'is_frequent' => in_array((int) $productId, $frequentProductIds, true),
                    'total_approved_qty' => $totalApprovedQty,
                    'bought_qty' => $boughtQty,
                    'draft_qty' => $draftQty,
                    'draft_purchasers' => $draftPurchasers,
                    'remaining_qty' => $remainingQty,
                    'quantity_buckets' => $quantityBuckets,
                    'order_date' => $itemDate,
                    'shop_details' => $items->map(fn (ShopOrderItem $item): array => [
                        'shop_order_item_id' => $item->id,
                        'shop_name' => $item->order->shop->name,
                        'approved_qty' => (float) $item->approved_qty,
                        'unit' => $item->unit,
                        'order_number' => $item->order->order_number,
                        'notes' => $item->notes,
                    ])->sortBy('shop_name')->values()->all(),
                    'search_index' => strtolower(implode(' ', [
                        $product->name,
                        $product->sku,
                        $categoryName,
                    ])),
                ];
            })
            ->filter()
            ->sortBy(fn (array $item): string => Product::sortableSku((string) $item['sku']).'_'.$item['order_date']->format('Y-m-d'))
            ->values();
    }

    private function filterProductsForChip(Collection $items, string $selectedChip, string $search, array $frequentProductIds): Collection
    {
        return $items->filter(function ($item) use ($selectedChip, $search, $frequentProductIds): bool {
            $categoryName = is_array($item)
                ? (string) ($item['category_name'] ?? '')
                : (string) ($item->category?->name ?? '');
            $productId = is_array($item) ? (int) ($item['product_id'] ?? 0) : (int) $item->id;
            $searchIndex = is_array($item)
                ? (string) ($item['search_index'] ?? '')
                : strtolower(implode(' ', [$item->name, $item->sku, $categoryName]));

            $matchesChip = match ($selectedChip) {
                'Frequent' => in_array($productId, $frequentProductIds, true),
                'All' => true,
                default => $categoryName === $selectedChip,
            };

            if (! $matchesChip) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            return str_contains($searchIndex, strtolower($search));
        })->values();
    }

    private function frequentProductIds(int $userId): array
    {
        $cartItems = PurchaserCartItem::query()
            ->selectRaw('product_id, COUNT(*) as usage_count')
            ->whereHas('cart', function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->whereDate('business_date', '>=', now()->subDays(14)->toDateString());
            })
            ->groupBy('product_id')
            ->orderByDesc('usage_count')
            ->limit(12)
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        if ($cartItems !== []) {
            return $cartItems;
        }

        return Product::query()
            ->whereHas('category', function ($query): void {
                $query->whereIn('name', ['Supply', 'VEG']);
            })
            ->ordered()
            ->limit(12)
            ->pluck('id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();
    }

    private function resolveSubmissionSupplier(Request $request): Supplier
    {
        $supplierId = $request->integer('supplier_id');

        if ($supplierId > 0) {
            return Supplier::query()->findOrFail($supplierId);
        }

        return Supplier::query()->create([
            'name' => $request->string('vendor_name')->toString(),
            'type' => $request->string('vendor_type')->toString() ?: 'Vendor',
            'category' => 'market',
            'is_default_purchase' => false,
            'contact' => (string) $request->input('vendor_mobile_number', ''),
            'location' => $request->input('vendor_location'),
            'mobile_number' => $request->input('vendor_mobile_number'),
            'payment_terms' => $request->input('payment_terms', 'Cash'),
            'preferred_payment_method' => $request->input('preferred_payment_method', $request->string('payment_method')->toString() ?: 'Cash'),
            'credit_approved' => false,
            'credit_terms' => null,
            'quality_score' => 100,
        ]);
    }

    /**
     * @param  array<int, array{cart_item: PurchaserCartItem, quantity: float, unit_price: float}>  $lines
     * @return array{purchase_order: PurchaseOrder, grn: GoodsReceived}
     */
    private function createPurchaseDocumentsFromLines(Supplier $supplier, Carbon $date, int $userId, array $lines, bool $isExtra, string $notes, ?int $cartId = null): array
    {
        $purchaseOrder = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cartId,
            'po_number' => $this->generatePurchaseOrderNumber($date),
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => $date,
            'created_by' => $userId,
            'notes' => $notes,
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchaser_cart_id' => $cartId,
            'grn_number' => $this->generateGrnNumber($date),
            'status' => 'pending_approval',
            'received_by' => $userId,
            'received_at' => $date,
            'notes' => $notes,
            'is_extra' => $isExtra,
        ]);

        foreach ($lines as $line) {
            $cartItem = $line['cart_item'];
            $quantity = $line['quantity'];

            $purchaseOrderItem = $purchaseOrder->items()->create([
                'product_id' => $cartItem->product_id,
                'purchase_unit' => $cartItem->product->unit,
                'quantity' => $quantity,
                'unit_price' => $line['unit_price'],
                'price_basis' => $cartItem->product->unit === 'kg' ? 'per_kg' : 'per_unit',
            ]);

            $grn->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'product_id' => $cartItem->product_id,
                'received_qty' => $quantity,
                'variance' => 0,
            ]);
        }

        return [
            'purchase_order' => $purchaseOrder,
            'grn' => $grn,
        ];
    }

    private function remainingApprovedQuantityForProduct(Carbon $date, int $productId, int $currentCartId): float
    {
        $approvedQuantity = (float) ShopOrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)->where('state', 'approved');
            })
            ->sum('approved_qty');

        $alreadySubmittedQuantity = (float) PurchaserCartItem::query()
            ->where('product_id', $productId)
            ->whereHas('cart', function ($query) use ($date, $currentCartId): void {
                $query->whereDate('business_date', $date)
                    ->where('status', 'submitted')
                    ->whereKeyNot($currentCartId);
            })
            ->sum('quantity');

        return max(0, $approvedQuantity - $alreadySubmittedQuantity);
    }

    private function resolveBusinessDate(Request $request): Carbon|RedirectResponse
    {
        $operationalDate = $this->businessDayService->operationalDate();
        PurchaserCart::cancelOverdueCartsAndOrders($operationalDate);
        $dateInput = $request->input('date');

        if ($dateInput) {
            $date = Carbon::parse($dateInput)->startOfDay();

            $routeName = $request->route()?->getName();
            if (in_array($routeName, ['purchaser.daily', 'purchaser.vendors', 'purchaser.bulk-buy', 'purchaser.bulk-buy.details'], true)) {
                if (! $date->isSameDay($operationalDate)) {
                    $fallbackDate = $operationalDate->format('Y-m-d');

                    return redirect()
                        ->route($routeName, array_filter([
                            'date' => $fallbackDate,
                            'chip' => $request->input('chip'),
                            'search' => $request->input('search'),
                            'tab' => $request->input('tab'),
                        ]))
                        ->with('error', 'Only the active business day order can be viewed/processed.');
                }
            }

            if (! $this->businessDayService->isSelectableDate($date)) {
                $fallbackDate = $operationalDate->format('Y-m-d');

                return redirect()
                    ->route('purchaser.daily', [
                        'date' => $fallbackDate,
                        'chip' => $request->input('chip'),
                        'search' => $request->input('search'),
                    ])
                    ->with('error', 'That purchase date is not available right now. Showing the active business day instead.');
            }

            return $date;
        }

        return $operationalDate;
    }

    private function buildPurchaseDocumentNotes(PurchaserCart $cart, string $notes): string
    {
        return trim(implode("\n", array_filter([
            trim($notes) !== '' ? trim($notes) : 'Generated from purchaser vendor cart.',
            'Cart: '.$cart->cart_number,
        ])));
    }

    /**
     * @return array{show: bool, same_day: bool, overdue_count: int, vendor_missing_count: int, bill_pending_count: int, warehouse_pending_count: int, pending_total_count: int, overdue_carts: Collection<int, PurchaserCart>, operational_date: string}
     */
    private function filterOverdueCartsForPurchaser(Collection $overdueCarts, array $batchState): Collection
    {
        return $overdueCarts->filter(function (PurchaserCart $cart) use ($batchState): bool {
            if ($cart->status === 'draft') {
                return true;
            }
            $isConfirmed = $this->isWarehouseConfirmed($batchState[(int) $cart->id] ?? []);
            if (! $isConfirmed) {
                return false; // Skip warehouse receipt pending
            }

            return $this->cartHasPaymentPending($cart);
        });
    }

    private function buildDeadlineAlert(int $userId, Carbon $selectedDate): array
    {
        $operationalDate = $this->businessDayService->operationalDate();
        $calendarDate = $this->businessDayService->currentCalendarDate();
        $sameDayCarts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $calendarDate)
            ->with(['supplier', 'items.product.category', 'goodsReceived', 'purchaseInvoice'])
            ->orderByDesc('updated_at')
            ->get();
        $overdueCarts = $this->overdueCartsForUser($userId);
        $overdueBatchState = $this->relatedBatchStateForCarts($overdueCarts);
        $overdueCarts = $this->filterOverdueCartsForPurchaser($overdueCarts, $overdueBatchState);

        $vendorMissingCount = $sameDayCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status === 'draft' && $cart->items->isNotEmpty() && $cart->supplier_id === null)
            ->count();
        $billPendingCount = $sameDayCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status === 'draft' && $cart->items->isNotEmpty() && $cart->supplier_id !== null)
            ->count();
        $warningOpen = $this->businessDayService->isWarningWindowOpen($calendarDate) && $selectedDate->isSameDay($calendarDate);

        return [
            'show' => $overdueCarts->isNotEmpty() || ($warningOpen && ($vendorMissingCount > 0 || $billPendingCount > 0)),
            'same_day' => $warningOpen,
            'overdue_count' => $overdueCarts->count(),
            'vendor_missing_count' => $vendorMissingCount,
            'bill_pending_count' => $billPendingCount,
            'warehouse_pending_count' => 0,
            'pending_total_count' => $overdueCarts->count() + $vendorMissingCount + $billPendingCount,
            'overdue_carts' => $overdueCarts,
            'operational_date' => $operationalDate->format('Y-m-d'),
        ];
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>
     */
    private function relatedBatchStateForCarts(Collection $carts): array
    {
        return $carts->mapWithKeys(function (PurchaserCart $cart): array {
            $batches = $this->relatedStockBatchesForCart($cart);
            $totalBatches = $batches->count();
            $confirmedBatches = $batches->where('warehouse_receive_pending', false)->count();

            return [
                (int) $cart->id => [
                    'warehouse_confirmed' => $totalBatches > 0 && $confirmedBatches === $totalBatches,
                    'total_batches' => $totalBatches,
                    'confirmed_batches' => $confirmedBatches,
                ],
            ];
        })->all();
    }

    /**
     * @param  array{warehouse_confirmed?: bool}  $batchState
     */
    private function isWarehouseConfirmed(array $batchState): bool
    {
        return (bool) ($batchState['warehouse_confirmed'] ?? false);
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, array{label: string, tone: string}>
     */
    private function statusBadgesForCarts(Collection $carts, array $relatedBatchState): array
    {
        $operationalDate = $this->businessDayService->operationalDate();

        return $carts->mapWithKeys(function (PurchaserCart $cart) use ($relatedBatchState, $operationalDate): array {
            $batchState = $relatedBatchState[(int) $cart->id] ?? [];
            $isOverdue = $cart->business_date->lt($operationalDate) && $this->isCartOperationallyUnresolved($cart, $batchState);

            if ($isOverdue) {
                return [(int) $cart->id => ['label' => 'Overdue', 'tone' => 'bg-rose-100 text-rose-700']];
            }

            if ($cart->status === 'draft') {
                $label = $cart->supplier_id === null ? 'Vendor Pending' : 'Bill Pending';

                return [(int) $cart->id => ['label' => $label, 'tone' => 'bg-amber-100 text-amber-700']];
            }

            if (! $this->cartHasPaymentPending($cart)) {
                return [(int) $cart->id => ['label' => 'Completed', 'tone' => 'bg-emerald-100 text-emerald-700']];
            }

            if (! $this->isWarehouseConfirmed($batchState)) {
                return [(int) $cart->id => ['label' => 'Processing', 'tone' => 'bg-teal-100 text-teal-700']];
            }

            return [(int) $cart->id => ['label' => 'Payment Pending', 'tone' => 'bg-amber-100 text-amber-700']];
        })->all();
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, string>
     */
    private function relatedReceiptNotesForCarts(Collection $carts): array
    {
        return $carts->mapWithKeys(function (PurchaserCart $cart): array {
            $notes = $this->relatedGoodsReceiptsForCart($cart)
                ->pluck('notes')
                ->filter(fn (?string $note): bool => filled($note))
                ->unique()
                ->implode("\n");

            return [(int) $cart->id => $notes];
        })->all();
    }

    /**
     * @return Collection<int, PurchaserCart>
     */
    private function overdueCartsForUser(int $userId): Collection
    {
        $operationalDate = $this->businessDayService->operationalDate();
        $carts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '<', $operationalDate)
            ->with(['supplier', 'items.product.category', 'goodsReceived', 'purchaseInvoice'])
            ->orderBy('business_date')
            ->orderByDesc('updated_at')
            ->get();
        $batchState = $this->relatedBatchStateForCarts($carts);

        return $carts
            ->filter(fn (PurchaserCart $cart): bool => $this->isCartOperationallyUnresolved($cart, $batchState[(int) $cart->id] ?? []))
            ->values();
    }

    /**
     * @param  array{warehouse_confirmed?: bool}  $batchState
     */
    private function isCartOperationallyUnresolved(PurchaserCart $cart, array $batchState): bool
    {
        if ($cart->status === 'cancelled') {
            return false;
        }

        if ($cart->status === 'draft') {
            return $cart->items->isNotEmpty();
        }

        if (! $this->isWarehouseConfirmed($batchState)) {
            return true;
        }

        return $this->cartHasPaymentPending($cart);
    }

    private function cartHasPaymentPending(PurchaserCart $cart): bool
    {
        if ($cart->purchaseInvoice) {
            $paymentStatus = (string) ($cart->purchaseInvoice->payment_status ?: $cart->payment_status ?: 'unpaid');
            $paymentMethod = (string) ($cart->purchaseInvoice->payment_method ?: $cart->payment_method ?: 'Cash');
            $remainingBalance = $this->invoiceRemainingBalance($cart->purchaseInvoice);

            if (strcasecmp($paymentMethod, 'Credit') === 0) {
                return $paymentStatus !== 'paid' || $remainingBalance > 0;
            }

            return $remainingBalance > 0;
        }

        $paymentStatus = (string) ($cart->payment_status ?: 'unpaid');

        return in_array($paymentStatus, ['unpaid', 'partial', 'credit_pending_approval'], true);
    }

    /**
     * @param  array{warehouse_confirmed?: bool}  $batchState
     * @return array{label:string,tone:string,unresolved:bool,payment_pending:bool}
     */
    private function cartOperationalState(PurchaserCart $cart, array $batchState): array
    {
        if ($cart->status === 'draft') {
            return [
                'label' => $cart->supplier_id === null ? 'Vendor Pending' : 'Bill Pending',
                'tone' => 'bg-amber-100 text-amber-700',
                'unresolved' => $cart->items->isNotEmpty(),
                'payment_pending' => false,
            ];
        }

        if (! $this->isWarehouseConfirmed($batchState)) {
            return [
                'label' => 'Receipt Pending',
                'tone' => 'bg-teal-100 text-teal-700',
                'unresolved' => true,
                'payment_pending' => false,
            ];
        }

        if ($this->cartHasPaymentPending($cart)) {
            return [
                'label' => 'Payment Pending',
                'tone' => 'bg-amber-100 text-amber-700',
                'unresolved' => true,
                'payment_pending' => true,
            ];
        }

        return [
            'label' => 'Completed',
            'tone' => 'bg-emerald-100 text-emerald-700',
            'unresolved' => false,
            'payment_pending' => false,
        ];
    }

    /**
     * @param  Collection<int, PurchaserCart>  $sameDayAssignedDrafts
     * @param  Collection<int, PurchaserCart>  $overdueCarts
     * @param  array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>  $overdueBatchState
     */
    private function supplierPendingHubIssueCount(
        Supplier $supplier,
        Collection $sameDayAssignedDrafts,
        Collection $overdueCarts,
        array $overdueBatchState,
        string $selectedTab,
    ): int {
        if ($selectedTab === 'credit') {
            return $overdueCarts
                ->filter(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id)
                ->filter(fn (PurchaserCart $cart): bool => $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? []))
                ->filter(fn (PurchaserCart $cart): bool => $this->cartHasPaymentPending($cart))
                ->filter(fn (PurchaserCart $cart): bool => ($cart->purchaseInvoice?->payment_method ?: $cart->payment_method) === 'Credit')
                ->count();
        }

        $sameDayCount = $sameDayAssignedDrafts
            ->filter(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id)
            ->count();

        $overdueCount = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id)
            ->filter(function (PurchaserCart $cart) use ($overdueBatchState): bool {
                if ($cart->status === 'draft') {
                    return true;
                }

                if (! $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? [])) {
                    return true;
                }

                $paymentMethod = $cart->purchaseInvoice?->payment_method ?: $cart->payment_method;

                return $paymentMethod !== 'Credit' && $this->cartHasPaymentPending($cart);
            })
            ->count();

        return $sameDayCount + $overdueCount;
    }

    /**
     * @return Collection<int, GoodsReceived>
     */
    private function relatedGoodsReceiptsForCart(PurchaserCart $cart): Collection
    {
        return GoodsReceived::query()
            ->when(
                $cart->goods_received_id !== null,
                fn ($query) => $query->whereKey($cart->goods_received_id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orWhere('notes', 'like', '%Cart: '.$cart->cart_number.'%')
            ->orderByDesc('received_at')
            ->get();
    }

    private function buildReceiptDiscrepancySummary(?GoodsReceived $goodsReceived): ?string
    {
        if (! $goodsReceived) {
            return null;
        }

        $lines = $goodsReceived->items
            ->filter(fn ($item): bool => abs((float) $item->variance) > 0.0001)
            ->map(function ($item): string {
                $productName = $item->product?->name
                    ?? $item->purchaseOrderItem?->product?->name
                    ?? 'Item';
                $unit = $item->product?->unit
                    ?? $item->purchaseOrderItem?->product?->unit
                    ?? 'qty';
                $variance = (float) $item->variance;
                $direction = $variance < 0 ? 'Short' : 'Extra';

                return $productName.': '.$direction.' '.number_format(abs($variance), 2).' '.$unit;
            })
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->implode("\n");
    }

    /**
     * @return Collection<int, StockBatch>
     */
    private function relatedStockBatchesForCart(PurchaserCart $cart): Collection
    {
        $grnNumbers = $this->relatedGoodsReceiptsForCart($cart)
            ->pluck('grn_number')
            ->filter()
            ->values();

        if ($grnNumbers->isEmpty()) {
            return collect();
        }

        $query = StockBatch::query();

        foreach ($grnNumbers as $index => $grnNumber) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $query->{$method}('notes', 'like', '%Auto-created from GRN: '.$grnNumber.'%');
        }

        return $query->orderByDesc('received_at')->get();
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @return Collection<int, array{key:string,label:string,description:string,count:int,empty:string,rows:Collection<int, array{supplier:Supplier,cart:PurchaserCart,route:string,button:string,popup_title:string,popup_message:string}>}>
     */
    private function buildSupplierIssueSections(int $userId, Carbon $selectedDate, Collection $suppliers, string $selectedTab): Collection
    {
        $sameDayAssignedDrafts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $selectedDate)
            ->where('status', 'draft')
            ->whereNotNull('supplier_id')
            ->with(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (PurchaserCart $cart): bool => $cart->items->isNotEmpty());

        $overdueCarts = $this->overdueCartsForUser($userId)->loadMissing(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived']);
        $overdueBatchState = $this->relatedBatchStateForCarts($overdueCarts);

        $overdueDraftRows = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status === 'draft')
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->map(function (PurchaserCart $cart): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => route('purchaser.bill', ['cart' => $cart, 'date' => $cart->business_date->format('Y-m-d')]),
                    'button' => 'Open Bill Page',
                    'action_type' => 'link',
                    'popup_title' => 'Finish overdue cart',
                    'popup_message' => "This older business-day cart still needs bill processing for {$cart->cart_number}.",
                ];
            })
            ->values();

        $receiptPendingRows = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status !== 'draft')
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->filter(fn (PurchaserCart $cart): bool => ! $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? []))
            ->map(function (PurchaserCart $cart): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => $cart->purchaseInvoice ? route('purchaser.invoices.show', $cart->purchaseInvoice) : route('purchaser.history', ['date' => $cart->business_date->format('Y-m-d')]),
                    'button' => 'View Bill',
                    'action_type' => 'link',
                    'popup_title' => 'Receipt still pending',
                    'popup_message' => "Warehouse receipt confirmation is still pending for {$cart->cart_number}.",
                ];
            })
            ->values();

        $billPendingRows = $sameDayAssignedDrafts
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->map(function (PurchaserCart $cart): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => route('purchaser.bill', ['cart' => $cart, 'date' => $cart->business_date->format('Y-m-d')]),
                    'button' => 'Open Bill Page',
                    'action_type' => 'link',
                    'popup_title' => 'Finish bill processing',
                    'popup_message' => "Finish bill processing from the bill page for {$cart->cart_number}.",
                ];
            })
            ->values();

        $paymentFollowUpRows = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->filter(fn (PurchaserCart $cart): bool => $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? []))
            ->filter(fn (PurchaserCart $cart): bool => $this->cartHasPaymentPending($cart))
            ->filter(function (PurchaserCart $cart) use ($selectedTab): bool {
                $paymentMethod = $cart->purchaseInvoice?->payment_method ?: $cart->payment_method;

                if ($selectedTab === 'credit') {
                    return $paymentMethod === 'Credit';
                }

                return $paymentMethod !== 'Credit';
            })
            ->map(function (PurchaserCart $cart) use ($selectedDate, $selectedTab): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => '',
                    'button' => 'Update Payment',
                    'action_type' => 'update_payment',
                    'invoice' => [
                        'id' => $cart->purchaseInvoice?->id,
                        'number' => $cart->purchaseInvoice?->invoice_number,
                        'supplier' => $cart->supplier?->name,
                        'amount' => (float) ($cart->purchaseInvoice?->amount ?? 0),
                        'discountAmount' => (float) ($cart->purchaseInvoice?->discount_amount ?? 0),
                        'paidAmount' => (float) ($cart->purchaseInvoice?->paid_amount ?? 0),
                        'paymentMethod' => $cart->purchaseInvoice?->payment_method ?: 'Cash',
                        'paymentNote' => $cart->purchaseInvoice?->payment_note,
                        'paymentDetails' => $cart->purchaseInvoice?->payment_details,
                        'creditApproved' => (bool) $cart->supplier?->credit_approved,
                    ],
                    'payment_route' => $cart->purchaseInvoice ? route('purchaser.invoices.payment', $cart->purchaseInvoice) : null,
                    'popup_title' => 'Resolve payment follow-up',
                    'popup_message' => $selectedTab === 'credit'
                        ? "Credit follow-up is still open for {$cart->cart_number}."
                        : "Payment settlement is still open for {$cart->cart_number}.",
                    'vendor_route' => route('purchaser.suppliers.show', ['supplier' => $cart->supplier, 'date' => $selectedDate->format('Y-m-d')]),
                ];
            })
            ->values();

        if ($selectedTab === 'credit') {
            return collect([
                [
                    'key' => 'credit',
                    'label' => 'Credit Follow-up',
                    'description' => 'Approved credit purchases still waiting for settlement or confirmation.',
                    'count' => $paymentFollowUpRows->count(),
                    'empty' => 'No open credit follow-up right now.',
                    'rows' => $paymentFollowUpRows,
                ],
            ]);
        }

        return collect([
            [
                'key' => 'bill_pending',
                'label' => 'Bill Pending',
                'description' => 'Current business-day draft carts with supplier selected but bill not processed yet.',
                'count' => $billPendingRows->count(),
                'empty' => 'No bill-pending vendor issue right now.',
                'rows' => $billPendingRows,
            ],
            [
                'key' => 'overdue_bill_pending',
                'label' => 'Overdue Bill Pending',
                'description' => 'Older business-day carts still waiting for bill processing or completion.',
                'count' => $overdueDraftRows->count(),
                'empty' => 'No overdue bill-processing issue right now.',
                'rows' => $overdueDraftRows,
            ],
            [
                'key' => 'receipt_pending',
                'label' => 'Receipt Pending',
                'description' => 'Older business-date submitted carts still waiting for warehouse receipt confirmation.',
                'count' => $receiptPendingRows->count(),
                'empty' => 'No old receipt-pending issue right now.',
                'rows' => $receiptPendingRows,
            ],
            [
                'key' => 'payment_pending',
                'label' => 'Payment Pending',
                'description' => 'Older business-date bills still waiting for payment follow-up.',
                'count' => $paymentFollowUpRows->count(),
                'empty' => 'No old payment follow-up issue right now.',
                'rows' => $paymentFollowUpRows,
            ],
        ]);
    }

    private function resolveQuickFilter(string $selectedChip): string
    {
        return in_array($selectedChip, self::QUICK_FILTERS, true) ? $selectedChip : 'All';
    }

    private function resolveDailyShareMode(string $shareMode): string
    {
        return in_array($shareMode, ['all', 'tag', 'product'], true) ? $shareMode : 'all';
    }

    /**
     * @return Collection<int, PurchaseInvoice>
     */
    private function linkedInvoicesForSupplier(Supplier $supplier): Collection
    {
        return $supplier->purchaserCarts
            ->pluck('purchaseInvoice')
            ->filter(fn ($invoice): bool => $invoice instanceof PurchaseInvoice)
            ->unique(fn (PurchaseInvoice $invoice): int|string => $invoice->getKey())
            ->values();
    }

    private function invoiceRemainingBalance(PurchaseInvoice $invoice): float
    {
        return max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
    }

    /**
     * @param  array<int, string>  $selectedTags
     * @param  array<int, int>  $selectedProductIds
     */
    private function filterDailySummaryForShare(
        Collection $dailySummary,
        string $shareMode,
        array $selectedTags,
        array $selectedProductIds,
        int $selectedProductId,
    ): Collection {
        return match ($shareMode) {
            'tag' => $dailySummary
                ->filter(fn (array $summary): bool => in_array((int) $summary['product_id'], $selectedProductIds, true))
                ->values(),
            'product' => $dailySummary
                ->filter(fn (array $summary): bool => (int) $summary['product_id'] === $selectedProductId)
                ->values(),
            default => $dailySummary->values(),
        };
    }

    private function buildDailySummaryShareText(Collection $dailySummary, Carbon $date): string
    {
        $lines = [
            '*Daily Purchase Summary*',
            $date->format('d M Y'),
            '---',
            '',
        ];

        foreach ($dailySummary as $summary) {
            $productHeader = '*'.$summary['product_name'].'*';
            $orderDate = $summary['order_date'];
            if ($orderDate->format('Y-m-d') !== $date->format('Y-m-d')) {
                $productHeader .= ' (Pending '.$orderDate->format('d M Y').')';
            }
            $lines[] = $productHeader;

            foreach ($summary['quantity_buckets'] as $bucket) {
                $lines[] = $bucket['formatted'].' x '.$bucket['count'];
            }

            $lines[] = 'Total '.$this->formatShareQuantity((float) $summary['total_approved_qty'], $summary['unit']);
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function buildCartShareText(PurchaserCart $cart, bool $includePrice, float $discountAmount = 0): string
    {
        $nameWidth = 14;
        $qtyWidth = 4;
        $rateWidth = 5;
        $totalWidth = 6;

        $lines = [
            'Green Leaf Traders - Purchase Order',
            'Date: '.$cart->business_date->format('d/m/Y').' | '.$cart->cart_number,
            '',
            '---',
        ];

        $subTotal = 0.0;
        $formattedRows = [];

        foreach ($cart->items as $item) {
            if ($includePrice) {
                $quantity = $this->formatCompactShareNumber((float) $item->quantity);
                $unitPrice = (float) $item->unit_price;
                $lineTotal = round((float) $item->quantity * $unitPrice, 2);
                $subTotal += $lineTotal;

                array_push(
                    $formattedRows,
                    ...$this->formatSharePriceRows(
                        (string) $item->product->name,
                        $quantity,
                        $this->formatCompactShareNumber($unitPrice),
                        $this->formatCompactShareNumber($lineTotal),
                        $nameWidth,
                        $qtyWidth,
                        $rateWidth,
                        $totalWidth,
                    )
                );

                continue;
            }

            foreach ($this->wrapShareProductName((string) $item->product->name, $nameWidth) as $index => $wrappedLine) {
                if ($index === 0) {
                    $lines[] = str_pad($wrappedLine, $nameWidth).' '.$this->formatShareQuantity((float) $item->quantity, $item->product->unit);

                    continue;
                }

                $lines[] = $wrappedLine;
            }
        }

        if ($includePrice) {
            $netTotal = max(0, round($subTotal - $discountAmount, 2));

            $lines[] = '```';
            $lines[] = rtrim(sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Item', 'Qty', 'Rate', 'Total'));
            $lines[] = rtrim(sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", str_repeat('-', $nameWidth), str_repeat('-', $qtyWidth), str_repeat('-', $rateWidth), str_repeat('-', $totalWidth)));
            $lines[] = '';
            foreach ($formattedRows as $formattedRow) {
                $lines[] = rtrim($formattedRow);
            }
            $lines[] = '';
            $lines[] = sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Total', '', '', $this->formatCompactShareNumber($subTotal));
            $lines[] = sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Discount', '', '', $this->formatCompactShareNumber($discountAmount));
            $lines[] = sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Net Total', '', '', $this->formatCompactShareNumber($netTotal));
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = 'Please pack and confirm.';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function formatSharePriceRows(
        string $productName,
        string $quantity,
        string $unitPrice,
        string $lineTotal,
        int $nameWidth,
        int $qtyWidth,
        int $rateWidth,
        int $totalWidth,
    ): array {
        $nameLines = $this->wrapShareProductName($productName, $nameWidth);

        if (count($nameLines) === 1) {
            return [
                sprintf(
                    "%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s",
                    $nameLines[0],
                    $quantity,
                    $unitPrice,
                    $lineTotal,
                ),
            ];
        }

        return [
            $nameLines[0],
            sprintf(
                "%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s",
                $nameLines[1],
                $quantity,
                $unitPrice,
                $lineTotal,
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function wrapShareProductName(string $productName, int $lineWidth): array
    {
        $trimmed = preg_replace('/\s+/', ' ', trim($productName)) ?? trim($productName);

        if (mb_strlen($trimmed) <= $lineWidth) {
            return [$trimmed];
        }

        $words = preg_split('/\s+/', $trimmed) ?: [$trimmed];
        $firstLine = '';
        $usedWords = 0;

        foreach ($words as $index => $word) {
            $candidate = $firstLine === '' ? $word : $firstLine.' '.$word;

            if (mb_strlen($candidate) > $lineWidth) {
                break;
            }

            $firstLine = $candidate;
            $usedWords = $index + 1;
        }

        if ($firstLine === '') {
            return [$this->truncateShareProductName($trimmed, $lineWidth)];
        }

        $secondLine = trim(implode(' ', array_slice($words, $usedWords)));

        if ($secondLine === '') {
            return [$firstLine];
        }

        return [
            $firstLine,
            $this->truncateShareProductName($secondLine, $lineWidth),
        ];
    }

    private function truncateShareProductName(string $productName, int $maxLength = 14): string
    {
        $trimmed = trim($productName);

        if (mb_strlen($trimmed) <= $maxLength) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, max(1, $maxLength - 1))).'.';
    }

    private function formatCompactShareNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function buildSupplierWhatsAppUrl(Supplier $supplier, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $supplier->mobile_number);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://api.whatsapp.com/send?phone='.$digits.'&text='.rawurlencode($message);
    }

    private function resolvePaymentStatus(string $paymentMethod, float $invoiceAmount, float $paidAmount): string
    {
        if (strcasecmp($paymentMethod, 'Credit') === 0) {
            return 'credit_pending_approval';
        }

        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $invoiceAmount) {
            return 'partial';
        }

        return 'paid';
    }

    private function generatePurchaseOrderNumber(Carbon $date): string
    {
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = 'PO-PURCH-'.$date->format('Ymd').'-'.$suffix;
        } while (PurchaseOrder::query()->where('po_number', $number)->exists());

        return $number;
    }

    private function generateGrnNumber(Carbon $date): string
    {
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = 'GRN-PURCH-'.$date->format('Ymd').'-'.$suffix;
        } while (GoodsReceived::query()->where('grn_number', $number)->exists());

        return $number;
    }

    private function normalizeBucketKey(float $quantity): string
    {
        return number_format($quantity, 3, '.', '');
    }

    private function formatBucketLabel(float $quantity, string $unit): string
    {
        $value = $this->trimTrailingZeros($quantity);

        return $unit === 'kg' ? $value.'kg' : $value;
    }

    private function formatShareQuantity(float $quantity, string $unit): string
    {
        return $this->trimTrailingZeros($quantity).' '.$unit;
    }

    private function trimTrailingZeros(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function ensurePurchaser(Request $request): void
    {
        if (! $request->user()->hasRole('purchaser')) {
            abort(403, 'Unauthorized access.');
        }

        $operationalDate = $this->businessDayService->operationalDate();
        PurchaserCart::cancelOverdueCartsAndOrders($operationalDate);
    }

    private function ensurePurchaseManager(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchase')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->can('purchasing.order.approve')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }
}
