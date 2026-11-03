<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProcurementExpenseCategory;
use App\Models\ProcurementExpenseSubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementExpenseSubCategory>
 */
class ProcurementExpenseSubCategoryFactory extends Factory
{
    protected $model = ProcurementExpenseSubCategory::class;

    public function definition(): array
    {
        $name = $this->faker->city().' -> Main Warehouse';

        return [
            'procurement_expense_category_id' => ProcurementExpenseCategory::factory(),
            'name' => $name,
            'code' => $this->faker->optional()->lexify('LOC-???'),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 50),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
