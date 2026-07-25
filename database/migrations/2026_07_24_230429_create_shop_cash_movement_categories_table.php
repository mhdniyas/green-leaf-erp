<?php

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
        Schema::create('shop_cash_movement_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $categoryId = DB::table('shop_cash_movement_categories')->insertGetId([
            'name' => 'Petty Cash',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('shop_credits', function (Blueprint $table): void {
            $table->foreignId('shop_cash_movement_category_id')
                ->nullable()
                ->after('is_petty_cash')
                ->constrained('shop_cash_movement_categories', indexName: 'shop_credits_cash_movement_category_fk')
                ->nullOnDelete();
            $table->index(['shop_cash_movement_category_id', 'business_date'], 'shop_credits_cash_category_date_index');
        });

        DB::table('shop_credits')
            ->where('is_petty_cash', true)
            ->whereNull('shop_cash_movement_category_id')
            ->update(['shop_cash_movement_category_id' => $categoryId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_credits', function (Blueprint $table): void {
            $table->dropForeign('shop_credits_cash_movement_category_fk');
            $table->dropIndex('shop_credits_cash_category_date_index');
            $table->dropColumn('shop_cash_movement_category_id');
        });

        Schema::dropIfExists('shop_cash_movement_categories');
    }
};
