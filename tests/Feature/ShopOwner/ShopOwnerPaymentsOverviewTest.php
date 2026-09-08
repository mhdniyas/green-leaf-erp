<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOwnerPaymentsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_owner_payments_page_renders_read_only_settlement_overview(): void
    {
        Permission::findOrCreate('sales.order.create');
        Role::findOrCreate('shop');

        $user = User::factory()->create();
        $user->givePermissionTo('sales.order.create');
        $user->assignRole('shop');

        $shop = Shop::factory()->create();

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.payments.index'));

        $response->assertStatus(200);
        $response->assertSee('PAYMENTS');
        $response->assertSee('SHOP BALANCE');
        $response->assertSee('MONEY SPLIT');
        $response->assertSee('EXPENSE PAYABLES');
        $response->assertSee('SETTLED EXPENSES');
        $response->assertSee('PAYMENTS TO COMPANY');
        $response->assertSee('Sales Collections');
        $response->assertSee('Sent to Company');
    }

    public function test_shop_owner_payments_page_handles_transactions_with_direct_company_account(): void
    {
        Permission::findOrCreate('sales.order.create');
        Role::findOrCreate('shop');

        $user = User::factory()->create();
        $user->givePermissionTo('sales.order.create');
        $user->assignRole('shop');

        $shop = Shop::factory()->create();

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'enabled' => true,
        ]);

        $companyAccount = CompanyAccount::query()->create([
            'name' => 'Paytm Merchant Account',
            'account_type' => 'upi',
            'is_active' => true,
        ]);

        $entryType = LedgerEntryType::query()->create([
            'name' => 'Paytm Collection',
            'code' => 'paytm_coll',
            'category' => 'income',
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'entry_type_id' => $entryType->id,
            'company_account_id' => $companyAccount->id,
            'amount' => 3000.00,
            'direction' => 'income',
            'funding_source' => 'bank',
            'affects_sales' => true,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.payments.index'));

        $response->assertStatus(200);
        $response->assertSee('SHOP BALANCE');
        $response->assertSee('EXPENSE PAYABLES');
        $response->assertSee('Direct to Company');
    }

    public function test_shop_owner_can_pay_unpaid_expense(): void
    {
        Permission::findOrCreate('sales.order.create');
        Role::findOrCreate('shop');

        $user = User::factory()->create();
        $user->givePermissionTo('sales.order.create');
        $user->assignRole('shop');

        $shop = Shop::factory()->create();

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $entryType = LedgerEntryType::query()->create([
            'name' => 'Vehicle Expense',
            'code' => 'veh_exp',
            'category' => 'expense',
        ]);

        $setting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $shop->id,
            'entry_type_id' => $entryType->id,
            'settlement_role' => 'payable',
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        ShopLedgerTransaction::query()->create([
            'shop_id' => $shop->id,
            'business_date' => now()->toDateString(),
            'entry_type_id' => $entryType->id,
            'amount' => 15000.00,
            'direction' => 'expense',
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->post(route('shop-owner.payments.pay-expense'), [
                'entry_setting_id' => $setting->id,
                'amount' => 5000.00,
                'payment_date' => '2026-08-15',
                'paid_from_source' => 'shop_balance',
                'notes' => 'Partial payment for vehicle',
            ]);

        $response->assertRedirect(route('shop-owner.payments.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $shop->id,
            'entry_type_id' => $entryType->id,
            'business_date' => '2026-08-15',
            'amount' => 5000.00,
            'direction' => 'expense',
            'reference_type' => 'expense_payment',
            'funding_source' => 'shop_cash',
        ]);
    }

    public function test_shop_owner_can_submit_pay_to_company_request(): void
    {
        Permission::findOrCreate('sales.order.create');
        Role::findOrCreate('shop');

        $user = User::factory()->create();
        $user->givePermissionTo('sales.order.create');
        $user->assignRole('shop');

        $shop = Shop::factory()->create(['accounting_mode' => 'owned', 'accounting_enabled' => true]);

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $shop->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'amount_mode' => 'shop_balance',
                'amount' => 5000.00,
                'payment_method' => 'online_upi',
                'payment_reference' => 'UPI-REF-1234',
                'shop_note' => 'Monthly payment to company',
            ]);

        $response->assertRedirect(route('shop-owner.payments.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'shop_id' => $shop->id,
            'requested_by' => $user->id,
            'request_type' => 'shop_balance',
            'requested_amount' => 5000.00,
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'payment_method' => 'online_upi',
            'payment_reference' => 'UPI-REF-1234',
            'shop_note' => 'Monthly payment to company',
        ]);
    }
}
