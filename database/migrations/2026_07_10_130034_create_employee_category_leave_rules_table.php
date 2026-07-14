<?php

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
        Schema::create('employee_category_leave_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('annual_entitlement', 8, 2)->default(0);
            $table->decimal('monthly_accrual_amount', 8, 2)->nullable();
            $table->string('allocation_frequency')->default('monthly');
            $table->boolean('carry_forward_allowed')->default(false);
            $table->decimal('maximum_carry_forward_days', 8, 2)->default(0);
            $table->unsignedInteger('carry_forward_expiry_months')->nullable();
            $table->decimal('payroll_weight', 5, 2)->default(1);
            $table->boolean('negative_balance_allowed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_category_id', 'leave_type_id'], 'category_leave_rule_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_category_leave_rules');
    }
};
