<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmptyInventoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool, float}> */
    public static function unsortedBatches(): array
    {
        return [
            'confirmed' => [false, 0.0],
            'awaiting receipt' => [true, 0.0],
            'confirmed with write-off' => [false, 10.0],
            'awaiting receipt with write-off' => [true, 10.0],
        ];
    }

    #[DataProvider('unsortedBatches')]
    public function test_empty_warehouse_clears_sorted_and_unsorted_stock_without_recreating_it(bool $receiptPending, float $writtenOff): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $pendingBatch = StockBatch::factory()->pending()->create([
            'warehouse_id' => Warehouse::factory(),
            'warehouse_receive_pending' => $receiptPending,
            'total_kg' => 50,
        ]);
        $sortedBatch = StockBatch::factory()->sorted()->warehouseConfirmed()->create([
            'warehouse_id' => Warehouse::factory(),
        ]);
        StockMovement::factory()->create([
            'product_id' => $sortedBatch->product_id,
            'batch_id' => $sortedBatch->id,
            'warehouse_id' => $sortedBatch->warehouse_id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 25,
        ]);
        if ($writtenOff > 0) {
            StockMovement::factory()->create([
                'product_id' => $pendingBatch->product_id,
                'batch_id' => $pendingBatch->id,
                'warehouse_id' => $pendingBatch->warehouse_id,
                'grade' => ProductGrade::Unsorted,
                'type' => StockMovementType::Wastage,
                'quantity' => $writtenOff,
            ]);
        }

        $stock = app(StockMovementRepository::class);
        $this->assertSame(50 - $writtenOff, $stock->currentStockForProduct($pendingBatch->product_id, $pendingBatch->warehouse_id));
        $this->assertSame(0.0, $stock->currentStockForProduct($pendingBatch->product_id, $sortedBatch->warehouse_id));
        $this->assertSame(25.0, $stock->currentStockForProduct($sortedBatch->product_id, $sortedBatch->warehouse_id));

        $this->actingAs($admin)->post(route('admin.inventory-empty.store'), [
            'confirmation' => 'EMPTY WAREHOUSE',
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_empty_processes', [
            'status' => 'completed', 'total_records' => 2, 'successful_records' => 2, 'failed_records' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $pendingBatch->id,
            'grade' => ProductGrade::Unsorted->value,
            'type' => StockMovementType::Wastage->value,
            'quantity' => 50 - $writtenOff,
            'created_by' => $admin->id,
        ]);
        $this->assertTrue(app(StockMovementRepository::class)->currentStockByProductAndGrade()->isEmpty());
        $this->assertSame(50.0, (float) $pendingBatch->refresh()->total_kg);
        $movementCount = StockMovement::count();

        $this->post(route('admin.inventory-empty.store'), [
            'confirmation' => 'EMPTY WAREHOUSE',
        ])->assertRedirect();

        $this->assertSame($movementCount, StockMovement::count());
        $this->assertTrue(app(StockMovementRepository::class)->currentStockByProductAndGrade()->isEmpty());
    }

    public function test_empty_warehouse_requires_admin_and_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.inventory-empty.store'), [
            'confirmation' => 'EMPTY WAREHOUSE',
        ])->assertForbidden();

        Role::findOrCreate('admin', 'web');
        $user->assignRole('admin');
        $this->post(route('admin.inventory-empty.store'), [
            'confirmation' => 'EMPTY',
        ])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('inventory_empty_processes', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
