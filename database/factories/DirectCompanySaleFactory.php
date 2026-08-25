<?php

namespace Database\Factories;

use App\Models\Cashbook\CompanyAccount;
use App\Models\DirectCompanySale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DirectCompanySale>
 */
class DirectCompanySaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_uuid' => fake()->uuid(),
            'business_date' => fake()->date(),
            'customer_name' => fake()->optional()->name(),
            'amount' => fake()->randomFloat(2, 1, 50000),
            'payment_method' => 'bank',
            'company_account_id' => fn () => CompanyAccount::query()->firstOrCreate(
                ['name' => 'Main Operating Account'],
                ['account_type' => 'bank', 'enabled' => true]
            )->id,
            'reference' => fake()->optional()->bothify('DIRECT-####'),
            'note' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
