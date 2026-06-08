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
use App\Models\ShopOrderRevision;
use App\Models\ShopOrderRevisionItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrderCreatedNotification;
use App\Notifications\PurchasingOrderRevisionRequestedNotification;
use App\Notifications\PurchasingOrderSubmittedNotification;
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
            $tomorrow = Carbon::tomorrow();

            $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();
            $warehouseManager = User::query()->where('email', 'warehouse@greenleaf.com')->firstOrFail();

            $supplier = Supplier::query()->where('name', 'Green Valley Farm')->firstOrFail();

            $shops = Shop::query()
                ->whereIn('code', ['SHOP_CASIO', 'SHOP_BUDEGERE', 'SHOP_GRANCITY', 'SHOP_ASHIRWAD'])
                ->get()
                ->keyBy('code');

            $shopOwners = User::query()
                ->whereIn('email', [
                    'shop@greenleaf.com',
                    'shop-budegere@greenleaf.com',
                    'shop-grancity@greenleaf.com',
                    'shop-ashirwad@greenleaf.com',
                ])
                ->get()
                ->keyBy('email');

            $products = Product::query()
                ->whereIn('sku', [
                    'TOMATOH-001',
                    'TOMATON-002',
                    'ONION-003',
                    'POTATOAGRA-005',
                    'CORRIANDER-101',
                    'CHERRYTMTOBOX-126',
                ])
                ->get()
                ->keyBy('sku');

            $this->seedClosedDay(
                businessDate: $today->copy()->subDays(5),
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $supplier,
                shop: $shops['SHOP_CASIO'],
                shopOwner: $shopOwners['shop@greenleaf.com'],
                products: $products,
                orderNumber: 'RQ-WEEK-01-CASIO',
                poNumber: 'PO-WEEK-01',
                grnNumber: 'GRN-WEEK-01',
                invoiceNumber: 'PINV-WEEK-01',
                invoiceStatus: InvoiceStatus::Paid,
                orderItems: [
                    ['sku' => 'TOMATOH-001', 'requested' => 24, 'approved' => 24, 'delivered' => 24, 'sorting_status' => 'loaded', 'unit_cost' => 28.00],
                    ['sku' => 'ONION-003', 'requested' => 18, 'approved' => 18, 'delivered' => 18, 'sorting_status' => 'loaded', 'unit_cost' => 24.00],
                    ['sku' => 'CORRIANDER-101', 'requested' => 8, 'approved' => 8, 'delivered' => 8, 'sorting_status' => 'loaded', 'unit_cost' => 19.50],
                ],
            );

            $this->seedClosedDay(
                businessDate: $today->copy()->subDays(4),
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $supplier,
                shop: $shops['SHOP_BUDEGERE'],
                shopOwner: $shopOwners['shop-budegere@greenleaf.com'],
                products: $products,
                orderNumber: 'RQ-WEEK-02-BUD',
                poNumber: 'PO-WEEK-02',
                grnNumber: 'GRN-WEEK-02',
                invoiceNumber: 'PINV-WEEK-02',
                invoiceStatus: InvoiceStatus::Approved,
                orderItems: [
                    ['sku' => 'POTATOAGRA-005', 'requested' => 16, 'approved' => 16, 'delivered' => 15, 'shortage' => 1, 'sorting_status' => 'loaded', 'unit_cost' => 62.50],
                    ['sku' => 'TOMATON-002', 'requested' => 20, 'approved' => 20, 'delivered' => 20, 'sorting_status' => 'loaded', 'unit_cost' => 31.00],
                    ['sku' => 'CHERRYTMTOBOX-126', 'requested' => 4, 'approved' => 4, 'delivered' => 4, 'sorting_status' => 'loaded', 'unit_cost' => 120.00],
                ],
            );

            $this->seedApprovedOnlyDay(
                businessDate: $today->copy()->subDays(3),
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $supplier,
                shop: $shops['SHOP_GRANCITY'],
                shopOwner: $shopOwners['shop-grancity@greenleaf.com'],
                products: $products,
                orderNumber: 'RQ-WEEK-03-GRAND',
                poNumber: 'PO-WEEK-03',
                grnNumber: 'GRN-WEEK-03',
                orderItems: [
                    ['sku' => 'TOMATOH-001', 'requested' => 18, 'approved' => 18, 'sorting_status' => 'allocated', 'unit_cost' => 28.00],
                    ['sku' => 'ONION-003', 'requested' => 10, 'approved' => 10, 'sorting_status' => 'allocated', 'unit_cost' => 24.00],
                ],
            );

            $this->seedPendingReceiptDay(
                businessDate: $today->copy()->subDays(2),
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $supplier,
                shop: $shops['SHOP_ASHIRWAD'],
                shopOwner: $shopOwners['shop-ashirwad@greenleaf.com'],
                products: $products,
                orderNumber: 'RQ-WEEK-04-ASH',
                poNumber: 'PO-WEEK-04',
                grnNumber: 'GRN-WEEK-04',
                invoiceNumber: 'PINV-WEEK-04',
                orderItems: [
                    ['sku' => 'POTATOAGRA-005', 'requested' => 22, 'approved' => 22, 'sorting_status' => 'pending', 'unit_cost' => 62.50],
                    ['sku' => 'CORRIANDER-101', 'requested' => 11, 'approved' => 11, 'sorting_status' => 'pending', 'unit_cost' => 19.50],
                ],
            );

            $this->seedTodayOperationalQueue(
                businessDate: $today,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $supplier,
                shops: $shops,
                shopOwners: $shopOwners,
                products: $products,
            );

            $tomorrowRecords = $this->seedTomorrowReviewBoard(
                businessDate: $tomorrow,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $supplier,
                shops: $shops,
                shopOwners: $shopOwners,
                products: $products,
            );

            $purchaseManager->notifications()->delete();
            $purchaseManager->notify(new PurchasingOrderSubmittedNotification($tomorrowRecords['submitted_order']));
            $purchaseManager->notify(new PurchasingOrderRevisionRequestedNotification($tomorrowRecords['pending_revision']));
            $purchaseManager->notify(new PurchaseOrderCreatedNotification($tomorrowRecords['tomorrow_po']));
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
        Collection $shopOwners,
        Collection $products,
    ): void {
        $casioOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_CASIO'],
            shopOwner: $shopOwners['shop@greenleaf.com'],
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
            ['sku' => 'POTATOAGRA-005', 'requested' => 10, 'approved' => 10, 'delivered' => 9, 'shortage' => 1, 'sorting_status' => 'loaded', 'unit_cost' => 62.50],
            ['sku' => 'TOMATOH-001', 'requested' => 20, 'approved' => 20, 'delivered' => 20, 'sorting_status' => 'loaded', 'unit_cost' => 28.00],
        ], $products, $warehouseManager);

        $budegereOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_BUDEGERE'],
            shopOwner: $shopOwners['shop-budegere@greenleaf.com'],
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
            ['sku' => 'ONION-003', 'requested' => 16, 'approved' => 16, 'sorting_status' => 'loaded', 'unit_cost' => 24.00],
            ['sku' => 'CORRIANDER-101', 'requested' => 7, 'approved' => 7, 'sorting_status' => 'loaded', 'unit_cost' => 19.50],
        ], $products, $warehouseManager);

        $grancityOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_GRANCITY'],
            shopOwner: $shopOwners['shop-grancity@greenleaf.com'],
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
            ['sku' => 'TOMATON-002', 'requested' => 14, 'approved' => 14, 'sorting_status' => 'allocated', 'unit_cost' => 31.00],
            ['sku' => 'CHERRYTMTOBOX-126', 'requested' => 5, 'approved' => 5, 'sorting_status' => 'pending', 'unit_cost' => 120.00],
        ], $products, $warehouseManager);

        $po = $this->upsertPurchaseOrder(
            purchaseManager: $purchaseManager,
            supplier: $supplier,
            poNumber: 'PO-WEEK-05',
            businessDate: $businessDate,
            status: POStatus::PartiallyReceived,
            items: [
                ['sku' => 'POTATOAGRA-005', 'quantity' => 30, 'unit_price' => 62.50, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => 'ONION-003', 'quantity' => 20, 'unit_price' => 24.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => 'TOMATON-002', 'quantity' => 14, 'unit_price' => 31.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
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
                ['sku' => 'POTATOAGRA-005', 'received_qty' => 29.0, 'variance' => -1.0],
                ['sku' => 'ONION-003', 'received_qty' => 20.0, 'variance' => 0.0],
                ['sku' => 'TOMATON-002', 'received_qty' => 14.0, 'variance' => 0.0],
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
     * @return array{submitted_order: ShopOrder, pending_revision: ShopOrderRevision, tomorrow_po: PurchaseOrder}
     */
    private function seedTomorrowReviewBoard(
        Carbon $businessDate,
        User $purchaseManager,
        User $warehouseManager,
        Supplier $supplier,
        Collection $shops,
        Collection $shopOwners,
        Collection $products,
    ): array {
        $casioOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_CASIO'],
            shopOwner: $shopOwners['shop@greenleaf.com'],
            orderNumber: 'RQ-WEEK-06-CASIO',
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'latest_revision_no' => 2,
                'has_pending_revision' => false,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 10),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($casioOrder, [
            ['sku' => 'TOMATOH-001', 'requested' => 18, 'approved' => 18, 'sorting_status' => 'pending', 'unit_cost' => 28.00],
            ['sku' => 'ONION-003', 'requested' => 12, 'approved' => 12, 'sorting_status' => 'pending', 'unit_cost' => 24.00],
            ['sku' => 'CORRIANDER-101', 'requested' => 6, 'approved' => 6, 'sorting_status' => 'pending', 'unit_cost' => 19.50],
        ], $products, $warehouseManager);

        $appliedRevision = ShopOrderRevision::updateOrCreate(
            [
                'shop_order_id' => $casioOrder->id,
                'revision_no' => 2,
            ],
            [
                'status' => 'applied',
                'reason' => 'Manager approved small quantity increase.',
                'requested_by' => $shopOwners['shop@greenleaf.com']->id,
                'reviewed_by' => $purchaseManager->id,
                'reviewed_at' => now()->subHours(2),
            ]
        );

        $this->syncRevisionItems($appliedRevision, [
            ['sku' => 'ONION-003', 'old' => 10, 'new' => 12],
            ['sku' => 'CORRIANDER-101', 'old' => 5, 'new' => 6],
        ], $products);

        $budegereOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_BUDEGERE'],
            shopOwner: $shopOwners['shop-budegere@greenleaf.com'],
            orderNumber: 'RQ-WEEK-06-BUD',
            businessDate: $businessDate,
            attributes: [
                'state' => 'update_requested',
                'update_reason' => 'Need more potato and onion for weekend demand.',
                'latest_revision_no' => 2,
                'has_pending_revision' => true,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 25),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($budegereOrder, [
            ['sku' => 'POTATOAGRA-005', 'requested' => 14, 'approved' => 14, 'sorting_status' => 'pending', 'unit_cost' => 62.50],
            ['sku' => 'ONION-003', 'requested' => 10, 'approved' => 10, 'sorting_status' => 'pending', 'unit_cost' => 24.00],
            ['sku' => 'TOMATON-002', 'requested' => 8, 'approved' => 8, 'sorting_status' => 'pending', 'unit_cost' => 31.00],
        ], $products, $warehouseManager);

        $pendingRevision = ShopOrderRevision::updateOrCreate(
            [
                'shop_order_id' => $budegereOrder->id,
                'revision_no' => 2,
            ],
            [
                'status' => 'pending',
                'reason' => 'Weekend walk-in demand increased.',
                'requested_by' => $shopOwners['shop-budegere@greenleaf.com']->id,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        $this->syncRevisionItems($pendingRevision, [
            ['sku' => 'POTATOAGRA-005', 'old' => 14, 'new' => 18],
            ['sku' => 'ONION-003', 'old' => 10, 'new' => 13],
        ], $products);

        $submittedOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_GRANCITY'],
            shopOwner: $shopOwners['shop-grancity@greenleaf.com'],
            orderNumber: 'RQ-WEEK-06-GRAND',
            businessDate: $businessDate,
            attributes: [
                'state' => 'submitted',
                'submitted_at' => $businessDate->copy()->subDay()->setTime(19, 5),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($submittedOrder, [
            ['sku' => 'TOMATOH-001', 'requested' => 15, 'approved' => null, 'sorting_status' => 'pending', 'unit_cost' => 28.00],
            ['sku' => 'CHERRYTMTOBOX-126', 'requested' => 3, 'approved' => null, 'sorting_status' => 'pending', 'unit_cost' => 120.00],
        ], $products, $warehouseManager, false);

        $ashirwadOrder = $this->upsertShopOrder(
            shop: $shops['SHOP_ASHIRWAD'],
            shopOwner: $shopOwners['shop-ashirwad@greenleaf.com'],
            orderNumber: 'RQ-WEEK-06-ASH',
            businessDate: $businessDate,
            attributes: [
                'state' => 'approved',
                'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 35),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
            ],
        );

        $this->syncShopOrderItems($ashirwadOrder, [
            ['sku' => 'CORRIANDER-101', 'requested' => 9, 'approved' => 9, 'sorting_status' => 'pending', 'unit_cost' => 19.50],
            ['sku' => 'TOMATON-002', 'requested' => 11, 'approved' => 11, 'sorting_status' => 'pending', 'unit_cost' => 31.00],
        ], $products, $warehouseManager);

        $tomorrowPo = $this->upsertPurchaseOrder(
            purchaseManager: $purchaseManager,
            supplier: $supplier,
            poNumber: 'PO-WEEK-06',
            businessDate: $businessDate,
            status: POStatus::Approved,
            items: [
                ['sku' => 'TOMATOH-001', 'quantity' => 33, 'unit_price' => 28.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => 'ONION-003', 'quantity' => 22, 'unit_price' => 24.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => 'CORRIANDER-101', 'quantity' => 15, 'unit_price' => 19.50, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
                ['sku' => 'TOMATON-002', 'quantity' => 19, 'unit_price' => 31.00, 'purchase_unit' => 'kg', 'price_basis' => 'per_kg'],
            ],
            products: $products,
            notes: 'Auto-generated from Approved Requisitions Board',
        );

        return [
            'submitted_order' => $submittedOrder,
            'pending_revision' => $pendingRevision->load('shopOrder', 'items'),
            'tomorrow_po' => $tomorrowPo->load('supplier'),
        ];
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
     * @param  array<int, array{sku: string, old: int|float, new: int|float}>  $items
     */
    private function syncRevisionItems(ShopOrderRevision $revision, array $items, Collection $products): void
    {
        $productIds = collect($items)
            ->map(fn (array $item): ?int => $products->get($item['sku'])?->id)
            ->filter()
            ->values()
            ->all();

        $revision->items()->whereNotIn('product_id', $productIds)->delete();

        foreach ($items as $item) {
            $product = $products->get($item['sku']);

            if (! $product) {
                continue;
            }

            $oldQty = (float) $item['old'];
            $newQty = (float) $item['new'];

            ShopOrderRevisionItem::updateOrCreate(
                [
                    'shop_order_revision_id' => $revision->id,
                    'product_id' => $product->id,
                ],
                [
                    'old_requested_qty' => $oldQty,
                    'new_requested_qty' => $newQty,
                    'delta_qty' => $newQty - $oldQty,
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
