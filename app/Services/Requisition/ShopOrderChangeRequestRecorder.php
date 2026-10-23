<?php

declare(strict_types=1);

namespace App\Services\Requisition;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderChangeRequest;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderRevision;
use App\Models\User;

class ShopOrderChangeRequestRecorder
{
    /**
     * @param  array<int, array{product: Product, quantity: float, grade?: string|ProductGrade}>  $items
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
            $grade = $item->product_grade?->value ?? (string) ($item->product_grade ?? 'A');
            $changeRequest->items()->create([
                'shop_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_grade' => $grade,
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
        $revision->loadMissing(['shopOrder.items', 'items.product', 'requestedBy']);

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
            $grade = $item->product_grade?->value ?? (string) ($item->product_grade ?? 'A');
            $baselineItem = $revision->shopOrder->items
                ->first(fn (ShopOrderItem $soi): bool => (int) $soi->product_id === (int) $item->product_id && ($soi->product_grade?->value ?? (string) ($soi->product_grade ?? 'A')) === $grade);

            $changeRequest->items()->create([
                'shop_order_item_id' => $baselineItem?->id,
                'product_id' => $item->product_id,
                'product_grade' => $grade,
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
     * @param  array<int, array{product: Product, quantity: float, grade?: string|ProductGrade}>  $items
     */
    private function syncRequestedItems(ShopOrderChangeRequest $changeRequest, ShopOrder $order, array $items): void
    {
        $baselineItems = $order->items()->get()->keyBy(
            fn (ShopOrderItem $item): string => (string) $item->product_id.'|'.($item->product_grade?->value ?? (string) ($item->product_grade ?? 'A'))
        );

        foreach ($items as $item) {
            $product = $item['product'];
            $grade = $item['grade'] ?? 'A';
            $gradeValue = $grade instanceof ProductGrade ? $grade->value : (string) $grade;
            $key = (string) $product->id.'|'.$gradeValue;

            $newQty = round((float) $item['quantity'], 2);
            $baselineItem = $baselineItems->get($key);
            $oldQty = round((float) ($baselineItem?->requested_qty ?? 0), 2);

            $changeRequest->items()->create([
                'shop_order_item_id' => $baselineItem?->id,
                'product_id' => $product->id,
                'product_grade' => $gradeValue,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'approved_qty' => null,
                'delta_qty' => round($newQty - $oldQty, 2),
            ]);
        }
    }
}
