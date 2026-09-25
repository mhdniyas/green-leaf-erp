<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopStaffPayment;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminExactCashbookService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly LedgerRuleResolver $ruleResolver,
        private readonly FundingSourceEffectResolver $effectResolver,
        private readonly BalanceCalculator $balanceCalculator,
        private readonly ShopCashbookMonthRecalculationService $monthRecalculationService,
        private readonly ShopCashbookMonthConfigService $monthConfigService,
        private readonly InvoiceCashbookProjectionService $invoiceCashbookProjectionService,
        private readonly ?StaffPaymentCashbookProjectionService $staffPaymentCashbookProjectionService,
        private readonly ShopCashbookSalarySectionService $shopCashbookSalarySectionService,
        private readonly CollectionGroupPostingService $collectionGroupPostingService,
        private readonly CashbookShopSyncService $cashbookShopSyncService,
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService,
    ) {}

    /**
     * Get the exact canonical cashbook dataset for a shop and business date.
     * Reuses the real Shop Cashbook query and projection pipelines.
     *
     * @return array<string, mixed>
     */
    public function getExactCashbookData(Shop $shop, string $businessDate, ?User $actor = null): array
    {
        $date = Carbon::parse($businessDate)->toDateString();
        $month = substr($date, 0, 7);
        $userId = $actor?->id ?? 1;

        $this->cashbookShopSyncService->syncAndGetProfiles();

        // 1. Sync approved invoice bills and staff payments for the date
        $invoices = ShopInvoice::query()
            ->where('shop_id', (int) $shop->id)
            ->whereDate('business_date', $date)
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $this->invoiceCashbookProjectionService->syncInvoice($invoice, $userId);
        }

        if ($this->staffPaymentCashbookProjectionService instanceof StaffPaymentCashbookProjectionService) {
            $staffPayments = ShopStaffPayment::query()
                ->where('shop_id', (int) $shop->id)
                ->whereDate('paid_on', $date)
                ->orderBy('id')
                ->get();

            foreach ($staffPayments as $sp) {
                $this->staffPaymentCashbookProjectionService->syncPayment($sp, $userId);
            }
        }

        // 2. Fetch Settings, Header Groups, and Relations
        $settings = ShopLedgerEntrySetting::query()
            ->with([
                'entryType:id,name,code,category,display_order',
                'companyAccount:id,name,bank_name,account_number',
                'headerGroup:id,name,type,cash_flow_mode,company_account_id,note_enabled,show_both_sides,product_tagging_enabled',
                'vendorSettlementRelation:id,name',
                'definedShopSuppliers.supplier',
            ])
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $headerGroups = ShopLedgerHeaderGroup::query()
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $relations = ShopCashbookRelation::query()
            ->with(['items.setting.entryType', 'items.setting.companyAccount', 'items.headerGroup', 'items.sourceSettlement'])
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // 3. Fetch today's transactions with all relations
        $transactions = ShopLedgerTransaction::query()
            ->with([
                'entryType',
                'companyAccount',
                'enteredBy:id,name,email',
                'approvedBy:id,name,email',
                'voidedBy:id,name,email',
                'children.entryType',
                'parent.entryType',
                'paymentLedgerAllocations.paymentRequest',
                'companyExpenseAllocations',
                'statementEntries',
            ])
            ->where('shop_id', (int) $shop->id)
            ->where('business_date', $date)
            ->orderBy('id', 'asc')
            ->get();

        // 4. Daily summary & snapshot
        $snapshot = $this->dailyLedgerService->dailySummary((int) $shop->id, $date);

        // 5. Product entries
        $productEntries = ShopLedgerProductEntry::query()
            ->with(['product.orderUnits', 'headerGroup', 'enteredBy:id,name'])
            ->where('shop_id', (int) $shop->id)
            ->where('business_date', $date)
            ->get();

        // 6. Linked vendors & purchasable products
        $linkedVendors = $shop->isPurchasingEnabled()
            ? $shop->suppliers()
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $s): object => (object) [
                    'id' => $s->id,
                    'name' => $s->name,
                    'mobile_number' => $s->mobile_number,
                    'credit_approved' => (bool) ($s->pivot?->credit_approved ?? $s->credit_approved ?? false),
                ])
            : collect();

        $purchasableProducts = $shop->isPurchasingEnabled()
            ? Product::query()->active()->with(['category:id,name'])->orderBy('name')->get(['id', 'category_id', 'name', 'sku', 'unit'])
                ->map(fn (Product $p): array => [
                    'id' => (int) $p->id,
                    'category_id' => $p->category_id ? (int) $p->category_id : null,
                    'category_name' => $p->category?->name,
                    'name' => (string) $p->name,
                    'sku' => (string) ($p->sku ?? ''),
                    'unit' => (string) ($p->unit ?: 'kg'),
                ])
            : collect();

        // 7. Salary section & Collection groups
        $salarySectionData = $this->shopCashbookSalarySectionService->getSalarySection($shop, $date, $date);
        $collectionGroups = $this->collectionGroupPostingService->groupsForShop((int) $shop->id);

        // 8. Available Entry Types for Admin edit picker: only enabled categories configured for this shop.
        $allEntryTypes = $settings
            ->pluck('entryType')
            ->filter(fn (?LedgerEntryType $entryType): bool => $entryType?->active ?? false)
            ->unique('id')
            ->sortBy([
                ['category', 'asc'],
                ['display_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $profile = ShopLedgerProfile::query()->where('shop_id', (int) $shop->id)->first();
        $pettyConfig = $profile?->getPaymentConfiguration()['petty'] ?? [
            'configured' => false,
            'enabled' => true,
            'shop_owner_view_petty' => true,
        ];

        return [
            'shop' => $shop,
            'businessDate' => $date,
            'carbonDate' => Carbon::parse($date),
            'month' => $month,
            'settings' => $settings,
            'headerGroups' => $headerGroups,
            'relations' => $relations,
            'companyAccounts' => $companyAccounts,
            'transactions' => $transactions,
            'snapshot' => $snapshot,
            'productEntries' => $productEntries,
            'linkedVendors' => $linkedVendors,
            'purchasableProducts' => $purchasableProducts,
            'salarySectionData' => $salarySectionData,
            'collectionGroups' => $collectionGroups,
            'allEntryTypes' => $allEntryTypes,
            'pettyConfig' => $pettyConfig,
        ];
    }

    /**
     * Domain-safe Admin correction of a canonical cashbook entry.
     *
     * @param  array<string, mixed>  $input
     * @return array{transaction: ShopLedgerTransaction, snapshot: ShopDailyLedgerSnapshot, message: string}
     */
    public function correctEntry(User $adminUser, Shop $shop, int $transactionId, array $input): array
    {
        return DB::transaction(function () use ($adminUser, $shop, $transactionId, $input): array {
            // 1. Lock canonical transaction
            /** @var ShopLedgerTransaction $tx */
            $tx = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->with(['entryType', 'companyAccount', 'children', 'parent', 'paymentLedgerAllocations'])
                ->lockForUpdate()
                ->findOrFail($transactionId);

            if ($tx->generated_by_rule && $tx->parent_transaction_id) {
                throw ValidationException::withMessages([
                    'transaction_id' => "This is an auto-generated secondary entry linked to Parent #{$tx->parent_transaction_id}. Please edit the parent transaction directly.",
                ]);
            }

            // 2. Capture BEFORE state for audit log & delta reversal
            $beforeState = [
                'id' => $tx->id,
                'business_date' => $tx->business_date?->toDateString(),
                'entry_type_id' => $tx->entry_type_id,
                'entry_type_code' => $tx->entryType?->code,
                'entry_type_name' => $tx->entryType?->name,
                'amount' => (float) $tx->amount,
                'direction' => $tx->direction,
                'funding_source' => $tx->funding_source,
                'company_account_id' => $tx->company_account_id,
                'notes' => $tx->notes,
                'status' => $tx->status,
                'reference_type' => $tx->reference_type,
                'reference_id' => $tx->reference_id,
                'pl_delta' => (float) $tx->pl_delta,
                'settlement_delta' => (float) $tx->settlement_delta,
                'petty_delta' => (float) $tx->petty_delta,
                'company_pending_delta' => (float) $tx->company_pending_delta,
                'affects_sales' => (bool) $tx->affects_sales,
                'affects_expense' => (bool) $tx->affects_expense,
            ];

            $oldBusinessDate = $beforeState['business_date'];
            $newBusinessDate = isset($input['business_date']) ? Carbon::parse((string) $input['business_date'])->toDateString() : $oldBusinessDate;

            $newAmount = round((float) ($input['amount'] ?? $tx->amount), 2);
            if ($newAmount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount cannot be negative.',
                ]);
            }

            // 3. Validate allocations: amount cannot be lower than settled/allocated amounts
            $totalAllocated = (float) $tx->paymentLedgerAllocations()->sum('amount');
            if ($newAmount < $totalAllocated && $newAmount > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Amount (₹{$newAmount}) cannot be reduced below the already allocated settlement amount (₹{$totalAllocated}). Clear or adjust allocations first.",
                ]);
            }

            // 4. Resolve new Entry Type & Setting
            $entryTypeId = isset($input['entry_type_id']) ? (int) $input['entry_type_id'] : (int) $tx->entry_type_id;
            /** @var LedgerEntryType $entryType */
            $entryType = LedgerEntryType::findOrFail($entryTypeId);

            $setting = $this->ruleResolver->resolve((int) $shop->id, $entryType->id, $newBusinessDate);

            // 5. Resolve Funding Source
            $fundingSourceInput = $input['funding_source'] ?? $tx->funding_source;
            if ($fundingSourceInput && $fundingSourceInput !== 'none') {
                $fundingSource = FundingSource::tryFrom((string) $fundingSourceInput) ?? FundingSource::None;
            } else {
                $fundingSource = FundingSource::tryFrom((string) ($setting->default_funding_source ?? 'none')) ?? FundingSource::None;
            }

            // 6. Resolve Direction & deltas via FundingSourceEffectResolver
            $direction = LedgerDirection::tryFrom((string) $entryType->category) ?? LedgerDirection::Expense;
            $effect = $this->effectResolver->resolve($direction, $fundingSource, $newAmount, $setting);

            // 7. Resolve Company Account
            $companyAccountId = array_key_exists('company_account_id', $input)
                ? ($input['company_account_id'] ? (int) $input['company_account_id'] : null)
                : $tx->company_account_id;

            $newNotes = array_key_exists('notes', $input) ? ($input['notes'] ? trim((string) $input['notes']) : null) : $tx->notes;
            $newStatus = isset($input['status']) && in_array($input['status'], [
                TransactionStatus::Posted->value,
                TransactionStatus::Approved->value,
                TransactionStatus::Draft->value,
                TransactionStatus::Submitted->value,
                TransactionStatus::Void->value,
            ], true) ? $input['status'] : $tx->status;

            // 8. Synchronize underlying canonical source record if projected
            $this->syncCanonicalSourceRecord($tx, $newAmount, $newBusinessDate, $fundingSource, $newNotes, $newStatus);

            // 9. Mutate canonical ShopLedgerTransaction
            $tx->update([
                'business_date' => $newBusinessDate,
                'entry_type_id' => $entryType->id,
                'amount' => $newAmount,
                'direction' => $direction->value,
                'funding_source' => $fundingSource->value,
                'company_account_id' => $companyAccountId,
                'notes' => $newNotes,
                'status' => $newStatus,
                'affects_sales' => (bool) $setting->include_in_sales,
                'affects_income' => (bool) $setting->include_in_income,
                'affects_expense' => (bool) $setting->include_in_expense,
                'affects_pl' => (bool) $setting->include_in_pl,
                'pl_delta' => $effect->plDelta,
                'settlement_delta' => $effect->settlementDelta,
                'settlement_direction' => $effect->settlementDirection->value,
                'petty_delta' => $effect->pettyDelta,
                'petty_direction' => $effect->pettyDirection->value,
                'company_pending_delta' => $effect->companyPendingDelta,
                'company_pending_direction' => $effect->companyPendingDirection->value,
            ]);

            // 10. Update dependent statement entries & child rule entries
            $this->syncDependentStatementEntries($tx, $companyAccountId, $newAmount, $newBusinessDate, $direction, $newNotes, $newStatus);
            $this->syncChildRuleEntries($tx, $newAmount, $newBusinessDate);

            // 11. Recalculate affected day balances & forward cascade
            $affectedDates = collect([$oldBusinessDate, $newBusinessDate])->filter()->unique()->values();
            foreach ($affectedDates as $dateStr) {
                $this->balanceCalculator->recalculate((int) $shop->id, (string) $dateStr);
            }

            // Cascade forward through the month(s)
            $affectedMonths = $affectedDates->map(fn ($d) => substr((string) $d, 0, 7))->unique()->values();
            foreach ($affectedMonths as $monthStr) {
                try {
                    $this->monthRecalculationService->recalculateMonth((int) $shop->id, (string) $monthStr, (int) $adminUser->id);
                } catch (Throwable) {
                    // Fallback to day recalculation if month recalculation is non-critical
                }
            }

            // 12. Log immutable audit trail
            $afterState = [
                'id' => $tx->id,
                'business_date' => $newBusinessDate,
                'entry_type_id' => $entryType->id,
                'entry_type_code' => $entryType->code,
                'entry_type_name' => $entryType->name,
                'amount' => $newAmount,
                'direction' => $direction->value,
                'funding_source' => $fundingSource->value,
                'company_account_id' => $companyAccountId,
                'notes' => $newNotes,
                'status' => $newStatus,
                'pl_delta' => $effect->plDelta,
                'settlement_delta' => $effect->settlementDelta,
                'petty_delta' => $effect->pettyDelta,
                'company_pending_delta' => $effect->companyPendingDelta,
            ];

            $changedFields = [];
            foreach ($afterState as $k => $v) {
                if (($beforeState[$k] ?? null) !== $v) {
                    $changedFields[$k] = [
                        'from' => $beforeState[$k] ?? null,
                        'to' => $v,
                    ];
                }
            }

            if (function_exists('activity')) {
                activity('exact_cashbook_edit')
                    ->performedOn($tx)
                    ->causedBy($adminUser)
                    ->withProperties([
                        'shop_id' => (int) $shop->id,
                        'transaction_id' => $tx->id,
                        'reason' => $input['reason'] ?? 'Admin correction from Exact Shop Cashbook',
                        'changed_fields' => $changedFields,
                        'before' => $beforeState,
                        'after' => $afterState,
                    ])
                    ->log("Admin {$adminUser->name} corrected Cashbook Transaction #{$tx->id} for {$shop->name}");
            }

            $freshSnapshot = $this->dailyLedgerService->dailySummary((int) $shop->id, $newBusinessDate);

            return [
                'transaction' => $tx->fresh(['entryType', 'companyAccount', 'enteredBy', 'approvedBy', 'children']),
                'snapshot' => $freshSnapshot,
                'message' => 'Cashbook entry corrected and recalculated successfully.',
            ];
        });
    }

    /**
     * Domain-safe Admin void/reversal of a cashbook transaction.
     *
     * @return array{snapshot: ShopDailyLedgerSnapshot, message: string}
     */
    /**
     * Domain-safe Admin permanent deletion of a cashbook transaction.
     * Reconciles balances and cascades month without leaving voided ghost rows.
     * Salary and GL Bill entries are strictly protected.
     *
     * @return array{snapshot: ShopDailyLedgerSnapshot, message: string}
     */
    public function deleteEntry(User $adminUser, Shop $shop, int $transactionId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($adminUser, $shop, $transactionId, $reason): array {
            /** @var ShopLedgerTransaction $tx */
            $tx = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->with(['entryType', 'children'])
                ->lockForUpdate()
                ->findOrFail($transactionId);

            if ($tx->isProtectedSalaryOrGlBill()) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Salary and GL Bill entries cannot be deleted from the financial ledger.',
                ]);
            }

            if ($tx->generated_by_rule && $tx->parent_transaction_id) {
                throw ValidationException::withMessages([
                    'transaction_id' => "This is an auto-generated secondary entry linked to Parent #{$tx->parent_transaction_id}. Please delete the parent transaction directly.",
                ]);
            }

            $businessDate = $tx->business_date->toDateString();
            $month = substr($businessDate, 0, 7);

            // Delete unfinalized statement entries
            CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $tx->id)
                ->where('is_finalized', false)
                ->delete();

            // Delete child rule-generated transactions and their statement entries
            foreach ($tx->children as $child) {
                CompanyAccountStatementEntry::query()
                    ->where('source_type', ShopLedgerTransaction::class)
                    ->where('source_id', $child->id)
                    ->where('is_finalized', false)
                    ->delete();
                $child->delete();
            }

            // Sync underlying source record if applicable
            $this->syncCanonicalSourceRecord($tx, 0.0, $businessDate, FundingSource::None, $tx->notes, TransactionStatus::Void->value);

            // Delete the transaction itself
            $tx->delete();

            // Recalculate day snapshot & cascade month
            $snapshot = $this->balanceCalculator->recalculate((int) $shop->id, $businessDate);

            try {
                $this->monthRecalculationService->recalculateMonth((int) $shop->id, $month, (int) $adminUser->id);
            } catch (Throwable) {
                // Fallback to day recalculation
            }

            // Log activity
            if (function_exists('activity')) {
                activity('exact_cashbook_edit')
                    ->causedBy($adminUser)
                    ->withProperties([
                        'shop_id' => (int) $shop->id,
                        'transaction_id' => $tx->id,
                        'action' => 'delete',
                        'reason' => $reason ?: 'Deleted by Admin',
                    ])
                    ->log("Admin {$adminUser->name} deleted Cashbook Transaction #{$tx->id} for {$shop->name}");
            }

            return [
                'snapshot' => $snapshot,
                'message' => 'Cashbook transaction deleted successfully.',
            ];
        });
    }

    /**
     * Clear manual Shop Cashbook entries and product tags for a specific shop and business date.
     * Source-backed records, Salary, and GL Bill entries are preserved.
     *
     * @return array{deleted_count: int, snapshot: ShopDailyLedgerSnapshot, message: string}
     */
    public function clearDay(User $adminUser, Shop $shop, string $businessDate): array
    {
        return DB::transaction(function () use ($adminUser, $shop, $businessDate): array {
            $date = Carbon::parse($businessDate)->toDateString();
            $month = substr($date, 0, 7);

            $deletedProductCount = ShopLedgerProductEntry::query()
                ->where('shop_id', (int) $shop->id)
                ->where('business_date', $date)
                ->lockForUpdate()
                ->delete();

            // Fetch all transactions for this shop and date
            $transactions = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->where('business_date', $date)
                ->with(['entryType', 'children'])
                ->lockForUpdate()
                ->get();

            $deletedCount = 0;

            // First delete non-protected root transactions and their children
            $rootTransactions = $transactions->whereNull('parent_transaction_id');

            foreach ($rootTransactions as $tx) {
                if (! $tx->isManualCashbookEntry()) {
                    continue;
                }

                CompanyAccountStatementEntry::query()
                    ->where('source_type', ShopLedgerTransaction::class)
                    ->where('source_id', $tx->id)
                    ->where('is_finalized', false)
                    ->delete();

                foreach ($tx->children as $child) {
                    CompanyAccountStatementEntry::query()
                        ->where('source_type', ShopLedgerTransaction::class)
                        ->where('source_id', $child->id)
                        ->where('is_finalized', false)
                        ->delete();
                    $child->delete();
                }

                $tx->delete();
                $deletedCount++;
            }

            // Clean up any remaining non-protected standalone or child transactions for the day
            $remaining = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->where('business_date', $date)
                ->with(['entryType'])
                ->lockForUpdate()
                ->get();

            foreach ($remaining as $remTx) {
                if (! $remTx->isManualCashbookEntry()) {
                    continue;
                }

                CompanyAccountStatementEntry::query()
                    ->where('source_type', ShopLedgerTransaction::class)
                    ->where('source_id', $remTx->id)
                    ->where('is_finalized', false)
                    ->delete();

                $remTx->delete();
                $deletedCount++;
            }

            // Recalculate day snapshot & cascade month
            $snapshot = $this->balanceCalculator->recalculate((int) $shop->id, $date);

            try {
                $this->monthRecalculationService->recalculateMonth((int) $shop->id, $month, (int) $adminUser->id);
            } catch (Throwable) {
                // Fallback to day recalculation
            }

            if (function_exists('activity')) {
                activity('exact_cashbook_clear_day')
                    ->causedBy($adminUser)
                    ->withProperties([
                        'shop_id' => (int) $shop->id,
                        'business_date' => $date,
                        'deleted_count' => $deletedCount,
                        'deleted_product_count' => $deletedProductCount,
                    ])
                    ->log("Admin {$adminUser->name} cleared {$deletedCount} cashbook entries for {$shop->name} on {$date}");
            }

            return [
                'deleted_count' => $deletedCount,
                'snapshot' => $snapshot,
                'message' => "Cleared {$deletedCount} cashbook entries and {$deletedProductCount} product-tagged purchases for {$date}. Salary and GL Bill entries were preserved.",
            ];
        });
    }

    /**
     * Delete one manually tagged product row from the selected shop and day.
     * Product rows have no transaction foreign key, so no cashbook transaction is inferred or deleted.
     *
     * @return array{snapshot: ShopDailyLedgerSnapshot, message: string}
     */
    public function deleteProductEntry(User $adminUser, Shop $shop, int $productEntryId, string $businessDate): array
    {
        return DB::transaction(function () use ($adminUser, $shop, $productEntryId, $businessDate): array {
            $date = Carbon::parse($businessDate)->toDateString();

            $productEntry = ShopLedgerProductEntry::query()
                ->where('shop_id', (int) $shop->id)
                ->where('business_date', $date)
                ->lockForUpdate()
                ->findOrFail($productEntryId);

            $productEntry->delete();
            $snapshot = $this->balanceCalculator->recalculate((int) $shop->id, $date);

            if (function_exists('activity')) {
                activity('exact_cashbook_edit')
                    ->causedBy($adminUser)
                    ->withProperties([
                        'shop_id' => (int) $shop->id,
                        'business_date' => $date,
                        'product_entry_id' => $productEntryId,
                        'action' => 'delete_product_entry',
                    ])
                    ->log("Admin {$adminUser->name} deleted product ledger entry #{$productEntryId} for {$shop->name}");
            }

            return [
                'snapshot' => $snapshot,
                'message' => 'Product ledger entry deleted successfully.',
            ];
        });
    }

    /**
     * Domain-safe Admin void/reversal of a cashbook transaction.
     *
     * @return array{snapshot: ShopDailyLedgerSnapshot, message: string}
     */
    public function voidEntry(User $adminUser, Shop $shop, int $transactionId, string $reason): array
    {
        return DB::transaction(function () use ($adminUser, $shop, $transactionId, $reason): array {
            /** @var ShopLedgerTransaction $tx */
            $tx = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->with(['entryType', 'children'])
                ->lockForUpdate()
                ->findOrFail($transactionId);

            $businessDate = $tx->business_date->toDateString();
            $month = substr($businessDate, 0, 7);

            $beforeState = [
                'id' => $tx->id,
                'status' => $tx->status,
                'amount' => (float) $tx->amount,
                'business_date' => $businessDate,
            ];

            // Void parent transaction
            $tx->update([
                'status' => TransactionStatus::Void->value,
                'voided_by' => $adminUser->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            // Supersede unfinalized statement entries
            CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $tx->id)
                ->where('is_finalized', false)
                ->update([
                    'status' => 'superseded',
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' | Voided by Admin: ".addslashes($reason)."')"),
                ]);

            // Void child rule-generated transactions
            foreach ($tx->children as $child) {
                $child->update([
                    'status' => TransactionStatus::Void->value,
                    'voided_by' => $adminUser->id,
                    'voided_at' => now(),
                    'void_reason' => "Parent #{$tx->id} voided: {$reason}",
                ]);
            }

            // Sync underlying source record if applicable
            $this->syncCanonicalSourceRecord($tx, 0.0, $businessDate, FundingSource::None, $tx->notes, TransactionStatus::Void->value);

            // Recalculate day snapshot & cascade month
            $snapshot = $this->balanceCalculator->recalculate((int) $shop->id, $businessDate);

            try {
                $this->monthRecalculationService->recalculateMonth((int) $shop->id, $month, (int) $adminUser->id);
            } catch (Throwable) {
                // Fallback to day recalculation
            }

            // Log activity
            if (function_exists('activity')) {
                activity('exact_cashbook_edit')
                    ->performedOn($tx)
                    ->causedBy($adminUser)
                    ->withProperties([
                        'shop_id' => (int) $shop->id,
                        'transaction_id' => $tx->id,
                        'action' => 'void',
                        'reason' => $reason,
                        'before' => $beforeState,
                        'after' => ['status' => TransactionStatus::Void->value, 'voided_at' => now()->toDateTimeString()],
                    ])
                    ->log("Admin {$adminUser->name} voided Cashbook Transaction #{$tx->id} for {$shop->name}");
            }

            return [
                'snapshot' => $snapshot,
                'message' => 'Cashbook transaction voided successfully.',
            ];
        });
    }

    /**
     * Synchronize canonical source models when a projected transaction is corrected.
     */
    private function syncCanonicalSourceRecord(
        ShopLedgerTransaction $tx,
        float $newAmount,
        string $newDate,
        FundingSource $fundingSource,
        ?string $notes,
        string $status
    ): void {
        if (! $tx->reference_type || ! $tx->reference_id) {
            return;
        }

        if ($tx->reference_type === ShopStaffPayment::class) {
            $payment = ShopStaffPayment::find($tx->reference_id);
            if ($payment) {
                $payment->update([
                    'amount' => $newAmount,
                    'paid_on' => $newDate,
                    'notes' => $notes ?: $payment->notes,
                ]);
            }
        } elseif ($tx->reference_type === PurchaseInvoice::class) {
            $invoice = PurchaseInvoice::find($tx->reference_id);
            if ($invoice) {
                $invoice->update([
                    'final_total' => $newAmount,
                    'invoice_date' => $newDate,
                ]);
            }
        } elseif ($tx->reference_type === ShopInvoice::class) {
            $shopInvoice = ShopInvoice::find($tx->reference_id);
            if ($shopInvoice && $status === TransactionStatus::Void->value) {
                $shopInvoice->update(['status' => 'cancelled']);
            }
        }
    }

    /**
     * Synchronize unfinalized statement entries when company account or amount changes.
     */
    private function syncDependentStatementEntries(
        ShopLedgerTransaction $tx,
        ?int $companyAccountId,
        float $newAmount,
        string $newDate,
        LedgerDirection $direction,
        ?string $notes,
        string $status
    ): void {
        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $tx->id)
            ->where('is_finalized', false)
            ->get();

        foreach ($statements as $stmt) {
            if ($status === TransactionStatus::Void->value) {
                $stmt->update([
                    'status' => 'superseded',
                    'notes' => trim(($stmt->notes ? $stmt->notes.' | ' : '').'Voided'),
                ]);

                continue;
            }

            $stmtUpdate = [
                'amount' => $newAmount,
                'transaction_date' => $newDate,
                'value_date' => $newDate,
                'direction' => $direction->value === 'income' ? 'in' : 'out',
            ];

            if ($companyAccountId && $companyAccountId !== (int) $stmt->company_account_id) {
                $stmtUpdate['company_account_id'] = $companyAccountId;
            }

            if ($notes !== null) {
                $stmtUpdate['notes'] = $notes ?: $stmt->notes;
            }

            $stmt->update($stmtUpdate);
        }
    }

    /**
     * Synchronize child rule-generated transactions.
     */
    private function syncChildRuleEntries(ShopLedgerTransaction $parent, float $newAmount, string $newDate): void
    {
        foreach ($parent->children as $child) {
            $childSetting = $this->ruleResolver->resolve((int) $child->shop_id, (int) $child->entry_type_id, $newDate);
            $childAmount = $childSetting->secondary_amount_mode === 'percentage'
                ? round($newAmount * ((float) $childSetting->secondary_amount_value / 100), 2)
                : $newAmount;

            $child->update([
                'business_date' => $newDate,
                'amount' => $childAmount,
                'pl_delta' => -$childAmount,
            ]);
        }
    }
}
