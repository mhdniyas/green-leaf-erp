<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_invoices', function (Blueprint $table): void {
            $table->text('discount_note')->nullable()->after('payment_note');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_note')->constrained('users')->nullOnDelete();
            $table->timestamp('discount_approved_at')->nullable()->after('discount_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('shop_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn(['discount_note', 'discount_approved_at']);
        });
    }
};
