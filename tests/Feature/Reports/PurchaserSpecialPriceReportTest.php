<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaserSpecialPriceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $admin;

    private ShopPriceGroup $groupA;

    private ShopPriceGroup $groupB;

    private Shop $shop1;

    private Shop $shop2;

    private Category $category;

    private Product $productTomato;

    private Product $productPotato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Niyas']);
        $this->purchaser->assignRole('purchaser');

        $this->admin = User::factory()->create(['name' => 'Admin Boss']);
        $this->admin->assignRole('admin');

        $this->groupA = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->groupB = ShopPriceGroup::factory()->create(['name' => 'B', 'is_active' => true]);

        $this->shop1 = Shop::factory()->create([
            'name' => 'Shop Downtown',
            'code' => 'SH-01',
            'shop_price_group_id' => $this->groupA->id,
        ]);

        $this->shop2 = Shop::factory()->create([
            'name' => 'Shop Uptown',
            'code' => 'SH-02',
            'shop_price_group_id' => $this->groupB->id,
        ]);

        $this->category = Category::factory()->create(['name' => 'Vegetables']);

        $this->productTomato = Product::factory()->create([
            'name' => 'Fresh Tomato',
            'sku' => 'VEG-TOM-01',
            'unit' => 'kg',
            'category_id' => $this->category->id,
        ]);

        $this->productPotato = Product::factory()->create([
            'name' => 'Russet Potato',
            'sku' => 'VEG-POT-02',
            'unit' => 'kg',
            'category_id' => $this->category->id,
        ]);
    }

    public function test_purchaser_can_view_special_price_report_with_shop_and_product_associations(): void
    {
        $today = today()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('Fresh Tomato');
        $response->assertSee('VEG-TOM-01');
        $response->assertSee('Shop Downtown');
        $response->assertSee('₹45.00');
        $response->assertSee('Grade A');
    }

    public function test_multiple_shops_receiving_different_special_prices_for_same_product(): void
    {
        $today = today()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop2->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 42.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('Shop Downtown');
        $response->assertSee('₹45.00');
        $response->assertSee('Shop Uptown');
        $response->assertSee('₹42.00');
    }

    public function test_correct_normal_price_and_special_price_comparison(): void
    {
        $today = today()->toDateString();

        DailyPriceApproval::query()->create([
            'product_id' => $this->productTomato->id,
            'business_date' => $today,
            'grade' => 'A',
            'purchase_price' => 35.00,
            'price_a' => 50.00,
            'price_b' => 52.00,
            'price_c' => 55.00,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
            'approved_by' => $this->purchaser->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('₹50.00');
        $response->assertSee('₹45.00');
        $response->assertSee('-₹5.00');
    }

    public function test_business_date_filtering(): void
    {
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $yesterday,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productPotato->id,
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        $responseToday = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['range' => 'today']));
        $responseToday->assertOk();
        $responseToday->assertSee('Fresh Tomato');
        $responseToday->assertDontSee('Russet Potato');

        $responseYesterday = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['range' => 'yesterday']));
        $responseYesterday->assertOk();
        $responseYesterday->assertSee('Russet Potato');
        $responseYesterday->assertDontSee('Fresh Tomato');
    }

    public function test_grade_handling_reflects_grade_a(): void
    {
        $today = today()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('Grade A');
    }

    public function test_updater_attribution_and_timestamp_without_inventing_identities(): void
    {
        $today = today()->toDateString();

        $priceWithUser = ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
            'approved_by' => $this->purchaser->id,
            'approved_at' => Carbon::parse('2026-11-04 10:30:00'),
        ]);

        $priceWithoutUser = ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop2->id,
            'product_id' => $this->productPotato->id,
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'draft',
            'created_by' => null,
            'approved_by' => null,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('Purchaser Niyas');
        $response->assertSee('Updated by: Purchaser Niyas');
    }

    public function test_lifecycle_statuses_draft_and_expired(): void
    {
        $today = today()->toDateString();
        $pastDate = today()->subDays(5)->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'draft',
            'created_by' => $this->purchaser->id,
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $pastDate,
            'shop_id' => $this->shop2->id,
            'product_id' => $this->productPotato->id,
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', [
                'range' => 'custom',
                'date_from' => $pastDate,
                'date_to' => $today,
            ]));

        $response->assertOk();
        $response->assertSee('Draft');
        $response->assertSee('Expired');
    }

    public function test_configured_versus_invoice_applied_distinction(): void
    {
        $today = today()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop2->id,
            'product_id' => $this->productPotato->id,
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop1->id,
            'business_date' => $today,
            'state' => 'approved',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_order_id' => $order->id,
            'shop_id' => $this->shop1->id,
            'business_date' => $today,
            'status' => 'approved',
            'invoice_number' => 'INV-TEST-001',
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->productTomato->id,
            'product_grade' => 'A',
            'unit_price' => 45.00,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('Applied');
        $response->assertSee('Configured');
    }

    public function test_purchaser_authorization_and_unauthorized_access_denial(): void
    {
        $unauthenticated = $this->get(route('purchaser.reports.special-prices'));
        $unauthenticated->assertRedirect(route('login'));

        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');
        $denied = $this->actingAs($shopUser)->get(route('purchaser.reports.special-prices'));
        $denied->assertRedirect(route('dashboard'));
        $denied->assertSessionHas('error', 'You do not have access to that page.');

        $deniedJson = $this->actingAs($shopUser)->getJson(route('purchaser.reports.special-prices'));
        $deniedJson->assertForbidden();

        $allowedPurchaser = $this->actingAs($this->purchaser)->get(route('purchaser.reports.special-prices'));
        $allowedPurchaser->assertOk();

        $allowedAdmin = $this->actingAs($this->admin)->get(route('purchaser.reports.special-prices'));
        $allowedAdmin->assertOk();
    }

    public function test_search_and_status_filtering(): void
    {
        $today = today()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop2->id,
            'product_id' => $this->productPotato->id,
            'selling_price' => 30.00,
            'price_unit' => 'kg',
            'status' => 'draft',
        ]);

        $searchTomato = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['search' => 'Tomato', 'date' => $today]));
        $searchTomato->assertOk();
        $searchTomato->assertSee('Fresh Tomato');
        $searchTomato->assertDontSee('Russet Potato');

        $filterDraft = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['status' => 'draft', 'date' => $today]));
        $filterDraft->assertOk();
        $filterDraft->assertSee('Russet Potato');
        $filterDraft->assertDontSee('Fresh Tomato');
    }

    public function test_report_is_read_only_and_does_not_modify_invoices(): void
    {
        $today = today()->toDateString();

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop1->id,
            'business_date' => $today,
            'state' => 'approved',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_order_id' => $order->id,
            'shop_id' => $this->shop1->id,
            'business_date' => $today,
            'status' => 'finalized',
            'final_total' => 500.00,
            'finalized_at' => now(),
        ]);

        $item = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->productTomato->id,
            'product_grade' => 'A',
            'unit_price' => 50.00,
            'line_subtotal' => 500.00,
            'final_line_total' => 500.00,
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices', ['date' => $today]));

        $response->assertOk();

        $invoice->refresh();
        $item->refresh();
        $this->assertEquals(500.00, (float) $invoice->final_total);
        $this->assertEquals('finalized', $invoice->status);
        $this->assertEquals(50.00, (float) $item->unit_price);
    }

    public function test_csv_export_returns_streamed_csv(): void
    {
        $today = today()->toDateString();

        ShopDailyProductPrice::query()->create([
            'business_date' => $today,
            'shop_id' => $this->shop1->id,
            'product_id' => $this->productTomato->id,
            'selling_price' => 45.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices.csv', ['date' => $today]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Special Price Report', $content);
        $this->assertStringContainsString('Fresh Tomato', $content);
        $this->assertStringContainsString('Shop Downtown', $content);
        $this->assertStringContainsString('45.00', $content);
    }

    public function test_sidebar_contains_special_price_report_link(): void
    {
        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.reports.special-prices'));

        $response->assertOk();
        $response->assertSee(route('purchaser.reports.special-prices'));
        $response->assertSee('Special Price Report');
    }
}
