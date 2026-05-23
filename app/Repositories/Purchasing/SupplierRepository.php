<?php

declare(strict_types=1);

namespace App\Repositories\Purchasing;

use App\Models\Supplier;
use App\Repositories\BaseRepository;

class SupplierRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Supplier::class;
    }
}
