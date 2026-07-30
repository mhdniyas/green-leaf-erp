<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_accounting_entry_lines', 'loan_cashbook_offset_enabled')) {
            return;
        }

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->boolean('loan_cashbook_offset_enabled')->default(false)->after('cash_effect');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shop_accounting_entry_lines', 'loan_cashbook_offset_enabled')) {
            return;
        }

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->dropColumn('loan_cashbook_offset_enabled');
        });
    }
};
