<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\EmployeeCategorySeeder;
use Database\Seeders\ShopEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopEmployeeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_thirty_non_user_shop_employees(): void
    {
        $this->seed([
            DemoUserSeeder::class,
            EmployeeCategorySeeder::class,
            ShopEmployeeSeeder::class,
        ]);

        $shopEmployeeCategory = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $seededEmployees = Employee::query()
            ->where('employee_code', 'like', 'SHOPEMP%')
            ->orderBy('employee_code')
            ->get();

        $this->assertCount(30, $seededEmployees);
        $this->assertTrue($seededEmployees->every(fn (Employee $employee): bool => $employee->staff_area === 'shop'));
        $this->assertTrue($seededEmployees->every(fn (Employee $employee): bool => $employee->employee_category_id === $shopEmployeeCategory->id));
        $this->assertTrue($seededEmployees->every(fn (Employee $employee): bool => $employee->default_shop_id !== null));
        $this->assertTrue($seededEmployees->every(fn (Employee $employee): bool => $employee->is_user_linked === false));
    }
}
