<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add public_uuid column as nullable
        Schema::table('suppliers', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->after('id');
        });
        Schema::table('purchaser_cart_items', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->after('id');
        });
        Schema::table('purchaser_correction_requests', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->after('id');
        });

        // Add purchaser_cart_id to purchase_orders and goods_received
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchaser_cart_id')->nullable()->after('supplier_id')->constrained('purchaser_carts')->nullOnDelete();
        });
        Schema::table('goods_received', function (Blueprint $table) {
            $table->foreignId('purchaser_cart_id')->nullable()->after('purchase_order_id')->constrained('purchaser_carts')->nullOnDelete();
        });

        // 2. Populate existing records with UUIDs
        foreach (DB::table('suppliers')->get() as $row) {
            DB::table('suppliers')->where('id', $row->id)->update(['public_uuid' => (string) Str::uuid()]);
        }
        foreach (DB::table('purchaser_cart_items')->get() as $row) {
            DB::table('purchaser_cart_items')->where('id', $row->id)->update(['public_uuid' => (string) Str::uuid()]);
        }
        foreach (DB::table('purchaser_correction_requests')->get() as $row) {
            DB::table('purchaser_correction_requests')->where('id', $row->id)->update(['public_uuid' => (string) Str::uuid()]);
        }

        // 3. Make the columns non-nullable and unique
        Schema::table('suppliers', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable(false)->change();
            $table->unique('public_uuid');
        });
        Schema::table('purchaser_cart_items', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable(false)->change();
            $table->unique('public_uuid');
        });
        Schema::table('purchaser_correction_requests', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable(false)->change();
            $table->unique('public_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('goods_received', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchaser_cart_id');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchaser_cart_id');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
        Schema::table('purchaser_cart_items', function (Blueprint $table) {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
        Schema::table('purchaser_correction_requests', function (Blueprint $table) {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
