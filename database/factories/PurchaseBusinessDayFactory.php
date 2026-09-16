<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseBusinessDay;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseBusinessDay>
 */
class PurchaseBusinessDayFactory extends Factory
{
    protected $model = PurchaseBusinessDay::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'business_date' => now()->toDateString(),
            'warehouse_id' => Warehouse::factory(),
            'status' => PurchaseBusinessDay::STATUS_OPEN,
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'closed_by' => null,
            'closed_at' => null,
            'reopened_by' => null,
            'reopened_at' => null,
            'reopen_reason' => null,
            'close_note' => null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => [
            'status' => PurchaseBusinessDay::STATUS_CLOSED,
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'close_note' => 'Day closed normally.',
        ]);
    }

    public function reopened(): self
    {
        return $this->state(fn () => [
            'status' => PurchaseBusinessDay::STATUS_REOPENED,
            'closed_by' => User::factory(),
            'closed_at' => now()->subHour(),
            'reopened_by' => User::factory(),
            'reopened_at' => now(),
            'reopen_reason' => 'Late vendor bills arrived.',
        ]);
    }
}
