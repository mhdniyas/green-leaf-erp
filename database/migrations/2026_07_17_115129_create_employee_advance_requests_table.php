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
        if (Schema::hasTable('employee_advance_requests')) {
            Schema::table('employee_advance_requests', function (Blueprint $table): void {
                $table->index('shop_staff_payment_id', 'employee_advance_requests_shop_staff_payment_id_index');
                $table->index('requested_on', 'employee_advance_requests_requested_on_index');
                $table->index('payroll_month', 'employee_advance_requests_payroll_month_index');
                $table->index('fund_source', 'employee_advance_requests_fund_source_index');
                $table->index('status', 'employee_advance_requests_status_index');
                $table->index(['shop_id', 'status'], 'employee_advance_requests_shop_id_status_index');
                $table->index(['employee_id', 'payroll_month'], 'employee_advance_requests_employee_id_payroll_month_index');
                $table->foreign('employee_advance_rule_id', 'employee_advance_requests_employee_advance_rule_id_foreign')->references('id')->on('employee_advance_rules')->nullOnDelete();
                $table->foreign('payroll_payment_id', 'employee_advance_requests_payroll_payment_id_foreign')->references('id')->on('payroll_payments')->nullOnDelete();
                $table->foreign('requested_by', 'employee_advance_requests_requested_by_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('reviewed_by', 'employee_advance_requests_reviewed_by_foreign')->references('id')->on('users')->nullOnDelete();
            });

            return;
        }

        Schema::create('employee_advance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_advance_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('shop_staff_payment_id')->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('requested_on')->index();
            $table->date('payroll_month')->index();
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('eligible_amount', 12, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->string('fund_source', 30)->default('petty_cash')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->json('rule_snapshot')->nullable();
            $table->text('request_note')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['employee_id', 'payroll_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_advance_requests');
    }
};
