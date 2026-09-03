<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // User creation should NOT automatically create an Employee.
    }

    public function updated(User $user): void
    {
        // User updates should NOT automatically update an Employee.
    }
}
