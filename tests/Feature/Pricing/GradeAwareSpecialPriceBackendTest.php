<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GradeAwareSpecialPriceBackendTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private ShopPriceGroup $priceGroupA;

    private Shop $shop1;

    private Shop $shop2;

    private Product $tomato;

    private Product $potato;

    private string $date = '2026-11-04';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00'));

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Jane']);
        $this->purchaser->assignRole('purchaser');

        $this->priceGroupA = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);

        $this->shop1 = Shop::factory()->create([
            'name' => 'Downtown Supermarket',
            'code' => 'SH-01',
            'shop_price_group_id' => $this->priceGroupA->id,
        ]);

        $this->shop2 = Shop::factory()->create([
            'name' => 'Uptown Grocery',
            'code' => 'SH-02',
            'shop_price_group_id' => $this->priceGroupA->id,
        ]);

        $category = Category::factory()->create(['name' => 'Fresh Veg']);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'VEG-TOM-01',
            'unit' => 'kg',
            'category_id' => $category->id,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Potato',
            'sku' => 'VEG-POT-01',
            'unit' => 'kg',
            'category_id' => $category->id,
        ]);

        // Standard approved daily prices on 2026-11-04:
        // Tomato Grade A = 50, Grade B = 35, Grade C = 25
        $this->createDailyApproval($this->tomato, 'A', 50.00, $this->date);
        $this->createDailyApproval($this->tomato, 'B', 35.00, $this->date);
        $this->createDailyApproval($this->tomato, 'C', 25.00, $this->date);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createDailyApproval(Product $product, string $grade, float $priceA, string $date): DailyPriceApproval
    {
        return DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => $date,
            'grade' => $grade,
            'price_a' => $priceA,
            'price_b' => $priceA,
            'price_c' => $priceA,
            'status' => 'approved',
            'price_unit' => 'kg',
            'approved_at' => now(),
            'approved_by' => $this->purchaser->id,
        ]);
    }

    public function test_grade_b_special_price_does_not_affect_grade_a_or_c_and_resolves_independently(): void
    {
        $resolver = app(ApprovedDailyPriceResolver::class);

        // Before: Grade A = 50, Grade B = 35, Grade C = 25
        $resA = $resolver->resolve($this->tomato, $this->shop1, $this->date, ProductGrade::GradeA);
        $resB = $resolver->resolve($this->tomato, $this->shop1, $this->date, ProductGrade::GradeB);
        $resC = $resolver->resolve($this->tomato, $this->shop1, $this->date, ProductGrade::GradeC);

        $this->assertSame(50.00, $resA['price']);
        $this->assertSame('normal', $resA['source']);
        $this->assertSame(35.00, $resB['price']);
        $this->assertSame('normal', $resB['source']);
        $this->assertSame(25.00, $resC['price']);
        $this->assertSame('normal', $resC['source']);

        // Set Special Price for Grade B = 30.00
        ShopDailyProductPrice::query()->create([
            'business_date' => $this->date,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        // After: Grade A must remain 50.00 (normal), Grade B becomes 30.00 (special), Grade C remains 25.00 (normal)
        $resAAfter = $resolver->resolve($this->tomato, $this->shop1, $this->date, ProductGrade::GradeA);
        $resBAfter = $resolver->resolve($this->tomato, $this->shop1, $this->date, ProductGrade::GradeB);
        $resCAfter = $resolver->resolve($this->tomato, $this->shop1, $this->date, ProductGrade::GradeC);

        $this->assertSame(50.00, $resAAfter['price']);
        $this->assertSame('normal', $resAAfter['source']);

        $this->assertSame(30.00, $resBAfter['price']);
        $this->assertSame('special', $resBAfter['source']);

        $this->assertSame(25.00, $resCAfter['price']);
        $this->assertSame('normal', $resCAfter['source']);
    }

    public function test_another_shop_and_another_date_remain_isolated(): void
    {
        $resolver = app(ApprovedDailyPriceResolver::class);

        // Set Special Price for Shop 1 Grade B = 30.00
        ShopDailyProductPrice::query()->create([
            'business_date' => $this->date,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        // Shop 2 on same date must still get normal price 35.00 for Grade B
        $resShop2B = $resolver->resolve($this->tomato, $this->shop2, $this->date, ProductGrade::GradeB);
        $this->assertSame(35.00, $resShop2B['price']);
        $this->assertSame('normal', $resShop2B['source']);

        // Tomorrow's date on Shop 1:
        $tomorrow = '2026-11-05';
        $this->createDailyApproval($this->tomato, 'B', 38.00, $tomorrow);
        $resTomorrowB = $resolver->resolve($this->tomato, $this->shop1, $tomorrow, ProductGrade::GradeB);
        $this->assertSame(38.00, $resTomorrowB['price']);
        $this->assertSame('normal', $resTomorrowB['source']);
    }

    public function test_mobile_api_update_bill_price_for_grade_b_line_reprices_only_grade_b(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        $this->assertSame(50.00, (float) $itemGradeA->unit_price);
        $this->assertSame(35.00, (float) $itemGradeB->unit_price);

        // Purchaser edits Grade B special price via Mobile API
        $response = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $itemGradeB->id,
                'item_id' => $itemGradeB->id,
                'order_number' => $invoice->invoice_number,
                'shop_id' => $this->shop1->id,
                'product_id' => $this->tomato->id,
                'special_price' => 30.00,
                'unit_price' => 30.00,
                'business_date' => $this->date,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'special_price' => 30.00,
                'product_grade' => 'B',
                'invoice_repriced' => true,
            ],
        ]);

        // Check DB persisted special price has grade B
        $this->assertDatabaseHas('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 30.00,
            'status' => 'approved',
        ]);

        // Grade A special price should NOT exist
        $this->assertDatabaseMissing('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
        ]);

        // Verify invoice lines after repricing: Grade A is STILL 50.00, Grade B is 30.00
        $freshItemA = $itemGradeA->fresh();
        $freshItemB = $itemGradeB->fresh();

        $this->assertSame(50.00, (float) $freshItemA->unit_price);
        $this->assertSame(30.00, (float) $freshItemB->unit_price);
    }

    public function test_mobile_api_rejects_mismatched_invoice_item_identities(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // 1. Pass item_id of Tomato Grade B, but product_id of Potato
        $response1 = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $itemGradeB->id,
                'item_id' => $itemGradeB->id,
                'order_number' => $invoice->invoice_number,
                'product_id' => $this->potato->id,
                'special_price' => 30.00,
            ]);

        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors('product_id');

        // 2. Pass item_id of Tomato Grade B, but explicit grade 'A'
        $response2 = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $itemGradeB->id,
                'item_id' => $itemGradeB->id,
                'order_number' => $invoice->invoice_number,
                'product_id' => $this->tomato->id,
                'product_grade' => 'A',
                'special_price' => 30.00,
            ]);

        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors('product_grade');
    }

    public function test_finalized_invoices_remain_locked_and_reject_special_price_updates(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // Lock/finalize the invoice
        $invoice->update(['status' => 'finalized']);

        // Mobile API attempt
        $response = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $itemGradeB->id,
                'item_id' => $itemGradeB->id,
                'order_number' => $invoice->invoice_number,
                'product_id' => $this->tomato->id,
                'special_price' => 28.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('invoice');

        // Verify no special price was written
        $this->assertDatabaseMissing('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'selling_price' => 28.00,
        ]);

        // Verify line prices remain unchanged
        $this->assertSame(35.00, (float) $itemGradeB->fresh()->unit_price);
    }

    public function test_bulk_invoice_prices_update_distinguishes_grades(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // Submit bulk prices via Web route: Grade A = 48.00, Grade B = 32.00
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.bill-prices.invoice-prices.update', $invoice), [
                'prices' => [
                    $itemGradeA->id => [
                        'item_id' => $itemGradeA->id,
                        'product_id' => $this->tomato->id,
                        'product_grade' => 'A',
                        'selling_price' => 48.00,
                        'price_unit' => 'kg',
                        'reason' => 'Grade A promo',
                    ],
                    $itemGradeB->id => [
                        'item_id' => $itemGradeB->id,
                        'product_id' => $this->tomato->id,
                        'product_grade' => 'B',
                        'selling_price' => 32.00,
                        'price_unit' => 'kg',
                        'reason' => 'Grade B standard promo',
                    ],
                ],
            ]);

        $response->assertRedirect();

        // Verify both special prices are persisted in DB with their distinct grades
        $this->assertDatabaseHas('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'selling_price' => 48.00,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 32.00,
            'status' => 'approved',
        ]);

        // Verify invoice was repriced accordingly
        $this->assertSame(48.00, (float) $itemGradeA->fresh()->unit_price);
        $this->assertSame(32.00, (float) $itemGradeB->fresh()->unit_price);
    }

    public function test_discount_from_total_distributes_with_correct_grades(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // Grade A subtotal: 10 kg * 50 = 500
        // Grade B subtotal: 10 kg * 35 = 350
        // Total selected: 850
        // Discount 85.00 (10%)
        // Grade A becomes 450 / 10 = 45.00
        // Grade B becomes 315 / 10 = 31.50
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.bill-prices.discount.apply', $invoice), [
                'selected_items' => [$itemGradeA->id, $itemGradeB->id],
                'discount_amount' => 85.00,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'selling_price' => 45.00,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 31.50,
            'status' => 'approved',
        ]);

        $this->assertSame(45.00, (float) $itemGradeA->fresh()->unit_price);
        $this->assertSame(31.50, (float) $itemGradeB->fresh()->unit_price);
    }

    public function test_copy_previous_day_preserves_grades(): void
    {
        $yesterday = '2026-11-03';

        // Yesterday has Grade A and Grade B approved prices
        ShopDailyProductPrice::query()->create([
            'business_date' => $yesterday,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'selling_price' => 47.00,
            'status' => 'approved',
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $yesterday,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 31.00,
            'status' => 'approved',
        ]);

        // Copy yesterday's prices to today ($this->date)
        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.bill-prices.copy-previous-day', ['date' => $this->date]));

        $response->assertRedirect();

        // Verify both grades were copied to today with draft status
        $todayPrices = ShopDailyProductPrice::query()
            ->whereDate('business_date', $this->date)
            ->where('shop_id', $this->shop1->id)
            ->where('product_id', $this->tomato->id)
            ->get();

        $this->assertCount(2, $todayPrices);

        $copiedA = $todayPrices->firstWhere('product_grade', 'A');
        $this->assertNotNull($copiedA);
        $this->assertSame(47.00, (float) $copiedA->selling_price);
        $this->assertSame('draft', $copiedA->status);

        $copiedB = $todayPrices->firstWhere('product_grade', 'B');
        $this->assertNotNull($copiedB);
        $this->assertSame(31.00, (float) $copiedB->selling_price);
        $this->assertSame('draft', $copiedB->status);
    }

    public function test_approve_and_destroy_special_price_preserves_grades_and_reprices(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // Create draft Grade B special price = 30.00
        $specialPriceB = ShopDailyProductPrice::query()->create([
            'business_date' => $this->date,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'selling_price' => 30.00,
            'status' => 'draft',
        ]);

        // Grade B is not approved yet, so invoice remains at normal 35.00
        $this->assertSame(35.00, (float) $itemGradeB->fresh()->unit_price);

        // Approve Grade B special price
        $response = $this->actingAs($this->purchaser)
            ->patch(route('purchaser.bill-prices.approve', $specialPriceB));

        $response->assertRedirect();
        $this->assertSame('approved', $specialPriceB->fresh()->status);

        // Invoice was repriced: Grade B becomes 30.00, Grade A remains 50.00
        $this->assertSame(30.00, (float) $itemGradeB->fresh()->unit_price);
        $this->assertSame(50.00, (float) $itemGradeA->fresh()->unit_price);

        // Delete Grade B special price
        $responseDel = $this->actingAs($this->purchaser)
            ->delete(route('purchaser.bill-prices.destroy', $specialPriceB));

        $responseDel->assertRedirect();
        $this->assertDatabaseMissing('shop_daily_product_prices', ['id' => $specialPriceB->id]);

        // Invoice was repriced back: Grade B reverts to 35.00, Grade A stays at 50.00
        $this->assertSame(35.00, (float) $itemGradeB->fresh()->unit_price);
        $this->assertSame(50.00, (float) $itemGradeA->fresh()->unit_price);
    }

    private function createOrderAndInvoiceWithTwoGrades(): array
    {
        $order = ShopOrder::factory()->create([
            'order_number' => 'SO-20261104-001',
            'shop_id' => $this->shop1->id,
            'business_date' => $this->date,
        ]);

        $orderItemA = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'unit' => 'kg',
        ]);

        $orderItemB = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'unit' => 'kg',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'invoice_number' => 'INV-20261104-001',
            'shop_order_id' => $order->id,
            'shop_id' => $this->shop1->id,
            'business_date' => $this->date,
            'status' => 'issued',
            'subtotal' => 850.00,
            'final_total' => 850.00,
        ]);

        $itemGradeA = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItemA->id,
            'product_id' => $this->tomato->id,
            'product_name' => 'Tomato',
            'product_grade' => 'A',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'price_quantity' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 50.00,
            'line_subtotal' => 500.00,
            'final_line_total' => 500.00,
        ]);

        $itemGradeB = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItemB->id,
            'product_id' => $this->tomato->id,
            'product_name' => 'Tomato (B)',
            'product_grade' => 'B',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'price_quantity' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 35.00,
            'line_subtotal' => 350.00,
            'final_line_total' => 350.00,
        ]);

        return [$order, $invoice, $itemGradeA, $itemGradeB];
    }

    public function test_legacy_request_without_explicit_grade_on_grade_a_item_preserves_compatibility(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // Older mobile client sends request for itemGradeA without passing product_grade or grade
        $response = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $itemGradeA->id,
                'item_id' => $itemGradeA->id,
                'order_number' => $invoice->invoice_number,
                'product_id' => $this->tomato->id,
                'special_price' => 44.00,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'special_price' => 44.00,
                'product_grade' => 'A',
            ],
        ]);

        $this->assertDatabaseHas('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'selling_price' => 44.00,
        ]);

        $this->assertSame(44.00, (float) $itemGradeA->fresh()->unit_price);
        $this->assertSame(35.00, (float) $itemGradeB->fresh()->unit_price);
    }

    public function test_failed_repricing_rolls_back_special_price_persistence(): void
    {
        [$order, $invoice, $itemGradeA, $itemGradeB] = $this->createOrderAndInvoiceWithTwoGrades();

        // Set historical business date in past where historical repricing is disallowed
        $invoice->update(['business_date' => '2025-01-01']);

        // Attempting to update bill price for Grade B on historical locked invoice
        $response = $this->actingAs($this->purchaser)
            ->postJson(route('api.v1.purchaser.special-price.approve'), [
                'id' => $itemGradeB->id,
                'item_id' => $itemGradeB->id,
                'order_number' => $invoice->invoice_number,
                'product_id' => $this->tomato->id,
                'special_price' => 29.00,
            ]);

        $response->assertStatus(422);

        // Verify that the special price was rolled back and NOT saved to shop_daily_product_prices
        $this->assertDatabaseMissing('shop_daily_product_prices', [
            'shop_id' => $this->shop1->id,
            'product_id' => $this->tomato->id,
            'selling_price' => 29.00,
        ]);
    }
}
