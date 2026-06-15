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
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaserDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = Carbon::today();
            $purchaser = User::query()->where('email', 'purchaser@greenleaf.com')->firstOrFail();
            $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();
            $warehouseManager = User::query()->where('email', 'warehouse@greenleaf.com')->firstOrFail();

            $marketA = Supplier::query()->where('name', 'Market A')->firstOrFail();
            $marketB = Supplier::query()->where('name', 'Market B')->firstOrFail();

            $products = Product::query()
                ->whereIn('sku', [
                    'TOMATOH-001',
                    'ONION-003',
                    'FRENCHBEANS-013',
                    'ENGCUCUMBER-054',
                    'CORRIANDER-101',
                    'CHERRYTMTOBOX-126',
                    'POTATOAGRA-005',
                    'BANANANENDRAN-164',
                ])
                ->get()
                ->keyBy('sku');

            $this->seedDraftCart(
                purchaser: $purchaser,
                supplier: $marketA,
                businessDate: $today,
                cartNumber: 'VC-DEMO-DRAFT-001',
                products: $products,
                items: [
                    ['sku' => 'TOMATOH-001', 'quantity' => 5, 'unit_price' => 28.00, 'notes' => 'Hot market pickup.'],
                    ['sku' => 'ONION-003', 'quantity' => 13, 'unit_price' => 24.00, 'notes' => 'Loose bag.'],
                    ['sku' => 'CHERRYTMTOBOX-126', 'quantity' => 2, 'unit_price' => 120.00, 'notes' => 'Box pack.'],
                ],
                notes: 'Draft cart for mobile purchaser testing.',
            );

            $this->seedStandalonePurchaseOrder(
                supplier: $marketA,
                owner: $purchaseManager,
                businessDate: $today->copy()->subDay(),
                poNumber: 'PO-DEMO-STANDALONE-001',
                status: POStatus::Draft,
                products: $products,
                items: [
                    ['sku' => 'TOMATOH-001', 'quantity' => 10, 'unit_price' => 26.00],
                    ['sku' => 'ONION-003', 'quantity' => 8, 'unit_price' => 22.50],
                ],
                notes: 'Draft PO for purchaser purchase-order screen testing.',
            );

            $this->seedStandalonePurchaseOrder(
                supplier: $marketB,
                owner: $purchaseManager,
                businessDate: $today,
                poNumber: 'PO-DEMO-STANDALONE-002',
                status: POStatus::Approved,
                products: $products,
                items: [
                    ['sku' => 'FRENCHBEANS-013', 'quantity' => 6, 'unit_price' => 31.00],
                    ['sku' => 'ENGCUCUMBER-054', 'quantity' => 5, 'unit_price' => 18.50],
                ],
                notes: 'Approved PO for purchaser purchase-order screen testing.',
            );

            $this->seedStandalonePurchaseOrder(
                supplier: $marketB,
                owner: $purchaseManager,
                businessDate: $today,
                poNumber: 'PO-DEMO-STANDALONE-003',
                status: POStatus::SentToSupplier,
                products: $products,
                items: [
                    ['sku' => 'BANANANENDRAN-164', 'quantity' => 4, 'unit_price' => 46.00],
                ],
                notes: 'Sent-to-supplier PO for purchaser purchase-order screen testing.',
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketB,
                businessDate: $today,
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
                    ['sku' => 'FRENCHBEANS-013', 'quantity' => 4, 'unit_price' => 32.00, 'notes' => 'Approved bundle.'],
                    ['sku' => 'ENGCUCUMBER-054', 'quantity' => 6, 'unit_price' => 18.00, 'notes' => 'English cucumber mix.'],
                ],
                notes: 'Submitted cash cart with linked PO, GRN, and invoice.',
            );

            $this->seedSubmittedCartWithDocuments(
                purchaser: $purchaser,
                purchaseManager: $purchaseManager,
                warehouseManager: $warehouseManager,
                supplier: $marketA,
                businessDate: $today,
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
                    ['sku' => 'POTATOAGRA-005', 'quantity' => 12, 'unit_price' => 62.50, 'notes' => 'Credit line item.'],
                    ['sku' => 'BANANANENDRAN-164', 'quantity' => 3, 'unit_price' => 48.00, 'notes' => 'Urgent delivery.'],
                ],
                notes: 'Submitted credit cart for purchase manager approval.',
            );

            $this->seedCorrectionRequest(
                purchaser: $purchaser,
                businessDate: $today,
                shopOrderNumber: 'RQ-DEMO-TODAY-GRAND',
                productSku: 'TOMATOH-001',
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
        $cart = PurchaserCart::query()->updateOrCreate(
            ['cart_number' => $cartNumber],
            [
                'user_id' => $purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $businessDate->toDateString(),
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

    /**
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
     */
    private function seedSubmittedCartWithDocuments(
        User $purchaser,
        User $purchaseManager,
        User $warehouseManager,
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
                'notes' => 'Seeded purchaser demo goods receipt.',
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
     * @param  array<int, array{sku: string, quantity: float|int, unit_price: float|int, notes?: string}>  $items
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

            $goodsReceived->items()->updateOrCreate(
                [
                    'product_id' => $product->id,
                ],
                [
                    'purchase_order_item_id' => $purchaseOrderItems[$product->id]->id,
                    'received_unit' => $purchaseOrderItems[$product->id]->purchase_unit,
                    'received_packet_qty' => $purchaseOrderItems[$product->id]->purchase_unit === 'box' ? $quantity : null,
                    'received_weight_per_packet' => $purchaseOrderItems[$product->id]->purchase_unit === 'box' ? 1.0 : null,
                    'received_qty' => $quantity,
                    'variance' => 0,
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
            ->firstOrFail();

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
    private function sumItems(array $items): float
    {
        return round(collect($items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']
        ), 2);
    }
}
