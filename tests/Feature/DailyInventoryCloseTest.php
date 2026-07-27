<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyInventoryCloseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_split_daily_closing_stock_between_wastage_and_carryover(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));
        $product = Product::factory()->create([
            'name' => 'Potato Local',
            'buffer_qty' => 5,
            'carryover_enabled' => true,
        ]);
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'created_by' => $admin->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'total_kg' => 10,
            'cost_per_kg' => 30,
            'received_at' => '2026-07-27',
        ]);
        StockMovement::factory()->create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $admin->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 10,
            'cost_per_unit' => 30,
            'created_at' => '2026-07-27 08:00:00',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('inventory.daily-close.index', ['date' => '2026-07-27']))
            ->assertOk()
            ->assertSeeText('Daily Inventory Close')
            ->assertSeeText('Potato Local');

        $this
            ->actingAs($admin)
            ->post(route('inventory.daily-close.store'), [
                'date' => '2026-07-27',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'grade' => ProductGrade::GradeA->value,
                        'closing_qty' => 10,
                        'wastage_qty' => 3,
                        'carryover_qty' => 7,
                        'carryover_enabled' => 1,
                        'negative_note' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.daily-close.index', ['date' => '2026-07-27']));

        $this->assertDatabaseHas('daily_inventory_close_lines', [
            'business_date' => '2026-07-27 00:00:00',
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA->value,
            'closing_qty' => '10.000',
            'wastage_qty' => '3.000',
            'carryover_qty' => '7.000',
            'closed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Wastage->value,
            'quantity' => '3.000',
            'notes' => 'Daily inventory close wastage - Date: 2026-07-27',
        ]);
    }

    public function test_daily_close_requires_negative_stock_note(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));
        $product = Product::factory()->create(['name' => 'Potato Local']);
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'created_by' => $admin->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'total_kg' => 0,
            'cost_per_kg' => 30,
            'received_at' => '2026-07-27',
        ]);
        StockMovement::factory()->create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $admin->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 4,
            'cost_per_unit' => 30,
            'created_at' => '2026-07-27 08:00:00',
        ]);

        $this
            ->actingAs($admin)
            ->from(route('inventory.daily-close.index', ['date' => '2026-07-27']))
            ->post(route('inventory.daily-close.store'), [
                'date' => '2026-07-27',
                'lines' => [
                    [
                        'product_id' => $product->id,
                        'grade' => ProductGrade::GradeA->value,
                        'closing_qty' => -4,
                        'wastage_qty' => 0,
                        'carryover_qty' => 0,
                        'carryover_enabled' => 0,
                        'negative_note' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.daily-close.index', ['date' => '2026-07-27']))
            ->assertSessionHasErrors('lines.0.negative_note');
    }
}
