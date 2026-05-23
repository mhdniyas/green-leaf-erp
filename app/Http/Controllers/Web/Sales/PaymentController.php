<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\DTOs\Sales\PaymentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Sales\StorePaymentRequest;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $service,
    ) {}

    public function store(StorePaymentRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        try {
            $this->service->recordPayment(
                $invoice,
                PaymentData::fromRequest($request),
                $request->user()->id
            );

            return redirect()->route('sales.invoices.show', $invoice)
                ->with('success', 'Payment recorded successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->route('sales.invoices.show', $invoice)
                ->with('error', $e->getMessage());
        }
    }
}
