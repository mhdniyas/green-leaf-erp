<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\PurchaseGradePrice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that the purchaser-entered unit_price wins over PurchaseGradePriceResolver output
 * during POST /purchaser/carts/bulk-store.
 *
 * The resolver must remain as the fallback when no positive price is submitted.
 */
class BulkStoreCartPricePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Warehouse $warehouse;

    private Category $category;

    private string $businessDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-TEST',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
    }

    /** Shared: create a product with an approved Grade A price (₹28 – the "previous/approved" hint). */
    private function makeProductWithApprovedPrice(float $approvedPrice = 28.0): Product
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Carrot',
            'sku' => 'CAR-TST-'.uniqid(),
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'purchase_price' => $approvedPrice,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        return $product;
    }

    // ------------------------------------------------------------------
    // Core fix: new cart item
    // ------------------------------------------------------------------

    /**
     * @test
     *
     * Purchaser enters ₹63 while approved/previous Grade A price is ₹28.
     * After bulk-store the DB must persist ₹63, not ₹28.
     */
    public function test_submitted_price_wins_over_approved_grade_a_price_for_new_item(): void
    {
        $product = $this->makeProductWithApprovedPrice(28.0);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'A',
            'product_ids' => [$product->id],
            'items' => [
                $product->id => ['quantity' => 10, 'unit_price' => 63],
            ],
        ]);

        $response->assertRedirect();

        $item = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->firstOrFail();

        $this->assertSame(63.0, (float) $item->unit_price, 'unit_price must be the purchaser-entered ₹63');
        $this->assertSame(10.0, (float) $item->quantity);
        $this->assertSame(630.0, (float) $item->line_total, 'line_total must be 10 × 63 = 630');
    }

    // ------------------------------------------------------------------
    // Core fix: existing draft cart item price override
    // ------------------------------------------------------------------

    /**
     * @test
     *
     * Purchaser previously stored ₹28 in a draft item, then re-opens Bulk Buy
     * and enters ₹63. The draft item's unit_price must be updated to ₹63.
     */
    public function test_submitted_price_overwrites_existing_draft_cart_item_price(): void
    {
        $product = $this->makeProductWithApprovedPrice(28.0);

        // Existing draft cart with ₹28
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->businessDate,
            'cart_number' => PurchaserCart::generateCartNumber(now()),
            'status' => 'draft',
            'purchase_grade' => 'A',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 5,
            'unit_price' => 28.0,
            'line_total' => 140.0,
        ]);

        // Purchaser re-submits with ₹63 and qty 10
        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'A',
            'cart_id' => $cart->id,
            'product_ids' => [$product->id],
            'items' => [
                $product->id => ['quantity' => 10, 'unit_price' => 63],
            ],
        ]);

        $response->assertRedirect();

        $item = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->firstOrFail();

        $this->assertSame(63.0, (float) $item->unit_price, 'draft item must be updated to ₹63');
        // bulkStoreCart accumulates quantity (5 + 10 = 15)
        $this->assertSame(15.0, (float) $item->quantity);
        $this->assertSame(round(15 * 63, 2), (float) $item->line_total, 'line_total must be 15 × 63');
    }

    // ------------------------------------------------------------------
    // Decimal price
    // ------------------------------------------------------------------

    /**
     * @test
     *
     * Purchaser enters ₹63.50 (decimal). Must be stored exactly.
     */
    public function test_decimal_submitted_price_is_persisted_exactly(): void
    {
        $product = $this->makeProductWithApprovedPrice(28.0);

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'A',
            'product_ids' => [$product->id],
            'items' => [
                $product->id => ['quantity' => 5, 'unit_price' => 63.50],
            ],
        ]);

        $item = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->firstOrFail();

        $this->assertSame(63.50, (float) $item->unit_price);
        $this->assertSame(round(5 * 63.50, 2), (float) $item->line_total);
    }

    /**
     * @test
     *
     * Purchaser enters ₹40. Must be stored as 40, not the approved ₹28.
     */
    public function test_price_40_is_persisted_not_approved_price(): void
    {
        $product = $this->makeProductWithApprovedPrice(28.0);

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'A',
            'product_ids' => [$product->id],
            'items' => [
                $product->id => ['quantity' => 3, 'unit_price' => 40],
            ],
        ]);

        $item = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'A')
            ->firstOrFail();

        $this->assertSame(40.0, (float) $item->unit_price);
        $this->assertSame(120.0, (float) $item->line_total);
    }

    // ------------------------------------------------------------------
    // Blank / zero price is rejected for Grade A
    // ------------------------------------------------------------------

    /**
     * @test
     *
     * Grade A: missing or zero price must be rejected with a validation error.
     * No cart item should be created.
     */
    public function test_zero_price_is_rejected_for_grade_a(): void
    {
        $product = $this->makeProductWithApprovedPrice(28.0);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'A',
            'product_ids' => [$product->id],
            'items' => [
                $product->id => ['quantity' => 5, 'unit_price' => 0],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $this->assertDatabaseMissing('purchaser_cart_items', ['product_id' => $product->id]);
    }

    // ------------------------------------------------------------------
    // Grade B: resolver is the fallback (no explicit price submitted)
    // ------------------------------------------------------------------

    /**
     * @test
     *
     * Grade B: purchaser does not submit a price; the resolver must supply it
     * from an approved PurchaseGradePrice row.
     */
    public function test_grade_b_without_submitted_price_uses_resolver_fallback(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Grade B Product',
            'sku' => 'GRADB-TST-'.uniqid(),
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'show_in_purchaser_order' => true,
            'is_active' => true,
        ]);

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 18.0,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.bulk-store'), [
            'business_date' => $this->businessDate,
            'purchase_grade' => 'B',
            'product_ids' => [$product->id],
            'items' => [
                // No unit_price submitted for Grade B
                $product->id => ['quantity' => 8],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $item = PurchaserCartItem::query()
            ->where('product_id', $product->id)
            ->where('grade', 'B')
            ->firstOrFail();

        $this->assertSame(18.0, (float) $item->unit_price, 'resolver fallback price ₹18 must be used for Grade B');
        $this->assertSame(144.0, (float) $item->line_total);
    }
}
