<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DailyShopOrderTestSeeder extends Seeder
{
    private const ORDER_SOURCE = 'daily_shop_order_test_seed';

    public function run(): void
    {
        $today = Carbon::today(config('app.timezone'));

        $reviewer = $this->firstUserForRole('purchase')
            ?? $this->firstUserForRole('purchaser')
            ?? $this->firstUserForRole('admin')
            ?? User::query()->first();

        if (! $reviewer instanceof User) {
            $this->command?->warn('DailyShopOrderTestSeeder skipped: no user available.');

            return;
        }

        /** @var Collection<int, Product> $fruits */
        $fruits = Product::query()
            ->active()
            ->whereHas('category', fn ($query) => $query->where('name', 'Frut'))
            ->ordered()
            ->get();

        /** @var Collection<int, Product> $vegetables */
        $vegetables = Product::query()
            ->active()
            ->whereHas('category', fn ($query) => $query->where('name', 'VEG'))
            ->ordered()
            ->get();

        if ($fruits->count() < 2 || $vegetables->count() < 3) {
            $this->command?->warn('DailyShopOrderTestSeeder skipped: needs at least 2 active Frut products and 3 active VEG products.');

            return;
        }

        $shops = Shop::query()->active()->orderBy('code')->get();

        if ($shops->isEmpty()) {
            $this->command?->warn('DailyShopOrderTestSeeder skipped: no active shops available.');

            return;
        }

        DB::transaction(function () use ($today, $fruits, $vegetables, $shops, $reviewer): void {
            $createdOrders = 0;
            $createdItems = 0;
            $businessDate = $today;

            ShopOrder::query()
                ->where('order_source', self::ORDER_SOURCE)
                ->whereDate('business_date', '!=', $businessDate->toDateString())
                ->delete();

            foreach ($shops as $shop) {
                $creator = $this->firstUserForRole('shop', $shop->id)
                    ?? $shop->users()->first()
                    ?? $reviewer;

                $order = ShopOrder::query()->updateOrCreate(
                    ['shop_daily_order_key' => $this->dailyOrderKey($shop->code, $businessDate)],
                    [
                        'shop_id' => $shop->id,
                        'order_source' => self::ORDER_SOURCE,
                        'state' => 'approved',
                        'delivery_status' => 'pending_delivery',
                        'delivery_review_status' => 'not_started',
                        'payment_status' => 'unpaid',
                        'business_date' => $businessDate->toDateString(),
                        'submitted_at' => $businessDate->copy()->subDay()->setTime(19, 0),
                        'deadline_at' => app(PurchaserBusinessDayService::class)->rolloverStartsAt($businessDate->copy()->subDay()),
                        'created_by' => $creator->id,
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => $businessDate->copy()->setTime(8, 0),
                        'manager_note' => 'Seeded daily test order: 2 fruits and 3 vegetables.',
                        'is_late' => false,
                    ],
                );

                $order->items()->delete();
                $createdOrders++;

                $products = $this->pickProducts($fruits, 2, $shop->code, $businessDate, 'fruit')
                    ->merge($this->pickProducts($vegetables, 3, $shop->code, $businessDate, 'veg'));

                foreach ($products->values() as $index => $product) {
                    $quantity = $this->quantityFor($shop->code, $businessDate, (string) $product->sku, $index);

                    ShopOrderItem::query()->create([
                        'shop_order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_grade' => 'A',
                        'requested_qty' => $quantity,
                        'approved_qty' => $quantity,
                        'unit' => $product->unit,
                        'requested_unit' => $product->unit,
                        'requested_unit_label' => strtoupper((string) $product->unit),
                        'requested_unit_quantity' => $quantity,
                        'requested_unit_conversion_to_base' => 1,
                        'locked_selling_price' => 0,
                        'locked_price_source' => 'test_seed',
                        'line_total' => 0,
                        'fulfillment_type' => 'warehouse',
                        'sorting_status' => 'pending',
                        'notes' => 'Daily test seed item.',
                    ]);

                    $createdItems++;
                }
            }

            $this->command?->info(sprintf(
                'Seeded %d daily test shop orders with %d items for %s.',
                $createdOrders,
                $createdItems,
                $today->toDateString(),
            ));
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function pickProducts(Collection $products, int $count, string $shopCode, Carbon $businessDate, string $type): Collection
    {
        return $products
            ->sortBy(fn (Product $product): string => sha1($shopCode.'|'.$businessDate->toDateString().'|'.$type.'|'.$product->sku))
            ->take($count)
            ->values();
    }

    private function quantityFor(string $shopCode, Carbon $businessDate, string $sku, int $index): float
    {
        $hash = crc32($shopCode.'|'.$businessDate->toDateString().'|'.$sku.'|'.$index);

        return (float) (2 + ($hash % 19));
    }

    private function dailyOrderKey(string $shopCode, Carbon $businessDate): string
    {
        return sprintf('%s:%s:%s', self::ORDER_SOURCE, $shopCode, $businessDate->toDateString());
    }

    private function firstUserForRole(string $roleName, ?int $shopId = null): ?User
    {
        if (! Role::query()->where('guard_name', 'web')->where('name', $roleName)->exists()) {
            return null;
        }

        $query = User::role($roleName);

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        $user = $query->first();

        return $user instanceof User ? $user : null;
    }
}
