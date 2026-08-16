<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DailyPriceBoardController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        $purchaseDate = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
        $targetBusinessDate = Carbon::parse($purchaseDate)->toDateString();
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $perPage = max(10, min(50, (int) $request->input('per_page', 20)));

        $productQuery = Product::query()
            ->active()
            ->with('category')
            ->ordered();

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $productQuery->where(function ($query) use ($search): void {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $productQuery->paginate($perPage)->withQueryString();
        $pageProductIds = $products->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $currentApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows): DailyPriceApproval => $rows->first());

        $previousApprovals = DailyPriceApproval::query()
            ->with('product.category')
            ->whereDate('business_date', '<', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows): DailyPriceApproval => $rows->first());

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($currentApprovals, $previousApprovals): array {
                $currentApproval = $currentApprovals->get($product->id);
                $previousApproval = $previousApprovals->get($product->id);

                $purchasePrice = (float) ($currentApproval?->purchase_price
                    ?? $previousApproval?->purchase_price
                    ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price));

                $previousPurchasePrice = $previousApproval?->purchase_price !== null
                    ? (float) $previousApproval->purchase_price
                    : null;

                $sellingPriceA = (float) ($currentApproval?->price_a ?? $previousApproval?->price_a ?? 0);
                $sellingPriceB = (float) ($currentApproval?->price_b ?? $previousApproval?->price_b ?? 0);
                $sellingPriceC = (float) ($currentApproval?->price_c ?? $previousApproval?->price_c ?? 0);

                $diff = $previousPurchasePrice !== null ? round($purchasePrice - $previousPurchasePrice, 4) : null;

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?: 'kg',
                    'category_name' => $product->category?->name ?? 'Uncategorized',
                    'purchase_price' => $purchasePrice,
                    'previous_purchase_price' => $previousPurchasePrice,
                    'purchase_diff' => $diff,
                    'selling_price_a' => $sellingPriceA,
                    'selling_price_b' => $sellingPriceB,
                    'selling_price_c' => $sellingPriceC,
                    'approval_status' => $currentApproval?->status ?? $previousApproval?->status ?? 'missing',
                    'approved_at' => $currentApproval?->approved_at ?? $previousApproval?->approved_at,
                    'price_unit' => $currentApproval?->price_unit ?? $previousApproval?->price_unit ?? $product->unit,
                    'is_updated_today' => $currentApproval !== null,
                    'source_label' => $currentApproval ? 'Today' : ($previousApproval ? 'Carried forward' : 'Vendor fallback'),
                ];
            })->values()
        );

        $isPublished = DailyPricePublication::isPublishedForDate($targetBusinessDate);

        return view('purchase-manager.prices.index', [
            'products' => $products,
            'search' => $search,
            'categoryId' => $categoryId,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'purchaseDate' => $purchaseDate,
            'targetBusinessDate' => $targetBusinessDate,
            'previousDate' => Carbon::parse($purchaseDate)->subDay()->toDateString(),
            'perPage' => $perPage,
            'isPublished' => $isPublished,
        ]);
    }

    public function togglePublish(Request $request)
    {
        $this->authorizeBoardAccess();

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'is_published' => ['required', 'boolean'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $isPublished = (bool) $validated['is_published'];

        $publication = \App\Models\DailyPricePublication::setPublishStatus($date, $isPublished, $request->user());

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_published' => $publication->is_published,
                'message' => $isPublished
                    ? "Daily prices for {$date} published successfully."
                    : "Daily prices for {$date} unpublished (set to draft).",
            ]);
        }

        $formattedDate = Carbon::parse($date)->format('d M Y');
        $message = $isPublished
            ? "Daily prices for {$formattedDate} are now published and visible to shop owners."
            : "Daily prices for {$formattedDate} are set to draft (hidden from shop owners).";

        return redirect()->back()->with('success', $message);
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }
}
