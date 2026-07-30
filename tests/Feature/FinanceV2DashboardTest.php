<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanyAccountingCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceV2DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
            CompanyAccountingCategorySeeder::class,
        ]);
    }

    public function test_admin_can_open_finance_v2_dashboard_with_account_sections(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->createFinanceV2Fixture();

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.dashboard', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Finance V2 Dashboard')
            ->assertSeeText('Green Leaf Account Details')
            ->assertSeeText('Purchase')
            ->assertSeeText('Expense')
            ->assertSeeText('Salary')
            ->assertSeeText('Credit / Loan')
            ->assertSeeText('Aishwarya Veg')
            ->assertSeeText('Total received, paid, salary, loan and balance')
            ->assertSee(route('admin.finance-v2.green-leaf.section', ['section' => 'purchase', 'date' => '2026-07-29']), false);
    }

    public function test_green_leaf_purchase_drilldown_shows_supplier_invoice_totals(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->createFinanceV2Fixture();

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.green-leaf.section', ['section' => 'purchase', 'date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Green Leaf Account Details')
            ->assertSeeText('Purchase split')
            ->assertSeeText('Supplier One')
            ->assertSeeText('PINV-V2-001')
            ->assertSeeText('Rs. 1,500.00');
    }

    public function test_aishwarya_veg_page_shows_shop_balance_split_and_reports(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.aishwarya-veg', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Aishwarya Veg')
            ->assertSeeText($shop->name)
            ->assertSeeText('Rs. 800.00')
            ->assertSee(route('admin.finance-v2.shops.show', ['shop' => $shop, 'date' => '2026-07-29']), false);

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.reports', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Finance V2 Reports')
            ->assertSeeText('Shop Balance Report')
            ->assertSeeText($shop->name);
    }

    public function test_admin_can_create_and_approve_finance_v2_payment_with_verified_amount(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $this
            ->actingAs($admin)
            ->post(route('admin.finance-v2.payments.store'), [
                'shop_id' => $shop->id,
                'requested_amount' => 500,
                'payment_method' => 'cash',
                'payment_date' => '2026-07-29',
                'payment_reference' => 'CASH-V2-001',
                'shop_note' => 'Cash received by admin',
            ])
            ->assertRedirect();

        $paymentRequest = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('admin_v2', $paymentRequest->request_type);
        $this->assertSame('pending', $paymentRequest->status);

        $this
            ->actingAs($admin)
            ->patch(route('admin.finance-v2.payments.approve', $paymentRequest), [
                'admin_verified_amount' => 480,
                'admin_note' => 'Cash counted',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $paymentRequest->refresh();
        $invoice = ShopInvoice::query()->where('shop_id', $shop->id)->firstOrFail();

        $this->assertSame('approved', $paymentRequest->status);
        $this->assertSame('480.00', $paymentRequest->admin_verified_amount);
        $this->assertSame('480.00', $paymentRequest->approved_amount);
        $this->assertSame('480.00', $paymentRequest->applied_amount);
        $this->assertSame('480.00', $invoice->fresh()->paid_amount);
        $this->assertSame('320.00', $invoice->fresh()->balance_amount);
    }

    public function test_cheque_payment_does_not_update_shop_until_cleared(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $this
            ->actingAs($admin)
            ->post(route('admin.finance-v2.payments.store'), [
                'shop_id' => $shop->id,
                'requested_amount' => 500,
                'payment_method' => 'cheque',
                'payment_date' => '2026-07-29',
                'payment_reference' => 'CHQ-001',
                'cheque_bank_name' => 'Test Bank',
                'cheque_date' => '2026-07-30',
            ])
            ->assertRedirect();

        $paymentRequest = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('admin.finance-v2.payments.approve', $paymentRequest), [
                'admin_verified_amount' => 500,
                'admin_note' => 'Cheque received',
            ])
            ->assertSessionHasErrors('cheque_status');

        $invoice = ShopInvoice::query()->where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('pending', $paymentRequest->fresh()->status);

        $this
            ->actingAs($admin)
            ->patch(route('admin.finance-v2.payments.cheque', $paymentRequest), [
                'cheque_status' => 'cleared',
                'admin_note' => 'Cheque cleared',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this
            ->actingAs($admin)
            ->patch(route('admin.finance-v2.payments.approve', $paymentRequest), [
                'admin_verified_amount' => 500,
                'admin_note' => 'Approved after clearance',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('approved', $paymentRequest->fresh()->status);
        $this->assertSame('500.00', $invoice->fresh()->paid_amount);
    }

    private function createFinanceV2Fixture(): Shop
    {
        $supplier = Supplier::factory()->create(['name' => 'Supplier One']);
        PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-V2-001',
            'amount' => 1500,
            'paid_amount' => 500,
            'payment_status' => 'partial',
            'created_at' => '2026-07-29 10:00:00',
        ]);

        $category = CompanyAccountingCategory::query()
            ->where('type', 'expense')
            ->firstOrFail();
        CompanyAccountingEntry::query()->create([
            'company_accounting_category_id' => $category->id,
            'type' => 'expense',
            'business_date' => '2026-07-29',
            'payment_mode' => 'cash',
            'amount' => 300,
            'description' => 'Market expense',
            'status' => CompanyAccountingEntry::StatusFinal,
            'created_by' => User::factory()->create()->id,
        ]);

        $client = Client::query()->updateOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
        $shop = Shop::factory()->create([
            'name' => 'Aishwarya Shop One',
            'code' => 'AISH-SHOP-ONE',
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $product = Product::factory()->create(['name' => 'Tomato']);
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'invoice_number' => 'SINV-V2-001',
            'business_date' => '2026-07-29',
            'subtotal' => 800,
            'final_total' => 800,
            'paid_amount' => 0,
            'balance_amount' => 800,
            'payment_status' => 'unpaid',
        ]);
        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => 'Tomato',
            'approved_qty' => 8,
            'unit_price' => 100,
            'line_subtotal' => 800,
            'final_line_total' => 800,
        ]);

        return $shop;
    }
}
