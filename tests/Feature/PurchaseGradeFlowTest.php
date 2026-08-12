<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\PurchaseGradePrice;
use App\Models\PurchaserCart;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaseGradePriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseGradeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_b_catalog_item_creates_direct_grade_b_cart_and_returns_to_b_flow(): void
    {
        Role::findOrCreate('purchaser');
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $product = Product::factory()->create(['unit' => 'kg']);
        $businessDate = now()->toDateString();

        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => $businessDate,
            'grade' => 'B',
            'purchase_price' => 18.25,
            'status' => 'approved',
        ]);

        $this->actingAs($purchaser)
            ->post(route('purchaser.cart-items.store'), [
                'business_date' => $businessDate,
                'product_id' => $product->id,
                'purchase_grade' => 'B',
                'quantity' => 5,
                'purchase_source' => 'green_leaf_direct_purchase',
            ])
            ->assertRedirect(route('purchaser.b-grade', ['date' => $businessDate]));

        $cart = PurchaserCart::query()->sole();
        $this->assertSame('B', $cart->purchase_grade);
        $this->assertSame('green_leaf_direct_purchase', $cart->purchase_source);
        $this->assertDatabaseHas('purchaser_cart_items', [
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'B',
            'quantity' => 5,
            'unit_price' => 18.25,
            'is_extra_purchase' => true,
        ]);
    }

    public function test_grade_b_bulk_purchase_keeps_the_grade_b_context_and_shows_catalog_products(): void
    {
        Role::findOrCreate('purchaser');
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $product = Product::factory()->create(['name' => 'B Grade Bulk Tomato']);

        $this->actingAs($purchaser)
            ->get(route('purchaser.bulk-buy', [
                'date' => now()->toDateString(),
                'purchase_grade' => 'B',
            ]))
            ->assertOk()
            ->assertSee('B Grade Bulk Purchase')
            ->assertSee($product->name)
            ->assertSee('Add-ons');
    }

    public function test_purchaser_can_copy_approved_grade_a_prices_to_grade_b(): void
    {
        Role::findOrCreate('purchaser');
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        $product = Product::factory()->create();

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-08-12',
            'purchase_price' => 24.50,
            'price_a' => 31.75,
            'price_b' => 33.00,
            'price_c' => 35.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $purchaser->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($purchaser)
            ->post(route('purchaser.purchase-grade-prices.copy-a-to-b'), ['business_date' => '2026-08-12'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_grade_prices', [
            'product_id' => $product->id,
            'business_date' => '2026-08-12 00:00:00',
            'grade' => 'B',
            'purchase_price' => 31.75,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);
    }

    public function test_grade_b_price_is_resolved_without_falling_back_to_grade_a(): void
    {
        $product = Product::factory()->create();
        PurchaseGradePrice::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-08-12',
            'grade' => 'B',
            'purchase_price' => 22.50,
            'status' => 'approved',
        ]);

        $resolver = app(PurchaseGradePriceResolver::class);

        $this->assertSame(22.5, $resolver->resolve($product->id, '2026-08-12', 'B', 99.0));

        $otherProduct = Product::factory()->create();
        $this->expectException(ValidationException::class);
        $resolver->resolve($otherProduct->id, '2026-08-12', 'B', 99.0);
    }

    public function test_grade_b_stock_consumption_never_uses_grade_a_stock(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $gradeABatch = $this->createBatch($product, $user, 'A');
        $gradeBBatch = $this->createBatch($product, $user, 'B');

        foreach ([[$gradeABatch, 'A'], [$gradeBBatch, 'B']] as [$batch, $grade]) {
            StockMovement::query()->create([
                'batch_id' => $batch->id,
                'product_id' => $product->id,
                'created_by' => $user->id,
                'grade' => $grade,
                'type' => StockMovementType::In->value,
                'quantity' => 10,
                'cost_per_unit' => 20,
            ]);
        }

        app(StockLedgerService::class)->consumeSortedStockForProduct(
            $product->id,
            4,
            $user->id,
            StockMovementType::Out,
            'Grade B allocation test',
            grade: ProductGrade::GradeB,
        );

        $this->assertSame(10.0, app(StockLedgerService::class)->availableSortedStockForProduct($product->id, grade: ProductGrade::GradeA));
        $this->assertSame(6.0, app(StockLedgerService::class)->availableSortedStockForProduct($product->id, grade: ProductGrade::GradeB));
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $gradeBBatch->id, 'grade' => 'B', 'type' => StockMovementType::Out->value]);
        $this->assertDatabaseMissing('stock_movements', ['batch_id' => $gradeABatch->id, 'grade' => 'A', 'type' => StockMovementType::Out->value]);
    }

    public function test_fixed_grade_b_batch_cannot_enter_the_sorting_flow(): void
    {
        $batch = $this->createBatch(Product::factory()->create(), User::factory()->create(), 'B');
        $batch->update(['status' => 'pending', 'grading_mode' => 'fixed_purchase_grade']);

        $this->assertFalse($batch->fresh()->canBeSorted());
    }

    private function createBatch(Product $product, User $user, string $grade): StockBatch
    {
        return StockBatch::query()->create([
            'product_id' => $product->id,
            'created_by' => $user->id,
            'reference' => 'TEST-'.$grade.'-'.str()->uuid(),
            'received_at' => '2026-08-12',
            'total_kg' => 10,
            'cost_per_kg' => 20,
            'status' => 'sorted',
            'purchase_grade' => $grade,
            'grading_mode' => 'fixed_purchase_grade',
            'warehouse_receive_pending' => false,
        ]);
    }
}
