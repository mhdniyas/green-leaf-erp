<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\ProductWholesalePrice;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Database\Seeder;

class PriceBoardSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PriceBoardService $priceBoardService */
        $priceBoardService = app(PriceBoardService::class);
        $groups = $priceBoardService->ensureDefaultPriceGroups();
        $defaultGroup = $priceBoardService->defaultGroup();

        Shop::query()
            ->whereNull('shop_price_group_id')
            ->update(['shop_price_group_id' => $defaultGroup->id]);

        $shopAssignments = [
            'SHOP_CASIO' => 'A',
            'SHOP_BUDEGERE' => 'B',
            'SHOP_ASHIRWAD' => 'A',
            'SHOP_BEGUR' => 'B',
            'SHOP_BAZARO' => 'A',
            'SHOP_CARRY' => 'C',
        ];

        foreach ($shopAssignments as $shopCode => $name) {
            $group = ShopPriceGroup::query()
                ->where('name', $name)
                ->first();

            if ($group) {
                Shop::query()
                    ->where('code', $shopCode)
                    ->update(['shop_price_group_id' => $group->id]);
            }
        }

        Product::query()
            ->active()
            ->select(['id', 'base_price'])
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    foreach ([ProductGrade::GradeA, ProductGrade::GradeB, ProductGrade::GradeC] as $grade) {
                        ProductWholesalePrice::query()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'grade' => $grade->value,
                            ],
                            [
                                'weighted_average_cost' => $product->base_price,
                                'wholesale_price' => $product->base_price,
                                'sellable_quantity' => 0,
                                'total_cost' => 0,
                                'source_type' => 'seed',
                                'source_reference' => 'base_price',
                                'calculated_at' => now(),
                            ]
                        );
                    }
                }
            });

        foreach ($groups as $group) {
            $priceBoardService->ensureSellingPricesForGroup($group);
        }

        $this->command->info('✅ Price groups, wholesale prices, and selling prices seeded successfully.');
    }
}
