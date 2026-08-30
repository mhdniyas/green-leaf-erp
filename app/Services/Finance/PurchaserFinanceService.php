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

        if ($credit->purchase_invoice_id || $journals->count() !== 1 || ! in_array($journals->first()->source_event, ['purchaser_funding', 'purchaser_funding_return'], true)) {
            return 'Historical funding dependency — editing is protected.';
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
            ->selectRaw("
                purchaser_id,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as cash_given,
                SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NULL THEN amount ELSE 0 END) as cash_returned,
                SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NOT NULL THEN amount ELSE 0 END) as cash_used_invoices,
                SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as cash_used,
                SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) as remaining_advance
            ")
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('business_date', '<=', $endDate))
            ->groupBy('purchaser_id');
    }

    /** @return array{cash_given:float,cash_returned:float,net_funding:float,cash_used_invoices:float,cash_used:float,remaining_advance:float,credit_purchases:float} */
    public function summaryFor(int $purchaserId): array
    {
        $balance = $this->balanceRows()->where('purchaser_id', $purchaserId)->first();
        $creditPurchases = DB::query()
            ->fromSub($this->creditPurchaseRowsQuery($purchaserId), 'credit_rows')
            ->sum('amount');

        $cashGiven = round((float) ($balance->cash_given ?? 0), 2);
        $cashReturned = round((float) ($balance->cash_returned ?? 0), 2);
        $cashUsedInvoices = round((float) ($balance->cash_used_invoices ?? 0), 2);
        $cashUsed = round((float) ($balance->cash_used ?? 0), 2);
        $remainingAdvance = round((float) ($balance->remaining_advance ?? 0), 2);

        return [
            'cash_given' => $cashGiven,
            'cash_returned' => $cashReturned,
            'net_funding' => round($cashGiven - $cashReturned, 2),
            'cash_used_invoices' => $cashUsedInvoices,
            'cash_used' => $cashUsed,
            'remaining_advance' => $remainingAdvance,
            'credit_purchases' => round((float) $creditPurchases, 2),
        ];
    }

    /** @return array{cash_given:float,cash_returned:float,net_funding:float,cash_used_invoices:float,cash_used:float,remaining_advance:float,credit_purchases:float} */
    public function activityFor(int $purchaserId, string $startDate, string $endDate): array
    {
        $balance = $this->balanceRows($startDate, $endDate)->where('purchaser_id', $purchaserId)->first();
        $creditPurchases = DB::query()
            ->fromSub($this->creditPurchaseRowsQuery($purchaserId, $startDate, $endDate), 'credit_rows')
            ->sum('amount');

        $cashGiven = round((float) ($balance->cash_given ?? 0), 2);
        $cashReturned = round((float) ($balance->cash_returned ?? 0), 2);
        $cashUsedInvoices = round((float) ($balance->cash_used_invoices ?? 0), 2);
        $cashUsed = round((float) ($balance->cash_used ?? 0), 2);
        $remainingAdvance = round((float) ($balance->remaining_advance ?? 0), 2);

        return [
            'cash_given' => $cashGiven,
            'cash_returned' => $cashReturned,
            'net_funding' => round($cashGiven - $cashReturned, 2),
            'cash_used_invoices' => $cashUsedInvoices,
            'cash_used' => $cashUsed,
            'remaining_advance' => $remainingAdvance,
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
            ->leftJoin('users as creators', 'creators.id', '=', 'credits.created_by')
            ->where('credits.purchaser_id', $purchaserId)
            ->whereDate('credits.business_date', '>=', $startDate)
            ->selectRaw("
                credits.id,
                credits.business_date,
                credits.created_at,
                credits.updated_at,
                CASE
                    WHEN credits.type = 'in' THEN 'Company → Purchaser'
                    WHEN credits.type = 'out' AND credits.purchase_invoice_id IS NULL THEN 'Purchaser → Company'
                    ELSE 'Cash Purchase Spend'
                END as direction_label,
                CASE
                    WHEN credits.type = 'in' THEN 'company_to_purchaser'
                    WHEN credits.type = 'out' AND credits.purchase_invoice_id IS NULL THEN 'purchaser_to_company'
                    ELSE 'cash_purchase_usage'
                END as direction_type,
                CASE
                    WHEN credits.type = 'in' THEN 'Purchaser Funding'
                    WHEN credits.type = 'out' AND credits.purchase_invoice_id IS NULL THEN 'Funding Returned'
                    ELSE 'Cash Used'
                END as movement_type,
                credits.type,
                credits.amount,
                credits.purchase_invoice_id,
                accounts.name as company_account,
                credits.company_account_id,
                credits.payment_source,
                creators.name as created_by_name,
                COALESCE(credits.reference, invoices.invoice_number, credits.description) as movement_reference,
                credits.reference as funding_reference,
                credits.description as funding_description,
                (
                    SELECT COALESCE(SUM(CASE WHEN b.type = 'in' THEN b.amount ELSE -b.amount END), 0)
                    FROM purchaser_credits b
                    WHERE b.purchaser_id = credits.purchaser_id
                      AND (b.business_date < credits.business_date OR (b.business_date = credits.business_date AND b.id <= credits.id))
                ) as running_balance,
                CASE 
                    WHEN credits.type = 'out' AND credits.purchase_invoice_id IS NOT NULL THEN 'advance_utilized'
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
                OR (SELECT COUNT(*) FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id) != 1
                OR NOT EXISTS (SELECT 1 FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id AND funding_journal.source_event IN ('purchaser_funding', 'purchaser_funding_return'))
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
                        OR NOT EXISTS (SELECT 1 FROM journal_entries funding_journal WHERE funding_journal.source_type = ? AND funding_journal.source_id = credits.id AND funding_journal.source_event IN ('purchaser_funding', 'purchaser_funding_return'))
                    THEN 'Historical funding dependency — editing is protected.'
                    WHEN EXISTS (SELECT 1 FROM purchaser_credits used WHERE used.purchaser_id = credits.purchaser_id AND used.type = 'out' AND used.business_date >= credits.business_date AND used.id > credits.id)
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
        $direction = $credit->type === 'in' ? 'out' : 'in';

        $candidates = $this->reconciliationService->findStatementCandidates(
            companyAccountId: $credit->company_account_id,
            amount: $creditAmount,
            direction: $direction,
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

    /**
     * Exact split records backing each summary card for Purchaser Funding / Cash Movement.
     * All calculations come directly from canonical purchaser_credits and invoices.
     *
     * @return array<string, mixed>
     */
    public function fundingSplitsFor(int $purchaserId, string $startDate = '', string $endDate = ''): array
    {
        $allCredits = DB::table('purchaser_credits as credits')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'credits.company_account_id')
            ->leftJoin('purchase_invoices as invoices', 'invoices.id', '=', 'credits.purchase_invoice_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'credits.id')
                    ->where('statements.source_type', PurchaserCredit::class)
                    ->where('statements.is_finalized', 1);
            })
            ->leftJoin('cashbook_company_accounts as stmt_accounts', 'stmt_accounts.id', '=', 'statements.company_account_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'invoices.supplier_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'credits.created_by')
            ->where('credits.purchaser_id', $purchaserId)
            ->selectRaw("
                credits.id,
                credits.type,
                credits.amount,
                credits.business_date,
                credits.created_at,
                credits.updated_at,
                credits.payment_source,
                credits.company_account_id,
                accounts.name as company_account,
                credits.reference as funding_reference,
                credits.description as funding_description,
                creators.name as created_by_name,
                credits.purchase_invoice_id,
                invoices.invoice_number,
                suppliers.name as supplier_name,
                CASE 
                    WHEN credits.type = 'out' AND credits.purchase_invoice_id IS NOT NULL THEN 'advance_utilized'
                    WHEN statements.id IS NULL OR statements.is_finalized = 0 THEN 'unmatched'
                    WHEN statements.source = 'imported' OR statements.import_file_name IS NOT NULL OR statements.import_fingerprint IS NOT NULL THEN 'matched'
                    WHEN stmt_accounts.account_type = 'cash' THEN 'manual_cash'
                    WHEN stmt_accounts.account_type = 'bank' THEN 'manual_statement'
                    ELSE 'matched'
                END as status,
                CASE
                    WHEN statements.id IS NOT NULL AND statements.is_finalized = 1 THEN 1
                    ELSE 0
                END as funding_action_blocked
            ")
            ->orderByDesc('credits.business_date')
            ->orderByDesc('credits.id')
            ->get();

        $given = collect();
        $returned = collect();
        $used = collect();

        $periodGiven = 0.0;
        $periodReturned = 0.0;
        $periodUsed = 0.0;

        foreach ($allCredits as $credit) {
            $dateStr = $credit->business_date ? substr((string) $credit->business_date, 0, 10) : '';
            $inPeriod = true;
            if ($startDate !== '' && $dateStr < $startDate) {
                $inPeriod = false;
            }
            if ($endDate !== '' && $dateStr > $endDate) {
                $inPeriod = false;
            }

            $credit->funding_action_blocked = (bool) $credit->funding_action_blocked;
            $credit->in_period = $inPeriod;

            if ($credit->type === 'in') {
                $given->push($credit);
                if ($inPeriod) {
                    $periodGiven += (float) $credit->amount;
                }
            } elseif ($credit->type === 'out' && $credit->purchase_invoice_id === null) {
                $returned->push($credit);
                if ($inPeriod) {
                    $periodReturned += (float) $credit->amount;
                }
            } elseif ($credit->type === 'out' && $credit->purchase_invoice_id !== null) {
                $used->push($credit);
                if ($inPeriod) {
                    $periodUsed += (float) $credit->amount;
                }
            }
        }

        $cumulativeGiven = (float) $given->sum('amount');
        $cumulativeReturned = (float) $returned->sum('amount');
        $cumulativeNetFunding = round($cumulativeGiven - $cumulativeReturned, 2);
        $cumulativeUsed = (float) $used->sum('amount');
        $expectedCash = round($cumulativeNetFunding - $cumulativeUsed, 2);

        return [
            'given' => $given,
            'returned' => $returned,
            'used' => $used,
            'cumulative' => [
                'cash_given' => $cumulativeGiven,
                'cash_returned' => $cumulativeReturned,
                'net_funding' => $cumulativeNetFunding,
                'cash_used_invoices' => $cumulativeUsed,
                'remaining_advance' => $expectedCash,
            ],
            'period' => [
                'cash_given' => round($periodGiven, 2),
                'cash_returned' => round($periodReturned, 2),
                'net_funding' => round($periodGiven - $periodReturned, 2),
                'cash_used_invoices' => round($periodUsed, 2),
            ],
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
