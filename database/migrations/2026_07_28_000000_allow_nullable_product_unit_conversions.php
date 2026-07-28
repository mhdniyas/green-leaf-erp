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
        Schema::table('product_units', function (Blueprint $table): void {
            $table->decimal('conversion_to_base', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('product_units')
            ->whereNull('conversion_to_base')
            ->update(['conversion_to_base' => 1]);

        Schema::table('product_units', function (Blueprint $table): void {
            $table->decimal('conversion_to_base', 12, 4)->default(1)->nullable(false)->change();
        });
    }
};
