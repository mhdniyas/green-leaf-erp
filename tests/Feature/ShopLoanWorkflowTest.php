<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopLoanCategorySetting;
use App\Models\ShopLoanEntry;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Finance\ShopLoanService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopLoanWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_paid_from_loan_category_reduces_loan_balance_without_cash_journal_duplication(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create();
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Staff Advance',
            'is_active' => true,
        ]);
        ShopLoanCategorySetting::query()->create([
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
            'effect' => ShopLoanCategorySetting::EffectUseLoan,
        ]);

        app(ShopLoanService::class)->recordCashMovement(
            $shop,
            'cash_given',
            Carbon::parse('2026-07-29'),
            10000,
            'Cash loan given',
            null,
            (int) $user->id,
        );

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'daily_entry_key' => ShopAccountingEntry::dailyEntryKey($shop->id, '2026-07-29'),
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $user->id,
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'is_loan_entry' => true,
            'amount' => 500,
            'description' => 'Advance paid from loan',
        ]);

        $loanRows = app(ShopLoanService::class)->ledgerRows($shop);
        $receiptSummary = app(OwnedShopAccountingService::class)->receiptSummary($entry);

        $this->assertSame(9500.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertSame(0.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-29')));
        $this->assertSame(0.00, $receiptSummary['cash_debit']);
        $this->assertSame(0.00, $receiptSummary['expected_closing']);
        $this->assertSame(-500.00, (float) $loanRows->firstWhere('category', 'Staff Advance')['signed_amount']);

        $cashFlow = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-29'));
        $loanJournalRows = $cashFlow['journal_rows']->where('source', 'client_shop_loan')->values();

        $this->assertCount(1, $loanJournalRows);
        $this->assertSame('OUT', $loanJournalRows->first()['direction']);
        $this->assertSame(10000.00, $loanJournalRows->first()['amount']);
    }

    public function test_unfunded_paid_from_loan_category_can_go_negative_without_cash_journal(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create();
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Vehicle Expense',
            'is_active' => true,
        ]);
        ShopLoanCategorySetting::query()->create([
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
            'effect' => ShopLoanCategorySetting::EffectUseLoan,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'is_loan_entry' => true,
            'amount' => 500,
            'description' => 'Vehicle paid from loan',
        ]);

        $cashFlow = app(AdminFinancePillarService::class)->cashFlowReport(Carbon::parse('2026-07-29'));

        $this->assertSame(-500.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertCount(0, $cashFlow['journal_rows']->where('source', 'client_shop_loan'));
    }

    public function test_shop_owner_can_mark_any_category_line_under_loan(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create(['shop_id' => $shop->id]);
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Vehicle Expense',
            'is_active' => true,
        ]);

        $entry = app(OwnedShopAccountingService::class)->saveShopOwnerEntry($shop, [
            'business_date' => '2026-07-29',
            'submission_action' => 'submit',
            'lines' => [
                [
                    'shop_accounting_category_id' => $category->id,
                    'amount' => 500,
                    'description' => 'Taken under loan',
                    'is_loan_entry' => true,
                ],
            ],
        ], (int) $user->id);

        $entry->update(['status' => 'approved']);
        $entry->lines()->update(['review_status' => 'approved']);

        $line = $entry->fresh('lines')->lines->first();
        $loanRows = app(ShopLoanService::class)->ledgerRows($shop);
        $receiptSummary = app(OwnedShopAccountingService::class)->receiptSummary($entry->fresh('lines'));

        $this->assertTrue((bool) $line->is_loan_entry);
        $this->assertSame(-500.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertSame(-500.00, (float) $loanRows->firstWhere('category', 'Vehicle Expense')['signed_amount']);
        $this->assertSame(0.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-29')));
        $this->assertSame(0.00, $receiptSummary['cash_debit']);
    }

    public function test_same_category_without_under_loan_stays_in_cashbook(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create(['shop_id' => $shop->id]);
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Vehicle Expense',
            'is_active' => true,
        ]);

        $entry = app(OwnedShopAccountingService::class)->saveShopOwnerEntry($shop, [
            'business_date' => '2026-07-29',
            'submission_action' => 'submit',
            'lines' => [
                [
                    'shop_accounting_category_id' => $category->id,
                    'amount' => 500,
                    'description' => 'Normal shop expense',
                ],
            ],
        ], (int) $user->id);

        $entry->update(['status' => 'approved']);
        $entry->lines()->update(['review_status' => 'approved']);

        $receiptSummary = app(OwnedShopAccountingService::class)->receiptSummary($entry->fresh('lines'));

        $this->assertSame(0.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertSame(-500.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-29')));
        $this->assertSame(500.00, $receiptSummary['cash_debit']);
    }

    public function test_income_category_cannot_be_marked_under_loan(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create();
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Cash From Super Market',
            'is_active' => true,
        ]);

        $entry = app(OwnedShopAccountingService::class)->saveShopOwnerEntry($shop, [
            'business_date' => '2026-07-29',
            'submission_action' => 'submit',
            'lines' => [
                [
                    'shop_accounting_category_id' => $category->id,
                    'amount' => 2000,
                    'description' => 'Taken from shop cash',
                    'is_loan_entry' => true,
                ],
            ],
        ], (int) $user->id);
        $entry->update(['status' => 'approved']);
        $entry->lines()->update(['review_status' => 'approved']);

        $receiptSummary = app(OwnedShopAccountingService::class)->receiptSummary($entry->fresh('lines'));

        $this->assertFalse((bool) $entry->fresh('lines')->lines->first()->is_loan_entry);
        $this->assertSame(0.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertSame(2000.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-29')));
        $this->assertSame(2000.00, $receiptSummary['cash_credit']);
    }

    public function test_admin_can_toggle_loan_category_cashbook_offset(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Cash From Super Market',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Vehicle Expense',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.loans.categories.update', ['shop' => $shop]), [
                'loan_effects' => [
                    $category->id => ShopLoanCategorySetting::EffectUseLoan,
                ],
                'loan_default_daily_amounts' => [
                    $category->id => 2000,
                ],
            ])
            ->assertRedirect(route('admin.accounting.loans', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shop_loan_category_settings', [
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.loans.categories.update', ['shop' => $shop]), [
                'loan_effects' => [
                    $category->id => ShopLoanCategorySetting::EffectUseLoan,
                    $expenseCategory->id => ShopLoanCategorySetting::EffectUseLoan,
                ],
                'loan_default_daily_amounts' => [
                    $category->id => 2000,
                    $expenseCategory->id => 500,
                ],
                'loan_cashbook_offsets' => [
                    $category->id => '1',
                    $expenseCategory->id => '1',
                ],
            ])
            ->assertRedirect(route('admin.accounting.loans', ['shop' => $shop->code]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shop_loan_category_settings', [
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('shop_loan_category_settings', [
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $expenseCategory->id,
            'default_daily_amount' => '500.00',
            'cashbook_offset_enabled' => false,
        ]);
    }

    public function test_cashbook_receipt_shows_company_payable_without_loan_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $cashSalesCategory = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'income',
            'cash_effect' => true,
            'name' => 'Cash Sales',
            'is_active' => true,
        ]);

        app(ShopLoanService::class)->recordCashMovement(
            $shop,
            'cash_given',
            Carbon::parse('2026-07-29'),
            850,
            'Cash loan given',
            null,
            (int) $shopOwner->id,
        );

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'daily_entry_key' => ShopAccountingEntry::dailyEntryKey($shop->id, '2026-07-29'),
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 52110,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);
        $entry->lines()->create([
            'shop_accounting_category_id' => $cashSalesCategory->id,
            'type' => 'income',
            'cash_effect' => true,
            'amount' => 52110,
            'description' => 'Cashbook balance',
            'review_status' => 'approved',
        ]);

        $this->assertSame(52110.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-29')));
        $this->assertSame(850.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertSame(52110.00, app(OwnedShopAccountingService::class)->receiptSummaryForDate($shop, Carbon::parse('2026-07-29'))['to_be_paid_to_company']);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'date' => '2026-07-29',
            ]))
            ->assertOk()
            ->assertSeeText('Closing')
            ->assertDontSeeText('Loan Balance')
            ->assertSeeText('Balance')
            ->assertSeeText('Rs. 52,110.00')
            ->assertDontSeeText('Rs. 850.00')
            ->assertDontSeeText('Rs. 51,260.00');
    }

    public function test_loan_category_default_daily_amount_prefills_shop_owner_cashbook(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Vehicle Expense',
            'is_active' => true,
        ]);

        app(ShopLoanService::class)->syncCategorySettings($shop, [
            $category->id => ShopLoanCategorySetting::EffectUseLoan,
        ], [
            $category->id => 500,
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'date' => '2026-07-29',
            ]))
            ->assertOk()
            ->assertSee('"shop_accounting_category_id":"'.$category->id.'"', false)
            ->assertSee('"amount":"500.00"', false)
            ->assertSee('"is_loan_entry":"1"', false)
            ->assertSee('"loan_default_daily_amount":500', false)
            ->assertSee('Auto paid from loan');

        $this->assertDatabaseHas('shop_loan_category_settings', [
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
            'default_daily_amount' => '500.00',
        ]);
    }

    public function test_admin_can_update_approved_paid_from_loan_line_and_shop_owner_sees_revised_data(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Cash From Super Market',
            'is_active' => true,
        ]);
        ShopLoanCategorySetting::query()->create([
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
            'effect' => ShopLoanCategorySetting::EffectUseLoan,
        ]);
        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'daily_entry_key' => ShopAccountingEntry::dailyEntryKey($shop->id, '2026-07-29'),
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $line = $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'is_loan_entry' => true,
            'amount' => 2000,
            'description' => 'Auto paid from loan',
            'review_status' => 'approved',
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.lines.update', [
                'shop' => $shop,
                'entry' => $entry,
                'line' => $line,
            ]), [
                'shop_accounting_category_id' => $category->id,
                'amount' => 2500,
                'description' => 'Corrected amount from bill',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'approved',
                'date' => '2026-07-29',
            ]))
            ->assertSessionHas('success');

        $line->refresh();

        $this->assertSame('2500.00', $line->amount);
        $this->assertSame('Corrected amount from bill', $line->description);
        $this->assertSame(-2500.00, app(ShopLoanService::class)->approvedBalance($shop));
        $this->assertSame(0.00, app(OwnedShopAccountingService::class)->closingBalanceForDate($shop, Carbon::parse('2026-07-29')));

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'loan',
                'date' => '2026-07-29',
            ]))
            ->assertOk()
            ->assertSee('Cash From Super Market')
            ->assertSee('Corrected amount from bill')
            ->assertSee('Rs. 2,500.00');
    }

    public function test_admin_can_clear_cashbook_without_deleting_invoices_payment_requests_or_loan_cash_movements(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $category = ShopAccountingCategory::query()->create([
            'shop_id' => $shop->id,
            'type' => 'expense',
            'cash_effect' => true,
            'name' => 'Staff Advance',
            'is_active' => true,
        ]);
        ShopLoanCategorySetting::query()->create([
            'shop_id' => $shop->id,
            'shop_accounting_category_id' => $category->id,
            'effect' => ShopLoanCategorySetting::EffectUseLoan,
            'default_daily_amount' => 500,
        ]);

        app(ShopLoanService::class)->recordCashMovement(
            $shop,
            'cash_given',
            Carbon::parse('2026-07-29'),
            10000,
            'Cash loan given',
            null,
            (int) $admin->id,
        );

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'entry_type' => ShopAccountingEntry::TypeDaily,
            'daily_entry_key' => ShopAccountingEntry::dailyEntryKey($shop->id, '2026-07-29'),
            'status' => 'approved',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $shopOwner->id,
            'submitted_by' => $shopOwner->id,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $line = $entry->lines()->create([
            'shop_accounting_category_id' => $category->id,
            'type' => 'expense',
            'cash_effect' => true,
            'is_loan_entry' => true,
            'amount' => 500,
            'description' => 'Advance paid from loan',
            'review_status' => 'approved',
        ]);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-29',
            'created_by' => $shopOwner->id,
        ]);
        $invoice = ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260729-'.$shop->code,
            'business_date' => '2026-07-29',
            'status' => 'generated',
            'delivery_status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'shortage_total' => 0,
            'excess_total' => 0,
            'discount_total' => 0,
            'final_total' => 1000,
            'paid_amount' => 0,
            'balance_amount' => 1000,
            'generated_by' => $admin->id,
        ]);
        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $shop->id,
            'requested_by' => $shopOwner->id,
            'request_type' => 'custom',
            'requested_amount' => 250,
            'status' => 'pending',
        ]);

        $this->assertSame(9500.00, app(ShopLoanService::class)->approvedBalance($shop));

        $this
            ->actingAs($admin)
            ->delete(route('admin.accounting.owned-shops.entries.clear', [
                'shop' => $shop,
                'entry' => $entry,
            ]), [
                'confirmation' => 'CLEAR CASHBOOK',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', [
                'shop' => $shop->code,
                'tab' => 'cashbook',
                'approval_tab' => 'pending',
                'date' => '2026-07-29',
            ]))
            ->assertSessionHas('success');

        $this->assertModelMissing($entry);
        $this->assertModelMissing($line);
        $this->assertModelExists($invoice);
        $this->assertModelExists($paymentRequest);
        $this->assertDatabaseHas('shop_loan_entries', [
            'shop_id' => $shop->id,
            'type' => ShopLoanEntry::TypeCashGiven,
            'amount' => '10000.00',
        ]);
        $this->assertSame(10000.00, app(ShopLoanService::class)->approvedBalance($shop));

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', [
                'tab' => 'cashbook',
                'date' => '2026-07-29',
            ]))
            ->assertOk()
            ->assertSee('Add Credit / Debit')
            ->assertSee('Auto paid from loan')
            ->assertSee('"loan_default_daily_amount":500', false)
            ->assertDontSee('Advance paid from loan', false);
    }
}
