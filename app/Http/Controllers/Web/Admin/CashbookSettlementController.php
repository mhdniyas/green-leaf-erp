<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\SaveCashbookSettlementRequest;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashbookSettlementController extends Controller
{
    public function __construct(private readonly CashbookShopSyncService $shopSync, private readonly ShopSettlementService $settlements) {}

    public function index(Request $request, string $shop): View
    {
        return $this->page($request, $shop);
    }

    public function create(Request $request, string $shop): View
    {
        return $this->page($request, $shop, true);
    }

    public function edit(Request $request, string $shop, string $settlement): View
    {
        return $this->page($request, $shop, true, $settlement);
    }

    private function page(Request $request, string $shop, bool $editing = false, ?string $settlement = null): View
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);
        $this->settlements->ensureDefaults($currentShop);
        $relations = $this->settlements->settlements((int) $currentShop->shop_id);
        $relation = $settlement === null ? null : $relations->firstWhere('public_uuid', $settlement);
        abort_if($settlement !== null && $relation === null, 404);
        $settings = $currentShop->entrySettings()->with(['entryType', 'headerGroup'])->orderBy('display_order')->get();
        $company = config('greenleaf');

        $allRelations = ShopCashbookRelation::with(['shop', 'items.setting.entryType'])
            ->where('enabled', true)
            ->get();

        $importableSettlements = $allRelations->map(function (ShopCashbookRelation $r) use ($settings, $currentShop): array {
            $isSameShop = (int) $r->shop_id === (int) $currentShop->shop_id;
            $items = $r->items->map(function ($item) use ($settings, $isSameShop): ?array {
                $settingId = $isSameShop
                    ? (string) $item->shop_ledger_entry_setting_id
                    : (string) ($settings->firstWhere('entry_type_id', $item->setting?->entry_type_id)?->id ?? '');

                if ($settingId === '') {
                    return null;
                }

                return [
                    'setting_id' => $settingId,
                    'role' => $item->role ?? 'add',
                ];
            })->filter()->values()->all();

            return [
                'id' => $r->id,
                'name' => $r->name,
                'shop_name' => $r->shop?->name ?? 'Default',
                'is_same_shop' => $isSameShop,
                'items' => $items,
            ];
        })->filter(fn (array $s): bool => ! empty($s['items']))->values()->all();

        return view('admin.cashbook.settings.settlements.'.($editing ? 'form' : 'index'), compact('shops', 'currentShop', 'relations', 'relation', 'settings', 'company', 'importableSettlements'));
    }

    public function store(SaveCashbookSettlementRequest $request, string $shop): RedirectResponse
    {
        $this->settlements->save($request->profile(), $request->validated());

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)->with('success', 'Settlement created. Its result is now available in the summary.');
    }

    public function update(SaveCashbookSettlementRequest $request, string $shop, string $settlement): RedirectResponse
    {
        $profile = $request->profile();
        $relation = ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('public_uuid', $settlement)->firstOrFail();
        $this->settlements->save($profile, $request->validated(), $relation);

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)->with('success', 'Settlement updated.');
    }

    public function copy(Request $request, string $shop, string $settlement): RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isMainAdmin() || $request->user()->hasRole('admin')), 403);
        $shops = $this->shopSync->syncAndGetProfiles();
        $currentShop = $shops->first(fn (ShopLedgerProfile $profile): bool => in_array($shop, [(string) $profile->shop_id, $profile->slug, $profile->uuid, $profile->code], true));
        abort_unless($currentShop, 404);

        $relation = ShopCashbookRelation::where('shop_id', $currentShop->shop_id)->where('public_uuid', $settlement)->firstOrFail();

        $validated = $request->validate([
            'target_shop_ids' => ['required', 'array', 'min:1'],
            'target_shop_ids.*' => ['required', 'integer', 'exists:shops,id'],
        ]);

        $copiedCount = 0;
        foreach ($validated['target_shop_ids'] as $targetShopId) {
            if ((int) $targetShopId === (int) $currentShop->shop_id) {
                continue;
            }

            $targetProfile = $shops->firstWhere('shop_id', (int) $targetShopId);
            if ($targetProfile) {
                $this->settlements->copyToShop($relation, $targetProfile);
                $copiedCount++;
            }
        }

        return redirect()->route('admin.cashbook.settings.shop.settlements.index', $shop)
            ->with('success', "Settlement '{$relation->name}' successfully copied to {$copiedCount} shop(s).");
    }
}
