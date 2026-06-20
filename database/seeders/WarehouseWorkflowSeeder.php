<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = Carbon::today();

            $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();
            $warehouseManager = User::query()->where('email', 'warehouse@greenleaf.com')->firstOrFail();

            $marketA = Supplier::query()->where('name', 'Market A')->firstOrFail();
            $marketB = Supplier::query()->where('name', 'Market B')->firstOrFail();

            $shops = Shop::query()
                ->whereIn('code', ['SHOP_CASIO', 'SHOP_BUDEGERE', 'SHOP_GRANCITY', 'SHOP_ASHIRWAD'])
                ->get()
                ->keyBy('code');

            $products = Product::query()
                ->whereIn('sku', [
                    '1',
                    '2',
                    '3',
                    '5',
                    '101',
                    '126',
                ])
                ->get()
                ->keyBy('sku');

            $this->seedClosedDay(
                businessDate: $today->copy()->subDays(2),
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketA,
                shop: $shops['SHOP_CASIO'],
                shopOwner: $purchaseManager,
                products: $products,
                orderNumber: 'RQ-DEMO-D2-CASIO',
                poNumber: 'PO-DEMO-D2-A',
                grnNumber: 'GRN-DEMO-D2-A',
                invoiceNumber: 'PINV-DEMO-D2-A',
                invoiceStatus: InvoiceStatus::Paid,
                orderItems: [
                    ['sku' => '1', 'requested' => 24, 'approved' => 24, 'delivered' => 24, 'sorting_status' => 'loaded', 'unit_cost' => 28.00],
                    ['sku' => '3', 'requested' => 18, 'approved' => 18, 'delivered' => 18, 'sorting_status' => 'loaded', 'unit_cost' => 24.00],
                    ['sku' => '101', 'requested' => 8, 'approved' => 8, 'delivered' => 8, 'sorting_status' => 'loaded', 'unit_cost' => 19.50],
                ],
            );

            $this->seedClosedDay(
                businessDate: $today->copy()->subDay(),
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketB,
                shop: $shops['SHOP_BUDEGERE'],
                shopOwner: $purchaseManager,
                products: $products,
                orderNumber: 'RQ-DEMO-D1-BUD',
                poNumber: 'PO-DEMO-D1-B',
                grnNumber: 'GRN-DEMO-D1-B',
                invoiceNumber: 'PINV-DEMO-D1-B',
                invoiceStatus: InvoiceStatus::Approved,
                orderItems: [
                    ['sku' => '5', 'requested' => 16, 'approved' => 16, 'delivered' => 15, 'shortage' => 1, 'sorting_status' => 'loaded', 'unit_cost' => 62.50],
                    ['sku' => '2', 'requested' => 20, 'approved' => 20, 'delivered' => 20, 'sorting_status' => 'loaded', 'unit_cost' => 31.00],
                    ['sku' => '126', 'requested' => 4, 'approved' => 4, 'delivered' => 4, 'sorting_status' => 'loaded', 'unit_cost' => 120.00],
                ],
            );

            $this->seedApprovedOnlyDay(
                businessDate: $today,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketA,
                shop: $shops['SHOP_GRANCITY'],
                shopOwner: $purchaseManager,
                products: $products,
                orderNumber: 'RQ-DEMO-TODAY-GRAND',
                poNumber: 'PO-DEMO-TODAY-A',
                grnNumber: 'GRN-DEMO-TODAY-A',
                orderItems: [
                    ['sku' => '1', 'requested' => 18, 'approved' => 18, 'sorting_status' => 'allocated', 'unit_cost' => 28.00],
                    ['sku' => '3', 'requested' => 10, 'approved' => 10, 'sorting_status' => 'allocated', 'unit_cost' => 24.00],
                ],
            );

            $this->seedPendingReceiptDay(
                businessDate: $today,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketB,
                shop: $shops['SHOP_ASHIRWAD'],
                shopOwner: $purchaseManager,
                products: $products,
                orderNumber: 'RQ-DEMO-TODAY-ASH',
                poNumber: 'PO-DEMO-TODAY-B',
                grnNumber: 'GRN-DEMO-TODAY-B',
                invoiceNumber: 'PINV-DEMO-TODAY-B',
                orderItems: [
                    ['sku' => '5', 'requested' => 22, 'approved' => 22, 'sorting_status' => 'pending', 'unit_cost' => 62.50],
                    ['sku' => '101', 'requested' => 11, 'approved' => 11, 'sorting_status' => 'pending', 'unit_cost' => 19.50],
                ],
            );

            $this->seedTodayOperationalQueue(
                businessDate: $today,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketB,
                shops: $shops,
                products: $products,
            );

            $purchaseManager->notifications()->delete();
        });

        $this->command?->info('One-week workflow seed complete for shop, purchase, warehouse, and admin review.');
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $orderItems
     */
    private function seedClosedDay(
        Carbon $businessDate,
        User $purchaseManager,
        User $warehouseManager,
        Supplier $supplier,
        Shop $shop,
        User $shopOwner,
        Collection $products,
        string $orderNumber,
        string $poNumber,
        string $grnNumber,
        string $invoiceNumber,
        InvoiceStatus $invoiceStatus,
        array $orderItems,
    ): void {
        $order = $this->upsertShopOrder(
            shop: $shop,
            shopOwner: $shopOwner,
            orderNumber: $orderNumber,
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'delivery_status' => 'delivered',
                'is_allocation_completed' => true,
                'is_delivered' => true,
                'delivered_at' => $businessDate->copy()->setTime(13, 30),
                'delivered_by' => $warehouseManager->id,
                'cash_collected' => 0,
                'cash_discrepancy' => 0,
                'balance_amount' => 0,
                'total_shortage_value' => collect($orderItems)->sum(fn (array $item): float => ((float) ($item['shortage'] ?? 0)) * (float) ($item['unit_cost'] ?? 0)),
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 45),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($order, $orderItems, $products, $warehouseManager);

        $po = $this->upsertPurchaseOrder(
            purchaseManager: $purchaseManager,
            supplier: $supplier,
            poNumber: $poNumber,
            businessDate: $businessDate,
            status: POStatus::Received,
            items: $this->buildPurchaseOrderItems($orderItems),
            products: $products,
        );

        $grn = $this->upsertGoodsReceived(
            purchaseOrder: $po,
            warehouseManager: $warehouseManager,
            grnNumber: $grnNumber,
            businessDate: $businessDate,
            status: 'approved',
            items: $this->buildGoodsReceivedItems($orderItems),
            products: $products,
        );

        PurchaseInvoice::updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'goods_received_id' => $grn->id,
                'supplier_id' => $supplier->id,
                'amount' => (float) $po->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->quantity * (float) $item->unit_price),
                'status' => $invoiceStatus,
                'notes' => 'Seeded weekly workflow invoice.',
            ]
        );

        foreach ($po->items as $purchaseOrderItem) {
            StockBatch::updateOrCreate(
                ['reference' => sprintf('BATCH-%s-%s', $businessDate->format('Ymd'), $purchaseOrderItem->product_id)],
                [
                    'product_id' => $purchaseOrderItem->product_id,
                    'created_by' => $warehouseManager->id,
                    'received_at' => $businessDate->toDateString(),
                    'total_kg' => $purchaseOrderItem->quantity,
                    'cost_per_kg' => $purchaseOrderItem->unit_price,
                    'transport_cost' => 0,
                    'labour_cost' => 0,
                    'status' => BatchStatus::Pending,
                    'notes' => 'Seeded from approved GRN.',
                ]
            );
        }
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $orderItems
     */
    private function seedApprovedOnlyDay(
        Carbon $businessDate,
        User $purchaseManager,
        User $warehouseManager,
        Supplier $supplier,
        Shop $shop,
        User $shopOwner,
        Collection $products,
        string $orderNumber,
        string $poNumber,
        string $grnNumber,
        array $orderItems,
    ): void {
        $order = $this->upsertShopOrder(
            shop: $shop,
            shopOwner: $shopOwner,
            orderNumber: $orderNumber,
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'delivery_status' => 'pending_delivery',
                'is_allocation_completed' => false,
                'is_delivered' => false,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 30),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($order, $orderItems, $products, $warehouseManager);

        $po = $this->upsertPurchaseOrder(
            purchaseManager: $purchaseManager,
            supplier: $supplier,
            poNumber: $poNumber,
            businessDate: $businessDate,
            status: POStatus::Received,
            items: $this->buildPurchaseOrderItems($orderItems),
            products: $products,
        );

        $this->upsertGoodsReceived(
            purchaseOrder: $po,
            warehouseManager: $warehouseManager,
            grnNumber: $grnNumber,
            businessDate: $businessDate,
            status: 'approved',
            items: $this->buildGoodsReceivedItems($orderItems),
            products: $products,
        );
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $orderItems
     */
    private function seedPendingReceiptDay(
        Carbon $businessDate,
        User $purchaseManager,
        User $warehouseManager,
        Supplier $supplier,
        Shop $shop,
        User $shopOwner,
        Collection $products,
        string $orderNumber,
        string $poNumber,
        string $grnNumber,
        string $invoiceNumber,
        array $orderItems,
    ): void {
        $order = $this->upsertShopOrder(
            shop: $shop,
            shopOwner: $shopOwner,
            orderNumber: $orderNumber,
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'delivery_status' => 'pending_delivery',
                'is_allocation_completed' => false,
                'is_delivered' => false,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 40),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($order, $orderItems, $products, $warehouseManager);

        $po = $this->upsertPurchaseOrder(
            purchaseManager: $purchaseManager,
            supplier: $supplier,
            poNumber: $poNumber,
            businessDate: $businessDate,
            status: POStatus::PartiallyReceived,
            items: $this->buildPurchaseOrderItems($orderItems),
            products: $products,
        );

        $grn = $this->upsertGoodsReceived(
            purchaseOrder: $po,
            warehouseManager: $warehouseManager,
            grnNumber: $grnNumber,
            businessDate: $businessDate,
            status: 'pending_approval',
            items: $this->buildGoodsReceivedItems($orderItems, -1.0),
            products: $products,
        );

        PurchaseInvoice::updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'goods_received_id' => $grn->id,
                'supplier_id' => $supplier->id,
                'amount' => (float) $po->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->quantity * (float) $item->unit_price),
                'status' => InvoiceStatus::Pending,
                'notes' => 'Pending until GRN approval.',
            ]
        );
    }

    private function seedTodayOperationalQueue(
        Carbon $businessDate,
        User $purchaseManager,
        User $warehouseManager,
        Supplier $supplier,
        Collection $shops,
        Collection $products,
    ): void {
        $casioOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_CASIO'],
            shopOwner: $purchaseManager,
            orderNumber: 'RQ-WEEK-05-CASIO',
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'delivery_status' => 'partially_delivered',
                'is_allocation_completed' => true,
                'is_delivered' => true,
                'delivered_at' => $businessDate->copy()->setTime(11, 45),
                'delivered_by' => $warehouseManager->id,
                'total_shortage_value' => 62.50,
                'cash_discrepancy' => 62.50,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 45),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($casioOrder, [
            ['sku' => '5', 'requested' => 10, 'approved' => 10, 'delivered' => 9, 'shortage' => 1, 'sorting_status' => 'loaded', 'unit_cost' => 62.50],
            ['sku' => '1', 'requested' => 20, 'approved' => 20, 'delivered' => 20, 'sorting_status' => 'loaded', 'unit_cost' => 28.00],
        ], $products, $warehouseManager);

        $budegereOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_BUDEGERE'],
            shopOwner: $purchaseManager,
            orderNumber: 'RQ-WEEK-05-BUD',
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'delivery_status' => 'pending_delivery',
                'is_allocation_completed' => true,
                'is_delivered' => false,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 30),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($budegereOrder, [
            ['sku' => '3', 'requested' => 16, 'approved' => 16, 'sorting_status' => 'loaded', 'unit_cost' => 24.00],
            ['sku' => '101', 'requested' => 7, 'approved' => 7, 'sorting_status' => 'loaded', 'unit_cost' => 19.50],
        ], $products, $warehouseManager);

        $grancityOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_GRANCITY'],
            shopOwner: $purchaseManager,
            orderNumber: 'RQ-WEEK-05-GRAND',
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'delivery_status' => 'pending_delivery',
                'is_allocation_completed' => false,
                'is_delivered' => false,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 20),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($grancityOrder, [
            ['sku' => '2', 'requested' => 14, 'approved' => 14, 'sorting_status' => 'allocated', 'unit_cost' => 31.00],
            ['sku' => '126', 'requested' => 5, 'approved' => 5, 'sorting_status' => 'pending', 'unit_cost' => 120.00],
        ], $products, $warehouseManager);

        $po = $this->upsertPurchaseOrder(
            purchaseManager: $purchaseManager,
            supplier: $supplier,
            poNumber: 'PO-WEEK-05',
            businessDate: $businessDate,
            status: POStatus::PartiallyReceived,
            items: [
                ['sku' => '5', 'quantity' => 30, 'unit_price' => 62.50, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => '3', 'quantity' => 20, 'unit_price' => 24.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => '2', 'quantity' => 14, 'unit_price' => 31.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
            ],
            products: $products,
        );

        $grn = $this->upsertGoodsReceived(
            purchaseOrder: $po,
            warehouseManager: $warehouseManager,
            grnNumber: 'GRN-WEEK-05',
            businessDate: $businessDate,
            status: 'pending_approval',
            items: [
                ['sku' => '5', 'received_qty' => 29.0, 'variance' => -1.0],
                ['sku' => '3', 'received_qty' => 20.0, 'variance' => 0.0],
                ['sku' => '2', 'received_qty' => 14.0, 'variance' => 0.0],
            ],
            products: $products,
        );

        PurchaseInvoice::updateOrCreate(
            ['invoice_number' => 'PINV-WEEK-05'],
            [
                'goods_received_id' => $grn->id,
                'supplier_id' => $supplier->id,
                'amount' => (float) $po->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->quantity * (float) $item->unit_price),
                'status' => InvoiceStatus::Pending,
                'notes' => 'Supplier invoice expected after goods receipt.',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertShopOrder(
        Shop $shop,
        User $shopOwner,
        string $orderNumber,
        Carbon $businessDate,
        array $attributes,
    ): ShopOrder {
        return ShopOrder::updateOrCreate(
            ['order_number' => $orderNumber],
            array_merge([
                'shop_id' => $shop->id,
                'business_date' => $businessDate->toDateString(),
                'created_by' => $shopOwner->id,
            ], $attributes)
        );
    }

    /**
     * @param  array<int, array<string, int|float|string|null>>  $items
     */
    private function syncShopOrderItems(
        ShopOrder $order,
        array $items,
        Collection $products,
        User $warehouseManager,
        bool $includeApprovals = true,
    ): void {
        $productIds = collect($items)
            ->map(fn (array $item): ?int => $products->get($item['sku'])?->id)
            ->filter()
            ->values()
            ->all();

        $order->items()->whereNotIn('product_id', $productIds)->delete();

        foreach ($items as $item) {
            $product = $products->get($item['sku']);

            if (! $product) {
                continue;
            }

            $sortingStatus = (string) ($item['sorting_status'] ?? 'pending');
            $approvedQty = $includeApprovals ? (float) ($item['approved'] ?? $item['requested']) : null;
            $deliveredQty = array_key_exists('delivered', $item) ? (float) $item['delivered'] : null;
            $shortageQty = (float) ($item['shortage'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            ShopOrderItem::updateOrCreate(
                [
                    'shop_order_id' => $order->id,
                    'product_id' => $product->id,
                ],
                [
                    'requested_qty' => (float) $item['requested'],
                    'approved_qty' => $approvedQty,
                    'unit' => $product->unit,
                    'notes' => 'Seeded weekly workflow line.',
                    'fulfillment_type' => 'warehouse',
                    'is_sorted' => $sortingStatus !== 'pending',
                    'sorted_at' => $sortingStatus !== 'pending' ? now()->subHour() : null,
                    'sorted_by' => $sortingStatus !== 'pending' ? $warehouseManager->id : null,
                    'sorting_status' => $sortingStatus,
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'unit_cost' => $unitCost,
                    'shortage_value' => $shortageQty * $unitCost,
                ]
            );
        }
    }

    /**
     * @param  array<int, array{sku: string, quantity: int|float, unit_price: float, purchase_unit: string, price_basis: string}>  $items
     */
    private function upsertPurchaseOrder(
        User $purchaseManager,
        Supplier $supplier,
        string $poNumber,
        Carbon $businessDate,
        POStatus $status,
        array $items,
        Collection $products,
        string $notes = 'Seeded weekly workflow purchase order.',
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::updateOrCreate(
            ['po_number' => $poNumber],
            [
                'supplier_id' => $supplier->id,
                'status' => $status,
                'order_date' => $businessDate->toDateString(),
                'created_by' => $purchaseManager->id,
                'fulfillment_type' => 'warehouse',
                'notes' => $notes,
            ]
        );

        $productIds = collect($items)
            ->map(fn (array $item): ?int => $products->get($item['sku'])?->id)
            ->filter()
            ->values()
            ->all();

        $purchaseOrder->items()->whereNotIn('product_id', $productIds)->delete();

        foreach ($items as $item) {
            $product = $products->get($item['sku']);

            if (! $product) {
                continue;
            }

            PurchaseOrderItem::updateOrCreate(
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                ],
                [
                    'purchase_unit' => $item['purchase_unit'],
                    'packet_qty' => $item['purchase_unit'] === 'box' ? (float) $item['quantity'] : null,
                    'weight_per_packet' => $item['purchase_unit'] === 'box' ? 1.0 : null,
                    'actual_weight' => null,
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'price_basis' => $item['price_basis'],
                ]
            );
        }

        return $purchaseOrder->load('items');
    }

    /**
     * @param  array<int, array{sku: string, received_qty: float, variance: float}>  $items
     */
    private function upsertGoodsReceived(
        PurchaseOrder $purchaseOrder,
        User $warehouseManager,
        string $grnNumber,
        Carbon $businessDate,
        string $status,
        array $items,
        Collection $products,
    ): GoodsReceived {
        $goodsReceived = GoodsReceived::updateOrCreate(
            ['grn_number' => $grnNumber],
            [
                'purchase_order_id' => $purchaseOrder->id,
                'status' => $status,
                'received_by' => $warehouseManager->id,
                'received_at' => $businessDate->toDateString(),
                'transport_cost' => 180.00,
                'labour_cost' => 75.00,
                'notes' => 'Seeded weekly workflow GRN.',
            ]
        );

        $purchaseOrderItems = $purchaseOrder->items()->get()->keyBy('product_id');
        $productIds = collect($items)
            ->map(fn (array $item): ?int => $products->get($item['sku'])?->id)
            ->filter()
            ->values()
            ->all();

        $goodsReceived->items()->whereNotIn('product_id', $productIds)->delete();

        foreach ($items as $item) {
            $product = $products->get($item['sku']);

            if (! $product || ! $purchaseOrderItems->has($product->id)) {
                continue;
            }

            $goodsReceived->items()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'purchase_order_item_id' => $purchaseOrderItems[$product->id]->id,
                    'received_unit' => $purchaseOrderItems[$product->id]->purchase_unit,
                    'received_packet_qty' => $purchaseOrderItems[$product->id]->purchase_unit === 'box' ? (float) $item['received_qty'] : null,
                    'received_weight_per_packet' => $purchaseOrderItems[$product->id]->purchase_unit === 'box' ? 1.0 : null,
                    'received_qty' => $item['received_qty'],
                    'variance' => $item['variance'],
                ]
            );
        }

        return $goodsReceived->load('items');
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $orderItems
     * @return array<int, array{sku: string, quantity: float, unit_price: float, purchase_unit: string, price_basis: string}>
     */
    private function buildPurchaseOrderItems(array $orderItems): array
    {
        return collect($orderItems)
            ->map(fn (array $item): array => [
                'sku' => (string) $item['sku'],
                'quantity' => (float) ($item['approved'] ?? $item['requested']),
                'unit_price' => (float) ($item['unit_cost'] ?? 0),
                'purchase_unit' => str_contains((string) $item['sku'], 'BOX') ? 'box' : 'kg',
                'price_basis' => str_contains((string) $item['sku'], 'BOX') ? 'per_unit' : 'per_kg',
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $orderItems
     * @return array<int, array{sku: string, received_qty: float, variance: float}>
     */
    private function buildGoodsReceivedItems(array $orderItems, float $varianceOffset = 0.0): array
    {
        return collect($orderItems)
            ->map(function (array $item) use ($varianceOffset): array {
                $approved = (float) ($item['approved'] ?? $item['requested']);
                $received = max(0.0, $approved + $varianceOffset);

                if (array_key_exists('delivered', $item)) {
                    $received = (float) ($item['delivered'] ?? $approved);
                }

                return [
                    'sku' => (string) $item['sku'],
                    'received_qty' => $received,
                    'variance' => $received - $approved,
                ];
            })
            ->all();
    }
}
