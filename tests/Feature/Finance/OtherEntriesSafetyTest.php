<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\TransactionGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtherEntriesSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Veg Market',
            'code' => 'CASIO-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_new_shop_receives_other_income_and_other_expense_enabled_by_default(): void
    {
        $otherIncomeType = LedgerEntryType::where('code', 'other_income')->firstOrFail();
        $otherExpenseType = LedgerEntryType::where('code', 'other_expense')->firstOrFail();

        $incomeSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherIncomeType->id)
            ->first();

        $expenseSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherExpenseType->id)
            ->first();

        $this->assertNotNull($incomeSetting, 'Other Income setting must exist for new shop');
        $this->assertNotNull($expenseSetting, 'Other Expense setting must exist for new shop');

        $this->assertTrue($incomeSetting->enabled, 'Other Income must be enabled by default');
        $this->assertTrue($expenseSetting->enabled, 'Other Expense must be enabled by default');

        $this->assertNull($incomeSetting->header_group_id, 'Other Income must be unassigned to any header by default');
        $this->assertNull($expenseSetting->header_group_id, 'Other Expense must be unassigned to any header by default');
    }

    public function test_syncing_is_idempotent_and_does_not_duplicate_settings(): void
    {
        $syncService = app(CashbookShopSyncService::class);
        $syncService->syncAndGetProfiles();

        $otherIncomeType = LedgerEntryType::where('code', 'other_income')->firstOrFail();
        $otherExpenseType = LedgerEntryType::where('code', 'other_expense')->firstOrFail();

        $this->assertEquals(
            1,
            ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->where('entry_type_id', $otherIncomeType->id)->count(),
            'Other Income setting must not be duplicated'
        );

        $this->assertEquals(
            1,
            ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->where('entry_type_id', $otherExpenseType->id)->count(),
            'Other Expense setting must not be duplicated'
        );
    }

    public function test_other_income_is_normal_income_and_other_expense_is_normal_expense(): void
    {
        $otherIncomeType = LedgerEntryType::where('code', 'other_income')->firstOrFail();
        $otherExpenseType = LedgerEntryType::where('code', 'other_expense')->firstOrFail();

        $this->assertEquals('income', $otherIncomeType->category);
        $this->assertEquals('expense', $otherExpenseType->category);
        $this->assertTrue($otherIncomeType->requiresNote());
        $this->assertTrue($otherExpenseType->requiresNote());
    }

    public function test_admin_can_disable_and_enable_other_income_and_other_expense(): void
    {
        $otherIncomeType = LedgerEntryType::where('code', 'other_income')->firstOrFail();
        $incomeSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherIncomeType->id)
            ->firstOrFail();

        $incomeSetting->update(['enabled' => false]);
        $this->assertFalse($incomeSetting->fresh()->enabled);

        $incomeSetting->update(['enabled' => true]);
        $this->assertTrue($incomeSetting->fresh()->enabled);
    }

    public function test_disabled_other_income_is_hidden_from_demo_page(): void
    {
        $otherIncomeType = LedgerEntryType::where('code', 'other_income')->firstOrFail();
        $incomeSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherIncomeType->id)
            ->firstOrFail();

        $incomeSetting->update(['enabled' => false]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop->id]));

        $response->assertStatus(200);
        $response->assertDontSee('input-s-'.$incomeSetting->id);
    }

    public function test_server_side_validation_requires_note_when_amount_greater_than_zero(): void
    {
        $generator = app(TransactionGenerator::class);

        // Amount 0 does not require note
        $zeroTx = $generator->record([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'entry_type_code' => 'other_income',
            'amount' => 0,
            'notes' => '',
            'entered_by' => $this->admin->id,
        ]);
        $this->assertInstanceOf(ShopLedgerTransaction::class, $zeroTx);

        // Amount > 0 requires note
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please add a note for Other Income.');

        $generator->record([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'entry_type_code' => 'other_income',
            'amount' => 1500,
            'notes' => '',
            'entered_by' => $this->admin->id,
        ]);
    }

    public function test_server_side_validation_passes_when_positive_amount_has_note(): void
    {
        $generator = app(TransactionGenerator::class);

        $tx = $generator->record([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'entry_type_code' => 'other_income',
            'amount' => 1500,
            'notes' => 'Supplier refund received',
            'entered_by' => $this->admin->id,
        ]);

        $this->assertInstanceOf(ShopLedgerTransaction::class, $tx);
        $this->assertEquals('Supplier refund received', $tx->notes);
    }

    public function test_disabling_entry_setting_does_not_delete_historical_transactions(): void
    {
        $generator = app(TransactionGenerator::class);

        $tx = $generator->record([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-04',
            'entry_type_code' => 'other_expense',
            'amount' => 850,
            'notes' => 'Emergency plumbing repair',
            'entered_by' => $this->admin->id,
        ]);

        $otherExpenseType = LedgerEntryType::where('code', 'other_expense')->firstOrFail();
        $expenseSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherExpenseType->id)
            ->firstOrFail();

        $expenseSetting->update(['enabled' => false]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'id' => $tx->id,
            'amount' => 850,
            'notes' => 'Emergency plumbing repair',
        ]);
    }
}
