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
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->decimal('opening_recovery_amount', 12, 2)->nullable()->after('final_amount');
            $table->decimal('closing_recovery_amount', 12, 2)->nullable()->after('opening_recovery_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->dropColumn([
                'opening_recovery_amount',
                'closing_recovery_amount',
            ]);
        });
    }
};
