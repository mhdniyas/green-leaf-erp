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
        Schema::table('shop_cashbook_month_config_snapshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_cashbook_month_config_snapshots', 'recalculated_at')) {
                $table->timestamp('recalculated_at')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('shop_cashbook_month_config_snapshots', 'recalculation_summary')) {
                $table->json('recalculation_summary')->nullable()->after('recalculated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_cashbook_month_config_snapshots', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_cashbook_month_config_snapshots', 'recalculation_summary')) {
                $table->dropColumn('recalculation_summary');
            }
            if (Schema::hasColumn('shop_cashbook_month_config_snapshots', 'recalculated_at')) {
                $table->dropColumn('recalculated_at');
            }
        });
    }
};
