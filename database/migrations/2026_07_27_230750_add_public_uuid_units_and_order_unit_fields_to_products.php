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
        Schema::table('products', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->after('id');
        });

        DB::table('products')
            ->whereNull('public_uuid')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['public_uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->unique('public_uuid');
        });

        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit', 20);
            $table->string('label', 50);
            $table->decimal('conversion_to_base', 12, 4)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_orderable')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'unit']);
            $table->index(['product_id', 'is_orderable']);
        });

        DB::table('products')
            ->orderBy('id')
            ->select(['id', 'unit'])
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    DB::table('product_units')->insert([
                        'product_id' => $product->id,
                        'unit' => $product->unit ?: 'kg',
                        'label' => strtoupper((string) ($product->unit ?: 'kg')),
                        'conversion_to_base' => 1,
                        'is_base' => true,
                        'is_orderable' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->string('requested_unit', 20)->nullable()->after('unit');
            $table->decimal('requested_unit_quantity', 10, 2)->nullable()->after('requested_unit');
            $table->decimal('requested_unit_conversion_to_base', 12, 4)->nullable()->after('requested_unit_quantity');
        });

        DB::table('shop_order_items')
            ->orderBy('id')
            ->select(['id', 'requested_qty', 'unit'])
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    DB::table('shop_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'requested_unit' => $item->unit,
                            'requested_unit_quantity' => $item->requested_qty,
                            'requested_unit_conversion_to_base' => 1,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'requested_unit',
                'requested_unit_quantity',
                'requested_unit_conversion_to_base',
            ]);
        });

        Schema::dropIfExists('product_units');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
