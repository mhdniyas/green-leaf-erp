<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GoodsReceived;
use App\Models\PurchaserCart;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserCartBatchStateResolver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaserCartBatchStateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_goods_received_foreign_keys_and_preserves_batch_state(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $cart = $this->cart($user, $supplier, 'A');
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $cart->update(['goods_received_id' => $goodsReceived->id]);
        StockBatch::factory()->warehouseConfirmed()->create(['goods_received_id' => $goodsReceived->id, 'received_at' => '2026-08-22']);
        StockBatch::factory()->warehousePending()->create(['goods_received_id' => $goodsReceived->id, 'received_at' => '2026-08-23']);
        $unrelated = GoodsReceived::factory()->create();
        StockBatch::factory()->warehouseConfirmed()->create(['goods_received_id' => $unrelated->id]);

        $state = app(PurchaserCartBatchStateResolver::class)->statesForCarts(collect([$cart]));

        $this->assertSame(['warehouse_confirmed' => false, 'total_batches' => 2, 'confirmed_batches' => 1], $state[$cart->id]);
    }

    public function test_stock_batch_queries_are_constant_as_cart_count_grows_and_keep_grade_isolation(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $carts = collect();

        foreach (range(1, 6) as $index) {
            $cart = $this->cart($user, $supplier, $index % 2 === 0 ? 'B' : 'A');
            $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id, 'purchase_grade' => $cart->purchase_grade]);
            StockBatch::factory()->warehouseConfirmed()->create(['goods_received_id' => $goodsReceived->id, 'purchase_grade' => $cart->purchase_grade]);
            $carts->push($cart);
        }

        $stockBatchQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$stockBatchQueries): void {
            if (str_contains($query->sql, 'stock_batches')) {
                $stockBatchQueries++;
            }
        });

        $state = app(PurchaserCartBatchStateResolver::class)->statesForCarts($carts);

        $this->assertSame(1, $stockBatchQueries);
        $this->assertCount(6, $state);
        foreach ($carts as $cart) {
            $this->assertSame(['warehouse_confirmed' => true, 'total_batches' => 1, 'confirmed_batches' => 1], $state[$cart->id]);
        }

        $emptyCart = $this->cart($user, $supplier, 'A');
        $emptyState = app(PurchaserCartBatchStateResolver::class)->statesForCarts(collect([$emptyCart]));
        $this->assertSame(['warehouse_confirmed' => false, 'total_batches' => 0, 'confirmed_batches' => 0], $emptyState[$emptyCart->id]);
    }

    public function test_it_preserves_the_legacy_note_based_link_for_unlinked_records(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $cart = $this->cart($user, $supplier, 'B');
        $goodsReceived = GoodsReceived::factory()->create([
            'purchaser_cart_id' => null,
            'notes' => 'Legacy Cart: '.$cart->cart_number,
        ]);
        StockBatch::factory()->warehouseConfirmed()->create([
            'goods_received_id' => null,
            'purchase_grade' => 'B',
            'notes' => 'Auto-created from GRN: '.$goodsReceived->grn_number,
        ]);

        $state = app(PurchaserCartBatchStateResolver::class)->statesForCarts(collect([$cart]));

        $this->assertSame(['warehouse_confirmed' => true, 'total_batches' => 1, 'confirmed_batches' => 1], $state[$cart->id]);
    }

    private function cart(User $user, Supplier $supplier, string $purchaseGrade): PurchaserCart
    {
        return PurchaserCart::query()->create([
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'business_date' => '2026-08-24',
            'status' => 'submitted',
            'purchase_grade' => $purchaseGrade,
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
        ]);
    }
}
