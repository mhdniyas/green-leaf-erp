<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\GoodsReceived;
use App\Models\PurchaserCart;
use App\Models\StockBatch;
use Illuminate\Support\Collection;

class PurchaserCartBatchStateResolver
{
    /** @param Collection<int, PurchaserCart> $carts @return array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}> */
    public function statesForCarts(Collection $carts): array
    {
        if ($carts->isEmpty()) {
            return [];
        }

        $cartIds = $carts->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $goodsReceivedIds = $carts->pluck('goods_received_id')->filter()->map(fn ($id): int => (int) $id)->values();
        $cartNumbers = $carts->pluck('cart_number')->filter()->values();
        $receipts = GoodsReceived::query()->select(['id', 'purchaser_cart_id', 'grn_number', 'notes', 'received_at'])
            ->where(function ($query) use ($goodsReceivedIds, $cartIds, $cartNumbers): void {
                $query->whereIn('id', $goodsReceivedIds)->orWhereIn('purchaser_cart_id', $cartIds);
                if ($cartNumbers->isNotEmpty()) {
                    $query->orWhere(function ($legacy) use ($cartNumbers): void {
                        foreach ($cartNumbers as $i => $number) {
                            $legacy->{$i === 0 ? 'where' : 'orWhere'}('notes', 'like', '%Cart: '.$number.'%');
                        }
                    });
                }
            })->orderByDesc('received_at')->get();
        $byCartId = $receipts->groupBy('purchaser_cart_id');
        $byId = $receipts->keyBy('id');
        $legacyReceipts = $cartNumbers->mapWithKeys(fn (string $number): array => [$number => $receipts->filter(fn (GoodsReceived $receipt): bool => str_contains((string) $receipt->notes, 'Cart: '.$number))]);
        $receiptsForCart = $carts->mapWithKeys(function (PurchaserCart $cart) use ($byId, $byCartId, $legacyReceipts): array {
            $forCart = $cart->goods_received_id !== null ? collect([$byId->get($cart->goods_received_id)])->filter() : $byCartId->get($cart->id, collect())->merge($legacyReceipts->get($cart->cart_number, collect()))->unique('id');

            return [(int) $cart->id => $forCart];
        });

        if ($receipts->isEmpty()) {
            return $carts->mapWithKeys(fn (PurchaserCart $cart): array => [(int) $cart->id => [
                'warehouse_confirmed' => false,
                'total_batches' => 0,
                'confirmed_batches' => 0,
            ]])->all();
        }

        $receiptIds = $receipts->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $grnNumbers = $receipts->pluck('grn_number')->filter()->values();
        $batches = StockBatch::query()->where(function ($query) use ($receiptIds, $grnNumbers): void {
            $query->whereIn('goods_received_id', $receiptIds);
            if ($grnNumbers->isNotEmpty()) {
                $query->orWhere(function ($legacy) use ($grnNumbers): void {
                    $legacy->whereNull('goods_received_id')->where(function ($notes) use ($grnNumbers): void {
                        foreach ($grnNumbers as $i => $number) {
                            $notes->{$i === 0 ? 'where' : 'orWhere'}('notes', 'like', '%Auto-created from GRN: '.$number.'%');
                        }
                    });
                });
            }
        })->orderByDesc('received_at')->get();
        $batchesByReceiptId = $batches->filter(fn (StockBatch $batch): bool => $batch->goods_received_id !== null)->groupBy('goods_received_id');
        $legacyBatches = $grnNumbers->mapWithKeys(fn (string $number): array => [$number => $batches->filter(fn (StockBatch $batch): bool => $batch->goods_received_id === null && str_contains((string) $batch->notes, 'Auto-created from GRN: '.$number))]);

        return $carts->mapWithKeys(function (PurchaserCart $cart) use ($receiptsForCart, $batchesByReceiptId, $legacyBatches): array {
            $related = $receiptsForCart->get($cart->id, collect())->flatMap(fn (GoodsReceived $receipt): Collection => $batchesByReceiptId->get($receipt->id, collect())->merge($legacyBatches->get($receipt->grn_number, collect())))->unique('id')->values();
            $total = $related->count();
            $confirmed = $related->where('warehouse_receive_pending', false)->count();

            return [(int) $cart->id => ['warehouse_confirmed' => $total > 0 && $confirmed === $total, 'total_batches' => $total, 'confirmed_batches' => $confirmed]];
        })->all();
    }
}
