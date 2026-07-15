<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Repositories\Purchasing\PurchaseInvoiceRepository;
use App\Services\Finance\JournalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $repository,
        private readonly JournalService $journalService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->query()
            ->with(['goodsReceived', 'supplier'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(PurchaseInvoiceData $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data): PurchaseInvoice {
            /** @var GoodsReceived $grn */
            $grn = GoodsReceived::query()
                ->with('purchaseOrder.purchaserCart')
                ->findOrFail($data->goodsReceivedId);

            $invoiceData = $data->toArray();

            if ($grn->purchaseOrder?->purchaserCart?->isGreenLeafDirectPurchase()) {
                $invoiceData['purchaser_cart_id'] = $grn->purchaseOrder->purchaserCart->id;
                $invoiceData['purchase_source'] = $grn->purchaseOrder->purchaserCart->purchase_source;
                $invoiceData['purchaser_submitted_by'] = $grn->purchaseOrder->purchaserCart->user_id;
                $invoiceData['purchaser_submitted_at'] = $grn->purchaseOrder->purchaserCart->submitted_at;
            }

            /** @var PurchaseInvoice $invoice */
            $invoice = $this->repository->create($invoiceData);

            // Transition associated Purchase Order status to Closed upon matching invoice
            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update([
                    'status' => POStatus::Closed,
                ]);
            }

            // Log activity
            activity()
                ->performedOn($invoice)
                ->log('invoice.created');

            return $invoice->fresh(['goodsReceived', 'supplier']);
        });
    }

    public function updateStatus(PurchaseInvoice $invoice, string $status): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $status): PurchaseInvoice {
            $invoice->update(['status' => $status]);

            return $invoice->fresh();
        });
    }

    /**
     * @param  array{payment_method:string, discount_amount:float, paid_amount:float, payment_note:?string, payment_details:?string}  $payload
     */
    public function updatePayment(PurchaseInvoice $invoice, array $payload): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $payload): PurchaseInvoice {
            $invoice->loadMissing(['supplier', 'purchaserCart']);
            $previousPaidAmount = round((float) ($invoice->paid_amount ?? 0), 2);

            $invoiceAmount = round((float) $invoice->amount, 2);
            $discountAmount = min($invoiceAmount, max(0, round((float) ($payload['discount_amount'] ?? $invoice->discount_amount ?? 0), 2)));
            $netInvoiceAmount = max(0, round($invoiceAmount - $discountAmount, 2));
            $paidAmount = min($netInvoiceAmount, max(0, round((float) $payload['paid_amount'], 2)));
            $paymentMethod = $payload['payment_method'];
            $paymentStatus = $this->resolvePaymentStatus(
                paymentMethod: $paymentMethod,
                supplierCreditApproved: (bool) $invoice->supplier?->credit_approved,
                invoiceAmount: $netInvoiceAmount,
                paidAmount: $paidAmount,
            );
            $invoiceStatus = $paymentStatus === 'paid'
                ? InvoiceStatus::Paid->value
                : InvoiceStatus::Pending->value;
            $invoiceUpdateData = [
                'payment_method' => $paymentMethod,
                'discount_amount' => $discountAmount,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $payload['payment_note'],
                'payment_details' => $payload['payment_details'],
                'status' => $invoiceStatus,
            ];

            if (! empty($payload['bill_number'])) {
                $invoiceUpdateData['invoice_number'] = $payload['bill_number'];
            }

            $invoice->update($invoiceUpdateData);

            if ($invoice->purchaserCart) {
                $cartUpdateData = [
                    'payment_method' => $paymentMethod,
                    'discount_amount' => $discountAmount,
                    'payment_status' => $paymentStatus,
                    'paid_amount' => $paidAmount,
                    'payment_note' => $payload['payment_note'],
                    'payment_details' => $payload['payment_details'],
                    'payment_made_at' => $paymentStatus === 'paid' ? now() : null,
                    'goods_received_at' => $paymentStatus === 'paid' ? ($invoice->purchaserCart->goods_received_at ?: now()) : $invoice->purchaserCart->goods_received_at,
                ];

                if (! empty($payload['bill_number'])) {
                    $cartUpdateData['bill_number'] = $payload['bill_number'];
                }

                $invoice->purchaserCart->update($cartUpdateData);
            }

            if ($invoice->purchaser_submitted_by) {
                PurchaserCredit::updateOrCreate(
                    ['purchase_invoice_id' => $invoice->id, 'type' => 'out'],
                    [
                        'purchaser_id' => $invoice->purchaser_submitted_by,
                        'amount' => $netInvoiceAmount,
                        'description' => 'Debit for invoice: '.($payload['bill_number'] ?? $invoice->invoice_number),
                        'created_by' => auth()->id() ?: $invoice->purchaser_submitted_by,
                        'business_date' => $invoice->purchaserCart?->business_date ?: today(),
                    ]
                );
            }

            $updatedInvoice = $invoice->fresh(['supplier', 'purchaserCart']);
            $paidIncrease = round((float) $updatedInvoice->paid_amount - $previousPaidAmount, 2);

            if ($updatedInvoice->isGreenLeafDirectPurchase() && $paidIncrease > 0) {
                $this->journalService->recordGreenLeafDirectPurchasePayment(
                    invoice: $updatedInvoice,
                    amount: $paidIncrease,
                    userId: (int) (auth()->id() ?: $updatedInvoice->purchaser_submitted_by ?: 1)
                );
            }

            return $updatedInvoice;
        });
    }

    private function resolvePaymentStatus(string $paymentMethod, bool $supplierCreditApproved, float $invoiceAmount, float $paidAmount): string
    {
        if (strcasecmp($paymentMethod, 'Credit') === 0) {
            return $supplierCreditApproved ? 'credit_pending_approval' : 'credit_pending_approval';
        }

        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $invoiceAmount) {
            return 'partial';
        }

        return 'paid';
    }
}
