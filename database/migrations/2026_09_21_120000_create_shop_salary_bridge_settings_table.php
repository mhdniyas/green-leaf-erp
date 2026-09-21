<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_salary_bridge_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('transaction_type', 40);
            $table->unsignedBigInteger('shop_ledger_entry_setting_id')->nullable();
            $table->string('default_payment_mode', 30)->default('sales_cash');
            $table->json('allowed_payment_modes')->nullable();
            $table->unsignedBigInteger('company_payable_settlement_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('shop_id', 'fk_ssb_shop')
                ->references('id')
                ->on('shops')
                ->cascadeOnDelete();

            $table->foreign('shop_ledger_entry_setting_id', 'fk_ssb_setting')
                ->references('id')
                ->on('shop_ledger_entry_settings')
                ->nullOnDelete();

            $table->foreign('company_payable_settlement_id', 'fk_ssb_settlement')
                ->references('id')
                ->on('shop_cashbook_relations')
                ->nullOnDelete();

            $table->unique(['shop_id', 'transaction_type'], 'uniq_shop_salary_bridge_type');
            $table->index(['shop_id', 'is_enabled'], 'idx_shop_salary_bridge_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_salary_bridge_settings');
    }
};
