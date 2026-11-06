<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreShopCreditNoteRequest;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Support\CashbookAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminShopReportsController extends Controller
{
    /**
     * Display the Shop Invoices Report page.
     */
    public function invoices(Request $request): View
    {
        $this->ensureAuthorized($request);

        $dateRange = $this->resolveDateRange($request);
        $shops = Shop::query()->orderBy('name')->get();

        $shopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', ''));

        $query = ShopInvoice::query()
            ->where(function ($q) use ($dateRange): void {
                $q->whereDate(DB::raw('COALESCE(shop_invoices.business_date, shop_invoices.created_at)'), '>=', $dateRange['start'])
                    ->whereDate(DB::raw('COALESCE(shop_invoices.business_date, shop_invoices.created_at)'), '<=', $dateRange['end']);
            });

        if ($shopId) {
            $query->where('shop_invoices.shop_id', $shopId);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where(function ($q) use ($status): void {
                $q->where('shop_invoices.status', $status)
                    ->orWhere('shop_invoices.payment_status', $status);
            });
        }

        if ($search !== '') {
            $query->where('shop_invoices.invoice_number', 'like', "%{$search}%");
        }

        // Summary cards calculated across all filtered invoices
        $originalTotal = (float) (clone $query)->sum('shop_invoices.final_total');
        $totalPaid = (float) (clone $query)->sum('shop_invoices.paid_amount');

        $invoiceIdsSubquery = (clone $query)->select('shop_invoices.id');

        $approvedCredits = (float) ShopCreditNote::query()
            ->whereIn('shop_invoice_id', $invoiceIdsSubquery)
            ->where('status', ShopCreditNoteStatus::Approved)
            ->where('direction', ShopCreditNoteDirection::Credit)
            ->sum('total_credit_amount');

        $approvedDebits = (float) ShopCreditNote::query()
            ->whereIn('shop_invoice_id', $invoiceIdsSubquery)
            ->where('status', ShopCreditNoteStatus::Approved)
            ->where('direction', ShopCreditNoteDirection::Debit)
            ->sum('total_credit_amount');

        $netPayable = round($originalTotal + $approvedDebits - $approvedCredits, 2);
        $outstandingBalance = round(max(0.0, $netPayable - $totalPaid), 2);

        $invoices = (clone $query)
            ->with([
                'shop',
                'creditNotes.items.invoiceItem',
                'approvedCreditNotes.items.invoiceItem',
            ])
            ->orderByDesc(DB::raw('COALESCE(shop_invoices.business_date, shop_invoices.created_at)'))
            ->orderByDesc('shop_invoices.id')
            ->paginate(25)
            ->withQueryString();

        $user = $request->user();
        $canCreateCreditNote = $user instanceof User && (
            $user->isMainAdmin() || $user->hasRole('admin') || $user->hasRole('super_admin')
        );

        return view('admin.cashbook.reports.shops.invoices', [
            'invoices' => $invoices,
            'shops' => $shops,
            'selectedShopId' => $shopId,
            'selectedStatus' => $status,
            'search' => $search,
            'timeframe' => $dateRange['timeframe'],
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'canCreateCreditNote' => $canCreateCreditNote,
            'summary' => [
                'original_total' => $originalTotal,
                'approved_credits' => $approvedCredits,
                'approved_debits' => $approvedDebits,
                'net_payable' => $netPayable,
                'total_paid' => $totalPaid,
                'outstanding_balance' => $outstandingBalance,
                'invoice_count' => (int) (clone $query)->count(),
            ],
        ]);
    }

    /**
     * Display the Shop Credit Notes Report page.
     */
    public function creditNotes(Request $request): View
    {
        $this->ensureAuthorized($request);

        $dateRange = $this->resolveDateRange($request);
        $shops = Shop::query()->orderBy('name')->get();

        $shopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;
        $status = trim((string) $request->input('status', ''));
        $direction = trim((string) $request->input('direction', ''));
        $invoiceSearch = trim((string) $request->input('invoice_search', ''));
        $search = trim((string) $request->input('search', ''));

        $query = ShopCreditNote::query()
            ->whereDate('shop_credit_notes.credit_note_date', '>=', $dateRange['start'])
            ->whereDate('shop_credit_notes.credit_note_date', '<=', $dateRange['end']);

        if ($shopId) {
            $query->where('shop_credit_notes.shop_id', $shopId);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('shop_credit_notes.status', $status);
        }

        if ($direction !== '' && $direction !== 'all') {
            $query->where('shop_credit_notes.direction', $direction);
        }

        if ($invoiceSearch !== '') {
            $query->whereHas('invoice', function ($q) use ($invoiceSearch): void {
                $q->where('invoice_number', 'like', "%{$invoiceSearch}%");
            });
        }

        if ($search !== '') {
            $query->where('shop_credit_notes.credit_note_number', 'like', "%{$search}%");
        }

        // Summary cards calculated across all filtered credit notes
        $totalNotesCount = (int) (clone $query)->count();

        $approvedCredits = (float) (clone $query)
            ->where('status', ShopCreditNoteStatus::Approved)
            ->where('direction', ShopCreditNoteDirection::Credit)
            ->sum('total_credit_amount');

        $approvedDebits = (float) (clone $query)
            ->where('status', ShopCreditNoteStatus::Approved)
            ->where('direction', ShopCreditNoteDirection::Debit)
            ->sum('total_credit_amount');

        $netAdjustment = round($approvedDebits - $approvedCredits, 2);

        $draftNotesCount = (int) (clone $query)
            ->where('status', ShopCreditNoteStatus::Draft)
            ->count();

        $creditNotes = (clone $query)
            ->with([
                'shop',
                'invoice',
                'items.product',
                'items.invoiceItem',
                'creator',
                'approver',
            ])
            ->orderByDesc('shop_credit_notes.credit_note_date')
            ->orderByDesc('shop_credit_notes.id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.cashbook.reports.shops.credit-notes', [
            'creditNotes' => $creditNotes,
            'shops' => $shops,
            'selectedShopId' => $shopId,
            'selectedStatus' => $status,
            'selectedDirection' => $direction,
            'invoiceSearch' => $invoiceSearch,
            'search' => $search,
            'timeframe' => $dateRange['timeframe'],
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'summary' => [
                'total_count' => $totalNotesCount,
                'approved_credits' => $approvedCredits,
                'approved_debits' => $approvedDebits,
                'net_adjustment' => $netAdjustment,
                'draft_count' => $draftNotesCount,
            ],
        ]);
    }

    /**
     * Resolve the active date range from request timeframe and inputs.
     *
     * @return array{timeframe: string, start: string, end: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $timeframe = (string) $request->input('timeframe', 'this_month');
        $today = today();

        if ($request->filled('start_date') && $request->filled('end_date') && ! $request->has('timeframe')) {
            $timeframe = 'custom';
        }

        return match ($timeframe) {
            'today' => [
                'timeframe' => 'today',
                'start' => $today->toDateString(),
                'end' => $today->toDateString(),
            ],
            'yesterday' => [
                'timeframe' => 'yesterday',
                'start' => $today->copy()->subDay()->toDateString(),
                'end' => $today->copy()->subDay()->toDateString(),
            ],
            'prev_month', 'previous_month' => [
                'timeframe' => 'prev_month',
                'start' => $today->copy()->subMonth()->startOfMonth()->toDateString(),
                'end' => $today->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            'custom' => [
                'timeframe' => 'custom',
                'start' => (string) $request->input('start_date', $today->copy()->startOfMonth()->toDateString()),
                'end' => (string) $request->input('end_date', $today->toDateString()),
            ],
            default => [ // 'this_month', 'monthly'
                'timeframe' => 'this_month',
                'start' => $today->copy()->startOfMonth()->toDateString(),
                'end' => $today->copy()->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * Store the selected invoice reference in session and redirect to the clean creation URL.
     */
    public function selectInvoiceForCreditNote(Request $request): RedirectResponse
    {
        $this->ensureAdminAuthorized($request);

        $invoiceId = null;
        if ($request->filled('invoice_ref')) {
            try {
                $invoiceId = (int) Crypt::decryptString((string) $request->input('invoice_ref'));
            } catch (\Throwable) {
                return redirect()
                    ->route('admin.cashbook.reports.shops.invoices')
                    ->with('error', 'Invalid invoice selection reference.');
            }
        } elseif ($request->filled('invoice_id')) {
            $invoiceId = (int) $request->input('invoice_id');
        }

        if (! $invoiceId) {
            return redirect()
                ->route('admin.cashbook.reports.shops.invoices')
                ->with('error', 'Please select an invoice to create a credit note.');
        }

        /** @var ShopInvoice|null $invoice */
        $invoice = ShopInvoice::query()->find($invoiceId);

        if (! $invoice) {
            return redirect()
                ->route('admin.cashbook.reports.shops.invoices')
                ->with('error', 'The selected invoice could not be found.');
        }

        if ($invoice->status === 'cancelled') {
            return redirect()
                ->route('admin.cashbook.reports.shops.invoices')
                ->with('error', "Invoice #{$invoice->invoice_number} is cancelled and cannot receive credit notes.");
        }

        $token = Str::random(40);
        $now = now()->timestamp;
        $selections = (array) $request->session()->get('credit_note_selections', []);

        // Prune expired selections older than 2 hours (7200 seconds)
        $selections = array_filter(
            $selections,
            fn ($entry) => is_array($entry) && isset($entry['created_at']) && ($now - (int) $entry['created_at']) < 7200
        );

        $selections[$token] = [
            'invoice_id' => $invoice->id,
            'created_at' => $now,
        ];

        $request->session()->put('credit_note_selections', $selections);
        $request->session()->flash('credit_note_pending_token', $token);

        return redirect()->route('admin.cashbook.reports.shops.credit-notes.create');
    }

    /**
     * Display the separate Credit Note creation page for an eligible invoice.
     */
    public function createCreditNote(Request $request): View|Response|RedirectResponse
    {
        $this->ensureAdminAuthorized($request);

        // If an invoice_id was directly provided (e.g. legacy bookmark or direct call),
        // capture it securely into session and redirect to remove the parameter from the browser URL.
        if ($request->filled('invoice_id')) {
            $invoiceId = (int) $request->input('invoice_id');
            /** @var ShopInvoice|null $inv */
            $inv = ShopInvoice::query()->find($invoiceId);

            if (! $inv) {
                return response()->view('admin.cashbook.reports.shops.reselect_credit_note', [
                    'message' => 'The selected invoice could not be found. Please select an invoice from the Invoices Report.',
                    'hasExpiredToken' => false,
                ], 200);
            }

            if ($inv->status === 'cancelled') {
                return response()->view('admin.cashbook.reports.shops.reselect_credit_note', [
                    'message' => "Invoice #{$inv->invoice_number} is cancelled and cannot receive credit notes.",
                    'hasExpiredToken' => false,
                ], 200);
            }

            $token = Str::random(40);
            $now = now()->timestamp;
            $selections = (array) $request->session()->get('credit_note_selections', []);
            $selections = array_filter(
                $selections,
                fn ($entry) => is_array($entry) && isset($entry['created_at']) && ($now - (int) $entry['created_at']) < 7200
            );
            $selections[$token] = [
                'invoice_id' => $inv->id,
                'created_at' => $now,
            ];
            $request->session()->put('credit_note_selections', $selections);
            $request->session()->flash('credit_note_pending_token', $token);

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.create');
        }

        $now = now()->timestamp;
        $selections = (array) $request->session()->get('credit_note_selections', []);
        $selections = array_filter(
            $selections,
            fn ($entry) => is_array($entry) && isset($entry['created_at']) && ($now - (int) $entry['created_at']) < 7200
        );
        $request->session()->put('credit_note_selections', $selections);

        // Resolve active token:
        // Priority 1: Validation error redirect old input
        // Priority 2: Explicit request input / query param (e.g. from tab sessionStorage or tests)
        // Priority 3: Explicit request header
        // Priority 4: Freshly selected pending token flash from POST select action
        // Priority 5: Single selection fallback ONLY IF exactly 1 selection exists in session (zero multi-tab ambiguity)
        $activeToken = (string) (
            old('selection_token')
            ?? $request->input('selection_token')
            ?? $request->query('selection_token')
            ?? $request->query('token')
            ?? $request->header('X-Selection-Token')
            ?? $request->session()->get('credit_note_pending_token')
            ?? (count($selections) === 1 ? array_key_first($selections) : '')
        );

        if (! $activeToken || ! isset($selections[$activeToken]['invoice_id'])) {
            $reason = ! $activeToken
                ? 'Please select an invoice from the Invoices Report to create a credit note.'
                : 'The invoice selection has expired or is invalid. Please select an invoice again from the Invoices Report.';

            return response()->view('admin.cashbook.reports.shops.reselect_credit_note', [
                'message' => $reason,
                'hasExpiredToken' => (bool) $activeToken,
            ], 200);
        }

        $invoiceId = (int) $selections[$activeToken]['invoice_id'];

        /** @var ShopInvoice|null $invoice */
        $invoice = ShopInvoice::query()
            ->with([
                'shop',
                'order',
                'items.product',
                'creditNotes.items',
            ])
            ->find($invoiceId);

        if (! $invoice) {
            unset($selections[$activeToken]);
            $request->session()->put('credit_note_selections', $selections);

            return response()->view('admin.cashbook.reports.shops.reselect_credit_note', [
                'message' => 'The selected invoice could not be found. Please select an invoice from the Invoices Report.',
                'hasExpiredToken' => true,
            ], 200);
        }

        if ($invoice->status === 'cancelled') {
            unset($selections[$activeToken]);
            $request->session()->put('credit_note_selections', $selections);

            return response()->view('admin.cashbook.reports.shops.reselect_credit_note', [
                'message' => "Invoice #{$invoice->invoice_number} is cancelled and cannot receive credit notes.",
                'hasExpiredToken' => true,
            ], 200);
        }

        $creditNoteService = app(ShopCreditNoteService::class);
        $itemRows = [];

        foreach ($invoice->items as $item) {
            $cumulative = $creditNoteService->cumulativeAdjustmentsForInvoiceItem($item);
            $origQty = (float) ($item->delivered_qty ?? $item->approved_qty ?? 0);
            $origPrice = (float) $item->unit_price;
            $origTotal = round((float) ($item->final_line_total ?? $item->line_subtotal ?? ($origQty * $origPrice)), 2);

            $itemRows[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? $item->product_name,
                'product_grade' => (string) ($item->product_grade ?? 'A'),
                'unit' => (string) ($item->price_unit ?: $item->unit),
                'original_quantity' => $origQty,
                'original_unit_price' => $origPrice,
                'original_line_total' => $origTotal,
                'effective_quantity' => (float) $cumulative['active']['effective_quantity'],
                'effective_unit_price' => $origPrice,
                'effective_line_total' => (float) $cumulative['active']['effective_line_total'],
                'remaining_creditable_quantity' => (float) $cumulative['active']['remaining_creditable_quantity'],
                'remaining_creditable_amount' => (float) $cumulative['active']['remaining_creditable_amount'],
            ];
        }

        return view('admin.cashbook.reports.shops.create_credit_note', [
            'invoice' => $invoice,
            'selectionToken' => $activeToken,
            'itemRows' => $itemRows,
            'reasons' => ShopCreditNoteReason::cases(),
            'inventoryActions' => ShopCreditNoteInventoryAction::cases(),
            'wastageReasons' => WastageReason::cases(),
            'canReturnToStock' => true,
            'canDamage' => true,
        ]);
    }

    /**
     * Store and either save as draft or finalize a credit note for an invoice.
     */
    public function storeCreditNote(
        StoreShopCreditNoteRequest $request,
        ShopCreditNoteService $creditNoteService
    ): RedirectResponse {
        $adminUser = $this->ensureAdminAuthorized($request);
        $validated = $request->validated();

        /** @var ShopInvoice $invoice */
        $invoice = ShopInvoice::query()
            ->with(['items.product', 'shop'])
            ->findOrFail($validated['invoice_id']);

        // Filter items to only those with actual revisions
        $adjustedItems = [];
        foreach ($validated['items'] as $itemData) {
            $invoiceItem = $invoice->items->firstWhere('id', (int) ($itemData['shop_invoice_item_id'] ?? 0));
            if (! $invoiceItem) {
                continue;
            }

            $cumulative = $creditNoteService->cumulativeAdjustmentsForInvoiceItem($invoiceItem);
            $currentEffectiveQty = (float) $cumulative['active']['effective_quantity'];
            $origPrice = (float) $invoiceItem->unit_price;

            $revisedQty = isset($itemData['revised_quantity']) && $itemData['revised_quantity'] !== null && $itemData['revised_quantity'] !== ''
                ? (float) $itemData['revised_quantity']
                : $currentEffectiveQty;

            $revisedPrice = isset($itemData['revised_unit_price']) && $itemData['revised_unit_price'] !== null && $itemData['revised_unit_price'] !== ''
                ? (float) $itemData['revised_unit_price']
                : $origPrice;

            if (abs($revisedQty - $currentEffectiveQty) > 0.0001 || abs($revisedPrice - $origPrice) > 0.001) {
                $lineNotes = $itemData['notes'] ?? null;
                if (! empty($itemData['damage_reason'])) {
                    $lineNotes = filled($lineNotes)
                        ? "Damage Reason: {$itemData['damage_reason']}; {$lineNotes}"
                        : "Damage Reason: {$itemData['damage_reason']}";
                }

                $adjustedItems[] = [
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'revised_quantity' => $revisedQty,
                    'revised_unit_price' => $revisedPrice,
                    'inventory_action' => $itemData['inventory_action'],
                    'damage_reason' => $itemData['damage_reason'] ?? null,
                    'notes' => $lineNotes,
                ];
            }
        }

        if (empty($adjustedItems)) {
            throw ValidationException::withMessages([
                'items' => 'No adjustments were made. Please adjust the revised quantity or unit price for at least one product row.',
            ]);
        }

        $payload = [
            'credit_note_date' => $validated['credit_note_date'],
            'reason_type' => $validated['reason_type'],
            'notes' => $validated['notes'] ?? null,
            'items' => $adjustedItems,
        ];

        $isFinalize = $validated['action'] === 'finalize';

        $creditNote = DB::transaction(function () use ($creditNoteService, $invoice, $payload, $adminUser, $isFinalize): ShopCreditNote {
            $draftNote = $creditNoteService->createDraft($invoice, $payload, $adminUser->id);

            if ($isFinalize) {
                return $creditNoteService->approve($draftNote, $adminUser, 'Created and Finalized from Shops Report');
            }

            return $draftNote;
        });

        $finalBill = $invoice->fresh(['creditNotes'])->finalBill();
        $message = $isFinalize
            ? "Credit Note {$creditNote->credit_note_number} successfully finalized! Net adjustment: ₹".number_format((float) $creditNote->total_credit_amount, 2).'. Effective Final Bill payable updated to ₹'.number_format((float) $finalBill->finalNetPayable, 2).'.'
            : "Credit Note draft {$creditNote->credit_note_number} saved successfully.";

        // Clear consumed selection token from session
        if ($request->filled('selection_token')) {
            $token = (string) $request->input('selection_token');
            $selections = (array) $request->session()->get('credit_note_selections', []);
            unset($selections[$token]);
            $request->session()->put('credit_note_selections', $selections);
            if ($request->session()->get('credit_note_pending_token') === $token) {
                $request->session()->forget('credit_note_pending_token');
            }
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes', ['search' => $creditNote->credit_note_number])
            ->with('success', $message);
    }

    /**
     * Ensure current user has permission to view reports.
     */
    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || CashbookAccess::canAccessCashbook($user)
                || $user->can(CashbookAccess::ReportsView)
            ),
            403
        );
    }

    /**
     * Ensure current user has administrator authorization.
     */
    private function ensureAdminAuthorized(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('super_admin')
            ),
            403,
            'Only authorized administrators can create or finalize credit notes.'
        );

        return $user;
    }
}
