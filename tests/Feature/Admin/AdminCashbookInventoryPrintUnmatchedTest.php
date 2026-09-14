<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCashbookInventoryPrintUnmatchedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Product $productFullyMatched;

    private Product $productPartialMatched;

    private Product $productUnitMismatch;

    private Product $productAdvanceOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse1 = Warehouse::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'WH1',
            'is_active' => true,
        ]);

        $this->warehouse2 = Warehouse::factory()->create([
            'name' => 'Secondary Warehouse',
            'code' => 'WH2',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->productFullyMatched = Product::create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'name' => 'Fully Matched Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->productPartialMatched = Product::create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'name' => 'Partial Potato',
            'sku' => 'POT-002',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->productUnitMismatch = Product::create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'name' => 'Mismatch Onion',
            'sku' => 'ONI-003',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->productAdvanceOnly = Product::create([
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'name' => 'Advance Only Garlic',
            'sku' => 'GAR-004',
            'unit' => 'kg',
            'is_active' => true,
        ]);
    }

    public function test_print_unmatched_requires_admin_authorization(): void
    {
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        $response = $this->actingAs($shopUser)
            ->getJson(route('admin.cashbook.inventory.print-unmatched', ['date' => '2026-09-14']));

        $response->assertForbidden();
    }

    public function test_print_unmatched_shows_only_non_100_percent_matched_items(): void
    {
        $date = '2026-09-14';

        // 1. Fully Matched Product: Advance 100 kg, Bill 100 kg, Match 100 kg
        $advGrn1 = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        $advItem1 = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn1->id,
            'product_id' => $this->productFullyMatched->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);

        $billGrn1 = GoodsReceived::factory()->create([
            'receipt_type' => 'purchase_order',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        $billItem1 = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn1->id,
            'product_id' => $this->productFullyMatched->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn1->id,
            'bill_goods_received_id' => $billGrn1->id,
            'advance_goods_received_item_id' => $advItem1->id,
            'bill_goods_received_item_id' => $billItem1->id,
            'product_id' => $this->productFullyMatched->id,
            'matched_qty' => 100,
            'matched_unit' => 'kg',
            'base_qty' => 100,
            'conversion_to_base' => 1.0,
            'confirmed_at' => now(),
        ]);

        // 2. Partial Matched Product: Advance 100 kg, Bill 100 kg, Match only 60 kg (40 kg pending)
        $advGrn2 = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        $advItem2 = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn2->id,
            'product_id' => $this->productPartialMatched->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);

        $billGrn2 = GoodsReceived::factory()->create([
            'receipt_type' => 'purchase_order',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        $billItem2 = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn2->id,
            'product_id' => $this->productPartialMatched->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn2->id,
            'bill_goods_received_id' => $billGrn2->id,
            'advance_goods_received_item_id' => $advItem2->id,
            'bill_goods_received_item_id' => $billItem2->id,
            'product_id' => $this->productPartialMatched->id,
            'matched_qty' => 60,
            'matched_unit' => 'kg',
            'base_qty' => 60,
            'conversion_to_base' => 1.0,
            'confirmed_at' => now(),
        ]);

        // 3. Unit Mismatch Product: Advance 10 box, Bill 50 kg
        $advGrn3 = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn3->id,
            'product_id' => $this->productUnitMismatch->id,
            'received_qty' => 10,
            'received_unit' => 'box',
        ]);

        $billGrn3 = GoodsReceived::factory()->create([
            'receipt_type' => 'purchase_order',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn3->id,
            'product_id' => $this->productUnitMismatch->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
        ]);

        // 4. Advance Only Product: Advance 30 kg, No bill
        $advGrn4 = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'received',
            'received_at' => $date,
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn4->id,
            'product_id' => $this->productAdvanceOnly->id,
            'received_qty' => 30,
            'received_unit' => 'kg',
        ]);

        // Call the print unmatched route
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.inventory.print-unmatched', [
                'date' => $date,
                'warehouse_id' => $this->warehouse1->id,
            ]));

        $response->assertOk();

        // Must see non-100% matched products
        $response->assertSee('Partial Potato');
        $response->assertSee('Mismatch Onion');
        $response->assertSee('Advance Only Garlic');

        // Must NOT see the 100% fully matched product
        $response->assertDontSee('Fully Matched Tomato');

        // Check rows passed to view
        $rows = $response->viewData('rows');
        $this->assertCount(3, $rows);

        $productNames = collect($rows)->pluck('product_name')->all();
        $this->assertContains('Partial Potato', $productNames);
        $this->assertContains('Mismatch Onion', $productNames);
        $this->assertContains('Advance Only Garlic', $productNames);
        $this->assertNotContains('Fully Matched Tomato', $productNames);

        // Verify summary
        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['total_items']);
        $this->assertSame(1, $summary['unit_fix_count']);
    }

    public function test_inventory_page_contains_print_unmatched_link_and_filter_pills(): void
    {
        $date = '2026-09-14';

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.inventory', [
                'date' => $date,
                'warehouse_id' => $this->warehouse1->id,
            ]));

        $response->assertOk();
        $response->assertSee(route('admin.cashbook.inventory.print-unmatched', [
            'date' => $date,
            'warehouse_id' => $this->warehouse1->id,
        ]));
        $response->assertSee('Print (< 100% Match)', false);
    }
}
