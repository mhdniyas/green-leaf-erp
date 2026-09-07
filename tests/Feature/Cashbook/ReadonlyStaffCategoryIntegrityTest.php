<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\HR\EmployeeAdvanceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadonlyStaffCategoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Shop $shop;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO_01',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->owner->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->employee = Employee::factory()->create([
            'name' => 'Suresh Kumar',
            'employee_code' => 'EMP-SURESH',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'default_shop_id' => $this->shop->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 20000,
            'joined_on' => '2026-01-01',
        ]);

        ShopEmployeeAssignment::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'assigned_from' => '2026-01-01',
            'assigned_to' => null,
            'status' => 'active',
        ]);

        EmployeeAdvanceRule::query()->create([
            'rule_name' => 'Default Rule',
            'advance_percent' => 50,
            'minimum_present_days' => 1,
            'is_active' => true,
        ]);

        for ($d = 1; $d <= 30; $d++) {
            EmployeeAttendance::query()->create([
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => sprintf('2026-09-%02d', $d),
                'status' => 'present',
                'source' => 'manual',
                'day_count' => 1.0,
            ]);
        }

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_admin_can_toggle_and_save_is_readonly_category_setting(): void
    {
        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'rent_expense'))
            ->firstOrFail();

        $this->assertFalse($setting->is_readonly);

        $payload = [
            'setting_id' => $setting->id,
            'display_name' => 'Rent Expense',
            'enabled' => '1',
            'note_enabled' => '0',
            'is_readonly' => '1',
            'default_funding_source' => 'sales',
            'include_in_sales' => '0',
            'include_in_income' => '0',
            'include_in_expense' => '1',
            'include_in_pl' => '1',
            'include_in_payable' => '0',
            'payable_direction' => 'add',
            'settlement_behavior' => 'none',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
            'generates_secondary_entry' => '0',
            'secondary_amount_mode' => 'same_amount',
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertTrue($setting->fresh()->is_readonly);
    }

    public function test_backend_rejects_manual_salary_and_staff_advance_cashbook_submissions(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
            'business_date' => '2026-09-15',
            'entries' => [
                [
                    'entry_type_code' => 'salary',
                    'amount' => 5000.0,
                    'funding_source' => 'sales',
                    'notes' => 'Manual salary entry attempt',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entries']);
        $response->assertJsonPath('message', 'Manual cashbook entry for Salary is prohibited. Salary payments and staff advances must be created and updated only from the Staff section.');
    }

    public function test_backend_rejects_manual_cashbook_submissions_for_explicitly_configured_readonly_categories(): void
    {
        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'rent_expense'))
            ->firstOrFail();

        $setting->update(['is_readonly' => true]);

        $response = $this->actingAs($this->owner)->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
            'business_date' => '2026-09-15',
            'entries' => [
                [
                    'entry_type_code' => 'rent_expense',
                    'amount' => 12000.0,
                    'funding_source' => 'sales',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entries']);
    }

    public function test_backend_allows_manual_cashbook_submissions_for_normal_unrestricted_categories(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('shop-owner.cashbook.api.bulk-record-entries'), [
            'business_date' => '2026-09-15',
            'entries' => [
                [
                    'entry_type_code' => 'other_expense',
                    'amount' => 150.0,
                    'funding_source' => 'sales',
                    'notes' => 'Tea & Snacks',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_staff_salary_creation_and_advance_update_syncs_to_cashbook_without_duplicates_and_matches_dates(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        $advanceService = app(EmployeeAdvanceService::class);

        // 1. Staff Advance Creation
        $advanceRequest = $advanceService->requestOrPayAdvance(
            $this->employee,
            $this->shop,
            3000.0,
            'sales_cash',
            Carbon::parse('2026-09-15'),
            $this->owner,
            'Mid-month advance',
            'uuid-advance-101'
        );

        $this->assertEquals('approved', $advanceRequest->status);
        $payment = $advanceRequest->shopStaffPayment;
        $this->assertNotNull($payment);
        $this->assertEquals('2026-09-15', $payment->paid_on->toDateString());

        // Check projected Cashbook transaction
        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $transactions);
        $tx = $transactions->first();
        $this->assertEquals(3000.0, (float) $tx->amount);
        $this->assertEquals('2026-09-15', $tx->business_date->toDateString());

        // Verify Staff History date matches Cashbook transaction date
        $historyResponse = $this->actingAs($this->owner)->get(route('shop-owner.staff.index', [
            'shop' => $this->shop->code,
            'tab' => 'history',
            'month' => '2026-09',
        ]));
        $historyResponse->assertOk();

        // 2. Staff Advance Update (Update amount to 4500 and date to 2026-09-16)
        $updatedAdvance = $advanceService->updateAdvance(
            $advanceRequest,
            4500.0,
            Carbon::parse('2026-09-16'),
            $this->owner,
            'Updated advance note'
        );

        // Check that no duplicate cashbook transactions were created
        $updatedTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $payment->id)
            ->get();

        $this->assertCount(1, $updatedTransactions);
        $updatedTx = $updatedTransactions->first();
        $this->assertEquals(4500.0, (float) $updatedTx->amount);
        $this->assertEquals('2026-09-16', $updatedTx->business_date->toDateString());
        $this->assertEquals($updatedAdvance->shopStaffPayment->paid_on->toDateString(), $updatedTx->business_date->toDateString());

        // 3. Staff Salary Payment Creation
        $salaryPayment = $advanceService->recordShopSalaryPayment(
            $this->employee,
            $this->shop,
            5000.0,
            'sales_cash',
            Carbon::parse('2026-09-30'),
            $this->owner,
            'September Salary Final',
            'uuid-salary-202'
        );

        $this->assertNotNull($salaryPayment);
        $salaryTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopStaffPayment::class)
            ->where('reference_id', $salaryPayment->id)
            ->get();

        $this->assertCount(1, $salaryTransactions);
        $this->assertEquals(5000.0, (float) $salaryTransactions->first()->amount);
        $this->assertEquals('2026-09-30', $salaryTransactions->first()->business_date->toDateString());
    }
}
