<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_ledger_header_groups', 'product_tagging_enabled')) {
            Schema::table('shop_ledger_header_groups', function (Blueprint $table) {
                $table->boolean('product_tagging_enabled')->default(false)->after('enabled');
            });
        }

        if (! Schema::hasTable('shop_ledger_header_products')) {
            Schema::create('shop_ledger_header_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('header_group_id')->constrained('shop_ledger_header_groups')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['header_group_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ledger_header_products');

        if (Schema::hasColumn('shop_ledger_header_groups', 'product_tagging_enabled')) {
            Schema::table('shop_ledger_header_groups', function (Blueprint $table) {
                $table->dropColumn('product_tagging_enabled');
            });
        }
    }
};
