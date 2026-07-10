<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopAccountingInvoice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
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
        $this->admin->assignRole('admin');
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

        $purchaser = User::factory()->create(['name' => 'Achu']);
        $purchaser->assignRole('purchaser');

        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'type' => 'out',
            'amount' => 125000,
            'description' => 'Advance issued',
            'business_date' => '2026-06-12',
            'created_by' => $this->admin->id,
        ]);

        $category = ShopAccountingCategory::create([
            'shop_id' => $this->ownedShop->id,
            'type' => 'expense',
            'name' => 'Local Sale',
            'is_active' => true,
        ]);

        $entry = ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-12',
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ShopAccountingEntryLine::create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'amount' => 800,
            'description' => 'Petty cash',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-06-12']))
            ->assertOk()
            ->assertSee('Accounting Dashboard')
            ->assertSee('accounting-sidebar-toggle', false)
            ->assertSee('data-admin-dashboard-switcher', false)
            ->assertSee('Admin')
            ->assertSee('Purchasing Dashboard')
            ->assertSee('Inventory')
            ->assertSee('Staff')
            ->assertSee('Owned Shop Accounting')
            ->assertSee('Cash Flow Report')
            ->assertSee('Combined purchaser and owned shop cash journal')
            ->assertSee('Achu')
            ->assertSee('Local Sale')
            ->assertDontSee('Shop Sales Table')
            ->assertDontSee('Vendor Cash Flow');

        $this->actingAs($shopUser)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();

        $this->actingAs($purchaseUser)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();
    }

    public function test_accounting_dashboard_cash_flow_hides_future_dates_and_combines_purchaser_day_entries(): void
    {
        $purchaser = User::factory()->create(['name' => 'Purchaser Niyas']);
        $purchaser->assignRole('purchaser');

        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'type' => 'out',
            'amount' => 6000,
            'description' => 'Debit for invoice: PENDING-BILL-VC-DEMO-DRAFT-001',
            'business_date' => '2026-07-01',
            'created_by' => $this->admin->id,
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'type' => 'out',
            'amount' => 9000,
            'description' => 'Debit for invoice: PENDING-BILL-VC-20260701-739C',
            'business_date' => '2026-07-01',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-07-01']));

        $response->assertOk()
            ->assertSee('Purchaser Paid')
            ->assertSee('Purchaser Receive')
            ->assertSee('data-cash-flow-tabs', false)
            ->assertSee('data-cash-flow-tab-button="journal"', false)
            ->assertSee('01-Jul')
            ->assertDontSee('02-Jul')
            ->assertSee('Rs. 15,000.00')
            ->assertSee('Purchaser Niyas')
            ->assertSee('combined 2 entries');

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-07-01', 'cash_tab' => 'daily-balance']))
            ->assertOk()
            ->assertSee('Running cash position for the month')
            ->assertSee('data-cash-flow-active-tab="daily-balance"', false);
    }

    public function test_daily_sales_report_uses_accounting_route_and_renders_accounting_tables(): void
    {
        ShopInvoice::factory()->create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'invoice_number' => 'SINV-DAILY-ACCOUNTING',
            'final_total' => 1400,
            'paid_amount' => 500,
            'balance_amount' => 900,
        ]);

        $otherShop = Shop::create([
            'code' => 'STD-002',
            'name' => 'Standard Outlet',
            'status' => 'active',
            'accounting_mode' => 'standard',
            'accounting_enabled' => false,
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $otherShop->id,
            'business_date' => '2026-06-24',
            'invoice_number' => 'SINV-OTHER-SHOP',
            'final_total' => 2100,
            'paid_amount' => 2100,
            'balance_amount' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.daily-sales', [
                'date' => '2026-06-24',
                'status' => 'pending',
                'tab' => 'invoices',
                'owned_shop_id' => $this->ownedShop->id,
            ]))
            ->assertOk()
            ->assertSee('Daily Sale Report')
            ->assertSee('Owned Shop')
            ->assertSee('Sales by Shop')
            ->assertSee('Invoice list')
            ->assertSee('Export Excel')
            ->assertSee('Export PDF')
            ->assertSee('SINV-DAILY-ACCOUNTING')
            ->assertSee('Owned Outlet')
            ->assertSee('Pending')
            ->assertDontSee('SINV-OTHER-SHOP')
            ->assertDontSee(route('purchasing.shop-invoices.index'), false)
            ->assertDontSee('Sales Daily View');
    }

    public function test_vendor_reports_use_accounting_route_and_dashboard_links_stay_inside_accounting(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Fresh Supplier']);

        PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-ACCOUNTING-001',
            'amount' => 3200,
            'paid_amount' => 1200,
            'created_at' => '2026-06-24 09:30:00',
            'updated_at' => '2026-06-24 09:30:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-06-24']))
            ->assertOk()
            ->assertSee(route('admin.accounting.daily-sales', ['date' => '2026-06-24']), false)
            ->assertSee(route('admin.accounting.vendor-reports', ['date' => '2026-06-24']), false)
            ->assertDontSee(route('purchasing.shop-invoices.index'), false);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.vendor-reports', ['date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('Vendor Reports')
            ->assertSee('Vendor ledger by supplier')
            ->assertSee('PINV-ACCOUNTING-001')
            ->assertSee('Fresh Supplier');
    }

    public function test_daily_sales_report_can_filter_to_all_owned_shops_only(): void
    {
        ShopInvoice::factory()->create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'invoice_number' => 'SINV-OWNED-ONLY',
            'final_total' => 1600,
            'paid_amount' => 600,
            'balance_amount' => 1000,
        ]);

        $standardShop = Shop::create([
            'code' => 'STD-003',
            'name' => 'Standard Retail',
            'status' => 'active',
            'accounting_mode' => 'standard',
            'accounting_enabled' => false,
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $standardShop->id,
            'business_date' => '2026-06-24',
            'invoice_number' => 'SINV-STANDARD-ONLY',
            'final_total' => 2500,
            'paid_amount' => 2500,
            'balance_amount' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.daily-sales', [
                'date' => '2026-06-24',
                'tab' => 'shops',
                'only_owned_shops' => 1,
            ]))
            ->assertOk()
            ->assertSee('Only owned shops')
            ->assertSee('Owned Outlet')
            ->assertDontSee('Standard Retail');
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
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code]));

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
            ->from(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code]))
            ->post(route('admin.accounting.owned-shops.ownerships.store', $this->ownedShop), $invalidPayload)
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code]))
            ->assertSessionHasErrors('ownerships');
    }

    public function test_admin_can_enable_existing_shop_for_owned_shop_accounting_from_index(): void
    {
        $shop = Shop::create([
            'code' => 'STD-NEW-001',
            'name' => 'Future Owned Shop',
            'status' => 'active',
            'accounting_mode' => 'standard',
            'accounting_enabled' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.store'), [
                'shop_id' => $shop->id,
                'accounting_mode' => 'partnership',
                'reserve_amount' => 1500,
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $shop->code]));

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'accounting_mode' => 'partnership',
            'accounting_enabled' => true,
            'reserve_amount' => 1500.00,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.index'))
            ->assertOk()
            ->assertSee('Future Owned Shop');
    }

    public function test_admin_can_update_owned_shop_reserve_amount(): void
    {
        $this->ownedShop->update(['reserve_amount' => 500]);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.reserve-amount.update', $this->ownedShop), [
                'reserve_amount' => 1850.75,
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook']));

        $this->assertDatabaseHas('shops', [
            'id' => $this->ownedShop->id,
            'reserve_amount' => 1850.75,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook']))
            ->assertOk()
            ->assertSee('Reserve Amount')
            ->assertSee('1,850.75');
    }

    public function test_owned_shop_register_shows_update_notifications_for_submitted_and_recheck_entries(): void
    {
        $shopOwner = User::factory()->create(['shop_id' => $this->ownedShop->id, 'name' => 'Register Shop Owner']);
        $shopOwner->assignRole('shop');

        ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'status' => 'submitted',
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        $recheckShop = Shop::create([
            'code' => 'PART-001',
            'name' => 'Partnership Outlet',
            'status' => 'active',
            'accounting_mode' => 'partnership',
            'accounting_enabled' => true,
        ]);

        ShopAccountingEntry::create([
            'shop_id' => $recheckShop->id,
            'business_date' => '2026-06-24',
            'status' => 'recheck_required',
            'created_by' => $this->admin->id,
            'submitted_by' => $this->admin->id,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.index'))
            ->assertOk()
            ->assertSee('Owned and partnership shops table')
            ->assertSee('Update Alert')
            ->assertSee('New Update')
            ->assertSee('Register Shop Owner')
            ->assertSee('Recheck Update')
            ->assertSee('Partnership Outlet');
    }

    public function test_admin_can_create_global_and_shop_specific_categories_and_shop_detail_lists_both(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.categories.store', $this->ownedShop), [
                'scope' => 'global',
                'type' => 'income',
                'name' => 'Sales Cash',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.categories.index', ['shop' => $this->ownedShop->code]));

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.categories.store', $this->ownedShop), [
                'scope' => 'shop',
                'type' => 'expense',
                'name' => 'Local Rent',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.categories.index', ['shop' => $this->ownedShop->code]));

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
            ->get(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook']))
            ->assertOk()
            ->assertDontSee('Ownership shares')
            ->assertDontSee('Category management moved to its own page')
            ->assertSee('Manage Ownership Shares')
            ->assertSee('Open Categories Page');

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.categories.index', ['shop' => $this->ownedShop->code]))
            ->assertOk()
            ->assertSee('Add new ledger category')
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
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'date' => '2026-06-24']));

        $this->assertDatabaseHas('shop_accounting_entries', [
            'shop_id' => $this->ownedShop->id,
            'status' => 'finalized',
        ]);

        $entry = ShopAccountingEntry::query()->where('shop_id', $this->ownedShop->id)->firstOrFail();
        $this->assertSame('2026-06-24', $entry->business_date?->toDateString());
        $this->assertSame(2, $entry->lines()->count());

        $this->actingAs($this->admin)
            ->from(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'date' => '2026-06-24']))
            ->post(route('admin.accounting.owned-shops.entries.store', $this->ownedShop), $payload)
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'date' => '2026-06-24']))
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
            'shop' => $this->ownedShop->code,
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
            ->from(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code]))
            ->post(route('admin.accounting.owned-shops.invoices.store', $this->ownedShop), [
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code]))
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
            'invoice_number' => 'SINV-OWN-001',
            'final_total' => 900,
            'paid_amount' => 300,
            'balance_amount' => 600,
        ]);

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'type' => 'in',
            'amount' => 800,
            'description' => 'Advance',
            'created_by' => $this->admin->id,
            'business_date' => '2026-06-24',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('Purchasing Dashboard')
            ->assertSee('Cash Flow Report')
            ->assertSee('Purchasing workflow moved out of admin')
            ->assertSee('Purchaser Cash Flow')
            ->assertSee('Owned Shop Accounting')
            ->assertDontSee('Shop Sales Table')
            ->assertDontSee('Vendor Cash Flow')
            ->assertSee('Rs. 1,000.00')
            ->assertSee('Owned Outlet')
            ->assertDontSee('SINV-OWN-001')
            ->assertSee($purchaser->name);
    }

    public function test_owned_shop_monthly_analytics_include_submitted_entries_not_only_approved_ones(): void
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
            'name' => 'Cleaning Expense',
            'is_active' => true,
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-07-08',
            'invoice_number' => 'SINV-OWN-ANALYTICS-001',
            'final_total' => 8500,
            'paid_amount' => 5000,
            'balance_amount' => 3500,
        ]);

        $entry = ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-07-08',
            'status' => 'submitted',
            'created_by' => $this->admin->id,
            'submitted_by' => $this->admin->id,
            'submitted_at' => now(),
        ]);

        $entry->lines()->createMany([
            [
                'shop_accounting_category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 7000,
                'description' => 'Counter sales',
            ],
            [
                'shop_accounting_category_id' => $expenseCategory->id,
                'type' => 'expense',
                'amount' => 1200,
                'description' => 'Cleaning and supplies',
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.show', [
                'shop' => $this->ownedShop->code,
                'tab' => 'bills',
                'date' => '2026-07-08',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertSee('Rs. 8,500.00')
            ->assertSee('Rs. 5,000.00')
            ->assertSee('Rs. 3,500.00')
            ->assertSee('Rs. 7,000.00')
            ->assertSee('Rs. 1,200.00')
            ->assertSee('Rs. 10,800.00');
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

        $incomeCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);

        $expenseCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Cash Purchase',
            'is_active' => true,
        ]);

        $entry->lines()->createMany([
            [
                'shop_accounting_category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 1000,
                'description' => 'Morning counter sales',
            ],
            [
                'shop_accounting_category_id' => $expenseCategory->id,
                'type' => 'expense',
                'amount' => 350,
                'description' => 'Urgent stock purchase',
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook', 'date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('Request details')
            ->assertSee('Morning counter sales')
            ->assertSee('Urgent stock purchase')
            ->assertSee('Approve All Items')
            ->assertSee('Approve This Item')
            ->assertSee('Send Item For Recheck')
            ->assertSee('approve-entry-modal', false)
            ->assertSee('Confirm Approve All')
            ->assertSee('line-review-modal', false);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $entry]), [
                'decision' => 'review_lines',
                'line_reviews' => [
                    $entry->lines[0]->id => [
                        'decision' => 'approve',
                        'review_note' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('submitted', $entry->status);
        $this->assertNull($entry->admin_note);
        $this->assertNotNull($entry->reviewed_at);
        $this->assertSame('approved', $entry->lines()->findOrFail($entry->lines[0]->id)->review_status);
        $this->assertNull($entry->lines()->findOrFail($entry->lines[1]->id)->review_status);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $entry]), [
                'decision' => 'review_lines',
                'admin_note' => 'Expense line needs bill confirmation.',
                'line_reviews' => [
                    $entry->lines[1]->id => [
                        'decision' => 'recheck',
                        'review_note' => 'Upload supplier slip.',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('recheck_required', $entry->status);
        $this->assertSame('Expense line needs bill confirmation.', $entry->admin_note);
        $this->assertSame('recheck_required', $entry->lines()->findOrFail($entry->lines[1]->id)->review_status);
        $this->assertSame('Upload supplier slip.', $entry->lines()->findOrFail($entry->lines[1]->id)->review_note);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $entry]), [
                'decision' => 'approve',
                'admin_note' => 'Approved after correction.',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $this->ownedShop->code, 'tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('approved', $entry->status);
        $this->assertSame('Approved after correction.', $entry->admin_note);
        $entry->load('lines');
        $this->assertTrue($entry->lines->every(fn (ShopAccountingEntryLine $line): bool => $line->review_status === 'approved'));
    }

    public function test_admin_dashboard_lists_pending_owned_shop_updates_with_line_items(): void
    {
        $incomeCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Income - Cash',
            'is_active' => true,
        ]);

        $expenseCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Cash Purchase',
            'is_active' => true,
        ]);

        $shopOwner = User::factory()->create(['shop_id' => $this->ownedShop->id]);
        $shopOwner->assignRole('shop');

        $entry = ShopAccountingEntry::create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-06-24',
            'status' => 'submitted',
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'shop_reply_note' => 'Added the latest shop update.',
        ]);

        $entry->lines()->createMany([
            [
                'shop_accounting_category_id' => $incomeCategory->id,
                'type' => 'income',
                'amount' => 1500,
                'description' => 'Cash counter sales',
            ],
            [
                'shop_accounting_category_id' => $expenseCategory->id,
                'type' => 'expense',
                'amount' => 250,
                'description' => 'Urgent local purchase',
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index', ['date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('Submitted ledger updates waiting for accounting')
            ->assertSee($this->ownedShop->name)
            ->assertSee('Sales Income - Cash')
            ->assertSee('Cash counter sales')
            ->assertSee('Cash Purchase')
            ->assertSee('Urgent local purchase')
            ->assertSee('Review This Update');
    }

    public function test_admin_can_approve_shop_payment_request_and_invoice_sales_totals_update(): void
    {
        $shopOwner = User::factory()->create([
            'shop_id' => $this->ownedShop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $this->ownedShop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $shopOwner->id,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $this->ownedShop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-OWNED-APR-001',
            'business_date' => today()->toDateString(),
            'status' => 'payment_pending',
            'delivery_status' => 'received_full',
            'payment_status' => 'partially_paid',
            'final_total' => 900.00,
            'paid_amount' => 200.00,
            'balance_amount' => 700.00,
            'subtotal' => 900.00,
            'shortage_total' => 0.00,
            'discount_total' => 0.00,
        ]);

        $product = Product::factory()->create();
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 10.00,
            'unit' => 'kg',
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'approved_qty' => 10.00,
            'delivered_qty' => 10.00,
            'shortage_qty' => 0.00,
            'unit_price' => 90.00,
            'line_subtotal' => 900.00,
            'shortage_amount' => 0.00,
            'final_line_total' => 900.00,
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->ownedShop->id,
            'requested_by' => $shopOwner->id,
            'request_type' => 'custom',
            'requested_amount' => 300.00,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('purchasing.shop-invoices.payment-requests.review', $paymentRequest), [
                'decision' => 'approve',
                'admin_note' => 'Cash received and verified.',
            ])
            ->assertRedirect(route('purchasing.shop-invoices.show', $invoice));

        $paymentRequest->refresh();
        $invoice->refresh();

        $this->assertSame('approved', $paymentRequest->status);
        $this->assertSame(300.00, (float) $paymentRequest->approved_amount);
        $this->assertSame('Cash received and verified.', $paymentRequest->admin_note);
        $this->assertSame(500.00, (float) $invoice->paid_amount);
        $this->assertSame(400.00, (float) $invoice->balance_amount);
        $this->assertSame('partially_paid', $invoice->payment_status);
    }
}
