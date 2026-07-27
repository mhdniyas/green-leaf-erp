<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopStaffPayment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccountingDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        view()->share('errors', new ViewErrorBag);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_accounting_dashboard_does_not_show_purchasing_handoff_card(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.index', ['date' => '2026-07-17']))
            ->assertOk()
            ->assertSee('Client Accounting')
            ->assertDontSee('Purchasing workflow moved out of admin')
            ->assertDontSee('Open Purchasing Dashboard');
    }

    public function test_admin_can_open_client_daily_report_with_invoice_collection_loans_and_balances(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $client = Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $shop = Shop::factory()->create([
            'name' => 'Aishwarya Veg Shop 1',
            'code' => 'SHOP_AISHWARYA_01',
            'client_id' => $client->id,
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'created_by' => $admin->id,
            'business_date' => '2026-07-27',
        ]);
        ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260727-SHOP_AISHWARYA_01',
            'business_date' => '2026-07-27',
            'final_total' => 1200,
            'paid_amount' => 500,
            'balance_amount' => 700,
            'generated_by' => $admin->id,
        ]);
        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'in',
            'is_petty_cash' => false,
            'amount' => 300,
            'description' => 'Loan to shop owner',
            'created_by' => $admin->id,
            'business_date' => '2026-07-27',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        ShopStaffPayment::query()->create([
            'employee_id' => Employee::factory()->create(['name' => 'Shared Employee'])->id,
            'shop_id' => $shop->id,
            'paid_by' => $admin->id,
            'paid_on' => '2026-07-27',
            'amount' => 250,
            'payment_type' => 'salary',
            'fund_source' => 'petty_cash',
            'status' => 'paid',
        ]);
        $expenseCategory = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'purpose' => 'general',
            'name' => 'Daily Expense',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-27',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'daily_entry_key' => ShopAccountingEntry::dailyEntryKey($shop->id, '2026-07-27'),
            'status' => 'approved',
            'opening_cash' => 900,
            'closing_cash' => 650,
            'created_by' => $admin->id,
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $expenseCategory->id,
            'type' => 'expense',
            'cash_effect' => true,
            'amount' => 250,
            'description' => 'Daily Expense',
            'review_status' => 'approved',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.clients.show', [
                'client' => $client,
                'start_date' => '2026-07-27',
                'end_date' => '2026-07-27',
            ]))
            ->assertOk()
            ->assertSeeText('Aishwarya Veg Daily Report')
            ->assertSeeText('Shop Daily Table')
            ->assertSeeText('Aishwarya Veg Shop 1')
            ->assertSeeText('Invoice Collected')
            ->assertSeeText('Invoice Pending')
            ->assertSeeText('Total Expense')
            ->assertSeeText('Opening Balance')
            ->assertSeeText('Closing Balance')
            ->assertSeeText('Rs. 500.00')
            ->assertSeeText('Rs. 700.00')
            ->assertSeeText('Rs. 300.00')
            ->assertSeeText('Rs. 250.00')
            ->assertSeeText('Rs. 900.00')
            ->assertSeeText('Rs. 650.00')
            ->assertDontSeeText('SINV-20260727-SHOP_AISHWARYA_01')
            ->assertDontSeeText('Shared Employee');
    }

    public function test_admin_overview_shows_green_leaf_sales_channels(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $client = Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $directShop = Shop::factory()->create([
            'name' => 'Direct Sales Shop',
            'code' => 'SHOP_DIRECT_01',
            'client_id' => null,
        ]);
        $clientShop = Shop::factory()->create([
            'name' => 'Aishwarya Veg Shop 1',
            'code' => 'SHOP_AISHWARYA_01',
            'client_id' => $client->id,
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);
        $directOrder = ShopOrder::factory()->approved()->create([
            'shop_id' => $directShop->id,
            'created_by' => $admin->id,
            'business_date' => '2026-07-27',
        ]);
        $clientOrder = ShopOrder::factory()->approved()->create([
            'shop_id' => $clientShop->id,
            'created_by' => $admin->id,
            'business_date' => '2026-07-27',
        ]);

        ShopInvoice::query()->create([
            'shop_id' => $directShop->id,
            'shop_order_id' => $directOrder->id,
            'invoice_number' => 'SINV-20260727-DIRECT',
            'business_date' => '2026-07-27',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'partially_paid',
            'subtotal' => 1000,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 1000,
            'paid_amount' => 600,
            'balance_amount' => 400,
            'generated_by' => $admin->id,
        ]);
        ShopInvoice::query()->create([
            'shop_id' => $clientShop->id,
            'shop_order_id' => $clientOrder->id,
            'invoice_number' => 'SINV-20260727-AISHWARYA',
            'business_date' => '2026-07-27',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 2000,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 2000,
            'paid_amount' => 0,
            'balance_amount' => 2000,
            'generated_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.overview', ['date' => '2026-07-27']))
            ->assertOk()
            ->assertSeeText('Green Leaf Main Company')
            ->assertSeeText('Direct Sales')
            ->assertSeeText('Aishwarya Sales')
            ->assertSeeText('Rs. 3,000.00')
            ->assertSeeText('Rs. 600.00')
            ->assertSeeText('Rs. 2,400.00')
            ->assertSee(route('admin.accounting.daily-sales', [
                'date' => '2026-07-27',
                'sales_scope' => 'direct',
            ]))
            ->assertSee(route('admin.accounting.clients.show', [
                'client' => $client,
                'start_date' => '2026-07-27',
                'end_date' => '2026-07-27',
            ]));
    }
}
