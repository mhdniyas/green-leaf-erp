<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookShopDayWorkspaceRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected LedgerClient $client;

    protected Shop $casio;

    protected ShopLedgerProfile $casioProfile;

    protected CompanyAccount $kotakBank;

    protected CompanyAccount $cashBox;

    protected LedgerEntryType $cashSalesType;

    protected LedgerEntryType $paytmType;

    protected LedgerEntryType $cardType;

    protected LedgerEntryType $rentExpenseType;

    protected DailyLedgerService $dailyLedgerService;

    protected CompanyPaymentReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin.redesign@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create([
            'email' => 'staff.redesign@example.com',
        ]);

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1100'], ['name' => 'Accounts Receivable', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4100'], ['name' => 'Sales Revenue', 'type' => 'revenue', 'is_active' => true]);

        $this->client = LedgerClient::create([
            'name' => 'Casio Client',
            'slug' => 'casio-client',
            'enabled' => true,
        ]);

        $this->casio = Shop::create([
            'code' => 'AV_CASIO',
            'name' => 'Casio',
            'client_id' => $this->client->id,
            'status' => 'active',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);

        $this->casioProfile = ShopLedgerProfile::create([
            'client_id' => $this->client->id,
            'shop_id' => $this->casio->id,
            'code' => 'AV_CASIO',
            'slug' => 'av-casio-casio',
            'name' => 'Casio',
            'profile_template' => 'owned_standard',
            'enabled' => true,
        ]);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank Main',
            'account_type' => 'bank',
            'current_balance' => 0.00,
            'enabled' => true,
        ]);

        $this->cashBox = CompanyAccount::create([
            'name' => 'Company Cash Box',
            'account_type' => 'cash',
            'current_balance' => 0.00,
            'enabled' => true,
        ]);

        $this->paytmType = LedgerEntryType::firstOrCreate(
            ['code' => 'paytm_sales'],
            ['name' => 'Paytm', 'category' => 'income', 'display_order' => 10, 'active' => true, 'is_system' => true]
        );

        $this->cardType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_sales'],
            ['name' => 'Card', 'category' => 'income', 'display_order' => 11, 'active' => true, 'is_system' => true]
        );

        $this->cashSalesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sales'],
            ['name' => 'Cash Sales', 'category' => 'income', 'display_order' => 1, 'active' => true, 'is_system' => true]
        );

        $this->rentExpenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'rent_expense'],
            ['name' => 'Rent', 'category' => 'expense', 'display_order' => 20, 'active' => true, 'is_system' => true]
        );

        LedgerEntryType::firstOrCreate(
            ['code' => 'shop_paid_company'],
            ['name' => 'Shop Paid Company', 'category' => 'settlement', 'display_order' => 50, 'active' => true, 'is_system' => true]
        );

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->kotakBank->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cardType->id,
            'company_account_id' => $this->kotakBank->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->cashBox->id,
            'enabled' => true,
            'effective_from' => '2026-01-01',
        ]);

        $this->dailyLedgerService = app(DailyLedgerService::class);
        $this->reconciliationService = app(CompanyPaymentReconciliationService::class);
    }

    public function test_1_and_2_url_without_date_and_with_date_query_load_correct_business_date(): void
    {
        // 1. URL without date
        $responseNoDate = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
        ]));
        $responseNoDate->assertOk()
            ->assertViewIs('admin.cashbook.shops.show');

        // 2. URL with specific date query
        $date = '2026-08-22';
        $responseWithDate = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));
        $responseWithDate->assertOk()
            ->assertSee('22 Aug 2026')
            ->assertSee('Casio')
            ->assertSee('Shop Position');
    }

    public function test_3_4_5_6_calendar_retains_slug_navigates_months_and_marks_activity(): void
    {
        $date = '2026-08-22';

        // Record activity on 2026-08-22
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
            'month' => '2026-08',
        ]));

        $response->assertOk()
            ->assertSee('August 2026')
            ->assertSee(route('admin.cashbook.shop.show', ['shop' => $this->casioProfile->slug, 'month' => '2026-07', 'date' => $date]))
            ->assertSee(route('admin.cashbook.shop.show', ['shop' => $this->casioProfile->slug, 'month' => '2026-09', 'date' => $date]))
            ->assertSee(route('admin.cashbook.shop.show', ['shop' => $this->casioProfile->slug, 'date' => '2026-08-22']));
    }

    public function test_7_8_9_10_shop_position_and_settlement_summary_reconcile_and_emphasize_still_to_settle(): void
    {
        $date = '2026-08-22';

        // Collection 1: Paytm ₹10,000 (unapproved)
        $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Collection 2: Cash ₹15,000 (approved, unverified)
        $txCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 15000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCash['transaction'], $this->admin->id);

        // Expense: Rent ₹2,000 (from sales)
        $txRent = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->rentExpenseType->code,
            'amount' => 2000.00,
            'funding_source' => 'sales',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txRent['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));

        $response->assertOk()
            ->assertSee('Shop Position')
            ->assertSee('Net Company Receivable')
            ->assertSee('₹23,000.00') // Gross ₹25,000 - ₹2,000 deduction = ₹23,000
            ->assertSee('Still To Settle')
            ->assertSee('Settlement Summary')
            ->assertSee('Less: Used From Collection')
            ->assertSee('-₹2,000.00')
            ->assertSee('Breakdown of Still to Settle')
            ->assertSee('₹13,000.00'); // Cash with shop (15000 - 2000 rent)
    }

    public function test_11_12_13_14_approval_and_verification_controls_and_explanations(): void
    {
        $date = '2026-08-22';

        // 1. Unapproved entry -> can_accept
        $tx1 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 8000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // 2. Approved entry -> can_verify
        $tx2 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 12000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx2['transaction'], $this->admin->id);

        // 3. Reconciled entry -> is_received
        $tx3 = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 5000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($tx3['transaction'], $this->admin->id);
        $stmt3 = CompanyAccountStatementEntry::where('source_id', $tx3['transaction']->id)->firstOrFail();
        $this->reconciliationService->verifyPendingShopCollection($stmt3, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));

        $response->assertOk()
            ->assertSee('Review Collections (1)')
            ->assertSee('Approve for receipt tracking')
            ->assertSee('Does NOT mark money as received', false)
            ->assertSee('Confirm Company Receipt (1)')
            ->assertSee('Confirm received')
            ->assertSee('Received Collections (1)')
            ->assertSee('RECEIVED');
    }

    public function test_15_adjustment_details_modal_and_add_drawer(): void
    {
        $date = '2026-08-22';

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));

        $response->assertOk()
            ->assertSee('Adjustments')
            ->assertSee('View details')
            ->assertSee('Add adjustment')
            ->assertSee('Settlement Adjustments Details');
    }

    public function test_16_17_18_existing_post_routes_and_permissions_are_preserved(): void
    {
        $date = '2026-08-22';

        $tx = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 10000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);

        // Unauthorized user cannot accept
        $this->actingAs($this->staff)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->casioProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx['transaction']->id],
        ])->assertForbidden();

        // Admin can accept
        $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.accept-selected', [
            'shop' => $this->casioProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$tx['transaction']->id],
        ])->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));

        $this->assertSame('approved', $tx['transaction']->fresh()->status);
    }

    public function test_account_mapping_defect_investigation_and_safety_enforcement(): void
    {
        $date = '2026-08-22';

        // Set up CP entry type
        $cpType = LedgerEntryType::firstOrCreate(
            ['code' => 'income_cp'],
            ['name' => 'CP', 'category' => 'income', 'display_order' => 12, 'active' => true, 'is_system' => true]
        );

        // Explicitly remove mappings for CP, Paytm, and Cash so ONLY Card is configured
        ShopLedgerEntrySetting::where('shop_id', $this->casio->id)
            ->whereIn('entry_type_id', [$cpType->id, $this->paytmType->id, $this->cashSalesType->id])
            ->delete();

        // 1. Only Card is configured to Kotak Bank Main
        $cardSetting = ShopLedgerEntrySetting::where('shop_id', $this->casio->id)
            ->where('entry_type_id', $this->cardType->id)
            ->first();
        $this->assertNotNull($cardSetting);
        $this->assertSame($this->kotakBank->id, $cardSetting->company_account_id);

        // Record 4 approved transactions: Card, CP, Paytm, Cash
        $txCard = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->cardType->code,
            'amount' => 19642.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCard['transaction'], $this->admin->id);

        $txCp = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $cpType->code,
            'amount' => 2810.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCp['transaction'], $this->admin->id);

        $txPaytm = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->paytmType->code,
            'amount' => 26119.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txPaytm['transaction'], $this->admin->id);

        $txCash = $this->dailyLedgerService->recordEntry([
            'shop_id' => $this->casio->id,
            'business_date' => $date,
            'entry_type_code' => $this->cashSalesType->code,
            'amount' => 8000.00,
            'funding_source' => 'none',
            'entered_by' => $this->admin->id,
        ]);
        $this->dailyLedgerService->approveEntry($txCash['transaction'], $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));

        $response->assertOk();

        // 2 & 3. Card displays real account name and has enabled checkbox
        $response->assertSee('Kotak Bank Main');
        $response->assertDontSee('Configured Company Account');

        // 4 & 5. CP and Paytm display Destination account not configured
        $response->assertSee('Destination account not configured');

        // 6. Cash displays Currently with shop and Setup required
        $response->assertSee('Currently with shop');
        $response->assertSee('Setup required');

        // 7. Verification form contains enabled checkbox for Card and disabled checkboxes for CP, Paytm, Cash
        $this->assertTrue($txCard['transaction']->fresh()->status === 'approved');
        $response->assertSee('value="'.$txCard['transaction']->id.'"', false);
        $response->assertSee('Kotak Bank Main');
        $response->assertSee('Setup required');
        $response->assertSee('Currently with shop');

        // 9. Batch verification rejects manually submitted unconfigured ID (Paytm)
        $verifyUnconfigured = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->casioProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txPaytm['transaction']->id],
        ]);

        $verifyUnconfigured->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));
        $verifyUnconfigured->assertSessionHas('error');
        $this->assertStringContainsString('Configure a destination company account for', session('error'));

        // 10 & 11. No statement or account movement was created for rejected unconfigured entry
        $this->assertDatabaseMissing('cashbook_company_account_statement_entries', [
            'source_id' => $txPaytm['transaction']->id,
        ]);
        $this->assertEquals(0.00, $this->kotakBank->fresh()->current_balance);

        // 12. Mismatched statement is blocked
        // Create an artificial mismatched statement for Card pointing to a different account (e.g. cashBox)
        $cardStmt = CompanyAccountStatementEntry::where('source_id', $txCard['transaction']->id)->first();
        if ($cardStmt) {
            $cardStmt->update(['company_account_id' => $this->cashBox->id]);

            $mismatchVerify = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
                'shop' => $this->casioProfile->slug,
            ]), [
                'business_date' => $date,
                'transaction_ids' => [$txCard['transaction']->id],
            ]);

            $mismatchVerify->assertRedirect();
            $mismatchVerify->assertSessionHas('error');
            $this->assertStringContainsString('Account mismatch — review required', session('error'));

            // Restore correct account mapping
            $cardStmt->update(['company_account_id' => $this->kotakBank->id]);
        }

        // 13. Valid Card verification works exactly once and does not duplicate
        $validVerify = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->casioProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txCard['transaction']->id],
        ]);

        $validVerify->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $this->casioProfile->slug,
            'date' => $date,
        ]));
        $validVerify->assertSessionHas('success');

        $this->assertSame('reconciled', $cardStmt->fresh()->status);
        $this->assertEquals(19642.00, $this->kotakBank->fresh()->current_balance);
        $this->assertSame(1, CompanyAccountStatementEntry::where('source_id', $txCard['transaction']->id)->count());

        // Repeated verification fails
        $repeatVerify = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.day.verify-selected', [
            'shop' => $this->casioProfile->slug,
        ]), [
            'business_date' => $date,
            'transaction_ids' => [$txCard['transaction']->id],
        ]);
        $repeatVerify->assertSessionHas('error');
        $this->assertStringContainsString('already been verified', session('error'));
        $this->assertSame(1, CompanyAccountStatementEntry::where('source_id', $txCard['transaction']->id)->count());
    }
}
