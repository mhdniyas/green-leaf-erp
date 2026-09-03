<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupLegacyStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cleanup_command_deletes_legacy_usr_and_staff_employees_without_deleting_users(): void
    {
        $shop = Shop::factory()->create();
        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        /** @var User $user1 */
        $user1 = User::factory()->create(['name' => 'Legacy Owner 1', 'email' => 'legacy1@greenleaf.local']);
        $user1->assignRole('shop');

        /** @var Employee $legacyUsrEmployee */
        $legacyUsrEmployee = Employee::factory()->create([
            'user_id' => $user1->id,
            'employee_code' => 'USR-00001-AV',
            'name' => 'Legacy Owner 1',
            'employee_category_id' => $category->id,
            'default_shop_id' => $shop->id,
        ]);

        /** @var Employee $dummyStaffEmployee */
        $dummyStaffEmployee = Employee::factory()->create([
            'user_id' => null,
            'employee_code' => 'STAFF-CASIO-01',
            'name' => 'Casio Staff 1',
            'employee_category_id' => $category->id,
            'default_shop_id' => $shop->id,
        ]);

        /** @var Employee $realEmployee */
        $realEmployee = Employee::factory()->create([
            'user_id' => null,
            'employee_code' => 'EMP-00001',
            'name' => 'Real Staff Worker',
            'employee_category_id' => $category->id,
            'default_shop_id' => $shop->id,
        ]);

        $usersCountBefore = User::count();
        $employeesCountBefore = Employee::count();

        // 1. Dry run execution
        $this->artisan('hr:cleanup-legacy-staff', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertEquals($usersCountBefore, User::count());
        $this->assertEquals($employeesCountBefore, Employee::count());

        // 2. Real cleanup execution
        $this->artisan('hr:cleanup-legacy-staff')
            ->assertExitCode(0);

        // User account MUST survive
        $this->assertDatabaseHas('users', ['id' => $user1->id]);
        $this->assertEquals($usersCountBefore, User::count());

        // Legacy USR and STAFF employees deleted
        $this->assertDatabaseMissing('employees', ['id' => $legacyUsrEmployee->id]);
        $this->assertDatabaseMissing('employees', ['id' => $dummyStaffEmployee->id]);

        // Real EMP-* employee MUST be preserved
        $this->assertDatabaseHas('employees', ['id' => $realEmployee->id]);
    }
}
