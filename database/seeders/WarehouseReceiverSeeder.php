<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseReceiverSeeder extends Seeder
{
    private const BUSINESS_DATE = '2026-07-14';

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,
            DemoUserSeeder::class,
        ]);

        $this->clearSeededWorkflow();
        $this->call(JulyFourteenShopOwnerOrderSeeder::class);

        DB::transaction(function (): void {
            $businessDate = Carbon::parse(self::BUSINESS_DATE)->startOfDay();
            $purchaser = User::query()->where('email', 'purchaser@greenleaf.com')->firstOrFail();
            $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();

            $this->approveShopOwnerOrders($businessDate, $purchaseManager);

            $this->seedSubmittedPurchase(
                businessDate: $businessDate,
                purchaser: $purchaser,
                supplierName: 'Market A',
                cartNumber: 'VC-20260714-WH01',
                purchaseOrderNumber: 'PO-20260714-WH01',
                grnNumber: 'GRN-20260714-WH01',
                invoiceNumber: 'PINV-20260714-WH01',
                billNumber: 'BILL-MARKETA-0714',
                paymentMethod: 'Cash',
                paymentStatus: 'paid',
                paidAmount: 7415.00,
                productSkus: ['1', '3', '5', '12'],
            );

            $this->seedSubmittedPurchase(
                businessDate: $businessDate,
                purchaser: $purchaser,
                supplierName: 'Market B',
                cartNumber: 'VC-20260714-WH02',
                purchaseOrderNumber: 'PO-20260714-WH02',
                grnNumber: 'GRN-20260714-WH02',
                invoiceNumber: 'PINV-20260714-WH02',
                billNumber: 'BILL-MARKETB-0714',
                paymentMethod: 'Credit',
                paymentStatus: 'credit_pending_approval',
                paidAmount: 0.00,
                productSkus: ['23', '33', '60', '101'],
            );
        });

        $this->command?->info('Warehouse receiver purchase handoff seeded for July 14, 2026.');
    }

    /**
     * @param  array<int, string>  $productSkus
     */
    private function seedSubmittedPurchase(
        Carbon $businessDate,
        User $purchaser,
        string $supplierName,
        string $cartNumber,
        string $purchaseOrderNumber,
        string $grnNumber,
        string $invoiceNumber,
        string $billNumber,
        string $paymentMethod,
        string $paymentStatus,
        float $paidAmount,
        array $productSkus,
    ): void {
        $supplier = Supplier::query()->where('name', $supplierName)->firstOrFail();
        $lines = $this->approvedPurchaseLines($businessDate, $productSkus);
        $subtotal = collect($lines)->sum(
            fn (array $line): float => round((float) $line['quantity'] * (float) $line['unit_price'], 2)
        );

        $cart = PurchaserCart::query()->updateOrCreate(
            ['cart_number' => $cartNumber],
            [
                'user_id' => $purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $businessDate->toDateString(),
                'status' => 'submitted',
                'bill_number' => $billNumber,
                'discount_amount' => 0,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => 'Seeded purchaser handoff for warehouse receiver checking.',
                'payment_details' => null,
                'notes' => 'Purchaser purchase completed; awaiting warehouse receiver physical check.',
                'submitted_at' => $businessDate->copy()->setTime(8, 45),
                'whatsapp_sent_at' => $businessDate->copy()->setTime(8, 50),
                'goods_received_at' => null,
                'bill_received_at' => $businessDate->copy()->setTime(9, 5),
                'payment_made_at' => $paymentStatus === 'paid' ? $businessDate->copy()->setTime(9, 10) : null,
            ],
        );

        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['po_number' => $purchaseOrderNumber],
            [
                'supplier_id' => $supplier->id,
                'purchaser_cart_id' => $cart->id,
                'status' => POStatus::Received,
                'fulfillment_type' => 'warehouse',
                'order_date' => $businessDate->toDateString(),
                'created_by' => $purchaser->id,
                'notes' => 'Seeded from submitted purchaser cart for warehouse receiver checking.',
            ],
        );

        $goodsReceived = GoodsReceived::query()->updateOrCreate(
            ['grn_number' => $grnNumber],
            [
                'purchase_order_id' => $purchaseOrder->id,
                'purchaser_cart_id' => $cart->id,
                'status' => 'pending_approval',
                'rejection_remarks' => null,
                'received_by' => $purchaser->id,
                'approved_by' => null,
                'updated_by' => null,
                'received_at' => $businessDate->toDateString(),
                'approved_at' => null,
                'transport_cost' => 0,
                'labour_cost' => 0,
                'notes' => 'Vendor sheet submitted by purchaser; physical stock check pending.',
                'is_extra' => false,
            ],
        );

        $cart->update([
            'purchase_order_id' => $purchaseOrder->id,
            'goods_received_id' => $goodsReceived->id,
        ]);

        $products = collect($lines)
            ->mapWithKeys(function (array $line): array {
                $product = Product::query()->where('sku', $line['sku'])->firstOrFail();

                return [$line['sku'] => $product];
            });
        $productIds = $products->pluck('id')->all();

        PurchaserCartItem::query()
            ->where('purchaser_cart_id', $cart->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();

        PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();

        $goodsReceived->items()
            ->whereNotIn('product_id', $productIds)
            ->delete();

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $products[$line['sku']];
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);

            PurchaserCartItem::query()->updateOrCreate(
                [
                    'purchaser_cart_id' => $cart->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                    'is_extra_purchase' => false,
                    'notes' => 'Seeded purchaser completed quantity.',
                ],
            );

            $purchaseOrderItem = PurchaseOrderItem::query()->updateOrCreate(
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                ],
                [
                    'purchase_unit' => $product->unit,
                    'packet_qty' => null,
                    'weight_per_packet' => null,
                    'actual_weight' => null,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'price_basis' => $product->unit === 'kg' ? 'per_kg' : 'per_unit',
                ],
            );

            $goodsReceived->items()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'received_qty' => $line['quantity'],
                    'variance' => 0,
                    'purchased_qty' => $line['quantity'],
                    'discrepancy_type' => 'none',
                    'discrepancy_note' => null,
                ],
            );
        }

        $invoice = PurchaseInvoice::query()->updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'goods_received_id' => $goodsReceived->id,
                'supplier_id' => $supplier->id,
                'purchaser_cart_id' => $cart->id,
                'amount' => $subtotal,
                'discount_amount' => 0,
                'status' => InvoiceStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => 'Seeded purchaser bill for receiver handoff.',
                'payment_details' => null,
                'purchaser_submitted_by' => $purchaser->id,
                'purchaser_submitted_at' => $businessDate->copy()->setTime(9, 5),
                'notes' => 'Invoice check is complete from purchaser side; receiver stock check is pending.',
            ],
        );

        $cart->update([
            'purchase_invoice_id' => $invoice->id,
        ]);
    }

    private function clearSeededWorkflow(): void
    {
        DB::transaction(function (): void {
            $cartIds = PurchaserCart::query()
                ->where('cart_number', 'like', 'VC-20260714-WH%')
                ->pluck('id');

            $purchaseInvoiceIds = PurchaseInvoice::query()
                ->where('invoice_number', 'like', 'PINV-20260714-WH%')
                ->pluck('id');

            $goodsReceivedIds = GoodsReceived::query()
                ->where('grn_number', 'like', 'GRN-20260714-WH%')
                ->pluck('id');

            $purchaseOrderIds = PurchaseOrder::query()
                ->where('po_number', 'like', 'PO-20260714-WH%')
                ->pluck('id');

            $shopOrderIds = ShopOrder::query()
                ->whereDate('business_date', self::BUSINESS_DATE)
                ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
                ->pluck('id');

            $shopInvoiceIds = ShopInvoice::query()
                ->whereDate('business_date', self::BUSINESS_DATE)
                ->where(function ($query) use ($shopOrderIds): void {
                    $query->where('invoice_number', 'like', 'SINV-20260714-SHOP_JUL14_%')
                        ->when($shopOrderIds->isNotEmpty(), fn ($innerQuery) => $innerQuery->orWhereIn('shop_order_id', $shopOrderIds));
                })
                ->pluck('id');

            if ($shopInvoiceIds->isNotEmpty() || $purchaseInvoiceIds->isNotEmpty()) {
                JournalEntry::query()
                    ->where(function ($query) use ($shopInvoiceIds, $purchaseInvoiceIds): void {
                        $query
                            ->when($shopInvoiceIds->isNotEmpty(), function ($innerQuery) use ($shopInvoiceIds): void {
                                $innerQuery->orWhere(function ($sourceQuery) use ($shopInvoiceIds): void {
                                    $sourceQuery
                                        ->where('source_type', ShopInvoice::class)
                                        ->whereIn('source_id', $shopInvoiceIds);
                                });
                            })
                            ->when($purchaseInvoiceIds->isNotEmpty(), function ($innerQuery) use ($purchaseInvoiceIds): void {
                                $innerQuery->orWhere(function ($sourceQuery) use ($purchaseInvoiceIds): void {
                                    $sourceQuery
                                        ->where('source_type', PurchaseInvoice::class)
                                        ->whereIn('source_id', $purchaseInvoiceIds);
                                });
                            });
                    })
                    ->delete();
            }

            if ($cartIds->isNotEmpty()) {
                PurchaserCart::query()
                    ->whereIn('id', $cartIds)
                    ->update([
                        'purchase_order_id' => null,
                        'goods_received_id' => null,
                        'purchase_invoice_id' => null,
                    ]);

                PurchaserCartItem::query()->whereIn('purchaser_cart_id', $cartIds)->delete();
            }

            if ($purchaseInvoiceIds->isNotEmpty()) {
                PurchaseInvoice::query()->whereIn('id', $purchaseInvoiceIds)->forceDelete();
            }

            if ($goodsReceivedIds->isNotEmpty()) {
                StockBatch::query()->whereIn('goods_received_id', $goodsReceivedIds)->forceDelete();

                GoodsReceived::query()->whereIn('id', $goodsReceivedIds)->forceDelete();
            }

            if ($purchaseOrderIds->isNotEmpty()) {
                PurchaseOrderItem::query()->whereIn('purchase_order_id', $purchaseOrderIds)->forceDelete();
                PurchaseOrder::query()->whereIn('id', $purchaseOrderIds)->forceDelete();
            }

            if ($cartIds->isNotEmpty()) {
                PurchaserCart::query()->whereIn('id', $cartIds)->delete();
            }

            if ($shopInvoiceIds->isNotEmpty()) {
                ShopInvoice::query()->whereIn('id', $shopInvoiceIds)->delete();
            }

            if ($shopOrderIds->isNotEmpty()) {
                ShopOrderItem::query()->whereIn('shop_order_id', $shopOrderIds)->delete();
                ShopOrder::query()->whereIn('id', $shopOrderIds)->delete();
            }
        });
    }

    private function approveShopOwnerOrders(Carbon $businessDate, User $purchaseManager): void
    {
        ShopOrder::query()
            ->whereDate('business_date', $businessDate)
            ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
            ->update([
                'state' => 'approved',
                'delivery_status' => 'pending_delivery',
                'reviewed_by' => $purchaseManager->id,
                'reviewed_at' => $businessDate->copy()->setTime(7, 45),
                'manager_note' => 'Seeded purchase manager approval before purchaser buying.',
                'is_allocation_completed' => false,
                'is_delivered' => false,
            ]);
    }

    /**
     * @param  array<int, string>  $productSkus
     * @return array<int, array{sku:string, quantity:float, unit_price:float}>
     */
    private function approvedPurchaseLines(Carbon $businessDate, array $productSkus): array
    {
        /** @var Collection<string, Product> $products */
        $products = Product::query()
            ->whereIn('sku', $productSkus)
            ->get()
            ->keyBy('sku');

        $approvedQuantities = ShopOrderItem::query()
            ->whereIn('product_id', $products->pluck('id')->all())
            ->whereHas('order', function ($query) use ($businessDate): void {
                $query
                    ->whereDate('business_date', $businessDate)
                    ->where('state', 'approved');
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(approved_qty) as total_qty'))
            ->pluck('total_qty', 'product_id');

        return collect($productSkus)
            ->map(function (string $sku) use ($products, $approvedQuantities): array {
                /** @var Product $product */
                $product = $products->get($sku) ?? Product::query()->where('sku', $sku)->firstOrFail();
                $quantity = (float) ($approvedQuantities[$product->id] ?? 0.0);

                if ($quantity <= 0.0) {
                    throw new \RuntimeException("No approved shop-owner quantity found for product SKU {$sku}.");
                }

                return [
                    'sku' => $sku,
                    'quantity' => round($quantity, 3),
                    'unit_price' => $this->unitPriceFor($product),
                ];
            })
            ->all();
    }

    private function unitPriceFor(Product $product): float
    {
        $unitPrice = (float) ($product->vendor_price ?: $product->base_price ?: 0);

        if ($unitPrice > 0.0) {
            return round($unitPrice, 4);
        }

        return round(random_int(1800, 9500) / 100, 4);
    }
}
