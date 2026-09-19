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
        Schema::table('vendor_settlement_allocations', function (Blueprint $table): void {
            $table->boolean('is_reversed')->default(false)->after('total_settled');
            $table->timestamp('reversed_at')->nullable()->after('is_reversed');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversed_by');

            $table->index(['vendor_settlement_id', 'is_reversed'], 'idx_vsa_settle_rev');
            $table->index(['purchase_invoice_id', 'is_reversed'], 'idx_vsa_inv_rev');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_settlement_allocations', function (Blueprint $table): void {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['is_reversed', 'reversed_at', 'reversed_by', 'reversal_reason']);
        });
    }
};
