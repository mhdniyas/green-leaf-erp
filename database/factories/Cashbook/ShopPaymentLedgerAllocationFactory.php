<?php

declare(strict_types=1);

namespace Database\Factories\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\ShopInvoicePaymentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopPaymentLedgerAllocation>
 */
class ShopPaymentLedgerAllocationFactory extends Factory
{
    public function definition(): array
    {
        $paymentRequest = ShopInvoicePaymentRequest::factory()->create();
        $entryType = LedgerEntryType::query()->firstOrCreate(
            ['code' => 'factory_settlement_credit'],
            ['name' => 'Factory Settlement Credit', 'category' => 'income', 'active' => true],
        );
        $transaction = ShopLedgerTransaction::query()->create([
            'shop_id' => $paymentRequest->shop_id,
            'business_date' => today()->toDateString(),
            'entry_type_id' => $entryType->id,
            'amount' => 100.00,
            'direction' => 'income',
            'funding_source' => 'none',
            'affects_sales' => false,
            'affects_income' => false,
            'affects_expense' => false,
            'affects_pl' => false,
            'settlement_delta' => 100.00,
            'settlement_direction' => 'increase',
            'status' => 'posted',
        ]);

        return [
            'payment_request_id' => $paymentRequest->id,
            'shop_id' => $paymentRequest->shop_id,
            'shop_ledger_transaction_id' => $transaction->id,
            'amount' => 100.00,
        ];
    }
}
