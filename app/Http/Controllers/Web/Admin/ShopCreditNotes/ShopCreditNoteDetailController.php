<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\ShopCreditNotes;

use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Http\Controllers\Controller;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopCreditNoteDetailController extends Controller
{
    public function __construct(
        protected ShopCreditApplicationService $applicationService,
        protected ShopFinalBillService $finalBillService,
        protected ShopCreditNoteService $creditNoteService,
    ) {}

    /**
     * Display a finalized shop-level Credit Note detail view.
     */
    public function show(Request $request, ShopCreditNote $creditNote): View|RedirectResponse
    {
        $this->ensureAuthorizedOfficeUser($request);

        if (! $creditNote->isShopLevelMode()) {
            return redirect()
                ->route('admin.cashbook.reports.shops.credit-notes')
                ->with('error', "Credit note #{$creditNote->credit_note_number} is a legacy invoice-linked note.");
        }

        $creditNote->load([
            'shop',
            'creator',
            'approvedBy',
            'voidedBy',
            'items.product',
            'items.sourceInvoice',
            'items.invoiceItem.invoice',
            'applications.invoice',
            'applications.appliedByUser',
            'applications.reversedByUser',
            'receipts.warehouse',
            'receipts.receiver',
        ]);

        $productItems = $creditNote->items->where('line_type', ShopCreditNoteLineType::ProductAdjustment)->values();
        $discountItems = $creditNote->items->where('line_type', ShopCreditNoteLineType::CommercialDiscount)->values();

        $returnCreditTotal = round((float) $productItems->where('inventory_action.value', 'return_to_stock')->sum('line_credit_amount'), 2);
        $damageCreditTotal = round((float) $productItems->where('inventory_action.value', 'write_off_wastage')->sum('line_credit_amount'), 2);
        $otherAdjustmentTotal = round((float) $productItems->whereNotIn('inventory_action.value', ['return_to_stock', 'write_off_wastage'])->sum('line_credit_amount'), 2);
        $commercialDiscountTotal = round((float) $discountItems->sum('line_credit_amount'), 2);

        $appliedCreditTotal = $creditNote->appliedCreditTotal();
        $unappliedCreditTotal = $creditNote->unappliedCreditTotal();

        $user = $request->user();
        $isAdmin = $user instanceof User && (
            (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin')
        );
        $canReverseApp = $user instanceof User && $this->applicationService->isAuthorizedToReverseApplication($user);
        $canCancelCreditNote = $user instanceof User && $this->creditNoteService->isAuthorizedToCancelCreditNote($user);
        $hasUnreconciledPhysicalReturns = $creditNote->hasUnreconciledPhysicalReturns();
        $activeApplicationsCount = $creditNote->activeApplications()->count();

        return view('admin.cashbook.reports.shops.show_shop_credit_note', [
            'creditNote' => $creditNote,
            'isAdmin' => $isAdmin,
            'productItems' => $productItems,
            'discountItems' => $discountItems,
            'returnCreditTotal' => $returnCreditTotal,
            'damageCreditTotal' => $damageCreditTotal,
            'otherAdjustmentTotal' => $otherAdjustmentTotal,
            'commercialDiscountTotal' => $commercialDiscountTotal,
            'appliedCreditTotal' => $appliedCreditTotal,
            'unappliedCreditTotal' => $unappliedCreditTotal,
            'canReverseApp' => $canReverseApp,
            'canCancelCreditNote' => $canCancelCreditNote,
            'hasUnreconciledPhysicalReturns' => $hasUnreconciledPhysicalReturns,
            'activeApplicationsCount' => $activeApplicationsCount,
        ]);
    }

    /**
     * AJAX search for unpaid finalized invoices of the same shop eligible for credit allocation.
     */
    public function outstandingInvoices(Request $request, ShopCreditNote $creditNote): JsonResponse
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if (! $creditNote->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Credit note must be approved/finalized to apply credit to invoices.',
            ], 422);
        }

        $searchQuery = trim((string) $request->input('q', ''));
        $dateFilter = trim((string) $request->input('date', ''));
        $includeAll = filter_var($request->input('include_all', false), FILTER_VALIDATE_BOOLEAN);

        $isAdmin = (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin');

        $invoicesQuery = ShopInvoice::query()
            ->where('shop_id', $creditNote->shop_id)
            ->where('status', '!=', 'cancelled')
            ->when(! ($isAdmin && ($includeAll || $searchQuery !== '' || $dateFilter !== '')), function ($q): void {
                $q->where(function ($sub): void {
                    $sub->where('balance_amount', '>', 0)
                        ->orWhereIn('status', ['finalized', 'payment_pending']);
                });
            })
            ->when($searchQuery !== '', function ($q) use ($searchQuery): void {
                $q->where('invoice_number', 'like', "%{$searchQuery}%");
            })
            ->when($dateFilter !== '', function ($q) use ($dateFilter): void {
                $q->whereDate('business_date', $dateFilter);
            })
            ->with(['creditApplications', 'creditNotes'])
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(50);

        $invoices = $invoicesQuery->get();

        $results = [];
        foreach ($invoices as $inv) {
            $finalBill = $this->finalBillService->calculate($inv);
            $remainingOutstanding = round($finalBill->remainingBalance, 2);

            $shouldInclude = ($isAdmin && ($includeAll || $searchQuery !== '' || $dateFilter !== ''))
                || $remainingOutstanding > 0.0001;

            if ($shouldInclude) {
                $results[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->business_date?->toDateString() ?? '',
                    'status' => $inv->status,
                    'payment_status' => $finalBill->effectivePaymentStatus,
                    'final_total' => (float) $inv->final_total,
                    'paid_amount' => (float) $finalBill->amountPaid,
                    'prior_credits' => round((float) $finalBill->finalizedCreditTotal + (float) $finalBill->appliedCreditTotal, 2),
                    'effective_outstanding' => $remainingOutstanding,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
            'unapplied_credit' => $creditNote->unappliedCreditTotal(),
            'is_admin' => $isAdmin,
            'count' => count($results),
            'invoices' => $results,
        ]);
    }

    /**
     * Allocate credit from the finalized Credit Note to specific shop invoices.
     */
    public function applyCredit(Request $request, ShopCreditNote $creditNote): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if (! $creditNote->isApproved()) {
            $msg = 'Credit note must be approved/finalized before it can be applied to invoices.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        if (! $creditNote->isShopLevelMode()) {
            $msg = 'Legacy single-invoice credit notes cannot use bill-wise multi-invoice application.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer', 'exists:shop_invoices,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
            'allocations.*.notes' => ['nullable', 'string', 'max:500'],
            'applied_at' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $allocations = (array) $request->input('allocations', []);
        $appliedAt = $request->filled('applied_at') ? Carbon::parse((string) $request->input('applied_at')) : null;
        $idempotencyKey = $request->input('idempotency_key');

        $isAdmin = (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->hasRole('admin')
            || $user->hasRole('super_admin');

        try {
            $applied = $this->applicationService->applyCreditToMany(
                creditNote: $creditNote,
                allocations: $allocations,
                actor: $user,
                appliedAt: $appliedAt,
                idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : null,
                allowSettledInvoice: $isAdmin,
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

        $totalAppliedInBatch = round((float) $applied->sum('amount'), 2);
        $freshCreditNote = $creditNote->fresh(['applications']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Successfully applied ₹{$totalAppliedInBatch} across {$applied->count()} invoice(s).",
                'applied_batch_total' => $totalAppliedInBatch,
                'applied_total' => $freshCreditNote->appliedCreditTotal(),
                'unapplied_total' => $freshCreditNote->unappliedCreditTotal(),
                'redirect_url' => route('admin.cashbook.reports.shops.credit-notes.show', $creditNote),
            ]);
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)
            ->with('success', "Successfully applied ₹{$totalAppliedInBatch} across {$applied->count()} invoice(s).");
    }

    /**
     * Reverse an individual active credit application.
     */
    public function reverseApplication(
        Request $request,
        ShopCreditNote $creditNote,
        ShopCreditApplication $application
    ): JsonResponse|RedirectResponse {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if (! $this->applicationService->isAuthorizedToReverseApplication($user)) {
            abort(403, 'Unauthorized: You do not have permission to reverse credit applications.');
        }

        if (! $creditNote->isShopLevelMode()) {
            $msg = 'Legacy single-invoice credit notes cannot use application reversal.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        if ((int) $application->shop_credit_note_id !== (int) $creditNote->id) {
            $msg = 'This credit application does not belong to the specified credit note.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        if ($application->isReversed()) {
            $msg = 'Credit application is already reversed.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $reversed = $this->applicationService->reverseCreditApplication(
                application: $application,
                actor: $user,
                reason: $validated['reason'],
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

        $freshCreditNote = $creditNote->fresh(['applications']);
        $formattedAmt = number_format((float) $reversed->amount, 2);
        $msg = "Credit application of ₹{$formattedAmt} reversed successfully.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'application' => $reversed,
                'applied_total' => $freshCreditNote->appliedCreditTotal(),
                'unapplied_total' => $freshCreditNote->unappliedCreditTotal(),
                'redirect_url' => route('admin.cashbook.reports.shops.credit-notes.show', $creditNote),
            ]);
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)
            ->with('success', $msg);
    }

    /**
     * Cancel/void a finalized shop-level credit note and reverse all financial effects.
     */
    public function cancel(Request $request, ShopCreditNote $creditNote): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorizedOfficeUser($request);

        if (! $this->creditNoteService->isAuthorizedToCancelCreditNote($user)) {
            abort(403, 'Unauthorized: You do not have permission to cancel credit notes.');
        }

        if (! $creditNote->isShopLevelMode()) {
            $msg = 'Legacy single-invoice credit notes cannot be cancelled using the shop-level workflow.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes')->with('error', $msg);
        }

        if ($creditNote->isVoided()) {
            $msg = "Credit note #{$creditNote->credit_note_number} is already cancelled/voided.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        if (! $creditNote->isApproved()) {
            $msg = "Credit note #{$creditNote->credit_note_number} cannot be cancelled because its status is '{$creditNote->status->value}'.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        if ($creditNote->hasUnreconciledPhysicalReturns()) {
            $msg = 'Cannot cancel credit note with active, unreconciled warehouse receipts. Physical receipts must be reversed by warehouse staff first.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('admin.cashbook.reports.shops.credit-notes.show', $creditNote)->with('error', $msg);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $cancelled = $this->creditNoteService->cancelShopLevelCreditNote(
                creditNote: $creditNote,
                actor: $user,
                reason: $validated['reason'],
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

        $msg = "Credit note #{$cancelled->credit_note_number} has been cancelled and its financial credit reversed.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'credit_note_id' => $cancelled->id,
                'credit_note_number' => $cancelled->credit_note_number,
                'redirect_url' => route('admin.cashbook.reports.shops.credit-notes.show', $cancelled),
            ]);
        }

        return redirect()
            ->route('admin.cashbook.reports.shops.credit-notes.show', $cancelled)
            ->with('success', $msg);
    }

    /**
     * Enforce server-side authorization: only authorized office administrative and accounts roles.
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
            'Unauthorized: Only office administrative and accounts roles may access the Shop Credit Note manager.'
        );

        return $user;
    }
}
