<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_cashbook_relation_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('shop_ledger_entry_setting_id')->nullable()->change();
            $table->foreignId('header_group_id')->nullable()->after('shop_ledger_entry_setting_id')->constrained('shop_ledger_header_groups')->nullOnDelete();
            $table->string('header_mode')->default('all_categories')->after('header_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('shop_cashbook_relation_items', function (Blueprint $table): void {
            $table->dropForeign(['header_group_id']);
            $table->dropColumn(['header_group_id', 'header_mode']);
        });
    }
};
