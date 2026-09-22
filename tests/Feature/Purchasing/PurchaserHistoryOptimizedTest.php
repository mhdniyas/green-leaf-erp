<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
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

class PurchaserHistoryOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

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
            'credit_approved' => true,
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'name' => 'Tomato Premium',
            'unit' => 'kg',
            'category_id' => $category->id,
            'vendor_price' => 50.00,
            'show_in_purchaser_order' => true,
        ]);
    }

    public function test_history_initial_load_renders_successfully(): void
    {
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-20260922-001',
            'status' => 'submitted',
            'discount_amount' => 10.00,
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 50,
            'line_total' => 1000,
        ]);

        $grn = GoodsReceived::create([
            'purchaser_cart_id' => $cart->id,
            'grn_number' => 'GRN-20260922-001',
            'status' => 'received',
            'received_by' => $this->purchaser->id,
            'received_at' => $this->date,
        ]);

        $invoice = PurchaseInvoice::create([
            'purchaser_cart_id' => $cart->id,
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-20260922-001',
            'invoice_date' => $this->date,
            'amount' => 1000.00,
            'discount_amount' => 10.00,
            'paid_amount' => 990.00,
            'payment_status' => 'paid',
            'payment_method' => 'Cash',
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.history', ['date' => $this->date]));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser.history');
        $response->assertSee('CART-20260922-001');
        $response->assertSee('Al-Mabrook Traders');
        $response->assertSee('INV-20260922-001');
    }

    public function test_history_tabs_and_pagination(): void
    {
        $yesterday = $this->today->copy()->subDay()->format('Y-m-d');
        for ($i = 1; $i <= 30; $i++) {
            $cart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => $yesterday,
                'purchase_grade' => 'A',
                'cart_number' => sprintf('CART-HIST-%03d', $i),
                'status' => 'submitted',
                'discount_amount' => 0.00,
            ]);

            PurchaserCartItem::create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]);

            $grn = GoodsReceived::create([
                'purchaser_cart_id' => $cart->id,
                'grn_number' => sprintf('GRN-HIST-%03d', $i),
                'status' => 'received',
                'received_by' => $this->purchaser->id,
                'received_at' => $yesterday,
            ]);

            $invoice = PurchaseInvoice::create([
                'purchaser_cart_id' => $cart->id,
                'goods_received_id' => $grn->id,
                'supplier_id' => $this->supplier->id,
                'invoice_number' => sprintf('INV-HIST-%03d', $i),
                'invoice_date' => $yesterday,
                'amount' => 10.00,
                'discount_amount' => 0.00,
                'paid_amount' => 10.00,
                'payment_status' => 'paid',
                'payment_method' => 'Cash',
            ]);
            $cart->update(['purchase_invoice_id' => $invoice->id]);
        }

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.history', ['date' => $this->date, 'tab' => 'history']));

        $response->assertOk();
        $response->assertViewHas('tabCounts', fn (array $counts) => ($counts['history'] ?? 0) === 30);
        $response->assertViewHas('paginatedCarts', fn ($paginated) => $paginated->count() === 25 && $paginated->total() === 30);
    }

    public function test_history_cart_details_endpoint_returns_json(): void
    {
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-DETAIL-001',
            'status' => 'submitted',
            'discount_amount' => 5.00,
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 45,
            'line_total' => 450,
        ]);

        $grn = GoodsReceived::create([
            'purchaser_cart_id' => $cart->id,
            'grn_number' => 'GRN-DETAIL-001',
            'status' => 'received',
            'received_by' => $this->purchaser->id,
            'received_at' => $this->date,
        ]);

        $invoice = PurchaseInvoice::create([
            'purchaser_cart_id' => $cart->id,
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-DETAIL-001',
            'invoice_date' => $this->date,
            'amount' => 450.00,
            'discount_amount' => 5.00,
            'paid_amount' => 445.00,
            'payment_status' => 'paid',
            'payment_method' => 'Online',
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $response = $this->actingAs($this->purchaser)
            ->getJson(route('purchaser.history.details', ['cart' => $cart->id]));

        $response->assertOk();
        $response->assertJsonStructure([
            'invoiceData' => [
                'id',
                'number',
                'supplier',
                'amount',
                'paidAmount',
                'paymentMethod',
            ],
            'modalPayload' => [
                'supplierName',
                'billRef',
                'invoiceNumber',
                'items',
            ],
            'paymentActionUrl',
        ]);
        $this->assertSame('INV-DETAIL-001', $response->json('invoiceData.number'));
        $this->assertSame('Al-Mabrook Traders', $response->json('modalPayload.supplierName'));
    }

    public function test_history_cart_details_endpoint_forbids_other_users(): void
    {
        $otherPurchaser = User::factory()->create(['name' => 'Other Purchaser']);
        $otherPurchaser->assignRole('purchaser');

        $cart = PurchaserCart::create([
            'user_id' => $otherPurchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $this->date,
            'purchase_grade' => 'A',
            'cart_number' => 'CART-OTHER-001',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->getJson(route('purchaser.history.details', ['cart' => $cart->id]));

        $response->assertForbidden();
    }

    public function test_history_initial_load_query_count_is_bounded(): void
    {
        $yesterday = $this->today->copy()->subDay()->format('Y-m-d');
        for ($i = 1; $i <= 50; $i++) {
            $cart = PurchaserCart::create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => $yesterday,
                'purchase_grade' => 'A',
                'cart_number' => sprintf('CART-QUERY-%03d', $i),
                'status' => 'submitted',
                'discount_amount' => 0.00,
            ]);

            PurchaserCartItem::create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]);

            $grn = GoodsReceived::create([
                'purchaser_cart_id' => $cart->id,
                'grn_number' => sprintf('GRN-QUERY-%03d', $i),
                'status' => 'received',
                'received_by' => $this->purchaser->id,
                'received_at' => $yesterday,
            ]);

            $invoice = PurchaseInvoice::create([
                'purchaser_cart_id' => $cart->id,
                'goods_received_id' => $grn->id,
                'supplier_id' => $this->supplier->id,
                'invoice_number' => sprintf('INV-QUERY-%03d', $i),
                'invoice_date' => $yesterday,
                'amount' => 10.00,
                'discount_amount' => 0.00,
                'paid_amount' => 10.00,
                'payment_status' => 'paid',
                'payment_method' => 'Cash',
            ]);
            $cart->update(['purchase_invoice_id' => $invoice->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.history', ['date' => $this->date, 'tab' => 'history']));

        $response->assertOk();
        $queries = DB::getQueryLog();

        // Must be less than 20 queries, regardless of 50+ carts in database
        $this->assertLessThan(20, count($queries));
    }
}
