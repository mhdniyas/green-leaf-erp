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
            $table->foreignId('source_settlement_id')->nullable()->after('header_mode')->constrained('shop_cashbook_relations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_cashbook_relation_items', function (Blueprint $table): void {
            $table->dropForeign(['source_settlement_id']);
            $table->dropColumn('source_settlement_id');
        });
    }
};
