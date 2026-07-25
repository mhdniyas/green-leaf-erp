<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\UpdateDailySellingPricesRequest;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\ShopPriceGroup;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\VendorPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyPriceBoardController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly VendorPriceService $vendorPriceService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        $purchaseDate = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
        $targetBusinessDate = Carbon::parse($purchaseDate)->toDateString();
        $search = trim((string) $request->input('search', ''));

        $approvals = $this->priceBoardService
            ->ensurePendingApprovalsForPurchaseDate($purchaseDate)
            ->when($search !== '', function ($query): void {
                // handled after collection load
            })
            ->values();

        $matchingApprovals = $approvals
            ->filter(function (DailyPriceApproval $approval) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $product = $approval->product;
                if (! $product) {
                    return false;
                }

                $haystack = strtolower(implode(' ', array_filter([
                    $product->name,
                    $product->sku,
                    $product->unit,
                    $product->category?->name,
                ])));

                return str_contains($haystack, strtolower($search));
            })
            ->sortBy([
                ['status', 'asc'],
                ['product.name', 'asc'],
            ])
            ->values();

        $pendingApprovals = $matchingApprovals
            ->where('status', 'pending')
            ->values();

        $approvedApprovals = $matchingApprovals
            ->where('status', 'approved')
            ->values();

        return view('purchase-manager.prices.index', [
            'pendingApprovals' => $pendingApprovals,
            'approvedApprovals' => $approvedApprovals,
            'search' => $search,
            'purchaseDate' => $purchaseDate,
            'targetBusinessDate' => $targetBusinessDate,
        ]);
    }

    public function update(UpdateDailySellingPricesRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $isAdmin = (bool) $user?->hasRole('admin');

        DB::transaction(function () use ($validated, $isAdmin, $user): void {
            $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
            $groupB = ShopPriceGroup::query()->where('name', 'B')->first();
            $groupC = ShopPriceGroup::query()->where('name', 'C')->first();
            $userId = (int) $user->id;

            DailyPriceApproval::query()
                ->whereIn('id', array_map('intval', array_keys($validated['prices'])))
                ->with('product')
                ->get()
                ->each(function (DailyPriceApproval $approval) use ($validated, $isAdmin, $userId, $groupA, $groupB, $groupC): void {
                    $row = $validated['prices'][(string) $approval->id] ?? $validated['prices'][$approval->id] ?? null;

                    if (! is_array($row)) {
                        return;
                    }

                    $priceA = round((float) $row['price_a'], 2);
                    $priceB = round((float) $row['price_b'], 2);
                    $priceC = round((float) $row['price_c'], 2);

                    $approval->update([
                        'price_a' => $priceA,
                        'price_b' => $priceB,
                        'price_c' => $priceC,
                        'status' => $isAdmin ? 'approved' : 'pending',
                        'approved_by' => $isAdmin ? $userId : null,
                        'approved_at' => $isAdmin ? now() : null,
                    ]);

                    if (! $isAdmin) {
                        return;
                    }

                    $product = $approval->product;
                    if (! $product) {
                        return;
                    }

                    $this->updateActivePricesForGroup($product, $groupA, $priceA, $userId);
                    $this->updateActivePricesForGroup($product, $groupB, $priceB, $userId);
                    $this->updateActivePricesForGroup($product, $groupC, $priceC, $userId);
                    $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
                });
        });

        if ($isAdmin) {
            $targetBusinessDate = Carbon::parse($validated['date'])->toDateString();
            $this->shopInvoiceService->generateForBusinessDate($targetBusinessDate, (int) $user->id);
            $this->shopInvoiceService->repriceAllForBusinessDate(
                $targetBusinessDate,
                (int) $user->id,
                'Admin saved and published daily prices from price proposal board.',
            );
        }

        return redirect()
            ->route('purchasing.prices.index', [
                'search' => $request->validated('search'),
                'date' => $validated['date'],
            ])
            ->with('success', $isAdmin
                ? 'Daily prices published immediately.'
                : 'Price proposals updated and sent for admin approval.');
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }

    private function updateActivePricesForGroup(Product $product, ?ShopPriceGroup $group, float $priceGradeA, int $userId): void
    {
        if (! $group) {
            return;
        }

        $grades = [
            ProductGrade::GradeA->value => 1.00,
            ProductGrade::GradeB->value => 0.90,
            ProductGrade::GradeC->value => 0.80,
        ];

        foreach ($grades as $gradeVal => $multiplier) {
            $calculatedPrice = round($priceGradeA * $multiplier, 2);

            $activePrice = DailyProductPrice::firstOrNew([
                'product_id' => $product->id,
                'shop_price_group_id' => $group->id,
                'grade' => $gradeVal,
            ]);

            $oldPrice = $activePrice->exists ? (float) $activePrice->selling_price : null;

            $activePrice->fill([
                'selling_price' => $calculatedPrice,
                'price_source' => 'manual',
                'margin_percent' => null,
                'manual_override' => true,
                'override_reason' => 'Admin approved daily price approval',
                'changed_by' => $userId,
            ]);
            $activePrice->save();

            if ($oldPrice === null || abs($oldPrice - $calculatedPrice) > 0.0001) {
                DailyProductPriceRevision::create([
                    'daily_product_price_id' => $activePrice->id,
                    'product_id' => $product->id,
                    'shop_price_group_id' => $group->id,
                    'grade' => $gradeVal,
                    'old_price' => $oldPrice,
                    'new_price' => $calculatedPrice,
                    'old_margin_percent' => null,
                    'new_margin_percent' => null,
                    'change_type' => 'manual',
                    'reason' => 'Admin approved proposed daily price',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);
            }
        }
    }
}
