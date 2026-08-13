<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashbook_ledger_clients', function (Blueprint $table): void {
            $table->foreignId('erp_client_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cashbook_ledger_clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('erp_client_id');
        });
    }
};
