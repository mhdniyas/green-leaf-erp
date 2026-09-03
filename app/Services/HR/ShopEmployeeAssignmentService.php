<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShopEmployeeAssignmentService
{
    public function assign(Employee $employee, Shop $shop, Carbon $effectiveFrom, User $actor, ?string $notes = null): ShopEmployeeAssignment
    {
        return DB::transaction(function () use ($employee, $shop, $effectiveFrom, $actor, $notes): ShopEmployeeAssignment {
            ShopEmployeeAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString());
                })
                ->update([
                    'status' => 'inactive',
                    'effective_to' => $effectiveFrom->copy()->subDay()->toDateString(),
                ]);

            $assignment = ShopEmployeeAssignment::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'assigned_by' => $actor->id,
                    'effective_from' => $effectiveFrom->toDateString(),
                    'effective_to' => null,
                    'status' => 'active',
                    'notes' => $notes,
                ],
            );

            $employee->update([
                'default_shop_id' => $shop->id,
                'staff_area' => 'shop',
            ]);

            return $assignment->fresh(['shop', 'employee', 'assignedBy']);
        });
    }

    public function isAssignedToShopOn(Employee $employee, Shop $shop, Carbon $date): bool
    {
        return ShopEmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $shop->id)
            ->where('status', 'active')
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->exists();
    }

    public function hasWorkedAtShopOnOrBefore(Employee $employee, Shop $shop, Carbon $date): bool
    {
        return ShopEmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $shop->id)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->exists();
    }

    public function getAssignedShopOn(Employee $employee, Carbon $date): ?Shop
    {
        $assignment = ShopEmployeeAssignment::query()
            ->with('shop')
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        return $assignment?->shop ?? $employee->defaultShop;
    }
}
