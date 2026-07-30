<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrder20260729Seeder extends Seeder
{
    private const SUPPLIER_NAME = 'Seeded Daily Market Purchase';

    private const ORDER_SOURCE = 'seeded_purchase_order_today';

    /**
     * Format: SKU|Per-Shop Approved Quantity|Unit Price
     *
     * Two fruit and two veg products are seeded for every active shop.
     */
    private const ITEM_LINES = <<<'DATA'
181|8|1
182|6|1
12|10|1
13|7|1
DATA;

    public function run(): void
    {
        $creator = User::role('purchase')->first()
            ?? User::query()->first();

        if (! $creator instanceof User) {
            $this->command?->warn(
                'PurchaseOrder20260729Seeder skipped: no user available for created_by.'
            );

            return;
        }

        $businessDate = Carbon::today();
        $submittedDate = $businessDate->copy()->subDay();
        $deadlineAt = app(PurchaserBusinessDayService::class)->rolloverStartsAt($submittedDate);

        DB::transaction(function () use ($creator, $businessDate, $submittedDate, $deadlineAt): void {
            $supplier = Supplier::query()->updateOrCreate(
                [
                    'name' => self::SUPPLIER_NAME,
                ],
                [
                    'type' => 'Market Agent',
                    'category' => 'own_purchase',
                    'is_default_purchase' => true,
                    'payment_terms' => 'COD',
                    'quality_score' => 100.00,
                ],
            );

            $shops = Shop::query()
                ->active()
                ->with('users')
                ->orderBy('name')
                ->get();

            if ($shops->isEmpty()) {
                $this->command?->warn('PurchaseOrder20260729Seeder skipped shop demand: no active shops found.');
            }

            $itemLines = $this->itemLines();
            $productsBySku = Product::query()
                ->active()
                ->with('category')
                ->whereIn('sku', array_keys($itemLines))
                ->get()
                ->keyBy(fn (Product $product): string => (string) $product->sku);

            $missingSkus = collect(array_keys($itemLines))
                ->reject(fn (string $sku): bool => $productsBySku->has($sku))
                ->values()
                ->all();

            if ($missingSkus !== []) {
                $this->command?->warn('Missing active products: '.implode(', ', $missingSkus));
            }

            $poNumber = sprintf('PO-%s-SEED', $businessDate->format('Ymd'));
            $purchaseOrder = PurchaseOrder::withTrashed()
                ->where('po_number', $poNumber)
                ->first();

            if ($purchaseOrder instanceof PurchaseOrder) {
                if ($purchaseOrder->trashed()) {
                    $purchaseOrder->restore();
                }

                $purchaseOrder->forceFill([
                    'supplier_id' => $supplier->id,
                    'status' => POStatus::Approved,
                    'order_date' => $businessDate->toDateString(),
                    'created_by' => $creator->id,
                    'fulfillment_type' => 'warehouse',
                    'notes' => 'Seeded purchase order containing two fruit and two veg products for all shops.',
                ])->save();
            } else {
                $purchaseOrder = PurchaseOrder::query()->create([
                    'supplier_id' => $supplier->id,
                    'po_number' => $poNumber,
                    'status' => POStatus::Approved,
                    'order_date' => $businessDate->toDateString(),
                    'created_by' => $creator->id,
                    'fulfillment_type' => 'warehouse',
                    'notes' => 'Seeded purchase order containing two fruit and two veg products for all shops.',
                ]);
            }

            /*
             * Remove the previous items before inserting the current four items.
             * This makes the seeder safe to run repeatedly.
             */
            PurchaseOrderItem::withTrashed()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->forceDelete();

            foreach ($itemLines as $sku => $line) {
                $product = $productsBySku->get((string) $sku);

                if (! $product instanceof Product) {
                    $this->command?->warn(
                        sprintf('Product with SKU %s was not found or is inactive.', $sku)
                    );

                    continue;
                }

                $purchaseOrder->items()->create([
                    'product_id' => $product->id,
                    'purchase_unit' => $product->unit,
                    'quantity' => $line['per_shop_quantity'] * max(1, $shops->count()),
                    'unit_price' => $line['unit_price'],
                    'price_basis' => 'per_kg',
                ]);
            }

            $createdOrders = 0;
            $createdItems = 0;

            foreach ($shops as $shop) {
                $shopCreator = $shop->users->first() ?? User::role('shop')->where('shop_id', $shop->id)->first() ?? $creator;

                $order = ShopOrder::query()->updateOrCreate(
                    [
                        'shop_daily_order_key' => $this->dailyOrderKey((int) $shop->id, $businessDate),
                    ],
                    [
                        'shop_id' => $shop->id,
                        'order_source' => self::ORDER_SOURCE,
                        'state' => 'approved',
                        'delivery_status' => 'pending_delivery',
                        'delivery_review_status' => 'not_started',
                        'payment_status' => 'unpaid',
                        'business_date' => $businessDate->toDateString(),
                        'submitted_at' => $submittedDate->copy()->setTime(19, 0),
                        'deadline_at' => $deadlineAt,
                        'created_by' => $shopCreator->id,
                        'reviewed_by' => $creator->id,
                        'reviewed_at' => $businessDate->copy()->setTime(8, 0),
                        'manager_note' => 'Seeded four-item demand for today.',
                        'is_late' => false,
                    ],
                );

                $order->items()->delete();

                foreach ($itemLines as $sku => $line) {
                    $product = $productsBySku->get((string) $sku);

                    if (! $product instanceof Product) {
                        continue;
                    }

                    ShopOrderItem::query()->create([
                        'shop_order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_grade' => 'A',
                        'requested_qty' => $line['per_shop_quantity'],
                        'approved_qty' => $line['per_shop_quantity'],
                        'unit' => $product->unit,
                        'requested_unit' => $product->unit,
                        'requested_unit_label' => strtoupper((string) $product->unit),
                        'requested_unit_quantity' => $line['per_shop_quantity'],
                        'requested_unit_conversion_to_base' => 1,
                        'locked_selling_price' => 0,
                        'locked_price_source' => 'seed',
                        'line_total' => 0,
                        'fulfillment_type' => 'warehouse',
                        'sorting_status' => 'pending',
                        'notes' => 'Seeded four-item demand for today.',
                    ]);

                    $createdItems++;
                }

                $createdOrders++;
            }

            $itemCount = $purchaseOrder->items()->count();

            $this->command?->info(sprintf(
                'Seeded %s with %d PO items and %d approved shop orders / %d shop items for %s.',
                $poNumber,
                $itemCount,
                $createdOrders,
                $createdItems,
                $businessDate->toDateString(),
            ));
        });
    }

    /**
     * @return array<string, array{per_shop_quantity: float, unit_price: float}>
     */
    private function itemLines(): array
    {
        $items = [];

        foreach (explode("\n", trim(self::ITEM_LINES)) as $line) {
            $columns = explode('|', trim($line));

            if (count($columns) !== 3) {
                $this->command?->warn(
                    sprintf('Invalid purchase-order item line skipped: %s', $line)
                );

                continue;
            }

            [$sku, $quantity, $unitPrice] = $columns;

            $items[trim($sku)] = [
                'per_shop_quantity' => (float) trim($quantity),
                'unit_price' => (float) trim($unitPrice),
            ];
        }

        return $items;
    }

    private function dailyOrderKey(int $shopId, Carbon $businessDate): string
    {
        return sprintf('seeded-po-demand:%d:%s', $shopId, $businessDate->toDateString());
    }
}
