<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_accounting_entry_lines', 'is_loan_entry')) {
            return;
        }

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->boolean('is_loan_entry')->default(false)->after('loan_cashbook_offset_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shop_accounting_entry_lines', 'is_loan_entry')) {
            return;
        }

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->dropColumn('is_loan_entry');
        });
    }
};
