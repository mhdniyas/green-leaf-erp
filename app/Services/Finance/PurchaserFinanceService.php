<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaserCredit;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaserFinanceService
{
    private const FUNDING_SOURCE_TYPE = PurchaserCredit::class;

    /**
     * No per-funding allocation exists: protect funding with subsequent usage or
     * insufficient pooled advance, rather than guessing which entry paid a bill.
     */
    public function assertFundingMutable(PurchaserCredit $credit): void
    {
        $reason = $this->fundingMutationBlockReason($credit, lock: true);

        if ($reason !== null) {
            throw ValidationException::withMessages(['credit' => $reason]);
        }
    }

    public function fundingMutationBlockReason(PurchaserCredit $credit, bool $lock = false): ?string
    {
        $journalsQuery = JournalEntry::query()->withCount('reconciliations')
            ->where('source_type', self::FUNDING_SOURCE_TYPE)
            ->where('source_id', $credit->id);

        if ($lock) {
            $journalsQuery->lockForUpdate();
        }

        $journals = $journalsQuery->get();
        $journalIds = $journals->modelKeys();

        $currentStatementQuery = CompanyAccountStatementEntry::query()
            ->where(function ($query) use ($credit, $journalIds): void {
                $query->where(function ($source) use ($credit): void {
                    $source->whereIn('source_type', [self::FUNDING_SOURCE_TYPE, 'purchaser_funding'])
                        ->where('source_id', $credit->id);
                });

                if ($journalIds !== []) {
                    $query->orWhereIn('journal_entry_id', $journalIds);
                }
            })
            ->where(function ($query): void {
                $query->where('is_finalized', true)
                    ->orWhereIn('status', ['matched', 'reconciled', 'partially_matched'])
                    ->orWhere('matched_amount', '>', 0)
                    ->orWhere('source', 'manual');
            });

        if ($lock) {
            $currentStatementQuery->lockForUpdate();
        }

        $currentStatement = $currentStatementQuery->orderByDesc('is_finalized')->orderByDesc('matched_amount')->first();

        if ($currentStatement instanceof CompanyAccountStatementEntry) {
            $isImported = $currentStatement->source === 'imported'
                || ! empty($currentStatement->import_file_name)
                || ! empty($currentStatement->import_fingerprint);

            return $isImported
                ? 'Matched to imported statement — unmatch first.'
                : 'Manual cash or statement counterpart — editing is protected.';
        }

        if ($journals->contains(fn (JournalEntry $journal): bool => $journal->reconciliations_count > 0)) {
            return 'Manual reconciliation allocation exists — editing is protected.';
        }

        if ($credit->purchase_invoice_id || $journals->count() !== 1 || $journals->first()->source_event !== 'purchaser_funding') {
            return 'Historical funding dependency — editing is protected.';
        }

        $movementsQuery = PurchaserCredit::query()->where('purchaser_id', $credit->purchaser_id)->orderBy('id');
        if ($lock) {
            $movementsQuery->lockForUpdate();
        }

        $movements = $movementsQuery->get();
        $balance = $movements->sum(fn (PurchaserCredit $movement): float => $movement->type === 'in' ? (float) $movement->amount : -(float) $movement->amount);
        $subsequentUsage = $movements->contains(fn (PurchaserCredit $movement): bool => $movement->type === 'out'
            && $movement->business_date >= $credit->business_date);

        if ($subsequentUsage || $balance + 0.009 < (float) $credit->amount) {
            return 'Used by purchase bills — editing is protected.';
        }

        return null;
    }

    public function cashMovementSumSubquery(string $type, string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchaser_credits')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('purchaser_credits.purchaser_id', 'users.id')
            ->where('purchaser_credits.type', $type)
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '<=', $endDate));
    }

    public function creditPurchasesSumSubquery(string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->selectRaw('COALESCE(SUM(purchase_invoices.amount - purchase_invoices.discount_amount), 0)')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereRaw('(purchase_invoices.purchaser_submitted_by = users.id OR purchaser_carts.user_id = users.id)')
            ->where(function (Builder $query): void {
                $query->where('purchase_invoices.payment_method', 'Credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit');
            })
            ->when($startDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]))
            ->when($endDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]));
    }

    public function balanceRows(string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchaser_credits')
            ->selectRaw("purchaser_id, COUNT(*) as transaction_count, SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as cash_given, SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as cash_used, SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) as remaining_advance")
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('business_date', '<=', $endDate))
            ->groupBy('purchaser_id');
    }

    /** @return array{cash_given:float,cash_used:float,remaining_advance:float,credit_purchases:float} */
    public function summaryFor(int $purchaserId): array
    {
        $balance = $this->balanceRows()->where('purchaser_id', $purchaserId)->first();
        $creditPurchases = DB::query()
            ->fromSub($this->creditPurchaseRowsQuery($purchaserId), 'credit_rows')
            ->sum('amount');

        return [
            'cash_given' => round((float) ($balance->cash_given ?? 0), 2),
            'cash_used' => round((float) ($balance->cash_used ?? 0), 2),
            'remaining_advance' => round((float) ($balance->remaining_advance ?? 0), 2),
            'credit_purchases' => round((float) $creditPurchases, 2),
        ];
    }

    /** @return array{cash_given:float,cash_used:float,remaining_advance:float,credit_purchases:float} */
    public function activityFor(int $purchaserId, string $startDate, string $endDate): array
    {
        $balance = $this->balanceRows($startDate, $endDate)->where('purchaser_id', $purchaserId)->first();
        $creditPurchases = DB::query()
            ->fromSub($this->creditPurchaseRowsQuery($purchaserId, $startDate, $endDate), 'credit_rows')
            ->sum('amount');

        return [
            'cash_given' => round((float) ($balance->cash_given ?? 0), 2),
            'cash_used' => round((float) ($balance->cash_used ?? 0), 2),
            'remaining_advance' => round((float) ($balance->remaining_advance ?? 0), 2),
            'credit_purchases' => round((float) $creditPurchases, 2),
        ];
    }

    public function splitsFor(int $purchaserId, string $startDate = '', string $endDate = '', string $search = '', string $payment = 'all'): LengthAwarePaginator
    {
        $cashSplits = DB::table('purchaser_credits')
            ->leftJoin('purchase_invoices', 'purchase_invoices.id', '=', 'purchaser_credits.purchase_invoice_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->where('purchaser_credits.purchaser_id', $purchaserId)
            ->where('purchaser_credits.type', 'out')
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '<=', $endDate))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('purchaser_credits.description', 'like', '%'.$search.'%')
                        ->orWhere('purchase_invoices.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('suppliers.name', 'like', '%'.$search.'%');
                });
            })
            ->selectRaw('purchaser_credits.business_date as row_date, suppliers.public_uuid as supplier_public_uuid, COALESCE(suppliers.name, "—") as supplier_name, COALESCE(purchase_invoices.invoice_number, purchaser_credits.description, "Advance Utilization") as invoice_number, "Cash" as payment_type, purchaser_credits.amount as amount, purchaser_credits.description as movement_reference, COALESCE(purchase_invoices.payment_status, "advance_utilized") as status, purchase_invoices.id as invoice_id, purchaser_credits.id as purchaser_credit_id');

        $creditSplits = DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($purchaserId): void {
                $query->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhere('purchaser_carts.user_id', $purchaserId);
            })
            ->where(function (Builder $query): void {
                $query->where('purchase_invoices.payment_method', 'Credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit');
            })
            ->when($startDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]))
            ->when($endDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('purchase_invoices.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('purchase_invoices.payment_note', 'like', '%'.$search.'%')
                        ->orWhere('purchase_invoices.payment_details', 'like', '%'.$search.'%')
                        ->orWhere('suppliers.name', 'like', '%'.$search.'%');
                });
            })
            ->selectRaw('COALESCE(purchaser_carts.business_date, DATE(purchase_invoices.created_at)) as row_date, suppliers.public_uuid as supplier_public_uuid, COALESCE(suppliers.name, "—") as supplier_name, purchase_invoices.invoice_number as invoice_number, "Credit" as payment_type, (purchase_invoices.amount - purchase_invoices.discount_amount) as amount, COALESCE(purchase_invoices.payment_note, purchase_invoices.payment_details, "Vendor Credit") as movement_reference, purchase_invoices.payment_status as status, purchase_invoices.id as invoice_id, NULL as purchaser_credit_id');

        return DB::query()
            ->fromSub($cashSplits->unionAll($creditSplits), 'purchaser_splits')
            ->when($payment !== 'all', fn (Builder $query) => $query->where('payment_type', ucfirst($payment)))
            ->orderByDesc('row_date')
            ->orderByDesc('invoice_id')
            ->paginate(25)
            ->withQueryString();
    }

    /** @return array{reconciled_amount:float,pending_reconciliation:float} */
    public function reconciliationFor(int $purchaserId): array
    {
        $row = DB::table('purchaser_credits as credits')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'credits.id')
                    ->where('statements.source_type', PurchaserCredit::class)
                    ->where('statements.is_finalized', 1);
            })
            ->where('credits.purchaser_id', $purchaserId)
            ->where('credits.type', 'in')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(statements.matched_amount, 0) > credits.amount THEN credits.amount ELSE COALESCE(statements.matched_amount, 0) END), 0) as reconciled_amount, COALESCE(SUM(credits.amount), 0) as funding_amount')
            ->first();

        $reconciled = round((float) ($row->reconciled_amount ?? 0), 2);

        return [
            'reconciled_amount' => $reconciled,
            'pending_reconciliation' => round(max(0, (float) ($row->funding_amount ?? 0) - $reconciled), 2),
        ];
    }

    public function transactionsFor(int $purchaserId, string $startDate, string $endDate): LengthAwarePaginator
    {
        return DB::table('purchaser_credits as credits')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'credits.company_account_id')
            ->leftJoin('purchase_invoices as invoices', 'invoices.id', '=', 'credits.purchase_invoice_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'credits.id')
                    ->where('statements.source_type', PurchaserCredit::class)
                    ->where('statements.is_finalized', 1);
            })
            ->leftJoin('cashbook_company_accounts as stmt_accounts', 'stmt_accounts.id', '=', 'statements.company_account_id')
            ->leftJoin('users as reconcilers', 'reconcilers.id', '=', 'statements.reconciled_by')
            ->where('credits.purchaser_id', $purchaserId)
            ->whereDate('credits.business_date', '>=', $startDate)
            ->whereDate('credits.business_date', '<=', $endDate)
            ->selectRaw("
                credits.id,
                credits.business_date,
                CASE WHEN credits.type = 'in' THEN 'Purchaser Funding' ELSE 'Cash Used' END as movement_type,
                credits.type,
                credits.amount,
                accounts.name as company_account,
                credits.company_account_id,
                credits.payment_source,
                COALESCE(credits.reference, invoices.invoice_number, credits.description) as movement_reference,
                credits.reference as funding_reference,
                credits.description as funding_description,
                CASE 
                    WHEN credits.type = 'out' THEN 'advance_utilized'
                    WHEN statements.id IS NULL OR statements.is_finalized = 0 THEN 'unmatched'
                    WHEN statements.source = 'imported' OR statements.import_file_name IS NOT NULL OR statements.import_fingerprint IS NOT NULL THEN 'matched'
                    WHEN stmt_accounts.account_type = 'cash' THEN 'manual_cash'
                    WHEN stmt_accounts.account_type = 'bank' THEN 'manual_statement'
                    ELSE 'matched'
                END as status,
                statements.id as statement_entry_id,
                statements.public_uuid as statement_uuid,
                statements.transaction_date as statement_date,
                statements.amount as statement_amount,
                statements.reference as statement_reference,
                statements.narration as statement_narration,
                statements.source as statement_source,
                statements.import_file_name,
                stmt_accounts.name as statement_account_name,
                stmt_accounts.account_type as statement_account_type,
                reconcilers.name as reconciled_by_name,
                statements.reconciled_at
            ")
            ->selectRaw("(
                credits.purchase_invoice_id IS NOT NULL
                OR EXISTS (SELECT 1 FROM purchaser_credits used WHERE used.purchaser_id = credits.purchaser_id AND used.type = 'out' AND used.business_date >= credits.business_date)
                OR (SELECT COALESCE(SUM(CASE WHEN balance.type = 'in' THEN balance.amount ELSE -balance.amount END), 0) FROM purchaser_credits balance WHERE balance.purchaser_id = credits.purchaser_id) + 0.009 < credits.amount
                OR (SELECT COUNT(*) FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id) != 1
                OR NOT EXISTS (SELECT 1 FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id AND funding_journal.source_event = 'purchaser_funding')
                OR EXISTS (SELECT 1 FROM cashbook_company_payment_reconciliations allocation JOIN journal_entries allocation_journal ON allocation_journal.id = allocation.journal_entry_id WHERE allocation_journal.source_type = ? AND allocation_journal.source_id = credits.id)
                OR EXISTS (
                    SELECT 1 FROM cashbook_company_account_statement_entries linked
                    WHERE (
                        (linked.source_type IN (?, ?) AND linked.source_id = credits.id)
                        OR linked.journal_entry_id IN (SELECT id FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id)
                    )
                    AND (
                        linked.is_finalized = 1
                        OR linked.status IN ('matched', 'reconciled', 'partially_matched')
                        OR linked.matched_amount > 0
                        OR linked.source = 'manual'
                    )
                )
            ) as funding_action_blocked", [PurchaserCredit::class, PurchaserCredit::class, PurchaserCredit::class, PurchaserCredit::class, 'purchaser_funding', PurchaserCredit::class])
            ->selectRaw("
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM cashbook_company_account_statement_entries linked
                        WHERE (
                            (linked.source_type IN (?, ?) AND linked.source_id = credits.id)
                            OR linked.journal_entry_id IN (SELECT id FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id)
                        )
                        AND (
                            linked.is_finalized = 1
                            OR linked.status IN ('matched', 'reconciled', 'partially_matched')
                            OR linked.matched_amount > 0
                        )
                        AND (linked.source = 'imported' OR linked.import_file_name IS NOT NULL OR linked.import_fingerprint IS NOT NULL)
                    ) THEN 'Matched to imported statement — unmatch first.'
                    WHEN EXISTS (
                        SELECT 1 FROM cashbook_company_account_statement_entries linked
                        WHERE (
                            (linked.source_type IN (?, ?) AND linked.source_id = credits.id)
                            OR linked.journal_entry_id IN (SELECT id FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id)
                        )
                        AND linked.source = 'manual'
                    ) THEN 'Manual cash or statement counterpart — editing is protected.'
                    WHEN EXISTS (SELECT 1 FROM cashbook_company_payment_reconciliations allocation JOIN journal_entries allocation_journal ON allocation_journal.id = allocation.journal_entry_id WHERE allocation_journal.source_type = ? AND allocation_journal.source_id = credits.id) THEN 'Manual reconciliation allocation exists — editing is protected.'
                    WHEN credits.purchase_invoice_id IS NOT NULL
                        OR (SELECT COUNT(*) FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id) != 1
                        OR NOT EXISTS (SELECT 1 FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id AND funding_journal.source_event = 'purchaser_funding')
                    THEN 'Historical funding dependency — editing is protected.'
                    WHEN EXISTS (SELECT 1 FROM purchaser_credits used WHERE used.purchaser_id = credits.purchaser_id AND used.type = 'out' AND used.business_date >= credits.business_date)
                        OR (SELECT COALESCE(SUM(CASE WHEN balance.type = 'in' THEN balance.amount ELSE -balance.amount END), 0) FROM purchaser_credits balance WHERE balance.purchaser_id = credits.purchaser_id) + 0.009 < credits.amount
                    THEN 'Used by purchase bills — editing is protected.'
                    ELSE NULL
                END as funding_action_block_reason
            ", [PurchaserCredit::class, 'purchaser_funding', PurchaserCredit::class, PurchaserCredit::class, 'purchaser_funding', PurchaserCredit::class, PurchaserCredit::class, PurchaserCredit::class, PurchaserCredit::class])
            ->orderByDesc('credits.business_date')
            ->orderByDesc('credits.id')
            ->paginate(20, ['*'], 'finance_page')
            ->withQueryString();
    }

    /** @return Collection<int, array{statement: CompanyAccountStatementEntry, is_exact_amount: bool, score: int}> */
    public function __construct(
        private readonly CompanyPaymentReconciliationService $reconciliationService,
    ) {}

    /**
     * @return array{
     *     funding: array<string, mixed>,
     *     pending: list<array<string, mixed>>,
     *     reconciled: list<array<string, mixed>>,
     *     counts: array{
     *         pending: int,
     *         reconciled: int,
     *         exact_date_pending: int,
     *         exact_date_reconciled: int
     *     }
     * }
     */
    public function candidateStatementsForCredit(PurchaserCredit $credit): array
    {
        $credit->loadMissing(['purchaser', 'companyAccount']);
        $creditAmount = round((float) $credit->amount, 2);
        $fundingDate = $credit->business_date ?: today();

        $candidates = $this->reconciliationService->findStatementCandidates(
            companyAccountId: $credit->company_account_id,
            amount: $creditAmount,
            direction: 'out',
            referenceDate: $fundingDate,
        );

        return [
            'funding' => [
                'id' => $credit->id,
                'amount' => (float) $credit->amount,
                'formatted_amount' => '₹'.number_format((float) $credit->amount, 2),
                'business_date' => $credit->business_date?->format('d M Y') ?? $credit->business_date?->toDateString(),
                'raw_date' => $credit->business_date?->toDateString(),
                'purchaser_name' => $credit->purchaser?->name ?? 'Purchaser',
                'account_name' => $credit->companyAccount?->name ?? ($credit->payment_source ?: 'Any Account'),
                'company_account_id' => $credit->company_account_id,
                'reference' => $credit->reference ?: '—',
                'description' => $credit->description ?: 'Company funding to purchaser',
            ],
            'pending' => $candidates['pending'],
            'reconciled' => $candidates['reconciled'],
            'counts' => $candidates['counts'],
        ];
    }

    private function creditPurchaseRowsQuery(int $purchaserId, string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($purchaserId): void {
                $query->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhere('purchaser_carts.user_id', $purchaserId);
            })
            ->where(function (Builder $query): void {
                $query->where('purchase_invoices.payment_method', 'Credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit');
            })
            ->when($startDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]))
            ->when($endDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]))
            ->selectRaw('(purchase_invoices.amount - purchase_invoices.discount_amount) as amount');
    }
}
