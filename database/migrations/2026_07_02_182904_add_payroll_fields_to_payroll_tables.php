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
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')->nullable()->after('finalized_by')->constrained()->nullOnDelete();
        });

        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->decimal('unpaid_leave_days', 8, 2)->default(0)->after('paid_leave_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->dropColumn('unpaid_leave_days');
        });

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
