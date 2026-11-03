<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProcurementExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProcurementExpenseCategory>
 */
class ProcurementExpenseCategoryFactory extends Factory
{
    protected $model = ProcurementExpenseCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'key' => Str::slug($name, '_'),
            'name' => ucwords($name),
            'sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_NONE,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function optionalLocations(): static
    {
        return $this->state(['sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_OPTIONAL]);
    }

    public function requiredLocations(): static
    {
        return $this->state(['sub_category_mode' => ProcurementExpenseCategory::SUB_CATEGORY_MODE_REQUIRED]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
