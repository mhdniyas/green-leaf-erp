<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseSale;
use App\Services\Warehouse\WarehouseSalesAccessService;

class WarehouseSalePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            if ($ability === 'cancel') {
                return null; // Admin still cannot cancel already-cancelled sales
            }

            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return WarehouseSalesAccessService::userCanMakeSales($user);
    }

    public function view(User $user, WarehouseSale $sale): bool
    {
        if ($user->can('accounting.ledger.view')) {
            return true;
        }

        return WarehouseSalesAccessService::userCanSellFromWarehouse($user, (int) $sale->warehouse_id);
    }

    public function create(User $user, ?int $warehouseId = null): bool
    {
        if (! WarehouseSalesAccessService::userCanMakeSales($user)) {
            return false;
        }

        if ($warehouseId !== null) {
            return WarehouseSalesAccessService::userCanSellFromWarehouse($user, $warehouseId);
        }

        return true;
    }

    public function cancel(User $user, WarehouseSale $sale): bool
    {
        if ($sale->isCancelled()) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $sale->sold_by_user_id === $user->id
            && WarehouseSalesAccessService::userCanSellFromWarehouse($user, (int) $sale->warehouse_id);
    }
}
