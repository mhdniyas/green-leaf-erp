<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Models\BusinessSetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\MultiGradeOrderGate;
use App\Services\ShopInvoices\ShopInvoiceIntegrityValidator;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ShopInvoiceMultiGradePhase4BTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ShopPriceGroup $groupA;

    private ShopPriceGroup $groupB;

    private Shop $shopA;

    private Shop $shopB;

    private Product $product;

    private string $businessDate;

    private ShopInvoiceService $shopInvoiceService;

    private ShopInvoiceIntegrityValidator $integrityValidator;

    private ApprovedDailyPriceResolver $approvedDailyPriceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->groupA = ShopPriceGroup::create([
            'name' => 'A',
            'code' => 'A',
            'is_active' => true,
        ]);

        $this->groupB = ShopPriceGroup::create([
            'name' => 'B',
            'code' => 'B',
            'is_active' => true,
        ]);

        $this->shopA = Shop::create([
            'name' => 'Shop Casio',
            'code' => 'CAS-01',
            'shop_price_group_id' => $this->groupA->id,
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->shopB = Shop::create([
            'name' => 'Shop Mart B',
            'code' => 'MRT-02',
            'shop_price_group_id' => $this->groupB->id,
            'is_active' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H',
            'unit' => 'KG',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '0']
        );

        $this->shopInvoiceService = app(ShopInvoiceService::class);
        $this->integrityValidator = app(ShopInvoiceIntegrityValidator::class);
        $this->approvedDailyPriceResolver = app(ApprovedDailyPriceResolver::class);
    }

    private function approvePrice(Product $product, string $grade, float $priceA, float $priceB, float $priceC, ?string $date = null): DailyPriceApproval
    {
        $d = $date ?? $this->businessDate;

        return DailyPriceApproval::updateOrCreate(
            [
                'product_id' => $product->id,
                'business_date' => $d,
                'grade' => $grade,
            ],
            [
                'purchase_price' => round($priceA * 0.7, 4),
                'price_unit' => 'KG',
                'price_a' => $priceA,
                'price_b' => $priceB,
                'price_c' => $priceC,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $this->admin->id,
            ]
        );
    }

    private function createOrderItem(ShopOrder $order, Product $product, string $grade, float $approvedQty, ?float $loadedQty = null, array $overrides = []): ShopOrderItem
    {
        return ShopOrderItem::create(array_merge([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'requested_qty' => $approvedQty,
            'approved_qty' => $approvedQty,
            'loaded_qty' => $loadedQty ?? $approvedQty,
            'unit' => 'KG',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ], $overrides));
    }

    // =========================================================================
    // Grade A Regression Tests (1 - 6)
    // =========================================================================

    public function test_01_existing_single_grade_a_invoice_generation(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->assertCount(1, $invoice->items);
        $item = $invoice->items->first();
        $this->assertSame('A', $item->product_grade);
        $this->assertSame(10.0, (float) $item->approved_qty);
        $this->assertSame(40.0, (float) $item->unit_price);
        $this->assertSame(400.0, (float) $item->final_line_total);
        $this->assertSame(400.0, (float) $invoice->subtotal);
        $this->assertSame(400.0, (float) $invoice->final_total);
    }

    public function test_02_existing_daily_approved_price_validation(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->integrityValidator->assertMatchesApprovedDailyPrices($invoice);
        $this->assertTrue(true);
    }

    public function test_03_existing_invoice_synchronization(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);

        $invoice1 = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $this->assertCount(1, $invoice1->items);
        $originalItemId = $invoice1->items->first()->id;

        // Sync again
        $invoice2 = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $this->assertCount(1, $invoice2->items);
        $this->assertSame($originalItemId, $invoice2->items->first()->id);
        $this->assertSame(400.0, (float) $invoice2->final_total);
    }

    public function test_04_existing_invoice_editing_and_credit_notes(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $invoiceItem = $invoice->items->first();

        // Adjust item from 10 to 8 kg at 40
        $updated = $this->shopInvoiceService->updateItemFinalQuantityAndPrice(
            $invoice,
            $invoiceItem,
            8.0,
            40.0,
            $this->admin->id,
            'Adjustment for damage.'
        );

        $this->assertSame(320.0, (float) $updated->final_total);
        $summary = $updated->creditNoteSummary();
        $this->assertTrue($summary['has_changes']);
        $this->assertSame(-80.0, (float) $summary['net_difference']);
        $this->assertSame('Tomato H', $summary['rows']->first()['product_name']);
    }

    public function test_05_existing_pdf_invoice_output_has_no_grade_suffix_for_single_grade_a(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('purchasing.shop-invoices.pdf', $invoice));
        $response->assertOk();
        $response->assertSee('Tomato H');
        $response->assertDontSee('Tomato H — Grade A');
    }

    public function test_06_existing_accounting_totals_post_accurately(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $finalized = $this->shopInvoiceService->markFinalized($invoice, $this->admin->id, 'finalized', 'Finalized.');

        $this->assertSame(400.0, (float) $finalized->final_total);
        $this->assertSame(400.0, (float) $finalized->balance_amount);
        $this->assertSame(1, ShopLedgerTransaction::query()->count());
        $this->assertSame(400.0, (float) ShopLedgerTransaction::query()->first()->amount);
    }

    // =========================================================================
    // Multi-Grade Combined Invoice Tests (7 - 24)
    // =========================================================================

    public function test_07_same_product_grade_a_and_b_produces_two_invoice_lines(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->assertCount(2, $invoice->items);
        $grades = $invoice->items->pluck('product_grade')->sort()->values()->all();
        $this->assertSame(['A', 'B'], $grades);
    }

    public function test_08_same_product_a_b_c_produces_three_invoice_lines(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);
        $this->approvePrice($this->product, 'C', 15.0, 14.0, 12.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);
        $this->createOrderItem($order, $this->product, 'C', 3.0, 3.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->assertCount(3, $invoice->items);
        $grades = $invoice->items->pluck('product_grade')->sort()->values()->all();
        $this->assertSame(['A', 'B', 'C'], $grades);
    }

    public function test_09_correct_grade_specific_delivery_date_approved_rates(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $itemA = $invoice->items->firstWhere('product_grade', 'A');
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        $this->assertSame(40.0, (float) $itemA->unit_price);
        $this->assertSame(25.0, (float) $itemB->unit_price);
    }

    public function test_10_correct_shop_price_category_applied_to_every_grade(): void
    {
        // For Shop B (Price Group B): Grade A price_b is 38.0, Grade B price_b is 24.0
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopB->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $itemA = $invoice->items->firstWhere('product_grade', 'A');
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        $this->assertSame(38.0, (float) $itemA->unit_price);
        $this->assertSame(24.0, (float) $itemB->unit_price);
        $this->assertSame(380.0, (float) $itemA->final_line_total);
        $this->assertSame(120.0, (float) $itemB->final_line_total);
        $this->assertSame(500.0, (float) $invoice->final_total);
    }

    public function test_11_invoice_grand_total_equals_sum_of_valid_lines_and_adjustments(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        // Grand total: 10 * 40 + 5 * 25 = 400 + 125 = 525
        $this->assertSame(525.0, (float) $invoice->subtotal);
        $this->assertSame(525.0, (float) $invoice->final_total);

        // Apply discount of 25
        $discounted = $this->shopInvoiceService->applyAdminDiscount($invoice, [
            'discount_total' => 25.0,
            'discount_note' => 'Combined volume discount.',
        ], $this->admin->id);

        $this->assertSame(500.0, (float) $discounted->final_total);
        $this->assertSame(500.0, (float) $discounted->balance_amount);
    }

    public function test_12_saving_or_syncing_again_does_not_duplicate_or_delete_grade_lines(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice1 = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $this->assertCount(2, $invoice1->items);
        $ids1 = $invoice1->items->pluck('id')->sort()->values()->all();

        // Resync 1
        $invoice2 = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $this->assertCount(2, $invoice2->items);
        $ids2 = $invoice2->items->pluck('id')->sort()->values()->all();
        $this->assertSame($ids1, $ids2);

        // Resync 2
        $invoice3 = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $this->assertCount(2, $invoice3->items);
        $this->assertSame(525.0, (float) $invoice3->final_total);
    }

    public function test_13_updating_grade_b_does_not_affect_grade_a(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        // Update Grade B qty from 5.0 to 3.0 at rate 25
        $updated = $this->shopInvoiceService->updateItemFinalQuantityAndPrice(
            $invoice,
            $itemB,
            3.0,
            25.0,
            $this->admin->id,
            'Grade B partial shortage adjustment.'
        );

        $itemA = $updated->items->firstWhere('product_grade', 'A');
        $itemBUpdated = $updated->items->firstWhere('product_grade', 'B');

        $this->assertSame(10.0, (float) $itemA->approved_qty);
        $this->assertSame(40.0, (float) $itemA->unit_price);
        $this->assertSame(400.0, (float) $itemA->final_line_total);

        $this->assertSame(3.0, (float) $itemBUpdated->delivered_qty);
        $this->assertSame(2.0, (float) $itemBUpdated->shortage_qty);
        $this->assertSame(25.0, (float) $itemBUpdated->unit_price);
        $this->assertSame(75.0, (float) $itemBUpdated->final_line_total);

        // 400 + 75 = 475
        $this->assertSame(475.0, (float) $updated->final_total);
    }

    public function test_14_correct_partial_delivery_and_shortage_calculations(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $orderItemA = $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $orderItemB = $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        // Delivery checkin: Grade A delivered full (10), Grade B delivered partial (3)
        $deliveredQtys = [
            $orderItemA->id => 10.0,
            $orderItemB->id => 3.0,
        ];

        $invoice = $this->shopInvoiceService->applyDeliveryCheckin(
            $order,
            $deliveredQtys,
            $this->admin->id,
            'Delivery completed with Grade B shortage.'
        );

        $itemA = $invoice->items->firstWhere('product_grade', 'A');
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        $this->assertSame(10.0, (float) $itemA->delivered_qty);
        $this->assertSame(0.0, (float) $itemA->shortage_qty);
        $this->assertSame(400.0, (float) $itemA->final_line_total);

        $this->assertSame(3.0, (float) $itemB->delivered_qty);
        $this->assertSame(2.0, (float) $itemB->shortage_qty);
        $this->assertSame(50.0, (float) $itemB->shortage_amount);
        $this->assertSame(75.0, (float) $itemB->final_line_total);

        $this->assertSame(50.0, (float) $invoice->shortage_total);
        $this->assertSame(475.0, (float) $invoice->final_total);
    }

    public function test_15_grade_specific_credit_note_at_original_invoice_rate(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        // Adjust Grade B from 5 to 4 kg
        $updated = $this->shopInvoiceService->updateItemFinalQuantityAndPrice(
            $invoice,
            $itemB,
            4.0,
            25.0,
            $this->admin->id,
            '1kg damaged Grade B returned.'
        );

        $summary = $updated->creditNoteSummary();
        $this->assertTrue($summary['has_changes']);
        $this->assertSame(-25.0, (float) $summary['net_difference']);
        $this->assertSame(525.0, (float) $summary['previous_invoice_total']);
        $this->assertSame(500.0, (float) $summary['revised_invoice_total']);

        $row = $summary['rows']->first();
        $this->assertSame('B', $row['product_grade']);
        $this->assertSame('Tomato H — Grade B', $row['product_name']);
        $this->assertSame(25.0, (float) $row['rate']);
        $this->assertSame(5.0, (float) $row['previous_qty']);
        $this->assertSame(4.0, (float) $row['revised_qty']);
        $this->assertSame(-25.0, (float) $row['difference']);
    }

    public function test_16_missing_grade_b_approval_prevents_unsafe_finalization(): void
    {
        // Only approve Grade A; Grade B has no approved daily price
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $this->expectException(ValidationException::class);
        $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
    }

    public function test_17_incorrect_grade_b_price_fails_integrity_validation(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        // Tamper with Grade B price to 30.00 instead of 25.00
        $itemB->update(['unit_price' => 30.0]);

        $this->expectException(ValidationException::class);
        $this->integrityValidator->assertMatchesApprovedDailyPrices($invoice->fresh('items'));
    }

    public function test_18_valid_grade_b_price_passes_integrity_validation(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->integrityValidator->assertMatchesApprovedDailyPrices($invoice);
        $this->assertTrue(true);
    }

    public function test_19_distinct_measures_remain_correctly_represented(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $productUnit = ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'Box',
            'label' => 'Box',
            'conversion_to_base' => 5.0,
            'is_orderable' => true,
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        // Grade A ordered as 2 Boxes = 10 KG
        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0, [
            'requested_product_unit_id' => $productUnit->id,
            'requested_unit' => 'Box',
            'requested_unit_quantity' => 2.0,
            'requested_unit_conversion_to_base' => 5.0,
        ]);

        // Grade B ordered as 5 KG directly
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        $this->assertCount(2, $invoice->items);
        $itemA = $invoice->items->firstWhere('product_grade', 'A');
        $itemB = $invoice->items->firstWhere('product_grade', 'B');

        $this->assertSame(10.0, (float) $itemA->approved_qty);
        $this->assertSame(40.0, (float) $itemA->unit_price);
        $this->assertSame(400.0, (float) $itemA->final_line_total);

        $this->assertSame(5.0, (float) $itemB->approved_qty);
        $this->assertSame(25.0, (float) $itemB->unit_price);
        $this->assertSame(125.0, (float) $itemB->final_line_total);
    }

    public function test_20_finalized_historical_invoices_stay_unchanged(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $finalized = $this->shopInvoiceService->markFinalized($invoice, $this->admin->id, 'finalized', 'Locked bill.');

        // Attempting to reprice finalized invoice must throw
        $this->expectException(ValidationException::class);
        $this->shopInvoiceService->repriceInvoice($finalized, $this->admin->id, 'Reprice attempt.');
    }

    public function test_21_pdf_and_shared_invoice_display_all_grade_lines(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);

        // PDF View
        $pdfRes = $this->actingAs($this->admin)->get(route('purchasing.shop-invoices.pdf', $invoice));
        $pdfRes->assertOk();
        $pdfRes->assertSee('Tomato H — Grade A');
        $pdfRes->assertSee('Tomato H — Grade B');

        // Dompdf View
        $dompdfRes = $this->actingAs($this->admin)->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'dompdf' => 1]));
        $dompdfRes->assertOk();
        $dompdfRes->assertSee('Tomato H — Grade A');
        $dompdfRes->assertSee('Tomato H — Grade B');

        // Shared View
        $signedUrl = URL::signedRoute('shop-invoices.shared', [
            'invoice' => $invoice->invoice_number,
        ]);
        $sharedRes = $this->get($signedUrl);
        $sharedRes->assertOk();
        $sharedRes->assertSee('Tomato H — Grade A');
        $sharedRes->assertSee('Tomato H — Grade B');
    }

    public function test_22_cashbook_and_financial_totals_remain_correct(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
        $finalized = $this->shopInvoiceService->markFinalized($invoice, $this->admin->id, 'finalized', 'Test combined finalization.');

        // Ledger must have exactly 1 transaction for the combined amount 525.00
        $transactions = ShopLedgerTransaction::query()->get();
        $this->assertCount(1, $transactions);
        $this->assertSame(525.0, (float) $transactions->first()->amount);
        $this->assertSame($this->shopA->id, (int) $transactions->first()->shop_id);
    }

    public function test_23_repeated_concurrent_invoice_synchronization_remains_safe(): void
    {
        $this->approvePrice($this->product, 'A', 40.0, 38.0, 35.0);
        $this->approvePrice($this->product, 'B', 25.0, 24.0, 22.0);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => $this->businessDate,
        ]);

        $this->createOrderItem($order, $this->product, 'A', 10.0, 10.0);
        $this->createOrderItem($order, $this->product, 'B', 5.0, 5.0);

        // Run 5 successive synchronization passes
        for ($i = 0; $i < 5; $i++) {
            $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($order, $this->admin->id);
            $this->assertCount(2, $invoice->items);
            $this->assertSame(525.0, (float) $invoice->final_total);
        }

        $this->assertSame(2, ShopInvoiceItem::query()->where('shop_invoice_id', $invoice->id)->count());
    }

    public function test_24_global_addon_remains_disabled(): void
    {
        $gate = app(MultiGradeOrderGate::class);
        $this->assertFalse($gate->isFeatureEnabled());
        $this->assertSame('0', BusinessSetting::where('key', 'enable_multi_grade_ordering')->value('value'));
    }
}
