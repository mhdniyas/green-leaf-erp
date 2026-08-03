<?php

namespace Tests\Feature;

use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminChangeBillVendorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
    }

    public function test_admin_can_change_vendor_on_purchase_bill(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchase');

        $originalSupplier = Supplier::factory()->create(['name' => 'Original Vendor']);
        $newSupplier = Supplier::factory()->create(['name' => 'New Vendor']);

        $cart = PurchaserCart::factory()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $originalSupplier->id,
        ]);

        $grn = GoodsReceived::factory()->create([
            'supplier_id' => $originalSupplier->id,
            'purchaser_cart_id' => $cart->id,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $grn->id,
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $originalSupplier->id,
            'amount' => 5000.00,
        ]);

        // 1. Non-admin update fails with 403
        $responseNonAdmin = $this->actingAs($purchaser)->post(route('purchasing.invoices.change-supplier', $invoice), [
            'supplier_id' => $newSupplier->id,
        ]);
        $responseNonAdmin->assertStatus(403);

        // 2. Admin update succeeds
        $responseAdmin = $this->actingAs($admin)->post(route('purchasing.invoices.change-supplier', $invoice), [
            'supplier_id' => $newSupplier->id,
        ]);

        $responseAdmin->assertRedirect();

        $invoice->refresh();
        $cart->refresh();
        $grn->refresh();

        $this->assertEquals($newSupplier->id, $invoice->supplier_id);
        $this->assertEquals($newSupplier->id, $cart->supplier_id);
        $this->assertEquals($newSupplier->id, $grn->supplier_id);
    }
}
