<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\PaymentData;
use App\Enums\Sales\SalesInvoiceStatus;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Repositories\Sales\PaymentRepository;
use App\Repositories\Sales\SalesInvoiceRepository;
use App\Services\Finance\JournalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesInvoiceService
{
    public function __construct(
        private readonly SalesInvoiceRepository $repository,
        private readonly PaymentRepository $paymentRepository,
        private readonly SalesOrderService $salesOrderService,
        private readonly JournalService $journalService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->query()
            ->with(['customer', 'salesOrder', 'createdBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create an invoice from a dispatched sales order.
     */
    public function createFromOrder(SalesOrder $so, int $userId): SalesInvoice
    {
        if ($so->invoice()->exists()) {
            throw new RuntimeException("Invoice already exists for order {$so->so_number}.");
        }

        return DB::transaction(function () use ($so, $userId) {
            $so->load('items', 'customer');

            $invoiceNumber = $this->repository->generateInvoiceNumber();
            $amount = $so->total_amount;
            $dueDays = $so->customer->dueDaysFromPaymentTerms();
            $dueDate = now()->addDays($dueDays)->format('Y-m-d');

            /** @var SalesInvoice $invoice */
            $invoice = $this->repository->create([
                'sales_order_id' => $so->id,
                'customer_id' => $so->customer_id,
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'paid_amount' => 0,
                'due_date' => $dueDate,
                'status' => SalesInvoiceStatus::Unpaid->value,
                'created_by' => $userId,
            ]);

            // Mark the order as invoiced
            $this->salesOrderService->markInvoiced($so);

            // Post General Ledger entries
            $this->journalService->recordSalesInvoice($invoice);

            return $invoice;
        });
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(SalesInvoice $invoice, PaymentData $data, int $userId): SalesInvoice
    {
        if ($invoice->isFullyPaid()) {
            throw new RuntimeException("Invoice {$invoice->invoice_number} is already fully paid.");
        }

        $outstanding = $invoice->outstanding_amount;

        if ((float) $data->amount > $outstanding + 0.01) {
            throw new RuntimeException(
                "Payment amount ({$data->amount}) exceeds outstanding balance ({$outstanding})."
            );
        }

        return DB::transaction(function () use ($invoice, $data, $userId) {
            /** @var Payment $payment */
            $payment = $this->paymentRepository->create(array_merge($data->toArray(), [
                'sales_invoice_id' => $invoice->id,
                'created_by' => $userId,
            ]));

            // Post General Ledger entries
            $this->journalService->recordSalesPayment($payment);

            $newPaidAmount = (float) $invoice->paid_amount + (float) $data->amount;
            $newStatus = $newPaidAmount >= (float) $invoice->amount
                ? SalesInvoiceStatus::Paid
                : SalesInvoiceStatus::PartiallyPaid;

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            return $invoice->fresh();
        });
    }
}
