<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('payment_paid_by', 30)
                ->default('purchaser')
                ->after('payment_method')
                ->index();
        });

        DB::table('purchase_invoices')
            ->where('payment_method', 'Credit')
            ->where('paid_amount', '<=', 0)
            ->update(['payment_paid_by' => 'vendor_credit']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex(['payment_paid_by']);
            $table->dropColumn('payment_paid_by');
        });
    }
};
