<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\InventoryEmptyProcess;
use App\Models\InventoryEmptyProcessItem;
use App\Models\Product;
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
        $expectedInitialPendingStock = $receiptPending ? 0.0 : (50.0 - $writtenOff);
        $this->assertSame($expectedInitialPendingStock, $stock->currentStockForProduct($pendingBatch->product_id, $pendingBatch->warehouse_id));
        $this->assertSame(0.0, $stock->currentStockForProduct($pendingBatch->product_id, $sortedBatch->warehouse_id));
        $this->assertSame(25.0, $stock->currentStockForProduct($sortedBatch->product_id, $sortedBatch->warehouse_id));

        $this->actingAs($admin)->post(route('admin.inventory-empty.store'), [
            'confirmation' => 'EMPTY WAREHOUSE',
        ])->assertRedirect();

        if (! $receiptPending) {
            $this->assertDatabaseHas('stock_movements', [
                'batch_id' => $pendingBatch->id,
                'grade' => ProductGrade::Unsorted->value,
                'type' => StockMovementType::Wastage->value,
                'quantity' => 50 - $writtenOff,
                'created_by' => $admin->id,
            ]);
        }
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

    public function test_admin_can_cancel_active_process(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $process = InventoryEmptyProcess::create([
            'status' => 'running',
            'total_records' => 10,
            'started_by' => $admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.inventory-empty.cancel', $process->id));

        $response->assertRedirect(route('admin.inventory-empty.index'));
        $this->assertSame('cancelled', $process->fresh()->status);
        $this->assertNotNull($process->fresh()->error_message);
        $this->assertNotNull($process->fresh()->completed_at);
    }

    public function test_non_admin_cannot_cancel_process(): void
    {
        $user = User::factory()->create();
        $process = InventoryEmptyProcess::create([
            'status' => 'running',
            'total_records' => 10,
            'started_by' => $user->id,
            'started_at' => now(),
        ]);

        $this->actingAs($user)->post(route('admin.inventory-empty.cancel', $process->id))->assertForbidden();
        $this->assertSame('running', $process->fresh()->status);
    }

    public function test_cannot_cancel_already_completed_process(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $process = InventoryEmptyProcess::create([
            'status' => 'completed',
            'total_records' => 10,
            'started_by' => $admin->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.inventory-empty.cancel', $process->id))->assertStatus(422);
        $this->assertSame('completed', $process->fresh()->status);
    }

    public function test_admin_can_step_process_via_json(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $process = InventoryEmptyProcess::create([
            'status' => 'pending',
            'total_records' => 1,
            'started_by' => $admin->id,
        ]);

        $product = Product::factory()->create();
        InventoryEmptyProcessItem::create([
            'process_id' => $process->id,
            'product_id' => $product->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.inventory-empty.step', $process->id));

        $response->assertOk()
            ->assertJson([
                'status' => 'completed',
                'total' => 1,
                'processed' => 1,
                'successful' => 1,
                'failed' => 0,
                'percent' => 100,
                'is_active' => false,
            ]);

        $this->assertSame('completed', $process->fresh()->status);
    }

    public function test_console_command_can_cancel_and_run_process(): void
    {
        $user = User::factory()->create();
        $process = InventoryEmptyProcess::create([
            'status' => 'pending',
            'total_records' => 1,
            'started_by' => $user->id,
        ]);

        $this->artisan('inventory:empty-process', ['process' => $process->id, '--cancel' => true])
            ->assertSuccessful();

        $this->assertSame('cancelled', $process->fresh()->status);
    }

    public function test_empty_warehouse_skips_zero_stock_products(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create a product with 0 stock (no movements)
        $zeroProduct = Product::factory()->create();

        // Create a product with positive stock
        $positiveProduct = Product::factory()->create();
        $sortedBatch = StockBatch::factory()->sorted()->warehouseConfirmed()->create([
            'product_id' => $positiveProduct->id,
            'warehouse_id' => Warehouse::factory(),
        ]);
        StockMovement::factory()->create([
            'product_id' => $positiveProduct->id,
            'batch_id' => $sortedBatch->id,
            'warehouse_id' => $sortedBatch->warehouse_id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inventory-empty.index'));
        $response->assertOk();
        $response->assertViewHas('recordCount', 1);

        $this->post(route('admin.inventory-empty.store'), [
            'confirmation' => 'EMPTY WAREHOUSE',
        ])->assertRedirect();

        $process = InventoryEmptyProcess::latest()->first();
        $this->assertSame(1, $process->total_records);
        $this->assertTrue($process->items()->where('product_id', $positiveProduct->id)->exists());
        $this->assertFalse($process->items()->where('product_id', $zeroProduct->id)->exists());
    }
}
