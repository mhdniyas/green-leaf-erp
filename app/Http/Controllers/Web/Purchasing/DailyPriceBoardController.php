<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\UpdateDailySellingPricesRequest;
use App\Models\DailyPriceApproval;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DailyPriceBoardController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoardAccess();

        $purchaseDate = $request->input('date', Carbon::today()->toDateString());
        $targetBusinessDate = Carbon::parse($purchaseDate)->addDay()->toDateString();
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

        DailyPriceApproval::query()
            ->whereIn('id', array_map('intval', array_keys($validated['prices'])))
            ->get()
            ->each(function (DailyPriceApproval $approval) use ($validated): void {
                $row = $validated['prices'][(string) $approval->id] ?? $validated['prices'][$approval->id] ?? null;

                if (! is_array($row)) {
                    return;
                }

                $approval->update([
                    'price_a' => round((float) $row['price_a'], 2),
                    'price_b' => round((float) $row['price_b'], 2),
                    'price_c' => round((float) $row['price_c'], 2),
                    'status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
            });

        return redirect()
            ->route('purchasing.prices.index', [
                'search' => $request->validated('search'),
                'date' => $validated['date'],
            ])
            ->with('success', 'Price proposals updated and sent for admin approval.');
    }

    private function authorizeBoardAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }
}
