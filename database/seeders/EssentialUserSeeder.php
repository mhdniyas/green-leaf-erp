<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRule;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\HR\EmployeeSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EssentialUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('BASE_ROLE_USER_PASSWORD');
        $defaultPriceGroup = $this->defaultShopPriceGroup();
        $this->defaultEmployeeAdvanceRule();

        foreach ($this->roleAccounts() as $account) {
            $user = $this->upsertUser(
                name: $account['name'],
                email: $account['email'],
                password: $password,
                shop: null,
            );

            $user->syncRoles([$account['role']]);
            app(EmployeeSyncService::class)->ensureForUser($user->fresh());
        }

        foreach ($this->shops() as $shopSeed) {
            $shop = Shop::query()->updateOrCreate(
                ['code' => $shopSeed['code']],
                [
                    'name' => $shopSeed['name'],
                    'warehouse_tag' => $shopSeed['warehouse_tag'],
                    'status' => 'active',
                    'shop_price_group_id' => $shop->shop_price_group_id ?? $defaultPriceGroup->id,
                    'accounting_mode' => 'owned',
                    'accounting_enabled' => true,
                    'approved_at' => now(),
                ],
            );

            $owner = $this->upsertUser(
                name: $shopSeed['name'].' Owner',
                email: $shopSeed['owner_email'],
                password: $password,
                shop: $shop,
            );

            $owner->syncRoles(['shop']);

            ShopOwnerAssignment::query()->updateOrCreate([
                'user_id' => $owner->id,
                'shop_id' => $shop->id,
            ]);

            app(EmployeeSyncService::class)->ensureForUser($owner->fresh());

            $this->ensureAssignedShopStaff($shop, $owner);
        }

        $this->command?->info('Essential role users and real shop-owner users seeded successfully.');
    }

    private function defaultShopPriceGroup(): ShopPriceGroup
    {
        return ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'B'],
            [
                'default_margin_percent' => 12,
                'is_active' => true,
            ],
        );
    }

    private function defaultEmployeeAdvanceRule(): EmployeeAdvanceRule
    {
        return EmployeeAdvanceRule::query()->updateOrCreate(
            ['name' => 'Default advance rule'],
            [
                'minimum_present_days' => 10,
                'advance_percent' => 50,
                'default_from_petty_cash' => true,
                'allow_negative_shop_balance' => true,
                'is_active' => true,
                'notes' => 'Seeded for shop-owner advance testing after 10 present check-ins.',
            ],
        );
    }

    private function upsertUser(string $name, string $email, ?string $password, ?Shop $shop): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $attributes = [
            'name' => $name,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'shop_id' => $shop?->id,
            'registration_status' => 'approved',
            'approved_at' => $user->approved_at ?? now(),
            'approved_by' => null,
        ];

        if ($password !== null && $password !== '') {
            $attributes['password'] = Hash::make($password);
        } elseif (! $user->exists) {
            $attributes['password'] = Hash::make(Str::password(32));
        }

        $user->forceFill($attributes)->save();

        return $user;
    }

    private function ensureAssignedShopStaff(Shop $shop, User $assignedBy): void
    {
        $category = EmployeeCategory::query()->firstOrCreate(
            ['code' => 'other-shop'],
            [
                'name' => 'Shop Employees',
                'staff_area' => 'shop',
                'default_monthly_salary' => 18000,
                'monthly_paid_leave_limit' => 4,
                'is_active' => true,
            ],
        );
        foreach ($this->shopStaffSeeds($shop) as $staffSeed) {
            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $staffSeed['code']],
                [
                    'default_shop_id' => $shop->id,
                    'employee_category_id' => $category->id,
                    'name' => $staffSeed['name'],
                    'email' => strtolower($staffSeed['code']).'@greenleaf.local',
                    'staff_area' => 'shop',
                    'employment_status' => 'active',
                    'joined_on' => now()->startOfMonth()->toDateString(),
                    'monthly_salary' => $staffSeed['salary'],
                    'is_user_linked' => false,
                    'notes' => 'Seeded essential shop staff for attendance and advance testing.',
                ],
            );

            ShopEmployeeAssignment::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'assigned_by' => $assignedBy->id,
                    'effective_from' => now()->startOfMonth()->toDateString(),
                    'effective_to' => null,
                    'status' => 'active',
                    'notes' => 'Seeded essential assignment for shop-owner staff workflow.',
                ],
            );

            $this->seedPresentCheckIns($employee, $shop, $assignedBy);
        }
    }

    /**
     * @return array<int, array{code:string, name:string, salary:int}>
     */
    private function shopStaffSeeds(Shop $shop): array
    {
        return [
            [
                'code' => 'STAFF-'.$shop->code.'-01',
                'name' => $shop->name.' Staff 1',
                'salary' => 18000,
            ],
            [
                'code' => 'STAFF-'.$shop->code.'-02',
                'name' => $shop->name.' Staff 2',
                'salary' => 21000,
            ],
            [
                'code' => 'STAFF-'.$shop->code.'-03',
                'name' => $shop->name.' Staff 3',
                'salary' => 24000,
            ],
        ];
    }

    private function seedPresentCheckIns(Employee $employee, Shop $shop, User $markedBy): void
    {
        $startDate = now()->startOfMonth();

        for ($dayOffset = 0; $dayOffset < 10; $dayOffset++) {
            $attendanceDate = $startDate->copy()->addDays($dayOffset);

            EmployeeAttendance::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_date' => $attendanceDate->toDateString(),
                ],
                [
                    'status' => 'present',
                    'shop_id' => $shop->id,
                    'marked_by' => $markedBy->id,
                    'source' => 'owner',
                    'notes' => 'Seeded present check-in for advance testing.',
                ],
            );
        }
    }

    /**
     * @return array<int, array{name:string, email:string, role:string}>
     */
    private function roleAccounts(): array
    {
        return [
            ['name' => 'Administrator', 'email' => 'admin@greenleaf.com', 'role' => 'admin'],
            ['name' => 'HR Manager', 'email' => 'hr@greenleaf.com', 'role' => 'hr_manager'],
            ['name' => 'Purchase Manager', 'email' => 'purchase@greenleaf.com', 'role' => 'purchase'],
            ['name' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'role' => 'purchaser'],
            ['name' => 'Warehouse Receiver', 'email' => 'receiver@greenleaf.com', 'role' => 'warehouse_receiver'],
        ];
    }

    /**
     * @return array<int, array{code:string, name:string, warehouse_tag:string, owner_email:string}>
     */
    private function shops(): array
    {
        return [
            ['code' => 'SHOP_CASIO', 'name' => 'Casio Hypermarket', 'warehouse_tag' => 'A', 'owner_email' => 'shop-casio@greenleaf.com'],
            ['code' => 'SHOP_BUDEGERE', 'name' => 'Budegere', 'warehouse_tag' => 'B', 'owner_email' => 'shop-budegere@greenleaf.com'],
            ['code' => 'SHOP_GRANCITY', 'name' => 'Grancity', 'warehouse_tag' => 'C', 'owner_email' => 'shop-grancity@greenleaf.com'],
            ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad', 'warehouse_tag' => 'D', 'owner_email' => 'shop-ashirwad@greenleaf.com'],
            ['code' => 'SHOP_METRO', 'name' => 'Metro Retail', 'warehouse_tag' => 'E', 'owner_email' => 'shop-metro@greenleaf.com'],
            ['code' => 'SHOP_RELIANCE', 'name' => 'Reliance Fresh', 'warehouse_tag' => 'F', 'owner_email' => 'shop-reliance@greenleaf.com'],
            ['code' => 'SHOP_SPAR', 'name' => 'Spar Hypermarket', 'warehouse_tag' => 'G', 'owner_email' => 'shop-spar@greenleaf.com'],
            ['code' => 'SHOP_MORE', 'name' => 'More Supermarket', 'warehouse_tag' => 'H', 'owner_email' => 'shop-more@greenleaf.com'],
            ['code' => 'SHOP_LULU', 'name' => 'Lulu Express', 'warehouse_tag' => 'I', 'owner_email' => 'shop-lulu@greenleaf.com'],
            ['code' => 'SHOP_STAR', 'name' => 'Star Bazaar', 'warehouse_tag' => 'J', 'owner_email' => 'shop-star@greenleaf.com'],
            ['code' => 'SHOP_FOODWORLD', 'name' => 'Foodworld', 'warehouse_tag' => 'K', 'owner_email' => 'shop-foodworld@greenleaf.com'],
            ['code' => 'SHOP_NILGIRIS', 'name' => 'Nilgiris', 'warehouse_tag' => 'L', 'owner_email' => 'shop-nilgiris@greenleaf.com'],
            ['code' => 'SHOP_DMART', 'name' => 'DMart', 'warehouse_tag' => 'M', 'owner_email' => 'shop-dmart@greenleaf.com'],
            ['code' => 'SHOP_EASYDAY', 'name' => 'Easyday', 'warehouse_tag' => 'N', 'owner_email' => 'shop-easyday@greenleaf.com'],
        ];
    }
}
