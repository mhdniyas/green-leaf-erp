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
        Schema::table('daily_price_approvals', function (Blueprint $table): void {
            $table->string('price_unit', 20)
                ->nullable()
                ->after('purchase_price');
        });

        DB::table('daily_price_approvals')
            ->orderBy('id')
            ->chunkById(200, function ($approvals): void {
                foreach ($approvals as $approval) {
                    $productUnit = DB::table('products')
                        ->where('id', $approval->product_id)
                        ->value('unit');

                    DB::table('daily_price_approvals')
                        ->where('id', $approval->id)
                        ->update(['price_unit' => $productUnit ?: 'kg']);
                }
            });

        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->string('price_unit', 20)
                ->nullable()
                ->after('unit');
            $table->decimal('price_quantity', 12, 4)
                ->default(0.0000)
                ->after('approved_qty');
            $table->decimal('delivered_price_quantity', 12, 4)
                ->default(0.0000)
                ->after('delivered_qty');
            $table->decimal('shortage_price_quantity', 12, 4)
                ->default(0.0000)
                ->after('shortage_qty');
            $table->decimal('excess_price_quantity', 12, 4)
                ->default(0.0000)
                ->after('excess_qty');
        });
    }

    public function down(): void
    {
        Schema::table('shop_invoice_items', function (Blueprint $table): void {
            $table->dropColumn([
                'price_unit',
                'price_quantity',
                'delivered_price_quantity',
                'shortage_price_quantity',
                'excess_price_quantity',
            ]);
        });

        Schema::table('daily_price_approvals', function (Blueprint $table): void {
            $table->dropColumn('price_unit');
        });
    }
};
