<?php

declare(strict_types=1);

namespace App\Support\ShopOwner;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActiveShopResolver
{
    private const SESSION_KEY = 'shop_owner_active_shop_code';

    /**
     * @return Collection<int, Shop>
     */
    public function authorizedShops(User $user): Collection
    {
        $shops = Shop::query()
            ->whereIn('id', $user->ownedShopAssignments()->pluck('shop_id'))
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        if ($user->shop_id !== null) {
            $primaryShop = Shop::query()->find($user->shop_id);

            if ($primaryShop instanceof Shop) {
                $shops->put($primaryShop->id, $primaryShop);
            }
        }

        return $shops->sortBy('name')->values();
    }

    public function resolve(Request $request): Shop
    {
        /** @var User $user */
        $user = $request->user();
        $authorizedShops = $this->authorizedShops($user);

        abort_unless($user->hasRole('shop') && $authorizedShops->isNotEmpty(), 403);

        $requestedShopCode = trim($request->string('shop')->toString());

        if ($requestedShopCode !== '') {
            $requestedShop = $authorizedShops->firstWhere('code', $requestedShopCode);

            abort_unless($requestedShop instanceof Shop, 403, 'This shop is outside your scope.');

            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $requestedShop->code);
            }

            return $requestedShop;
        }

        $sessionShopCode = $request->hasSession()
            ? (string) $request->session()->get(self::SESSION_KEY, '')
            : '';

        if ($sessionShopCode !== '') {
            $sessionShop = $authorizedShops->firstWhere('code', $sessionShopCode);

            if ($sessionShop instanceof Shop) {
                return $sessionShop;
            }
        }

        $defaultShop = $user->shop_id !== null
            ? $authorizedShops->firstWhere('id', $user->shop_id)
            : null;

        $activeShop = $defaultShop instanceof Shop ? $defaultShop : $authorizedShops->first();

        abort_unless($activeShop instanceof Shop, 403);

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $activeShop->code);
        }

        return $activeShop;
    }
}
