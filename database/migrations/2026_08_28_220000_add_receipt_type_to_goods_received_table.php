<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->string('receipt_type', 32)->nullable()->after('bill_status')->index();
        });

        // 1. Backfill clear warehouse advance records
        DB::table('goods_received')
            ->whereNull('purchase_order_id')
            ->whereNull('purchaser_cart_id')
            ->where('bill_status', 'bill_pending')
            ->update(['receipt_type' => 'warehouse_advance']);

        // 2. Backfill clear normal purchase / commercial records
        DB::table('goods_received')
            ->where(function ($query): void {
                $query->whereNotNull('purchase_order_id')
                    ->orWhereNotNull('purchaser_cart_id');
            })
            ->update(['receipt_type' => 'normal_purchase']);

        // Any remaining rows remain null and can be audited.
    }

    public function down(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropIndex(['receipt_type']);
            $table->dropColumn('receipt_type');
        });
    }
};
