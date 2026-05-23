<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Sales\PaymentMethod;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_invoice_id' => SalesInvoice::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'reference' => $this->faker->optional()->bothify('REF-#####'),
            'notes' => $this->faker->optional()->sentence(),
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'created_by' => User::factory(),
        ];
    }
}
