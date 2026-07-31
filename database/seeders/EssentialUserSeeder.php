<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
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
        $defaultPriceGroup = $this->defaultShopPriceGroup();
        $this->defaultEmployeeAdvanceRule();

        $this->deactivateRemovedSeedShops();

        foreach ($this->roleAccounts() as $account) {
            $roleFirstWord = explode(' ', trim($account['name']))[0];
            $rolePassword = $roleFirstWord.'@123';

            $user = $this->upsertUser(
                name: $account['name'],
                email: $account['email'],
                password: $rolePassword,
                shop: null,
            );

            $user->syncRoles([$account['role']]);
            app(EmployeeSyncService::class)->ensureForUser($user->fresh());
        }

        foreach ($this->shops() as $shopSeed) {
            $existingShop = Shop::query()->where('code', $shopSeed['code'])->first();

            $shop = Shop::query()->updateOrCreate(
                ['code' => $shopSeed['code']],
                [
                    'name' => $shopSeed['name'],
                    'warehouse_tag' => $shopSeed['warehouse_tag'],
                    'status' => 'active',
                    'shop_price_group_id' => $existingShop?->shop_price_group_id ?? $defaultPriceGroup->id,
                    'client_id' => $this->clientId($shopSeed['client_code']),
                    'accounting_mode' => $shopSeed['accounting_mode'],
                    'accounting_enabled' => $shopSeed['accounting_enabled'],
                    'approved_at' => now(),
                ],
            );

            $shopFirstWord = explode(' ', trim($shopSeed['name']))[0];
            $shopPassword = $shopFirstWord.'@123';

            $owner = $this->upsertUser(
                name: $shopSeed['name'].' Owner',
                email: $shopSeed['owner_email'],
                password: $shopPassword,
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

        $this->call(ShopAccountingCategorySeeder::class);

        $this->deactivateRemovedSeedShops();

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
            ['name' => 'Faisal', 'email' => 'faisal@greenleaf.com', 'role' => 'purchaser'],
            ['name' => 'Shadhuli', 'email' => 'shadhuli@greenleaf.com', 'role' => 'purchaser'],
            ['name' => 'Ashraf', 'email' => 'ashraf@greenleaf.com', 'role' => 'purchaser'],
            ['name' => 'Warehouse Receiver', 'email' => 'receiver@greenleaf.com', 'role' => 'warehouse_receiver'],
        ];
    }

    /**
     * @return array<int, array{code:string, name:string, warehouse_tag:string, owner_email:string, accounting_mode:string, accounting_enabled:bool, client_code:?string}>
     */
    private function shops(): array
    {
        return [
            [
                'code' => 'AV_CASIO',
                'name' => 'Casio',
                'warehouse_tag' => 'AV-CASIO',
                'owner_email' => 'shop-aishwarya-casio@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_LULU_BUDHIGERE',
                'name' => 'Lulu Budhigere',
                'warehouse_tag' => 'AV-BDG',
                'owner_email' => 'shop-aishwarya-lulu-budhigere@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_GRANDCITY',
                'name' => 'Grandcity',
                'warehouse_tag' => 'AV-GCITY',
                'owner_email' => 'shop-aishwarya-grancity@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_ASHIRWAD',
                'name' => 'Ashirwad',
                'warehouse_tag' => 'AV-ASH',
                'owner_email' => 'shop-aishwarya-ashirwad@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_SANA',
                'name' => 'Sana',
                'warehouse_tag' => 'AV-SANA',
                'owner_email' => 'shop-aishwarya-sana@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_BAZARO',
                'name' => 'Bazaro',
                'warehouse_tag' => 'AV-BAZ',
                'owner_email' => 'shop-aishwarya-bazaro@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_SANA_JP',
                'name' => 'Sana JP',
                'warehouse_tag' => 'AV-SANA-JP',
                'owner_email' => 'shop-aishwarya-sana-jp@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_LULU_VARTHUR',
                'name' => 'Lulu Varthur',
                'warehouse_tag' => 'AV-VAR',
                'owner_email' => 'shop-aishwarya-lulu-varthur@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_GM_MIDLAND',
                'name' => 'GM Midland',
                'warehouse_tag' => 'AV-GM',
                'owner_email' => 'shop-aishwarya-gm@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_FOOD_PALACE_HSR',
                'name' => 'FOOD PALACE HSR',
                'warehouse_tag' => 'AV-HSR',
                'owner_email' => 'shop-aishwarya-hsr@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_LULU_BEGUR',
                'name' => 'Lulu Begur',
                'warehouse_tag' => 'AV-BEGUR',
                'owner_email' => 'shop-aishwarya-lulu-begur@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'AV_JINDAL_CITY',
                'name' => 'Families Jindal City',
                'warehouse_tag' => 'AV-JINDAL',
                'owner_email' => 'shop-aishwarya-jindal-city@greenleaf.com',
                'accounting_mode' => 'owned',
                'accounting_enabled' => true,
                'client_code' => 'AISHWARYA_VEG',
            ],
            [
                'code' => 'DS_QUICK_MART',
                'name' => 'Quick Mart',
                'warehouse_tag' => 'DS-QM',
                'owner_email' => 'shop-direct-quick-mart@greenleaf.com',
                'accounting_mode' => 'regular',
                'accounting_enabled' => false,
                'client_code' => null,
            ],
            [
                'code' => 'DS_FORTUNE_SM',
                'name' => 'Fortune SM',
                'warehouse_tag' => 'DS-FSM',
                'owner_email' => 'shop-direct-fortune-sm@greenleaf.com',
                'accounting_mode' => 'regular',
                'accounting_enabled' => false,
                'client_code' => null,
            ],
        ];
    }

    private function clientId(?string $clientCode): ?int
    {
        if ($clientCode === null) {
            return null;
        }

        return Client::query()->updateOrCreate(
            ['code' => $clientCode],
            [
                'name' => 'Aishwarya Veg',
                'status' => 'active',
                'notes' => 'Default client for client-shop accounting.',
            ],
        )->id;
    }

    private function deactivateRemovedSeedShops(): void
    {
        $activeSeedCodes = collect($this->shops())->pluck('code')->all();

        Shop::query()
            ->whereNotIn('code', $activeSeedCodes)
            ->update([
                'status' => 'inactive',
                'client_id' => null,
                'accounting_enabled' => false,
                'warehouse_tag' => null,
            ]);
    }
}
