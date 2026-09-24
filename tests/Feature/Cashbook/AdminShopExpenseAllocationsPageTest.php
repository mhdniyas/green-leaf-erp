<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminShopExpenseAllocationsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private CompanyAccount $companyAccount;

    private DailyLedgerService $ledgerService;

    private ShopPaymentLedgerReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.user_access.main_admin_email' => 'admin-allocations@example.test']);
        $this->admin = User::factory()->create(['email' => 'admin-allocations@example.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['name' => 'Bazaro Supermarket', 'code' => 'BAZARO']);
        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'uuid' => (string) Str::uuid(),
            'slug' => 'av-bazaro-bazaro',
            'code' => $this->shop->code,
            'name' => $this->shop->name,
            'enabled' => true,
            'payment_configuration' => [
                'expense_allocation' => [
                    'enabled' => true,
                    'auto_allocate' => true,
                ],
            ],
        ]);

        $this->companyAccount = CompanyAccount::query()->create([
            'name' => 'SIB Company Account',
            'account_type' => 'bank',
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);

        foreach ([
            ['code' => 'rent_expense', 'name' => 'Rent Expense', 'category' => 'expense'],
            ['code' => 'mess_expense', 'name' => 'Mess Expense', 'category' => 'expense'],
            ['code' => 'salary_expense', 'name' => 'Salary Expense', 'category' => 'expense'],
            ['code' => 'vehicle_expense', 'name' => 'Vehicle Expense', 'category' => 'expense'],
        ] as $entryType) {
            $et = LedgerEntryType::query()->firstOrCreate(['code' => $entryType['code']], $entryType + ['active' => true]);
            ShopLedgerEntrySetting::query()->firstOrCreate([
                'shop_id' => $this->shop->id,
                'entry_type_id' => $et->id,
            ], [
                'enabled' => true,
                'effective_from' => '2026-01-01',
                'include_in_sales' => false,
                'include_in_expense' => true,
            ]);
        }

        $this->ledgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(ShopPaymentLedgerReconciliationService::class);
    }

    public function test_expense_allocations_page_loads_with_exact_summary_and_lists(): void
    {
        // 1. Create a payment of ₹10,000 for 2026-09
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TXN-SEP-001',
            'payment_date' => '2026-09-05',
            'amount' => 10000.00,
            'requested_amount' => 10000.00,
            'approved_amount' => 10000.00,
            'status' => 'approved',
        ]);

        // 2. Create Rent Expense of ₹4,000 and Mess Expense of ₹560
        $rent = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_code' => 'rent_expense',
            'amount' => 4000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $mess = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-03',
            'entry_type_code' => 'mess_expense',
            'amount' => 560.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Allocate Rent (₹4,000)
        ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $rent->id,
            'amount' => 4000.00,
            'status' => 'active',
            'reconciled_by' => $this->admin->id,
        ]);

        // Request page
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.allocations', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.shops.history.allocations');
        $response->assertViewHas('summary');
        $response->assertViewHas('payments');
        $response->assertViewHas('expenses');

        $summary = $response->viewData('summary');
        $this->assertEquals(10000.00, $summary['received']);
        $this->assertEquals(4000.00, $summary['allocated']);
        $this->assertEquals(6000.00, $summary['unallocated']);
        $this->assertEquals(560.00, $summary['open_expenses']);

        $response->assertSee('Bazaro Supermarket');
        $response->assertSee('TXN-SEP-001');
        $response->assertSee('Rent Expense');
        $response->assertSee('Mess Expense');
    }

    public function test_manual_allocation_flow(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_reference' => 'CASH-100',
            'payment_date' => '2026-09-10',
            'amount' => 5000.00,
            'requested_amount' => 5000.00,
            'approved_amount' => 5000.00,
            'status' => 'approved',
        ]);

        $salary = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-08',
            'entry_type_code' => 'salary_expense',
            'amount' => 3000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $vehicle = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-09',
            'entry_type_code' => 'vehicle_expense',
            'amount' => 1000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Submit manual allocation: ₹3,000 to Salary, ₹1,000 to Vehicle
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payment', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
            'redirect_to' => 'allocations',
            'allocations' => [
                ['ledger_transaction_id' => $salary->id, 'amount' => 3000.00],
                ['ledger_transaction_id' => $vehicle->id, 'amount' => 1000.00],
            ],
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.allocations', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $this->assertEquals(2, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'active')->count());
        $this->assertEquals(4000.00, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('status', 'active')->sum('amount'));
    }

    public function test_single_payment_auto_allocate(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'AUTO-PAY-01',
            'payment_date' => '2026-09-05',
            'amount' => 2000.00,
            'requested_amount' => 2000.00,
            'approved_amount' => 2000.00,
            'status' => 'approved',
        ]);

        $rent = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.allocate-expenses', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
            'redirect_to' => 'allocations',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.allocations', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $alloc = ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->first();
        $this->assertNotNull($alloc);
        $this->assertEquals(2000.00, (float) $alloc->amount);
        $this->assertEquals($rent->id, (int) $alloc->shop_ledger_transaction_id);
    }

    public function test_allocate_all_bulk_flow(): void
    {
        $payment1 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'BULK-P1',
            'payment_date' => '2026-09-01',
            'amount' => 3000.00,
            'requested_amount' => 3000.00,
            'approved_amount' => 3000.00,
            'status' => 'approved',
        ]);

        $payment2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'BULK-P2',
            'payment_date' => '2026-09-02',
            'amount' => 2000.00,
            'requested_amount' => 2000.00,
            'approved_amount' => 2000.00,
            'status' => 'approved',
        ]);

        $exp1 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 4000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $exp2 = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_code' => 'mess_expense',
            'amount' => 1500.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Total payment = ₹5,000. Total expenses = ₹5,500. Expected allocation = ₹5,000.
        $submissionUuid = (string) Str::uuid();

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payments.bulk', [
            'shop' => $this->profile->slug,
        ]), [
            'month' => '2026-09',
            'expected_total' => 5000.00,
            'submission_uuid' => $submissionUuid,
            'redirect_to' => 'allocations',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.allocations', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $this->assertEquals(5000.00, (float) ShopPaymentLedgerAllocation::query()->where('shop_id', $this->shop->id)->where('status', 'active')->sum('amount'));
    }

    public function test_undo_reversal_flow(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'UNDO-PAY',
            'payment_date' => '2026-09-05',
            'amount' => 1000.00,
            'requested_amount' => 1000.00,
            'approved_amount' => 1000.00,
            'status' => 'approved',
        ]);

        $exp = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 1000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $alloc = ShopPaymentLedgerAllocation::query()->create([
            'payment_request_id' => $payment->id,
            'shop_id' => $this->shop->id,
            'shop_ledger_transaction_id' => $exp->id,
            'amount' => 1000.00,
            'status' => 'active',
            'reconciled_by' => $this->admin->id,
        ]);

        // Submit removal / undo
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocations.remove', [
            'shop' => $this->profile->slug,
            'allocation' => $alloc->id,
        ]), [
            'month' => '2026-09',
            'redirect_to' => 'allocations',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.history.allocations', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));

        $alloc->refresh();
        $this->assertEquals('reversed', $alloc->status);
        $this->assertNotNull($alloc->reversed_at);
        $this->assertEquals($this->admin->id, $alloc->reversed_by);
    }

    public function test_fifo_ordering_and_multi_expense_settlement(): void
    {
        // 1 Payment of ₹7,000
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'FIFO-PAY',
            'payment_date' => '2026-09-10',
            'amount' => 7000.00,
            'requested_amount' => 7000.00,
            'approved_amount' => 7000.00,
            'status' => 'approved',
        ]);

        // 3 Expenses: Rent ₹4,000 (01 Sep), Mess ₹1,000 (02 Sep), Salary ₹5,000 (03 Sep)
        $rent = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 4000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $mess = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_code' => 'mess_expense',
            'amount' => 1000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $salary = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-03',
            'entry_type_code' => 'salary_expense',
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Auto allocate payment: Should settle Rent (₹4,000 in full), Mess (₹1,000 in full), and Salary (₹2,000 partial)
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.allocate-expenses', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
            'redirect_to' => 'allocations',
        ]);

        $allocRent = ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('shop_ledger_transaction_id', $rent->id)->first();
        $allocMess = ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('shop_ledger_transaction_id', $mess->id)->first();
        $allocSalary = ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->where('shop_ledger_transaction_id', $salary->id)->first();

        $this->assertNotNull($allocRent);
        $this->assertEquals(4000.00, (float) $allocRent->amount);

        $this->assertNotNull($allocMess);
        $this->assertEquals(1000.00, (float) $allocMess->amount);

        $this->assertNotNull($allocSalary);
        $this->assertEquals(2000.00, (float) $allocSalary->amount);

        // Check UI reflects these exact amounts
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.history.allocations', [
            'shop' => $this->profile->slug,
            'month' => '2026-09',
        ]));
        $summary = $response->viewData('summary');
        $this->assertEquals(7000.00, $summary['received']);
        $this->assertEquals(7000.00, $summary['allocated']);
        $this->assertEquals(0.00, $summary['unallocated']);
        $this->assertEquals(3000.00, $summary['open_expenses']); // Salary remaining due: 5000 - 2000 = 3000
    }

    public function test_multiple_payments_settling_single_large_expense(): void
    {
        $largeExpense = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        $p1 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_reference' => 'SPLIT-P1',
            'payment_date' => '2026-09-02',
            'amount' => 6000.00,
            'requested_amount' => 6000.00,
            'approved_amount' => 6000.00,
            'status' => 'approved',
        ]);

        $p2 = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'SPLIT-P2',
            'payment_date' => '2026-09-03',
            'amount' => 4000.00,
            'requested_amount' => 4000.00,
            'approved_amount' => 4000.00,
            'status' => 'approved',
        ]);

        // Allocate P1 manually (6,000)
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payment', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $p1->id,
            'month' => '2026-09',
            'redirect_to' => 'allocations',
            'allocations' => [
                ['ledger_transaction_id' => $largeExpense->id, 'amount' => 6000.00],
            ],
        ]);

        // Allocate P2 auto (4,000)
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.history.payments.allocate-expenses', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $p2->id,
            'month' => '2026-09',
            'redirect_to' => 'allocations',
        ]);

        // Verify total allocated to the single expense is 10,000
        $allocTotal = ShopPaymentLedgerAllocation::query()
            ->where('shop_ledger_transaction_id', $largeExpense->id)
            ->where('status', 'active')
            ->sum('amount');
        $this->assertEquals(10000.00, (float) $allocTotal);
    }

    public function test_cannot_overallocate_beyond_available_balance(): void
    {
        $payment = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $this->shop->id,
            'requested_by' => $this->admin->id,
            'submission_uuid' => (string) Str::uuid(),
            'request_type' => 'shop_cashbook',
            'payment_method' => 'cash',
            'payment_reference' => 'LIMIT-TEST',
            'payment_date' => '2026-09-05',
            'amount' => 1000.00,
            'requested_amount' => 1000.00,
            'approved_amount' => 1000.00,
            'status' => 'approved',
        ]);

        $exp = $this->ledgerService->recordEntry([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-01',
            'entry_type_code' => 'rent_expense',
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ])['transaction'];

        // Attempt to allocate ₹2,000 from a ₹1,000 payment
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.allocate-payment', [
            'shop' => $this->profile->slug,
        ]), [
            'payment_request_id' => $payment->id,
            'month' => '2026-09',
            'redirect_to' => 'allocations',
            'allocations' => [
                ['ledger_transaction_id' => $exp->id, 'amount' => 2000.00],
            ],
        ]);

        $response->assertSessionHasErrors('allocations');
        $this->assertEquals(0, ShopPaymentLedgerAllocation::query()->where('payment_request_id', $payment->id)->count());
    }
}
