<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseInvoice;
use App\Models\User;

class PurchaseInvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('accounting.ledger.view');
    }

    public function view(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->can('accounting.ledger.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounting.entry.create');
    }

    public function update(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->can('accounting.entry.create');
    }
}
