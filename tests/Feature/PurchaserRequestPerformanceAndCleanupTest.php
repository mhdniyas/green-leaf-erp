<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\POStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserRequestPerformanceAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');
        Role::findOrCreate('purchaser');
        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
        $this->supplier = Supplier::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cleanup_command_cancels_only_overdue_eligible_work_and_is_idempotent(): void
    {
        $overdueCart = $this->cart('2026-08-23', 'draft');
        $currentCart = $this->cart('2026-08-24', 'draft');
        $submittedCart = $this->cart('2026-08-23', 'submitted');
        $overdueOrder = PurchaseOrder::factory()->create([
            'status' => POStatus::Approved,
            'order_date' => '2026-08-23',
            'created_by' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
        ]);
        $currentOrder = PurchaseOrder::factory()->create([
            'status' => POStatus::Approved,
            'order_date' => '2026-08-24',
            'created_by' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
        ]);
        $terminalOrder = PurchaseOrder::factory()->create([
            'status' => POStatus::Received,
            'order_date' => '2026-08-23',
            'created_by' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $this->artisan('purchaser:cancel-overdue-work')->assertSuccessful();
        $this->artisan('purchaser:cancel-overdue-work')->assertSuccessful();

        $this->assertSame('cancelled', $overdueCart->refresh()->status);
        $this->assertSame('draft', $currentCart->refresh()->status);
        $this->assertSame('submitted', $submittedCart->refresh()->status);
        $this->assertSame(POStatus::Cancelled, $overdueOrder->refresh()->status);
        $this->assertSame(POStatus::Approved, $currentOrder->refresh()->status);
        $this->assertSame(POStatus::Received, $terminalOrder->refresh()->status);
        $this->assertTrue(collect(Schema::getIndexes('purchase_orders'))->contains(
            fn (array $index): bool => $index['name'] === 'purchase_orders_status_order_date_idx'
        ));
    }

    public function test_purchaser_get_does_not_execute_cleanup_updates(): void
    {
        $updates = [];
        DB::listen(function (QueryExecuted $query) use (&$updates): void {
            if (
                str_starts_with(strtolower(trim($query->sql)), 'update')
                && (str_contains($query->sql, '"purchaser_carts"') || str_contains($query->sql, '"purchase_orders"'))
            ) {
                $updates[] = $query->sql;
            }
        });

        $this->actingAs($this->purchaser)->get(route('purchaser.daily'))->assertOk();

        $this->assertSame([], $updates);
    }

    public function test_purchaser_authorization_and_grade_a_and_b_pages_remain_available(): void
    {
        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser)->get(route('purchaser.daily'))->assertRedirect();
        $this->actingAs($this->purchaser)->get(route('purchaser.daily'))->assertOk();
        $this->actingAs($this->purchaser)->get(route('purchaser.b-grade'))->assertOk();
    }

    private function cart(string $businessDate, string $status): PurchaserCart
    {
        return PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $businessDate,
            'status' => $status,
            'purchase_grade' => 'A',
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
        ]);
    }
}
