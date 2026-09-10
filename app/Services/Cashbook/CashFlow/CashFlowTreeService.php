<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow;

use App\DTOs\Cashbook\CashFlowTreeNode;
use App\DTOs\Cashbook\MoneyMovement;
use App\DTOs\Cashbook\MonthlyReconciliationSummary;
use App\Services\Cashbook\CashFlow\Sources\BankCashFlowSource;
use App\Services\Cashbook\CashFlow\Sources\EmployeeCashFlowSource;
use App\Services\Cashbook\CashFlow\Sources\JournalCashFlowSource;
use App\Services\Cashbook\CashFlow\Sources\PurchaserCashFlowSource;
use App\Services\Cashbook\CashFlow\Sources\ShopCashFlowSource;
use App\Services\Cashbook\CashFlow\Sources\VendorCreditCashFlowSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowTreeService
{
    public function __construct(
        protected ShopCashFlowSource $shopSource,
        protected PurchaserCashFlowSource $purchaserSource,
        protected VendorCreditCashFlowSource $vendorCreditSource,
        protected BankCashFlowSource $bankSource,
        protected EmployeeCashFlowSource $employeeSource,
        protected JournalCashFlowSource $journalSource,
    ) {}

    /**
     * Build the complete monthly cash flow tree and reconciliation summary.
     *
     * @param  array<string, mixed>  $filters
     * @return array{tree: CashFlowTreeNode, summary: MonthlyReconciliationSummary, movements: Collection<int, MoneyMovement>}
     */
    public function build(string $month, array $filters = []): array
    {
        $startDate = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $endDate = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        // 1. Fetch normalized movements across all 6 adapters
        $shopMovements = $this->shopSource->forMonth($month, $filters);
        $purchaserMovements = $this->purchaserSource->forMonth($month, $filters);
        $vendorCreditMovements = $this->vendorCreditSource->forMonth($month, $filters);
        $bankMovements = $this->bankSource->forMonth($month, $filters);
        $employeeMovements = $this->employeeSource->forMonth($month, $filters);
        $journalMovements = $this->journalSource->forMonth($month, $filters);

        $allMovements = collect()
            ->concat($shopMovements)
            ->concat($purchaserMovements)
            ->concat($vendorCreditMovements)
            ->concat($bankMovements)
            ->concat($employeeMovements)
            ->concat($journalMovements);

        // 2. Fetch opening balances before month start
        $bankOpenings = $this->bankSource->openingBalances($startDate, $filters);
        $shopOpenings = $this->shopSource->openingBalances($startDate, $filters);
        $purchaserOpenings = $this->purchaserSource->openingBalances($startDate, $filters);
        $vendorOpenings = $this->vendorCreditSource->openingBalances($startDate, $filters);
        $employeeOpenings = $this->employeeSource->openingBalances($startDate, $filters);

        // 3. Build sub-trees
        $banksNode = $this->buildBanksNode($bankMovements, $bankOpenings);
        $shopsNode = $this->buildShopsNode($shopMovements, $shopOpenings);
        $purchasersNode = $this->buildPurchasersNode($purchaserMovements, $purchaserOpenings);
        $vendorsNode = $this->buildVendorsNode($vendorCreditMovements, $vendorOpenings);
        $employeesNode = $this->buildEmployeesNode($employeeMovements, $employeeOpenings);
        $reviewNode = $this->buildNeedsReviewNode($allMovements);

        // 4. Build Root Tree Node
        $rootOpening = $banksNode->openingBalance;
        $rootIn = $banksNode->totalIn + $shopsNode->totalIn;
        $rootOut = $banksNode->totalOut + $shopsNode->totalOut;
        $rootClosing = $banksNode->closingBalance;

        $root = new CashFlowTreeNode(
            id: 'root',
            title: 'GREEN LEAF / MAIN COMPANY',
            subtitle: "Monthly Cash Flow Tree — {$month}",
            badge: 'Company Root',
            entityType: 'root',
            openingBalance: $rootOpening,
            totalIn: $rootIn,
            totalOut: $rootOut,
            closingBalance: $rootClosing,
            netChange: $rootClosing - $rootOpening,
            children: [
                $banksNode,
                $shopsNode,
                $purchasersNode,
                $vendorsNode,
                $employeesNode,
                $reviewNode,
            ],
            movements: $allMovements->all(),
            metadata: [
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        // 5. Build Monthly Reconciliation Summary
        $summary = $this->calculateReconciliationSummary(
            $root,
            $banksNode,
            $shopsNode,
            $purchasersNode,
            $employeesNode,
            $reviewNode,
            $allMovements
        );

        return [
            'tree' => $root,
            'summary' => $summary,
            'movements' => $allMovements,
        ];
    }

    /**
     * Build the Banks branch.
     *
     * @param  Collection<int, MoneyMovement>  $movements
     * @param  array<string, mixed>  $openings
     */
    protected function buildBanksNode(Collection $movements, array $openings): CashFlowTreeNode
    {
        $bankMovements = $movements->where('sourceType', 'bank');
        $childNodes = [];

        $totalOpening = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $totalClosing = 0.0;

        foreach ($openings as $accId => $data) {
            $accMovements = $bankMovements->filter(fn (MoneyMovement $m): bool => ($m->metadata['account_id'] ?? null) === $accId
                || ($m->metadata['from_account_id'] ?? null) === $accId
                || ($m->metadata['to_account_id'] ?? null) === $accId
                || $m->fromEntityId === $accId
                || $m->toEntityId === $accId);

            $opening = (float) ($data['opening_balance'] ?? 0.0);
            $inAmt = 0.0;
            $outAmt = 0.0;
            $transferIn = 0.0;
            $transferOut = 0.0;

            foreach ($accMovements as $m) {
                if ($m->isInternalTransfer()) {
                    if ($m->toEntityId === $accId) {
                        $transferIn += $m->amount;
                    }
                    if ($m->fromEntityId === $accId) {
                        $transferOut += $m->amount;
                    }
                } else {
                    if ($m->toEntityId === $accId || $m->movementType === 'bank_deposit') {
                        $inAmt += $m->amount;
                    }
                    if ($m->fromEntityId === $accId || $m->movementType === 'bank_withdrawal') {
                        $outAmt += $m->amount;
                    }
                }
            }

            $netIn = $inAmt + $transferIn;
            $netOut = $outAmt + $transferOut;
            $closing = $opening + $netIn - $netOut;

            $totalOpening += $opening;
            $totalIn += $inAmt; // excluding internal transfer in aggregate
            $totalOut += $outAmt;
            $totalClosing += $closing;

            $childNodes[] = new CashFlowTreeNode(
                id: "bank_{$accId}",
                title: $data['name'],
                subtitle: ucfirst($data['account_type'] ?? 'Bank'),
                badge: 'Account #'.$accId,
                entityType: 'bank',
                openingBalance: $opening,
                totalIn: $netIn,
                totalOut: $netOut,
                closingBalance: $closing,
                netChange: $closing - $opening,
                children: [],
                movements: $accMovements->values()->all(),
                metadata: [
                    'account_id' => $accId,
                    'transfer_in' => round($transferIn, 2),
                    'transfer_out' => round($transferOut, 2),
                ]
            );
        }

        return new CashFlowTreeNode(
            id: 'branch_banks',
            title: 'BANKS & COMPANY ACCOUNTS',
            subtitle: count($childNodes).' Tracked Accounts',
            badge: 'Company Vaults',
            entityType: 'bank_group',
            openingBalance: $totalOpening,
            totalIn: $totalIn,
            totalOut: $totalOut,
            closingBalance: $totalClosing,
            netChange: $totalClosing - $totalOpening,
            children: $childNodes,
            movements: $bankMovements->values()->all()
        );
    }

    /**
     * Build the Shops branch.
     *
     * @param  Collection<int, MoneyMovement>  $movements
     * @param  array<string, mixed>  $openings
     */
    protected function buildShopsNode(Collection $movements, array $openings): CashFlowTreeNode
    {
        $shopMovements = $movements->where('sourceType', 'shop');
        $childNodes = [];

        $totalOpening = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $totalClosing = 0.0;

        foreach ($openings as $shopId => $data) {
            $currMovements = $shopMovements->filter(fn (MoneyMovement $m): bool => ($m->metadata['shop_id'] ?? null) === $shopId
                || $m->fromEntityId === $shopId
                || $m->toEntityId === $shopId);

            $opening = (float) ($data['opening_position'] ?? 0.0);
            $salesAmt = (float) $currMovements->where('movementType', 'shop_sales')->sum('amount');
            $expenseAmt = (float) $currMovements->where('movementType', 'shop_expense')->sum('amount');
            $settlementSent = (float) $currMovements->whereIn('movementType', ['shop_settlement', 'shop_payment_allocation'])->sum('amount');
            $settlementReceived = (float) $currMovements->whereIn('movementType', ['company_settlement', 'company_to_shop'])->sum('amount');

            $inAmt = $salesAmt + $settlementReceived;
            $outAmt = $expenseAmt + $settlementSent;
            $closing = $opening + $inAmt - $outAmt;

            $totalOpening += $opening;
            $totalIn += $salesAmt;
            $totalOut += $expenseAmt;
            $totalClosing += $closing;

            // Sub-branches for Shop (only add if non-zero)
            $subChildren = [];
            if ($salesAmt > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "shop_{$shopId}_sales",
                    title: 'Sales & Collections',
                    subtitle: 'External Customer Sales',
                    badge: 'Sales',
                    entityType: 'shop_sales',
                    openingBalance: 0.0,
                    totalIn: $salesAmt,
                    totalOut: 0.0,
                    closingBalance: $salesAmt,
                    netChange: $salesAmt,
                    children: [],
                    movements: $currMovements->where('movementType', 'shop_sales')->values()->all()
                );
            }

            if ($expenseAmt > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "shop_{$shopId}_expenses",
                    title: 'Shop Operating Expenses',
                    subtitle: 'Paid from Shop Cash / Petty',
                    badge: 'Expenses',
                    entityType: 'shop_expense',
                    openingBalance: 0.0,
                    totalIn: 0.0,
                    totalOut: $expenseAmt,
                    closingBalance: -$expenseAmt,
                    netChange: -$expenseAmt,
                    children: [],
                    movements: $currMovements->where('movementType', 'shop_expense')->values()->all()
                );
            }

            if ($settlementSent > 0.001 || $settlementReceived > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "shop_{$shopId}_settlements",
                    title: 'Company Settlements & Mappings',
                    subtitle: 'Direct Bank Deposits / Paytm / Net Settled',
                    badge: 'Settlements',
                    entityType: 'shop_settlement',
                    openingBalance: 0.0,
                    totalIn: $settlementReceived,
                    totalOut: $settlementSent,
                    closingBalance: $settlementReceived - $settlementSent,
                    netChange: $settlementReceived - $settlementSent,
                    children: [],
                    movements: $currMovements->whereIn('movementType', ['shop_settlement', 'company_settlement', 'shop_payment_allocation', 'company_to_shop'])->values()->all()
                );
            }

            $childNodes[] = new CashFlowTreeNode(
                id: "shop_{$shopId}",
                title: $data['shop_name'],
                subtitle: 'Shop Cash & Settlement Position',
                badge: 'Shop #'.$shopId,
                entityType: 'shop',
                openingBalance: $opening,
                totalIn: $inAmt,
                totalOut: $outAmt,
                closingBalance: $closing,
                netChange: $closing - $opening,
                children: $subChildren,
                movements: $currMovements->values()->all(),
                metadata: ['shop_id' => $shopId]
            );
        }

        return new CashFlowTreeNode(
            id: 'branch_shops',
            title: 'SHOPS (CASH & SETTLEMENTS)',
            subtitle: count($childNodes).' Configured Shops',
            badge: 'Retail Branches',
            entityType: 'shop_group',
            openingBalance: $totalOpening,
            totalIn: $totalIn,
            totalOut: $totalOut,
            closingBalance: $totalClosing,
            netChange: $totalClosing - $totalOpening,
            children: $childNodes,
            movements: $shopMovements->values()->all()
        );
    }

    /**
     * Build the Purchasers branch.
     *
     * @param  Collection<int, MoneyMovement>  $movements
     * @param  array<string, mixed>  $openings
     */
    protected function buildPurchasersNode(Collection $movements, array $openings): CashFlowTreeNode
    {
        $purchaserMovements = $movements->where('sourceType', 'purchaser');
        $childNodes = [];

        $totalOpening = 0.0;
        $totalFunding = 0.0;
        $totalSpent = 0.0;
        $totalClosing = 0.0;

        // Collect all unique purchaser IDs from openings and movements
        $purchaserIds = collect(array_keys($openings))
            ->concat($purchaserMovements->pluck('fromEntityId')->filter())
            ->concat($purchaserMovements->pluck('toEntityId')->filter())
            ->unique()
            ->values();

        foreach ($purchaserIds as $pId) {
            $pId = (int) $pId;
            $currMovements = $purchaserMovements->filter(fn (MoneyMovement $m): bool => ($m->metadata['purchaser_id'] ?? null) === $pId
                || ($m->toEntityType === 'purchaser' && $m->toEntityId === $pId)
                || ($m->fromEntityType === 'purchaser' && $m->fromEntityId === $pId));

            $opening = (float) ($openings[$pId]['opening_advance'] ?? 0.0);
            $pName = $openings[$pId]['purchaser_name'] ?? ($currMovements->first()?->toEntityName ?? ('Purchaser #'.$pId));

            $fundingAmt = (float) $currMovements->where('movementType', 'purchaser_funding')->sum('amount');
            $purchasesAmt = (float) $currMovements->where('movementType', 'cash_purchase')->sum('amount');
            $expensesAmt = (float) $currMovements->where('movementType', 'purchaser_expense')->sum('amount');
            $returnsAmt = (float) $currMovements->where('movementType', 'purchaser_return')->sum('amount');

            $inAmt = $fundingAmt;
            $outAmt = $purchasesAmt + $expensesAmt + $returnsAmt;
            $closing = $opening + $inAmt - $outAmt;

            $totalOpening += $opening;
            $totalFunding += $fundingAmt;
            $totalSpent += $outAmt;
            $totalClosing += $closing;

            // Split Cash Purchases by recorded Vendor/Supplier
            $vendorSplits = $currMovements->where('movementType', 'cash_purchase')
                ->groupBy('toEntityName')
                ->map(function (Collection $vendorMovements, string $vendorName) use ($pId): CashFlowTreeNode {
                    $vAmt = (float) $vendorMovements->sum('amount');

                    return new CashFlowTreeNode(
                        id: "purchaser_{$pId}_vendor_".md5($vendorName),
                        title: $vendorName,
                        subtitle: 'Cash Purchase Split',
                        badge: 'Vendor',
                        entityType: 'vendor_cash_purchase',
                        openingBalance: 0.0,
                        totalIn: 0.0,
                        totalOut: $vAmt,
                        closingBalance: -$vAmt,
                        netChange: -$vAmt,
                        children: [],
                        movements: $vendorMovements->values()->all()
                    );
                })->values()->all();

            $subChildren = [];
            if ($fundingAmt > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "purchaser_{$pId}_funding",
                    title: 'Received From Company',
                    subtitle: 'Funding / Cash Advance',
                    badge: 'Company Funding',
                    entityType: 'purchaser_funding',
                    openingBalance: 0.0,
                    totalIn: $fundingAmt,
                    totalOut: 0.0,
                    closingBalance: $fundingAmt,
                    netChange: $fundingAmt,
                    children: [],
                    movements: $currMovements->where('movementType', 'purchaser_funding')->values()->all()
                );
            }

            if ($purchasesAmt > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "purchaser_{$pId}_purchases",
                    title: 'Cash Purchases (Vendor Split)',
                    subtitle: count($vendorSplits).' Suppliers Paid in Cash',
                    badge: 'Purchases',
                    entityType: 'cash_purchases_group',
                    openingBalance: 0.0,
                    totalIn: 0.0,
                    totalOut: $purchasesAmt,
                    closingBalance: -$purchasesAmt,
                    netChange: -$purchasesAmt,
                    children: $vendorSplits,
                    movements: $currMovements->where('movementType', 'cash_purchase')->values()->all()
                );
            }

            if ($expensesAmt > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "purchaser_{$pId}_expenses",
                    title: 'Other Operating Expenses',
                    subtitle: 'Transport, Loading, Diesel, etc.',
                    badge: 'Expenses',
                    entityType: 'purchaser_expenses',
                    openingBalance: 0.0,
                    totalIn: 0.0,
                    totalOut: $expensesAmt,
                    closingBalance: -$expensesAmt,
                    netChange: -$expensesAmt,
                    children: [],
                    movements: $currMovements->where('movementType', 'purchaser_expense')->values()->all()
                );
            }

            if ($returnsAmt > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "purchaser_{$pId}_returns",
                    title: 'Returned to Company',
                    subtitle: 'Unused Advance Returned',
                    badge: 'Returns',
                    entityType: 'purchaser_return',
                    openingBalance: 0.0,
                    totalIn: 0.0,
                    totalOut: $returnsAmt,
                    closingBalance: -$returnsAmt,
                    netChange: -$returnsAmt,
                    children: [],
                    movements: $currMovements->where('movementType', 'purchaser_return')->values()->all()
                );
            }

            $childNodes[] = new CashFlowTreeNode(
                id: "purchaser_{$pId}",
                title: $pName,
                subtitle: 'Advance & Purchase Float',
                badge: 'Purchaser #'.$pId,
                entityType: 'purchaser',
                openingBalance: $opening,
                totalIn: $inAmt,
                totalOut: $outAmt,
                closingBalance: $closing,
                netChange: $closing - $opening,
                children: $subChildren,
                movements: $currMovements->values()->all(),
                metadata: ['purchaser_id' => $pId]
            );
        }

        return new CashFlowTreeNode(
            id: 'branch_purchasers',
            title: 'PURCHASERS (ADVANCES & CASH BUYS)',
            subtitle: count($childNodes).' Active Purchasers',
            badge: 'Procurement Team',
            entityType: 'purchaser_group',
            openingBalance: $totalOpening,
            totalIn: $totalFunding,
            totalOut: $totalSpent,
            closingBalance: $totalClosing,
            netChange: $totalClosing - $totalOpening,
            children: $childNodes,
            movements: $purchaserMovements->values()->all()
        );
    }

    /**
     * Build the Vendor Credit branch.
     *
     * @param  Collection<int, MoneyMovement>  $movements
     * @param  array<string, mixed>  $openings
     */
    protected function buildVendorsNode(Collection $movements, array $openings): CashFlowTreeNode
    {
        $vendorMovements = $movements->where('sourceType', 'vendor_credit');
        $childNodes = [];

        $totalOpening = 0.0;
        $totalBilled = 0.0;
        $totalPaid = 0.0;
        $totalClosing = 0.0;

        $vendorIds = collect(array_keys($openings))
            ->concat($vendorMovements->pluck('fromEntityId')->filter())
            ->concat($vendorMovements->pluck('toEntityId')->filter())
            ->unique()
            ->values();

        foreach ($vendorIds as $vId) {
            $vId = (int) $vId;
            $currMovements = $vendorMovements->filter(fn (MoneyMovement $m): bool => ($m->metadata['supplier_id'] ?? null) === $vId
                || ($m->fromEntityType === 'vendor' && $m->fromEntityId === $vId)
                || ($m->toEntityType === 'vendor' && $m->toEntityId === $vId));

            $opening = (float) ($openings[$vId]['opening_payable'] ?? 0.0);
            $vName = $openings[$vId]['supplier_name'] ?? ($currMovements->first()?->toEntityName ?? ('Vendor #'.$vId));

            $newBills = (float) $currMovements->where('movementType', 'vendor_credit_bill')->sum('amount');
            $cashPaid = (float) $currMovements->where('movementType', 'vendor_credit_payment')->sum('amount');
            $discounts = (float) $currMovements->where('movementType', 'settlement_discount')->sum('amount');

            $closing = round(max(0.0, $opening + $newBills - $cashPaid - $discounts), 2);

            $totalOpening += $opening;
            $totalBilled += $newBills;
            $totalPaid += $cashPaid + $discounts;
            $totalClosing += $closing;

            $subChildren = [];
            if ($newBills > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "vendor_{$vId}_bills",
                    title: 'New Credit Bills',
                    subtitle: 'Invoiced on Credit',
                    badge: 'Bills',
                    entityType: 'vendor_credit_bill',
                    openingBalance: 0.0,
                    totalIn: $newBills,
                    totalOut: 0.0,
                    closingBalance: $newBills,
                    netChange: $newBills,
                    children: [],
                    movements: $currMovements->where('movementType', 'vendor_credit_bill')->values()->all()
                );
            }

            if ($cashPaid > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "vendor_{$vId}_payments",
                    title: 'Paid by Company',
                    subtitle: 'Bank Settlement Payments',
                    badge: 'Payments',
                    entityType: 'vendor_credit_payment',
                    openingBalance: 0.0,
                    totalIn: 0.0,
                    totalOut: $cashPaid,
                    closingBalance: -$cashPaid,
                    netChange: -$cashPaid,
                    children: [],
                    movements: $currMovements->where('movementType', 'vendor_credit_payment')->values()->all()
                );
            }

            if ($discounts > 0.001) {
                $subChildren[] = new CashFlowTreeNode(
                    id: "vendor_{$vId}_discounts",
                    title: 'Settlement Discounts',
                    subtitle: 'Deductions & Adjustments',
                    badge: 'Discount',
                    entityType: 'settlement_discount',
                    openingBalance: 0.0,
                    totalIn: 0.0,
                    totalOut: $discounts,
                    closingBalance: -$discounts,
                    netChange: -$discounts,
                    children: [],
                    movements: $currMovements->where('movementType', 'settlement_discount')->values()->all()
                );
            }

            if (abs($opening) < 0.001 && $newBills < 0.001 && ($cashPaid + $discounts) < 0.001 && $currMovements->isEmpty()) {
                continue;
            }

            $childNodes[] = new CashFlowTreeNode(
                id: "vendor_{$vId}",
                title: $vName,
                subtitle: 'Accounts Payable Position',
                badge: 'Vendor #'.$vId,
                entityType: 'vendor_credit',
                openingBalance: $opening,
                totalIn: $newBills,
                totalOut: $cashPaid + $discounts,
                closingBalance: $closing,
                netChange: $closing - $opening,
                children: $subChildren,
                movements: $currMovements->values()->all(),
                metadata: ['supplier_id' => $vId]
            );
        }

        return new CashFlowTreeNode(
            id: 'branch_vendors',
            title: 'VENDORS (CREDIT & OUTSTANDING)',
            subtitle: count($childNodes).' Credit Suppliers',
            badge: 'Accounts Payable',
            entityType: 'vendor_group',
            openingBalance: $totalOpening,
            totalIn: $totalBilled,
            totalOut: $totalPaid,
            closingBalance: $totalClosing,
            netChange: $totalClosing - $totalOpening,
            children: $childNodes,
            movements: $vendorMovements->values()->all()
        );
    }

    /**
     * Build the Employees branch.
     *
     * @param  Collection<int, MoneyMovement>  $movements
     * @param  array<string, mixed>  $openings
     */
    protected function buildEmployeesNode(Collection $movements, array $openings): CashFlowTreeNode
    {
        $employeeMovements = $movements->where('sourceType', 'employee');
        $childNodes = [];

        $totalOpening = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $totalClosing = 0.0;

        $employeeIds = collect(array_keys($openings))
            ->concat($employeeMovements->pluck('toEntityId')->filter())
            ->unique()
            ->values();

        foreach ($employeeIds as $empId) {
            $empId = (int) $empId;
            $currMovements = $employeeMovements->filter(fn (MoneyMovement $m): bool => ($m->metadata['employee_id'] ?? null) === $empId
                || $m->toEntityId === $empId);

            $opening = (float) ($openings[$empId]['opening_advance'] ?? 0.0);
            $empName = $openings[$empId]['employee_name'] ?? ($currMovements->first()?->toEntityName ?? ('Employee #'.$empId));

            $advances = (float) $currMovements->where('movementType', 'employee_advance')->sum('amount');
            $salaries = (float) $currMovements->where('movementType', 'salary_payment')->sum('amount');

            $inAmt = $advances;
            $outAmt = 0.0; // deductions handled in payroll settlement
            $closing = $opening + $inAmt - $outAmt;

            $totalOpening += $opening;
            $totalIn += $advances + $salaries;
            $totalClosing += $closing;

            if (abs($opening) < 0.001 && ($advances + $salaries) < 0.001 && $currMovements->isEmpty()) {
                continue;
            }

            $childNodes[] = new CashFlowTreeNode(
                id: "employee_{$empId}",
                title: $empName,
                subtitle: 'Advance & Salary Payments',
                badge: 'Employee #'.$empId,
                entityType: 'employee',
                openingBalance: $opening,
                totalIn: $advances + $salaries,
                totalOut: 0.0,
                closingBalance: $closing,
                netChange: $closing - $opening,
                children: [],
                movements: $currMovements->values()->all(),
                metadata: [
                    'employee_id' => $empId,
                    'advances' => round($advances, 2),
                    'salaries' => round($salaries, 2),
                ]
            );
        }

        return new CashFlowTreeNode(
            id: 'branch_employees',
            title: 'EMPLOYEES & STAFF (ADVANCES & SALARIES)',
            subtitle: count($childNodes).' Staff Accounts',
            badge: 'Payroll & Advances',
            entityType: 'employee_group',
            openingBalance: $totalOpening,
            totalIn: $totalIn,
            totalOut: $totalOut,
            closingBalance: $totalClosing,
            netChange: $totalClosing - $totalOpening,
            children: $childNodes,
            movements: $employeeMovements->values()->all()
        );
    }

    /**
     * Build the Needs Review / Other branch.
     *
     * @param  Collection<int, MoneyMovement>  $allMovements
     */
    protected function buildNeedsReviewNode(Collection $allMovements): CashFlowTreeNode
    {
        $reviewMovements = $allMovements->filter(function (MoneyMovement $m): bool {
            return ($m->metadata['is_review'] ?? false)
                || $m->movementType === 'manual_journal'
                || $m->movementType === 'unassigned'
                || $m->toEntityName === 'Unlinked Vendor'
                || $m->fromEntityName === 'Unlinked Vendor'
                || $m->fromEntityType === 'unallocated'
                || $m->toEntityType === 'unallocated';
        });

        $totalAmount = (float) $reviewMovements->sum('amount');

        $missingVendors = $reviewMovements->filter(fn (MoneyMovement $m) => str_contains($m->toEntityName, 'Unlinked Vendor'));
        $manualJournals = $reviewMovements->filter(fn (MoneyMovement $m) => $m->movementType === 'manual_journal');
        $otherUnassigned = $reviewMovements->filter(fn (MoneyMovement $m) => ! $missingVendors->contains($m) && ! $manualJournals->contains($m));

        $subChildren = [];
        if ($missingVendors->isNotEmpty()) {
            $subChildren[] = new CashFlowTreeNode(
                id: 'review_unlinked_vendors',
                title: 'Unlinked Vendor Purchases',
                subtitle: $missingVendors->count().' Transactions without Supplier Link',
                badge: 'Unlinked',
                entityType: 'review_item',
                openingBalance: 0.0,
                totalIn: 0.0,
                totalOut: (float) $missingVendors->sum('amount'),
                closingBalance: (float) $missingVendors->sum('amount'),
                netChange: (float) $missingVendors->sum('amount'),
                children: [],
                movements: $missingVendors->values()->all(),
                metadata: ['is_review' => true]
            );
        }

        if ($manualJournals->isNotEmpty()) {
            $subChildren[] = new CashFlowTreeNode(
                id: 'review_manual_journals',
                title: 'Manual Accounting Journals',
                subtitle: $manualJournals->count().' Manual Adjustments',
                badge: 'Manual',
                entityType: 'review_item',
                openingBalance: 0.0,
                totalIn: (float) $manualJournals->sum('amount'),
                totalOut: 0.0,
                closingBalance: (float) $manualJournals->sum('amount'),
                netChange: (float) $manualJournals->sum('amount'),
                children: [],
                movements: $manualJournals->values()->all(),
                metadata: ['is_review' => true]
            );
        }

        if ($otherUnassigned->isNotEmpty()) {
            $subChildren[] = new CashFlowTreeNode(
                id: 'review_unassigned_other',
                title: 'Other Unassigned Adjustments',
                subtitle: $otherUnassigned->count().' Unclassified Adjustments',
                badge: 'Needs Review',
                entityType: 'review_item',
                openingBalance: 0.0,
                totalIn: (float) $otherUnassigned->sum('amount'),
                totalOut: 0.0,
                closingBalance: (float) $otherUnassigned->sum('amount'),
                netChange: (float) $otherUnassigned->sum('amount'),
                children: [],
                movements: $otherUnassigned->values()->all(),
                metadata: ['is_review' => true]
            );
        }

        return new CashFlowTreeNode(
            id: 'branch_review',
            title: 'OTHER / NEEDS REVIEW',
            subtitle: count($reviewMovements).' Items Requiring Attention',
            badge: 'Gaps & Adjustments',
            entityType: 'review_group',
            openingBalance: 0.0,
            totalIn: $totalAmount,
            totalOut: 0.0,
            closingBalance: $totalAmount,
            netChange: $totalAmount,
            children: $subChildren,
            movements: $reviewMovements->values()->all(),
            metadata: ['is_review' => true]
        );
    }

    /**
     * Compute top-level Monthly Reconciliation Summary.
     *
     * @param  Collection<int, MoneyMovement>  $allMovements
     */
    protected function calculateReconciliationSummary(
        CashFlowTreeNode $root,
        CashFlowTreeNode $banksNode,
        CashFlowTreeNode $shopsNode,
        CashFlowTreeNode $purchasersNode,
        CashFlowTreeNode $employeesNode,
        CashFlowTreeNode $reviewNode,
        Collection $allMovements
    ): MonthlyReconciliationSummary {
        // Opening Company Money is the money in all company accounts at start of month
        $openingCompanyMoney = $banksNode->openingBalance;

        // External Money In: Sales from shops + external bank deposits + other revenue
        $shopSales = (float) $allMovements->where('movementType', 'shop_sales')->sum('amount');
        $otherIncome = (float) $allMovements->where('movementType', 'other_income')->sum('amount');
        $externalBankIn = (float) $allMovements->where('movementType', 'bank_deposit')
            ->filter(fn (MoneyMovement $m) => $m->fromEntityType === 'external_party')
            ->sum('amount');
        $externalMoneyIn = $shopSales + $otherIncome + $externalBankIn;

        // External Money Out: Cash purchases + purchaser expenses + shop expenses + other expenses + vendor credit payments + payroll
        $cashPurchases = (float) $allMovements->where('movementType', 'cash_purchase')->sum('amount');
        $purchaserExpenses = (float) $allMovements->where('movementType', 'purchaser_expense')->sum('amount');
        $shopExpenses = (float) $allMovements->where('movementType', 'shop_expense')->sum('amount');
        $otherExpenses = (float) $allMovements->where('movementType', 'other_expense')->sum('amount');
        $vendorPayments = (float) $allMovements->where('movementType', 'vendor_credit_payment')->sum('amount');
        $payroll = (float) $allMovements->whereIn('movementType', ['salary_payment', 'employee_advance'])->sum('amount');
        $externalMoneyOut = $cashPurchases + $purchaserExpenses + $shopExpenses + $otherExpenses + $vendorPayments + $payroll;

        $expectedClosing = $openingCompanyMoney + $externalMoneyIn - $externalMoneyOut;

        // Money Location breakdown
        $locations = [
            'Banks & Accounts' => $banksNode->closingBalance,
            'Shop Cash Held' => $shopsNode->closingBalance,
            'Purchaser Floats' => $purchasersNode->closingBalance,
            'Employee Advances' => $employeesNode->closingBalance,
            'Other Tracked' => $reviewNode->closingBalance,
        ];

        $locatedMoney = array_sum($locations);
        $unexplainedDifference = $expectedClosing - $locatedMoney;

        return new MonthlyReconciliationSummary(
            openingCompanyMoney: $openingCompanyMoney,
            externalMoneyIn: $externalMoneyIn,
            externalMoneyOut: $externalMoneyOut,
            expectedClosing: $expectedClosing,
            locations: $locations,
            locatedMoney: $locatedMoney,
            unexplainedDifference: $unexplainedDifference,
            needsReviewAmount: $reviewNode->closingBalance
        );
    }
}
