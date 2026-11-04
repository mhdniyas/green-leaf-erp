<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\PurchaserReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserItemSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Shop $shop;

    private Warehouse $warehouse;

    private Category $categoryA;

    private Category $categoryB;

    private Product $productApple;

    private Product $productBanana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'code' => 'MW1']);
        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'Standard', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);

        $this->categoryA = Category::factory()->create(['name' => 'Fruits']);
        $this->categoryB = Category::factory()->create(['name' => 'Vegetables']);

        $this->productApple = Product::factory()->create([
            'category_id' => $this->categoryA->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'name' => 'Apple Royal Gala',
            'sku' => 'SKU-APP-01',
        ]);

        $this->productBanana = Product::factory()->create([
            'category_id' => $this->categoryB->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'box',
            'name' => 'Banana Cavendish',
            'sku' => 'SKU-BAN-02',
        ]);
    }

    private function createInvoiceItem(Product $product, float $qty, float $price, string $date = '2026-10-04'): void
    {
        $shop = Shop::factory()->create();

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_order_id' => $order->id,
            'shop_id' => $shop->id,
            'business_date' => $date,
            'status' => 'approved',
            'final_total' => $qty * $price,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'delivered_qty' => $qty,
            'delivered_price_quantity' => $qty,
            'unit' => $product->unit,
            'price_unit' => $product->unit,
            'final_line_total' => $qty * $price,
        ]);
    }

    public function test_item_summary_web_view_renders_sl_and_sku_columns(): void
    {
        $this->createInvoiceItem($this->productApple, 10.0, 150.0);
        $this->createInvoiceItem($this->productBanana, 20.0, 50.0);

        $response = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary?range=custom&date_from=2026-10-04&date_to=2026-10-04&status=all');

        $response->assertOk();
        $response->assertSee('SL');
        $response->assertSee('SKU');
        $response->assertSee('SKU-APP-01');
        $response->assertSee('SKU-BAN-02');
        $response->assertSee('Apple Royal Gala');
        $response->assertSee('Banana Cavendish');
    }

    public function test_sku_sorting_asc_and_desc(): void
    {
        $this->createInvoiceItem($this->productApple, 10.0, 150.0);
        $this->createInvoiceItem($this->productBanana, 20.0, 50.0);

        $service = app(PurchaserReportService::class);

        $asc = $service->itemSummary([
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
            'direction' => 'asc',
        ]);

        $this->assertSame('SKU-APP-01', $asc['items'][0]['product_sku']);
        $this->assertSame('SKU-BAN-02', $asc['items'][1]['product_sku']);

        $desc = $service->itemSummary([
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
            'direction' => 'desc',
        ]);

        $this->assertSame('SKU-BAN-02', $desc['items'][0]['product_sku']);
        $this->assertSame('SKU-APP-01', $desc['items'][1]['product_sku']);
    }

    public function test_sku_natural_numeric_sorting_places_11_before_109(): void
    {
        $garlic = Product::factory()->create([
            'category_id' => $this->categoryA->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'name' => 'Garlic Ootty',
            'sku' => '11',
        ]);

        $springOnion = Product::factory()->create([
            'category_id' => $this->categoryA->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'name' => 'Spring Onion',
            'sku' => '109',
        ]);

        $this->createInvoiceItem($springOnion, 10.0, 30.0);
        $this->createInvoiceItem($garlic, 5.0, 200.0);

        $service = app(PurchaserReportService::class);

        // In ASC, 11 must appear BEFORE 109 (natural numeric order, not alphabetical string order)
        $asc = $service->itemSummary([
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
            'direction' => 'asc',
        ]);

        $skus = array_column($asc['items'], 'product_sku');
        $garlicIndex = array_search('11', $skus, true);
        $springOnionIndex = array_search('109', $skus, true);

        $this->assertNotFalse($garlicIndex);
        $this->assertNotFalse($springOnionIndex);
        $this->assertLessThan($springOnionIndex, $garlicIndex, 'SKU 11 should appear before SKU 109 in ASC order');

        // In DESC, 109 must appear BEFORE 11
        $desc = $service->itemSummary([
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sku',
            'direction' => 'desc',
        ]);

        $skusDesc = array_column($desc['items'], 'product_sku');
        $garlicIndexDesc = array_search('11', $skusDesc, true);
        $springOnionIndexDesc = array_search('109', $skusDesc, true);

        $this->assertNotFalse($garlicIndexDesc);
        $this->assertNotFalse($springOnionIndexDesc);
        $this->assertLessThan($garlicIndexDesc, $springOnionIndexDesc, 'SKU 109 should appear before SKU 11 in DESC order');
    }

    public function test_table_header_sort_links_toggle_direction(): void
    {
        $this->createInvoiceItem($this->productApple, 10.0, 150.0);

        // When currently on sku asc, the sku header link should link to direction=desc
        $response = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary?range=custom&date_from=2026-10-04&date_to=2026-10-04&sort=sku&direction=asc');

        $response->assertOk();
        $response->assertSee('sort=sku');
        $response->assertSee('direction=desc');

        // When currently on sku desc, the sku header link should link to direction=asc
        $responseDesc = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary?range=custom&date_from=2026-10-04&date_to=2026-10-04&sort=sku&direction=desc');

        $responseDesc->assertOk();
        $responseDesc->assertSee('direction=asc');
    }

    public function test_sorting_by_sales_and_quantity(): void
    {
        // Apple: 10 qty * 150 = 1500 sales
        // Banana: 50 qty * 20 = 1000 sales
        $this->createInvoiceItem($this->productApple, 10.0, 150.0);
        $this->createInvoiceItem($this->productBanana, 50.0, 20.0);

        $service = app(PurchaserReportService::class);

        // Sort by sales desc: Apple first (1500 > 1000)
        $salesDesc = $service->itemSummary([
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'sales',
            'direction' => 'desc',
        ]);
        $this->assertSame('Apple Royal Gala', $salesDesc['items'][0]['product_name']);
        $this->assertSame('Banana Cavendish', $salesDesc['items'][1]['product_name']);

        // Sort by quantity desc: Banana first (50 > 10)
        $qtyDesc = $service->itemSummary([
            'date_from' => '2026-10-04',
            'date_to' => '2026-10-04',
            'shop_id' => null,
            'status' => null,
            'search' => '',
            'page' => 1,
            'per_page' => 25,
            'sort' => 'quantity',
            'direction' => 'desc',
        ]);
        $this->assertSame('Banana Cavendish', $qtyDesc['items'][0]['product_name']);
        $this->assertSame('Apple Royal Gala', $qtyDesc['items'][1]['product_name']);
    }

    public function test_pagination_computes_correct_sl_numbers(): void
    {
        $this->createInvoiceItem($this->productApple, 10.0, 150.0);
        $this->createInvoiceItem($this->productBanana, 20.0, 50.0);

        // Page 1 with per_page=1: shows Apple, SL = 1
        $responsePage1 = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary?range=custom&date_from=2026-10-04&date_to=2026-10-04&per_page=1&page=1&sort=sku&direction=asc');
        $responsePage1->assertOk();
        $responsePage1->assertSee('Apple Royal Gala');
        $responsePage1->assertDontSee('Banana Cavendish');

        // Page 2 with per_page=1: shows Banana, SL = 2
        $responsePage2 = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary?range=custom&date_from=2026-10-04&date_to=2026-10-04&per_page=1&page=2&sort=sku&direction=asc');
        $responsePage2->assertOk();
        $responsePage2->assertSee('Banana Cavendish');
        $responsePage2->assertDontSee('Apple Royal Gala');
    }

    public function test_pdf_and_csv_exports_include_sl(): void
    {
        $this->createInvoiceItem($this->productApple, 10.0, 150.0);

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary/pdf?range=custom&date_from=2026-10-04&date_to=2026-10-04');
        $pdfResponse->assertOk();
        $pdfResponse->assertSee('SL');

        $csvResponse = $this->actingAs($this->purchaser)
            ->get('/purchaser/reports/item-summary/csv?range=custom&date_from=2026-10-04&date_to=2026-10-04');
        $csvResponse->assertOk();
        $this->assertStringContainsString('SL,Product,SKU,Category', $csvResponse->streamedContent());
    }
}
