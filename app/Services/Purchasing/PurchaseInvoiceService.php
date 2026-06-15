<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
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
            /** @var PurchaseInvoice $invoice */
            $invoice = $this->repository->create($data->toArray());

            // Transition associated Purchase Order status to Closed upon matching invoice
            /** @var GoodsReceived $grn */
            $grn = GoodsReceived::findOrFail($data->goodsReceivedId);
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

            // Post General Ledger entries
            $this->journalService->recordPurchaseInvoice($invoice);

            return $invoice->fresh(['goodsReceived', 'supplier']);
        });
    }

    public function updateStatus(PurchaseInvoice $invoice, string $status): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $status): PurchaseInvoice {
            $oldStatus = $invoice->status;
            $invoice->update(['status' => $status]);

            if ($status === InvoiceStatus::Paid->value && $oldStatus !== InvoiceStatus::Paid) {
                // Post General Ledger entries for payment
                $this->journalService->recordPurchasePayment($invoice);
            }

            return $invoice->fresh();
        });
    }

    /**
     * @param  array{payment_method:string, paid_amount:float, payment_note:?string, payment_details:?string}  $payload
     */
    public function updatePayment(PurchaseInvoice $invoice, array $payload): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $payload): PurchaseInvoice {
            $invoice->loadMissing(['supplier', 'purchaserCart']);

            $invoiceAmount = round((float) $invoice->amount, 2);
            $paidAmount = min($invoiceAmount, round((float) $payload['paid_amount'], 2));
            $paymentMethod = $payload['payment_method'];
            $paymentStatus = $this->resolvePaymentStatus(
                paymentMethod: $paymentMethod,
                supplierCreditApproved: (bool) $invoice->supplier?->credit_approved,
                invoiceAmount: $invoiceAmount,
                paidAmount: $paidAmount,
            );
            $invoiceStatus = $paymentStatus === 'paid'
                ? InvoiceStatus::Paid->value
                : InvoiceStatus::Pending->value;
            $oldStatus = $invoice->status;

            $invoice->update([
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $payload['payment_note'],
                'payment_details' => $payload['payment_details'],
                'status' => $invoiceStatus,
            ]);

            if ($invoice->purchaserCart) {
                $invoice->purchaserCart->update([
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'paid_amount' => $paidAmount,
                    'payment_note' => $payload['payment_note'],
                    'payment_details' => $payload['payment_details'],
                    'goods_received_at' => $paymentStatus === 'paid' ? ($invoice->purchaserCart->goods_received_at ?? now()) : null,
                    'payment_made_at' => $paymentStatus === 'paid' ? now() : null,
                ]);
            }

            if ($invoiceStatus === InvoiceStatus::Paid->value && $oldStatus !== InvoiceStatus::Paid) {
                $this->journalService->recordPurchasePayment($invoice->fresh());
            }

            return $invoice->fresh(['supplier', 'purchaserCart']);
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
