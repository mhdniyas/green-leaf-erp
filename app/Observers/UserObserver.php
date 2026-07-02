<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\HR\EmployeeSyncService;

class UserObserver
{
    public function __construct(
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function created(User $user): void
    {
        $this->employeeSyncService->ensureForUser($user);
    }

    public function updated(User $user): void
    {
        $this->employeeSyncService->ensureForUser($user);
    }
}
