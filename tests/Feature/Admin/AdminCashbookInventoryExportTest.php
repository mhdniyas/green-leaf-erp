<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Exports\InventoryAdvanceOnlyExport;
use App\Exports\InventoryBusinessDayExport;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCashbookInventoryExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Product $tomato;

    private Product $potato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->tomato = Product::create([
            'category_id' => $category->id,
            'name' => 'Tomato H',
            'sku' => '1',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'base_price' => 20,
            'is_active' => true,
        ]);

        $this->potato = Product::create([
            'category_id' => $category->id,
            'name' => 'Potato Agra',
            'sku' => '5',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'base_price' => 25,
            'is_active' => true,
        ]);
    }

    public function test_inventory_page_contains_pdf_and_excel_export_buttons_and_admin_link(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory'));

        $response->assertOk();
        $response->assertSee('exportPdfUrl', false);
        $response->assertSee('exportExcelUrl', false);
        $response->assertSee('PDF');
        $response->assertSee('Excel');

        $adminOverview = $this->actingAs($this->admin)->get(route('admin.overview'));
        $adminOverview->assertOk();
        $adminOverview->assertSee('Advance Warehouse Receive');
    }

    public function test_export_pdf_returns_valid_pdf_with_business_day_title(): void
    {
        $date = '2026-11-03';

        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_at' => $date.' 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 225,
            'received_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory.export-pdf', [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_export_excel_returns_downloadable_excel_response(): void
    {
        $date = '2026-11-03';

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory.export-excel', [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), "business-day-inventory-{$date}.xlsx")
        );
    }

    public function test_format_query_param_delegates_to_pdf_and_excel(): void
    {
        $date = '2026-11-03';

        $pdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $date,
            'format' => 'pdf',
        ]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));

        $excelResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $date,
            'format' => 'excel',
        ]));
        $excelResponse->assertOk();
        $this->assertTrue(
            str_contains((string) $excelResponse->headers->get('content-disposition'), "business-day-inventory-{$date}.xlsx")
        );
    }

    public function test_inventory_business_day_export_data_structure_has_only_four_columns(): void
    {
        $rows = [
            [
                'product_code' => '1',
                'sku' => '1',
                'product_name' => 'Tomato H',
                'formatted_advance' => '225 kg',
            ],
            [
                'product_code' => '5',
                'sku' => '5',
                'product_name' => 'Potato Agra',
                'formatted_advance' => '175 kg',
            ],
        ];

        $export = new InventoryBusinessDayExport($rows, '03 Nov 2026', 'Main Warehouse');
        $this->assertSame('Business Day', $export->title());

        $sheetArray = $export->array();
        $this->assertSame(['Business Day'], $sheetArray[0]);
        $this->assertSame(['Date: 03 Nov 2026 | Warehouse: Main Warehouse'], $sheetArray[1]);
        $this->assertSame([], $sheetArray[2]);
        $this->assertSame(['#', 'CODE', 'PRODUCT', 'ADVANCE'], $sheetArray[3]);

        $this->assertSame([1, '1', 'Tomato H', '225 kg'], $sheetArray[4]);
        $this->assertSame([2, '5', 'Potato Agra', '175 kg'], $sheetArray[5]);
        $this->assertCount(4, $sheetArray[3]);
        $this->assertCount(4, $sheetArray[4]);
        $this->assertCount(4, $sheetArray[5]);
    }

    public function test_export_advance_only_pdf_returns_valid_pdf_with_advance_only_title(): void
    {
        $date = '2026-11-03';

        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_at' => $date.' 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 225,
            'received_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory.export-advance-pdf', [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_export_advance_only_excel_returns_downloadable_excel_response(): void
    {
        $date = '2026-11-03';

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory.export-advance-excel', [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), "business-day-advance-only-{$date}.xlsx")
        );
    }

    public function test_advance_only_query_param_delegates_to_advance_pdf_and_excel(): void
    {
        $date = '2026-11-03';

        $pdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $date,
            'export' => 'advance-pdf',
        ]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));

        $excelResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $date,
            'export' => 'advance-excel',
        ]));
        $excelResponse->assertOk();
        $this->assertTrue(
            str_contains((string) $excelResponse->headers->get('content-disposition'), "business-day-advance-only-{$date}.xlsx")
        );
    }

    public function test_inventory_advance_only_export_data_structure_has_five_columns_as_in_screenshot(): void
    {
        $rows = [
            [
                'product_code' => '166',
                'sku' => '166',
                'product_name' => 'Banana Robusta',
                'formatted_advance' => '270 kg',
                'formatted_bill' => '270 kg',
            ],
            [
                'product_code' => '5',
                'sku' => '5',
                'product_name' => 'Potato Agra',
                'formatted_advance' => '175 kg',
                'formatted_bill' => '0 kg',
            ],
        ];

        $export = new InventoryAdvanceOnlyExport($rows, '03 Nov 2026', 'Main Warehouse');
        $this->assertSame('Advance Only', $export->title());

        $sheetArray = $export->array();
        $this->assertSame(['Business Day — Advance Only'], $sheetArray[0]);
        $this->assertSame(['Date: 03 Nov 2026 | Warehouse: Main Warehouse'], $sheetArray[1]);
        $this->assertSame([], $sheetArray[2]);
        $this->assertSame(['#', 'CODE', 'PRODUCT', 'ADVANCE', 'BILL'], $sheetArray[3]);

        $this->assertSame([1, '166', 'Banana Robusta', '270 kg', '270 kg'], $sheetArray[4]);
        $this->assertSame([2, '5', 'Potato Agra', '175 kg', '0 kg'], $sheetArray[5]);
        $this->assertCount(5, $sheetArray[3]);
        $this->assertCount(5, $sheetArray[4]);
        $this->assertCount(5, $sheetArray[5]);
    }
}
