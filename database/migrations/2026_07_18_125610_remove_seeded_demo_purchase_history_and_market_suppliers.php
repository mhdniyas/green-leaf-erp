<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $demoCartNumbers = [
            'VC-DEMO-DRAFT-001',
            'VC-20260624-92E5',
            'VC-20260627-59C5',
        ];

        $demoPurchaseOrderNumbers = [
            'PO-DEMO-TODAY-B',
            'PO-WEEK-05',
            'PO-DEMO-STANDALONE-001',
            'PO-DEMO-STANDALONE-002',
            'PO-DEMO-STANDALONE-003',
        ];

        $seededSupplierNames = [
            'Market A',
            'Market B',
            'Market C',
            'Market D',
            'Market E',
        ];

        DB::table('purchaser_carts')
            ->whereIn('cart_number', $demoCartNumbers)
            ->where('status', 'cancelled')
            ->delete();

        DB::table('purchase_orders')
            ->whereIn('po_number', $demoPurchaseOrderNumbers)
            ->where('status', 'cancelled')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('suppliers')
            ->whereIn('name', $seededSupplierNames)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $seededSupplierNames = [
            'Market A',
            'Market B',
            'Market C',
            'Market D',
            'Market E',
        ];

        DB::table('suppliers')
            ->whereIn('name', $seededSupplierNames)
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
    }
};
