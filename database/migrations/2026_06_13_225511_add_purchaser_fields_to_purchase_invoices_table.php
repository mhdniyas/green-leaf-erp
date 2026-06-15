<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('purchaser_cart_id')->nullable()->after('supplier_id')->constrained('purchaser_carts')->nullOnDelete();
            $table->string('payment_method', 100)->nullable()->after('status');
            $table->text('payment_note')->nullable()->after('payment_method');
            $table->foreignId('purchaser_submitted_by')->nullable()->after('payment_note')->constrained('users')->nullOnDelete();
            $table->timestamp('purchaser_submitted_at')->nullable()->after('purchaser_submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchaser_cart_id');
            $table->dropConstrainedForeignId('purchaser_submitted_by');
            $table->dropColumn(['payment_method', 'payment_note', 'purchaser_submitted_at']);
        });
    }
};
