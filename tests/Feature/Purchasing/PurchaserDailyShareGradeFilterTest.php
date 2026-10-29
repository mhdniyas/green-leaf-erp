<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserSharePreset;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserDailyShareGradeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Shop $shop;

    private Product $tomato;

    private Product $potato;

    private string $testDate = '2026-10-29';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow($this->testDate.' 09:00:00');
        Role::findOrCreate('purchaser');
        $this->purchaser = User::factory()->create(['name' => 'Purchaser Daily Tester']);
        $this->purchaser->assignRole('purchaser');

        $category = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['name' => 'Branch One']);

        $this->tomato = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $this->potato = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Potato Fresh',
            'sku' => 'POT-F',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $this->testDate,
            'state' => 'approved',
        ]);
        DB::table('shop_orders')->where('id', $order->id)->update(['business_date' => $this->testDate]);

        // Demand for Tomato: Grade A (20 kg), Grade B (15 kg), Grade C (10 kg), Mixed (5 kg)
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'kg',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'C',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'U',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'unit' => 'kg',
        ]);

        // Demand for Potato: Grade A (30 kg) only
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->potato->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'unit' => 'kg',
        ]);
    }

    public function test_grade_a_filter_includes_only_grade_a_demand_and_has_correct_heading(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'A',
            'share_mode' => 'any',
        ]));

        $response->assertOk();
        $shareSummary = $response->viewData('shareSummary');
        $previewTotalText = $response->viewData('shareTotalPreviewText');
        $previewText = $response->viewData('sharePreviewText');

        // Header check
        $this->assertStringContainsString('*Daily Purchase Total Qty — Grade A*', $previewTotalText);
        $this->assertStringContainsString('*Daily Purchase Summary — Grade A*', $previewText);

        // Tomato should have 20 kg (Grade A demand)
        $tomatoSummary = $shareSummary->firstWhere('product_id', $this->tomato->id);
        $this->assertNotNull($tomatoSummary);
        $this->assertEquals(20.0, (float) $tomatoSummary['total_approved_qty']);
        $this->assertEquals(20.0, (float) $tomatoSummary['remaining_qty']);

        // Potato should have 30 kg
        $potatoSummary = $shareSummary->firstWhere('product_id', $this->potato->id);
        $this->assertNotNull($potatoSummary);
        $this->assertEquals(30.0, (float) $potatoSummary['total_approved_qty']);

        // Message text contains exact quantities
        $this->assertStringContainsString('Total 20 kg', $previewTotalText);
        $this->assertStringContainsString('Total 30 kg', $previewTotalText);
        // Does NOT contain Grade B (15kg) or Grade C (10kg)
        $this->assertStringNotContainsString('Total 15 kg', $previewTotalText);
        $this->assertStringNotContainsString('Total 10 kg', $previewTotalText);
    }

    public function test_grade_b_filter_includes_only_grade_b_demand_and_has_correct_heading(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
            'share_mode' => 'any',
        ]));

        $response->assertOk();
        $shareSummary = $response->viewData('shareSummary');
        $previewTotalText = $response->viewData('shareTotalPreviewText');

        // Header check
        $this->assertStringContainsString('*Daily Purchase Total Qty — Grade B*', $previewTotalText);

        // Tomato should have 15 kg (Grade B demand)
        $this->assertCount(1, $shareSummary);
        $tomatoSummary = $shareSummary->firstWhere('product_id', $this->tomato->id);
        $this->assertNotNull($tomatoSummary);
        $this->assertEquals(15.0, (float) $tomatoSummary['total_approved_qty']);
        $this->assertEquals(15.0, (float) $tomatoSummary['remaining_qty']);

        // Potato had NO Grade B demand, so it must not be included
        $this->assertNull($shareSummary->firstWhere('product_id', $this->potato->id));

        $this->assertStringContainsString('Total 15 kg', $previewTotalText);
        $this->assertStringNotContainsString('Potato Fresh', $previewTotalText);
    }

    public function test_grade_c_filter_includes_only_grade_c_demand_and_has_correct_heading(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'C',
            'share_mode' => 'any',
        ]));

        $response->assertOk();
        $shareSummary = $response->viewData('shareSummary');
        $previewTotalText = $response->viewData('shareTotalPreviewText');

        $this->assertStringContainsString('*Daily Purchase Total Qty — Grade C*', $previewTotalText);

        $this->assertCount(1, $shareSummary);
        $tomatoSummary = $shareSummary->firstWhere('product_id', $this->tomato->id);
        $this->assertNotNull($tomatoSummary);
        $this->assertEquals(10.0, (float) $tomatoSummary['total_approved_qty']);
        $this->assertEquals(10.0, (float) $tomatoSummary['remaining_qty']);
        $this->assertStringContainsString('Total 10 kg', $previewTotalText);
    }

    public function test_legacy_mixed_or_u_share_urls_safely_redirect_to_all_grades(): void
    {
        // Testing legacy purchase_grade=MIXED safely redirects to ALL
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'MIXED',
            'share_mode' => 'any',
        ]));

        $response->assertRedirect(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'ALL',
            'share_mode' => 'any',
        ]));
        $response->assertSessionHas('info');

        // Testing legacy purchase_grade=U safely redirects to ALL
        $responseU = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'U',
            'share_mode' => 'any',
        ]));

        $responseU->assertRedirect(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'ALL',
            'share_mode' => 'any',
        ]));
    }

    public function test_all_grades_filter_includes_only_a_b_c_and_does_not_include_mixed(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'ALL',
            'share_mode' => 'any',
        ]));

        $response->assertOk();
        $shareSummary = $response->viewData('shareSummary');
        $previewTotalText = $response->viewData('shareTotalPreviewText');
        $previewText = $response->viewData('sharePreviewText');

        // Header check
        $this->assertStringContainsString('*Daily Purchase Total Qty — All Grades*', $previewTotalText);
        $this->assertStringContainsString('*Daily Purchase Summary — All Grades*', $previewText);

        // Tomato should have separate rows for A, B, and C only (Mixed must be excluded)
        $tomatoRows = $shareSummary->where('product_id', $this->tomato->id)->values();
        $this->assertCount(3, $tomatoRows);

        $productNames = $tomatoRows->pluck('product_name')->all();
        $this->assertContains('Tomato H (A)', $productNames);
        $this->assertContains('Tomato H (B)', $productNames);
        $this->assertContains('Tomato H (C)', $productNames);
        $this->assertNotContains('Tomato H (Mixed)', $productNames);

        $rowA = $tomatoRows->firstWhere('product_name', 'Tomato H (A)');
        $rowB = $tomatoRows->firstWhere('product_name', 'Tomato H (B)');
        $rowC = $tomatoRows->firstWhere('product_name', 'Tomato H (C)');

        $this->assertEquals(20.0, (float) $rowA['remaining_qty']);
        $this->assertEquals(15.0, (float) $rowB['remaining_qty']);
        $this->assertEquals(10.0, (float) $rowC['remaining_qty']);

        // Check WhatsApp message contains separate labeled headers and does NOT contain Mixed
        $this->assertStringContainsString('*Tomato H (A)*', $previewTotalText);
        $this->assertStringContainsString('*Tomato H (B)*', $previewTotalText);
        $this->assertStringContainsString('*Tomato H (C)*', $previewTotalText);
        $this->assertStringNotContainsString('*Tomato H (Mixed)*', $previewTotalText);
    }

    public function test_empty_results_cleanly_displays_fallback_message_in_whatsapp_text(): void
    {
        // Delete all shop order items so demand is completely empty on test date
        ShopOrderItem::query()->delete();

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
        ]));

        $response->assertOk();
        $shareSummary = $response->viewData('shareSummary');
        $this->assertTrue($shareSummary->isEmpty());

        $previewTotalText = $response->viewData('shareTotalPreviewText');
        $previewText = $response->viewData('sharePreviewText');

        $this->assertStringContainsString('*Daily Purchase Total Qty — Grade B*', $previewTotalText);
        $this->assertStringContainsString('No items for selected grade.', $previewTotalText);

        $this->assertStringContainsString('*Daily Purchase Summary — Grade B*', $previewText);
        $this->assertStringContainsString('No items for selected grade.', $previewText);

        // Verify URL encoding
        $shareTotalUrl = $response->viewData('shareTotalUrl');
        $this->assertStringContainsString(rawurlencode('No items for selected grade.'), $shareTotalUrl);
    }

    public function test_screen_displayed_remaining_quantity_matches_whatsapp_total_text(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
            'share_mode' => 'any',
        ]));

        $response->assertOk();
        $shareSummary = $response->viewData('shareSummary');
        $previewTotalText = $response->viewData('shareTotalPreviewText');

        foreach ($shareSummary as $item) {
            $expectedQtyStr = 'Total '.rtrim(rtrim(number_format((float) $item['remaining_qty'], 2), '0'), '.').' '.$item['unit'];
            // Quantity in summary must be present in previewTotalText
            $this->assertStringContainsString($expectedQtyStr, $previewTotalText);
            // Screen renders the product name and remaining_qty
            $response->assertSee($item['product_name']);
            $response->assertSee(number_format((float) $item['remaining_qty'], 2));
        }
    }

    public function test_cart_deductions_are_grade_specific(): void
    {
        // Add a draft cart for Tomato Grade A (5 kg) and submitted cart for Tomato Grade B (10 kg)
        $cartA = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->testDate,
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-TEST-A1',
        ]);
        DB::table('purchaser_carts')->where('id', $cartA->id)->update(['business_date' => $this->testDate]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cartA->id,
            'product_id' => $this->tomato->id,
            'grade' => 'A',
            'quantity' => 5.0,
            'unit_price' => 20.0,
            'line_total' => 100.0,
        ]);

        $cartB = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->testDate,
            'status' => 'submitted',
            'purchase_grade' => 'B',
            'cart_number' => 'VC-TEST-B1',
        ]);
        DB::table('purchaser_carts')->where('id', $cartB->id)->update(['business_date' => $this->testDate]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cartB->id,
            'product_id' => $this->tomato->id,
            'grade' => 'B',
            'quantity' => 10.0,
            'unit_price' => 15.0,
            'line_total' => 150.0,
        ]);

        // When sharing Grade B: total 15, bought 10 => remaining 5
        $resB = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
            'share_mode' => 'any',
        ]));
        $summaryB = $resB->viewData('shareSummary')->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(15.0, (float) $summaryB['total_approved_qty']);
        $this->assertEquals(10.0, (float) $summaryB['bought_qty']);
        $this->assertEquals(5.0, (float) $summaryB['remaining_qty']);

        // When sharing Grade A: total 20, bought 0, draft 5 => remaining 20
        $resA = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'A',
            'share_mode' => 'any',
        ]));
        $summaryA = $resA->viewData('shareSummary')->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(20.0, (float) $summaryA['total_approved_qty']);
        $this->assertEquals(0.0, (float) $summaryA['bought_qty']);
        $this->assertEquals(5.0, (float) $summaryA['draft_qty']);
        $this->assertEquals(20.0, (float) $summaryA['remaining_qty']);
    }

    public function test_presets_respect_selected_purchase_grade(): void
    {
        PurchaserSharePreset::query()->create([
            'user_id' => $this->purchaser->id,
            'purchase_grade' => 'B',
            'name' => 'B Grade Essentials',
            'product_ids' => [$this->tomato->id],
        ]);

        PurchaserSharePreset::query()->create([
            'user_id' => $this->purchaser->id,
            'purchase_grade' => 'A',
            'name' => 'A Grade Essentials',
            'product_ids' => [$this->potato->id],
        ]);

        // In Grade B share, only B Grade Essentials should appear
        $resB = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
        ]));
        $presetsB = $resB->viewData('sharePresets');
        $this->assertTrue($presetsB->contains('name', 'B Grade Essentials'));
        $this->assertFalse($presetsB->contains('name', 'A Grade Essentials'));

        // In Grade A share, only A Grade Essentials should appear
        $resA = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'A',
        ]));
        $presetsA = $resA->viewData('sharePresets');
        $this->assertTrue($presetsA->contains('name', 'A Grade Essentials'));
        $this->assertFalse($presetsA->contains('name', 'B Grade Essentials'));
    }

    public function test_daily_page_has_grade_selector_and_passes_active_grade_to_share_link(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
        ]));

        $response->assertOk();
        $response->assertSee('All Grades');
        $response->assertSee('Grade A');
        $response->assertSee('Grade B');
        $response->assertSee('Grade C');

        // Verify the Share link passes purchase_grade=B
        $expectedShareUrl = route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'B',
        ]);
        $response->assertSee(e($expectedShareUrl), false);
    }

    public function test_mixed_purchase_quantities_do_not_deduct_from_or_affect_shop_order_demand(): void
    {
        // Create a submitted Mixed cart for Tomato
        $cartMixed = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->testDate,
            'status' => 'submitted',
            'purchase_grade' => 'MIXED',
            'cart_number' => 'VC-TEST-MIX1',
        ]);
        DB::table('purchaser_carts')->where('id', $cartMixed->id)->update(['business_date' => $this->testDate]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cartMixed->id,
            'product_id' => $this->tomato->id,
            'grade' => 'U',
            'quantity' => 15.0,
            'unit_price' => 12.0,
            'line_total' => 180.0,
        ]);

        // When sharing ALL, Tomato A (20 kg), Tomato B (15 kg), and Tomato C (10 kg) must NOT be deducted by Mixed purchase
        $resAll = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', [
            'date' => $this->testDate,
            'purchase_grade' => 'ALL',
            'share_mode' => 'any',
        ]));
        $resAll->assertOk();
        $rows = $resAll->viewData('shareSummary')->where('product_id', $this->tomato->id);

        $rowA = $rows->firstWhere('product_name', 'Tomato H (A)');
        $rowB = $rows->firstWhere('product_name', 'Tomato H (B)');
        $rowC = $rows->firstWhere('product_name', 'Tomato H (C)');

        $this->assertEquals(20.0, (float) $rowA['remaining_qty']);
        $this->assertEquals(15.0, (float) $rowB['remaining_qty']);
        $this->assertEquals(10.0, (float) $rowC['remaining_qty']);
    }
}
