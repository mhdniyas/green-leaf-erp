<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PurchaserReportWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-10 12:00:00');
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_web_report_routes_require_authentication_and_permissions(): void
    {
        $this->get(route('purchaser.reports.sales-summary'))->assertRedirect(route('login'));

        $warehouse = User::factory()->create();
        $warehouse->assignRole('warehouse_receiver');

        $this->actingAs($warehouse)->get(route('purchaser.reports.sales-summary'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
        $this->actingAs($warehouse)->get(route('purchaser.reports.item-summary'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
        $this->actingAs($warehouse)->get(route('purchaser.reports.sales-summary.csv'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
        $this->actingAs($warehouse)->get(route('purchaser.reports.item-summary.pdf'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        foreach (['purchaser', 'admin'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get(route('purchaser.reports.sales-summary'))->assertOk();
            $this->actingAs($user)->get(route('purchaser.reports.item-summary'))->assertOk();
        }
    }

    public function test_sales_page_renders_service_totals_filters_and_report_navigation(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $shop = Shop::factory()->create(['name' => 'Green Shop', 'code' => 'GREEN']);
        $this->invoice($shop, '2026-08-09', 'finalized', 125.50, 100, 25.50, 'WEB-SALE-1');
        $this->invoice($shop, '2026-08-10', 'paid', 74.50, 74.50, 0, 'WEB-SALE-2');

        $response = $this->actingAs($purchaser)->get(route('purchaser.reports.sales-summary', [
            'range' => 'custom',
            'date_from' => '2026-08-09',
            'date_to' => '2026-08-10',
        ]));

        $response->assertOk()
            ->assertSee('Sales Summary')
            ->assertSee('₹200.00')
            ->assertSee('₹174.50')
            ->assertSee('₹25.50')
            ->assertSee('Green Shop')
            ->assertSee('2')
            ->assertSee('Today')
            ->assertSee('Yesterday')
            ->assertSee('This Week')
            ->assertSee('This Month')
            ->assertSee('Custom')
            ->assertSee('Generated')
            ->assertSee('Delivery review')
            ->assertSee('Item Summary')
            ->assertSee('Settings')
            ->assertSee(route('purchaser.settings'), false)
            ->assertSee(route('purchaser.reports.sales-summary.csv'), false)
            ->assertSee(route('purchaser.reports.sales-summary.excel'), false)
            ->assertSee(route('purchaser.reports.sales-summary.pdf'), false)
            ->assertSee(route('purchaser.reports.sales-summary'), false)
            ->assertSee(route('purchaser.reports.item-summary'), false);
    }

    public function test_date_presets_and_custom_range_use_operational_dates(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $shop = Shop::factory()->create();
        $this->invoice($shop, '2026-08-09', 'paid', 40, 40, 0, 'YESTERDAY-1');

        $this->actingAs($purchaser)
            ->get(route('purchaser.reports.sales-summary', ['range' => 'today']))
            ->assertOk()
            ->assertSee('₹0.00')
            ->assertDontSee('YESTERDAY-1');

        $this->actingAs($purchaser)
            ->get(route('purchaser.reports.sales-summary', ['range' => 'yesterday']))
            ->assertOk()
            ->assertSee('₹40.00')
            ->assertSee('09 Aug 2026');

        $this->actingAs($purchaser)
            ->get(route('purchaser.reports.sales-summary', [
                'range' => 'custom',
                'date_from' => '2026-08-09',
                'date_to' => '2026-08-09',
            ]))->assertOk()
            ->assertSee('₹40.00');
    }

    public function test_item_page_renders_pricing_quantity_and_responsive_presentations(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $shop = Shop::factory()->create();
        $secondShop = Shop::factory()->create();
        $invoice = $this->invoice($shop, '2026-08-10', 'finalized', 108, 80, 28, 'WEB-ITEM-1');
        $secondInvoice = $this->invoice($secondShop, '2026-08-10', 'paid', 32, 32, 0, 'WEB-ITEM-2');
        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Potato Report', 'sku' => 'POT-WEB']);
        $greens = Product::factory()->create(['category_id' => $category->id, 'name' => 'Curry Leaf', 'sku' => 'CURRY-WEB']);
        $pieceProduct = Product::factory()->create(['category_id' => $category->id, 'name' => 'Tender Coconut', 'sku' => 'COCO-WEB']);

        $this->line($invoice, $product, 'kg', null, 12.75, 0, 51);
        $this->line($secondInvoice, $product, 'kg', 'BOX', 18, 0.50, 32);
        $this->line($invoice, $greens, 'bunch', null, 4, 0, 20);
        $this->line($invoice, $pieceProduct, 'piece', null, 5, 0, 5);

        $this->actingAs($purchaser)
            ->get(route('purchaser.reports.item-summary'))
            ->assertOk()
            ->assertSee('Item Summary')
            ->assertSee('Potato Report')
            ->assertSee('POT-WEB')
            ->assertSee('12.75 KG')
            ->assertSee('0.5 BOX')
            ->assertSee('Curry Leaf')
            ->assertSee('4 BUNCH')
            ->assertSee('Tender Coconut')
            ->assertSee('5 PIECE')
            ->assertSee('₹51.00')
            ->assertSee('₹32.00')
            ->assertSee('Settings')
            ->assertSee('hidden overflow-x-auto md:block', false)
            ->assertSee('divide-y divide-slate-200 md:hidden', false);
    }

    public function test_sales_and_item_reports_export_csv_excel_and_printable_pdf(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $shop = Shop::factory()->create(['name' => 'Export Shop', 'code' => 'EXP']);
        $invoice = $this->invoice($shop, '2026-08-10', 'paid', 125.75, 100.25, 25.50, 'EXPORT-1');
        $category = Category::factory()->create(['name' => 'Export Category']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Export Potato', 'sku' => 'EXP-POT']);

        $this->line($invoice, $product, 'kg', 'BOX', 20, 0.50, 125.75);

        $query = [
            'range' => 'custom',
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-10',
        ];

        $salesCsv = $this->actingAs($purchaser)
            ->get(route('purchaser.reports.sales-summary.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Sales Summary', $salesCsv);
        $this->assertStringContainsString('"Export Shop",EXP,1,125.75,100.25,25.50', $salesCsv);

        $itemCsv = $this->actingAs($purchaser)
            ->get(route('purchaser.reports.item-summary.csv', $query))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Item Summary', $itemCsv);
        $this->assertStringContainsString('"Export Potato",EXP-POT,"Export Category",BOX,0.5000,125.75,1,1,1', $itemCsv);

        $this->actingAs($purchaser)
            ->get(route('purchaser.reports.sales-summary.pdf', $query))
            ->assertOk()
            ->assertSee('Purchaser Sales Summary')
            ->assertSee('Rs. 125.75')
            ->assertSee('Export Shop');

        $this->actingAs($purchaser)
            ->get(route('purchaser.reports.item-summary.pdf', $query))
            ->assertOk()
            ->assertSee('Purchaser Item Summary')
            ->assertSee('Export Potato')
            ->assertSee('0.5');

        Excel::fake();

        $this->actingAs($purchaser)->get(route('purchaser.reports.sales-summary.excel', $query));
        Excel::assertDownloaded('purchaser-sales-summary-2026-08-10_2026-08-10.xlsx');

        $this->actingAs($purchaser)->get(route('purchaser.reports.item-summary.excel', $query));
        Excel::assertDownloaded('purchaser-item-summary-2026-08-10_2026-08-10.xlsx');
    }

    public function test_custom_range_validation_rejects_invalid_dates(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $this->actingAs($purchaser)
            ->from(route('purchaser.reports.sales-summary'))
            ->get(route('purchaser.reports.sales-summary', [
                'range' => 'custom',
                'date_from' => '2026-08-10',
                'date_to' => '2026-08-01',
            ]))->assertRedirect(route('purchaser.reports.sales-summary'))
            ->assertSessionHasErrors('date_to');
    }

    private function invoice(
        Shop $shop,
        string $date,
        string $status,
        float $total,
        float $paid,
        float $balance,
        string $number,
    ): ShopInvoice {
        $creator = User::factory()->create();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'created_by' => $creator->id,
        ]);

        return ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $number,
            'business_date' => $date,
            'status' => $status,
            'delivery_status' => 'pending',
            'payment_status' => $status === 'paid' ? 'paid' : 'unpaid',
            'subtotal' => $total,
            'final_total' => $total,
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'generated_by' => $creator->id,
        ]);
    }

    private function line(
        ShopInvoice $invoice,
        Product $product,
        string $unit,
        ?string $priceUnit,
        float $deliveredQuantity,
        float $deliveredPriceQuantity,
        float $lineTotal,
    ): ShopInvoiceItem {
        return ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $unit,
            'price_unit' => $priceUnit,
            'approved_qty' => $deliveredQuantity,
            'price_quantity' => $deliveredPriceQuantity,
            'delivered_qty' => $deliveredQuantity,
            'delivered_price_quantity' => $deliveredPriceQuantity,
            'unit_price' => 1,
            'line_subtotal' => $lineTotal,
            'final_line_total' => $lineTotal,
        ]);
    }
}
