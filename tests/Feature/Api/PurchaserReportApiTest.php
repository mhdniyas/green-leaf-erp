<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

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
use Tests\TestCase;

class PurchaserReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_report_routes_require_authentication_and_report_permissions(): void
    {
        $this->getJson(route('api.v1.purchaser.reports.sales-summary'))->assertUnauthorized();

        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole('warehouse_receiver');

        $this->actingAs($warehouseUser)
            ->getJson(route('api.v1.purchaser.reports.sales-summary'))
            ->assertForbidden();
        $this->actingAs($warehouseUser)
            ->getJson(route('api.v1.purchaser.reports.item-summary'))
            ->assertForbidden();

        foreach (['purchaser', 'admin'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)
                ->getJson(route('api.v1.purchaser.reports.sales-summary'))
                ->assertOk();
            $this->actingAs($user)
                ->getJson(route('api.v1.purchaser.reports.item-summary'))
                ->assertOk();
        }
    }

    public function test_sales_summary_aggregates_only_billable_statuses_and_supports_filters(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $alpha = Shop::factory()->create(['name' => 'Alpha Market', 'code' => 'ALPHA']);
        $beta = Shop::factory()->create(['name' => 'Beta Store', 'code' => 'BETA']);

        $this->invoice($alpha, '2026-08-01', 'finalized', 100, 40, 60, 'INV-ALPHA-1');
        $this->invoice($alpha, '2026-08-02', 'paid', 50, 50, 0, 'INV-ALPHA-2');
        $this->invoice($beta, '2026-08-02', 'payment_pending', 80, 20, 60, 'INV-BETA-1');
        $this->invoice(Shop::factory()->create(), '2026-08-02', 'draft', 999, 0, 999, 'INV-IGNORED');

        $response = $this->actingAs($purchaser)->getJson(route('api.v1.purchaser.reports.sales-summary', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-02',
            'per_page' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.totals.total_sales', '230.00')
            ->assertJsonPath('data.totals.paid_amount', '110.00')
            ->assertJsonPath('data.totals.outstanding_amount', '120.00')
            ->assertJsonPath('data.totals.total_shops', 2)
            ->assertJsonPath('data.totals.total_invoices', 3)
            ->assertJsonPath('data.shops.0.shop_name', 'Alpha Market')
            ->assertJsonPath('data.shops.0.total_sales', '150.00')
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.per_page', 1);

        $this->actingAs($purchaser)->getJson(route('api.v1.purchaser.reports.sales-summary', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-02',
            'shop_id' => $alpha->id,
            'status' => 'paid',
            'search' => 'ALPHA-2',
        ]))->assertOk()
            ->assertJsonPath('data.totals.total_sales', '50.00')
            ->assertJsonPath('data.totals.total_invoices', 1)
            ->assertJsonCount(1, 'data.shops');
    }

    public function test_item_summary_uses_pricing_quantities_and_groups_by_product_and_unit(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $category = Category::factory()->create(['name' => 'Report Produce']);
        $potato = Product::factory()->create(['category_id' => $category->id, 'name' => 'Potato', 'sku' => 'POT-1', 'unit' => 'kg']);
        $herb = Product::factory()->create(['category_id' => $category->id, 'name' => 'Herb', 'sku' => 'HERB-1', 'unit' => 'bunch']);
        $invoiceA = $this->invoice($shopA, '2026-08-03', 'finalized', 100, 100, 0, 'ITEM-A');
        $invoiceB = $this->invoice($shopB, '2026-08-03', 'paid', 100, 100, 0, 'ITEM-B');

        $this->line($invoiceA, $potato, 'kg', 'BOX', 20, 0.25, 25);
        $this->line($invoiceB, $potato, 'kg', 'box', 30, 0.50, 50);
        $this->line($invoiceA, $herb, 'bunch', null, 3, 0, 15);

        $ignoredInvoice = $this->invoice(Shop::factory()->create(), '2026-08-03', 'draft', 500, 0, 500, 'ITEM-IGNORED');
        $ignoredProduct = Product::factory()->create(['name' => 'Ignored Product']);
        $this->line($ignoredInvoice, $ignoredProduct, 'piece', 'PIECE', 9, 9, 500);

        $response = $this->actingAs($purchaser)->getJson(route('api.v1.purchaser.reports.item-summary', [
            'date_from' => '2026-08-03',
            'date_to' => '2026-08-03',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.summary.distinct_products', 2)
            ->assertJsonPath('data.summary.product_unit_rows', 2)
            ->assertJsonPath('data.summary.invoice_lines', 3)
            ->assertJsonFragment([
                'product_name' => 'Potato',
                'unit' => 'BOX',
                'billed_quantity' => '0.7500',
                'outgoing_quantity' => '0.7500',
                'line_sales_amount' => '75.00',
                'invoice_line_count' => 2,
                'invoice_count' => 2,
                'shop_count' => 2,
            ])
            ->assertJsonCount(2, 'data.items.1.delivered_shops')
            ->assertJsonFragment([
                'product_name' => 'Herb',
                'unit' => 'BUNCH',
                'billed_quantity' => '3.0000',
            ])
            ->assertJsonMissing(['product_name' => 'Ignored Product']);
    }

    public function test_report_api_range_month_matches_mobile_filters_and_includes_generated_bills(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');

        try {
            $purchaser = User::factory()->create();
            $purchaser->assignRole('purchaser');
            $shop = Shop::factory()->create(['name' => 'Mobile Month Shop', 'code' => 'MOB']);
            $category = Category::factory()->create(['name' => 'Mobile Category']);
            $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Mobile Potato', 'sku' => 'MOB-POT']);
            $invoice = $this->invoice($shop, '2026-08-03', 'generated', 240, 0, 240, 'MOBILE-MONTH-1');

            $this->line($invoice, $product, 'kg', null, 12, 0, 240);

            $this->actingAs($purchaser)
                ->getJson(route('api.v1.purchaser.reports.item-summary', ['range' => 'month']))
                ->assertOk()
                ->assertJsonPath('data.period.date_from', '2026-08-01')
                ->assertJsonPath('data.period.date_to', '2026-08-10')
                ->assertJsonPath('data.summary.invoice_lines', 1)
                ->assertJsonFragment([
                    'product_name' => 'Mobile Potato',
                    'unit' => 'KG',
                    'billed_quantity' => '12.0000',
                ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reports_respect_authenticated_purchaser_category_settings(): void
    {
        $includedCategory = Category::factory()->create(['name' => 'Included Category']);
        $excludedCategory = Category::factory()->create(['name' => 'Excluded Category']);
        $purchaser = User::factory()->create(['assigned_category_ids' => [$includedCategory->id]]);
        $purchaser->assignRole('purchaser');
        $includedShop = Shop::factory()->create(['name' => 'Included Shop', 'code' => 'INC']);
        $excludedShop = Shop::factory()->create(['name' => 'Excluded Shop', 'code' => 'EXC']);
        $includedProduct = Product::factory()->create([
            'category_id' => $includedCategory->id,
            'name' => 'Included Potato',
            'sku' => 'INC-POT',
        ]);
        $excludedProduct = Product::factory()->create([
            'category_id' => $excludedCategory->id,
            'name' => 'Excluded Onion',
            'sku' => 'EXC-ONI',
        ]);
        $includedInvoice = $this->invoice($includedShop, '2026-08-03', 'generated', 100, 0, 100, 'INC-INV');
        $excludedInvoice = $this->invoice($excludedShop, '2026-08-03', 'generated', 90, 0, 90, 'EXC-INV');

        $this->line($includedInvoice, $includedProduct, 'kg', null, 10, 0, 100);
        $this->line($excludedInvoice, $excludedProduct, 'kg', null, 9, 0, 90);

        $this->actingAs($purchaser)
            ->getJson(route('api.v1.purchaser.reports.item-summary', [
                'date_from' => '2026-08-03',
                'date_to' => '2026-08-03',
            ]))->assertOk()
            ->assertJsonPath('data.applied_filters.category_ids', [$includedCategory->id])
            ->assertJsonFragment(['product_name' => 'Included Potato'])
            ->assertJsonMissing(['product_name' => 'Excluded Onion']);

        $this->actingAs($purchaser)
            ->getJson(route('api.v1.purchaser.reports.sales-summary', [
                'date_from' => '2026-08-03',
                'date_to' => '2026-08-03',
            ]))->assertOk()
            ->assertJsonPath('data.totals.total_sales', '100.00')
            ->assertJsonFragment(['shop_name' => 'Included Shop'])
            ->assertJsonMissing(['shop_name' => 'Excluded Shop']);
    }

    public function test_sales_summary_totals_only_include_assigned_lines_from_mixed_invoices(): void
    {
        $includedCategory = Category::factory()->create();
        $excludedCategory = Category::factory()->create();
        $purchaser = User::factory()->create(['assigned_category_ids' => [$includedCategory->id]]);
        $purchaser->assignRole('purchaser');
        $shop = Shop::factory()->create(['name' => 'Mixed Shop']);
        $includedProduct = Product::factory()->create(['category_id' => $includedCategory->id]);
        $excludedProduct = Product::factory()->create(['category_id' => $excludedCategory->id]);
        $invoice = $this->invoice($shop, '2026-08-03', 'generated', 150, 0, 150, 'MIXED-INV');

        $this->line($invoice, $includedProduct, 'kg', null, 5, 0, 50);
        $this->line($invoice, $excludedProduct, 'kg', null, 10, 0, 100);

        $this->actingAs($purchaser)
            ->getJson(route('api.v1.purchaser.reports.sales-summary', [
                'date_from' => '2026-08-03',
                'date_to' => '2026-08-03',
            ]))
            ->assertOk()
            ->assertJsonPath('data.totals.total_sales', '50.00')
            ->assertJsonPath('data.totals.outstanding_amount', '50.00')
            ->assertJsonPath('data.shops.0.total_sales', '50.00');
    }

    public function test_report_request_validates_dates_status_shop_and_pagination(): void
    {
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $this->actingAs($purchaser)->getJson(route('api.v1.purchaser.reports.sales-summary', [
            'date_from' => '2026-08-10',
            'date_to' => '2026-08-01',
            'status' => 'draft',
            'shop_id' => 999999,
            'per_page' => 101,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to', 'status', 'shop_id', 'per_page']);

        $this->actingAs($purchaser)->getJson(route('api.v1.purchaser.reports.sales-summary', [
            'date_from' => '2025-01-01',
            'date_to' => '2026-08-10',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_purchaser_can_read_and_update_category_preferences(): void
    {
        $first = Category::factory()->create(['name' => 'First Preference']);
        $second = Category::factory()->create(['name' => 'Second Preference']);
        $purchaser = User::factory()->create(['assigned_category_ids' => [$first->id]]);
        $purchaser->assignRole('purchaser');

        $this->actingAs($purchaser)
            ->getJson(route('api.v1.purchaser.settings.show'))
            ->assertOk()
            ->assertJsonPath('data.assigned_category_ids', [$first->id])
            ->assertJsonFragment(['name' => 'Second Preference']);

        $this->actingAs($purchaser)
            ->postJson(route('api.v1.purchaser.settings.update'), [
                'category_ids' => [$second->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_category_ids', [$second->id]);

        $this->assertSame([$second->id], $purchaser->fresh()->assignedCategoryIds());

        $warehouse = User::factory()->create();
        $warehouse->assignRole('warehouse_receiver');
        $this->actingAs($warehouse)
            ->getJson(route('api.v1.purchaser.settings.show'))
            ->assertForbidden();
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
