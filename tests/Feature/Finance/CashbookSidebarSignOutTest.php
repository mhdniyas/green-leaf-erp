<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookSidebarSignOutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Main Admin',
            'email' => 'admin@greenleaf.test',
        ]);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
    }

    public function test_cashbook_settings_sidebar_renders_sign_out_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings'));

        $response->assertOk();
        $response->assertSee(route('logout'));
        $response->assertSee('Sign Out');
        $response->assertSee('data-cashbook-sidebar-label', false);
    }

    public function test_submitting_sign_out_logs_user_out_and_redirects_to_login(): void
    {
        $this->actingAs($this->admin);
        $this->assertAuthenticatedAs($this->admin);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_protected_cashbook_pages_require_login_after_sign_out(): void
    {
        // Unauthenticated request
        $response = $this->get(route('admin.cashbook.settings'));

        $response->assertRedirect(route('login'));
    }
}
