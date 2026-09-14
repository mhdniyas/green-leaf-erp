<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class WarehouseSalesAccessService
{
    public const SETTING_ENABLED = 'warehouse_sales_enabled';

    public const SETTING_ALLOWED_USER_IDS = 'warehouse_sales_allowed_user_ids';

    public const SETTING_USER_WAREHOUSES = 'warehouse_sales_user_warehouses';

    /**
     * Check whether Warehouse Sales feature is globally enabled.
     */
    public function isFeatureEnabled(): bool
    {
        $val = BusinessSetting::query()
            ->where('key', self::SETTING_ENABLED)
            ->value('value');

        return filter_var($val ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get array of user IDs permitted to make warehouse sales.
     *
     * @return array<int, int>
     */
    public function allowedUserIds(): array
    {
        $raw = BusinessSetting::query()
            ->where('key', self::SETTING_ALLOWED_USER_IDS)
            ->value('value');

        if (empty($raw)) {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded)));
    }

    /**
     * Get user warehouse map: [user_id => [warehouse_id_1, warehouse_id_2]].
     *
     * @return array<int, array<int, int>>
     */
    public function userWarehousesMap(): array
    {
        $raw = BusinessSetting::query()
            ->where('key', self::SETTING_USER_WAREHOUSES)
            ->value('value');

        if (empty($raw)) {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $userId => $warehouseIds) {
            if (is_array($warehouseIds)) {
                $map[(int) $userId] = array_values(array_filter(array_map('intval', $warehouseIds)));
            }
        }

        return $map;
    }

    /**
     * Check if a specific user is authorized to perform warehouse sales.
     */
    public function canUserMakeSales(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $this->isFeatureEnabled()) {
            return false;
        }

        return in_array((int) $user->id, $this->allowedUserIds(), true);
    }

    public static function userCanMakeSales(?User $user): bool
    {
        return app(self::class)->canUserMakeSales($user);
    }

    /**
     * Return all warehouses that this user is permitted to sell from.
     *
     * @return Collection<int, Warehouse>
     */
    public function allowedWarehousesForUser(?User $user): Collection
    {
        if (! $this->canUserMakeSales($user)) {
            return collect();
        }

        $map = $this->userWarehousesMap();
        $configuredWarehouseIds = $map[(int) $user->id] ?? [];

        if (empty($configuredWarehouseIds)) {
            // Fallback: Check if user has explicit warehouses attached on User model
            $userAttachedIds = $user->warehouses()->pluck('warehouses.id')->all();
            if (! empty($userAttachedIds)) {
                return Warehouse::query()->active()->whereIn('id', $userAttachedIds)->orderBy('name')->get();
            }

            // If user has all warehouse access or no specific filter, return all active
            if ($user->hasAllWarehouseAccess() || $user->hasRole('admin')) {
                return Warehouse::query()->active()->orderBy('name')->get();
            }

            return collect();
        }

        return Warehouse::query()
            ->active()
            ->whereIn('id', $configuredWarehouseIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if a user is permitted to sell from a specific warehouse ID.
     */
    public function canUserSellFromWarehouse(?User $user, int $warehouseId): bool
    {
        if (! $this->canUserMakeSales($user)) {
            return false;
        }

        return $this->allowedWarehousesForUser($user)->contains('id', $warehouseId);
    }

    public static function userCanSellFromWarehouse(?User $user, int $warehouseId): bool
    {
        return app(self::class)->canUserSellFromWarehouse($user, $warehouseId);
    }

    /**
     * Update Warehouse Sales configuration settings.
     *
     * @param  array{enabled: bool, allowed_user_ids: array<int, int>, user_warehouses: array<int, array<int, int>>}  $config
     */
    public function updateSettings(array $config): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => self::SETTING_ENABLED],
            ['value' => $config['enabled'] ? '1' : '0']
        );

        BusinessSetting::query()->updateOrCreate(
            ['key' => self::SETTING_ALLOWED_USER_IDS],
            ['value' => json_encode(array_values(array_map('intval', $config['allowed_user_ids'] ?? [])))]
        );

        BusinessSetting::query()->updateOrCreate(
            ['key' => self::SETTING_USER_WAREHOUSES],
            ['value' => json_encode($config['user_warehouses'] ?? [])]
        );
    }
}
