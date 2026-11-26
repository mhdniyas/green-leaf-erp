<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseCreditNote;
use App\Support\CashbookAccess;

class WarehouseCreditNotePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['update', 'finalize', 'void', 'delete'], true)) {
            return null;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorizedRole($user)
            && $user->can(CashbookAccess::WarehouseCreditNotesView);
    }

    public function view(User $user, WarehouseCreditNote $creditNote): bool
    {
        return $this->isAuthorizedRole($user)
            && $user->can(CashbookAccess::WarehouseCreditNotesView)
            && $this->canAccessWarehouse($user, (int) $creditNote->warehouse_id);
    }

    public function create(User $user, ?int $warehouseId = null): bool
    {
        if (! $this->isAuthorizedRole($user) || ! $user->can(CashbookAccess::WarehouseCreditNotesManage)) {
            return false;
        }

        if ($warehouseId !== null) {
            return $this->canAccessWarehouse($user, $warehouseId);
        }

        return true;
    }

    public function update(User $user, WarehouseCreditNote $creditNote): bool
    {
        if (! $creditNote->isDraft()) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $this->isAuthorizedRole($user)
            && $user->can(CashbookAccess::WarehouseCreditNotesManage)
            && $this->canAccessWarehouse($user, (int) $creditNote->warehouse_id);
    }

    public function finalize(User $user, WarehouseCreditNote $creditNote): bool
    {
        if (! $creditNote->isDraft()) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $this->isAuthorizedRole($user)
            && $user->can(CashbookAccess::WarehouseCreditNotesManage)
            && $this->canAccessWarehouse($user, (int) $creditNote->warehouse_id);
    }

    public function void(User $user, WarehouseCreditNote $creditNote): bool
    {
        if (! $creditNote->isActive()) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $this->isAuthorizedRole($user)
            && $user->can(CashbookAccess::WarehouseCreditNotesVoid)
            && $this->canAccessWarehouse($user, (int) $creditNote->warehouse_id);
    }

    public function delete(User $user, WarehouseCreditNote $creditNote): bool
    {
        if (! $creditNote->isDraft()) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $this->isAuthorizedRole($user)
            && $user->can(CashbookAccess::WarehouseCreditNotesManage)
            && $this->canAccessWarehouse($user, (int) $creditNote->warehouse_id);
    }

    private function isAuthorizedRole(User $user): bool
    {
        return $user->isMainAdmin() || $user->hasAnyRole(['admin', 'accounts']);
    }

    private function canAccessWarehouse(User $user, int $warehouseId): bool
    {
        return $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->canAccessWarehouse($warehouseId);
    }
}
