<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CompanyAccountingCategory;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CompanyPayableService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CompanyAccountingCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceV2CompanyPayableTest extends TestCase
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

    public function test_company_funded_expense_creates_pending_payable_and_admin_can_approve_and_settle_directly(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$shop, $category] = $this->createShopFixture();

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-31',
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
            'is_loan_entry' => false,
            'funding_source' => 'company',
            'amount' => 8000,
            'description' => 'Refrigerator Repair',
        ]);

        $service = app(CompanyPayableService::class);
        $service->markCompanyPayableOnLine($line);
        $service->notifyAdmins($line->fresh(['entry.shop', 'category']));

        $line->refresh();
        $this->assertSame('pending', $line->company_payable_status);
        $this->assertSame('8000.00', $line->company_payable_amount);

        Notification::assertSentTo($admin, \App\Notifications\CompanyExpenseRequestSubmitted::class);

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.company-payables.show', $line))
            ->assertOk()
            ->assertSeeText('Repairs');

        $this
            ->actingAs($admin)
            ->patch(route('admin.finance-v2.company-payables.approve', $line), [
                'approved_amount' => 8000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $line->refresh();
        $this->assertSame('approved', $line->company_payable_status);
        $this->assertTrue(JournalEntry::query()
            ->where('source_type', ShopAccountingEntryLine::class)
            ->where('source_id', $line->id)
            ->where('source_event', 'company-payable-approved')
            ->exists());

        $this
            ->actingAs($admin)
            ->post(route('admin.finance-v2.company-payables.settle-direct', $line), [
                'amount' => 5000,
                'payment_mode' => 'cash',
                'payee' => 'Technician',
                'reference' => 'CP-001',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $line->refresh();
        $this->assertSame('5000.00', $line->company_settled_amount);
        $this->assertSame('partially_settled', $line->company_settlement_status);
        $this->assertSame(3000.0, $line->remainingCompanyPayableAmount());

        $this
            ->actingAs($admin)
            ->post(route('admin.finance-v2.company-payables.settle-direct', $line), [
                'amount' => 3000,
                'payment_mode' => 'bank',
                'payee' => 'Technician',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $line->refresh();
        $this->assertSame('settled', $line->company_settlement_status);
        $this->assertSame(0.0, $line->remainingCompanyPayableAmount());
    }

    public function test_rejected_payable_cannot_be_settled_and_duplicate_approve_is_blocked(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$shop, $category] = $this->createShopFixture();

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-31',
            'entry_type' => 'daily',
            'status' => 'submitted',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $admin->id,
        ]);

        $line = ShopAccountingEntryLine::query()->create([
            'shop_accounting_entry_id' => $entry->id,
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'funding_source' => 'company',
            'company_payable_status' => 'pending',
            'company_payable_amount' => 1000,
            'company_settled_amount' => 0,
            'amount' => 1000,
        ]);

        $service = app(CompanyPayableService::class);
        $service->approve($line, $admin->id, 1000);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->approve($line->fresh(), $admin->id, 1000);
    }

    public function test_direct_payment_against_purchase_invoice_updates_outstanding(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $supplier = Supplier::factory()->create();
        $invoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-DIRECT-1',
            'amount' => 1000,
            'paid_amount' => 200,
            'discount_amount' => 0,
            'payment_status' => 'partial',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.finance-v2.direct-payments.store', $invoice), [
                'amount' => 300,
                'payment_date' => '2026-07-31',
                'payment_method' => 'cash',
                'reference' => 'DIR-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('500.00', $invoice->paid_amount);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_finance_v2_dashboard_lists_all_clients(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $clientA = Client::query()->create(['code' => 'CLIENT_A', 'name' => 'Client Alpha', 'status' => 'active']);
        $clientB = Client::query()->create(['code' => 'CLIENT_B', 'name' => 'Client Beta', 'status' => 'active']);
        Shop::factory()->create(['client_id' => $clientA->id, 'accounting_enabled' => true]);
        Shop::factory()->create(['client_id' => $clientB->id, 'accounting_enabled' => true]);

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.dashboard', ['date' => '2026-07-31']))
            ->assertOk()
            ->assertSeeText('Client Alpha')
            ->assertSeeText('Client Beta')
            ->assertSeeText('Net Client Position');
    }

    public function test_shop_owner_company_funded_expense_appears_on_company_payables_index(): void
    {
        Notification::fake();
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-31 12:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        [$shop, $category] = $this->createShopFixture();

        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');

        $this
            ->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-07-31',
                'submission_action' => 'submit',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $category->id,
                        'amount' => 4500,
                        'description' => 'Fridge repair by company',
                        'funding_source' => 'company',
                        'is_loan_entry' => 0,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $line = ShopAccountingEntryLine::query()
            ->where('funding_source', 'company')
            ->where('shop_accounting_category_id', $category->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame('expense', $line->type);
        $this->assertSame('company', $line->funding_source);
        $this->assertSame('pending', $line->company_payable_status);
        $this->assertSame('4500.00', $line->company_payable_amount);

        $this
            ->actingAs($admin)
            ->get(route('admin.finance-v2.company-payables.index', ['date' => '2026-07-31']))
            ->assertOk()
            ->assertSeeText($shop->name)
            ->assertSeeText('Repairs')
            ->assertSeeText('Rs. 4,500.00');

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'loan']))
            ->assertOk()
            ->assertSeeText('Others')
            ->assertSeeText('Petty movements')
            ->assertDontSeeText('Company-funded expenses');

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'loan', 'others' => 'company']))
            ->assertOk()
            ->assertSeeText('Others')
            ->assertSeeText('Company-funded expenses')
            ->assertSeeText('Repairs')
            ->assertSeeText('Fridge repair by company')
            ->assertSeeText('Pending')
            ->assertSeeText('Rs. 4,500.00');
    }

    /**
     * @return array{0: Shop, 1: ShopAccountingCategory}
     */
    private function createShopFixture(): array
    {
        $client = Client::query()->create([
            'code' => 'TEST_CLIENT',
            'name' => 'Test Client',
            'status' => 'active',
        ]);
        $shop = Shop::factory()->create([
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Repairs',
            'type' => 'expense',
            'cash_effect' => true,
            'purpose' => 'general',
            'is_active' => true,
        ]);

        return [$shop, $category];
    }
}
