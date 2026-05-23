<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Sales\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesInvoice>
 */
class SalesInvoiceFactory extends Factory
{
    protected $model = SalesInvoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 100, 10000);

        return [
            'sales_order_id' => SalesOrder::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => $amount,
            'paid_amount' => 0,
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => SalesInvoiceStatus::Unpaid,
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => $attributes['amount'],
            'status' => SalesInvoiceStatus::Paid,
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'status' => SalesInvoiceStatus::Overdue,
        ]);
    }
}
