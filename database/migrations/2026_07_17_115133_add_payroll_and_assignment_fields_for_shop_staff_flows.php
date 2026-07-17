<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_employee_assignments', function (Blueprint $table): void {
            $table->date('effective_from')->nullable()->after('assigned_by')->index();
            $table->date('effective_to')->nullable()->after('effective_from')->index();
            $table->string('status', 20)->default('active')->after('effective_to')->index();
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('salary_type', 20)->default('monthly')->after('monthly_salary')->index();
            $table->decimal('daily_wage', 12, 2)->default(0)->after('salary_type');
        });

        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->string('salary_type', 20)->default('monthly')->after('employee_category_id')->index();
            $table->decimal('daily_wage', 12, 2)->default(0)->after('base_salary');
        });

        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->foreignId('shop_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->foreignId('employee_advance_request_id')->nullable()->after('journal_entry_id')->constrained()->nullOnDelete();
            $table->string('fund_source', 30)->default('company_cash')->after('payment_type')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->dropForeign(['shop_id']);
            $table->dropForeign(['employee_advance_request_id']);
            $table->dropColumn(['shop_id', 'employee_advance_request_id', 'fund_source']);
        });

        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->dropColumn(['salary_type', 'daily_wage']);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['salary_type', 'daily_wage']);
        });

        Schema::table('shop_employee_assignments', function (Blueprint $table): void {
            $table->dropColumn(['effective_from', 'effective_to', 'status', 'notes']);
        });
    }
};
