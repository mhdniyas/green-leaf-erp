<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'updateItems') {
            return null;
        }

        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.order.view') || $user->can('warehouse.receive.view');
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->can('purchasing.order.view') || $user->can('warehouse.receive.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.order.create');
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        // PO can only be updated if it is in draft status
        return $user->can('purchasing.order.create') && $po->status->value === 'draft';
    }

    public function updateItems(User $user, PurchaseOrder $po): bool
    {
        return $user->can('purchasing.order.create')
            && in_array($po->status->value, ['draft', 'approved'])
            && ! $po->hasFinalLockedShopInvoices();
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $user->can('purchasing.order.create') && $po->status->value === 'draft';
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $user->can('purchasing.order.approve') && $po->status->value === 'draft';
    }

    public function reject(User $user, PurchaseOrder $po): bool
    {
        return $user->can('purchasing.order.approve') && $po->status->value === 'draft';
    }

    public function send(User $user, PurchaseOrder $po): bool
    {
        return $user->can('purchasing.order.approve') && $po->status->value === 'approved';
    }
}
