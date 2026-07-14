<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\WastageEntry;
use App\Repositories\Finance\JournalEntryRepository;
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
     * Record purchase invoice entry.
     * Debit GRNI (2150), Credit AP (2100).
     */
    public function recordPurchaseInvoice(PurchaseInvoice $invoice): JournalEntry
    {
        $invoice->load(['goodsReceived.purchaseOrder', 'supplier']);

        $existingEntry = $this->entryForSource(PurchaseInvoice::class, $invoice->id, 'invoice')
            ?? $this->entryForReference($invoice->invoice_number);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $grniAccountId = $this->getAccountIdByCode('2150');
        $apAccountId = $this->getAccountIdByCode('2100');

        $lines = [
            ['account_id' => $grniAccountId, 'type' => 'debit', 'amount' => (float) $invoice->amount],
            ['account_id' => $apAccountId, 'type' => 'credit', 'amount' => (float) $invoice->amount],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $data = new JournalEntryData(
            entryDate: $invoice->created_at->format('Y-m-d'),
            reference: $invoice->invoice_number,
            description: "Purchase Invoice matched to GRN #{$invoice->goods_received_id} for Supplier: {$invoice->supplier->name}",
            lines: $lines,
            sourceType: PurchaseInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: 'invoice'
        );

        return $this->createEntry($data, (int) $userId);
    }

    /**
     * Record payment made for purchase invoice.
     * Debit AP (2100), Credit Bank Account (1020)
     */
    public function recordPurchasePayment(PurchaseInvoice $invoice): JournalEntry
    {
        $invoice->load('supplier');

        $reference = "PMT-{$invoice->invoice_number}";
        $existingEntry = $this->entryForSource(PurchaseInvoice::class, $invoice->id, 'payment')
            ?? $this->entryForReference($reference);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $apAccountId = $this->getAccountIdByCode('2100');
        $bankAccountId = $this->getAccountIdByCode('1020');

        $lines = [
            ['account_id' => $apAccountId, 'type' => 'debit', 'amount' => (float) $invoice->amount],
            ['account_id' => $bankAccountId, 'type' => 'credit', 'amount' => (float) $invoice->amount],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $data = new JournalEntryData(
            entryDate: now()->format('Y-m-d'),
            reference: $reference,
            description: "Supplier payment made for Invoice #{$invoice->invoice_number}",
            lines: $lines,
            sourceType: PurchaseInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: 'payment'
        );

        return $this->createEntry($data, (int) $userId);
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
