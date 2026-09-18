<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_purchaser_allotments', function (Blueprint $table): void {
            $table->unique(['product_id', 'effective_from'], 'uniq_prod_purchaser_allotment_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_purchaser_allotments', function (Blueprint $table): void {
            $table->dropUnique('uniq_prod_purchaser_allotment_start');
        });
    }
};
