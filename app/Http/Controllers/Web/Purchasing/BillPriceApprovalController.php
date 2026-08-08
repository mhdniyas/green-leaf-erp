<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillPriceApprovalController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizeAccess($request);

        $date = $this->resolveDate($request);
        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $search = trim($request->string('search')->toString());
        $searchProduct = trim($request->string('search_product')->toString());
        $searchShop = trim($request->string('search_shop')->toString());
        $status = strtolower(trim($request->string('status')->toString() ?: 'all'));
        $sort = $this->resolveSort($request->string('sort')->toString());
        $direction = $this->resolveDirection($request->string('direction')->toString());

        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $billCardsQuery = ShopInvoice::query()
            ->withCount('items')
            ->addSelect([
                'shop_name' => Shop::query()
                    ->select('name')
                    ->whereColumn('shops.id', 'shop_invoices.shop_id')
                    ->limit(1),
            ])
            ->with(['shop', 'items.product.category'])
            ->whereDate('business_date', $date)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('shop', fn ($shopQuery) => $shopQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('warehouse_tag', 'like', "%{$search}%"))
                        ->orWhereHas('items.product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->when($searchProduct !== '', function ($query) use ($searchProduct): void {
                $query->whereHas('items.product', fn ($productQuery) => $productQuery
                    ->where('name', 'like', "%{$searchProduct}%")
                    ->orWhere('sku', 'like', "%{$searchProduct}%"));
            })
            ->when($searchShop !== '', function ($query) use ($searchShop): void {
                $query->whereHas('shop', fn ($shopQuery) => $shopQuery
                    ->where('name', 'like', "%{$searchShop}%")
                    ->orWhere('code', 'like', "%{$searchShop}%")
                    ->orWhere('warehouse_tag', 'like', "%{$searchShop}%"));
            })
            ->whereHas('items')
            ->when($status !== 'all' && $status !== '', fn ($query) => $query->where('status', $status));

        match ($sort) {
            'invoice' => $billCardsQuery->orderBy('invoice_number', $direction),
            'total' => $billCardsQuery->orderByRaw('COALESCE(final_total, subtotal) '.strtoupper($direction))->orderBy('invoice_number'),
            'items' => $billCardsQuery->orderBy('items_count', $direction)->orderBy('invoice_number'),
            'status' => $billCardsQuery->orderBy('status', $direction)->orderBy('shop_name')->orderBy('invoice_number'),
            default => $billCardsQuery->orderBy('shop_name', $direction)->orderBy('invoice_number'),
        };

        $billCards = $billCardsQuery->get();

        $summary = [
            'bills' => $billCards->count(),
            'total' => $billCards->sum(fn (ShopInvoice $invoice): float => (float) ($invoice->final_total ?: $invoice->subtotal)),
            'items' => $billCards->sum(fn (ShopInvoice $invoice): int => $invoice->items->count()),
            'specials' => ShopDailyProductPrice::query()
                ->whereDate('business_date', $date)
                ->count(),
        ];

        return view('purchasing.purchaser.bill-price-approval', [
            'date' => $date,
            'search' => $search,
            'searchProduct' => $searchProduct,
            'searchShop' => $searchShop,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'categories' => $categories,
            'billCards' => $billCards,
            'summary' => $summary,
            'copyFromDate' => $date->copy()->subDay()->toDateString(),
            'todayShortcutDate' => $this->businessDayService->operationalDate()->toDateString(),
            'yesterdayShortcutDate' => $this->businessDayService->operationalDate()->copy()->subDay()->toDateString(),
        ]);
    }

    public function show(Request $request, ShopInvoice $invoice): View
    {
        $this->authorizeAccess($request);

        $invoice->loadMissing(['shop', 'items.product.category']);

        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $categories = $invoice->items
            ->map(fn ($item) => $item->product?->category)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $items = $invoice->items
            ->when($categoryId !== null, fn (Collection $items): Collection => $items
                ->filter(fn ($item): bool => (int) ($item->product?->category_id ?? 0) === $categoryId))
            ->sortBy(fn ($item): string => sprintf(
                '%s|%s',
                $item->product?->category?->name ?? 'No Category',
                $item->product?->name ?? $item->product_name
            ))
            ->values();

        $specialPrices = ShopDailyProductPrice::query()
            ->with(['createdBy', 'approvedBy'])
            ->whereDate('business_date', $invoice->business_date)
            ->where('shop_id', $invoice->shop_id)
            ->whereIn('product_id', $invoice->items->pluck('product_id')->filter()->unique()->values())
            ->get()
            ->keyBy('product_id');

        return view('purchasing.purchaser.bill-price-show', [
            'invoice' => $invoice,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'selectedCategory' => $categoryId ? $categories->firstWhere('id', $categoryId) : null,
            'items' => $items,
            'specialPrices' => $specialPrices,
            'visibleTotal' => $items->sum(fn ($item): float => round((float) ($item->final_line_total ?: $item->line_subtotal), 2)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'business_date' => ['required', 'date'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'selling_price' => ['required', 'numeric', 'min:0.01'],
            'price_unit' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = (int) $request->user()->id;
        $date = Carbon::parse($validated['business_date'])->toDateString();
        $payload = [
            'selling_price' => round((float) $validated['selling_price'], 2),
            'price_unit' => filled($validated['price_unit'] ?? null) ? trim((string) $validated['price_unit']) : null,
            'status' => 'draft',
            'reason' => filled($validated['reason'] ?? null) ? trim((string) $validated['reason']) : null,
        ];

        $specialPrice = ShopDailyProductPrice::query()->updateOrCreate(
            [
                'business_date' => $date,
                'shop_id' => (int) $validated['shop_id'],
                'product_id' => (int) $validated['product_id'],
            ],
            $payload + [
                'created_by' => ShopDailyProductPrice::query()
                    ->whereDate('business_date', $date)
                    ->where('shop_id', (int) $validated['shop_id'])
                    ->where('product_id', (int) $validated['product_id'])
                    ->value('created_by') ?? $userId,
                'approved_by' => null,
                'approved_at' => null,
            ]
        );

        return redirect()
            ->route('purchaser.bill-prices.index', array_filter([
                'date' => $date,
                'search' => $request->input('search'),
                'search_product' => $request->input('search_product'),
                'search_shop' => $request->input('search_shop'),
                'status' => $request->input('status'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'category_id' => $request->input('category_id'),
                'invoice_id' => $request->input('invoice_id'),
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $specialPrice->wasRecentlyCreated
                ? 'Special price saved as draft.'
                : 'Special price draft updated.');
    }

    public function updateInvoicePrices(Request $request, ShopInvoice $invoice): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess($request);

        $invoice->loadMissing(['shop', 'items.product']);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'prices' => ['required', 'array'],
            'prices.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'prices.*.selling_price' => ['nullable', 'numeric', 'min:0.01'],
            'prices.*.price_unit' => ['nullable', 'string', 'max:20'],
            'prices.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invoiceProductIds = $invoice->items->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->unique();
        $userId = (int) $request->user()->id;
        $updatedProductNames = [];
        $updated = 0;

        foreach ($validated['prices'] as $row) {
            $productId = (int) $row['product_id'];

            if (! $invoiceProductIds->contains($productId) || ! filled($row['selling_price'] ?? null)) {
                continue;
            }

            $existingCreatedBy = ShopDailyProductPrice::query()
                ->whereDate('business_date', $invoice->business_date)
                ->where('shop_id', $invoice->shop_id)
                ->where('product_id', $productId)
                ->value('created_by');

            $specialPrice = ShopDailyProductPrice::query()->updateOrCreate(
                [
                    'business_date' => $invoice->business_date->toDateString(),
                    'shop_id' => (int) $invoice->shop_id,
                    'product_id' => $productId,
                ],
                [
                    'selling_price' => round((float) $row['selling_price'], 2),
                    'price_unit' => filled($row['price_unit'] ?? null) ? trim((string) $row['price_unit']) : null,
                    'status' => 'approved',
                    'reason' => filled($row['reason'] ?? null) ? trim((string) $row['reason']) : null,
                    'created_by' => $existingCreatedBy ?? $userId,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]
            );

            $updatedProductNames[] = $specialPrice->product?->name
                ?? $invoice->items->firstWhere('product_id', $productId)?->product_name
                ?? 'item';
            $updated++;
        }

        $invoiceRepriced = false;
        $invoiceRepriceSkipped = false;

        if ($updated > 0) {
            try {
                $this->shopInvoiceService->repriceInvoice(
                    $invoice,
                    $userId,
                    'Special price auto-saved by '.$request->user()->name.' for '.$invoice->invoice_number,
                );
                $invoiceRepriced = true;
            } catch (ValidationException) {
                $invoiceRepriceSkipped = true;
            }
        }

        if ($request->wantsJson()) {
            $updatedByName = (string) $request->user()->name;
            $message = $updated > 0
                ? "Updated {$updated} item(s) as special price by {$updatedByName}."
                : 'No special prices were changed.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updated,
                'updated_product_names' => $updatedProductNames,
                'updated_by_name' => $updatedByName,
                'updated_time' => now()->format('g:i A'),
                'updated_at_formatted' => now()->format('d M Y \a\t g:i A'),
                'invoice_repriced' => $invoiceRepriced,
                'invoice_reprice_skipped' => $invoiceRepriceSkipped,
            ]);
        }

        return redirect()
            ->route('purchaser.bill-prices.show', array_filter([
                'invoice' => $invoice,
                'category_id' => $validated['category_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $updated > 0
                ? "{$updated} special price(s) approved for {$invoice->invoice_number}."
                : 'No special prices were changed.');
    }

    public function approve(Request $request, ShopDailyProductPrice $specialPrice): RedirectResponse
    {
        $this->authorizeAccess($request);

        $specialPrice->loadMissing(['shop', 'product']);

        $specialPrice->update([
            'status' => 'approved',
            'approved_by' => (int) $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->repriceMatchingInvoice($specialPrice, (int) $request->user()->id);

        return redirect()
            ->route('purchaser.bill-prices.index', array_filter([
                'date' => $specialPrice->business_date->toDateString(),
                'search' => $request->input('search'),
                'search_product' => $request->input('search_product'),
                'search_shop' => $request->input('search_shop'),
                'status' => $request->input('status'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'category_id' => $request->input('category_id'),
                'invoice_id' => $request->input('invoice_id'),
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Special price approved and applied where possible.');
    }

    public function destroy(Request $request, ShopDailyProductPrice $specialPrice): RedirectResponse
    {
        $this->authorizeAccess($request);

        $specialPrice->loadMissing(['shop', 'product']);
        $date = $specialPrice->business_date->toDateString();
        $specialPrice->delete();

        $this->repriceMatchingInvoice($specialPrice, (int) $request->user()->id);

        return redirect()
            ->route('purchaser.bill-prices.index', array_filter([
                'date' => $date,
                'search' => $request->input('search'),
                'search_product' => $request->input('search_product'),
                'search_shop' => $request->input('search_shop'),
                'status' => $request->input('status'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'category_id' => $request->input('category_id'),
                'invoice_id' => $request->input('invoice_id'),
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Special price removed. Normal pricing will be used again.');
    }

    public function copyPreviousDay(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $date = $this->resolveDate($request);
        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $previousDate = $date->copy()->subDay()->toDateString();
        $todayDate = $date->toDateString();
        $userId = (int) $request->user()->id;

        $sourcePrices = ShopDailyProductPrice::query()
            ->whereDate('business_date', $previousDate)
            ->where('status', 'approved')
            ->get();

        $copied = 0;

        foreach ($sourcePrices as $sourcePrice) {
            ShopDailyProductPrice::query()->updateOrCreate(
                [
                    'business_date' => $todayDate,
                    'shop_id' => $sourcePrice->shop_id,
                    'product_id' => $sourcePrice->product_id,
                ],
                [
                    'selling_price' => (float) $sourcePrice->selling_price,
                    'price_unit' => $sourcePrice->price_unit,
                    'status' => 'draft',
                    'reason' => $sourcePrice->reason,
                    'created_by' => $userId,
                    'approved_by' => null,
                    'approved_at' => null,
                ]
            );

            $copied++;
        }

        return redirect()
            ->route('purchaser.bill-prices.index', array_filter([
                'date' => $todayDate,
                'search' => $request->input('search'),
                'search_product' => $request->input('search_product'),
                'search_shop' => $request->input('search_shop'),
                'status' => $request->input('status'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'category_id' => $request->input('category_id'),
                'invoice_id' => $request->input('invoice_id'),
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', "{$copied} special price(s) copied from {$previousDate} as drafts.");
    }

    private function resolveSort(string $sort): string
    {
        return in_array($sort, ['shop', 'invoice', 'total', 'items', 'status'], true)
            ? $sort
            : 'shop';
    }

    private function resolveDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private function repriceMatchingInvoice(ShopDailyProductPrice $specialPrice, int $userId): void
    {
        $invoice = ShopInvoice::query()
            ->where('shop_id', $specialPrice->shop_id)
            ->whereDate('business_date', $specialPrice->business_date)
            ->first();

        if (! $invoice) {
            return;
        }

        try {
            $this->shopInvoiceService->repriceInvoice(
                $invoice,
                $userId,
                'Special price updated for '.$specialPrice->product?->name.' on '.$specialPrice->business_date->toDateString()
            );
        } catch (ValidationException) {
            // Finalized or historical-locked invoices remain unchanged.
        }
    }

    private function resolveDate(Request $request): Carbon|RedirectResponse
    {
        $dateInput = $request->input('date');

        if (! $dateInput) {
            return $this->businessDayService->operationalDate();
        }

        try {
            return Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable) {
            return redirect()->back()->withErrors(['date' => 'Invalid business date.']);
        }
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('admin')
            || $request->user()?->hasRole('purchase')
            || $request->user()?->hasRole('purchaser'),
            403,
        );
    }
}
