<?php

declare(strict_types=1);

namespace App\Services\Requisition;

use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderChangeRequest;
use App\Models\ShopOrderRevision;
use App\Models\User;

class ShopOrderChangeRequestRecorder
{
    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     */
    public function recordSubmittedOrderUpdate(ShopOrder $order, array $items, User $requester, ?string $reason = null): ShopOrderChangeRequest
    {
        $order->changeRequests()
            ->where('type', 'quantity_update')
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $changeRequest = $this->createRequest($order, 'quantity_update', $requester, $reason);
        $this->syncRequestedItems($changeRequest, $order, $items);

        return $changeRequest->fresh(['items.product']);
    }

    public function recordLateSubmission(ShopOrder $order, User $requester, ?string $reason = null): ShopOrderChangeRequest
    {
        $order->changeRequests()
            ->where('type', 'late_submission')
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $changeRequest = $this->createRequest($order, 'late_submission', $requester, $reason);

        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            $changeRequest->items()->create([
                'product_id' => $item->product_id,
                'old_qty' => 0,
                'new_qty' => (float) $item->requested_qty,
                'approved_qty' => null,
                'delta_qty' => (float) $item->requested_qty,
            ]);
        }

        return $changeRequest->fresh(['items.product']);
    }

    public function recordApprovedOrderRevision(ShopOrderRevision $revision): ShopOrderChangeRequest
    {
        $revision->loadMissing(['shopOrder', 'items.product', 'requestedBy']);

        $revision->shopOrder->changeRequests()
            ->where('type', 'approved_order_update')
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $changeRequest = $this->createRequest(
            $revision->shopOrder,
            'approved_order_update',
            $revision->requestedBy,
            $revision->reason,
            $revision
        );

        foreach ($revision->items as $item) {
            $changeRequest->items()->create([
                'product_id' => $item->product_id,
                'old_qty' => (float) $item->old_requested_qty,
                'new_qty' => (float) $item->new_requested_qty,
                'approved_qty' => $item->final_approved_qty,
                'delta_qty' => (float) $item->delta_qty,
            ]);
        }

        return $changeRequest->fresh(['items.product']);
    }

    public function markLatestPending(ShopOrder $order, string $type, string $status, User $reviewer, ?string $managerNote = null): void
    {
        $order->changeRequests()
            ->where('type', $type)
            ->where('status', 'pending')
            ->latest('id')
            ->first()
            ?->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
            ]);
    }

    public function markRevisionRequest(ShopOrderRevision $revision, string $status, User $reviewer, ?string $managerNote = null): void
    {
        $revision->changeRequests()
            ->where('type', 'approved_order_update')
            ->whereIn('status', ['pending', 'superseded'])
            ->latest('id')
            ->first()
            ?->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
            ]);
    }

    private function createRequest(
        ShopOrder $order,
        string $type,
        User $requester,
        ?string $reason = null,
        ?ShopOrderRevision $revision = null
    ): ShopOrderChangeRequest {
        return $order->changeRequests()->create([
            'shop_order_revision_id' => $revision?->id,
            'type' => $type,
            'status' => 'pending',
            'requested_by' => $requester->id,
            'requested_at' => now(),
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
        ]);
    }

    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     */
    private function syncRequestedItems(ShopOrderChangeRequest $changeRequest, ShopOrder $order, array $items): void
    {
        $baselineItems = $order->items()->get()->keyBy('product_id');

        foreach ($items as $item) {
            $product = $item['product'];
            $newQty = round((float) $item['quantity'], 2);
            $baselineItem = $baselineItems->get($product->id);
            $oldQty = round((float) ($baselineItem?->requested_qty ?? 0), 2);

            $changeRequest->items()->create([
                'product_id' => $product->id,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'approved_qty' => null,
                'delta_qty' => round($newQty - $oldQty, 2),
            ]);
        }
    }
}
