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
        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('present_days', 8, 2)->default(0);
            $table->decimal('half_days', 8, 2)->default(0);
            $table->decimal('paid_leave_days', 8, 2)->default(0);
            $table->decimal('absent_days', 8, 2)->default(0);
            $table->decimal('payable_units', 8, 2)->default(0);
            $table->decimal('computed_amount', 12, 2)->default(0);
            $table->decimal('override_amount', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->json('rule_snapshot');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_run_items');
    }
};
