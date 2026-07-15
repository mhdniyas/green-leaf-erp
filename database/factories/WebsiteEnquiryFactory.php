<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebsiteEnquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteEnquiry>
 */
class WebsiteEnquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'customer_type' => fake()->randomElement([
                'Wholesale buyer',
                'Retail customer',
                'Restaurant or hotel',
                'Shop or supermarket',
            ]),
            'required_date' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'message' => fake()->sentence(12),
            'source_page' => fake()->randomElement(['home', 'products']),
        ];
    }
}
