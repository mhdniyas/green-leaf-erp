<?php

declare(strict_types=1);

namespace App\Repositories\Sales;

use App\Models\SalesInvoice;
use App\Repositories\BaseRepository;

class SalesInvoiceRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return SalesInvoice::class;
    }

    public function generateInvoiceNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = "INV-{$today}-";

        $count = $this->query()
            ->withTrashed()
            ->where('invoice_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
