<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\UpdateShopPriceGroupRequest;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopPriceGroupController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    public function index(): View
    {
        $this->authorizeAccess();

        $groups = $this->priceBoardService
            ->ensureDefaultPriceGroups()
            ->load('shops');

        return view('purchase-manager.price-groups.index', [
            'groups' => $groups,
            'shops' => Shop::query()
                ->with('priceGroup')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(UpdateShopPriceGroupRequest $request): RedirectResponse
    {
        $group = ShopPriceGroup::query()->create([
            'name' => strtoupper((string) $request->validated('name')),
            'default_margin_percent' => $request->validated('default_margin_percent'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->priceBoardService->ensureSellingPricesForGroup($group, $request->user()?->id);

        return redirect()
            ->route('purchasing.price-groups.index')
            ->with('success', 'Shop price group created.');
    }

    public function update(UpdateShopPriceGroupRequest $request, ShopPriceGroup $priceGroup): RedirectResponse
    {
        $priceGroup->update([
            'name' => strtoupper((string) $request->validated('name')),
            'default_margin_percent' => $request->validated('default_margin_percent'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->priceBoardService->ensureSellingPricesForGroup($priceGroup, $request->user()?->id);

        return redirect()
            ->route('purchasing.price-groups.index')
            ->with('success', 'Shop price group updated.');
    }

    public function destroy(ShopPriceGroup $priceGroup): RedirectResponse
    {
        $this->authorizeAccess();

        if ($priceGroup->shops()->exists()) {
            return redirect()
                ->route('purchasing.price-groups.index')
                ->with('error', 'Move shops out of this price group before deleting it.');
        }

        $priceGroup->delete();

        return redirect()
            ->route('purchasing.price-groups.index')
            ->with('success', 'Shop price group deleted.');
    }

    public function assignShops(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'shops' => ['array'],
            'shops.*' => ['nullable', 'integer', 'exists:shop_price_groups,id'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach (($validated['shops'] ?? []) as $shopId => $groupId) {
                Shop::query()
                    ->whereKey((int) $shopId)
                    ->update(['shop_price_group_id' => $groupId ? (int) $groupId : null]);
            }
        });

        return redirect()
            ->route('purchasing.price-groups.index')
            ->with('success', 'Shop price group assignments updated.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin'), 403);
    }
}
