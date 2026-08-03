<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOrderItemGoldenRuleTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductUnit $bunchUnit;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a product with base unit KG
        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'Coriander',
            'sku' => 'COR-001',
            'category_id' => $category->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        // Create a secondary unit: BUNCH (1 bunch = 1.8 kg)
        $this->bunchUnit = ProductUnit::create([
            'product_id' => $this->product->id,
            'unit' => 'bunch',
            'label' => 'BUNCH',
            'conversion_to_base' => 1.8,
            'is_base' => false,
            'is_orderable' => true,
        ]);
    }

    /** @test */
    public function it_maintains_golden_rule_base_units_are_source_of_truth(): void
    {
        // This test verifies the Golden Rule:
        // All quantities are stored in base units, display units are calculated

        // Shop orders 25 bunches of coriander
        $orderQuantityInBunches = 25.0;
        $conversionFactor = 1.8; // kg per bunch

        // Create order item (only base unit is stored)
        $item = new ShopOrderItem([
            'product_id' => $this->product->id,
            'unit' => 'kg', // Base unit
            'requested_product_unit_id' => $this->bunchUnit->id,
            'requested_unit_conversion_to_base' => $conversionFactor,
            'requested_qty' => $orderQuantityInBunches * $conversionFactor, // 45 kg
            'approved_qty' => $orderQuantityInBunches * $conversionFactor, // 45 kg
        ]);

        // Verify base quantities are stored
        $this->assertEquals(45.0, $item->requested_qty);
        $this->assertEquals(45.0, $item->approved_qty);

        // Verify display quantities are calculated correctly
        $this->assertEquals(25.0, $item->requestedQuantityInOrderUnit());
        $this->assertEquals(25.0, $item->approvedQuantityInOrderUnit());

        // Warehouse loads actual weight
        $item->actual_weight = 44.1; // Slightly less than ordered

        // Verify loaded quantity is calculated from base unit
        $this->assertEquals(24.5, $item->loadedQuantityInOrderUnit()); // 44.1 / 1.8

        // Golden Rule verified:
        // ✅ Storage: Base units only (kg)
        // ✅ Display: Calculated on-the-fly (bunches)
        // ✅ Single source of truth: base quantity
    }
}
