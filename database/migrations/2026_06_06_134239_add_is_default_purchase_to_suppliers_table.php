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
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('is_default_purchase')->default(false)->after('category')->index();
        });

        $defaultSupplierId = DB::table('suppliers')
            ->where('category', 'own_purchase')
            ->orderBy('id')
            ->value('id');

        if ($defaultSupplierId !== null) {
            DB::table('suppliers')
                ->where('id', $defaultSupplierId)
                ->update(['is_default_purchase' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('is_default_purchase');
        });
    }
};
