<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\ShopPettyCashExpense;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use App\Services\Finance\OwnedShopAccountingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShopAccountingCategorySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopPettyCashTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_default_daily_receipt_categories_include_shop_cash_and_staff_purposes(): void
    {
        $this->seed(ShopAccountingCategorySeeder::class);

        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Shop Cash Credit',
            'cash_effect' => true,
            'purpose' => 'shop_cash_credit',
        ]);
        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Staff Salary',
            'cash_effect' => true,
            'purpose' => 'staff_salary',
        ]);
        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Staff Salary Advance',
            'cash_effect' => true,
            'purpose' => 'staff_advance',
        ]);
    }

    public function test_manual_petty_cash_expense_can_update_previous_days(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'default_petty_cash_amount' => 550,
        ]);
        $user = User::factory()->create();
        $service = app(OwnedShopAccountingService::class);

        $autoExpense = $service->ensureDefaultPettyCashExpense($shop, Carbon::parse('2026-07-15'), (int) $user->id);

        $this->assertInstanceOf(ShopPettyCashExpense::class, $autoExpense);
        $this->assertSame('auto', $autoExpense->source);
        $this->assertSame(550.00, (float) $autoExpense->amount);

        $manualExpense = $service->recordManualPettyCashExpense($shop, Carbon::parse('2026-07-15'), 700.0, (int) $user->id);

        $this->assertSame('manual', $manualExpense->source);
        $this->assertSame(700.00, (float) $manualExpense->amount);

        Carbon::setTestNow(Carbon::parse('2026-07-16 10:30:00'));

        $updatedExpense = $service->recordManualPettyCashExpense($shop, Carbon::parse('2026-07-15'), 800.0, (int) $user->id);

        $this->assertSame($manualExpense->id, $updatedExpense->id);
        $this->assertSame('manual', $updatedExpense->source);
        $this->assertSame(800.00, (float) $updatedExpense->amount);
        $this->assertSame(700.00, (float) $updatedExpense->previous_amount);
        $this->assertSame($user->id, $updatedExpense->updated_by);
        $this->assertSame($user->id, $updatedExpense->amount_changed_by);
        $this->assertSame('2026-07-16 10:30:00', $updatedExpense->updated_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-16 10:30:00', $updatedExpense->amount_changed_at?->format('Y-m-d H:i:s'));

        $row = $service->pettyCashRows($shop, Carbon::parse('2026-07-15'), Carbon::parse('2026-07-15'))->first();

        $this->assertStringContainsString('Changed from Rs. 700.00 to Rs. 800.00', $row['amount_change_label']);
        $this->assertStringContainsString('for 15 Jul 2026', $row['amount_change_label']);

        Carbon::setTestNow();
    }

    public function test_petty_cash_rows_use_entered_credit_expense_and_running_balance(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $admin = User::factory()->create(['name' => 'Shabeer']);
        $shopUser = User::factory()->create();
        $service = app(OwnedShopAccountingService::class);

        $credit = ShopCredit::factory()->create([
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => true,
            'amount' => 1000,
            'created_by' => $admin->id,
            'business_date' => '2026-07-15',
        ]);
        $credit = ShopCredit::factory()->create([
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => false,
            'amount' => 9999,
            'created_by' => $admin->id,
            'business_date' => '2026-07-15',
        ]);
        ShopPettyCashExpense::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-15',
            'amount' => 550,
            'source' => 'manual',
            'created_by' => $shopUser->id,
        ]);

        $category = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Sales',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-15',
            'status' => 'submitted',
            'created_by' => $shopUser->id,
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 250,
        ]);

        $rows = $service->pettyCashRows($shop, Carbon::parse('2026-07-15'), Carbon::parse('2026-07-15'));
        $row = $rows->first();

        $this->assertCount(1, $rows);
        $this->assertSame('2026-07-15', $row['date']);
        $this->assertSame(1000.00, $row['admin_cash']);
        $this->assertStringContainsString('Shabeer - Rs. 1,000.00', $row['admin_cash_label']);
        $this->assertSame(550.00, $row['expense']);
        $this->assertArrayNotHasKey('sales', $row);
        $this->assertSame(450.00, $row['balance']);
        $this->assertNull($row['amount_change_label']);
    }

    public function test_entered_petty_cash_credit_shows_as_cash_flow_out(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Ashirwad',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $admin = User::factory()->create(['name' => 'Shabeer']);

        $credit = ShopCredit::factory()->create([
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => true,
            'amount' => 1000,
            'description' => 'Shop cash sent to shop',
            'created_by' => $admin->id,
            'business_date' => '2026-07-15',
        ]);

        $this->assertSame('Given to Shop', $credit->accountingLabel());
        $this->assertSame(-1000.00, $credit->signedAccountingAmount());
        $this->assertSame('Shop Cash Credit', $credit->shopCashLabel());
        $this->assertSame(1000.00, $credit->shopSignedAmount());
        $this->assertSame(
            1000.00,
            app(OwnedShopAccountingService::class)->previousClosingBalance($shop, Carbon::parse('2026-07-16')),
        );

        $report = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-15'));
        $pettyCashRows = $report['journal_rows']->where('source', 'owned_shop_petty_cash')->values();

        $this->assertSame(1000.00, $report['summary']['total_out']);
        $this->assertSame(-1000.00, $report['summary']['closing_balance']);
        $this->assertSame(1000.00, $report['summary']['petty_cash_out']);
        $this->assertCount(1, $pettyCashRows);
        $this->assertSame('2026-07-15', $pettyCashRows->first()['date']);
        $this->assertSame(1000.00, $pettyCashRows->first()['amount']);
        $this->assertSame('OUT', $pettyCashRows->first()['direction']);
        $this->assertSame('Shop Cash Credit - Ashirwad', $pettyCashRows->first()['journal']);
        $this->assertSame('Shop Cash Credit', $pettyCashRows->first()['category']);
        $this->assertSame('Shop cash sent to shop', $pettyCashRows->first()['remarks']);

        $nextMonthReport = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-08-01'));

        $this->assertSame(-1000.00, $nextMonthReport['summary']['opening_balance']);
        $this->assertSame(-1000.00, $nextMonthReport['summary']['closing_balance']);
    }

    public function test_petty_cash_rows_can_include_every_day_in_range(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopPettyCashExpense::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-15',
            'amount' => 550,
        ]);

        $rows = app(OwnedShopAccountingService::class)->pettyCashRows(
            $shop,
            Carbon::parse('2026-07-14'),
            Carbon::parse('2026-07-16'),
            includeEmptyDays: true,
        );

        $this->assertSame(['2026-07-16', '2026-07-15', '2026-07-14'], $rows->pluck('date')->all());
        $this->assertSame(0.00, $rows->firstWhere('date', '2026-07-14')['expense']);
        $this->assertSame(550.00, $rows->firstWhere('date', '2026-07-15')['expense']);
    }

    public function test_shop_owner_cannot_submit_future_daily_shop_receipt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

        $this->seed(RolePermissionSeeder::class);
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');
        $category = ShopAccountingCategory::query()->create([
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Cash Sales',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('shop-owner.accounting.index', ['tab' => 'cashbook']))
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-07-16',
                'submission_action' => 'submit',
                'opening_cash' => 0,
                'closing_cash' => 500,
                'lines' => [
                    [
                        'shop_accounting_category_id' => $category->id,
                        'amount' => 500,
                        'description' => 'Future cash sale',
                    ],
                ],
            ]);

        $response->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook']));
        $response->assertSessionHasErrors('business_date');
        $this->assertDatabaseMissing('shop_accounting_entries', [
            'shop_id' => $shop->id,
            'business_date' => '2026-07-16',
        ]);

        Carbon::setTestNow();
    }
}
