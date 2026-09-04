<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_cashbook_relations', 'settlement_source')) {
                $table->string('settlement_source')->nullable()->after('relation_type');
            } else {
                $table->string('settlement_source')->nullable()->change();
            }
            if (! Schema::hasColumn('shop_cashbook_relations', 'eligibility_rule')) {
                $table->string('eligibility_rule')->nullable()->after('settlement_source');
            } else {
                $table->string('eligibility_rule')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_cashbook_relations', function (Blueprint $table) {
            if (Schema::hasColumn('shop_cashbook_relations', 'eligibility_rule')) {
                $table->dropColumn('eligibility_rule');
            }
            if (Schema::hasColumn('shop_cashbook_relations', 'settlement_source')) {
                $table->dropColumn('settlement_source');
            }
        });
    }
};
