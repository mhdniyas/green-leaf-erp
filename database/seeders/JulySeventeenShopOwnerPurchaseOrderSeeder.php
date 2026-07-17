<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JulySeventeenShopOwnerPurchaseOrderSeeder extends Seeder
{
    private const BUSINESS_DATE = '2026-07-17';

    private const SOURCE_DATE = '2026-07-16';

    private const PRODUCTS_PER_SHOP = 5;

    private const PRODUCT_SKUS = ['1', '3', '5', '12', '23', '33', '60', '101'];

    private const SHOP_CODES = [
        'SHOP_CASIO',
        'SHOP_BUDEGERE',
        'SHOP_GRANCITY',
        'SHOP_ASHIRWAD',
    ];

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

        $this->clearDemoWorkflow();

        DB::transaction(function (): void {
            $businessDate = Carbon::parse(self::BUSINESS_DATE)->startOfDay();
            $sourceDate = Carbon::parse(self::SOURCE_DATE)->startOfDay();
            $deadlineAt = $sourceDate->copy()->setTime(21, 30);
            $purchaseManager = User::query()->where('email', 'purchase@greenleaf.com')->firstOrFail();
            $products = Product::query()
                ->whereIn('sku', self::PRODUCT_SKUS)
                ->where('is_active', true)
                ->ordered()
                ->get()
                ->values();

            if ($products->count() < self::PRODUCTS_PER_SHOP) {
                throw new \RuntimeException('JulySeventeenShopOwnerPurchaseOrderSeeder requires at least five active seeded products.');
            }

            foreach (self::SHOP_CODES as $shopIndex => $shopCode) {
                $shop = Shop::query()->where('code', $shopCode)->firstOrFail();
                $shopOwner = User::query()
                    ->where('email', 'shop-'.str($shopCode)->lower()->replace('_', '-').'@greenleaf.com')
                    ->firstOrFail();

                $order = ShopOrder::query()->updateOrCreate(
                    ['order_number' => sprintf('RQ-SHOP-%s-%02d', $businessDate->format('Ymd'), $shopIndex + 1)],
                    [
                        'shop_id' => $shop->id,
                        'business_date' => $businessDate->toDateString(),
                        'order_source' => 'shop_owner',
                        'state' => 'approved',
                        'delivery_status' => 'pending_delivery',
                        'payment_status' => 'unpaid',
                        'is_late' => false,
                        'submitted_at' => $sourceDate->copy()->setTime(18 + $shopIndex, 15 + ($shopIndex * 5)),
                        'deadline_at' => $deadlineAt,
                        'created_by' => $shopOwner->id,
                        'reviewed_by' => $purchaseManager->id,
                        'reviewed_at' => $businessDate->copy()->setTime(7, 45),
                        'manager_note' => 'Seeded from shop-owner orders submitted on July 16 before the 9:30 PM cutoff.',
                        'latest_revision_no' => 1,
                        'has_pending_revision' => false,
                        'is_allocation_completed' => false,
                        'is_delivered' => false,
                        'cash_collected' => 0,
                        'cash_discrepancy' => 0,
                        'balance_amount' => 0,
                        'total_shortage_value' => 0,
                    ],
                );

                $this->seedOrderItems($order, $products, $shopIndex);
            }

            $this->seedPurchaseOrdersFromShopOrders($businessDate, $purchaseManager);
        });

        $this->command?->info('Seeded July 17 shop-owner orders and generated purchase orders from July 16 cutoff demand.');
    }

    private function clearDemoWorkflow(): void
    {
        DB::transaction(function (): void {
            $shopOrderIds = ShopOrder::query()
                ->where(function ($query): void {
                    $query
                        ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
                        ->orWhere('order_number', 'like', 'RQ-SHOP-20260717-%');
                })
                ->pluck('id');

            $shopInvoiceIds = ShopInvoice::query()
                ->where(function ($query) use ($shopOrderIds): void {
                    $query
                        ->where('invoice_number', 'like', 'SINV-20260714-SHOP_JUL14_%')
                        ->orWhere('invoice_number', 'like', 'SINV-20260717-%')
                        ->when($shopOrderIds->isNotEmpty(), fn ($innerQuery) => $innerQuery->orWhereIn('shop_order_id', $shopOrderIds));
                })
                ->pluck('id');

            $cartIds = PurchaserCart::query()
                ->where(function ($query): void {
                    $query
                        ->where('cart_number', 'like', 'VC-20260714-WH%')
                        ->orWhere('cart_number', 'like', 'VC-20260717-%');
                })
                ->pluck('id');

            $purchaseInvoiceIds = PurchaseInvoice::query()
                ->where(function ($query): void {
                    $query
                        ->where('invoice_number', 'like', 'PINV-20260714-WH%')
                        ->orWhere('invoice_number', 'like', 'PINV-20260717-%');
                })
                ->pluck('id');

            $goodsReceivedIds = GoodsReceived::query()
                ->where(function ($query): void {
                    $query
                        ->where('grn_number', 'like', 'GRN-20260714-WH%')
                        ->orWhere('grn_number', 'like', 'GRN-20260717-%');
                })
                ->pluck('id');

            $purchaseOrderIds = PurchaseOrder::query()
                ->where(function ($query): void {
                    $query
                        ->where('po_number', 'like', 'PO-20260714-WH%')
                        ->orWhere('po_number', 'like', 'PO-SHOP-20260717-%');
                })
                ->pluck('id');

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
                $stockBatchIds = StockBatch::query()
                    ->whereIn('goods_received_id', $goodsReceivedIds)
                    ->pluck('id');

                if ($stockBatchIds->isNotEmpty()) {
                    StockMovement::query()->whereIn('batch_id', $stockBatchIds)->delete();
                    StockBatch::query()->whereIn('id', $stockBatchIds)->forceDelete();
                }

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

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedOrderItems(ShopOrder $order, Collection $products, int $shopIndex): void
    {
        $selectedProducts = collect(range(0, self::PRODUCTS_PER_SHOP - 1))
            ->map(fn (int $offset): Product => $products[($shopIndex + $offset) % $products->count()])
            ->values();

        ShopOrderItem::query()
            ->where('shop_order_id', $order->id)
            ->whereNotIn('product_id', $selectedProducts->pluck('id')->all())
            ->delete();

        foreach ($selectedProducts as $itemIndex => $product) {
            $requestedQuantity = (float) (6 + $shopIndex + $itemIndex);
            $unitPrice = max(1.0, round((float) ($product->base_price ?? $product->vendor_price ?? 1.0), 2));

            ShopOrderItem::query()->updateOrCreate(
                [
                    'shop_order_id' => $order->id,
                    'product_id' => $product->id,
                ],
                [
                    'product_grade' => 'A',
                    'requested_qty' => $requestedQuantity,
                    'approved_qty' => $requestedQuantity,
                    'unit' => $product->unit,
                    'locked_selling_price' => $unitPrice,
                    'locked_price_source' => 'seeded_order',
                    'line_total' => round($requestedQuantity * $unitPrice, 2),
                    'notes' => 'Seeded shop-owner order item for July 17, 2026.',
                    'fulfillment_type' => 'warehouse',
                    'sorting_status' => 'pending',
                    'is_sorted' => false,
                    'delivered_qty' => 0,
                    'shortage_qty' => 0,
                    'unit_cost' => round($unitPrice * 0.82, 4),
                    'shortage_value' => 0,
                ],
            );
        }
    }

    private function seedPurchaseOrdersFromShopOrders(Carbon $businessDate, User $purchaseManager): void
    {
        $supplier = Supplier::query()
            ->where('is_default_purchase', true)
            ->first()
            ?? Supplier::query()->firstOrFail();

        $approvedQuantities = ShopOrderItem::query()
            ->whereHas('order', function ($query) use ($businessDate): void {
                $query
                    ->whereDate('business_date', $businessDate)
                    ->where('state', 'approved')
                    ->where('order_number', 'like', 'RQ-SHOP-20260717-%');
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(approved_qty) as total_qty'))
            ->pluck('total_qty', 'product_id');

        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['po_number' => 'PO-SHOP-20260717-001'],
            [
                'supplier_id' => $supplier->id,
                'status' => POStatus::Approved,
                'fulfillment_type' => 'warehouse',
                'order_date' => $businessDate->toDateString(),
                'created_by' => $purchaseManager->id,
                'notes' => 'Seeded from July 16 shop-owner demand for July 17 purchase processing.',
            ],
        );

        PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereNotIn('product_id', $approvedQuantities->keys()->all())
            ->forceDelete();

        Product::query()
            ->whereIn('id', $approvedQuantities->keys()->all())
            ->get()
            ->each(function (Product $product) use ($purchaseOrder, $approvedQuantities): void {
                $quantity = round((float) $approvedQuantities[$product->id], 3);

                PurchaseOrderItem::query()->updateOrCreate(
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'purchase_unit' => $product->unit,
                        'packet_qty' => null,
                        'weight_per_packet' => null,
                        'actual_weight' => null,
                        'quantity' => $quantity,
                        'unit_price' => $this->unitPriceFor($product),
                        'price_basis' => $product->unit === 'kg' ? 'per_kg' : 'per_unit',
                    ],
                );
            });
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
