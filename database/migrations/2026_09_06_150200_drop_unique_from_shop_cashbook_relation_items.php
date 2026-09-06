<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_cashbook_relation_items', function (Blueprint $table) {
            $table->index('relation_id', 'fk_rel_items_relation_idx');
            $table->index(['relation_id', 'shop_ledger_entry_setting_id'], 'rel_items_relation_setting_index');
            $table->dropUnique('relation_setting_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shop_cashbook_relation_items', function (Blueprint $table) {
            $table->unique(['relation_id', 'shop_ledger_entry_setting_id'], 'relation_setting_unique');
            $table->dropIndex('rel_items_relation_setting_index');
            $table->dropIndex('fk_rel_items_relation_idx');
        });
    }
};
