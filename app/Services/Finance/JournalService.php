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
        $debitsSum = 0.0;
        $creditsSum = 0.0;

        foreach ($data->lines as $line) {
            $amount = (float) $line['amount'];
            if (strtolower($line['type']) === 'debit') {
                $debitsSum += $amount;
            } elseif (strtolower($line['type']) === 'credit') {
                $creditsSum += $amount;
            } else {
                throw new RuntimeException("Invalid transaction type: {$line['type']}");
            }
        }

        // Allow micro variance due to floating points
        if (abs($debitsSum - $creditsSum) > 0.01) {
            throw new RuntimeException("Unbalanced journal entry. Debits ({$debitsSum}) must equal Credits ({$creditsSum}).");
        }

        return DB::transaction(function () use ($data, $userId) {
            /** @var JournalEntry $entry */
            $entry = $this->repository->create(array_merge($data->toArray(), [
                'created_by' => $userId,
            ]));

            foreach ($data->lines as $line) {
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
            lines: $lines
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
            lines: $lines
        );

        return $this->createEntry($data, $payment->created_by);
    }

    /**
     * Record purchase invoice entry.
     * Debit COGS (5100), Credit AP (2100)
     */
    public function recordPurchaseInvoice(PurchaseInvoice $invoice): JournalEntry
    {
        $invoice->load(['goodsReceived.purchaseOrder', 'supplier']);

        $cogsAccountId = $this->getAccountIdByCode('5100');
        $apAccountId = $this->getAccountIdByCode('2100');

        $lines = [
            ['account_id' => $cogsAccountId, 'type' => 'debit', 'amount' => (float) $invoice->amount],
            ['account_id' => $apAccountId, 'type' => 'credit', 'amount' => (float) $invoice->amount],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $data = new JournalEntryData(
            entryDate: $invoice->created_at->format('Y-m-d'),
            reference: $invoice->invoice_number,
            description: "Purchase Invoice matched to GRN #{$invoice->goods_received_id} for Supplier: {$invoice->supplier->name}",
            lines: $lines
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

        $apAccountId = $this->getAccountIdByCode('2100');
        $bankAccountId = $this->getAccountIdByCode('1020');

        $lines = [
            ['account_id' => $apAccountId, 'type' => 'debit', 'amount' => (float) $invoice->amount],
            ['account_id' => $bankAccountId, 'type' => 'credit', 'amount' => (float) $invoice->amount],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $data = new JournalEntryData(
            entryDate: now()->format('Y-m-d'),
            reference: "PMT-{$invoice->invoice_number}",
            description: "Supplier payment made for Invoice #{$invoice->invoice_number}",
            lines: $lines
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
            lines: $lines
        );

        return $this->createEntry($data, $wastage->recorded_by);
    }

    /**
     * Record Goods Received Note entry upon approval.
     * Debit Graded Inventory (1200), Credit Accounts Payable (2100)
     */
    public function recordGoodsReceipt(GoodsReceived $grn): JournalEntry
    {
        $grn->load(['purchaseOrder.supplier', 'items.purchaseOrderItem']);

        $inventoryAccountId = $this->getAccountIdByCode('1200');
        $apAccountId = $this->getAccountIdByCode('2100');

        // Calculate material cost of received items
        $materialCost = 0.00;
        foreach ($grn->items as $item) {
            $unitPrice = $item->purchaseOrderItem ? (float) $item->purchaseOrderItem->unit_price : 0.00;
            $materialCost += (float) $item->received_qty * $unitPrice;
        }

        $lines = [
            ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $materialCost],
            ['account_id' => $apAccountId, 'type' => 'credit', 'amount' => $materialCost],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $data = new JournalEntryData(
            entryDate: $grn->received_at->format('Y-m-d'),
            reference: $grn->grn_number,
            description: "Goods Received Note #{$grn->grn_number} approved for Supplier: {$grn->purchaseOrder->supplier->name}",
            lines: $lines
        );

        return $this->createEntry($data, (int) $userId);
    }

    private function getAccountIdByCode(string $code): int
    {
        $account = Account::where('code', $code)->first();
        if (! $account) {
            throw new RuntimeException("Chart of Accounts is missing account code: {$code}. Please seed the Chart of Accounts.");
        }

        return (int) $account->id;
    }
}
