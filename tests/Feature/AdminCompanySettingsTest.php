<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCompanySettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_update_company_bill_details(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $this
            ->actingAs($admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf',
                'company_address' => 'Market Road, Amravati',
                'company_phone' => '+91 9876543210',
                'company_email' => 'billing@greenleaf.test',
            ])
            ->assertRedirect(route('admin.company-settings.edit'));

        $this->assertSame('Green Leaf', BusinessSetting::query()->where('key', 'company_name')->value('value'));
        $this->assertSame('Market Road, Amravati', BusinessSetting::query()->where('key', 'company_address')->value('value'));
        $this->assertSame('+91 9876543210', BusinessSetting::query()->where('key', 'company_phone')->value('value'));
        $this->assertSame('billing@greenleaf.test', BusinessSetting::query()->where('key', 'company_email')->value('value'));
    }

    public function test_non_admin_cannot_update_company_bill_details(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf',
            ])
            ->assertForbidden();

        $this->assertNull(BusinessSetting::query()->where('key', 'company_name')->value('value'));
    }
}
