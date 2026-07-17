<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccountingDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        view()->share('errors', new ViewErrorBag);
    }

    public function test_admin_accounting_dashboard_does_not_show_purchasing_handoff_card(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('admin.accounting.index', ['date' => '2026-07-17']))
            ->assertOk()
            ->assertSee('Owned Shop Accounting')
            ->assertDontSee('Purchasing workflow moved out of admin')
            ->assertDontSee('Open Purchasing Dashboard');
    }
}
