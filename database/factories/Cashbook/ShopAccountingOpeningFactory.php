<?php

declare(strict_types=1);

namespace Database\Factories\Cashbook;

use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopAccountingOpening>
 */
class ShopAccountingOpeningFactory extends Factory
{
    protected $model = ShopAccountingOpening::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'accounting_start_date' => '2026-09-01',
            'opening_shop_company_balance' => 0.00,
            'opening_balance_direction' => 'settled',
            'opening_allocation_pending' => 0.00,
            'opening_petty_balance' => 0.00,
            'notes' => 'Accounting start opening',
        ];
    }
}
