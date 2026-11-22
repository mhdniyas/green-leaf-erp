<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_credit_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_credit_notes', 'allowed_negative_stock')) {
                $table->boolean('allowed_negative_stock')->default(false)->after('deduct_stock_for_damage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_credit_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_credit_notes', 'allowed_negative_stock')) {
                $table->dropColumn('allowed_negative_stock');
            }
        });
    }
};
