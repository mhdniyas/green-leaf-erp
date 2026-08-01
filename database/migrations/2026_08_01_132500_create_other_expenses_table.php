<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_accounting_entry_id')->nullable()->constrained('company_accounting_entries')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category', 80);
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expense_date'], 'other_expenses_user_date_idx');
            $table->index(['expense_date', 'company_accounting_entry_id'], 'other_expenses_date_company_entry_idx');
            $table->index(['category', 'expense_date'], 'other_expenses_category_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_expenses');
    }
};
