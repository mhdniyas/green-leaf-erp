<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\UpdateDailyPriceBoardRequest;
use App\Models\DailyProductPrice;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyPriceBoardController extends Controller
{
    public function index(): View
    {
        $this->authorizeBoardAccess();

        $date = Carbon::parse(request('date', Carbon::tomorrow()->toDateString()));
        $products = Product::query()
            ->with('category')
            ->active()
            ->orderBy('name')
            ->get();

        $dailyPrices = DailyProductPrice::query()
            ->whereDate('price_date', $date)
            ->pluck('price', 'product_id');

        return view('purchase-manager.prices.index', [
            'priceDate' => $date,
            'products' => $products,
            'dailyPrices' => $dailyPrices,
        ]);
    }

    public function update(UpdateDailyPriceBoardRequest $request): RedirectResponse
    {
        $this->authorizeBoardAccess();

        $priceDate = Carbon::parse($request->validated('date'))->toDateString();
        $basePrices = $request->validated('base_prices', []);
        $dailyPrices = $request->validated('daily_prices', []);
        $products = Product::query()->active()->pluck('base_price', 'id');

        DB::transaction(function () use ($products, $basePrices, $dailyPrices, $priceDate): void {
            foreach ($products as $productId => $existingBasePrice) {
                if (array_key_exists((string) $productId, $basePrices) || array_key_exists($productId, $basePrices)) {
                    Product::query()
                        ->whereKey($productId)
                        ->update([
                            'base_price' => (float) ($basePrices[$productId] ?? $basePrices[(string) $productId] ?? $existingBasePrice),
                        ]);
                }

                $dailyPrice = $dailyPrices[$productId] ?? $dailyPrices[(string) $productId] ?? null;
                $normalizedDailyPrice = $dailyPrice === null || $dailyPrice === '' ? null : (float) $dailyPrice;

                if ($normalizedDailyPrice === null) {
                    DailyProductPrice::query()
                        ->where('product_id', $productId)
                        ->whereDate('price_date', $priceDate)
                        ->delete();

                    continue;
                }

                DailyProductPrice::query()->updateOrCreate(
                    [
                        'product_id' => $productId,
                        'price_date' => $priceDate,
                    ],
                    [
                        'price' => $normalizedDailyPrice,
                    ]
                );
            }
        });

        return redirect()
            ->route('purchasing.prices.index', ['date' => $priceDate])
            ->with('success', 'Daily price board updated successfully.');
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }
}
