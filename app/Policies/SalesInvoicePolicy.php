<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;

class SalesInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.invoice.view');
    }

    public function view(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can('sales.invoice.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.invoice.create');
    }

    public function recordPayment(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can('sales.payment.record');
    }
}
