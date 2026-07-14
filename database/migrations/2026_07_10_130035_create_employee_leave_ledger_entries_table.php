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
        Schema::create('employee_leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_category_leave_rule_id')->nullable()->constrained('employee_category_leave_rules', 'id', 'ledger_rule_fk')->nullOnDelete();
            $table->date('financial_year_start');
            $table->date('transaction_date');
            $table->string('entry_type');
            $table->decimal('credit', 8, 2)->default(0);
            $table->decimal('debit', 8, 2)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'financial_year_start'], 'leave_ledger_balance_lookup');
            $table->index(['source_type', 'source_id'], 'leave_ledger_source_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_leave_ledger_entries');
    }
};
