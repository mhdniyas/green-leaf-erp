<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\EmployeeAttendance;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DailyProgressPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_sees_empty_state_for_date_without_operations(): void
    {
        $admin = $this->adminUser();
        Shop::factory()->create(['name' => 'Main Shop']);

        $this
            ->actingAs($admin)
            ->get(route('admin.daily-progress', ['date' => '2026-07-17']))
            ->assertOk()
            ->assertSee('Daily Operational Progress')
            ->assertSee('No operational activity found for 17 Jul 2026')
            ->assertSee('No shop orders found')
            ->assertSee('Shops missing orders');
    }

    public function test_admin_sees_operational_details_for_populated_day(): void
    {
        $admin = $this->adminUser();
        $shop = Shop::factory()->create([
            'name' => 'Central Market Shop',
            'code' => 'CENTRAL',
        ]);
        Shop::factory()->create(['name' => 'No Order Shop']);
        $product = Product::factory()->create(['name' => 'Tomato']);

        PurchaseOrder::factory()->create([
            'order_date' => '2026-07-17',
            'status' => POStatus::Approved,
        ]);

        StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => '2026-07-17',
            'total_kg' => 150,
            'status' => BatchStatus::Pending,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'delivery_status' => 'pending_approval',
            'delivery_review_status' => 'pending',
            'payment_status' => 'partially_paid',
            'business_date' => '2026-07-17',
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'is_allocation_completed' => true,
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivered_by' => $admin->id,
            'cash_collected' => 1000,
            'cash_discrepancy' => 50,
            'balance_amount' => 200,
            'total_shortage_value' => 75,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'delivered_qty' => 8,
            'shortage_qty' => 2,
            'unit' => 'kg',
            'is_sorted' => true,
            'sorting_status' => 'loaded',
            'delivery_discrepancy_type' => 'shortage',
            'line_total' => 1000,
            'shortage_value' => 75,
        ]);

        ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260717-CENTRAL',
            'business_date' => '2026-07-17',
            'status' => 'generated',
            'delivery_status' => 'pending_approval',
            'payment_status' => 'partially_paid',
            'subtotal' => 1000,
            'shortage_total' => 75,
            'discount_total' => 0,
            'final_total' => 925,
            'paid_amount' => 725,
            'balance_amount' => 200,
            'generated_by' => $admin->id,
        ]);

        ShopAccountingEntry::create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-17',
            'status' => 'submitted',
            'opening_cash' => 100,
            'closing_cash' => 825,
            'created_by' => $admin->id,
            'submitted_by' => $admin->id,
            'submitted_at' => now(),
        ]);

        EmployeeAttendance::factory()->create([
            'shop_id' => $shop->id,
            'attendance_date' => '2026-07-17',
            'status' => 'present',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.daily-progress', ['date' => '2026-07-17']))
            ->assertOk()
            ->assertSee('Daily Control Room')
            ->assertSee('Central Market Shop')
            ->assertSee('Orders Needing Attention')
            ->assertSee('Delivery review pending')
            ->assertSee('Outstanding shop invoice balance')
            ->assertSee('Rs. 200.00')
            ->assertSee('Stock sorting pending')
            ->assertSee('SINV-20260717-CENTRAL')
            ->assertSee('Pending Approval');
    }

    private function adminUser(): User
    {
        Permission::findOrCreate('admin.daily-progress.view', 'web');

        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('admin.daily-progress.view');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
