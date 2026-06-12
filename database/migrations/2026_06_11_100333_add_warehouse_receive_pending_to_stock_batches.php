<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->boolean('warehouse_receive_pending')->default(true)->after('status');
            $table->timestamp('warehouse_confirmed_at')->nullable()->after('warehouse_receive_pending');
            $table->foreignId('warehouse_confirmed_by')->nullable()->constrained('users')->nullOnDelete()->after('warehouse_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_confirmed_by');
            $table->dropColumn(['warehouse_receive_pending', 'warehouse_confirmed_at']);
        });
    }
};
