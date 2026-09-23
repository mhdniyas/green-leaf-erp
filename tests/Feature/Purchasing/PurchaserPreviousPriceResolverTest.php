<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserPreviousPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserPreviousPriceResolverTest extends TestCase
{
    use RefreshDatabase;

    private PurchaserPreviousPriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PurchaserPreviousPriceResolver::class);
    }

    private function createCart(User $user, Supplier $supplier, string $date, string $status = 'submitted', string $grade = 'A'): PurchaserCart
    {
        return PurchaserCart::query()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'business_date' => $date,
            'status' => $status,
            'cart_number' => PurchaserCart::generateCartNumber($date),
            'purchase_grade' => $grade,
        ]);
    }

    private function createInvoice(PurchaserCart $cart, string $status = 'paid'): PurchaseInvoice
    {
        $grn = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $cart->supplier_id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'amount' => 100.0,
            'status' => $status,
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        return $invoice;
    }

    private function createItem(PurchaserCart $cart, Product $product, float $qty, float $price, string $grade = 'A'): PurchaserCartItem
    {
        return PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => $grade,
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => round($qty * $price, 2),
        ]);
    }

    public function test_calculates_quantity_weighted_average_for_multiple_prices_on_same_prior_day(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Tomato N']);
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();

        $cart1 = $this->createCart($user, $supplierA, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($cart1);
        $this->createItem($cart1, $product, 22.00, 22.00, 'A');

        $cart2 = $this->createCart($user, $supplierB, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($cart2);
        $this->createItem($cart2, $product, 122.00, 28.00, 'A');

        // Current date: 2026-09-23
        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        // Total Value = 484 + 3416 = 3900; Total Qty = 144. 3900 / 144 = 27.0833... -> 27.0833
        $this->assertEquals(27.0833, $prices[$product->id], '', 0.001);
    }

    public function test_skips_days_without_purchases_and_finds_most_recent_prior_purchase_date(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Onion']);
        $supplier = Supplier::factory()->create();

        // Purchase on 2026-09-20 (No purchase on 2026-09-21 or 2026-09-22)
        $cart = $this->createCart($user, $supplier, '2026-09-20', 'submitted', 'A');
        $this->createInvoice($cart);
        $this->createItem($cart, $product, 100.00, 35.00, 'A');

        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        $this->assertEquals(35.00, $prices[$product->id]);
    }

    public function test_current_day_purchase_does_not_affect_today_previous_price(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Carrot']);
        $supplier = Supplier::factory()->create();

        // Prior purchase on 2026-09-22: ₹40
        $priorCart = $this->createCart($user, $supplier, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($priorCart);
        $this->createItem($priorCart, $product, 50.00, 40.00, 'A');

        // Today purchase on 2026-09-23: ₹60
        $todayCart = $this->createCart($user, $supplier, '2026-09-23', 'submitted', 'A');
        $this->createInvoice($todayCart);
        $this->createItem($todayCart, $product, 100.00, 60.00, 'A');

        // Target date 2026-09-23: Previous price MUST be ₹40 (from 2026-09-22), NOT ₹60 or average with ₹60
        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        $this->assertEquals(40.00, $prices[$product->id]);
    }

    public function test_isolates_grade_a_and_grade_b_for_same_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Tomato']);
        $supplier = Supplier::factory()->create();

        // Grade A purchase on 2026-09-22: ₹50
        $cartA = $this->createCart($user, $supplier, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($cartA);
        $this->createItem($cartA, $product, 100.00, 50.00, 'A');

        // Grade B purchase on 2026-09-22: ₹20
        $cartB = $this->createCart($user, $supplier, '2026-09-22', 'submitted', 'B');
        $this->createInvoice($cartB);
        $this->createItem($cartB, $product, 100.00, 20.00, 'B');

        $pricesGradeA = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');
        $pricesGradeB = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'B');

        $this->assertEquals(50.00, $pricesGradeA[$product->id]);
        $this->assertEquals(20.00, $pricesGradeB[$product->id]);
    }

    public function test_excludes_draft_carts(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Potato']);
        $supplier = Supplier::factory()->create();

        // Draft cart on 2026-09-22 (No invoice, status = draft)
        $draftCart = $this->createCart($user, $supplier, '2026-09-22', 'draft', 'A');
        $this->createItem($draftCart, $product, 100.00, 99.00, 'A');

        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        $this->assertEquals(0.00, $prices[$product->id]);
    }

    public function test_excludes_cancelled_invoices(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Ginger']);
        $supplier = Supplier::factory()->create();

        $cart = $this->createCart($user, $supplier, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($cart, 'cancelled');
        $this->createItem($cart, $product, 100.00, 150.00, 'A');

        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        $this->assertEquals(0.00, $prices[$product->id]);
    }

    public function test_combines_multiple_suppliers_into_company_wide_weighted_average(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Garlic']);
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        // Supplier 1: 10 KG @ ₹100 = ₹1,000
        $cart1 = $this->createCart($user, $supplier1, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($cart1);
        $this->createItem($cart1, $product, 10.00, 100.00, 'A');

        // Supplier 2: 30 KG @ ₹140 = ₹4,200
        $cart2 = $this->createCart($user, $supplier2, '2026-09-22', 'submitted', 'A');
        $this->createInvoice($cart2);
        $this->createItem($cart2, $product, 30.00, 140.00, 'A');

        // Company Weighted Average: (1000 + 4200) / (10 + 30) = 5200 / 40 = ₹130
        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        $this->assertEquals(130.00, $prices[$product->id]);
    }

    public function test_returns_zero_for_product_with_no_prior_purchases(): void
    {
        $product = Product::factory()->create(['name' => 'Exotic Mushroom']);

        $prices = $this->resolver->getPreviousWeightedPrices([$product->id], '2026-09-23', 'A');

        $this->assertArrayHasKey($product->id, $prices);
        $this->assertEquals(0.00, $prices[$product->id]);
    }
}
