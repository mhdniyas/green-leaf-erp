<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CompanyPayableService;
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
            ->assertSeeText('Company Dashboard')
            ->assertSeeText('Purchase')
            ->assertSeeText('Expense')
            ->assertSeeText('Salary')
            ->assertSeeText('Company Balance')
            ->assertSeeText('Aishwarya Veg')
            ->assertSeeText('Daily received, paid, salary, expense and balance')
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
            ->assertSeeText('Green Leaf Account')
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

        $client = Client::query()->where('code', 'AISHWARYA_VEG')->firstOrFail();

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.aishwarya-veg', ['date' => '2026-07-29']))
            ->assertRedirect(route('admin.finance-v2.clients.show', ['client' => $client, 'date' => '2026-07-29']));

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.clients.show', ['client' => $client, 'date' => '2026-07-29']))
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

    public function test_admin_can_open_create_shop_payment_page_with_detail_layout(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.payments.create', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Create shop payment')
            ->assertSeeText('Enter payment details')
            ->assertSeeText('No shop selected')
            ->assertSeeText($shop->name)
            ->assertSee('data-payment-create', false)
            ->assertSee(route('admin.finance-v2.payments.shop-context', ['shop' => '__SHOP__']), false);
    }

    public function test_shop_payment_context_returns_pending_invoices_and_allocation_preview(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $response = $this
            ->actingAs($admin)
            ->getJson(route('admin.finance-v2.payments.shop-context', [
                'shop' => $shop,
                'date' => '2026-07-29',
                'amount' => 500,
            ]))
            ->assertOk()
            ->assertJsonPath('shop.name', $shop->name)
            ->assertJsonPath('summary.pending_bills', 800)
            ->assertJsonPath('preview.applied_amount', 500)
            ->assertJsonPath('preview.credit_amount', 0)
            ->assertJsonPath('pending_invoices.0.invoice_number', 'SINV-V2-001')
            ->assertJsonPath('pending_invoices.0.balance_amount', 800);

        $this->assertStringContainsString('cover Rs. 500.00 of pending bills', (string) $response->json('preview.message'));

        $this
            ->actingAs($admin)
            ->getJson(route('admin.finance-v2.payments.shop-context', [
                'shop' => $shop,
                'date' => '2026-07-29',
                'amount' => 1000,
            ]))
            ->assertOk()
            ->assertJsonPath('preview.applied_amount', 800)
            ->assertJsonPath('preview.credit_amount', 200);
    }

    public function test_shop_payment_context_includes_open_company_payables_for_shop(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Fridge Repair',
            'type' => 'expense',
            'cash_effect' => true,
            'purpose' => 'general',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'entry_type' => 'daily',
            'status' => 'submitted',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $admin->id,
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);
        $line = ShopAccountingEntryLine::query()->create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'funding_source' => 'company',
            'amount' => 2500,
            'description' => 'Company paid repair',
        ]);
        app(CompanyPayableService::class)->markCompanyPayableOnLine($line);

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.payments.create', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Open Company Payables');

        $this
            ->actingAs($admin)
            ->getJson(route('admin.finance-v2.payments.shop-context', [
                'shop' => $shop,
                'date' => '2026-07-29',
                'amount' => 100,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.company_payable_remaining', 2500)
            ->assertJsonPath('summary.company_payable_pending_count', 1)
            ->assertJsonPath('company_payables.0.category', 'Fridge Repair')
            ->assertJsonPath('company_payables.0.status', 'pending')
            ->assertJsonPath('company_payables.0.status_label', 'Ready for review')
            ->assertJsonPath('company_payables.0.remaining', 2500)
            ->assertJsonPath('company_payables.0.url', route('admin.finance-v2.company-payables.show', $line));
    }

    public function test_admin_can_open_payments_index_and_show_pages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shop = $this->createFinanceV2Fixture();

        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Shop Supplies',
            'type' => 'expense',
            'cash_effect' => true,
            'purpose' => 'general',
            'is_active' => true,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'entry_type' => 'daily',
            'status' => 'submitted',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $admin->id,
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);
        ShopAccountingEntryLine::query()->create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'amount' => 150,
            'description' => 'Cleaning supplies',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.payments.index', ['date' => '2026-07-29']))
            ->assertOk()
            ->assertSeeText('Create Shop Payment');

        $this
            ->actingAs($admin)
            ->post(route('admin.finance-v2.payments.store'), [
                'shop_id' => $shop->id,
                'requested_amount' => 500,
                'payment_method' => 'cash',
                'payment_date' => '2026-07-29',
                'payment_reference' => 'CASH-V2-SHOW',
                'shop_note' => 'Show page test payment',
            ])
            ->assertRedirect();

        $paymentRequest = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->latest('id')
            ->firstOrFail();

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.payments.show', $paymentRequest))
            ->assertOk()
            ->assertSeeText('Payment Approval')
            ->assertSeeText($shop->name)
            ->assertSeeText('Manual Check Required')
            ->assertSeeText('Oldest pending invoices')
            ->assertSeeText('Shop Supplies');
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
