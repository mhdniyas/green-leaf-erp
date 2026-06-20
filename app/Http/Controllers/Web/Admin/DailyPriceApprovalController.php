<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\ShopPriceGroup;
use App\Services\Purchasing\VendorPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyPriceApprovalController extends Controller
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403, 'Unauthorized access.');

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        // Load pending price approvals for the date
        $pendingApprovals = DailyPriceApproval::where('status', 'pending')
            ->whereDate('business_date', $date)
            ->with(['product', 'approvedBy'])
            ->get();

        $approvedApprovals = DailyPriceApproval::where('status', 'approved')
            ->whereDate('business_date', $date)
            ->with(['product', 'approvedBy'])
            ->orderByDesc('approved_at')
            ->get();

        $priceGroups = ShopPriceGroup::whereIn('name', ['A', 'B', 'C'])->get()->keyBy('name');

        // Add history and calculate variance for sorting
        $items = $pendingApprovals->map(function (DailyPriceApproval $approval) use ($date) {
            $product = $approval->product;

            // 1. Get previous 2 days' approved purchase price history
            $history = DailyPriceApproval::where('product_id', $approval->product_id)
                ->where('business_date', '<', $date)
                ->where('status', 'approved')
                ->orderByDesc('business_date')
                ->limit(2)
                ->get()
                ->map(fn ($h) => [
                    'date' => $h->business_date->format('Y-m-d'),
                    'purchase_price' => (float) $h->purchase_price,
                ])
                ->all();

            // 2. Calculate variance compared to the most recent approved purchase price (or fallback to base_price)
            $lastApprovedPurchasePrice = count($history) > 0
                ? $history[0]['purchase_price']
                : (float) $product->base_price;

            $variance = abs((float) $approval->purchase_price - $lastApprovedPurchasePrice);

            return [
                'approval' => $approval,
                'product' => $product,
                'history' => $history,
                'variance' => $variance,
                'last_purchase_price' => $lastApprovedPurchasePrice,
            ];
        });

        // 3. Sort by variance descending
        $sortedItems = $items->sortByDesc('variance')->values();

        $revisionHistory = DailyProductPriceRevision::query()
            ->with(['product', 'shopPriceGroup', 'changedBy'])
            ->where('reason', 'Admin approved proposed daily price')
            ->where('grade', ProductGrade::GradeA->value)
            ->whereIn('product_id', $pendingApprovals->pluck('product_id')->merge($approvedApprovals->pluck('product_id'))->unique()->all())
            ->orderByDesc('changed_at')
            ->limit(60)
            ->get();

        return view('admin.price-approvals.index', [
            'date' => $date,
            'items' => $sortedItems,
            'priceGroups' => $priceGroups,
            'approvedApprovals' => $approvedApprovals,
            'revisionHistory' => $revisionHistory,
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403, 'Unauthorized access.');

        $action = $request->input('action');
        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        if ($action === 'approve_all') {
            $ids = DailyPriceApproval::where('status', 'pending')
                ->whereDate('business_date', $date)
                ->pluck('id')
                ->all();
        } else {
            $ids = $request->input('approvals', []);
        }

        if (empty($ids)) {
            return redirect()->back()->withErrors(['No price approvals selected.']);
        }

        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($ids, $request, $userId): void {
                $groupA = ShopPriceGroup::where('name', 'A')->first();
                $groupB = ShopPriceGroup::where('name', 'B')->first();
                $groupC = ShopPriceGroup::where('name', 'C')->first();

                foreach ($ids as $id) {
                    $approval = DailyPriceApproval::with('product')->findOrFail($id);

                    if ($approval->status !== 'pending') {
                        continue;
                    }

                    // Get manually edited prices, falling back to proposed values
                    $priceA = (float) $request->input("price_a.{$id}", $approval->price_a);
                    $priceB = (float) $request->input("price_b.{$id}", $approval->price_b);
                    $priceC = (float) $request->input("price_c.{$id}", $approval->price_c);

                    // Update approval record
                    $approval->update([
                        'price_a' => $priceA,
                        'price_b' => $priceB,
                        'price_c' => $priceC,
                        'status' => 'approved',
                        'approved_by' => $userId,
                        'approved_at' => now(),
                    ]);

                    // Write approved prices to daily_product_prices
                    $product = $approval->product;

                    $this->updateActivePricesForGroup($product, $groupA, $priceA, $userId);
                    $this->updateActivePricesForGroup($product, $groupB, $priceB, $userId);
                    $this->updateActivePricesForGroup($product, $groupC, $priceC, $userId);
                    $this->vendorPriceService->syncPrice($product->id, (float) $approval->purchase_price);
                }
            });

            $this->shopInvoiceService->generateForBusinessDate($date, $userId);
            $this->shopInvoiceService->repriceAllForBusinessDate(
                $date,
                $userId,
                'Admin approved updated daily prices.',
            );

            return redirect()->route('admin.price-approvals.index', ['date' => $date])
                ->with('success', 'Selected daily product prices approved and published.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
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
