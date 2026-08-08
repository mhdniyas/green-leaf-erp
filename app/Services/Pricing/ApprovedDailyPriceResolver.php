<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopPriceGroup;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ApprovedDailyPriceResolver
{
    /**
     * @return array{
     *     source: string,
     *     approval: DailyPriceApproval|null,
     *     special_price: ShopDailyProductPrice|null,
     *     group: ShopPriceGroup,
     *     price: float,
     *     price_unit: string,
     *     price_column: string,
     *     category_code: string
     * }
     */
    public function resolve(Product $product, Shop $shop, CarbonInterface|string $businessDate): array
    {
        $group = $shop->priceGroup;

        if (! $group instanceof ShopPriceGroup || ! $group->is_active) {
            throw ValidationException::withMessages([
                'prices' => 'This shop does not have an active price category assigned.',
            ]);
        }

        $specialPrice = ShopDailyProductPrice::query()
            ->whereDate('business_date', $businessDate)
            ->where('shop_id', $shop->id)
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->first();

        if ($specialPrice instanceof ShopDailyProductPrice) {
            $price = round((float) $specialPrice->selling_price, 2);

            if ($price <= 0.0) {
                throw ValidationException::withMessages([
                    'prices' => "Special price for {$product->name} is invalid.",
                ]);
            }

            return [
                'source' => 'special',
                'approval' => null,
                'special_price' => $specialPrice,
                'group' => $group,
                'price' => $price,
                'price_unit' => ProductUnit::normalizeUnit((string) ($specialPrice->price_unit ?: $product->unit ?: 'kg')),
                'price_column' => 'special',
                'category_code' => 'SPECIAL',
            ];
        }

        $priceColumn = $this->priceColumnForGroup($group);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', $businessDate)
            ->first();

        if (! $approval instanceof DailyPriceApproval) {
            throw ValidationException::withMessages([
                'prices' => "Approved daily price is missing for {$product->name}.",
            ]);
        }

        if ($approval->status !== 'approved' || $approval->approved_at === null) {
            throw ValidationException::withMessages([
                'prices' => "Daily price for {$product->name} has not been approved by admin.",
            ]);
        }

        $price = round((float) $approval->{$priceColumn}, 2);

        if ($price <= 0.0) {
            throw ValidationException::withMessages([
                'prices' => "Approved daily price for {$product->name} is invalid.",
            ]);
        }

        return [
            'source' => 'normal',
            'approval' => $approval,
            'special_price' => null,
            'group' => $group,
            'price' => $price,
            'price_unit' => ProductUnit::normalizeUnit((string) ($approval->price_unit ?: $product->unit ?: 'kg')),
            'price_column' => $priceColumn,
            'category_code' => strtoupper((string) $group->name),
        ];
    }

    private function priceColumnForGroup(ShopPriceGroup $group): string
    {
        return match (strtoupper((string) $group->name)) {
            'A' => 'price_a',
            'B' => 'price_b',
            'C' => 'price_c',
            default => throw ValidationException::withMessages([
                'prices' => "Shop price category {$group->name} is not mapped to the approved daily price table.",
            ]),
        };
    }
}
