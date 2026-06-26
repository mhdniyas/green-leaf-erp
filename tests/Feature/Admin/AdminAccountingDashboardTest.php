<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingInvoice;
use App\Models\ShopInvoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $ownedShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('admin.user.view');

        $this->ownedShop = Shop::create([
            'code' => 'OWN-001',
            'name' => 'Owned Outlet',
            'status' => 'active',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);
    }

    public function test_admin_can_view_accounting_dashboard_and_non_admin_users_cannot(): void
    {
        $shopUser = User::factory()->create(['shop_id' => $this->ownedShop->id]);
        $shopUser->assignRole('shop');

        $purchaseUser = User::factory()->create();
        $purchaseUser->assignRole('purchase');

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index'))
            ->assertOk()
            ->assertSee('Accounting Dashboard')
            ->assertSee('Owned Shop Accounting');

        $this->actingAs($shopUser)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();

        $this->actingAs($purchaseUser)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();
    }

    public function test_admin_can_store_valid_ownership_shares_and_invalid_totals_are_rejected(): void
    {
        $payload = [
            'ownerships' => [
                ['owner_name' => 'Partner A', 'ownership_percent' => 60, 'role_label' => 'Operations'],
                ['owner_name' => 'Partner B', 'ownership_percent' => 40, 'role_label' => 'Investor'],
            ],
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.ownerships.store', $this->ownedShop), $payload)
            ->assertRedirect(route('admin.accounting.owned-shops.show', $this->ownedShop));

        $this->assertDatabaseHas('shop_ownerships', [
            'shop_id' => $this->ownedShop->id,
            'owner_name' => 'Partner A',
            'ownership_percent' => 60.00,
        ]);
        $this->assertDatabaseHas('shop_ownerships', [
            'shop_id' => $this->ownedShop->id,
            'owner_name' => 'Partner B',
            'ownership_percent' => 40.00,
        ]);

        $invalidPayload = [
            'ownerships' => [
                ['owner_name' => 'Partner A', 'ownership_percent' => 55, 'role_label' => 'Operations'],
                ['owner_name' => 'Partner B', 'ownership_percent' => 40, 'role_label' => 'Investor'],
            ],
        ];

        $this->actingAs($this->admin)
            ->from(route('admin.accounting.owned-shops.show', $this->ownedShop))
            ->post(route('admin.accounting.owned-shops.ownerships.store', $this->ownedShop), $invalidPayload)
            ->assertRedirect(route('admin.accounting.owned-shops.show', $this->ownedShop))
            ->assertSessionHasErrors('ownerships');
    }

    public function test_admin_can_create_global_and_shop_specific_categories_and_shop_detail_lists_both(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.categories.store', $this->ownedShop), [
                'scope' => 'global',
                'type' => 'income',
                'name' => 'Sales Cash',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', $this->ownedShop));

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.categories.store', $this->ownedShop), [
                'scope' => 'shop',
                'type' => 'expense',
                'name' => 'Local Rent',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', $this->ownedShop));

        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Cash',
        ]);
        $this->assertDatabaseHas('shop_accounting_categories', [
            'shop_id' => $this->ownedShop->id,
            'type' => 'expense',
            'name' => 'Local Rent',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.show', $this->ownedShop))
            ->assertOk()
            ->assertSee('Sales Cash')
            ->assertSee('Local Rent');
    }

    public function test_admin_can_store_single_daily_entry_for_shop_and_standard_shops_are_rejected(): void
    {
        $incomeCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Income',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::create([
            'shop_id' => $this->ownedShop->id,
            'type' => 'expense',
            'name' => 'Daily Expense',
            'is_active' => true,
        ]);

        $payload = [
            'business_date' => '2026-06-24',
            'status' => 'finalized',
            'opening_cash' => 1000,
            'closing_cash' => 1250,
            'notes' => 'Closing balanced.',
            'lines' => [
                [
                    'shop_accounting_category_id' => $incomeCategory->id,
                    'amount' => 2500,
                    'description' => 'Counter sales',
                ],
                [
                    'shop_accounting_category_id' => $expenseCategory->id,
                    'amount' => 1250,
                    'description' => 'Cash expense',
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.entries.store', $this->ownedShop), $payload)
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop, 'date' => '2026-06-24']));

        $this->assertDatabaseHas('shop_accounting_entries', [
            'shop_id' => $this->ownedShop->id,
            'status' => 'finalized',
        ]);

        $entry = ShopAccountingEntry::query()->where('shop_id', $this->ownedShop->id)->firstOrFail();
        $this->assertSame('2026-06-24', $entry->business_date?->toDateString());
        $this->assertSame(2, $entry->lines()->count());

        $this->actingAs($this->admin)
            ->from(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop, 'date' => '2026-06-24']))
            ->post(route('admin.accounting.owned-shops.entries.store', $this->ownedShop), $payload)
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop, 'date' => '2026-06-24']))
            ->assertSessionHasErrors('business_date');

        $standardShop = Shop::create([
            'code' => 'STD-001',
            'name' => 'Standard Shop',
            'status' => 'active',
            'accounting_mode' => 'standard',
            'accounting_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.entries.store', $standardShop), $payload)
            ->assertNotFound();
    }

    public function test_admin_can_generate_settlement_invoice_and_duplicate_period_is_rejected(): void
    {
        $incomeCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Income',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Operating Expense',
            'is_active' => true,
        ]);

        $this->ownedShop->ownerships()->createMany([
            ['owner_name' => 'Partner A', 'ownership_percent' => 60, 'role_label' => 'Operations'],
            ['owner_name' => 'Partner B', 'ownership_percent' => 40, 'role_label' => 'Investor'],
        ]);

        $entry = ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-20',
            'status' => 'finalized',
            'created_by' => $this->admin->id,
        ]);
        $entry->lines()->createMany([
            [
                'shop_accounting_category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 3000,
                'description' => 'Sales',
            ],
            [
                'shop_accounting_category_id' => $expenseCategory->id,
                'type' => 'expense',
                'amount' => 1000,
                'description' => 'Rent',
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.invoices.store', $this->ownedShop), [
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
                'notes' => 'June settlement',
            ]);

        $invoice = ShopAccountingInvoice::query()->where('shop_id', $this->ownedShop->id)->firstOrFail();

        $response->assertRedirect(route('admin.accounting.owned-shops.invoices.show', [
            'shop' => $this->ownedShop,
            'invoice' => $invoice,
        ]));

        $invoice->refresh();
        $this->assertSame('2026-06-01', $invoice->period_start?->toDateString());
        $this->assertSame('2026-06-30', $invoice->period_end?->toDateString());
        $this->assertSame(3000.00, (float) $invoice->total_income);
        $this->assertSame(1000.00, (float) $invoice->total_expense);
        $this->assertSame(2000.00, (float) $invoice->net_amount);
        $this->assertDatabaseHas('shop_accounting_invoice_splits', [
            'shop_accounting_invoice_id' => $invoice->id,
            'owner_name_snapshot' => 'Partner A',
            'ownership_percent_snapshot' => 60.00,
            'share_amount' => 1200.00,
        ]);
        $this->assertDatabaseHas('shop_accounting_invoice_splits', [
            'shop_accounting_invoice_id' => $invoice->id,
            'owner_name_snapshot' => 'Partner B',
            'ownership_percent_snapshot' => 40.00,
            'share_amount' => 800.00,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.accounting.owned-shops.show', $this->ownedShop))
            ->post(route('admin.accounting.owned-shops.invoices.store', $this->ownedShop), [
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', $this->ownedShop))
            ->assertSessionHasErrors('invoice');
    }

    public function test_dashboard_reporting_shows_owned_shop_metrics_without_breaking_daily_workflow_finance_panel(): void
    {
        $incomeCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Income',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Operating Expense',
            'is_active' => true,
        ]);

        $entry = ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $entry->lines()->createMany([
            [
                'shop_accounting_category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 1500,
                'description' => 'Sales',
            ],
            [
                'shop_accounting_category_id' => $expenseCategory->id,
                'type' => 'expense',
                'amount' => 500,
                'description' => 'Expense',
            ],
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'final_total' => 900,
            'paid_amount' => 300,
            'balance_amount' => 600,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('Daily Workflow Finance')
            ->assertSee('Owned Shop Accounting')
            ->assertSee('Net Position')
            ->assertSee('Rs. 1,000.00')
            ->assertSee('Owned Outlet');
    }

    public function test_admin_can_review_entry_and_send_recheck_or_approval(): void
    {
        $entry = ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'status' => 'submitted',
            'created_by' => $this->admin->id,
            'submitted_by' => $this->admin->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $entry]), [
                'decision' => 'recheck',
                'admin_note' => 'Cash closing does not match.',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop, 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('recheck_required', $entry->status);
        $this->assertSame('Cash closing does not match.', $entry->admin_note);
        $this->assertNotNull($entry->reviewed_at);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $entry]), [
                'decision' => 'approve',
                'admin_note' => 'Approved after correction.',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop, 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('approved', $entry->status);
        $this->assertSame('Approved after correction.', $entry->admin_note);
    }
}
