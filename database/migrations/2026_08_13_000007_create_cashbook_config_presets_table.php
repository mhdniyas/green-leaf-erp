<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named preset configurations — shared rule sets that shops are assigned to.
        Schema::create('cashbook_config_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // e.g. "Standard Aiswarya Veg Shop"
            $table->string('slug')->unique();               // e.g. "standard-veg-shop"
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);  // one preset can be the system default
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Entry-type rules scoped to a preset — mirrors shop_ledger_entry_settings structure.
        Schema::create('cashbook_preset_entry_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('cashbook_config_presets')->cascadeOnDelete();
            $table->foreignId('entry_type_id')->constrained('ledger_entry_types');

            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->boolean('enabled')->default(true);

            $table->string('default_funding_source')->default('none');
            $table->json('allowed_funding_sources')->nullable();

            $table->boolean('include_in_sales')->default(false);
            $table->boolean('include_in_income')->default(false);
            $table->boolean('include_in_expense')->default(false);
            $table->boolean('include_in_pl')->default(true);

            $table->string('settlement_behavior')->nullable();
            $table->string('petty_behavior')->nullable();
            $table->string('company_pending_behavior')->nullable();

            $table->boolean('generates_secondary_entry')->default(false);
            $table->foreignId('secondary_entry_type_id')->nullable()->constrained('ledger_entry_types');
            $table->string('secondary_amount_mode')->default('same_amount');
            $table->decimal('secondary_amount_value', 15, 4)->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['preset_id', 'entry_type_id'], 'cashbook_preset_entry_unique');
            $table->index(['preset_id', 'entry_type_id'], 'cashbook_preset_entry_idx');
        });

        // Add preset_id FK to shop_ledger_profiles
        Schema::table('shop_ledger_profiles', function (Blueprint $table) {
            $table->foreignId('preset_id')
                ->nullable()
                ->constrained('cashbook_config_presets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_ledger_profiles', function (Blueprint $table) {
            $table->dropForeign(['preset_id']);
            $table->dropColumn('preset_id');
        });

        Schema::dropIfExists('cashbook_preset_entry_settings');
        Schema::dropIfExists('cashbook_config_presets');
    }
};
