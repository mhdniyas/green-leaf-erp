<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('amount')->index();
            $table->foreignId('reversed_by')->nullable()->after('reconciled_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            $table->string('reversal_reason')->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['status', 'reversed_by', 'reversed_at', 'reversal_reason']);
        });
    }
};
