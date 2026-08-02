<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DailyPriceApproval;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class DefaultProductPriceSeeder extends Seeder
{
    private const BUSINESS_DATES = ['2026-08-01', '2026-08-02'];

    /**
     * @var array<string, float>
     */
    private const PRICES = [
        '1' => 22.00,
        '2' => 20.00,
        '3' => 35.00,
        '4' => 35.00,
        '5' => 25.00,
        '6' => 30.00,
        '7' => 35.00,
        '8' => 80.00,
        '9' => 90.00,
        '10' => 220.00,
        '11' => 290.00,
        '12' => 105.00,
        '13' => 95.00,
        '14' => 80.00,
        '15' => 65.00,
        '16' => 110.00,
        '17' => 70.00,
        '18' => 70.00,
        '19' => 80.00,
        '20' => 80.00,
        '21' => 150.00,
        '22' => 70.00,
        '23' => 80.00,
        '24' => 85.00,
        '25' => 80.00,
        '26' => 129.00,
        '27' => 80.00,
        '28' => 85.00,
        '29' => 60.00,
        '30' => 90.00,
        '31' => 60.00,
        '32' => 90.00,
        '33' => 60.00,
        '34' => 100.00,
        '35' => 60.00,
        '36' => 40.00,
        '37' => 45.00,
        '38' => 130.00,
        '39' => 60.00,
        '40' => 70.00,
        '41' => 85.00,
        '42' => 60.00,
        '43' => 60.00,
        '44' => 60.00,
        '45' => 40.00,
        '46' => 80.00,
        '47' => 70.00,
        '48' => 25.00,
        '49' => 10.00,
        '50' => 10.00,
        '51' => 6.00,
        '52' => 25.00,
        '53' => 43.00,
        '54' => 55.00,
        '55' => 40.00,
        '56' => 90.00,
        '57' => 70.00,
        '58' => 60.00,
        '59' => 100.00,
        '60' => 43.00,
        '61' => 30.00,
        '62' => 50.00,
        '63' => 110.00,
        '64' => 120.00,
        '65' => 80.00,
        '66' => 0.00,
        '67' => 200.00,
        '68' => 80.00,
        '69' => 130.00,
        '70' => 0.00,
        '71' => 46.00,
        '72' => 95.00,
        '73' => 65.00,
        '74' => 40.00,
        '75' => 200.00,
        '76' => 100.00,
        '77' => 85.00,
        '78' => 70.00,
        '79' => 40.00,
        '80' => 175.00,
        '81' => 90.00,
        '82' => 45.00,
        '83' => 26.00,
        '84' => 26.00,
        '85' => 29.00,
        '86' => 190.00,
        '87' => 100.00,
        '88' => 170.00,
        '89' => 130.00,
        '90' => 320.00,
        '91' => 165.00,
        '92' => 20.00,
        '93' => 200.00,
        '94' => 0.00,
        '95' => 0.00,
        '96' => 110.00,
        '97' => 130.00,
        '101' => 15.00,
        '102' => 30.00,
        '103' => 50.00,
        '104' => 12.00,
        '105' => 12.00,
        '106' => 12.00,
        '107' => 18.00,
        '108' => 14.00,
        '109' => 17.00,
        '110' => 18.00,
        '111' => 18.00,
        '112' => 12.00,
        '113' => 17.00,
        '114' => 18.00,
        '115' => 32.00,
        '116' => 12.00,
        '117' => 12.00,
        '118' => 0.00,
        '120' => 22.00,
        '121' => 23.00,
        '122' => 28.00,
        '123' => 53.00,
        '124' => 15.00,
        '125' => 65.00,
        '126' => 35.00,
        '127' => 190.00,
        '128' => 47.00,
        '129' => 60.00,
        '130' => 130.00,
        '131' => 120.00,
        '132' => 140.00,
        '133' => 120.00,
        '134' => 120.00,
        '135' => 180.00,
        '136' => 180.00,
        '137' => 120.00,
        '138' => 120.00,
        '139' => 150.00,
        '140' => 150.00,
        '141' => 160.00,
        '142' => 140.00,
        '143' => 160.00,
        '144' => 140.00,
        '145' => 160.00,
        '146' => 25.00,
        '147' => 25.00,
        '148' => 25.00,
        '149' => 25.00,
        '150' => 25.00,
        '151' => 88.00,
        '152' => 88.00,
        '153' => 140.00,
        '154' => 90.00,
        '155' => 120.00,
        '156' => 110.00,
        '161' => 0.00,
        '162' => 92.00,
        '163' => 92.00,
        '164' => 74.00,
        '165' => 74.00,
        '166' => 37.00,
        '167' => 77.00,
        '168' => 0.00,
        '169' => 0.00,
        '170' => 38.00,
        '181' => 0.00,
    ];

    public function run(): void
    {
        $defaultPricesUpdated = 0;
        $dailyPricesUpdated = 0;
        $missingSkus = [];
        $hasDailyPriceUnit = Schema::hasColumn('daily_price_approvals', 'price_unit');

        DB::transaction(function () use ($hasDailyPriceUnit, &$defaultPricesUpdated, &$dailyPricesUpdated, &$missingSkus): void {
            foreach (self::PRICES as $sku => $price) {
                $product = Product::query()
                    ->where('sku', $sku)
                    ->first(['id', 'sku', 'unit']);

                if (! $product) {
                    $missingSkus[] = $sku;

                    continue;
                }

                $product->update(['base_price' => $price]);
                $defaultPricesUpdated++;

                foreach (self::BUSINESS_DATES as $bDate) {
                    $businessDate = Carbon::parse($bDate)->toDateString();

                    $approval = DailyPriceApproval::query()->firstOrNew([
                        'product_id' => $product->id,
                        'business_date' => $businessDate,
                    ]);

                    $payload = [
                        'purchase_price' => $price,
                        'price_a' => $price,
                        'price_b' => $price,
                        'price_c' => $price,
                        'status' => 'approved',
                        'approved_at' => $approval->approved_at ?? now(),
                    ];

                    if ($hasDailyPriceUnit) {
                        $payload['price_unit'] = $product->unit ?: 'kg';
                    }

                    $approval->fill($payload);
                    $approval->save();
                    $dailyPricesUpdated++;
                }
            }
        });

        $this->command?->info("Updated {$defaultPricesUpdated} product default prices.");
        $this->command?->info("Updated {$dailyPricesUpdated} approved daily prices for business dates.");

        if ($missingSkus !== []) {
            $this->command?->warn('Missing product SKUs: '.implode(', ', $missingSkus));
        }
    }
}
