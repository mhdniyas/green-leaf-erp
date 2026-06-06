<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('base_price', 10, 2)->default(0)->after('description');
        });

        DB::table('products')
            ->where('unit', 'kg')
            ->update(['base_price' => 40.00]);

        DB::table('products')
            ->where('unit', 'box')
            ->update(['base_price' => 120.00]);

        DB::table('products')
            ->where('unit', 'pcs')
            ->update(['base_price' => 18.00]);

        DB::table('products')
            ->where('unit', 'bag')
            ->update(['base_price' => 260.00]);

        DB::table('products')
            ->where('unit', 'roll')
            ->update(['base_price' => 32.00]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('base_price');
        });
    }
};
