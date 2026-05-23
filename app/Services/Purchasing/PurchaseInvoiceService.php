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
}
