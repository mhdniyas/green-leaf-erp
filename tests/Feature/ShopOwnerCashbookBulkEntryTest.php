<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShopOwnerCashbookBulkEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(LedgerEntryTypeSeeder::class);
        \Spatie\Permission\Models\Role::findOrCreate('shop', 'web');
        Permission::findOrCreate('sales.order.create', 'web');
    }

    public function test_shop_owner_can_bulk_record_entries(): void
    {
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        ShopOwnerAssignment::factory()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);

        $cashSales = LedgerEntryType::where('code', 'cash_sales')->first()
            ?? LedgerEntryType::create(['code' => 'cash_sales', 'name' => 'Cash Sales', 'category' => 'income']);

        $salary = LedgerEntryType::where('code', 'salary')->first()
            ?? LedgerEntryType::create(['code' => 'salary', 'name' => 'Salary', 'category' => 'expense']);

        $glBill = LedgerEntryType::where('code', 'gl_bill')->first()
            ?? LedgerEntryType::create(['code' => 'gl_bill', 'name' => 'GL Bill', 'category' => 'expense']);

        $response = $this->actingAs($user)
            ->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
                'business_date' => '2026-08-15',
                'entries' => [
                    [
                        'entry_type_code' => $cashSales->code,
                        'amount' => 9500.00,
                        'funding_source' => 'none',
                        'notes' => 'Today sales',
                    ],
                    [
                        'entry_type_code' => $salary->code,
                        'amount' => 5000.00,
                        'funding_source' => 'sales',
                        'notes' => 'Staff salary',
                    ],
                    [
                        'entry_type_code' => $glBill->code,
                        'amount' => 12000.00,
                        'funding_source' => 'none',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $shop->id,
            'business_date' => '2026-08-15',
            'amount' => 9500.00,
        ]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'shop_id' => $shop->id,
            'business_date' => '2026-08-15',
            'amount' => 5000.00,
        ]);

        // GL bill must be skipped
        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'shop_id' => $shop->id,
            'business_date' => '2026-08-15',
            'amount' => 12000.00,
        ]);
    }

    public function test_shop_owner_cashbook_page_renders_copy_yesterday_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        ShopOwnerAssignment::factory()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($user)
            ->get(route('shop-owner.cashbook.show'))
            ->assertOk()
            ->assertSee('Copy Yesterday');
    }
}
