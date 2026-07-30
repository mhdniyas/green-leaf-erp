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
                $table->foreignId('company_accounting_category_id');
                $table->foreignId('journal_entry_id')->nullable();
                $table->foreignId('reversal_journal_entry_id')->nullable();
                $table->string('type', 20);
                $table->date('business_date')->index();
                $table->string('payment_mode', 30)->index();
                $table->string('payment_reference', 120)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('reference', 120)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('final')->index();
                $table->foreignId('created_by');
                $table->foreignId('reversed_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_note')->nullable();
                $table->timestamps();

                $table->index(['type', 'business_date'], 'company_accounting_entries_type_date_idx');
                $table->index(['status', 'business_date'], 'company_accounting_entries_status_date_idx');

                $table->foreign('company_accounting_category_id', 'company_acct_entries_category_fk')
                    ->references('id')
                    ->on('company_accounting_categories')
                    ->restrictOnDelete();
                $table->foreign('journal_entry_id', 'company_acct_entries_journal_fk')
                    ->references('id')
                    ->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('reversal_journal_entry_id', 'company_acct_entries_reversal_journal_fk')
                    ->references('id')
                    ->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('created_by', 'company_acct_entries_created_by_fk')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
                $table->foreign('reversed_by', 'company_acct_entries_reversed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_accounting_entries');
        Schema::dropIfExists('company_accounting_categories');
    }
};
