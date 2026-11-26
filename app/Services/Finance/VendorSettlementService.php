<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\Supplier;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Purchasing\PurchaseCreditAllocationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorSettlementService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $reconciliationService,
        private readonly ?PurchaseFinanceAccountingOpeningService $accountingOpeningService = null,
        private readonly ?PurchaseCreditAllocationService $creditAllocationService = null,
    ) {}

    private function getAccountingOpeningService(): PurchaseFinanceAccountingOpeningService
    {
        return $this->accountingOpeningService ?? app(PurchaseFinanceAccountingOpeningService::class);
    }

    private function getCreditAllocationService(): PurchaseCreditAllocationService
    {
        return $this->creditAllocationService ?? app(PurchaseCreditAllocationService::class);
    }

    /** @param array{actual_payment_amount:float,settlement_discount_amount:float,vendor_advance_used_amount:float,purchase_credit_used_amount?:float,credit_notes?:array<int,array{purchase_credit_note_id:int,amount:float,purchase_invoice_id?:int|null}>,submission_token?:?string,payment_date:string,payment_method:?string,company_account_id:?int,statement_entry_id?:int,reference:?string,note:?string,allocations:array<int,array{purchase_invoice_id:int,cash_allocated:float,advance_allocated:float,credit_allocated?:float,discount_allocated:float}>} $payload */
    public function create(Supplier $supplier, array $payload, int $userId): VendorSettlement
    {
        $submissionToken = isset($payload['submission_token']) ? trim((string) $payload['submission_token']) : '';

        try {
            return DB::transaction(function () use ($supplier, $payload, $userId, $submissionToken): VendorSettlement {
                if ($submissionToken !== '') {
                    $existingByToken = VendorSettlement::query()
                        ->where('submission_token', $submissionToken)
                        ->lockForUpdate()
                        ->first();
                    if ($existingByToken instanceof VendorSettlement) {
                        $this->assertSettlementTokenMatchesPayload($existingByToken, $supplier, $payload);

                        return $existingByToken->fresh(['allocations.purchaseInvoice', 'creditAllocations.purchaseCreditNote', 'journalEntry.transactions.account', 'advances']);
                    }
                }

                $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
                $allocations = collect($payload['allocations'])->filter(fn (array $row): bool => round((float) ($row['cash_allocated'] ?? 0) + (float) ($row['advance_allocated'] ?? 0) + (float) ($row['credit_allocated'] ?? 0) + (float) ($row['discount_allocated'] ?? 0), 2) > 0);
                $invoiceIds = $allocations->pluck('purchase_invoice_id')->map(fn ($id): int => (int) $id)->all();

                if ($invoiceIds === [] || count($invoiceIds) !== count(array_unique($invoiceIds))) {
                    throw ValidationException::withMessages(['allocations' => 'Select each supplier invoice only once.']);
                }

                $invoices = PurchaseInvoice::query()
                    ->with('purchaserCart')
                    ->whereIn('id', $invoiceIds)
                    ->where('supplier_id', $supplier->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($invoices->count() !== count($invoiceIds)) {
                    throw ValidationException::withMessages(['allocations' => 'Every allocated invoice must belong to this supplier.']);
                }

                $cash = round((float) $payload['actual_payment_amount'], 2);
                $selectedStatement = null;
                if (! empty($payload['statement_entry_id'])) {
                    $selectedStatement = CompanyAccountStatementEntry::query()
                        ->whereKey((int) $payload['statement_entry_id'])
                        ->where('company_account_id', (int) $payload['company_account_id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    if ($selectedStatement->direction !== 'out' || $selectedStatement->is_finalized || $selectedStatement->journal_entry_id !== null) {
                        throw ValidationException::withMessages(['statement_entry_id' => 'Statement transaction is unavailable for vendor settlement.']);
                    }
                    $cash = round((float) $selectedStatement->amount, 2);
                    $payload['payment_date'] = $selectedStatement->transaction_date->toDateString();
                    $payload['reference'] = $selectedStatement->reference;
                }

                // Pre-opening payments cannot allocate to post-opening invoices.
                // Post-opening payments are permitted to settle carried-forward pre-opening vendor credit invoices.
                $paymentDate = (string) ($payload['payment_date'] ?? today()->toDateString());
                $accountingStartDate = $this->getAccountingOpeningService()->getAccountingStartDate(null, (int) $supplier->id, 'vendor')
                    ?? PurchaseFinanceAccountingOpeningService::DEFAULT_ACCOUNTING_START_DATE;

                $isPaymentInActivePeriod = $paymentDate >= $accountingStartDate;

                foreach ($invoices as $inv) {
                    $invDate = (string) ($inv->purchaserCart?->business_date?->toDateString()
                        ?? $inv->original_business_date?->toDateString()
                        ?? $inv->created_at->toDateString());
                    $isInvoiceInActivePeriod = $invDate >= $accountingStartDate;

                    if (! $isPaymentInActivePeriod && $isInvoiceInActivePeriod) {
                        throw ValidationException::withMessages([
                            'allocations' => "Historical pre-opening payment ({$paymentDate}) cannot allocate to post-opening Invoice {$inv->invoice_number} ({$invDate}).",
                        ]);
                    }
                }

                $discount = round((float) ($payload['settlement_discount_amount'] ?? 0), 2);
                $advanceUsed = round((float) ($payload['vendor_advance_used_amount'] ?? 0), 2);
                $creditNotesInput = is_array($payload['credit_notes'] ?? null) ? $payload['credit_notes'] : [];
                $creditUsed = isset($payload['purchase_credit_used_amount'])
                    ? round((float) $payload['purchase_credit_used_amount'], 2)
                    : round((float) collect($creditNotesInput)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);

                $totalCash = round((float) $allocations->sum('cash_allocated'), 2);
                $totalAdvance = round((float) $allocations->sum('advance_allocated'), 2);
                $totalCredit = round((float) $allocations->sum('credit_allocated'), 2);
                $totalDiscount = round((float) $allocations->sum('discount_allocated'), 2);
                $settled = round($totalCash + $totalAdvance + $totalCredit + $totalDiscount, 2);
                $newAdvance = round($cash + $discount + $advanceUsed + $creditUsed - $settled, 2);

                if ($cash < 0 || $discount < 0 || $advanceUsed < 0 || $creditUsed < 0 || $newAdvance < -0.01 || $totalCash > $cash + 0.01 || abs($discount - $totalDiscount) > 0.01 || abs($advanceUsed - $totalAdvance) > 0.01 || abs($creditUsed - $totalCredit) > 0.01 || abs($newAdvance - ($cash - $totalCash)) > 0.01) {
                    throw ValidationException::withMessages(['allocations' => 'Payment, credit, discount, and advance allocations must balance exactly.']);
                }
                $newAdvance = max(0, $newAdvance);

                $openAdvances = VendorAdvance::query()->where('supplier_id', $supplier->id)->where('amount_remaining', '>', 0)->orderBy('id')->lockForUpdate()->get();
                if ($advanceUsed > round((float) $openAdvances->sum('amount_remaining'), 2) + 0.01) {
                    throw ValidationException::withMessages(['vendor_advance_used_amount' => 'Vendor advance is no longer available.']);
                }

                foreach ($allocations as $row) {
                    $invoice = $invoices->get((int) $row['purchase_invoice_id']);
                    $invoice->unsetRelation('vendorSettlementAllocations');
                    $invoice->unsetRelation('purchaseCreditNotes');
                    $allocated = round((float) ($row['cash_allocated'] ?? 0) + (float) ($row['advance_allocated'] ?? 0) + (float) ($row['credit_allocated'] ?? 0) + (float) ($row['discount_allocated'] ?? 0), 2);
                    $outstanding = $invoice->settlementOutstanding();
                    if ($allocated > $outstanding + 0.01) {
                        throw ValidationException::withMessages(['allocations' => "Invoice {$invoice->invoice_number} has only ₹{$outstanding} outstanding."]);
                    }
                }

                $companyAccount = $cash > 0 && ! empty($payload['company_account_id'])
                    ? CompanyAccount::query()->whereKey((int) $payload['company_account_id'])->where('enabled', true)->lockForUpdate()->firstOrFail()
                    : null;

                $settlement = VendorSettlement::query()->create([
                    'submission_token' => $submissionToken !== '' ? $submissionToken : null,
                    'supplier_id' => $supplier->id, 'actual_payment_amount' => $cash, 'settlement_discount_amount' => $discount,
                    'vendor_advance_used_amount' => $advanceUsed, 'purchase_credit_used_amount' => $creditUsed, 'new_vendor_advance_amount' => $newAdvance,
                    'company_account_id' => $companyAccount?->id, 'payment_method' => $payload['payment_method'] ?? 'Bank', 'payment_date' => $payload['payment_date'],
                    'reference' => $payload['reference'] ?? null, 'note' => $payload['note'] ?? null, 'status' => 'approved',
                    'reconciliation_status' => $cash > 0 ? 'unreconciled' : 'not_required', 'is_finalized' => $cash <= 0, 'finalized_at' => $cash <= 0 ? now() : null, 'created_by' => $userId,
                ]);

                $invoiceCreditTargets = [];
                foreach ($allocations as $row) {
                    $invoice = $invoices->get((int) $row['purchase_invoice_id']);
                    $cashAllocation = round((float) ($row['cash_allocated'] ?? 0), 2);
                    $advanceAllocation = round((float) ($row['advance_allocated'] ?? 0), 2);
                    $creditAllocation = round((float) ($row['credit_allocated'] ?? 0), 2);
                    $discountAllocation = round((float) ($row['discount_allocated'] ?? 0), 2);

                    if ($creditAllocation > 0.0) {
                        $invoiceCreditTargets[$invoice->id] = $creditAllocation;
                    }

                    $allocation = VendorSettlementAllocation::query()->create([
                        'vendor_settlement_id' => $settlement->id, 'purchase_invoice_id' => $invoice->id,
                        'cash_allocated' => $cashAllocation, 'advance_allocated' => $advanceAllocation,
                        'credit_allocated' => $creditAllocation, 'discount_allocated' => $discountAllocation,
                        'total_settled' => round($cashAllocation + $advanceAllocation + $creditAllocation + $discountAllocation, 2),
                    ]);
                    if ($cashAllocation > 0) {
                        PurchaseInvoicePayment::query()->create(['purchase_invoice_id' => $invoice->id, 'supplier_id' => $supplier->id, 'payment_date' => $payload['payment_date'], 'amount' => $cashAllocation, 'discount_amount' => 0, 'payment_method' => $payload['payment_method'] ?? 'Bank', 'payment_paid_by' => 'company', 'note' => $payload['note'] ?? null, 'created_by' => $userId]);
                    }
                    if ($cashAllocation > 0) {
                        $invoice->update([
                            'paid_amount' => round((float) $invoice->paid_amount + $cashAllocation, 2),
                            'payment_method' => $payload['payment_method'] ?? $invoice->payment_method,
                            'payment_paid_by' => 'company',
                        ]);
                    }
                    $invoice->refreshPaymentStatus();
                }

                if ($creditUsed > 0.0 || $creditNotesInput !== []) {
                    $this->getCreditAllocationService()->allocateForSettlement(
                        settlement: $settlement,
                        supplier: $supplier,
                        creditNoteItems: $creditNotesInput,
                        invoiceCreditTargets: $invoiceCreditTargets,
                        lockedInvoices: $invoices,
                    );
                }

                $remainingAdvanceUse = $advanceUsed;
                foreach ($openAdvances as $advance) {
                    $used = min($remainingAdvanceUse, round((float) $advance->amount_remaining, 2));
                    if ($used <= 0) {
                        break;
                    }
                    $advance->update(['amount_remaining' => round((float) $advance->amount_remaining - $used, 2), 'status' => round((float) $advance->amount_remaining - $used, 2) <= 0.01 ? 'used' : 'open']);
                    $remainingAdvanceUse = round($remainingAdvanceUse - $used, 2);
                }

                $journal = $this->journalService->recordVendorSettlement($settlement, $userId);
                $settlement->update(['journal_entry_id' => $journal->id]);
                if ($newAdvance > 0) {
                    VendorAdvance::query()->create(['supplier_id' => $supplier->id, 'source_settlement_id' => $settlement->id, 'amount_original' => $newAdvance, 'amount_remaining' => $newAdvance, 'business_date' => $payload['payment_date'], 'status' => 'open', 'journal_entry_id' => $journal->id, 'created_by' => $userId]);
                }
                if ($cash > 0 && $companyAccount instanceof CompanyAccount) {
                    $this->reconciliationService->finalizeVendorSettlementMovement($settlement, $journal, [
                        'company_account_id' => $companyAccount->id,
                        'statement_entry_id' => $selectedStatement?->id,
                        'transaction_date' => $payload['payment_date'],
                        'reference' => ($payload['reference'] ?? null) ?: 'VENDOR-SETTLEMENT-'.$settlement->id,
                        'narration' => 'Vendor settlement for '.$supplier->name,
                        'notes' => $payload['note'] ?? null,
                    ], $userId);
                }

                return $settlement->fresh(['allocations.purchaseInvoice', 'creditAllocations.purchaseCreditNote', 'journalEntry.transactions.account', 'advances']);
            }, attempts: 3);
        } catch (QueryException $e) {
            if ($submissionToken !== '' && ($e->getCode() === '23000' || str_contains($e->getMessage(), 'submission_token'))) {
                $existingByToken = VendorSettlement::query()->where('submission_token', $submissionToken)->first();
                if ($existingByToken instanceof VendorSettlement) {
                    $this->assertSettlementTokenMatchesPayload($existingByToken, $supplier, $payload);

                    return $existingByToken->fresh(['allocations.purchaseInvoice', 'creditAllocations.purchaseCreditNote', 'journalEntry.transactions.account', 'advances']);
                }
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function assertSettlementTokenMatchesPayload(VendorSettlement $existing, Supplier $supplier, array $payload): void
    {
        $cash = round((float) ($payload['actual_payment_amount'] ?? 0), 2);
        $creditUsed = isset($payload['purchase_credit_used_amount'])
            ? round((float) $payload['purchase_credit_used_amount'], 2)
            : round((float) collect($payload['credit_notes'] ?? [])->sum(fn (array $r): float => (float) ($r['amount'] ?? 0)), 2);

        if (
            (int) $existing->supplier_id !== (int) $supplier->id
            || abs((float) $existing->actual_payment_amount - $cash) > 0.01
            || abs((float) $existing->purchase_credit_used_amount - $creditUsed) > 0.01
        ) {
            throw ValidationException::withMessages([
                'submission_token' => 'This submission token has already been used for a different settlement payload.',
            ]);
        }
    }

    /**
     * @param  array{invoice_ids:array<int,int>,actual_payment_amount:float,use_vendor_advance:bool,credit_notes?:array<int,array{purchase_credit_note_id:int,amount:float}>,submission_token?:?string,difference_treatment:string,allocation_order:string,payment_date:string,payment_method:?string,company_account_id:?int,reference:?string,note:?string,settlement_discount_amount?:float}  $payload
     */
    public function createAutomatic(Supplier $supplier, array $payload, int $userId): VendorSettlement
    {
        return DB::transaction(function () use ($supplier, $payload, $userId): VendorSettlement {
            if (! empty($payload['statement_entry_id'])) {
                $statement = CompanyAccountStatementEntry::query()
                    ->whereKey((int) $payload['statement_entry_id'])
                    ->where('company_account_id', (int) $payload['company_account_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($statement->direction !== 'out' || $statement->is_finalized || $statement->journal_entry_id !== null) {
                    throw ValidationException::withMessages(['statement_entry_id' => 'Statement transaction is unavailable for vendor settlement.']);
                }
                $payload['actual_payment_amount'] = (float) $statement->amount;
                $payload['payment_date'] = $statement->transaction_date->toDateString();
                $payload['reference'] = $statement->reference;
            }
            $invoiceIds = array_values(array_unique(array_map('intval', $payload['invoice_ids'])));
            if ($invoiceIds === []) {
                throw ValidationException::withMessages(['invoice_ids' => 'Select at least one outstanding invoice.']);
            }

            $invoices = PurchaseInvoice::query()
                ->whereIn('id', $invoiceIds)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->get()
                ->map(function (PurchaseInvoice $invoice): array {
                    $invoice->unsetRelation('vendorSettlementAllocations');
                    $invoice->unsetRelation('purchaseCreditNotes');

                    return [
                        'invoice' => $invoice,
                        'outstanding' => $invoice->settlementOutstanding(),
                    ];
                })
                ->filter(fn (array $row): bool => $row['outstanding'] > 0.01)
                ->sortBy(fn (array $row): mixed => $payload['allocation_order'] === 'newest' ? -$row['invoice']->id : $row['invoice']->id)
                ->values();

            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['invoice_ids' => 'Selected invoices must belong to supplier and remain outstanding.']);
            }

            $cash = round((float) $payload['actual_payment_amount'], 2);
            if ($cash < 0) {
                throw ValidationException::withMessages(['actual_payment_amount' => 'Payment amount cannot be negative.']);
            }

            $creditNotesInput = collect($payload['credit_notes'] ?? [])
                ->map(fn (array $row): array => [
                    'purchase_credit_note_id' => (int) ($row['purchase_credit_note_id'] ?? 0),
                    'amount' => round((float) ($row['amount'] ?? 0), 2),
                ])
                ->filter(fn (array $row): bool => $row['purchase_credit_note_id'] > 0 && $row['amount'] > 0.0)
                ->values()
                ->all();

            $creditRequested = round((float) collect($creditNotesInput)->sum('amount'), 2);
            $selectedTotal = round((float) $invoices->sum('outstanding'), 2);

            if ($creditRequested > $selectedTotal + 0.01) {
                throw ValidationException::withMessages([
                    'credit_notes' => 'Selected Purchase Credit Note amount cannot exceed selected invoices outstanding.',
                ]);
            }

            $advanceAvailable = round((float) VendorAdvance::query()
                ->where('supplier_id', $supplier->id)
                ->where('amount_remaining', '>', 0)
                ->lockForUpdate()
                ->sum('amount_remaining'), 2);

            $afterCreditTotal = round(max(0, $selectedTotal - $creditRequested), 2);
            $advance = $payload['use_vendor_advance'] ? min($advanceAvailable, $afterCreditTotal) : 0.0;
            $cashForInvoices = min($cash, round(max(0, $afterCreditTotal - $advance), 2));
            $difference = round(max(0, $afterCreditTotal - $advance - $cashForInvoices), 2);
            $requestedDiscount = round((float) ($payload['settlement_discount_amount'] ?? 0), 2);
            $discount = ($payload['difference_treatment'] ?? 'outstanding') === 'discount' || $requestedDiscount > 0
                ? min($difference, $requestedDiscount > 0 ? $requestedDiscount : $difference)
                : 0.0;

            $creditRemaining = $creditRequested;
            $cashRemaining = $cashForInvoices;
            $advanceRemaining = $advance;
            $discountRemaining = $discount;
            $allocations = [];
            foreach ($invoices as $row) {
                $outstanding = $row['outstanding'];
                $creditAllocated = min($creditRemaining, $outstanding);
                $creditRemaining = round($creditRemaining - $creditAllocated, 2);
                $afterCredit = round($outstanding - $creditAllocated, 2);

                $advanceAllocated = min($advanceRemaining, $afterCredit);
                $advanceRemaining = round($advanceRemaining - $advanceAllocated, 2);
                $afterAdvance = round($afterCredit - $advanceAllocated, 2);

                $cashAllocated = min($cashRemaining, $afterAdvance);
                $cashRemaining = round($cashRemaining - $cashAllocated, 2);
                $afterCash = round($afterAdvance - $cashAllocated, 2);

                $discountAllocated = min($discountRemaining, $afterCash);
                $discountRemaining = round($discountRemaining - $discountAllocated, 2);

                $allocations[] = [
                    'purchase_invoice_id' => $row['invoice']->id,
                    'cash_allocated' => $cashAllocated,
                    'advance_allocated' => $advanceAllocated,
                    'credit_allocated' => $creditAllocated,
                    'discount_allocated' => $discountAllocated,
                ];
            }

            return $this->create($supplier, [
                'submission_token' => $payload['submission_token'] ?? null,
                'actual_payment_amount' => $cash,
                'settlement_discount_amount' => $discount,
                'vendor_advance_used_amount' => $advance,
                'purchase_credit_used_amount' => $creditRequested,
                'credit_notes' => $creditNotesInput,
                'payment_date' => $payload['payment_date'],
                'payment_method' => $payload['payment_method'] ?? 'Bank',
                'company_account_id' => $payload['company_account_id'] ?? null,
                'statement_entry_id' => $payload['statement_entry_id'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'note' => $payload['note'] ?? null,
                'allocations' => $allocations,
            ], $userId);
        }, attempts: 3);
    }
}
