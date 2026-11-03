<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsV1GradesReportTopSummaryRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Carbon::setTestNow('2026-10-23');

        $this->admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@greenleaf.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_grade_selector_renders_compact_horizontal_tabs(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'period' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Grade A');
        $response->assertSee('Grade B');
        $response->assertSee('Grade C');
        $response->assertSee('Damage');

        // Grade A should be active by default
        $response->assertSee('bg-emerald-600 text-white', false);

        // Switch to Grade B
        $responseB = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'grade' => 'B',
            'period' => 'today',
        ]));

        $responseB->assertOk();
        $responseB->assertSee('bg-blue-600 text-white', false);
    }

    public function test_summary_cards_display_unit_table_without_concatenated_string(): void
    {
        $productKg = Product::create([
            'name' => 'Tomato KG',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $productBox = Product::create([
            'name' => 'Apple Box',
            'sku' => 'APP-001',
            'unit' => 'box',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        StockBatch::create([
            'product_id' => $productKg->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'reference' => 'BAT-KG-1',
            'purchase_grade' => 'A',
            'received_at' => '2026-10-23',
            'total_kg' => 150.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        StockBatch::create([
            'product_id' => $productBox->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'reference' => 'BAT-BOX-1',
            'purchase_grade' => 'A',
            'received_at' => '2026-10-23',
            'total_kg' => 25.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'grade' => 'A',
            'period' => 'today',
        ]));

        $response->assertOk();

        // Must see both KG and BOX in the unit-table
        $response->assertSee('KG');
        $response->assertSee('150.0');
        $response->assertSee('BOX');
        $response->assertSee('25.0');

        // Must NOT see the old concatenated multi-unit string with middle dots
        $response->assertDontSee('150.0 KG · 25.0 BOX');
        $response->assertDontSee('25.0 BOX · 150.0 KG');
    }

    public function test_summary_cards_with_four_units_show_expansion_toggle(): void
    {
        $units = ['kg', 'box', 'piece', 'bunch'];
        foreach ($units as $idx => $u) {
            $p = Product::create([
                'name' => "Product {$u}",
                'sku' => "SKU-{$u}",
                'unit' => $u,
                'category_id' => $this->category->id,
                'is_active' => true,
            ]);

            StockBatch::create([
                'product_id' => $p->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->admin->id,
                'reference' => "BAT-U-{$idx}",
                'purchase_grade' => 'A',
                'received_at' => '2026-10-23',
                'total_kg' => 10.0 * ($idx + 1),
                'cost_per_kg' => 15.0,
                'status' => BatchStatus::Sorted,
                'warehouse_receive_pending' => false,
                'warehouse_confirmed_at' => now(),
                'warehouse_confirmed_by' => $this->admin->id,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'grade' => 'A',
            'period' => 'today',
        ]));

        $response->assertOk();
        // Since there are 4 units and top 3 are shown directly, expansion toggle must show "+1 more units"
        $response->assertSee('+1 more units');
    }

    public function test_pending_cost_reconciliation_warning_renders_compactly(): void
    {
        $product = Product::create([
            'name' => 'Garlic',
            'sku' => 'GAR-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $negBatch = StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'reference' => 'NEG-20261023-'.$product->id.'-A',
            'purchase_grade' => 'A',
            'received_at' => '2026-10-23',
            'total_kg' => 0,
            'cost_per_kg' => 0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $negBatch->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 12.0,
            'cost_per_unit' => 0.00,
            'notes' => 'Fast morning loadout shortfall; negative stock allowed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports-v1.grades', [
            'product' => $product->id,
            'period' => 'this_month',
        ]));

        $response->assertOk();
        $response->assertSee('Pending Cost Reconciliation');
        $response->assertSee('12.0 kg pending');
    }
}
