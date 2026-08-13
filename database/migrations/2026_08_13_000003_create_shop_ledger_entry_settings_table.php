<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->foreignId('entry_type_id')->constrained('ledger_entry_types');

            // Versioning: a shop's rule for an entry type can change over time
            // without ever rewriting history.
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->boolean('enabled')->default(true);

            $table->string('default_funding_source')->default('none');
            $table->json('allowed_funding_sources')->nullable(); // null/empty = all allowed

            $table->boolean('include_in_sales')->default(false);
            $table->boolean('include_in_income')->default(false);
            $table->boolean('include_in_expense')->default(false);
            $table->boolean('include_in_pl')->default(true);

            // Explicit overrides — used for Transfers/Settlements where the
            // universal funding-source table doesn't apply.
            // Leave null to let the funding-source engine decide.
            $table->string('settlement_behavior')->nullable(); // increase | decrease | none
            $table->string('petty_behavior')->nullable();
            $table->string('company_pending_behavior')->nullable();

            // Secondary entry generation: e.g. Income CP → Expense CP
            $table->boolean('generates_secondary_entry')->default(false);
            $table->foreignId('secondary_entry_type_id')->nullable()->constrained('ledger_entry_types');
            $table->string('secondary_amount_mode')->default('same_amount'); // same_amount | percentage
            $table->decimal('secondary_amount_value', 15, 4)->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'entry_type_id', 'effective_from', 'effective_to'], 'shop_entry_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ledger_entry_settings');
    }
};
