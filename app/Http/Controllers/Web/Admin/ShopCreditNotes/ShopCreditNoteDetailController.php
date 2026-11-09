<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\ShopCreditNotes;

use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Http\Controllers\Controller;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditApplicationService;
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
            'items.product',
            'items.sourceInvoice',
            'items.invoiceItem.invoice',
            'applications.invoice',
            'applications.appliedByUser',
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

        return view('admin.cashbook.reports.shops.show_shop_credit_note', [
            'creditNote' => $creditNote,
            'productItems' => $productItems,
            'discountItems' => $discountItems,
            'returnCreditTotal' => $returnCreditTotal,
            'damageCreditTotal' => $damageCreditTotal,
            'otherAdjustmentTotal' => $otherAdjustmentTotal,
            'commercialDiscountTotal' => $commercialDiscountTotal,
            'appliedCreditTotal' => $appliedCreditTotal,
            'unappliedCreditTotal' => $unappliedCreditTotal,
        ]);
    }

    /**
     * AJAX search for unpaid finalized invoices of the same shop eligible for credit allocation.
     */
    public function outstandingInvoices(Request $request, ShopCreditNote $creditNote): JsonResponse
    {
        $this->ensureAuthorizedOfficeUser($request);

        if (! $creditNote->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Credit note must be approved/finalized to apply credit to invoices.',
            ], 422);
        }

        $searchQuery = trim((string) $request->input('q', ''));

        $invoicesQuery = ShopInvoice::query()
            ->where('shop_id', $creditNote->shop_id)
            ->where('status', 'finalized')
            ->where('balance_amount', '>', 0)
            ->when($searchQuery !== '', function ($q) use ($searchQuery): void {
                $q->where('invoice_number', 'like', "%{$searchQuery}%");
            })
            ->with(['creditApplications', 'creditNotes'])
            ->orderBy('business_date', 'asc')
            ->limit(50);

        $invoices = $invoicesQuery->get();

        $results = [];
        foreach ($invoices as $inv) {
            $finalBill = $this->finalBillService->calculate($inv);
            $remainingOutstanding = round($finalBill->remainingBalance, 2);

            if ($remainingOutstanding > 0.0001) {
                $results[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->business_date?->toDateString() ?? '',
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

        try {
            $applied = $this->applicationService->applyCreditToMany(
                creditNote: $creditNote,
                allocations: $allocations,
                actor: $user,
                appliedAt: $appliedAt,
                idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : null,
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
