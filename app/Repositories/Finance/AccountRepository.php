<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use App\Models\Account;
use App\Repositories\BaseRepository;

class AccountRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return Account::class;
    }
}
