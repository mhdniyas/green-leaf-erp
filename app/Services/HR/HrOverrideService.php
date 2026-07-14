<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\HrOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HrOverrideService
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $overrideType,
        ?Employee $employee,
        ?Model $relatedRecord,
        array $oldValues,
        array $newValues,
        string $reason,
        User $actor,
    ): ?HrOverride {
        if ($oldValues === $newValues) {
            return null;
        }

        return HrOverride::query()->create([
            'employee_id' => $employee?->id,
            'override_type' => $overrideType,
            'related_type' => $relatedRecord !== null ? $relatedRecord::class : null,
            'related_id' => $relatedRecord?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
        ]);
    }
}
