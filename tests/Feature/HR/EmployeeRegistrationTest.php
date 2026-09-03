<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_register_employee_without_user_account(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = EmployeeCategory::factory()->create();
        $shop = Shop::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'John Doe Worker',
                'employee_code' => 'EMP-TEST-999',
                'employee_category_id' => $category->id,
                'staff_area' => 'shop',
                'default_shop_id' => $shop->id,
                'phone' => '9876543210',
                'alternate_phone' => '9123456789',
                'email' => 'johndoe@example.com',
                'id_type' => 'aadhaar',
                'id_number' => '999988887777',
                'address' => '456 Palm Grove Road, Indiranagar, Bengaluru',
                'monthly_salary' => 30000,
                'salary_type' => 'monthly',
                'daily_wage' => 0,
                'employment_status' => 'active',
                'joined_on' => '2026-09-01',
                'notes' => 'Test worker record',
                'photo' => UploadedFile::fake()->image('john_photo.jpg'),
                'id_front' => UploadedFile::fake()->image('aadhaar_front.jpg'),
                'id_back' => UploadedFile::fake()->image('aadhaar_back.jpg'),
            ]);

        $response->assertRedirect(route('admin.staff.employees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'name' => 'John Doe Worker',
            'employee_code' => 'EMP-TEST-999',
            'user_id' => null,
            'default_shop_id' => $shop->id,
            'phone' => '9876543210',
            'alternate_phone' => '9123456789',
            'id_type' => 'aadhaar',
            'id_number' => '999988887777',
            'address' => '456 Palm Grove Road, Indiranagar, Bengaluru',
        ]);

        $employee = Employee::query()->where('employee_code', 'EMP-TEST-999')->firstOrFail();
        $this->assertNull($employee->user_id);
        $this->assertFalse((bool) $employee->is_user_linked);
        $this->assertEquals('Aadhaar •••• 7777', $employee->masked_id_number);
        $this->assertNotNull($employee->photo_path);
        $this->assertNotNull($employee->id_front_path);
        $this->assertNotNull($employee->id_back_path);

        Storage::disk('public')->assertExists($employee->photo_path);
        Storage::disk('public')->assertExists($employee->id_front_path);
        Storage::disk('public')->assertExists($employee->id_back_path);

        // Verify stored file size is <= 512 KB
        $this->assertLessThanOrEqual(524288, Storage::disk('public')->size($employee->photo_path));
        $this->assertLessThanOrEqual(524288, Storage::disk('public')->size($employee->id_front_path));
        $this->assertLessThanOrEqual(524288, Storage::disk('public')->size($employee->id_back_path));
    }

    public function test_admin_can_register_employee_with_passport_or_other_id(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = EmployeeCategory::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Jane Smith Office',
                'employee_code' => 'EMP-TEST-888',
                'employee_category_id' => $category->id,
                'staff_area' => 'office',
                'phone' => '9888877776',
                'id_type' => 'other',
                'other_id_type' => 'State Ration Card',
                'id_number' => 'RC-987654321',
                'monthly_salary' => 35000,
                'salary_type' => 'monthly',
                'employment_status' => 'active',
            ]);

        $response->assertRedirect(route('admin.staff.employees.index'));

        $employee = Employee::query()->where('employee_code', 'EMP-TEST-888')->firstOrFail();
        $this->assertEquals('other', $employee->id_type);
        $this->assertEquals('State Ration Card', $employee->other_id_type);
        $this->assertEquals('State Ration Card •••• 4321', $employee->masked_id_number);
    }

    public function test_creating_user_does_not_automatically_create_employee(): void
    {
        $userCountBefore = User::query()->count();
        $employeeCountBefore = Employee::query()->count();

        $newUser = User::factory()->create([
            'name' => 'Standalone User Account',
            'email' => 'standalone@greenleaf.test',
        ]);
        $newUser->assignRole('shop');

        $this->assertEquals($userCountBefore + 1, User::query()->count());
        $this->assertEquals($employeeCountBefore, Employee::query()->count());
        $this->assertDatabaseMissing('employees', [
            'name' => 'Standalone User Account',
        ]);
    }

    public function test_employee_code_is_auto_generated_when_left_blank(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = EmployeeCategory::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Auto Code Worker',
                'employee_code' => '',
                'employee_category_id' => $category->id,
                'staff_area' => 'office',
                'id_type' => 'aadhaar',
                'id_number' => '999911112222',
                'monthly_salary' => 25000,
                'salary_type' => 'monthly',
                'employment_status' => 'active',
            ]);

        $response->assertRedirect(route('admin.staff.employees.index'));

        $employee = Employee::query()->where('name', 'Auto Code Worker')->firstOrFail();
        $this->assertStringStartsWith('EMP-', $employee->employee_code);
    }

    public function test_admin_can_register_employee_with_cropped_base64_data_urls(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = EmployeeCategory::factory()->create();

        // 1x1 white pixel JPEG base64 data URL
        $base64Image = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

        $response = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Cropped Data Worker',
                'employee_code' => 'EMP-CROP-101',
                'employee_category_id' => $category->id,
                'staff_area' => 'office',
                'id_type' => 'aadhaar',
                'id_number' => '222233334444',
                'monthly_salary' => 40000,
                'salary_type' => 'monthly',
                'employment_status' => 'active',
                'photo_data_url' => $base64Image,
                'id_front_data_url' => $base64Image,
                'id_back_data_url' => $base64Image,
            ]);

        $response->assertRedirect(route('admin.staff.employees.index'));

        $employee = Employee::query()->where('employee_code', 'EMP-CROP-101')->firstOrFail();
        $this->assertNotNull($employee->photo_path);
        $this->assertNotNull($employee->id_front_path);
        $this->assertNotNull($employee->id_back_path);

        Storage::disk('public')->assertExists($employee->photo_path);
        Storage::disk('public')->assertExists($employee->id_front_path);
        Storage::disk('public')->assertExists($employee->id_back_path);

        $this->assertLessThanOrEqual(524288, Storage::disk('public')->size($employee->photo_path));
        $this->assertLessThanOrEqual(524288, Storage::disk('public')->size($employee->id_front_path));
        $this->assertLessThanOrEqual(524288, Storage::disk('public')->size($employee->id_back_path));
    }

    public function test_admin_can_register_employee_with_daily_wage_salary_type(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = EmployeeCategory::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Daily Wage Worker',
                'employee_code' => 'EMP-DAILY-505',
                'employee_category_id' => $category->id,
                'staff_area' => 'office',
                'id_type' => 'aadhaar',
                'id_number' => '555566667777',
                'salary_type' => 'daily_wage',
                'daily_wage' => 850.50,
                'employment_status' => 'active',
            ]);

        $response->assertRedirect(route('admin.staff.employees.index'));

        $employee = Employee::query()->where('employee_code', 'EMP-DAILY-505')->firstOrFail();
        $this->assertEquals('daily_wage', $employee->salary_type);
        $this->assertEquals(850.50, (float) $employee->daily_wage);
    }

    public function test_other_id_type_name_required_only_when_id_type_is_other(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = EmployeeCategory::factory()->create();

        // 1. Submit id_type = other without other_id_type -> should fail validation
        $response1 = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Test Fail Worker',
                'employee_category_id' => $category->id,
                'staff_area' => 'office',
                'id_type' => 'other',
                'other_id_type' => '',
                'id_number' => 'ANY-1234',
                'salary_type' => 'monthly',
                'monthly_salary' => 20000,
                'employment_status' => 'active',
            ]);

        $response1->assertSessionHasErrors('other_id_type');

        // 2. Submit id_type = aadhaar without other_id_type -> should succeed
        $response2 = $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Aadhaar Valid Worker',
                'employee_category_id' => $category->id,
                'staff_area' => 'office',
                'id_type' => 'aadhaar',
                'other_id_type' => '',
                'id_number' => '333344445555',
                'salary_type' => 'monthly',
                'monthly_salary' => 20000,
                'employment_status' => 'active',
            ]);

        $response2->assertSessionHasNoErrors();
    }
}
