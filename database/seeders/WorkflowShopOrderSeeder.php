<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowShopOrderSeeder extends Seeder
{
    private const ORDERS_PER_SHOP = 2;

    private const ITEMS_PER_ORDER = 5;

    private const ORDER_SOURCE = 'seeded_shop_workflow';

    public function run(): void
    {
        $businessDate = app(PurchaserBusinessDayService::class)->operationalDate();
        $submittedDate = $businessDate->copy()->subDay();
        $deadlineAt = app(PurchaserBusinessDayService::class)->rolloverStartsAt($submittedDate);
        $products = $this->seedableProducts();
        $shops = Shop::query()
            ->where('status', 'active')
            ->with('users')
            ->orderBy('name')
            ->get();

        if ($products->count() < self::ITEMS_PER_ORDER) {
            $this->command?->warn('Workflow shop order seeder skipped: not enough active products.');

            return;
        }

        DB::transaction(function () use ($shops, $products, $businessDate, $submittedDate, $deadlineAt): void {
            $createdOrders = 0;

            foreach ($shops as $shop) {
                $creator = $shop->users->first() ?? User::role('shop')->where('shop_id', $shop->id)->first();

                if (! $creator instanceof User) {
                    continue;
                }

                for ($orderIndex = 1; $orderIndex <= self::ORDERS_PER_SHOP; $orderIndex++) {
                    $submittedAt = $this->submittedAt($submittedDate, $orderIndex);

                    $order = ShopOrder::query()->create([
                        'shop_id' => $shop->id,
                        'order_source' => self::ORDER_SOURCE,
                        'shop_daily_order_key' => null,
                        'state' => 'submitted',
                        'delivery_status' => 'pending_delivery',
                        'delivery_review_status' => 'not_started',
                        'payment_status' => 'unpaid',
                        'business_date' => $businessDate->toDateString(),
                        'submitted_at' => $submittedAt,
                        'deadline_at' => $deadlineAt,
                        'created_by' => $creator->id,
                        'is_late' => false,
                    ]);

                    $this->createItems($order, $products, $orderIndex);
                    $createdOrders++;
                }
            }

            $this->command?->info(sprintf(
                'Seeded %d submitted shop orders for %s. Submitted on %s before %s.',
                $createdOrders,
                $businessDate->format('d M Y'),
                $submittedDate->format('d M Y'),
                $deadlineAt->format('h:i A'),
            ));
        });
    }

    /**
     * @return Collection<int, Product>
     */
    private function seedableProducts(): Collection
    {
        return Product::query()
            ->active()
            ->whereIn('unit', ['kg', 'pcs', 'bag', 'box', 'roll'])
            ->ordered()
            ->get();
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function createItems(ShopOrder $order, Collection $products, int $orderIndex): void
    {
        $selectedProducts = $products
            ->shuffle()
            ->take(self::ITEMS_PER_ORDER)
            ->values();

        foreach ($selectedProducts as $itemIndex => $product) {
            ShopOrderItem::query()->create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'product_grade' => 'A',
                'requested_qty' => $this->quantityFor($product, $orderIndex, $itemIndex),
                'approved_qty' => null,
                'unit' => $product->unit,
                'locked_selling_price' => 0,
                'locked_price_source' => 'margin',
                'line_total' => 0,
                'fulfillment_type' => 'warehouse',
                'sorting_status' => 'pending',
                'notes' => 'Seeded for today purchase approval workflow.',
            ]);
        }
    }

    private function quantityFor(Product $product, int $orderIndex, int $itemIndex): float
    {
        $base = (($orderIndex + 1) * 3) + $itemIndex;

        return match ($product->unit) {
            'pcs', 'bag', 'box', 'roll' => (float) max(1, $base),
            default => (float) max(1, $base * 2),
        };
    }

    private function submittedAt(Carbon $submittedDate, int $orderIndex): Carbon
    {
        return $submittedDate
            ->copy()
            ->setTime(18 + $orderIndex, 10 + ($orderIndex * 7), 0);
    }
}
