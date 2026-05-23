<?php

declare(strict_types=1);

namespace App\Repositories\Sales;

use App\Models\Payment;
use App\Repositories\BaseRepository;

class PaymentRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Payment::class;
    }
}
