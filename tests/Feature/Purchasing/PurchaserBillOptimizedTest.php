<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaserBillOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product1;

    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $wh = Warehouse::create(['name' => 'Main WH', 'code' => 'WH-M', 'is_active' => true]);
        $cat = Category::create(['name' => 'Vegetables', 'is_active' => true]);

        $this->supplier = Supplier::create([
            'name' => 'Farm Fresh Vendor',
            'type' => 'farmer',
            'status' => 'active',
            'mobile_number' => '9876543210',
        ]);

        $this->product1 = Product::create([
            'category_id' => $cat->id,
            'name' => 'Tomato Local',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $wh->id,
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'category_id' => $cat->id,
            'name' => 'Potato Agra',
            'sku' => 'POT-01',
            'unit' => 'kg',
            'default_warehouse_id' => $wh->id,
            'is_active' => true,
        ]);
    }

    public function test_purchaser_bill_page_loads_with_minimal_queries_and_no_historical_waste(): void
    {
        // Create 5 historical overdue carts to test isolation
        for ($i = 0; $i < 5; $i++) {
            PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'cart_number' => "VC-HIST-{$i}",
                'status' => 'draft',
                'business_date' => now()->subDays(5 + $i)->toDateString(),
                'purchase_grade' => 'A',
                'total_amount' => 500,
            ]);
        }

        // Create target current cart
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'cart_number' => 'VC-20260922-B206',
            'status' => 'draft',
            'business_date' => now()->toDateString(),
            'purchase_grade' => 'A',
            'total_amount' => 1500,
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 10,
            'unit_price' => 100,
            'line_total' => 1000,
            'grade' => 'A',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product2->id,
            'quantity' => 10,
            'unit_price' => 50,
            'line_total' => 500,
            'grade' => 'A',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.bill', ['cart' => $cart, 'date' => now()->toDateString()]));

        $queries = DB::getQueryLog();

        $response->assertOk()
            ->assertSee('VC-20260922-B206')
            ->assertSee('Farm Fresh Vendor')
            ->assertSee('Tomato Local')
            ->assertSee('Potato Agra')
            ->assertSee('1,500')
            ->assertSee('bill-vendor-picker')
            ->assertSee('bill-remove-'.$cart->items()->firstOrFail()->id)
            ->assertSee('name="items['.$cart->items()->firstOrFail()->id.'][quantity]"', false);

        // Check query count is capped and flat
        $this->assertLessThanOrEqual(20, count($queries), 'Query count must remain under 20 queries');

        // Ensure zero settlement allocation queries
        $settlementQueries = array_filter($queries, fn ($q) => str_contains((string) ($q['query'] ?? ''), 'vendor_settlement_allocations'));
        $this->assertEmpty($settlementQueries, 'Must execute 0 vendor_settlement_allocations queries');
    }
}
