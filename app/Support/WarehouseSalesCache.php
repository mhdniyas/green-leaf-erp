<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WarehouseSalesCache
{
    public const KEY_PRODUCTS = 'warehouse_sales:products_catalog_v2';

    public const KEY_WAREHOUSES = 'warehouse_sales:active_warehouses_v2';

    public const KEY_SHOPS = 'warehouse_sales:active_shops_v2';

    public const KEY_STAFF = 'warehouse_sales:staff_users_v2';

    public const KEY_SETTING_PREFIX = 'warehouse_sales:setting:';

    public static function setting(string $key): mixed
    {
        return Cache::remember(
            self::KEY_SETTING_PREFIX.$key,
            600,
            fn () => BusinessSetting::query()->where('key', $key)->value('value')
        );
    }

    /**
     * @return Collection<int, object>
     */
    public static function productsCatalog(): Collection
    {
        $rows = Cache::remember(self::KEY_PRODUCTS, 300, function (): array {
            return Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->toBase()
                ->get(['id', 'name', 'sku', 'unit', 'base_price'])
                ->map(fn (object $p): array => [
                    'id' => (int) $p->id,
                    'name' => (string) $p->name,
                    'sku' => $p->sku !== null ? (string) $p->sku : null,
                    'unit' => $p->unit !== null ? (string) $p->unit : 'kg',
                    'base_price' => (float) ($p->base_price ?? 0),
                ])
                ->all();
        });

        return collect($rows)->map(fn (array $r): object => (object) $r);
    }

    /**
     * @return Collection<int, object>
     */
    public static function activeWarehouses(): Collection
    {
        $rows = Cache::remember(self::KEY_WAREHOUSES, 600, function (): array {
            return Warehouse::query()
                ->active()
                ->orderBy('id')
                ->toBase()
                ->get(['id', 'name', 'code'])
                ->map(fn (object $w): array => [
                    'id' => (int) $w->id,
                    'name' => (string) $w->name,
                    'code' => (string) ($w->code ?? ''),
                ])
                ->all();
        });

        return collect($rows)->map(fn (array $r): object => (object) $r);
    }

    /**
     * @return Collection<int, object>
     */
    public static function activeShops(): Collection
    {
        $rows = Cache::remember(self::KEY_SHOPS, 600, function (): array {
            return Shop::query()
                ->orderBy('name')
                ->toBase()
                ->get(['id', 'name', 'code', 'contact_phone'])
                ->map(fn (object $s): array => [
                    'id' => (int) $s->id,
                    'shop_id' => (int) $s->id,
                    'name' => (string) $s->name,
                    'code' => $s->code !== null ? (string) $s->code : null,
                    'slug' => null,
                    'contact_phone' => $s->contact_phone !== null ? (string) $s->contact_phone : null,
                ])
                ->all();
        });

        return collect($rows)->map(fn (array $r): object => (object) $r);
    }

    /**
     * @return Collection<int, array{id: int, name: string, is_purchaser: bool, role_label: string}>
     */
    public static function staffUsers(): Collection
    {
        $rows = Cache::remember(self::KEY_STAFF, 300, function (): array {
            $allStaff = User::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get(['id', 'name']);

            $purchaserStaff = $allStaff->filter(fn (User $u) => $u->hasRole('purchaser'));
            $otherStaff = $allStaff->reject(fn (User $u) => $u->hasRole('purchaser'));

            return $purchaserStaff->concat($otherStaff)->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'is_purchaser' => $u->hasRole('purchaser'),
                'role_label' => $u->hasRole('purchaser') ? 'Purchaser' : ($u->roles->pluck('name')->first() ?: 'Staff'),
            ])->values()->all();
        });

        return collect($rows);
    }

    public static function forgetProducts(): void
    {
        Cache::forget(self::KEY_PRODUCTS);
    }

    public static function forgetWarehouses(): void
    {
        Cache::forget(self::KEY_WAREHOUSES);
    }

    public static function forgetShops(): void
    {
        Cache::forget(self::KEY_SHOPS);
    }

    public static function forgetStaff(): void
    {
        Cache::forget(self::KEY_STAFF);
    }

    public static function forgetSettings(): void
    {
        Cache::forget(self::KEY_SETTING_PREFIX.'warehouse_sales_enabled');
        Cache::forget(self::KEY_SETTING_PREFIX.'warehouse_sales_allowed_user_ids');
        Cache::forget(self::KEY_SETTING_PREFIX.'warehouse_sales_user_warehouses');
    }
}
