<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_cashbook_relations', function (Blueprint $table) {
            $table->id();
            $table->string('public_uuid')->unique();
            $table->unsignedBigInteger('shop_id');
            $table->string('name');
            $table->string('relation_type')->default('settlement');
            $table->boolean('enabled')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'relation_type']);
        });

        Schema::create('shop_cashbook_relation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relation_id')->constrained('shop_cashbook_relations', 'id', 'fk_rel_items_relation')->cascadeOnDelete();
            $table->foreignId('shop_ledger_entry_setting_id')->constrained('shop_ledger_entry_settings', 'id', 'fk_rel_items_setting')->cascadeOnDelete();
            $table->string('role')->default('add'); // 'add' or 'subtract'
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['relation_id', 'shop_ledger_entry_setting_id'], 'relation_setting_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_cashbook_relation_items');
        Schema::dropIfExists('shop_cashbook_relations');
    }
};
