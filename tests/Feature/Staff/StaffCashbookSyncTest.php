<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\User;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCashbookSyncTest extends TestCase
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
            'name' => 'Ashirwad Veg Shop',
            'code' => 'AV_ASHIRWAD',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->owner->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->employee = Employee::factory()->create([
            'name' => 'Ramesh Kumar',
            'employee_code' => 'EMP-RAMESH',
            'employment_status' => 'active',
            'verification_status' => 'approved',
        ]);
    }

    public function test_sync_with_cashbook_button_route_and_action_executes_successfully(): void
    {
        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.sync-cashbook'), [
            'shop' => $this->shop->code,
            'date' => '2026-09-15',
            'month' => '2026-09',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('sync_results');
        $response->assertSessionHas('success');
    }

    public function test_missing_staff_entries_are_synced_and_created_in_cashbook(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 2500.00,
            'fund_source' => 'sales_cash',
            'payment_type' => 'advance',
            'paid_on' => '2026-09-15',
            'status' => 'completed',
        ]);

        // Assert no cashbook transaction exists yet
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => $payment->id,
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.sync-cashbook'), [
            'shop' => $this->shop->code,
            'date' => '2026-09-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $this->shop->id,
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => $payment->id,
            'amount' => 2500.00,
        ]);

        $syncResults = session('sync_results');
        $this->assertCount(1, $syncResults['created']);
    }

    public function test_existing_entries_are_updated_without_creating_duplicates(): void
    {
        $payment = ShopStaffPayment::query()->create([
            'shop_id' => $this->shop->id,
            'employee_id' => $this->employee->id,
            'amount' => 3000.00,
            'fund_source' => 'sales_cash',
            'payment_type' => 'salary',
            'paid_on' => '2026-09-15',
            'status' => 'completed',
        ]);

        // Run sync to create original cashbook transaction
        $this->actingAs($this->owner)->post(route('shop-owner.staff.sync-cashbook'), [
            'shop' => $this->shop->code,
            'date' => '2026-09-15',
        ]);

        $this->assertEquals(1, ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->count());

        // Update payment amount
        $payment->update(['amount' => 4200.00]);

        // Run sync again
        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.sync-cashbook'), [
            'shop' => $this->shop->code,
            'date' => '2026-09-15',
        ]);

        $response->assertRedirect();

        // Verify still only 1 transaction exists with updated amount (no duplicates)
        $txs = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $txs);
        $this->assertEquals(4200.00, (float) $txs->first()->amount);

        $syncResults = session('sync_results');
        $this->assertCount(1, $syncResults['updated']);
    }

    public function test_deleted_staff_advances_are_detected_as_orphan_cashbook_entries(): void
    {
        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();

        // Create orphan cashbook transaction pointing to a non-existent payment ID 99999
        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryEntryType->id,
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 99999,
            'business_date' => '2026-09-15',
            'amount' => 1500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
            'notes' => 'Deleted staff advance orphan test',
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.sync-cashbook'), [
            'shop' => $this->shop->code,
            'date' => '2026-09-15',
        ]);

        $response->assertRedirect();
        $syncResults = session('sync_results');

        $this->assertCount(1, $syncResults['orphans']);
        $this->assertEquals($orphanTx->id, $syncResults['orphans'][0]['transaction_id']);
    }

    public function test_manual_deletion_of_selected_orphan_entry(): void
    {
        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();

        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryEntryType->id,
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 88888,
            'business_date' => '2026-09-15',
            'amount' => 1200.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.delete-cashbook-orphan'), [
            'shop' => $this->shop->code,
            'transaction_id' => $orphanTx->id,
            'date' => '2026-09-15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Selected orphan Cashbook entry deleted successfully.');
        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $orphanTx->id]);
    }

    public function test_shop_and_date_authorization_boundaries_are_enforced(): void
    {
        $otherShop = Shop::query()->create([
            'name' => 'Other Unauthorized Shop',
            'code' => 'OTHER_SHOP',
            'warehouse_tag' => 'OS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $salaryEntryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();

        $otherOrphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $otherShop->id,
            'entry_type_id' => $salaryEntryType->id,
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 77777,
            'business_date' => '2026-09-15',
            'amount' => 500.00,
            'direction' => 'expense',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // Attempt sync for unauthorized shop
        $syncResponse = $this->actingAs($this->owner)->post(route('shop-owner.staff.sync-cashbook'), [
            'shop' => $otherShop->code,
        ]);
        $syncResponse->assertSessionHas('error', 'Unauthorized shop selection.');

        // Attempt orphan delete for unauthorized shop transaction
        $deleteResponse = $this->actingAs($this->owner)->post(route('shop-owner.staff.delete-cashbook-orphan'), [
            'shop' => $this->shop->code,
            'transaction_id' => $otherOrphanTx->id,
        ]);
        $deleteResponse->assertSessionHas('error', 'Orphan transaction not found or access denied.');
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $otherOrphanTx->id]);
    }
}
