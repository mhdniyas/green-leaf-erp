<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\SalesInvoice;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Models\WastageEntry;
use App\Repositories\Finance\JournalEntryRepository;
use App\Support\ChartOfAccounts;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JournalService
{
    public function __construct(
        private readonly JournalEntryRepository $repository,
    ) {}

    /**
     * Create a balanced journal entry.
     */
    public function createEntry(JournalEntryData $data, int $userId): JournalEntry
    {
        $lines = $this->validatedLines($data->lines);

        return DB::transaction(function () use ($data, $lines, $userId): JournalEntry {
            $existingEntry = $this->entryForSource($data->sourceType, $data->sourceId, $data->sourceEvent);
            if ($existingEntry instanceof JournalEntry) {
                return $existingEntry;
            }

            /** @var JournalEntry $entry */
            $entry = $this->repository->create(array_merge($data->toArray(), [
                'created_by' => $userId,
            ]));

            foreach ($lines as $line) {
                $entry->transactions()->create([
                    'account_id' => $line['account_id'],
                    'type' => strtolower($line['type']),
                    'amount' => $line['amount'],
                ]);
            }

            return $entry->fresh('transactions.account');
        });
    }

    /**
     * Record sales invoice entry.
     * Debit AR (1100), Credit Sales Revenue (4100)
     */
    public function recordSalesInvoice(SalesInvoice $invoice): JournalEntry
    {
        $existingEntry = $this->entryForSource(SalesInvoice::class, $invoice->id, 'invoice')
            ?? $this->entryForReference($invoice->invoice_number);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $arAccountId = $this->getAccountIdByCode('1100');
        $salesAccountId = $this->getAccountIdByCode('4100');

        $lines = [
            ['account_id' => $arAccountId, 'type' => 'debit', 'amount' => (float) $invoice->amount],
            ['account_id' => $salesAccountId, 'type' => 'credit', 'amount' => (float) $invoice->amount],
        ];

        $data = new JournalEntryData(
            entryDate: $invoice->created_at->format('Y-m-d'),
            reference: $invoice->invoice_number,
            description: "Invoice generated for Sales Order #{$invoice->salesOrder->so_number}",
            lines: $lines,
            sourceType: SalesInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: 'invoice'
        );

        return $this->createEntry($data, $invoice->created_by);
    }

    /**
     * Record payment received for sales invoice.
     * Debit Cash (1010) / Bank (1020), Credit AR (1100)
     */
    public function recordSalesPayment(Payment $payment): JournalEntry
    {
        $payment->load('salesInvoice');

        $existingEntry = $this->entryForSource(Payment::class, $payment->id, 'payment')
            ?? $this->entryForReference("PAY-{$payment->id}");
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $method = $payment->payment_method;
        $methodStr = $method instanceof \BackedEnum ? $method->value : ($method ?? 'cash');
        $cashMethod = strtolower((string) $methodStr) === 'cash';
        $debitAccountId = $cashMethod
            ? $this->getAccountIdByCode('1010')
            : $this->getAccountIdByCode('1020');

        $arAccountId = $this->getAccountIdByCode('1100');

        $lines = [
            ['account_id' => $debitAccountId, 'type' => 'debit', 'amount' => (float) $payment->amount],
            ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => (float) $payment->amount],
        ];

        $data = new JournalEntryData(
            entryDate: $payment->paid_at->format('Y-m-d'),
            reference: "PAY-{$payment->id}",
            description: "Payment received for Invoice #{$payment->salesInvoice->invoice_number}",
            lines: $lines,
            sourceType: Payment::class,
            sourceId: $payment->id,
            sourceEvent: 'payment'
        );

        return $this->createEntry($data, $payment->created_by);
    }

    /**
     * Record income received from a shop invoice.
     * Debit Cash (1010), Credit Sales Revenue (4100).
     */
    public function recordShopInvoicePayment(ShopInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null): JournalEntry
    {
        $amount = round($amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Shop invoice payment journal amount must be positive.');
        }

        $cashAccountId = $this->getAccountIdByCode('1010');
        $salesAccountId = $this->getAccountIdByCode('4100');

        $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);
        $event = $sourceEvent ?? "payment:paid-{$paidAmountCents}";

        $lines = [
            ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $salesAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $invoice->business_date->format('Y-m-d'),
            reference: "SHOP-PAY-{$invoice->invoice_number}-{$paidAmountCents}",
            description: "Shop invoice payment approved for Invoice #{$invoice->invoice_number}",
            lines: $lines,
            sourceType: ShopInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: $event
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Record cash advanced from Green Leaf to a purchaser.
     * Debit Purchaser Advances (1300), Credit Cash (1010).
     */
    public function recordPurchaserCredit(PurchaserCredit $credit): ?JournalEntry
    {
        if ($credit->type !== 'in') {
            return null;
        }

        $amount = round((float) $credit->amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Purchaser credit journal amount must be positive.');
        }

        $advanceAccountId = $this->getAccountIdByCode('1300');
        $cashAccountId = $this->getAccountIdByCode('1010');

        $lines = [
            ['account_id' => $advanceAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $credit->business_date->format('Y-m-d'),
            reference: "PURCH-CREDIT-{$credit->id}",
            description: $credit->description ?: 'Cash advance given to purchaser.',
            lines: $lines,
            sourceType: PurchaserCredit::class,
            sourceId: $credit->id,
            sourceEvent: 'cash_advance'
        );

        return $this->createEntry($data, (int) ($credit->created_by ?? $credit->purchaser_id));
    }

    /**
     * Record cash paid for Green Leaf direct purchase invoice.
     * Debit Graded Inventory (1200), Credit Cash (1010).
     */
    public function recordGreenLeafDirectPurchasePayment(PurchaseInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null): JournalEntry
    {
        $amount = round($amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Direct purchase payment journal amount must be positive.');
        }

        $invoice->loadMissing(['purchaserCart', 'supplier']);

        $inventoryAccountId = $this->getAccountIdByCode('1200');
        $cashAccountId = $this->getAccountIdByCode('1010');
        $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);
        $event = $sourceEvent ?? "green_leaf_direct_purchase_payment:paid-{$paidAmountCents}";
        $businessDate = $invoice->purchaserCart?->business_date?->format('Y-m-d')
            ?? $invoice->created_at?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $lines = [
            ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $businessDate,
            reference: "GL-DIRECT-PAY-{$invoice->id}-{$paidAmountCents}",
            description: 'Green Leaf Direct Purchase payment for invoice #'.($invoice->invoice_number ?: $invoice->id),
            lines: $lines,
            sourceType: PurchaseInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: $event
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Record wastage expense entry.
     * Debit Wastage Expense (5200), Credit COGS (5100)
     */
    public function recordWastage(WastageEntry $wastage): JournalEntry
    {
        $wastage->load('product');

        $wastageAccountId = $this->getAccountIdByCode('5200');
        $cogsAccountId = $this->getAccountIdByCode('5100');

        $lines = [
            ['account_id' => $wastageAccountId, 'type' => 'debit', 'amount' => (float) $wastage->quantity * (float) $wastage->cost_per_kg],
            ['account_id' => $cogsAccountId, 'type' => 'credit', 'amount' => (float) $wastage->quantity * (float) $wastage->cost_per_kg],
        ];

        $data = new JournalEntryData(
            entryDate: $wastage->wastage_date->format('Y-m-d'),
            reference: "WASTE-{$wastage->id}",
            description: "Wastage logged: {$wastage->quantity} kg of {$wastage->product->name} (Grade {$wastage->grade->value}) - Reason: {$wastage->reason->value}",
            lines: $lines,
            sourceType: WastageEntry::class,
            sourceId: $wastage->id,
            sourceEvent: 'wastage'
        );

        return $this->createEntry($data, $wastage->recorded_by);
    }

    /**
     * Record Goods Received Note entry upon approval.
     * Debit Graded Inventory (1200), Credit Goods Received Not Invoiced (2150)
     */
    public function recordGoodsReceipt(GoodsReceived $grn): JournalEntry
    {
        $grn->load(['purchaseOrder.supplier', 'items.purchaseOrderItem']);

        $existingEntry = $this->entryForSource(GoodsReceived::class, $grn->id, 'receipt')
            ?? $this->entryForReference($grn->grn_number);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $inventoryAccountId = $this->getAccountIdByCode('1200');
        $grniAccountId = $this->getAccountIdByCode('2150');

        // Calculate material cost of received items
        $materialCost = 0.00;
        foreach ($grn->items as $item) {
            $unitPrice = $item->purchaseOrderItem ? (float) $item->purchaseOrderItem->unit_price : 0.00;
            $materialCost += (float) $item->received_qty * $unitPrice;
        }

        $lines = [
            ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $materialCost],
            ['account_id' => $grniAccountId, 'type' => 'credit', 'amount' => $materialCost],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $data = new JournalEntryData(
            entryDate: $grn->received_at->format('Y-m-d'),
            reference: $grn->grn_number,
            description: "Goods Received Note #{$grn->grn_number} approved for Supplier: {$grn->purchaseOrder->supplier->name}",
            lines: $lines,
            sourceType: GoodsReceived::class,
            sourceId: $grn->id,
            sourceEvent: 'receipt'
        );

        return $this->createEntry($data, (int) $userId);
    }

    private function getAccountIdByCode(string $code): int
    {
        $account = Account::query()->where('code', $code)->where('is_active', true)->first();

        if (! $account) {
            $defaultAccount = ChartOfAccounts::find($code);

            if ($defaultAccount !== null) {
                $account = Account::query()->updateOrCreate(
                    ['code' => $defaultAccount['code']],
                    [
                        'name' => $defaultAccount['name'],
                        'type' => $defaultAccount['type'],
                        'is_active' => $defaultAccount['is_active'],
                        'parent_id' => $defaultAccount['parent_id'],
                    ],
                );
            }
        }

        if (! $account) {
            throw new RuntimeException("Chart of Accounts is missing account code: {$code}. Please seed the Chart of Accounts.");
        }

        return (int) $account->id;
    }

    private function entryForReference(string $reference): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('reference', $reference)
            ->with('transactions.account')
            ->first();
    }

    private function entryForSource(?string $sourceType, ?int $sourceId, ?string $sourceEvent): ?JournalEntry
    {
        if ($sourceType === null || $sourceId === null || $sourceEvent === null) {
            return null;
        }

        return JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('source_event', $sourceEvent)
            ->with('transactions.account')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{account_id:int, type:string, amount:float}>
     */
    private function validatedLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new RuntimeException('A journal entry must contain at least one debit and one credit line.');
        }

        $normalizedLines = [];
        $accountIds = [];
        $debitCents = 0;
        $creditCents = 0;

        foreach ($lines as $index => $line) {
            if (! is_array($line) || ! isset($line['account_id'], $line['type'], $line['amount'])) {
                throw new RuntimeException("Malformed journal line at index {$index}.");
            }

            $accountId = filter_var($line['account_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $type = strtolower(trim((string) $line['type']));
            $amount = is_numeric($line['amount']) ? (float) $line['amount'] : null;

            if ($accountId === false || ! in_array($type, ['debit', 'credit'], true)) {
                throw new RuntimeException("Invalid journal line at index {$index}.");
            }

            if ($amount === null || ! is_finite($amount) || $amount <= 0) {
                throw new RuntimeException("Journal line {$index} must have a positive amount.");
            }

            $amountCents = (int) round($amount * 100);
            if ($amountCents <= 0 || abs(($amount * 100) - $amountCents) > 0.0001) {
                throw new RuntimeException("Journal line {$index} amount must have no more than two decimal places.");
            }

            $accountIds[] = $accountId;
            $normalizedLines[] = [
                'account_id' => $accountId,
                'type' => $type,
                'amount' => round($amountCents / 100, 2),
            ];

            if ($type === 'debit') {
                $debitCents += $amountCents;
            } else {
                $creditCents += $amountCents;
            }
        }

        $activeAccountCount = Account::query()
            ->whereIn('id', array_unique($accountIds))
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])
            ->count();

        if ($activeAccountCount !== count(array_unique($accountIds))) {
            throw new RuntimeException('Journal entries may only use existing active accounts.');
        }

        if ($debitCents === 0 || $creditCents === 0 || $debitCents !== $creditCents) {
            throw new RuntimeException("Unbalanced journal entry. Debits ({$debitCents}) must equal Credits ({$creditCents}).");
        }

        return $normalizedLines;
    }
}
