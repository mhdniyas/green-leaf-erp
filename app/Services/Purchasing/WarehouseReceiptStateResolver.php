<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class WarehouseReceiptStateResolver
{
    /** Applicable batches: explicit foreign key wins over the legacy receipt marker. */
    public function batches(): Builder
    {
        $marker = (new StockBatch)->getConnection()->getDriverName() === 'sqlite'
            ? "'Auto-created from GRN: ' || goods_received.grn_number"
            : "CONCAT('Auto-created from GRN: ', goods_received.grn_number)";

        return StockBatch::query()->where(function (Builder $query) use ($marker): void {
            $query->whereColumn('stock_batches.goods_received_id', 'goods_received.id')
                ->orWhere(function (Builder $legacy) use ($marker): void {
                    $legacy->whereNull('stock_batches.goods_received_id')
                        ->whereRaw("stock_batches.notes = {$marker}");
                });
        });
    }

    public function reconciliations(): Builder
    {
        return BillReconciliation::query()->whereColumn('bill_reconciliations.goods_received_id', 'goods_received.id')
            ->where('bill_reconciliations.status', 'confirmed');
    }

    public function withFacts(Builder|Relation $query): Builder
    {
        $query = $query instanceof Relation ? $query->getQuery() : $query;

        return $query->addSelect([
            'receipt_batch_count' => $this->batches()->selectRaw('COUNT(*)'),
            'receipt_pending_batch_count' => $this->batches()->where('warehouse_receive_pending', true)->selectRaw('COUNT(*)'),
            'receipt_confirmed_at' => $this->batches()->selectRaw('MAX(warehouse_confirmed_at)'),
            'receipt_confirmed_by' => $this->batches()->whereNotNull('warehouse_confirmed_at')
                ->orderByDesc('warehouse_confirmed_at')->orderByDesc('id')->limit(1)->select('warehouse_confirmed_by'),
            'reconciliation_count' => $this->reconciliations()->selectRaw('COUNT(*)'),
            'reconciliation_new_receive_qty' => $this->reconciliations()->selectRaw('COALESCE(MAX(total_new_receive_base_qty), 0)'),
            'reconciliation_confirmed_at' => $this->reconciliations()->selectRaw('MAX(confirmed_at)'),
            'reconciliation_confirmed_by' => $this->reconciliations()->orderByDesc('confirmed_at')->orderByDesc('id')->limit(1)->select('confirmed_by'),
        ]);
    }

    public function filter(Builder $query, string $state): Builder
    {
        if ($state === 'received') {
            return $query->where('goods_received.status', 'approved')
                ->where(function (Builder $received): void {
                    $received->where(function (Builder $withBatches): void {
                        $withBatches->whereExists($this->batches()->selectRaw('1')->toBase())
                            ->whereNotExists($this->batches()->where('warehouse_receive_pending', true)->selectRaw('1')->toBase());
                    })->orWhere(function (Builder $reconciledAdvance): void {
                        $reconciledAdvance->whereExists(
                            $this->reconciliations()->where('total_new_receive_base_qty', '<=', 0.0001)->selectRaw('1')->toBase()
                        );
                    });
                });
        }

        return $query->where(function (Builder $pending): void {
            $pending->where('goods_received.status', '!=', 'approved')
                ->orWhere(function (Builder $incomplete): void {
                    $incomplete->whereNotExists(
                        $this->reconciliations()->where('total_new_receive_base_qty', '<=', 0.0001)->selectRaw('1')->toBase()
                    )->where(function (Builder $batchCheck): void {
                        $batchCheck->whereNotExists($this->batches()->selectRaw('1')->toBase())
                            ->orWhereExists($this->batches()->where('warehouse_receive_pending', true)->selectRaw('1')->toBase());
                    });
                })
                ->orWhere(function (Builder $incompleteReconciled): void {
                    $incompleteReconciled->whereExists(
                        $this->reconciliations()->where('total_new_receive_base_qty', '>', 0.0001)->selectRaw('1')->toBase()
                    )->where(function (Builder $batchCheck): void {
                        $batchCheck->whereNotExists($this->batches()->selectRaw('1')->toBase())
                            ->orWhereExists($this->batches()->where('warehouse_receive_pending', true)->selectRaw('1')->toBase());
                    });
                });
        });
    }

    /** @return array<string, mixed> */
    public function forReceipt(GoodsReceived $receipt): array
    {
        if (! array_key_exists('receipt_batch_count', $receipt->getAttributes())) {
            $receipt = $this->withFacts(GoodsReceived::query()->select('goods_received.*')->whereKey($receipt->id))->firstOrFail();
        }

        $isReconciledAdvance = (int) ($receipt->reconciliation_count ?? 0) > 0
            && (float) ($receipt->reconciliation_new_receive_qty ?? 0.0) <= 0.0001;

        $received = $receipt->status === 'approved' && (
            ((int) $receipt->receipt_batch_count > 0 && (int) $receipt->receipt_pending_batch_count === 0)
            || $isReconciledAdvance
        );

        $billPending = $receipt->bill_status === 'bill_pending'
            || (array_key_exists('purchase_invoices_count', $receipt->getAttributes())
                ? (int) $receipt->purchase_invoices_count === 0
                : ! $receipt->purchaseInvoices()->exists());

        $confirmedAt = $received
            ? ($receipt->receipt_confirmed_at ?: $receipt->reconciliation_confirmed_at)
            : null;
        $confirmedBy = $received
            ? ($receipt->receipt_confirmed_by !== null ? (int) $receipt->receipt_confirmed_by : ($receipt->reconciliation_confirmed_by !== null ? (int) $receipt->reconciliation_confirmed_by : null))
            : null;

        return [
            ...$this->stateFields($received),
            'warehouse_confirmed_at' => $confirmedAt,
            'warehouse_confirmed_by' => $confirmedBy,
            'bill_status' => $billPending ? 'bill_pending' : 'bill_available',
            'bill_status_label' => $billPending ? 'BILL PENDING' : 'BILL AVAILABLE',
            'is_bill_pending' => $billPending,
        ];
    }

    /** @return array<string, mixed> */
    public function forOrder(PurchaseOrder $order): array
    {
        $order->loadMissing(['goodsReceiveds' => fn (Builder|Relation $query) => $this->withFacts($query->select('goods_received.*'))->withCount('purchaseInvoices')]);
        $states = $order->goodsReceiveds->map(fn (GoodsReceived $receipt): array => $this->forReceipt($receipt));
        $received = $states->isNotEmpty() && $states->every(fn (array $state): bool => $state['receipt_status'] === 'received');
        $pendingReceipt = $order->goodsReceiveds->first(fn (GoodsReceived $receipt): bool => $this->forReceipt($receipt)['warehouse_receive_pending']);
        $billPending = $states->isEmpty() || $states->contains(fn (array $state): bool => $state['is_bill_pending']);
        $canCreate = $pendingReceipt === null && in_array($order->status->value, ['approved', 'sent_to_supplier', 'partially_received'], true);

        return [
            ...$this->stateFields($received),
            'bill_status' => $billPending ? 'bill_pending' : 'bill_available',
            'bill_status_label' => $billPending ? 'BILL PENDING' : 'BILL AVAILABLE',
            'can_create_receipt' => $canCreate,
            'pending_receive_url' => $pendingReceipt ? $this->receiveUrl($pendingReceipt) : null,
        ];
    }

    public function receiveUrl(GoodsReceived $receipt): string
    {
        if ($receipt->status === 'pending_approval' || ((int) $receipt->receipt_batch_count === 0 && (int) ($receipt->reconciliation_count ?? 0) === 0)) {
            return route('warehouse.receiver.receive-grn', $receipt->getRouteKey());
        }

        return route('warehouse.receiver.checklist', ['date' => $receipt->received_at->toDateString(), 'tab' => 'pending']);
    }

    /** @return array{receipt_status:string, receipt_status_label:string, warehouse_receive_pending:bool, inventory_posted:bool, status_label:string} */
    private function stateFields(bool $received): array
    {
        return [
            'receipt_status' => $received ? 'received' : 'pending',
            'receipt_status_label' => $received ? 'RECEIVED' : 'PENDING WAREHOUSE RECEIVE',
            'warehouse_receive_pending' => ! $received,
            /** This receipt API flag means confirmed inventory. Pre-confirmation stock visibility is unchanged. */
            'inventory_posted' => $received,
            'status_label' => $received ? 'RECEIVED' : 'PENDING WAREHOUSE RECEIVE',
        ];
    }
}
