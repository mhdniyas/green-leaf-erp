<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FulfillmentReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_fulfillment_report_labels_admin_direct_purchase_orders_without_shop(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        ShopOrder::query()->create([
            'shop_id' => null,
            'order_number' => 'ADP-20260717-REPORT',
            'order_source' => 'admin_direct_purchase',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'business_date' => '2026-07-17',
            'created_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('inventory.reports.fulfillment', [
                'start_date' => '2026-07-17',
                'end_date' => '2026-07-17',
            ]))
            ->assertOk()
            ->assertSee('ADP-20260717-REPORT')
            ->assertSee('Direct Purchase')
            ->assertDontSee('Attempt to read property');
    }
}
