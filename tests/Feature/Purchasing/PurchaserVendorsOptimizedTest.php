<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserVendorsOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product1;

    private Product $product2;

    private Carbon $today;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->purchaser = User::factory()->create(['name' => 'John Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->today = app(PurchaserBusinessDayService::class)->operationalDate();
        $this->date = $this->today->format('Y-m-d');

        $this->supplier = Supplier::factory()->create([
            'name' => 'Al-Mabrook Traders',
            'mobile_number' => '9876543210',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product1 = Product::factory()->create([
            'name' => 'Tomato Premium',
            'unit' => 'kg',
            'category_id' => $category->id,
            'vendor_price' => 50.00,
            'show_in_purchaser_order' => true,
        ]);
        $this->product2 = Product::factory()->create([
            'name' => 'Potato Standard',
            'unit' => 'kg',
            'category_id' => $category->id,
            'vendor_price' => 30.00,
            'show_in_purchaser_order' => true,
        ]);
    }

    public function test_vendors_initial_load_renders_successfully_with_draft_carts(): void
    {
        $draftCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-20260922-001',
            'status' => 'draft',
            'discount_amount' => 10.00,
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $draftCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 20,
            'unit_price' => 50,
            'line_total' => 1000,
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $draftCart->id,
            'product_id' => $this->product2->id,
            'quantity' => 10,
            'unit_price' => 30,
            'line_total' => 300,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors', ['date' => $this->date]));

        $response->assertOk();
        $response->assertSee('CART-20260922-001');
        $response->assertSee('Al-Mabrook Traders');
        $response->assertSee('Tomato Premium');
        $response->assertSee('Potato Standard');
        $response->assertSee('1,290.00'); // (1000 + 300) - 10
        $response->assertSee('Save & Process', false);
    }

    public function test_vendors_initial_load_has_minimal_query_count_and_no_deadline_alert_overkill(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $cart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => $this->date,
                'purchase_grade' => 'A',
                'cart_number' => "CART-20260922-00{$i}",
                'status' => 'draft',
            ]);

            PurchaserCartItem::create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $this->product1->id,
                'quantity' => 10 * $i,
                'unit_price' => 50,
                'line_total' => 500 * $i,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors', ['date' => $this->date]));

        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $this->assertLessThanOrEqual(20, $queryCount, "Query count was {$queryCount}, expected <= 20");

        foreach ($queries as $q) {
            $this->assertStringNotContainsString('vendor_settlement_allocations', $q['query']);
            $this->assertStringNotContainsString('notes LIKE', $q['query']);
        }
    }

    public function test_pending_tab_lazy_loads_successfully(): void
    {
        $submittedCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-20260922-SUB01',
            'status' => 'submitted',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 15,
            'unit_price' => 50,
            'line_total' => 750,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors.tabs.pending', ['date' => $this->date]));

        $response->assertOk();
        $response->assertSee('CART-20260922-SUB01');
        $response->assertSee('Al-Mabrook Traders');
        $response->assertSee('Tomato Premium');
    }

    public function test_completed_tab_lazy_loads_successfully(): void
    {
        $completedCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-20260922-COMP01',
            'status' => 'submitted',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $completedCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 15,
            'unit_price' => 50,
            'line_total' => 750,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors.tabs.completed', ['date' => $this->date]));

        $response->assertOk();
    }

    public function test_cancelled_tab_lazy_loads_successfully(): void
    {
        $cancelledCart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-20260922-CANC01',
            'status' => 'cancelled',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cancelledCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 5,
            'unit_price' => 50,
            'line_total' => 250,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors.tabs.cancelled', ['date' => $this->date]));

        $response->assertOk();
        $response->assertSee('CART-20260922-CANC01');
    }
}
