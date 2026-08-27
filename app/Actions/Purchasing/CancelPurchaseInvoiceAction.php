<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Models\VendorSettlementAllocation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelPurchaseInvoiceAction
{
    /**
     * @return array{invoice: PurchaseInvoice, already_cancelled: bool, purchaser_credit_rows_removed: int, grn_released: bool}
     */
    public function execute(PurchaseInvoice $invoice, User $user, string $reason, ?string $note = null): array
    {
        return DB::transaction(function () use ($invoice, $user, $reason, $note): array {
            /** @var PurchaseInvoice $lockedInvoice */
            $lockedInvoice = PurchaseInvoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->isCancelled()) {
                return [
                    'invoice' => $lockedInvoice->fresh(['goodsReceived', 'cancelledBy']),
                    'already_cancelled' => true,
                    'purchaser_credit_rows_removed' => 0,
                    'grn_released' => false,
                ];
            }

            $this->assertNoSettlementActivity($lockedInvoice);

            $journalCount = JournalEntry::query()
                ->where('source_type', PurchaseInvoice::class)
                ->where('source_id', $lockedInvoice->id)
                ->count();

            if ($journalCount > 0) {
                throw new RuntimeException('This bill has payment or settlement activity. Reverse/unallocate those transactions before cancelling the bill.');
            }

            $purchaserCreditRowsRemoved = PurchaserCredit::query()
                ->where('purchase_invoice_id', $lockedInvoice->id)
                ->delete();

            $grnReleased = false;
            if ($lockedInvoice->goods_received_id !== null) {
                /** @var GoodsReceived|null $goodsReceived */
                $goodsReceived = GoodsReceived::query()
                    ->whereKey($lockedInvoice->goods_received_id)
                    ->lockForUpdate()
                    ->first();

                if ($goodsReceived instanceof GoodsReceived && $goodsReceived->status === 'approved') {
                    $goodsReceived->update([
                        'bill_status' => 'bill_pending',
                        'bill_number' => null,
                        'matched_by' => null,
                        'matched_at' => null,
                        'updated_by' => $user->id,
                    ]);
                    $grnReleased = true;
                }
            }

            $lockedInvoice->update([
                'status' => InvoiceStatus::Cancelled,
                'payment_status' => 'cancelled',
                'paid_amount' => 0,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
                'cancellation_note' => $note,
            ]);

            activity()
                ->performedOn($lockedInvoice)
                ->causedBy($user)
                ->event('purchase_invoice.cancelled')
                ->withProperties([
                    'reason' => $reason,
                    'note' => $note,
                    'grn_released' => $grnReleased,
                    'purchaser_credit_rows_removed' => $purchaserCreditRowsRemoved,
                    'stock_changed' => false,
                ])
                ->log('purchase_invoice.cancelled');

            return [
                'invoice' => $lockedInvoice->fresh(['goodsReceived', 'cancelledBy']),
                'already_cancelled' => false,
                'purchaser_credit_rows_removed' => $purchaserCreditRowsRemoved,
                'grn_released' => $grnReleased,
            ];
        }, attempts: 3);
    }

    private function assertNoSettlementActivity(PurchaseInvoice $invoice): void
    {
        $paidAmount = round((float) $invoice->paid_amount, 2);
        $paymentCount = PurchaseInvoicePayment::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->count();
        $settlementCount = VendorSettlementAllocation::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->count();

        if ($paidAmount > 0.0 || $paymentCount > 0 || $settlementCount > 0) {
            throw new RuntimeException('This bill has payment or settlement activity. Reverse/unallocate those transactions before cancelling the bill.');
        }
    }
}
