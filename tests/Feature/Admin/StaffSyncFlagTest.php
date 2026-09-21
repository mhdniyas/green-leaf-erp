<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\StaffSyncFlag;
use App\Models\User;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\StaffSyncFlagScannerService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSyncFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    private Employee $employee;

    private EmployeeAdvanceService $advanceService;

    private StaffSyncFlagScannerService $scannerService;

    private StaffPaymentCashbookProjectionService $projectionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopOwner = User::factory()->create();
        $this->shopOwner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'City Center Shop',
            'code' => 'CITY_CENTER',
            'warehouse_tag' => 'CC',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopOwner->ownedShopAssignments()->create([
            'shop_id' => $this->shop->id,
            'is_active' => true,
        ]);

        $this->admin->ownedShopAssignments()->create([
            'shop_id' => $this->shop->id,
            'is_active' => true,
        ]);

        $category = EmployeeCategory::query()->create([
            'name' => 'Shop Sales Staff',
            'code' => 'SHOP_SALES_STAFF',
            'staff_area' => 'shop',
            'is_active' => true,
            'monthly_paid_leave_limit' => 2,
        ]);

        $this->employee = Employee::query()->create([
            'name' => 'Afeez',
            'employee_code' => 'EMP-AFEEZ',
            'employee_category_id' => $category->id,
            'phone' => '9876543210',
            'id_type' => 'aadhaar',
            'id_number' => '123456789012',
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'default_shop_id' => $this->shop->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 20000,
            'joined_on' => '2026-01-01',
        ]);

        $this->advanceService = app(EmployeeAdvanceService::class);
        $this->scannerService = app(StaffSyncFlagScannerService::class);
        $this->projectionService = app(StaffPaymentCashbookProjectionService::class);
    }

    public function test_clean_staff_advance_creation_generates_no_flags(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Afeez 3000 advance',
        );

        $this->assertDatabaseHas('shop_staff_payments', [
            'id' => $payment->id,
            'amount' => 3000.0,
            'payment_type' => 'advance',
        ]);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => $payment->id,
            'amount' => 3000.0,
        ]);

        $openFlags = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->get();
        $this->assertCount(0, $openFlags);
    }

    public function test_tampered_cashbook_amount_triggers_cashbook_amount_mismatch_flag(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        // Tamper cashbook transaction to Rs. 5,000 directly
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 5000.0]);

        $result = $this->scannerService->scanAll();

        $this->assertEquals(1, $result['open_flags']);

        $flag = StaffSyncFlag::query()->open()->first();
        $this->assertNotNull($flag);
        $this->assertEquals(StaffSyncFlag::CODE_CASHBOOK_AMOUNT_MISMATCH, $flag->flag_code);
        $this->assertEquals($payment->id, $flag->source_id);
        $this->assertEquals($this->shop->id, $flag->shop_id);
        $this->assertEquals($this->employee->id, $flag->employee_id);
    }

    public function test_admin_can_fix_sync_to_repair_cashbook_and_resolve_flag(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        // Tamper cashbook to 5,000
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 5000.0]);

        $this->scannerService->scanAll();
        $flag = StaffSyncFlag::query()->open()->first();
        $this->assertNotNull($flag);

        // Admin invokes Fix Sync route
        $response = $this->actingAs($this->admin)->post(route('admin.staff.sync-flags.fix-sync', $flag));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Cashbook is now reconciled back to 3,000
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(3000.0, (float) $tx->amount);

        // Flag is marked resolved
        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);
    }

    public function test_admin_can_save_and_sync_all_via_review_modal(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        // Mismatch
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 1500000.0]);

        $this->scannerService->scanAll();
        $flag = StaffSyncFlag::query()->open()->first();

        // Admin updates master record to 4000 and syncs all
        $response = $this->actingAs($this->admin)->post(route('admin.staff.sync-flags.save-and-sync-all', $flag), [
            'amount' => 4000.0,
            'paid_on' => now()->startOfDay()->toDateString(),
            'payment_type' => 'advance',
            'fund_source' => 'sales',
            'notes' => 'Updated master advance to 4000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals(4000.0, (float) $payment->amount);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(4000.0, (float) $tx->amount);

        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);
    }

    public function test_missing_cashbook_entry_creates_missing_cashbook_flag(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            2500.0,
            'salary',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Salary payment',
        );

        // Delete the linked cashbook transaction
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->delete();

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('flag_code', StaffSyncFlag::CODE_MISSING_CASHBOOK)->first();
        $this->assertNotNull($flag);
        $this->assertEquals($payment->id, $flag->source_id);

        // Fix sync restores the cashbook entry
        $this->actingAs($this->admin)->post(route('admin.staff.sync-flags.fix-sync', $flag));

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => $payment->id,
            'amount' => 2500.0,
        ]);

        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);
    }

    public function test_orphan_cashbook_is_flagged_and_repaired(): void
    {
        $entryType = LedgerEntryType::query()->first();

        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType?->id,
            'direction' => 'out',
            'business_date' => now()->startOfDay(),
            'amount' => 3500.0,
            'funding_source' => 'sales',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 999999, // non-existent payment
            'notes' => 'Old orphan entry',
        ]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('flag_code', StaffSyncFlag::CODE_ORPHAN_CASHBOOK)->first();
        $this->assertNotNull($flag);
        $this->assertEquals($orphanTx->id, $flag->source_id);

        // Fix sync deletes the orphan transaction
        $this->actingAs($this->admin)->post(route('admin.staff.sync-flags.fix-sync', $flag));

        $this->assertDatabaseMissing('shop_ledger_transactions', [
            'id' => $orphanTx->id,
        ]);

        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);
    }

    public function test_history_views_display_flag_icon_when_open_and_hides_when_resolved(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance test',
        );

        // Tamper cashbook to 5,000
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 5000.0]);

        $this->scannerService->scanAll();

        // 1. Check Admin HR Staff Detail View
        $adminViewResponse = $this->actingAs($this->admin)->get(route('admin.staff.show', $this->employee));
        $adminViewResponse->assertOk();
        $adminViewResponse->assertSee('🚩');
        $adminViewResponse->assertSee('Cashbook Amount Mismatch');

        // 2. Check Shop Owner Staff History View (Errors visible only to Admin)
        $shopOwnerResponse = $this->actingAs($this->shopOwner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shop->code,
            'tab' => 'history',
        ]));
        $shopOwnerResponse->assertOk();
        $shopOwnerResponse->assertDontSee('🚩');
        $shopOwnerResponse->assertDontSee('Sync Issue');

        // 3. Resolve the flag
        $flag = StaffSyncFlag::query()->open()->first();
        $this->actingAs($this->admin)->post(route('admin.staff.sync-flags.fix-sync', $flag));

        // 4. Verify flag is now absent from views
        $adminViewResponseAfter = $this->actingAs($this->admin)->get(route('admin.staff.show', $this->employee));
        $this->assertCount(0, StaffSyncFlag::query()->open()->get());
        $adminViewResponseAfter->assertOk();
        $adminViewResponseAfter->assertDontSee('🚩');

        $shopOwnerResponseAfter = $this->actingAs($this->shopOwner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shop->code,
            'tab' => 'history',
        ]));
        $shopOwnerResponseAfter->assertOk();
        $shopOwnerResponseAfter->assertDontSee('Sync Issue');
    }

    public function test_funding_source_aliases_do_not_create_false_positive_flags(): void
    {
        // 1. sales_income on master vs sales on cashbook
        $payment1 = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            1000.0,
            'advance',
            'sales_income',
            now()->startOfDay(),
            $this->admin,
            'sales_income test',
        );

        // 2. petty_cash on master vs petty on cashbook
        $payment2 = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            1000.0,
            'advance',
            'petty_cash',
            now()->startOfDay(),
            $this->admin,
            'petty_cash test',
        );

        $this->scannerService->scanAll();

        $fundSourceFlags = StaffSyncFlag::query()
            ->open()
            ->where('flag_code', StaffSyncFlag::CODE_CASHBOOK_FUND_SOURCE_MISMATCH)
            ->whereIn('source_id', [$payment1->id, $payment2->id])
            ->get();

        $this->assertCount(0, $fundSourceFlags);
    }

    public function test_category_mismatch_is_flagged_and_repaired_to_staff_advance(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            2000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();
        $advanceType = LedgerEntryType::query()->where('code', 'staff_advance')->first();
        $this->assertNotNull($salaryType);
        $this->assertNotNull($advanceType);

        // Tamper transaction entry type to 'salary'
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['entry_type_id' => $salaryType->id]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()
            ->open()
            ->where('source_id', $payment->id)
            ->where('flag_code', StaffSyncFlag::CODE_CASHBOOK_CATEGORY_MISMATCH)
            ->first();

        $this->assertNotNull($flag);
        $this->assertEquals('Cashbook Category Mismatch', $flag->title);

        // Fix sync updates entry type to staff_advance without duplicating transaction
        $response = $this->actingAs($this->admin)->post(route('admin.staff.sync-flags.fix-sync', $flag));
        $response->assertRedirect();

        $txs = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $txs);
        $this->assertEquals((int) $advanceType->id, (int) $txs->first()->entry_type_id);

        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);
    }

    public function test_manual_cashbook_salary_is_never_flagged_as_orphan(): void
    {
        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();

        // Manual entry: reference_type and reference_id are null
        $manualTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
            'direction' => 'expense',
            'business_date' => now()->startOfDay(),
            'amount' => 5000.0,
            'funding_source' => 'sales',
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'Manual salary entry entered by accountant',
        ]);

        $this->scannerService->scanAll();

        $orphanFlag = StaffSyncFlag::query()
            ->open()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $manualTx->id)
            ->first();

        $this->assertNull($orphanFlag);
        $this->assertDatabaseHas('shop_ledger_transactions', ['id' => $manualTx->id]);
    }

    public function test_repeated_scan_all_is_idempotent_and_creates_no_duplicate_flags(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        // Tamper amount
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 9999.0]);

        // Run scan twice
        $this->scannerService->scanAll();
        $firstCount = StaffSyncFlag::query()->where('source_id', $payment->id)->count();

        $this->scannerService->scanAll();
        $secondCount = StaffSyncFlag::query()->where('source_id', $payment->id)->count();

        $this->assertEquals(1, $firstCount);
        $this->assertEquals(1, $secondCount);
    }

    public function test_staff_sync_flags_index_page_loads_with_filters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.staff.sync-flags.index'));
        $response->assertOk();
        $response->assertSee('Staff Sync Flags');
        $response->assertSee('Scan & Verify All', false);

        $aliasResponse = $this->actingAs($this->admin)->get(route('admin.hr.staff-sync-flags.index'));
        $aliasResponse->assertOk();
    }

    public function test_review_endpoint_returns_flag_and_available_fixes(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();

        // Mismatches: category and amount
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update([
                'entry_type_id' => $salaryType->id,
                'amount' => 5000.0,
            ]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->first();
        $this->assertNotNull($flag);

        $response = $this->actingAs($this->admin)->getJson(route('admin.staff.sync-flags.review', $flag));
        $response->assertOk();
        $response->assertJsonStructure([
            'flag',
            'details' => ['master', 'cashbook', 'shop_history', 'hr_history', 'salary_advance'],
            'available_fixes',
            'related_flags',
            'issues_count',
        ]);

        $data = $response->json();
        $fixKeys = array_column($data['available_fixes'], 'key');
        $this->assertContains('fix_cashbook_category', $fixKeys);
        $this->assertContains('fix_cashbook_amount', $fixKeys);
    }

    public function test_admin_can_apply_selective_fixes_partially_leaving_unselected_mismatches_open(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();
        $advanceType = LedgerEntryType::query()->where('code', 'staff_advance')->first();

        // 2 issues: category is salary (expected staff_advance) & amount is 1,500,000 (expected 3,000)
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update([
                'entry_type_id' => $salaryType->id,
                'amount' => 1500000.0,
            ]);

        $this->scannerService->scanAll();

        $openFlags = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->get();
        $this->assertCount(2, $openFlags);

        $categoryFlag = $openFlags->firstWhere('flag_code', StaffSyncFlag::CODE_CASHBOOK_CATEGORY_MISMATCH);
        $this->assertNotNull($categoryFlag);

        // HR selects ONLY 'fix_cashbook_category'
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $categoryFlag), [
            'selected_fixes' => ['fix_cashbook_category'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'applied_count' => 1,
            'remaining_flags_count' => 1,
        ]);

        // Cashbook category is now staff_advance
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertEquals((int) $advanceType->id, (int) $tx->entry_type_id);
        // Cashbook amount is UNCHANGED at 1,500,000
        $this->assertEquals(1500000.0, (float) $tx->amount);

        // Category flag is RESOLVED
        $categoryFlag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $categoryFlag->status);

        // Amount mismatch flag is STILL OPEN
        $amountFlag = StaffSyncFlag::query()
            ->open()
            ->where('source_id', $payment->id)
            ->where('flag_code', StaffSyncFlag::CODE_CASHBOOK_AMOUNT_MISMATCH)
            ->first();
        $this->assertNotNull($amountFlag);
    }

    public function test_admin_can_selectively_remove_orphan_cashbook_entry(): void
    {
        $entryType = LedgerEntryType::query()->first();

        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType?->id,
            'direction' => 'expense',
            'business_date' => now()->startOfDay(),
            'amount' => 200.0,
            'funding_source' => 'sales',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 999999, // non-existent
            'notes' => 'Old orphan entry',
        ]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('flag_code', StaffSyncFlag::CODE_ORPHAN_CASHBOOK)->first();
        $this->assertNotNull($flag);

        // HR reviews and selectively removes orphan
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'selected_fixes' => ['remove_orphan_cashbook'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'applied_count' => 1,
        ]);

        $this->assertDatabaseMissing('shop_ledger_transactions', ['id' => $orphanTx->id]);

        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);
    }

    public function test_applying_fixes_without_selection_returns_validation_error(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 5000.0]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->first();

        // Empty selected_fixes
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'selected_fixes' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Please select at least one fix to apply.',
        ]);

        // Cashbook amount remains untouched
        $this->assertDatabaseHas('shop_ledger_transactions', [
            'reference_id' => $payment->id,
            'amount' => 5000.0,
        ]);
    }

    public function test_admin_decision_resolves_amount_mismatch_to_admin_selected_value(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Staff advance',
        );

        // Discrepancy: Cashbook was corrupted/entered as 1,500,000
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 1500000.0]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->first();
        $this->assertNotNull($flag);

        // Admin decides final amount is Rs. 3,000
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'amount' => 3000.0,
            'paid_on' => now()->toDateString(),
            'payment_type' => 'advance',
            'fund_source' => 'sales',
            'shop_id' => $this->shop->id,
            'notes' => 'Admin verified correct amount is 3000',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // All 4 linked sources now agree on Rs. 3,000
        $payment->refresh();
        $this->assertEquals(3000.0, (float) $payment->amount);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => $payment->id,
            'amount' => 3000.0,
        ]);

        $this->assertEquals(3000.0, (float) $payment->advanceRequest?->approved_amount);

        // Flag is resolved
        $this->assertCount(0, StaffSyncFlag::query()->open()->where('source_id', $payment->id)->get());
    }

    public function test_admin_decision_overrides_to_higher_known_actual_payment_amount(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Staff advance',
        );

        // Cashbook has Rs. 4,000
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['amount' => 4000.0]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->first();
        $this->assertNotNull($flag);

        // Admin knows actual payment was Rs. 4,000 and decides FINAL AMOUNT = 4,000
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'amount' => 4000.0,
            'paid_on' => now()->toDateString(),
            'payment_type' => 'advance',
            'fund_source' => 'sales',
            'shop_id' => $this->shop->id,
            'notes' => 'Admin confirmed actual paid amount was 4000',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // All systems updated to 4000
        $payment->refresh();
        $this->assertEquals(4000.0, (float) $payment->amount);

        $this->assertDatabaseHas('shop_ledger_transactions', [
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => $payment->id,
            'amount' => 4000.0,
        ]);

        $this->assertEquals(4000.0, (float) $payment->advanceRequest?->approved_amount);
        $this->assertCount(0, StaffSyncFlag::query()->open()->where('source_id', $payment->id)->get());
    }

    public function test_admin_decision_resolves_category_mismatch_to_chosen_type(): void
    {
        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();

        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Staff advance',
        );

        // Cashbook is typed as salary
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['entry_type_id' => $salaryType->id]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->first();
        $this->assertNotNull($flag);

        // Admin decides final category is Salary
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'amount' => 3000.0,
            'paid_on' => now()->toDateString(),
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'shop_id' => $this->shop->id,
            'notes' => 'Classified as salary by Admin',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $payment->refresh();
        $this->assertEquals('salary', $payment->payment_type);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->first();
        $this->assertEquals((int) $salaryType->id, (int) $tx->entry_type_id);

        $this->assertCount(0, StaffSyncFlag::query()->open()->where('source_id', $payment->id)->get());
    }

    public function test_admin_decision_can_cancel_payment_in_missing_cashbook_case(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            3000.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Staff advance',
        );

        // Delete cashbook to simulate missing cashbook
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->delete();

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->first();
        $this->assertNotNull($flag);

        // Admin chooses to cancel / void the payment
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'action' => 'cancel_payment',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('shop_staff_payments', ['id' => $payment->id]);
        $this->assertCount(0, StaffSyncFlag::query()->open()->where('source_id', $payment->id)->get());
    }

    public function test_admin_decision_can_restore_orphan_cashbook_by_creating_payment(): void
    {
        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();

        $orphanTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
            'direction' => 'expense',
            'business_date' => now()->startOfDay(),
            'amount' => 500.0,
            'funding_source' => 'sales',
            'reference_type' => ShopStaffPayment::class,
            'reference_id' => 888888, // non-existent
            'notes' => 'Orphan salary expense',
        ]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('flag_code', StaffSyncFlag::CODE_ORPHAN_CASHBOOK)->first();
        $this->assertNotNull($flag);

        // Admin decides "This payment should exist" and selects employee
        $response = $this->actingAs($this->admin)->postJson(route('admin.staff.sync-flags.apply-fixes', $flag), [
            'orphan_action' => 'create_payment',
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'amount' => 500.0,
            'paid_on' => now()->toDateString(),
            'payment_type' => 'salary',
            'fund_source' => 'sales',
            'notes' => 'Restored orphan cashbook entry',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // New ShopStaffPayment created for employee
        $newPayment = ShopStaffPayment::query()->where('employee_id', $this->employee->id)->where('amount', 500.0)->first();
        $this->assertNotNull($newPayment);

        // Orphan flag resolved
        $flag->refresh();
        $this->assertEquals(StaffSyncFlag::STATUS_RESOLVED, $flag->status);

        // No open flags remaining
        $this->assertCount(0, StaffSyncFlag::query()->open()->get());
    }

    public function test_review_payload_returns_rich_detected_problems_and_existing_values(): void
    {
        $payment = $this->advanceService->recordAdminShopStaffPayment(
            $this->employee,
            $this->shop,
            500.0,
            'advance',
            'sales',
            now()->startOfDay(),
            $this->admin,
            'Advance payment',
        );

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->first();
        // Tamper to salary type
        ShopLedgerTransaction::query()
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->update(['entry_type_id' => $salaryType->id]);

        $this->scannerService->scanAll();

        $flag = StaffSyncFlag::query()->open()->where('source_id', $payment->id)->first();
        $this->assertNotNull($flag);

        $response = $this->actingAs($this->admin)->getJson(route('admin.staff.sync-flags.review', $flag));

        $response->assertOk();
        $response->assertJsonStructure([
            'flag',
            'detected_problems',
            'existing_values' => [
                'amount',
                'category',
                'paid_on',
                'fund_source',
                'shop',
                'notes',
            ],
            'shops',
            'employees',
        ]);

        $data = $response->json();
        $this->assertFalse($data['is_orphan']);
        $this->assertEquals(500.0, $data['existing_values']['amount']['shop_staff']);
        $this->assertEquals(500.0, $data['existing_values']['amount']['cashbook']);
        $this->assertTrue($data['existing_values']['amount']['is_match']);
        $this->assertFalse($data['existing_values']['category']['is_match']);
    }
}
