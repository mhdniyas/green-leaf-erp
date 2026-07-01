<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCorrectionRequest;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserDemoSeeder extends Seeder
{
    private const DEMO_ORDER_ITEM_COUNT = 6;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,
            DemoUserSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        DB::transaction(function (): void {
            $businessDayService = app(PurchaserBusinessDayService::class);
            $activeBusinessDate = $businessDayService->operationalDate();
            $calendarDate = $businessDayService->currentCalendarDate();
            $previousBusinessDate = $activeBusinessDate->copy()->subDay();
            $approvalCenterDate = $calendarDate->copy()->addDay();
            $purchaser = User::query()->where('email', 'purchaser@greenleaf.com')->firstOrFail();
            $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();
            $warehouseManager = User::query()->where('email', 'warehouse@greenleaf.com')->firstOrFail();
            $warehouse = Warehouse::query()->orderBy('id')->firstOrFail();

            $marketA = Supplier::query()->where('name', 'Market A')->firstOrFail();
            $marketB = Supplier::query()->where('name', 'Market B')->firstOrFail();
            $marketC = Supplier::query()->where('name', 'Market C')->firstOrFail();
            $marketD = Supplier::query()->where('name', 'Market D')->firstOrFail();
            $marketE = Supplier::query()->where('name', 'Market E')->firstOrFail();

            $products = Product::query()
                ->whereIn('sku', [
                    '1',
                    '3',
                    '13',
                    '54',
                    '101',
                    '126',
                    '5',
                    '164',
                ])
                ->get()
                ->keyBy('sku');

            $this->seedShopOrdersForAllShops($previousBusinessDate, $purchaseManager, 'YDAY');
            $this->seedShopOrdersForAllShops($activeBusinessDate, $purchaseManager, 'ACTIVE');

            if (! $approvalCenterDate->isSameDay($activeBusinessDate)) {
                $this->seedShopOrdersForAllShops($approvalCenterDate, $purchaseManager, 'NEXT');
            }

            $this->seedDraftCart(
                purchaser: $purchaser,
                supplier: $marketA,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-DRAFT-001',
                products: $products,
                items: [
                    ['sku' => '1', 'quantity' => 5, 'unit_price' => 28.00, 'notes' => 'Hot market pickup.'],
                    ['sku' => '3', 'quantity' => 13, 'unit_price' => 24.00, 'notes' => 'Loose bag.'],
                    ['sku' => '126', 'quantity' => 2, 'unit_price' => 120.00, 'notes' => 'Box pack.'],
                ],
                notes: 'Draft cart for mobile purchaser testing.',
            );

            $this->seedDraftCart(
                purchaser: $purchaser,
                supplier: $marketB,
                businessDate: $previousBusinessDate,
                cartNumber: 'VC-DEMO-OVERDUE-DRAFT-001',
                products: $products,
                items: [
                    ['sku' => '101', 'quantity' => 4, 'unit_price' => 72.00, 'notes' => 'Vendor confirmed, bill still pending.'],
                    ['sku' => '164', 'quantity' => 2, 'unit_price' => 49.00, 'notes' => 'Keep visible in overdue.'],
                ],
                notes: 'Overdue vendor-assigned draft cart for vendor hub testing.',
            );

            $this->seedStandalonePurchaseOrder(
                supplier: $marketA,
                owner: $purchaseManager,
                businessDate: $previousBusinessDate,
                poNumber: 'PO-DEMO-STANDALONE-001',
                status: POStatus::Draft,
                products: $products,
                items: [
                    ['sku' => '1', 'quantity' => 10, 'unit_price' => 26.00],
                    ['sku' => '3', 'quantity' => 8, 'unit_price' => 22.50],
                ],
                notes: 'Draft PO for purchaser purchase-order screen testing.',
            );

            $this->seedStandalonePurchaseOrder(
                supplier: $marketB,
                owner: $purchaseManager,
                businessDate: $activeBusinessDate,
                poNumber: 'PO-DEMO-STANDALONE-002',
                status: POStatus::Approved,
                products: $products,
                items: [
                    ['sku' => '13', 'quantity' => 6, 'unit_price' => 31.00],
                    ['sku' => '54', 'quantity' => 5, 'unit_price' => 18.50],
                ],
                notes: 'Approved PO for purchaser purchase-order screen testing.',
            );

            $this->seedStandalonePurchaseOrder(
                supplier: $marketB,
                owner: $purchaseManager,
                businessDate: $activeBusinessDate,
                poNumber: 'PO-DEMO-STANDALONE-003',
                status: POStatus::SentToSupplier,
                products: $products,
                items: [
                    ['sku' => '164', 'quantity' => 4, 'unit_price' => 46.00],
                ],
                notes: 'Sent-to-supplier PO for purchaser purchase-order screen testing.',
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketB,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-SUBMIT-001',
                poNumber: 'PO-DEMO-PUR-001',
                grnNumber: 'GRN-DEMO-PUR-001',
                invoiceNumber: 'PINV-DEMO-PUR-001',
                invoiceStatus: InvoiceStatus::Paid,
                paymentMethod: 'Cash',
                paymentStatus: 'paid',
                paidAmount: 286.00,
                discountAmount: 14.00,
                paymentNote: 'Cash settled at pickup.',
                paymentDetails: 'Cash handover after vendor WhatsApp share.',
                products: $products,
                items: [
                    ['sku' => '13', 'quantity' => 4, 'unit_price' => 32.00, 'notes' => 'Approved bundle.'],
                    ['sku' => '54', 'quantity' => 6, 'unit_price' => 18.00, 'notes' => 'English cucumber mix.'],
                ],
                notes: 'Submitted cash cart with linked PO, GRN, and invoice.',
                warehouseConfirmed: true,
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketA,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-SUBMIT-002',
                poNumber: 'PO-DEMO-PUR-002',
                grnNumber: 'GRN-DEMO-PUR-002',
                invoiceNumber: 'PINV-DEMO-PUR-002',
                invoiceStatus: InvoiceStatus::Approved,
                paymentMethod: 'Credit',
                paymentStatus: 'credit_pending_approval',
                paidAmount: 0.00,
                discountAmount: 0.00,
                paymentNote: 'Credit request awaiting manager approval.',
                paymentDetails: 'WhatsApp order list sent to vendor.',
                products: $products,
                items: [
                    ['sku' => '5', 'quantity' => 12, 'unit_price' => 62.50, 'notes' => 'Credit line item.'],
                    ['sku' => '164', 'quantity' => 3, 'unit_price' => 48.00, 'notes' => 'Urgent delivery.'],
                ],
                notes: 'Submitted credit cart for purchase manager approval.',
                warehouseConfirmed: true,
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketA,
                businessDate: $previousBusinessDate,
                cartNumber: 'VC-DEMO-OVERDUE-RECEIPT-001',
                poNumber: 'PO-DEMO-OVERDUE-RECEIPT-001',
                grnNumber: 'GRN-DEMO-OVERDUE-RECEIPT-001',
                invoiceNumber: 'PINV-DEMO-OVERDUE-RECEIPT-001',
                invoiceStatus: InvoiceStatus::Paid,
                paymentMethod: 'Cash',
                paymentStatus: 'paid',
                paidAmount: 318.00,
                discountAmount: 12.00,
                paymentNote: 'Paid, but warehouse confirmation is still pending.',
                paymentDetails: 'Use this to test overdue receipt follow-up.',
                products: $products,
                items: [
                    ['sku' => '1', 'quantity' => 6, 'unit_price' => 30.00, 'notes' => 'Awaiting warehouse receive.'],
                    ['sku' => '3', 'quantity' => 6, 'unit_price' => 25.00, 'notes' => 'Still in unloading queue.'],
                ],
                notes: 'Paid overdue cart that should remain pending until warehouse confirmation.',
                warehouseConfirmed: false,
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketB,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-PAYMENT-PENDING-001',
                poNumber: 'PO-DEMO-PAYMENT-PENDING-001',
                grnNumber: 'GRN-DEMO-PAYMENT-PENDING-001',
                invoiceNumber: 'PINV-DEMO-PAYMENT-PENDING-001',
                invoiceStatus: InvoiceStatus::Approved,
                paymentMethod: 'Cash',
                paymentStatus: 'partial',
                paidAmount: 120.00,
                discountAmount: 0.00,
                paymentNote: 'Partial payment made. Balance still pending.',
                paymentDetails: 'Show this in vendor hub payment pending.',
                products: $products,
                items: [
                    ['sku' => '13', 'quantity' => 5, 'unit_price' => 31.00, 'notes' => 'Recent pending payment.'],
                    ['sku' => '54', 'quantity' => 8, 'unit_price' => 19.50, 'notes' => 'Warehouse already confirmed.'],
                ],
                notes: 'Current payment-pending cart for vendor hub testing.',
                warehouseConfirmed: true,
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketB,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-COMPLETED-001',
                poNumber: 'PO-DEMO-COMPLETED-001',
                grnNumber: 'GRN-DEMO-COMPLETED-001',
                invoiceNumber: 'PINV-DEMO-COMPLETED-001',
                invoiceStatus: InvoiceStatus::Paid,
                paymentMethod: 'Online',
                paymentStatus: 'paid',
                paidAmount: 244.00,
                discountAmount: 6.00,
                paymentNote: 'Online transfer settled.',
                paymentDetails: 'Use this as the clean completed example.',
                products: $products,
                items: [
                    ['sku' => '5', 'quantity' => 2, 'unit_price' => 64.00, 'notes' => 'Completed item.'],
                    [
                        'sku' => '126',
                        'quantity' => 1,
                        'unit_price' => 122.00,
                        'notes' => 'Completed box item with short receipt.',
                        'received_qty' => 0.75,
                        'variance' => -0.25,
                    ],
                ],
                notes: 'Warehouse confirmed and fully paid completed cart.',
                warehouseConfirmed: true,
                receiptNotes: 'One carton arrived short and was recorded by warehouse receiver.',
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketC,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-MARKET-C-001',
                poNumber: 'PO-DEMO-MARKET-C-001',
                grnNumber: 'GRN-DEMO-MARKET-C-001',
                invoiceNumber: 'PINV-DEMO-MARKET-C-001',
                invoiceStatus: InvoiceStatus::Approved,
                paymentMethod: 'Cash',
                paymentStatus: 'unpaid',
                paidAmount: 0.00,
                discountAmount: 0.00,
                paymentNote: 'Vendor promised evening collection.',
                paymentDetails: 'Keep this as unpaid vendor-hub sample.',
                products: $products,
                items: [
                    ['sku' => '101', 'quantity' => 3, 'unit_price' => 74.00, 'notes' => 'Fresh leafy lot.'],
                    ['sku' => '126', 'quantity' => 1, 'unit_price' => 118.00, 'notes' => 'Packed carton.'],
                ],
                notes: 'Market C unpaid purchase for vendor hub variety.',
                warehouseConfirmed: true,
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                warehouse: $warehouse,
                supplier: $marketD,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-MARKET-D-001',
                poNumber: 'PO-DEMO-MARKET-D-001',
                grnNumber: 'GRN-DEMO-MARKET-D-001',
                invoiceNumber: 'PINV-DEMO-MARKET-D-001',
                invoiceStatus: InvoiceStatus::Paid,
                paymentMethod: 'Online',
                paymentStatus: 'paid',
                paidAmount: 268.00,
                discountAmount: 8.00,
                paymentNote: 'NEFT settled after morning delivery.',
                paymentDetails: 'Use this as another completed vendor example.',
                products: $products,
                items: [
                    ['sku' => '54', 'quantity' => 7, 'unit_price' => 20.00, 'notes' => 'Strong recent completed bill.'],
                    ['sku' => '164', 'quantity' => 2, 'unit_price' => 68.00, 'notes' => 'Online paid vendor.'],
                ],
                notes: 'Market D completed vendor hub example.',
                warehouseConfirmed: true,
            );

            $this->seedDraftCart(
                purchaser: $purchaser,
                supplier: $marketE,
                businessDate: $activeBusinessDate,
                cartNumber: 'VC-DEMO-MARKET-E-DRAFT-001',
                products: $products,
                items: [
                    ['sku' => '5', 'quantity' => 3, 'unit_price' => 63.00, 'notes' => 'Open draft for GPay vendor.'],
                    ['sku' => '13', 'quantity' => 4, 'unit_price' => 30.00, 'notes' => 'Use in vendor hub recent cart.'],
                ],
                notes: 'Market E active draft cart for vendor hub breadth.',
            );

            $this->seedCorrectionRequest(
                purchaser: $purchaser,
                businessDate: $activeBusinessDate,
                shopOrderNumber: sprintf('RQ-DEMO-ACTIVE-%s-%02d', $activeBusinessDate->format('Ymd'), 1),
                productSku: '1',
                proposedQty: 15,
                note: 'Shop typed extra 3 kg. Please approve 15 kg only.',
            );

            $purchaseManager->notifications()->delete();
        });

        $this->command?->info('Purchaser demo seed complete.');
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function seedDraftCart(
        User $purchaser,
        Supplier $supplier,
        Carbon $businessDate,
        string $cartNumber,
        Collection $products,
        array $items,
        string $notes,
    ): PurchaserCart {
        $existingCart = PurchaserCart::query()
            ->where('cart_number', $cartNumber)
            ->first();

        if ($existingCart instanceof PurchaserCart) {
            $this->clearDraftCartWorkflowState($existingCart);
        }

        $cart = PurchaserCart::query()->updateOrCreate(
            ['cart_number' => $cartNumber],
            [
                'user_id' => $purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $businessDate->toDateString(),
                'purchase_order_id' => null,
                'goods_received_id' => null,
                'purchase_invoice_id' => null,
                'bill_number' => null,
                'discount_amount' => 0,
                'payment_method' => null,
                'payment_status' => 'unpaid',
                'paid_amount' => 0,
                'payment_note' => null,
                'payment_details' => null,
                'status' => 'draft',
                'notes' => $notes,
                'submitted_at' => null,
                'whatsapp_sent_at' => null,
                'goods_received_at' => null,
                'bill_received_at' => null,
                'payment_made_at' => null,
            ]
        );

        $this->syncCartItems($cart, $products, $items);

        return $cart->load(['supplier', 'items.product.category']);
    }

    private function clearDraftCartWorkflowState(PurchaserCart $cart): void
    {
        $invoiceIds = PurchaseInvoice::query()
            ->where('purchaser_cart_id', $cart->id)
            ->pluck('id');

        if ($invoiceIds->isNotEmpty()) {
            PurchaserCredit::query()
                ->whereIn('purchase_invoice_id', $invoiceIds->all())
                ->delete();

            PurchaseInvoice::query()
                ->whereIn('id', $invoiceIds->all())
                ->forceDelete();
        }

        if ($cart->goods_received_id !== null) {
            StockBatch::query()
                ->where('goods_received_id', $cart->goods_received_id)
                ->delete();

            GoodsReceived::query()
                ->whereKey($cart->goods_received_id)
                ->delete();
        }

        if ($cart->purchase_order_id !== null) {
            PurchaseOrder::query()
                ->whereKey($cart->purchase_order_id)
                ->delete();
        }
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function seedSubmittedCartWithDocuments(
        User $purchaser,
        User $purchaseManager,
        User $warehouseManager,
        Warehouse $warehouse,
        Supplier $supplier,
        Carbon $businessDate,
        string $cartNumber,
        string $poNumber,
        string $grnNumber,
        string $invoiceNumber,
        InvoiceStatus $invoiceStatus,
        string $paymentMethod,
        string $paymentStatus,
        float $paidAmount,
        float $discountAmount,
        string $paymentNote,
        string $paymentDetails,
        Collection $products,
        array $items,
        string $notes,
        bool $warehouseConfirmed = true,
        ?string $receiptNotes = null,
    ): PurchaserCart {
        $cart = PurchaserCart::query()->updateOrCreate(
            ['cart_number' => $cartNumber],
            [
                'user_id' => $purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $businessDate->toDateString(),
                'status' => 'submitted',
                'bill_number' => $invoiceNumber,
                'discount_amount' => $discountAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $paymentNote,
                'payment_details' => $paymentDetails,
                'notes' => $notes,
                'submitted_at' => $businessDate->copy()->setTime(11, 15),
                'whatsapp_sent_at' => $businessDate->copy()->setTime(9, 45),
                'goods_received_at' => $businessDate->copy()->setTime(11, 50),
                'bill_received_at' => $businessDate->copy()->setTime(11, 55),
                'payment_made_at' => $paidAmount > 0 ? $businessDate->copy()->setTime(12, 5) : null,
            ]
        );

        $this->syncCartItems($cart, $products, $items);

        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['po_number' => $poNumber],
            [
                'supplier_id' => $supplier->id,
                'status' => POStatus::Received,
                'order_date' => $businessDate->toDateString(),
                'created_by' => $purchaseManager->id,
                'notes' => 'Seeded purchaser demo purchase order.',
                'fulfillment_type' => 'warehouse',
            ]
        );

        $this->syncPurchaseOrderItems($purchaseOrder, $products, $items);

        $goodsReceived = GoodsReceived::query()->updateOrCreate(
            ['grn_number' => $grnNumber],
            [
                'purchase_order_id' => $purchaseOrder->id,
                'status' => 'approved',
                'received_by' => $warehouseManager->id,
                'approved_by' => $purchaseManager->id,
                'received_at' => $businessDate->toDateString(),
                'approved_at' => $businessDate->copy()->setTime(12, 0),
                'transport_cost' => 120.00,
                'labour_cost' => 45.00,
                'notes' => $receiptNotes ?: 'Seeded purchaser demo goods receipt.',
                'is_extra' => false,
            ]
        );

        $this->syncGoodsReceivedItems($goodsReceived, $purchaseOrder, $products, $items);

        $invoice = PurchaseInvoice::query()->updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'goods_received_id' => $goodsReceived->id,
                'supplier_id' => $supplier->id,
                'purchaser_cart_id' => $cart->id,
                'amount' => $this->sumItems($items),
                'discount_amount' => $discountAmount,
                'status' => $invoiceStatus,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $paymentNote,
                'payment_details' => $paymentDetails,
                'purchaser_submitted_by' => $purchaser->id,
                'purchaser_submitted_at' => $businessDate->copy()->setTime(11, 30),
                'notes' => 'Seeded purchaser demo purchase invoice.',
            ]
        );

        $cart->update([
            'purchase_order_id' => $purchaseOrder->id,
            'goods_received_id' => $goodsReceived->id,
            'purchase_invoice_id' => $invoice->id,
        ]);

        $this->syncStockBatches(
            goodsReceived: $goodsReceived,
            warehouse: $warehouse,
            warehouseManager: $warehouseManager,
            products: $products,
            items: $items,
            warehouseConfirmed: $warehouseConfirmed,
        );

        return $cart->fresh(['supplier', 'items.product.category', 'purchaseOrder', 'goodsReceived', 'purchaseInvoice']);
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int}>  $items
     */
    private function seedStandalonePurchaseOrder(
        Supplier $supplier,
        User $owner,
        Carbon $businessDate,
        string $poNumber,
        POStatus $status,
        Collection $products,
        array $items,
        string $notes,
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['po_number' => $poNumber],
            [
                'supplier_id' => $supplier->id,
                'status' => $status,
                'order_date' => $businessDate->toDateString(),
                'created_by' => $owner->id,
                'notes' => $notes,
                'fulfillment_type' => 'warehouse',
            ]
        );

        $this->syncPurchaseOrderItems($purchaseOrder, $products, $items);

        return $purchaseOrder->fresh('items.product');
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function syncCartItems(PurchaserCart $cart, Collection $products, array $items): void
    {
        $productIds = collect($items)
            ->map(fn (array $item): ?int => $products->get($item['sku'])?->id)
            ->filter()
            ->values()
            ->all();

        $cart->items()->whereNotIn('product_id', $productIds)->delete();

        foreach ($items as $item) {
            $product = $products->get($item['sku']);

            if (! $product) {
                continue;
            }

            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            PurchaserCartItem::query()->updateOrCreate(
                [
                    'purchaser_cart_id' => $cart->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function syncPurchaseOrderItems(PurchaseOrder $purchaseOrder, Collection $products, array $items): void
    {
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

            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            $purchaseOrder->items()->updateOrCreate(
                [
                    'product_id' => $product->id,
                ],
                [
                    'purchase_unit' => $product->unit === 'box' ? 'box' : 'kg',
                    'packet_qty' => $product->unit === 'box' ? $quantity : null,
                    'weight_per_packet' => $product->unit === 'box' ? 1.0 : null,
                    'actual_weight' => null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'price_basis' => $product->unit === 'box' ? 'per_unit' : 'per_kg',
                ]
            );
        }
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string, received_qty?: float|int, variance?: float|int}>  $items
     */
    private function syncGoodsReceivedItems(
        GoodsReceived $goodsReceived,
        PurchaseOrder $purchaseOrder,
        Collection $products,
        array $items,
    ): void {
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

            $quantity = (float) $item['quantity'];
            $receivedQuantity = (float) ($item['received_qty'] ?? $quantity);
            $variance = array_key_exists('variance', $item)
                ? (float) $item['variance']
                : round($receivedQuantity - $quantity, 2);

            $goodsReceived->items()->updateOrCreate(
                [
                    'product_id' => $product->id,
                ],
                [
                    'purchase_order_item_id' => $purchaseOrderItems[$product->id]->id,
                    'received_unit' => $purchaseOrderItems[$product->id]->purchase_unit,
                    'received_packet_qty' => $purchaseOrderItems[$product->id]->purchase_unit === 'box' ? $receivedQuantity : null,
                    'received_weight_per_packet' => $purchaseOrderItems[$product->id]->purchase_unit === 'box' ? 1.0 : null,
                    'received_qty' => $receivedQuantity,
                    'variance' => $variance,
                ]
            );
        }
    }

    private function seedShopOrdersForAllShops(Carbon $businessDate, User $orderCreator, string $prefix): void
    {
        $shops = Shop::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(max(self::DEMO_ORDER_ITEM_COUNT * 2, 12))
            ->get()
            ->values();

        if ($shops->isEmpty() || $products->count() < self::DEMO_ORDER_ITEM_COUNT) {
            return;
        }

        foreach ($shops->values() as $shopIndex => $shop) {
            $orderNumber = sprintf(
                'RQ-DEMO-%s-%s-%02d',
                $prefix,
                $businessDate->format('Ymd'),
                $shopIndex + 1,
            );

            $order = ShopOrder::query()->updateOrCreate(
                ['order_number' => $orderNumber],
                [
                    'shop_id' => $shop->id,
                    'state' => 'approved',
                    'delivery_status' => 'pending_delivery',
                    'payment_status' => 'pending',
                    'business_date' => $businessDate->toDateString(),
                    'submitted_at' => $businessDate->copy()->subDay()->setTime(18, 15),
                    'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 30),
                    'created_by' => $orderCreator->id,
                    'latest_revision_no' => 1,
                    'has_pending_revision' => false,
                    'is_allocation_completed' => false,
                    'is_delivered' => false,
                    'is_late' => false,
                    'cash_collected' => 0,
                    'cash_discrepancy' => 0,
                    'balance_amount' => 0,
                    'total_shortage_value' => 0,
                ]
            );

            $this->syncDemoOrderItems($order, $products, $shopIndex, $prefix);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function syncDemoOrderItems(ShopOrder $order, Collection $products, int $shopIndex, string $prefix): void
    {
        $selectedProducts = collect(range(0, self::DEMO_ORDER_ITEM_COUNT - 1))
            ->map(function (int $offset) use ($products, $shopIndex): Product {
                return $products[($shopIndex + $offset) % $products->count()];
            });

        $productIds = $selectedProducts->pluck('id')->all();

        ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();

        foreach ($selectedProducts->values() as $itemIndex => $product) {
            $quantity = (float) (3 + (($shopIndex + $itemIndex) % 5));
            $price = max(1.0, round((float) ($product->base_price ?? $product->vendor_price ?? 1.0), 2));

            ShopOrderItem::query()->updateOrCreate(
                [
                    'shop_order_id' => $order->id,
                    'product_id' => $product->id,
                ],
                [
                    'product_grade' => 'A',
                    'requested_qty' => $quantity,
                    'approved_qty' => $quantity,
                    'unit' => $product->unit,
                    'locked_selling_price' => $price,
                    'locked_price_source' => 'seeded_load',
                    'line_total' => round($quantity * $price, 2),
                    'notes' => sprintf('Dynamic %s purchaser demo order.', strtolower($prefix)),
                    'fulfillment_type' => 'warehouse',
                    'sorting_status' => 'pending',
                    'is_sorted' => false,
                    'delivered_qty' => 0,
                    'shortage_qty' => 0,
                    'unit_cost' => round($price * 0.82, 4),
                    'shortage_value' => 0,
                ]
            );
        }
    }

    private function seedCorrectionRequest(
        User $purchaser,
        Carbon $businessDate,
        string $shopOrderNumber,
        string $productSku,
        float $proposedQty,
        string $note,
    ): void {
        $shopOrderItem = ShopOrderItem::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', $shopOrderNumber))
            ->whereHas('product', fn ($query) => $query->where('sku', $productSku))
            ->first();

        if (! $shopOrderItem) {
            return;
        }

        PurchaserCorrectionRequest::query()->updateOrCreate(
            [
                'shop_order_item_id' => $shopOrderItem->id,
            ],
            [
                'business_date' => $businessDate->toDateString(),
                'current_approved_qty' => $shopOrderItem->approved_qty,
                'proposed_corrected_qty' => $proposedQty,
                'purchaser_note' => $note,
                'requester_user_id' => $purchaser->id,
                'status' => 'pending',
                'reviewer_user_id' => null,
                'review_note' => null,
                'reviewed_at' => null,
            ]
        );
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function syncStockBatches(
        GoodsReceived $goodsReceived,
        Warehouse $warehouse,
        User $warehouseManager,
        Collection $products,
        array $items,
        bool $warehouseConfirmed,
    ): void {
        foreach ($items as $item) {
            $product = $products->get($item['sku']);

            if (! $product) {
                continue;
            }

            $batch = StockBatch::withTrashed()->firstOrNew(
                ['reference' => 'BATCH-'.$goodsReceived->grn_number.'-'.$item['sku']],
            );

            $batch->fill([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'created_by' => $warehouseManager->id,
                'received_at' => $goodsReceived->received_at,
                'total_kg' => (float) $item['quantity'],
                'cost_per_kg' => (float) $item['unit_price'],
                'transport_cost' => 0,
                'labour_cost' => 0,
                'status' => 'pending',
                'warehouse_receive_pending' => ! $warehouseConfirmed,
                'warehouse_confirmed_at' => $warehouseConfirmed ? Carbon::parse($goodsReceived->received_at)->setTime(12, 20) : null,
                'warehouse_confirmed_by' => $warehouseConfirmed ? $warehouseManager->id : null,
                'notes' => 'Auto-created from GRN: '.$goodsReceived->grn_number,
                'sorted_at' => null,
                'deleted_at' => null,
            ]);
            $batch->save();
        }
    }

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function sumItems(array $items): float
    {
        return round(collect($items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']
        ), 2);
    }
}
