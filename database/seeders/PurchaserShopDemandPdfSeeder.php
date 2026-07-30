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
use Illuminate\Support\Facades\DB;
use JsonException;

class PurchaserShopDemandPdfSeeder extends Seeder
{
    private const DATA_FILE = 'seeders/data/purchaser_shop_demand_2026_07_28.json';

    private const ORDER_SOURCE = 'pdf_shop_demand_2026_07_28';

    /**
     * @throws JsonException
     */
    public function run(): void
    {
        $payload = json_decode(
            file_get_contents(database_path(self::DATA_FILE)) ?: '',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $businessDate = Carbon::parse($payload['business_date']);
        $submittedDate = $businessDate->copy()->subDay();
        $deadlineAt = app(PurchaserBusinessDayService::class)->rolloverStartsAt($submittedDate);
        $reviewer = User::role('purchase')->first() ?? User::role('admin')->first();

        DB::transaction(function () use ($payload, $businessDate, $submittedDate, $deadlineAt, $reviewer): void {
            $createdOrders = 0;
            $createdItems = 0;
            $missingProducts = [];
            $missingShops = [];

            foreach ($payload['shops'] as $shopData) {
                $shop = Shop::query()->where('code', $shopData['shop_code'])->first();

                if (! $shop instanceof Shop) {
                    $missingShops[] = $shopData['shop_code'];

                    continue;
                }

                $creator = User::role('shop')->where('shop_id', $shop->id)->first()
                    ?? $shop->users()->first()
                    ?? $reviewer;

                if (! $creator instanceof User) {
                    continue;
                }

                $order = ShopOrder::query()->updateOrCreate(
                    [
                        'shop_daily_order_key' => $this->dailyOrderKey($shop->code, $businessDate),
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
                        'created_by' => $creator->id,
                        'reviewed_by' => $reviewer?->id,
                        'reviewed_at' => $businessDate->copy()->setTime(8, 0),
                        'manager_note' => 'Seeded from Order Form 28-07 PDF.',
                        'is_late' => false,
                    ],
                );

                $order->items()->delete();

                foreach ($shopData['items'] as $itemData) {
                    $product = Product::query()->where('sku', (string) $itemData['product_sku'])->first();

                    if (! $product instanceof Product) {
                        $missingProducts[] = (string) $itemData['product_sku'];

                        continue;
                    }

                    ShopOrderItem::query()->create([
                        'shop_order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_grade' => 'A',
                        'requested_qty' => $itemData['quantity'],
                        'approved_qty' => $itemData['quantity'],
                        'unit' => $product->unit,
                        'requested_unit' => $product->unit,
                        'requested_unit_label' => strtoupper((string) $product->unit),
                        'requested_unit_quantity' => $itemData['quantity'],
                        'requested_unit_conversion_to_base' => 1,
                        'locked_selling_price' => 0,
                        'locked_price_source' => 'pdf_seed',
                        'line_total' => 0,
                        'fulfillment_type' => 'warehouse',
                        'sorting_status' => 'pending',
                        'notes' => 'Seeded from Order Form 28-07 PDF.',
                    ]);

                    $createdItems++;
                }

                $createdOrders++;
            }

            if ($missingShops !== []) {
                $this->command?->warn('Missing shops: '.implode(', ', array_unique($missingShops)));
            }

            if ($missingProducts !== []) {
                $this->command?->warn('Missing products: '.implode(', ', array_unique($missingProducts)));
            }

            $this->command?->info(sprintf(
                'Seeded %d approved shop demand orders with %d items from %s.',
                $createdOrders,
                $createdItems,
                self::DATA_FILE,
            ));
        });
    }

    private function dailyOrderKey(string $shopCode, Carbon $businessDate): string
    {
        return sprintf('pdf-shop-demand:%s:%s', $shopCode, $businessDate->toDateString());
    }
}
