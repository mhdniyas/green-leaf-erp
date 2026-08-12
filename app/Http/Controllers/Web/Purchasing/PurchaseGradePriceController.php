<?php

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\UpdatePurchaseGradePricesRequest;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\PurchaseGradePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseGradePriceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['purchaser', 'purchase', 'admin']), 403);

        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $searchQuery = trim($request->string('search')->toString());
        $selectedCategory = $request->string('category_id')->toString();

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $products = Product::query()
            ->active()
            ->ordered()
            ->when($searchQuery !== '', fn ($query) => $query->where(function ($nested) use ($searchQuery): void {
                $nested->where('name', 'like', "%{$searchQuery}%")
                    ->orWhere('sku', 'like', "%{$searchQuery}%");
            }))
            ->when($selectedCategory !== '' && $selectedCategory !== 'all', fn ($query) => $query->where('category_id', $selectedCategory))
            ->get(['id', 'name', 'sku', 'unit', 'category_id']);

        $priceHistory = PurchaseGradePrice::query()
            ->with('approvedBy:id,name')
            ->whereIn('product_id', $products->pluck('id'))
            ->where('grade', 'B')
            ->where('status', 'approved')
            ->whereDate('business_date', '<=', $date)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        $products = $products->map(function (Product $product) use ($priceHistory, $date): array {
            $history = $priceHistory->get($product->id, collect());
            $today = $history->first(fn (PurchaseGradePrice $price): bool => $price->business_date->toDateString() === $date);
            $previous = $history->first(fn (PurchaseGradePrice $price): bool => $price->business_date->toDateString() < $date);
            $todayPrice = $today ? (float) $today->purchase_price : null;
            $previousPrice = $previous ? (float) $previous->purchase_price : null;
            $difference = $todayPrice !== null && $previousPrice !== null ? round($todayPrice - $previousPrice, 2) : null;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => strtoupper((string) ($product->unit ?: 'KG')),
                'today_price' => $todayPrice,
                'previous_price' => $previousPrice,
                'difference' => $difference,
                'difference_percentage' => $difference !== null && $previousPrice > 0 ? round(($difference / $previousPrice) * 100, 1) : null,
                'updated_by' => $today?->approvedBy?->name,
                'updated_time' => $today?->updated_at?->format('g:i A'),
            ];
        });

        return view('purchasing.purchaser.purchase-grade-prices', compact('date', 'products', 'categories', 'selectedCategory', 'searchQuery'));
    }

    public function update(UpdatePurchaseGradePricesRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $request): void {
            foreach ($validated['prices'] as $productId => $grades) {
                foreach (['A', 'B'] as $grade) {
                    $value = $grades[$grade] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    PurchaseGradePrice::query()->updateOrCreate(
                        ['product_id' => (int) $productId, 'business_date' => $validated['business_date'], 'grade' => $grade],
                        ['purchase_price' => (float) $value, 'status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()],
                    );
                }
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Grade B buying price saved.']);
        }

        return back()->with('success', 'Purchase grade prices updated.');
    }

    public function copyGradeAToB(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['purchaser', 'purchase', 'admin']), 403);
        $validated = $request->validate(['business_date' => ['required', 'date']]);

        $copied = DB::transaction(function () use ($validated, $request): int {
            $gradeAPrices = DailyPriceApproval::query()
                ->whereDate('business_date', $validated['business_date'])
                ->where('status', 'approved')
                ->whereNotNull('approved_at')
                ->where('price_a', '>', 0)
                ->get();

            foreach ($gradeAPrices as $gradeAPrice) {
                PurchaseGradePrice::query()->updateOrCreate(
                    [
                        'product_id' => $gradeAPrice->product_id,
                        'business_date' => $validated['business_date'],
                        'grade' => 'B',
                    ],
                    [
                        'purchase_price' => $gradeAPrice->price_a,
                        'price_unit' => $gradeAPrice->price_unit,
                        'status' => 'approved',
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ],
                );
            }

            return $gradeAPrices->count();
        });

        return back()->with('success', "Fetched {$copied} today's Grade A prices into Grade B.");
    }
}
