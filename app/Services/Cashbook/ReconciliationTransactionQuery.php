<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\DirectCompanySale;
use App\Models\PayrollPayment;
use App\Models\PurchaserCredit;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\VendorSettlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ReconciliationTransactionQuery
{
    /** @return LengthAwarePaginator<int, object> */
    public function paginate(Request $request, string $monthStart, string $monthEnd): LengthAwarePaginator
    {
        return $this->filteredQuery($request, $monthStart, $monthEnd)
            ->orderByDesc('transaction_date')
            ->orderByDesc('source_id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (object $row): object => $this->decorate($row));
    }

    /** @return array{needs_review:int,suggested:int,reconciled:int} */
    public function counts(Request $request, string $monthStart, string $monthEnd): array
    {
        $rows = (clone $this->filteredQuery($request, $monthStart, $monthEnd, includeStatus: false))
            ->selectRaw('reconciliation_status, count(*) as aggregate')
            ->groupBy('reconciliation_status')
            ->pluck('aggregate', 'reconciliation_status');

        return [
            'needs_review' => (int) ($rows['NEEDS_REVIEW'] ?? 0),
            'suggested' => 0,
            'reconciled' => (int) ($rows['RECONCILED'] ?? 0),
        ];
    }

    /** @return Collection<int, object> */
    public function unreconciledChunk(Request $request, string $monthStart, string $monthEnd, int $offset, int $limit): Collection
    {
        return $this->filteredQuery($request, $monthStart, $monthEnd, includeStatus: false)
            ->where('reconciliation_status', 'NEEDS_REVIEW')
            ->orderByDesc('transaction_date')
            ->orderByDesc('source_id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (object $row): object => $this->decorate($row));
    }

    public function unreconciledCount(Request $request, string $monthStart, string $monthEnd): int
    {
        return $this->filteredQuery($request, $monthStart, $monthEnd, includeStatus: false)
            ->where('reconciliation_status', 'NEEDS_REVIEW')
            ->count();
    }

    public function reconciledCount(Request $request, string $monthStart, string $monthEnd): int
    {
        return $this->filteredQuery($request, $monthStart, $monthEnd, includeStatus: false)
            ->where('reconciliation_status', 'RECONCILED')
            ->count();
    }

    /** @return array<string, string> */
    public function inTypes(): array
    {
        return [
            'all' => 'All',
            'shop_collection' => 'Shop Collections',
            'shop_payment' => 'Shop Payments',
            'direct_sale' => 'Direct Sales',
            'other_income' => 'Other Income',
        ];
    }

    /** @return array<string, string> */
    public function outTypes(): array
    {
        return [
            'all' => 'All',
            'purchaser_funding' => 'Purchaser Funding',
            'vendor_payment' => 'Vendor Payments',
            'company_payable' => 'Company Payables',
            'expense' => 'Expenses',
            'petty_funding' => 'Petty Funding',
            'payroll' => 'Payroll',
        ];
    }

    private function filteredQuery(Request $request, string $monthStart, string $monthEnd, bool $includeStatus = true): Builder
    {
        $direction = $this->direction($request);
        $status = $this->status($request);
        $type = (string) $request->input('type', 'all');
        $accountId = (int) $request->input('company_account_id', 0);
        $search = trim((string) $request->input('search', ''));

        return DB::query()
            ->fromSub($this->baseUnion(), 'reconciliation_transactions')
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->when($direction !== 'all', fn (Builder $query) => $query->where('direction', $direction))
            ->when($includeStatus && $status !== 'all', fn (Builder $query) => $query->where('reconciliation_status', $status))
            ->when($type !== 'all', fn (Builder $query) => $query->where('transaction_type_key', $type))
            ->when($accountId > 0, fn (Builder $query) => $query->where('company_account_id', $accountId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $like = '%'.$search.'%';
                    $sub->where('party_name', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('description', 'like', $like);

                    if (is_numeric($search)) {
                        $sub->orWhere('amount', round((float) $search, 2));
                    }
                });
            });
    }

    private function baseUnion(): Builder
    {
        return $this->shopCollections()
            ->unionAll($this->shopPayments())
            ->unionAll($this->directSales())
            ->unionAll($this->companyAccounting('income'))
            ->unionAll($this->companyAccounting('expense'))
            ->unionAll($this->purchaserFunding())
            ->unionAll($this->vendorSettlements())
            ->unionAll($this->companyPayables())
            ->unionAll($this->pettyFunding())
            ->unionAll($this->payroll());
    }

    private function shopCollections(): Builder
    {
        return DB::table('shop_ledger_transactions as source')
            ->leftJoin('shops as parties', 'parties.id', '=', 'source.shop_id')
            ->leftJoin('ledger_entry_types as types', 'types.id', '=', 'source.entry_type_id')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('journal_entries as journals', function ($join): void {
                $join->on('journals.source_id', '=', 'source.id')
                    ->where('journals.source_type', ShopLedgerTransaction::class);
            })
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', ShopLedgerTransaction::class)
                    ->where('statements.is_finalized', 1);
            })
            ->where('source.direction', 'income')
            ->whereNotNull('source.company_account_id')
            ->whereNotIn('source.status', ['void', 'voided', 'reversed'])
            ->selectRaw($this->selectSql(
                'journals.id',
                'parties.name',
                'source.amount',
                'source.business_date',
                'source.reference_id',
                'types.name'
            ), [
                ShopLedgerTransaction::class,
                'in',
                'shop_collection',
                'Shop Collection',
                'NEEDS_REVIEW',
            ]);
    }

    private function shopPayments(): Builder
    {
        return DB::table('shop_invoice_payment_requests as source')
            ->leftJoin('shops as parties', 'parties.id', '=', 'source.shop_id')
            ->leftJoin('journal_entries as journals', function ($join): void {
                $join->on('journals.source_id', '=', 'source.id')
                    ->where('journals.source_type', ShopInvoicePaymentRequest::class);
            })
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', ShopInvoicePaymentRequest::class)
                    ->where('statements.is_finalized', 1);
            })
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'statements.company_account_id')
            ->where('source.status', '!=', 'rejected')
            ->selectRaw($this->selectSql('journals.id', 'parties.name', 'source.requested_amount', 'source.payment_date', 'source.payment_reference', 'source.shop_note'), [
                ShopInvoicePaymentRequest::class,
                'in',
                'shop_payment',
                'Shop Payment',
                'NEEDS_REVIEW',
            ]);
    }

    private function directSales(): Builder
    {
        return DB::table('direct_company_sales as source')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', DirectCompanySale::class)
                    ->where('statements.is_finalized', 1);
            })
            ->selectRaw($this->selectSql('source.journal_entry_id', 'source.customer_name', 'source.amount', 'source.business_date', 'source.reference', 'source.note'), [
                DirectCompanySale::class,
                'in',
                'direct_sale',
                'Direct Sale',
                'NEEDS_REVIEW',
            ]);
    }

    private function companyAccounting(string $type): Builder
    {
        return DB::table('company_accounting_entries as source')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', CompanyAccountingEntry::class)
                    ->where('statements.is_finalized', 1);
            })
            ->where('source.type', $type)
            ->whereNotNull('source.company_account_id')
            ->selectRaw($this->selectSql('source.journal_entry_id', 'source.description', 'source.amount', 'source.business_date', 'source.reference', 'source.description'), [
                CompanyAccountingEntry::class,
                $type === 'income' ? 'in' : 'out',
                $type === 'income' ? 'other_income' : 'expense',
                $type === 'income' ? 'Other Income' : 'Expense',
                'NEEDS_REVIEW',
            ]);
    }

    private function purchaserFunding(): Builder
    {
        return DB::table('purchaser_credits as source')
            ->leftJoin('users as parties', 'parties.id', '=', 'source.purchaser_id')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('journal_entries as journals', function ($join): void {
                $join->on('journals.source_id', '=', 'source.id')
                    ->where('journals.source_type', PurchaserCredit::class)
                    ->where('journals.source_event', 'purchaser_funding');
            })
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', PurchaserCredit::class)
                    ->where('statements.is_finalized', 1);
            })
            ->where('source.type', 'in')
            ->selectRaw($this->selectSql('journals.id', 'parties.name'), [
                PurchaserCredit::class,
                'out',
                'purchaser_funding',
                'Purchaser Funding',
                'NEEDS_REVIEW',
            ]);
    }

    private function vendorSettlements(): Builder
    {
        return DB::table('vendor_settlements as source')
            ->leftJoin('suppliers as parties', 'parties.id', '=', 'source.supplier_id')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', VendorSettlement::class)
                    ->where('statements.is_finalized', 1);
            })
            ->where('source.actual_payment_amount', '>', 0)
            ->selectRaw($this->selectSql('source.journal_entry_id', 'parties.name', 'source.actual_payment_amount', 'source.payment_date', 'source.reference', 'source.note'), [
                VendorSettlement::class,
                'out',
                'vendor_payment',
                'Vendor Payment',
                'NEEDS_REVIEW',
            ]);
    }

    private function companyPayables(): Builder
    {
        return DB::table('company_payable_settlements as source')
            ->leftJoin('shops as parties', 'parties.id', '=', 'source.shop_id')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.payment_account_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', CompanyPayableSettlement::class)
                    ->where('statements.is_finalized', 1);
            })
            ->selectRaw($this->selectSql('source.journal_entry_id', 'parties.name', 'source.amount', 'source.settlement_date', 'source.reference', 'source.notes'), [
                CompanyPayableSettlement::class,
                'out',
                'company_payable',
                'Company Payable',
                'NEEDS_REVIEW',
            ]);
    }

    private function pettyFunding(): Builder
    {
        return DB::table('shop_ledger_transactions as source')
            ->leftJoin('shops as parties', 'parties.id', '=', 'source.shop_id')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('journal_entries as journals', function ($join): void {
                $join->on('journals.source_id', '=', 'source.id')
                    ->where('journals.source_type', ShopLedgerTransaction::class);
            })
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', ShopLedgerTransaction::class)
                    ->where('statements.is_finalized', 1);
            })
            ->where('source.funding_source', 'company')
            ->where('source.petty_direction', 'in')
            ->selectRaw($this->selectSql('journals.id', 'parties.name', 'source.amount', 'source.business_date', 'source.reference_id', 'source.notes'), [
                ShopLedgerTransaction::class,
                'out',
                'petty_funding',
                'Petty Funding',
                'NEEDS_REVIEW',
            ]);
    }

    private function payroll(): Builder
    {
        return DB::table('payroll_payments as source')
            ->leftJoin('employees as parties', 'parties.id', '=', 'source.employee_id')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'source.company_account_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'source.id')
                    ->where('statements.source_type', PayrollPayment::class)
                    ->where('statements.is_finalized', 1);
            })
            ->selectRaw($this->selectSql('source.journal_entry_id', 'parties.name', 'source.amount', 'source.paid_on', 'source.reference', 'source.notes'), [
                PayrollPayment::class,
                'out',
                'payroll',
                'Payroll',
                'NEEDS_REVIEW',
            ]);
    }

    private function selectSql(
        string $journalColumn,
        string $partyColumn,
        string $amountColumn = 'source.amount',
        string $dateColumn = 'source.business_date',
        string $referenceColumn = 'source.reference',
        string $descriptionColumn = 'source.description',
    ): string {
        return "
            ? as source_type,
            source.id as source_id,
            {$journalColumn} as journal_entry_id,
            ? as direction,
            ? as transaction_type_key,
            ? as transaction_type,
            {$dateColumn} as transaction_date,
            {$amountColumn} as amount,
            accounts.id as company_account_id,
            accounts.name as company_account_name,
            COALESCE({$partyColumn}, {$descriptionColumn}, {$referenceColumn}, 'Company transaction') as party_name,
            CAST({$referenceColumn} AS CHAR) as reference,
            CAST(COALESCE({$descriptionColumn}, {$referenceColumn}) AS CHAR) as description,
            CASE WHEN statements.id IS NOT NULL THEN 'RECONCILED' ELSE ? END as reconciliation_status,
            statements.id as statement_entry_id,
            statements.public_uuid as statement_public_uuid,
            statements.transaction_date as statement_date,
            statements.reference as statement_reference,
            statements.narration as statement_narration,
            statements.amount as statement_amount,
            statements.status as statement_status
        ";
    }

    private function decorate(object $row): object
    {
        $journalEntryId = (int) ($row->journal_entry_id ?? 0);
        $row->find_kind = match ($row->source_type) {
            ShopInvoicePaymentRequest::class => 'shop_payment',
            ShopLedgerTransaction::class => 'shop_ledger',
            default => 'journal',
        };
        $row->source_ref = match ($row->source_type) {
            ShopInvoicePaymentRequest::class => $this->encodedRouteKey('shop-payment', (int) $row->source_id),
            ShopLedgerTransaction::class => $this->encodedRouteKey('shop-ledger', (int) $row->source_id),
            default => $journalEntryId > 0 ? $this->encodedRouteKey('journal-entry', $journalEntryId) : '',
        };
        $row->journal_ref = $journalEntryId > 0 ? $this->encodedRouteKey('journal-entry', $journalEntryId) : null;
        $row->statement_match_summary = $row->statement_entry_id
            ? trim(($row->company_account_name ?: 'Company Account').' • '.$row->statement_date.' • Ref '.($row->statement_reference ?: '—'))
            : null;

        return $row;
    }

    private function encodedRouteKey(string $type, int $id): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString($type.':'.$id)), '+/', '-_'), '=');
    }

    private function direction(Request $request): string
    {
        $direction = (string) $request->input('direction', 'all');

        return in_array($direction, ['all', 'in', 'out'], true) ? $direction : 'all';
    }

    private function status(Request $request): string
    {
        $status = (string) $request->input('status', 'NEEDS_REVIEW');

        return in_array($status, ['all', 'NEEDS_REVIEW', 'SUGGESTED', 'RECONCILED'], true) ? $status : 'NEEDS_REVIEW';
    }
}
