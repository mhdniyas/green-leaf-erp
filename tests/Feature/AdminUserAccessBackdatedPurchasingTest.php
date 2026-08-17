<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserAccessBackdatedPurchasingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $this->withoutVite();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchaser', 'web');
    }

    public function test_purchaser_is_restricted_to_operational_date(): void
    {
        $purchaser = User::factory()->create(['registration_status' => 'approved']);
        $purchaser->assignRole('purchaser');

        $pastDate = now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($purchaser)->get(route('purchaser.daily', ['date' => $pastDate]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Only the active business day order can be viewed/processed.');
    }

    public function test_admin_user_access_allows_backdated_purchasing_without_locking(): void
    {
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');

        $admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
            'registration_status' => 'approved',
        ]);
        $admin->assignRole('admin');

        $purchaser = User::factory()->create(['registration_status' => 'approved']);
        $purchaser->assignRole('purchaser');

        $pastDate = now()->subDays(5)->format('Y-m-d');

        // Start User Access impersonation session
        $this->actingAs($admin)->post(route('admin.user-access.store', ['user' => $purchaser->public_uuid]));

        // Request backdated purchaser daily route as admin acting as purchaser
        $response = $this->get(route('purchaser.daily', ['date' => $pastDate]));

        $response->assertOk();
    }
}
