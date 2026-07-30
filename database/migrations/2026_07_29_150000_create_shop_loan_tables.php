<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_loan_category_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_accounting_category_id')->constrained('shop_accounting_categories')->cascadeOnDelete();
            $table->string('effect', 30)->default('use_loan');
            $table->decimal('default_daily_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'shop_accounting_category_id'], 'shop_loan_category_settings_unique');
            $table->index(['shop_id', 'effect'], 'shop_loan_category_settings_effect_index');
        });

        Schema::create('shop_loan_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('business_date')->index();
            $table->decimal('amount', 12, 2);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('approved')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'business_date'], 'shop_loan_entries_shop_date_index');
            $table->index(['type', 'status', 'business_date'], 'shop_loan_entries_cash_journal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_loan_entries');
        Schema::dropIfExists('shop_loan_category_settings');
    }
};
