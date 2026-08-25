<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyPurchaserPriceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyPurchaserPriceSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Supplier $vendor;

    private DailyPurchaserPriceSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        $this->purchaser = User::factory()->create();
        $this->vendor = Supplier::factory()->create();
        $this->syncService = app(DailyPurchaserPriceSyncService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_single_purchase_updates_snapshot_purchaser_price_without_changing_selling_prices(): void
    {
        $product = $this->createProduct('Tomato', 'VEG-WH', 40.0);

        // Pre-existing approval with selling prices
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-08-25',
            'purchase_price' => 30.0,
            'price_unit' => 'kg',
            'price_a' => 45.0,
            'price_b' => 42.0,
            'price_c' => 40.0,
            'status' => 'approved',
        ]);

        // Record actual purchase on 2026-08-25: 10 kg @ ₹35.00
        $this->recordPurchase($product, '2026-08-25', 10.0, 35.0);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-08-25')
            ->first();

        $this->assertNotNull($approval);
        $this->assertSame(35.0, (float) $approval->purchase_price);
        $this->assertSame(45.0, (float) $approval->price_a);
        $this->assertSame(42.0, (float) $approval->price_b);
        $this->assertSame(40.0, (float) $approval->price_c);
    }

    public function test_multiple_purchases_produce_weighted_average(): void
    {
        $product = $this->createProduct('Onion', 'VEG-WH', 50.0);

        // Purchase 1: 10 kg @ ₹30.00 = ₹300
        $this->recordPurchase($product, '2026-08-25', 10.0, 30.0);

        // Purchase 2: 30 kg @ ₹40.00 = ₹1200
        // Total: 40 kg, ₹1500 => Weighted Avg: ₹37.50
        $this->recordPurchase($product, '2026-08-25', 30.0, 40.0);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-08-25')
            ->first();

        $this->assertNotNull($approval);
        $this->assertSame(37.5, (float) $approval->purchase_price);
    }

    public function test_no_purchase_carries_previous_price(): void
    {
        $product = $this->createProduct('Carrot', 'VEG-WH', 60.0);

        // Day 1 (Aug 24): purchase @ ₹50.00
        $this->recordPurchase($product, '2026-08-24', 10.0, 50.0);

        // Day 2 (Aug 25): seeded from Day 1 without any new purchases
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-08-25',
            'purchase_price' => 50.0,
            'price_unit' => 'kg',
            'price_a' => 65.0,
            'price_b' => 60.0,
            'price_c' => 58.0,
            'status' => 'approved',
        ]);

        // Sync Aug 25
        $this->syncService->syncForBusinessDate('2026-08-25');

        $approval25 = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-08-25')
            ->first();

        $this->assertNotNull($approval25);
        $this->assertSame(50.0, (float) $approval25->purchase_price);
        $this->assertSame(65.0, (float) $approval25->price_a);
    }

    public function test_editing_purchase_recalculates_price(): void
    {
        $product = $this->createProduct('Beans', 'VEG-WH', 70.0);

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->vendor->id,
            'business_date' => '2026-08-25',
            'status' => 'submitted',
            'cart_number' => 'VC-TEST-1',
            'payment_method' => 'Cash',
        ]);
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $this->vendor->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-TEST-1',
            'amount' => 600.0,
            'discount_amount' => 0,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'payment_paid_by' => 'purchaser',
            'paid_amount' => 600.0,
        ]);

        $item = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'unit_price' => 60.0,
            'line_total' => 600.0,
        ]);

        $approval = DailyPriceApproval::query()
            ->where('product_id', $product->id)
            ->whereDate('business_date', '2026-08-25')
            ->first();
        $this->assertSame(60.0, (float) $approval->purchase_price);

        // Edit item to unit price ₹65.00
        $item->update([
            'unit_price' => 65.0,
            'line_total' => 650.0,
        ]);

        $approval->refresh();
        $this->assertSame(65.0, (float) $approval->purchase_price);
    }

    public function test_purchaser_price_report_shows_actual_calculated_change(): void
    {
        $product = $this->createProduct('Broad Beans', 'VEG-WH', 80.0);

        // Aug 24: purchase @ ₹70.00
        $this->recordPurchase($product, '2026-08-24', 14.5, 70.0);

        // Aug 25: purchase @ ₹60.00
        $this->recordPurchase($product, '2026-08-25', 20.5, 60.0);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.purchaser-prices', [
            'date_a' => '2026-08-24',
            'date_b' => '2026-08-25',
        ]));

        $response->assertOk()
            ->assertSee('Broad Beans')
            ->assertSee('₹70.00')
            ->assertSee('₹60.00')
            ->assertSee('₹-10.00')
            ->assertSee('-14.29%');
    }

    private function createProduct(string $name, string $warehouseCode, float $basePrice): Product
    {
        $warehouse = Warehouse::query()->firstOrCreate(['code' => $warehouseCode], ['name' => $warehouseCode, 'is_active' => true]);
        $category = Category::query()->firstOrCreate(['name' => $name.' Category'], ['is_active' => true]);

        return Product::factory()->create([
            'name' => $name,
            'category_id' => $category->id,
            'default_warehouse_id' => $warehouse->id,
            'unit' => 'kg',
            'base_price' => $basePrice,
        ]);
    }

    private function recordPurchase(Product $product, string $date, float $quantity, float $unitPrice): PurchaseInvoice
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->vendor->id,
            'business_date' => $date,
            'status' => 'submitted',
            'cart_number' => 'VC-'.str()->upper(str()->random(10)),
            'payment_method' => 'Cash',
        ]);
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $this->vendor->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PUR-'.str()->upper(str()->random(10)),
            'amount' => $quantity * $unitPrice,
            'discount_amount' => 0,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'payment_paid_by' => 'purchaser',
            'paid_amount' => $quantity * $unitPrice,
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);

        return $invoice;
    }
}
