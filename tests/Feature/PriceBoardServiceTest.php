<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\POStatus;
use App\Models\BusinessSetting;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\PriceBoardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceBoardServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_per_unit_purchase_order_items_use_packet_quantity_for_daily_purchase_price(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Container 500 G',
            'unit' => 'pcs',
            'base_price' => 18,
        ]);

        $purchaseOrder = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-PER-UNIT-TEST',
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => '2026-07-23',
            'created_by' => $user->id,
        ]);

        $purchaseOrderItem = $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'purchase_unit' => 'pcs',
            'packet_qty' => 6,
            'quantity' => 6,
            'unit_price' => 55,
            'price_basis' => 'per_unit',
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'grn_number' => 'GRN-PER-UNIT-TEST',
            'status' => 'approved',
            'received_by' => $user->id,
            'received_at' => '2026-07-23',
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'received_qty' => 6,
            'variance' => 0,
        ]);

        app(PriceBoardService::class)->ensurePendingApprovalsForPurchaseDate('2026-07-23');

        $approval = DailyPriceApproval::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame('2026-07-23', $approval->business_date->toDateString());
        $this->assertSame('55.0000', $approval->purchase_price);
        $this->assertSame('pending', $approval->status);
    }

    public function test_same_purchase_price_is_auto_approved_by_default_and_keeps_previous_selling_prices(): void
    {
        $product = Product::factory()->create([
            'name' => 'Same Price Tomato',
            'base_price' => 50,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-07-25',
            'purchase_price' => 50,
            'price_a' => 61,
            'price_b' => 62,
            'price_c' => 63,
            'status' => 'approved',
            'approved_at' => '2026-07-25 08:00:00',
        ]);

        $this->createReceivedProduct($product, '2026-07-26', 50);

        $approvals = app(PriceBoardService::class)->ensurePendingApprovalsForPurchaseDate('2026-07-26');
        $approval = $approvals->firstWhere('product_id', $product->id);

        $this->assertNotNull($approval);
        $this->assertSame('approved', $approval->status);
        $this->assertNotNull($approval->approved_at);
        $this->assertSame('same', $approval->movement_status);
        $this->assertSame(50.0, $approval->comparison_purchase_price);
        $this->assertSame('61.00', $approval->price_a);
        $this->assertSame('62.00', $approval->price_b);
        $this->assertSame('63.00', $approval->price_c);
    }

    public function test_same_purchase_price_waits_for_admin_when_auto_approval_is_disabled(): void
    {
        BusinessSetting::query()->create([
            'key' => 'auto_approve_same_daily_purchase_price',
            'value' => '0',
        ]);

        $product = Product::factory()->create(['base_price' => 50]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-07-25',
            'purchase_price' => 50,
            'price_a' => 61,
            'price_b' => 62,
            'price_c' => 63,
            'status' => 'approved',
            'approved_at' => '2026-07-25 08:00:00',
        ]);

        $this->createReceivedProduct($product, '2026-07-26', 50);

        app(PriceBoardService::class)->ensurePendingApprovalsForPurchaseDate('2026-07-26');

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-07-26')
            ->firstOrFail();

        $this->assertSame('pending', $approval->status);
        $this->assertNull($approval->approved_at);
    }

    public function test_price_board_defaults_to_changed_products_and_filters_up_or_down_movements(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $sameProduct = Product::factory()->create(['name' => 'Same Board Product', 'base_price' => 50]);
        $upProduct = Product::factory()->create(['name' => 'Up Board Product', 'base_price' => 50]);
        $downProduct = Product::factory()->create(['name' => 'Down Board Product', 'base_price' => 50]);

        foreach ([$sameProduct, $upProduct, $downProduct] as $product) {
            DailyPriceApproval::query()->create([
                'product_id' => $product->id,
                'business_date' => '2026-07-25',
                'purchase_price' => 50,
                'price_a' => 61,
                'price_b' => 62,
                'price_c' => 63,
                'status' => 'approved',
                'approved_at' => '2026-07-25 08:00:00',
            ]);
        }

        $this->createReceivedProduct($sameProduct, '2026-07-26', 50);
        $this->createReceivedProduct($upProduct, '2026-07-26', 60);
        $this->createReceivedProduct($downProduct, '2026-07-26', 40);

        $this
            ->actingAs($admin)
            ->get(route('purchasing.prices.index', ['date' => '2026-07-26']))
            ->assertOk()
            ->assertSee('Up Board Product')
            ->assertSee('Down Board Product')
            ->assertSee('+ INR 10.00')
            ->assertSee('- INR 10.00')
            ->assertDontSee('Same Board Product');

        $this
            ->actingAs($admin)
            ->get(route('purchasing.prices.index', ['date' => '2026-07-26', 'movement' => 'up']))
            ->assertOk()
            ->assertSee('Up Board Product')
            ->assertDontSee('Down Board Product')
            ->assertDontSee('Same Board Product');

        $this
            ->actingAs($admin)
            ->get(route('purchasing.prices.index', ['date' => '2026-07-26', 'movement' => 'down']))
            ->assertOk()
            ->assertSee('Down Board Product')
            ->assertDontSee('Up Board Product')
            ->assertDontSee('Same Board Product');
    }

    public function test_price_board_shows_movement_amount_against_product_base_price_when_no_previous_daily_price_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $product = Product::factory()->create([
            'name' => 'First Price Product',
            'base_price' => 40,
        ]);

        $this->createReceivedProduct($product, '2026-07-26', 55);

        $this
            ->actingAs($admin)
            ->get(route('purchasing.prices.index', ['date' => '2026-07-26']))
            ->assertOk()
            ->assertSee('First Price Product')
            ->assertSee('+ INR 15.00')
            ->assertSee('Prev INR 40.00');
    }

    private function createReceivedProduct(Product $product, string $date, float $unitPrice): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();

        $purchaseOrder = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-PRICE-'.strtoupper(fake()->bothify('????-####')),
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => $date,
            'created_by' => $user->id,
        ]);

        $purchaseOrderItem = $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'quantity' => 10,
            'unit_price' => $unitPrice,
            'price_basis' => 'per_kg',
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'grn_number' => 'GRN-PRICE-'.strtoupper(fake()->bothify('????-####')),
            'status' => 'approved',
            'received_by' => $user->id,
            'received_at' => $date,
        ]);

        $grn->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'received_qty' => 10,
            'variance' => 0,
        ]);
    }
}
