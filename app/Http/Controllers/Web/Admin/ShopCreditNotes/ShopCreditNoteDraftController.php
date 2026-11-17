<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\ShopCreditNotes;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ShopCreditNotes\SaveShopCreditNoteDraftRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoiceItem;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\ShopInvoices\ShopCreditNoteEligibilityService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopCreditNoteDraftController extends Controller
{
    public function __construct(
        protected ShopCreditNoteService $creditNoteService,
        protected ShopCreditNoteEligibilityService $eligibilityService,
        protected ApprovedDailyPriceResolver $dailyPriceResolver,
    ) {}

    /**
     * Show the shop-first Credit Note creation form.
     */
    public function create(Request $request): View
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        $shops = Shop::query()->orderBy('name')->get(['id', 'name', 'code']);
        $selectedShopId = (int) $request->query('shop_id', 0);
        $defaultDate = today()->toDateString();

        return view('admin.cashbook.reports.shops.create_shop_credit_note', [
            'creditNote' => null,
            'isEdit' => false,
            'shops' => $shops,
            'selectedShopId' => $selectedShopId ?: null,
            'defaultDate' => $defaultDate,
            'canOverrideBusinessDate' => $this->creditNoteService->canOverrideBusinessDate($user),
            'reasons' => ShopCreditNoteReason::cases(),
            'inventoryActions' => ShopCreditNoteInventoryAction::cases(),
            'wastageReasons' => WastageReason::cases(),
            'productGrades' => ProductGrade::cases(),
        ]);
    }

    /**
     * Store a new shop-level Credit Note draft.
     */
    public function store(SaveShopCreditNoteDraftRequest $request): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        $shop = Shop::findOrFail((int) $request->input('shop_id'));

        try {
            $draft = $this->creditNoteService->createShopLevelDraft(
                shop: $shop,
                payload: $request->validated(),
                creator: $user,
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'credit_note_id' => $draft->id,
                'credit_note_number' => $draft->credit_note_number,
                'finalize_url' => route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft),
                'redirect_url' => route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $draft),
                'message' => "Draft Credit Note {$draft->credit_note_number} saved.",
            ], 201);
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $draft)
            ->with('success', "Draft Credit Note {$draft->credit_note_number} created successfully.");
    }

    /**
     * Reopen and edit an existing shop-level Credit Note draft.
     */
    public function edit(Request $request, ShopCreditNote $creditNote): View|RedirectResponse
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if (! $creditNote->isDraft()) {
            return redirect()
                ->route('admin.cashbook.reports.shops.credit-notes')
                ->with('error', "Credit Note #{$creditNote->credit_note_number} is already {$creditNote->status->value} and cannot be edited as a draft.");
        }

        if (! $creditNote->isShopLevelMode()) {
            return redirect()
                ->route('admin.cashbook.reports.shops.credit-notes')
                ->with('error', "Credit Note #{$creditNote->credit_note_number} is a legacy invoice-linked note. Use the invoice credit note screen.");
        }

        $creditNote->load([
            'shop',
            'items.product',
            'items.sourceInvoice',
            'items.invoiceItem.invoice',
        ]);

        $shops = Shop::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.cashbook.reports.shops.create_shop_credit_note', [
            'creditNote' => $creditNote,
            'isEdit' => true,
            'shops' => $shops,
            'selectedShopId' => $creditNote->shop_id,
            'defaultDate' => $creditNote->credit_note_date?->toDateString() ?? today()->toDateString(),
            'canOverrideBusinessDate' => $this->creditNoteService->canOverrideBusinessDate($user),
            'reasons' => ShopCreditNoteReason::cases(),
            'inventoryActions' => ShopCreditNoteInventoryAction::cases(),
            'wastageReasons' => WastageReason::cases(),
            'productGrades' => ProductGrade::cases(),
        ]);
    }

    /**
     * Update an existing shop-level Credit Note draft.
     */
    public function update(
        SaveShopCreditNoteDraftRequest $request,
        ShopCreditNote $creditNote
    ): JsonResponse|RedirectResponse {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if (! $creditNote->isDraft()) {
            $msg = "Credit Note #{$creditNote->credit_note_number} is already {$creditNote->status->value} and cannot be modified.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes')->with('error', $msg);
        }

        try {
            $updated = $this->creditNoteService->updateShopLevelDraft(
                creditNote: $creditNote,
                payload: $request->validated(),
                actor: $user,
            );
        } catch (ValidationException $e) {
            $isConcurrency = isset($e->errors()['concurrency']);
            $status = $isConcurrency ? 409 : 422;

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'concurrency' => $isConcurrency,
                ], $status);
            }

            throw $e;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'credit_note_id' => $updated->id,
                'credit_note_number' => $updated->credit_note_number,
                'lock_version' => $updated->lock_version,
                'expected_updated_at' => $updated->updated_at?->toISOString(),
                'finalize_url' => route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $updated),
                'message' => "Draft Credit Note {$updated->credit_note_number} updated successfully.",
            ]);
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $updated)
            ->with('success', "Draft Credit Note {$updated->credit_note_number} updated successfully.");
    }

    /**
     * Finalize an existing shop-level Credit Note draft into an approved financial credit.
     */
    public function finalize(Request $request, ShopCreditNote $creditNote): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if ($creditNote->isApproved()) {
            $msg = "Credit note #{$creditNote->credit_note_number} is already finalized.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        if (! $creditNote->isDraft()) {
            $msg = "Credit note #{$creditNote->credit_note_number} cannot be finalized because its status is '{$creditNote->status->value}'.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes')->with('error', $msg);
        }

        if (! $creditNote->isShopLevelMode()) {
            $msg = 'Legacy single-invoice credit notes cannot be finalized using the shop-level workflow.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes')->with('error', $msg);
        }

        $finalizationNote = $request->input('finalization_note');

        try {
            $finalized = $this->creditNoteService->finalizeShopLevelCreditNote(
                creditNote: $creditNote,
                actor: $user,
                finalizationNote: is_string($finalizationNote) ? $finalizationNote : null,
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'credit_note_id' => $finalized->id,
                'credit_note_number' => $finalized->credit_note_number,
                'redirect_url' => route('admin.cashbook.reports.shops.credit-notes.show', $finalized),
                'message' => "Credit note {$finalized->credit_note_number} finalized successfully.",
            ]);
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes.show', $finalized)
            ->with('success', "Credit note {$finalized->credit_note_number} finalized successfully.");
    }

    /**
     * Dynamic AJAX search for historical invoice lines for a selected shop.
     */
    public function searchLines(Request $request): JsonResponse
    {
        $this->ensureAuthorizedOfficeUser($request);

        $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'exclude_credit_note_id' => ['nullable', 'integer'],
        ]);

        $shopId = (int) $request->input('shop_id');
        $month = $request->input('month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $searchQuery = trim((string) $request->input('q', ''));
        $excludeCreditNoteId = $request->filled('exclude_credit_note_id')
            ? (int) $request->input('exclude_credit_note_id')
            : null;

        if ($month && ! $startDate && ! $endDate) {
            $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
            $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
            $startDate = $start;
            $endDate = $end;
        }

        $itemsQuery = ShopInvoiceItem::query()
            ->whereHas('invoice', function ($iq) use ($shopId, $startDate, $endDate): void {
                $iq->where('shop_id', $shopId)
                    ->finalized();
                if ($startDate) {
                    $iq->whereDate('business_date', '>=', $startDate);
                }
                if ($endDate) {
                    $iq->whereDate('business_date', '<=', $endDate);
                }
            })
            ->with([
                'invoice:id,shop_id,invoice_number,business_date,status,final_total',
                'product:id,name,sku,unit',
            ])
            ->when($searchQuery !== '', function ($q) use ($searchQuery): void {
                $q->where(function ($sub) use ($searchQuery): void {
                    $sub->where('product_name', 'like', "%{$searchQuery}%")
                        ->orWhereHas('product', function ($pq) use ($searchQuery): void {
                            $pq->where('name', 'like', "%{$searchQuery}%")
                                ->orWhere('sku', 'like', "%{$searchQuery}%");
                        })
                        ->orWhereHas('invoice', function ($iq) use ($searchQuery): void {
                            $iq->where('invoice_number', 'like', "%{$searchQuery}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->limit(50);

        $items = $itemsQuery->get();

        $results = [];
        foreach ($items as $item) {
            $adjustments = $this->eligibilityService->calculateItemAdjustments($item, $excludeCreditNoteId);
            $activeNetQtyCredited = (float) $adjustments['active']['net_quantity_credited'];
            $remainingQty = (float) $adjustments['active']['remaining_creditable_quantity'];
            $remainingAmt = (float) $adjustments['active']['remaining_creditable_amount'];

            $origQty = (float) ($item->delivered_qty ?? $item->approved_qty ?? 0.0);
            $origUnitPrice = (float) $item->unit_price;
            $origTotal = round((float) ($item->final_line_total ?? $item->line_subtotal ?? ($origQty * $origUnitPrice)), 2);

            $results[] = [
                'id' => $item->id,
                'source_shop_invoice_id' => $item->shop_invoice_id,
                'invoice_number' => $item->invoice?->invoice_number ?? "INV-{$item->shop_invoice_id}",
                'invoice_date' => $item->invoice?->business_date?->toDateString() ?? '',
                'shop_invoice_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? $item->product_name,
                'product_grade' => (string) ($item->product_grade ?? 'A'),
                'unit' => (string) ($item->price_unit ?: ($item->unit ?: ($item->product?->unit ?? 'kg'))),
                'original_quantity' => $origQty,
                'original_unit_price' => $origUnitPrice,
                'original_line_total' => $origTotal,
                'previously_credited_quantity' => $activeNetQtyCredited,
                'remaining_eligible_quantity' => $remainingQty,
                'remaining_eligible_amount' => $remainingAmt,
                'is_eligible' => ($remainingQty > 0.0001 || $remainingAmt > 0.0001),
            ];
        }

        return response()->json([
            'success' => true,
            'count' => count($results),
            'items' => $results,
        ]);
    }

    /**
     * Read-only paginated catalog search for active products in Shop Credit Note entry.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $this->ensureAuthorizedOfficeUser($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shopId = (int) $validated['shop_id'];
        $searchQuery = trim((string) ($validated['q'] ?? ''));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($validated['per_page'] ?? 25)));

        $paginator = Product::query()
            ->active()
            ->when($searchQuery !== '', function ($query) use ($searchQuery): void {
                $query->where(function ($sub) use ($searchQuery): void {
                    $sub->where('name', 'like', "%{$searchQuery}%")
                        ->orWhere('sku', 'like', "%{$searchQuery}%");
                });
            })
            ->ordered()
            ->paginate($perPage, ['id', 'name', 'sku', 'unit'], 'page', $page);

        $supportedGrades = array_map(
            static fn (ProductGrade $grade): string => $grade->value,
            ProductGrade::commercialGrades(),
        );

        $items = collect($paginator->items())
            ->map(static fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => (string) ($product->sku ?? ''),
                'unit' => (string) ($product->unit ?: 'kg'),
                'supported_grades' => $supportedGrades,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'shop_id' => $shopId,
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Read-only daily selling price lookup for a shop, product, grade, and business date.
     */
    public function dailyPrice(Request $request): JsonResponse
    {
        $this->ensureAuthorizedOfficeUser($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'grade' => ['nullable', 'string', 'max:20'],
            'business_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $gradeInput = $validated['grade'] ?? ProductGrade::GradeA->value;
        $resolvedGrade = ProductGrade::tryResolve($gradeInput);

        if (! $resolvedGrade instanceof ProductGrade || ! $resolvedGrade->isDirectGraded()) {
            throw ValidationException::withMessages([
                'grade' => 'Selected grade must be A, B, or C.',
            ]);
        }

        $businessDate = (string) ($validated['business_date'] ?? today()->toDateString());
        $shop = Shop::query()->with('priceGroup')->findOrFail((int) $validated['shop_id']);
        $product = Product::query()->findOrFail((int) $validated['product_id']);

        try {
            $resolved = $this->dailyPriceResolver->resolve($product, $shop, $businessDate, $resolvedGrade);

            return response()->json([
                'success' => true,
                'available' => true,
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'grade' => $resolved['grade'],
                'business_date' => $businessDate,
                'price' => $resolved['price'],
                'price_unit' => $resolved['price_unit'],
                'source' => $resolved['source'],
                'category_code' => $resolved['category_code'],
                'message' => null,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => true,
                'available' => false,
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'grade' => $resolvedGrade->value,
                'business_date' => $businessDate,
                'price' => null,
                'price_unit' => null,
                'source' => null,
                'category_code' => null,
                'message' => collect($e->errors())->flatten()->first()
                    ?? 'No approved daily price available for the selected date.',
            ]);
        }
    }

    /**
     * Enforce server-side authorization: only authorized office accounting roles.
     */
    protected function ensureAuthorizedOfficeUser(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('super_admin')
                || $user->hasRole('accounts')
            ),
            403,
            'Unauthorized: Only office administrative and accounts roles may access the Shop Credit Note editor.'
        );

        return $user;
    }
}
