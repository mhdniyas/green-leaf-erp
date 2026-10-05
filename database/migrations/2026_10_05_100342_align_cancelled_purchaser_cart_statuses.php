<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('purchaser_carts')
            ->where('status', 'submitted')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('purchase_invoices')
                    ->whereColumn('purchase_invoices.purchaser_cart_id', 'purchaser_carts.id')
                    ->where('purchase_invoices.status', 'cancelled');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('purchase_invoices')
                    ->whereColumn('purchase_invoices.purchaser_cart_id', 'purchaser_carts.id')
                    ->where('purchase_invoices.status', '!=', 'cancelled');
            })
            ->update([
                'status' => 'cancelled',
                'bill_number' => null,
                'payment_status' => 'unpaid',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve status integrity
    }
};
