<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Enums\Cashbook\FundingSource;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSalaryAdvanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $shop;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'Casio Fresh Shop',
            'code' => 'CASIO_FRESH',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->owner->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->employee = Employee::factory()->create([
            'name' => 'Arun Prakash',
            'employee_code' => 'EMP-ARUN',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'default_shop_id' => $this->shop->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_salary_update_appears_correctly_in_staff_history(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-10',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
            'notes' => 'Original note',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);

        $response = $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 3750.00,
            'paid_on' => '2026-09-12',
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'notes' => 'Updated salary notes',
        ]);

        $response->assertRedirect();
        $fresh = $payment->fresh();
        $this->assertEquals(3750.00, (float) $fresh->amount);
        $this->assertEquals('2026-09-12', $fresh->paid_on->toDateString());
        $this->assertEquals('salary', $fresh->payment_type);
        $this->assertEquals('Updated salary notes', $fresh->notes);

        $historyResponse = $this->actingAs($this->owner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shop->code,
            'tab' => 'history',
        ]));

        $historyResponse->assertOk();
        $historyResponse->assertSee('3,750.00');
        $historyResponse->assertSee('Updated salary notes');
    }

    public function test_salary_update_updates_the_same_cashbook_entry(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 2000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-10',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
        ]);

        $initialTx = app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);
        $this->assertNotNull($initialTx);

        $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 2800.00,
            'paid_on' => '2026-09-14',
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'notes' => 'Adjusted salary',
        ]);

        $txs = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $txs);
        $this->assertEquals($initialTx->id, $txs->first()->id);
        $this->assertEquals(2800.00, (float) $txs->first()->amount);
        $this->assertEquals('2026-09-14', $txs->first()->business_date->toDateString());
    }

    public function test_advance_update_appears_correctly_in_staff_history(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 1000.00,
            'fund_source' => 'sales',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-05',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
            'notes' => 'Early advance',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);

        $response = $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 1400.00,
            'paid_on' => '2026-09-08',
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'notes' => 'Increased advance',
        ]);

        $response->assertRedirect();
        $fresh = $payment->fresh();
        $this->assertEquals(1400.00, (float) $fresh->amount);
        $this->assertEquals('2026-09-08', $fresh->paid_on->toDateString());
        $this->assertEquals('advance', $fresh->payment_type);
        $this->assertEquals('petty_cash', $fresh->fund_source);
        $this->assertEquals('Increased advance', $fresh->notes);

        $historyResponse = $this->actingAs($this->owner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shop->code,
            'tab' => 'history',
        ]));

        $historyResponse->assertOk();
        $historyResponse->assertSee('1,400.00');
        $historyResponse->assertSee('Increased advance');
    }

    public function test_advance_update_updates_the_same_cashbook_entry(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 1200.00,
            'fund_source' => 'sales',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-05',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
        ]);

        $initialTx = app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);
        $this->assertNotNull($initialTx);

        $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 1800.00,
            'paid_on' => '2026-09-09',
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'notes' => 'Advance updated',
        ]);

        $txs = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $txs);
        $this->assertEquals($initialTx->id, $txs->first()->id);
        $this->assertEquals(1800.00, (float) $txs->first()->amount);
        $this->assertEquals('2026-09-09', $txs->first()->business_date->toDateString());
        $this->assertEquals(FundingSource::Petty->value, $txs->first()->funding_source);
    }

    public function test_staff_history_date_equals_cashbook_date(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 2200.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-02',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);

        $newDate = '2026-09-17';
        $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 2200.00,
            'paid_on' => $newDate,
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'notes' => 'Date shift',
        ]);

        $payment->refresh();
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals($newDate, $payment->paid_on->toDateString());
        $this->assertEquals($newDate, $tx->business_date->toDateString());
        $this->assertEquals($payment->paid_on->toDateString(), $tx->business_date->toDateString());
    }

    public function test_no_duplicate_cashbook_entries_after_repeated_updates(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 1000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-01',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);

        // Perform 4 repeated updates with different values
        $updates = [
            ['amount' => 1200.00, 'paid_on' => '2026-09-02', 'payment_type' => 'advance', 'fund_source' => 'petty_cash'],
            ['amount' => 1500.00, 'paid_on' => '2026-09-05', 'payment_type' => 'salary', 'fund_source' => 'sales'],
            ['amount' => 1800.00, 'paid_on' => '2026-09-08', 'payment_type' => 'advance', 'fund_source' => 'company'],
            ['amount' => 2100.00, 'paid_on' => '2026-09-12', 'payment_type' => 'salary', 'fund_source' => 'sales'],
        ];

        foreach ($updates as $data) {
            $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), $data)
                ->assertRedirect();
        }

        $txCount = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->count();

        $this->assertEquals(1, $txCount);

        $finalTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertEquals(2100.00, (float) $finalTx->amount);
        $this->assertEquals('2026-09-12', $finalTx->business_date->toDateString());
    }

    public function test_amount_category_and_fund_source_changes_are_synchronized(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 1100.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-03',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
            'notes' => 'Original salary',
        ]);

        app(StaffPaymentCashbookProjectionService::class)->syncPayment($payment, $this->owner->id);

        $this->actingAs($this->owner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 2220.00,
            'paid_on' => '2026-09-18',
            'payment_type' => 'advance',
            'fund_source' => 'petty_cash',
            'notes' => 'Converted to advance with petty cash',
        ])->assertRedirect();

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(2220.00, (float) $tx->amount);
        $this->assertEquals('2026-09-18', $tx->business_date->toDateString());
        $this->assertEquals(FundingSource::Petty->value, $tx->funding_source);
        $this->assertEquals('Converted to advance with petty cash', $tx->notes);
    }

    public function test_shop_authorization_boundaries_are_enforced(): void
    {
        $otherShop = Shop::query()->create([
            'name' => 'Other Shop',
            'code' => 'OTHER_SHOP',
            'warehouse_tag' => 'OTHER',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('shop');
        $otherOwner->ownedShopAssignments()->create(['shop_id' => $otherShop->id]);

        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 1000.00,
            'fund_source' => 'sales',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-01',
            'paid_by' => $this->owner->id,
            'status' => 'paid',
        ]);

        // Other owner attempts to update payment of first shop
        $response = $this->actingAs($otherOwner)->put(route('shop-owner.staff.payments.update', $payment), [
            'amount' => 9999.00,
            'paid_on' => '2026-09-01',
            'payment_type' => 'salary',
            'fund_source' => 'sales',
        ]);

        $response->assertForbidden();

        // Other owner attempts to delete payment of first shop
        $deleteResponse = $this->actingAs($otherOwner)->delete(route('shop-owner.staff.payments.destroy', $payment));
        $deleteResponse->assertForbidden();

        // Verify payment was not modified
        $this->assertEquals(1000.00, (float) $payment->fresh()->amount);
    }

    public function test_salary_tab_defaults_to_give_advance_mode(): void
    {
        $response = $this->actingAs($this->owner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shop->code,
            'date' => '2026-09-07',
            'tab' => 'salary',
        ]));

        $response->assertOk();
        // Check advance form is active and visible
        $response->assertSee('Request Advance / Give Advance');
        $response->assertSee('advance-request-form');
        $response->assertDontSee('id="advance-request-form" class="space-y-4 hidden"', false);
        $response->assertSee('id="salary-payment-form" class="space-y-4 hidden"', false);
    }
}
