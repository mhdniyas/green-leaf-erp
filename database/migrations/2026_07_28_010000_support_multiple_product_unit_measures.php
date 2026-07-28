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
        Schema::table('product_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_units', 'public_uuid')) {
                $table->uuid('public_uuid')->nullable()->after('id');
            }
        });

        DB::table('product_units')
            ->whereNull('public_uuid')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($units): void {
                foreach ($units as $unit) {
                    DB::table('product_units')
                        ->where('id', $unit->id)
                        ->update(['public_uuid' => (string) Str::uuid()]);
                }
            });

        Schema::table('product_units', function (Blueprint $table): void {
            $table->unique('public_uuid');
        });

        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropUnique('product_units_product_id_unit_unique');
            $table->unique(['product_id', 'label'], 'product_units_product_label_unique');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->foreignId('requested_product_unit_id')
                ->nullable()
                ->after('unit')
                ->constrained('product_units')
                ->nullOnDelete();
            $table->string('requested_unit_label', 80)
                ->nullable()
                ->after('requested_unit');
        });

        DB::table('shop_order_items')
            ->whereNull('requested_unit_label')
            ->orderBy('id')
            ->select(['id', 'requested_unit', 'unit'])
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    DB::table('shop_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'requested_unit_label' => strtoupper((string) ($item->requested_unit ?: $item->unit ?: '')),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requested_product_unit_id');
            $table->dropColumn('requested_unit_label');
        });

        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropUnique('product_units_product_label_unique');
            $table->unique(['product_id', 'unit']);
        });

        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
