<?php

declare(strict_types=1);

namespace App\Repositories\Purchasing;

use App\Models\PurchaseInvoice;
use App\Repositories\BaseRepository;

class PurchaseInvoiceRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return PurchaseInvoice::class;
    }
}
