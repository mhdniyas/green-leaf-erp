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
        if (! Schema::hasTable('shop_bank_settlement_adjustment_rules')) {
            Schema::create('shop_bank_settlement_adjustment_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('entry_type_id')->constrained('ledger_entry_types')->cascadeOnDelete();
                $table->string('label', 120);
                $table->string('direction', 10)->default('minus'); // plus | minus
                $table->boolean('enabled')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['shop_id', 'entry_type_id'], 'shop_bank_adj_rules_shop_entry_idx');
                $table->index(['shop_id', 'entry_type_id', 'enabled'], 'shop_bank_adj_rules_shop_entry_enabled_idx');
            });
        }

        if (! Schema::hasTable('shop_bank_settlement_adjustments')) {
            Schema::create('shop_bank_settlement_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->date('business_date');
                $table->foreignId('entry_type_id')->constrained('ledger_entry_types')->cascadeOnDelete();
                $table->foreignId('rule_id')->nullable()->constrained('shop_bank_settlement_adjustment_rules')->nullOnDelete();
                $table->string('label', 120);
                $table->string('direction', 10)->default('minus'); // plus | minus
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['shop_id', 'business_date', 'entry_type_id'], 'shop_bank_adj_daily_lookup_idx');
                $table->index('rule_id', 'shop_bank_adj_rule_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_bank_settlement_adjustments');
        Schema::dropIfExists('shop_bank_settlement_adjustment_rules');
    }
};
