<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invoiceIds = DB::table('purchase_invoices')
            ->where('payment_method', 'Credit')
            ->where('payment_status', 'credit_pending_approval')
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return;
        }

        DB::table('purchase_invoices')
            ->whereIn('id', $invoiceIds)
            ->update([
                'payment_paid_by' => 'vendor_credit',
                'paid_amount' => 0,
                'updated_at' => now(),
            ]);

        DB::table('purchaser_carts')
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->update([
                'payment_status' => 'credit_pending_approval',
                'paid_amount' => 0,
                'payment_made_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('purchaser_credits')
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->where('type', 'out')
            ->delete();
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversible.
    }
};
