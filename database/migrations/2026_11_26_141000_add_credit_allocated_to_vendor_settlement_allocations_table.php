<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_settlement_allocations', function (Blueprint $table): void {
            $table->decimal('credit_allocated', 14, 2)->default(0.00)->after('advance_allocated');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_settlement_allocations', function (Blueprint $table): void {
            $table->dropColumn('credit_allocated');
        });
    }
};
