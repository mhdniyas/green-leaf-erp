<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_accounting_categories')) {
            Schema::create('company_accounting_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 20);
                $table->string('name', 120);
                $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['type', 'name'], 'company_accounting_categories_type_name_unique');
                $table->index(['type', 'is_active'], 'company_accounting_categories_type_active_idx');
            });
        }

        if (! Schema::hasTable('company_accounting_entries')) {
            Schema::create('company_accounting_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_accounting_category_id')->constrained('company_accounting_categories')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->string('type', 20);
                $table->date('business_date')->index();
                $table->string('payment_mode', 30)->index();
                $table->string('payment_reference', 120)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('reference', 120)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('final')->index();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_note')->nullable();
                $table->timestamps();

                $table->index(['type', 'business_date'], 'company_accounting_entries_type_date_idx');
                $table->index(['status', 'business_date'], 'company_accounting_entries_status_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_accounting_entries');
        Schema::dropIfExists('company_accounting_categories');
    }
};
