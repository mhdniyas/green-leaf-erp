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
            $table->uuid('batch_uuid')->nullable()->after('reconciled_by');
            $table->index('batch_uuid', 'spl_alloc_batch_uuid_index');
        });
    }

    public function down(): void
    {
        Schema::table('shop_payment_ledger_allocations', function (Blueprint $table) {
            $table->dropIndex('spl_alloc_batch_uuid_index');
            $table->dropColumn('batch_uuid');
        });
    }
};
