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
        Schema::create('shop_staff_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_run_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_advance_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_on')->index();
            $table->decimal('amount', 12, 2);
            $table->string('payment_type', 20)->default('salary')->index();
            $table->string('fund_source', 30)->default('petty_cash')->index();
            $table->string('status', 20)->default('paid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'paid_on']);
            $table->index(['employee_id', 'paid_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_staff_payments');
    }
};
