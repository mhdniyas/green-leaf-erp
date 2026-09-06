<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Services\HR\SalaryStage2PreflightService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalaryStage2MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_factories_create_records_with_null_new_fields(): void
    {
        $advanceRequest = EmployeeAdvanceRequest::factory()->create();
        $this->assertNull($advanceRequest->request_uuid);
        $this->assertNull($advanceRequest->approved_fund_source);
        $this->assertNull($advanceRequest->approved_company_account_id);
        $this->assertNull($advanceRequest->review_snapshot);

        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
        ]);
        $this->assertNull($payrollItem->opening_recovery_amount);
        $this->assertNull($payrollItem->closing_recovery_amount);

        $shopPayment = ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $payrollItem->employee_id,
        ]);
        $this->assertNull($shopPayment->request_uuid);
    }

    public function test_multiple_historical_null_uuids_coexist_without_violation(): void
    {
        $employee = Employee::factory()->create();
        $shop = Shop::factory()->create();

        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $employee->id,
        ]);

        // Two advance requests with null request_uuid
        $req1 = EmployeeAdvanceRequest::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'request_uuid' => null,
        ]);
        $req2 = EmployeeAdvanceRequest::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'request_uuid' => null,
        ]);

        $this->assertNull($req1->request_uuid);
        $this->assertNull($req2->request_uuid);
        $this->assertNotEquals($req1->id, $req2->id);

        // Two shop payments with null request_uuid
        $pay1 = ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'request_uuid' => null,
        ]);
        $pay2 = ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'request_uuid' => null,
        ]);

        $this->assertNull($pay1->request_uuid);
        $this->assertNull($pay2->request_uuid);
        $this->assertNotEquals($pay1->id, $pay2->id);
    }

    public function test_duplicate_non_null_request_uuids_are_rejected(): void
    {
        $uuid = (string) Str::uuid();

        EmployeeAdvanceRequest::factory()->create(['request_uuid' => $uuid]);

        $this->expectException(QueryException::class);
        EmployeeAdvanceRequest::factory()->create(['request_uuid' => $uuid]);
    }

    public function test_shop_staff_payment_duplicate_uuid_is_rejected(): void
    {
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
        ]);

        $uuid = (string) Str::uuid();

        ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $payrollItem->employee_id,
            'request_uuid' => $uuid,
        ]);

        $this->expectException(QueryException::class);
        ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $payrollItem->employee_id,
            'request_uuid' => $uuid,
        ]);
    }

    public function test_existing_company_payment_request_uuid_uniqueness_is_preserved(): void
    {
        $uuid = (string) Str::uuid();
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]);
        $payrollItem = PayrollRunItem::factory()->create(['payroll_run_id' => $payrollRun->id]);
        $attributes = [
            'request_uuid' => $uuid,
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $payrollItem->employee_id,
        ];
        PayrollPayment::factory()->create($attributes);

        $this->expectException(QueryException::class);
        PayrollPayment::factory()->create($attributes);
    }

    public function test_preflight_reports_review_and_blocking_conditions_without_writes(): void
    {
        $request = EmployeeAdvanceRequest::factory()->create();
        $shopPayment = ShopStaffPayment::factory()->create([
            'payroll_run_id' => null,
            'payroll_run_item_id' => null,
            'employee_id' => $request->employee_id,
            'shop_id' => $request->shop_id,
            'employee_advance_request_id' => $request->id,
        ]);
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);
        $payrollItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $request->employee_id,
        ]);
        PayrollPayment::factory()->count(2)->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $request->employee_id,
            'shop_id' => $request->shop_id,
            'employee_advance_request_id' => $request->id,
        ]);
        $conflictingRequest = EmployeeAdvanceRequest::factory()->create([
            'shop_staff_payment_id' => $shopPayment->id,
        ]);
        $before = [
            EmployeeAdvanceRequest::class => EmployeeAdvanceRequest::count(),
            ShopStaffPayment::class => ShopStaffPayment::count(),
            PayrollPayment::class => PayrollPayment::count(),
        ];

        $result = $this->app->make(SalaryStage2PreflightService::class)->inspect();

        $this->assertContains($request->id, $result['company_instalment_request_ids']);
        $this->assertContains($request->id, $result['cross_table_request_ids']);
        $this->assertContains($conflictingRequest->id, $result['conflicting_forward_link_request_ids']);
        $this->assertContains($shopPayment->id, $result['conflicting_reverse_shop_payment_ids']);
        $this->assertTrue($result['has_blocking_issues']);
        $this->assertSame($before[EmployeeAdvanceRequest::class], EmployeeAdvanceRequest::count());
        $this->assertSame($before[ShopStaffPayment::class], ShopStaffPayment::count());
        $this->assertSame($before[PayrollPayment::class], PayrollPayment::count());
    }

    public function test_preflight_detects_duplicate_shop_request_links_without_mutating_them(): void
    {
        $shopMigration = require database_path('migrations/2026_09_05_195202_add_idempotency_and_unique_request_to_shop_staff_payments_table.php');
        $shopMigration->down();
        $request = EmployeeAdvanceRequest::factory()->create();
        $attributes = [
            'payroll_run_id' => null,
            'payroll_run_item_id' => null,
            'employee_id' => $request->employee_id,
            'shop_id' => $request->shop_id,
            'employee_advance_request_id' => $request->id,
        ];
        $payments = ShopStaffPayment::factory()->count(2)->create($attributes);
        $before = ShopStaffPayment::where('employee_advance_request_id', $request->id)->pluck('id')->all();

        $result = $this->app->make(SalaryStage2PreflightService::class)->inspect();

        $this->assertContains($request->id, $result['duplicate_shop_request_ids']);
        $this->assertTrue($result['has_blocking_issues']);
        $this->assertSame($before, ShopStaffPayment::where('employee_advance_request_id', $request->id)->pluck('id')->all());

        $payments->last()->delete();
        $shopMigration->up();
    }

    public function test_shop_staff_payments_unique_advance_request_constraint(): void
    {
        $advanceRequest = EmployeeAdvanceRequest::factory()->create();
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $advanceRequest->employee_id,
        ]);

        // First payment linked to request succeeds
        ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $advanceRequest->employee_id,
            'employee_advance_request_id' => $advanceRequest->id,
        ]);

        // Second payment linked to same request fails due to unique constraint
        $this->expectException(QueryException::class);
        ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $advanceRequest->employee_id,
            'employee_advance_request_id' => $advanceRequest->id,
        ]);
    }

    public function test_company_payments_support_instalments_without_unique_constraint_failure(): void
    {
        $advanceRequest = EmployeeAdvanceRequest::factory()->create();
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);
        $payrollRunItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $advanceRequest->employee_id,
        ]);

        // First instalment
        $payment1 = PayrollPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $advanceRequest->employee_id,
            'employee_advance_request_id' => $advanceRequest->id,
            'amount' => 500.0,
            'payment_type' => 'advance',
        ]);

        // Second instalment (must succeed, preserving instalment capability)
        $payment2 = PayrollPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollRunItem->id,
            'employee_id' => $advanceRequest->employee_id,
            'employee_advance_request_id' => $advanceRequest->id,
            'amount' => 500.0,
            'payment_type' => 'advance',
        ]);

        $this->assertEquals($advanceRequest->id, $payment1->employee_advance_request_id);
        $this->assertEquals($advanceRequest->id, $payment2->employee_advance_request_id);
        $this->assertNotEquals($payment1->id, $payment2->id);
    }

    public function test_approved_company_account_foreign_key_and_null_on_delete(): void
    {
        $account = CompanyAccount::query()->create([
            'name' => 'Main Operating Cash',
            'public_uuid' => (string) Str::uuid(),
            'account_type' => 'cash',
            'opening_balance' => 10000.0,
            'current_balance' => 10000.0,
            'is_default' => true,
            'enabled' => true,
        ]);

        $request = EmployeeAdvanceRequest::factory()->create([
            'approved_company_account_id' => $account->id,
            'approved_fund_source' => 'company_cash',
        ]);

        $this->assertEquals($account->id, $request->approvedCompanyAccount->id);
        $this->assertEquals('Main Operating Cash', $request->approvedCompanyAccount->name);

        // Deleting the account sets approved_company_account_id to NULL without deleting the request
        $account->delete();

        $request->refresh();
        $this->assertNull($request->approved_company_account_id);
        $this->assertNotNull($request->id);
    }

    public function test_full_review_snapshot_contract_serialization(): void
    {
        $snapshot = [
            'version' => 1,
            'calculated_at' => '2026-09-05T19:30:00Z',
            'employee_id' => 12,
            'shop_id' => 4,
            'payroll_month' => '2026-09-01',
            'earnings' => 5000.00,
            'advances_paid' => 1000.00,
            'salary_paid' => 0.00,
            'opening_recovery' => 0.00,
            'available_advance' => 1500.00,
            'requested_amount' => 2000.00,
            'approved_amount' => 1800.00,
            'decision' => 'approved',
            'reasons' => ['Approved by HR exception'],
            'approved_fund_source' => 'company_bank',
            'company_account' => [
                'id' => 3,
                'public_uuid' => 'acc-uuid-1234',
                'name' => 'Primary Bank Account',
                'account_type' => 'bank',
            ],
        ];

        $request = EmployeeAdvanceRequest::factory()->create([
            'review_snapshot' => $snapshot,
        ]);

        $request->refresh();
        $this->assertIsArray($request->review_snapshot);
        $this->assertEquals(1, $request->review_snapshot['version']);
        $this->assertEquals('Primary Bank Account', $request->review_snapshot['company_account']['name']);
        $this->assertEquals('bank', $request->review_snapshot['company_account']['account_type']);
    }

    public function test_recovery_balance_casts_and_null_versus_zero_semantics(): void
    {
        $payrollRun1 = PayrollRun::factory()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
        ]);
        $payrollRun2 = PayrollRun::factory()->create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);
        $payrollRun3 = PayrollRun::factory()->create([
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);

        // Unknown balance (null)
        $itemUnknown = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun1->id,
            'opening_recovery_amount' => null,
            'closing_recovery_amount' => null,
        ]);
        $this->assertNull($itemUnknown->opening_recovery_amount);
        $this->assertNull($itemUnknown->closing_recovery_amount);

        // Verified zero balance
        $itemZero = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun2->id,
            'opening_recovery_amount' => 0.00,
            'closing_recovery_amount' => 0.00,
        ]);
        $this->assertEquals('0.00', (string) $itemZero->opening_recovery_amount);
        $this->assertEquals('0.00', (string) $itemZero->closing_recovery_amount);

        // Positive debt balance
        $itemDebt = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun3->id,
            'opening_recovery_amount' => 1250.50,
            'closing_recovery_amount' => 500.00,
        ]);
        $this->assertEquals('1250.50', (string) $itemDebt->opening_recovery_amount);
        $this->assertEquals('500.00', (string) $itemDebt->closing_recovery_amount);
    }

    public function test_stage_2_migrations_preserve_historical_rows_and_are_reversible(): void
    {
        // Assert columns exist after migration
        $this->assertTrue(Schema::hasColumn('employee_advance_requests', 'request_uuid'));
        $this->assertTrue(Schema::hasColumn('employee_advance_requests', 'approved_fund_source'));
        $this->assertTrue(Schema::hasColumn('employee_advance_requests', 'approved_company_account_id'));
        $this->assertTrue(Schema::hasColumn('employee_advance_requests', 'review_snapshot'));
        $this->assertTrue(Schema::hasColumn('shop_staff_payments', 'request_uuid'));
        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'opening_recovery_amount'));
        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'closing_recovery_amount'));

        $migrations = $this->stageTwoMigrations();
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        // Assert Stage 2 columns are removed
        $this->assertFalse(Schema::hasColumn('employee_advance_requests', 'request_uuid'));
        $this->assertFalse(Schema::hasColumn('employee_advance_requests', 'approved_fund_source'));
        $this->assertFalse(Schema::hasColumn('employee_advance_requests', 'approved_company_account_id'));
        $this->assertFalse(Schema::hasColumn('employee_advance_requests', 'review_snapshot'));
        $this->assertFalse(Schema::hasColumn('shop_staff_payments', 'request_uuid'));
        $this->assertFalse(Schema::hasColumn('payroll_run_items', 'opening_recovery_amount'));
        $this->assertFalse(Schema::hasColumn('payroll_run_items', 'closing_recovery_amount'));

        // Base tables still exist
        $this->assertTrue(Schema::hasTable('employee_advance_requests'));
        $this->assertTrue(Schema::hasTable('shop_staff_payments'));
        $this->assertTrue(Schema::hasTable('payroll_run_items'));

        $request = EmployeeAdvanceRequest::factory()->create([
            'requested_amount' => 1234.56,
            'fund_source' => 'sales_cash',
            'request_note' => 'Historical request retained',
        ]);
        $payrollRun = PayrollRun::factory()->create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
        ]);
        $payrollItem = PayrollRunItem::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'employee_id' => $request->employee_id,
            'final_amount' => 987.65,
        ]);
        $shopPayment = ShopStaffPayment::factory()->create([
            'payroll_run_id' => $payrollRun->id,
            'payroll_run_item_id' => $payrollItem->id,
            'employee_id' => $request->employee_id,
            'shop_id' => $request->shop_id,
            'amount' => 321.09,
        ]);

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->assertTrue(Schema::hasColumn('employee_advance_requests', 'request_uuid'));
        $this->assertTrue(Schema::hasColumn('shop_staff_payments', 'request_uuid'));
        $this->assertTrue(Schema::hasColumn('payroll_run_items', 'opening_recovery_amount'));
        $this->assertEquals('1234.56', (string) $request->fresh()->requested_amount);
        $this->assertSame('sales_cash', $request->fresh()->fund_source);
        $this->assertSame('Historical request retained', $request->fresh()->request_note);
        $this->assertEquals('987.65', (string) $payrollItem->fresh()->final_amount);
        $this->assertEquals('321.09', (string) $shopPayment->fresh()->amount);
        $this->assertNull($request->fresh()->request_uuid);
        $this->assertNull($payrollItem->fresh()->opening_recovery_amount);
        $this->assertNull($shopPayment->fresh()->request_uuid);
    }

    /** @return array<int, Migration> */
    private function stageTwoMigrations(): array
    {
        return [
            require database_path('migrations/2026_09_05_195201_add_idempotency_and_approval_fields_to_employee_advance_requests_table.php'),
            require database_path('migrations/2026_09_05_195202_add_idempotency_and_unique_request_to_shop_staff_payments_table.php'),
            require database_path('migrations/2026_09_05_195204_add_recovery_fields_to_payroll_run_items_table.php'),
        ];
    }
}
