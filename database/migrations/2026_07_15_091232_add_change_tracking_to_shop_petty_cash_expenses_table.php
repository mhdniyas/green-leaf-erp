<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_petty_cash_expenses', function (Blueprint $table): void {
            $table->decimal('previous_amount', 12, 2)->nullable()->after('amount');
            $table->foreignId('amount_changed_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('amount_changed_at')->nullable()->after('amount_changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('shop_petty_cash_expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('amount_changed_by');
            $table->dropColumn(['previous_amount', 'amount_changed_at']);
        });
    }
};
