<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Seed representative purchase orders for the June 10 purchasing day.
     */
    public function run(): void
    {
        $suppliers = Supplier::query()->whereIn('name', ['Market A', 'Market B', 'Market C'])->get()->keyBy('name');
        $products = Product::query()->whereIn('sku', ['1', '3', '5', '12', '23', '33'])->get()->keyBy('sku');
        $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();

        $orders = [
            [
                'po_number' => 'PO-20260610-0001',
                'supplier' => 'Market A',
                'status' => POStatus::Received,
                'notes' => 'Morning vegetable purchase from Koyambedu Wholesale Market.',
                'items' => [
                    ['sku' => '1', 'quantity' => 180, 'unit_price' => 32.50],
                    ['sku' => '3', 'quantity' => 220, 'unit_price' => 28.00],
                ],
            ],
            [
                'po_number' => 'PO-20260610-0002',
                'supplier' => 'Market B',
                'status' => POStatus::PartiallyReceived,
                'notes' => 'Fresh produce order on one-day credit terms.',
                'items' => [
                    ['sku' => '5', 'quantity' => 150, 'unit_price' => 30.00],
                    ['sku' => '12', 'quantity' => 90, 'unit_price' => 36.00],
                ],
            ],
            [
                'po_number' => 'PO-20260610-0003',
                'supplier' => 'Market C',
                'status' => POStatus::Approved,
                'notes' => 'Approved afternoon top-up purchase.',
                'items' => [
                    ['sku' => '23', 'quantity' => 45, 'unit_price' => 52.00],
                    ['sku' => '33', 'quantity' => 35, 'unit_price' => 68.00],
                ],
            ],
        ];

        foreach ($orders as $orderData) {
            $order = PurchaseOrder::query()->updateOrCreate(
                ['po_number' => $orderData['po_number']],
                [
                    'supplier_id' => $suppliers[$orderData['supplier']]->id,
                    'status' => $orderData['status'],
                    'fulfillment_type' => 'warehouse',
                    'order_date' => '2026-06-10',
                    'created_by' => $purchaseManager->id,
                    'notes' => $orderData['notes'],
                ]
            );

            $order->items()->delete();

            foreach ($orderData['items'] as $itemData) {
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $products[$itemData['sku']]->id,
                    'purchase_unit' => 'kg',
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'price_basis' => 'per_kg',
                ]);
            }
        }
    }
}
