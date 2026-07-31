<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Finance\CompanyPayableService;
use App\Services\Finance\JournalService;
use App\Support\FinanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceV2PaymentsController extends Controller
{
    public function __construct(
        private readonly CompanyPayableService $companyPayables,
        private readonly JournalService $journalService,
    ) {}

    public function clientPaymentsIndex(Request $request): View
    {
        abort_unless(FinanceAccess::canViewPayments($request->user()), 403);

        $clients = Client::query()->active()->withCount('shops')->orderBy('name')->get();
        $payables = $this->companyPayables->openPayables();

        return view('admin.finance-v2.client-payments.index', [
            'date' => $this->date($request),
            'clients' => $clients,
            'payables' => $payables,
            'pending_count' => $payables->where('company_payable_status', 'pending')->count(),
        ]);
    }

    public function clientShopShow(Request $request, Client $client, Shop $shop): View
    {
        abort_unless(FinanceAccess::canViewPayments($request->user()), 403);
        abort_unless((int) $shop->client_id === (int) $client->id, 404);

        $payables = $this->companyPayables->openPayables($shop);
        $payments = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->limit(40)
            ->get();

        return view('admin.finance-v2.client-payments.shop', [
            'date' => $this->date($request),
            'client' => $client,
            'shop' => $shop,
            'payables' => $payables,
            'payments' => $payments,
        ]);
    }

    public function companyPayablesIndex(Request $request): View
    {
        abort_unless(FinanceAccess::canViewCompanyPayables($request->user()), 403);

        return view('admin.finance-v2.company-payables.index', [
            'date' => $this->date($request),
            'payables' => $this->companyPayables->openPayables(),
        ]);
    }

    public function companyPayableShow(Request $request, ShopAccountingEntryLine $line): View
    {
        abort_unless(FinanceAccess::canViewCompanyPayables($request->user()), 403);
        abort_unless($line->funding_source === ShopAccountingEntryLine::FundingCompany, 404);

        $line->loadMissing(['entry.shop.client', 'category', 'settlements.creator', 'approvedBy', 'rejectedBy']);

        $shopPayments = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $line->entry?->shop_id)
            ->where('status', 'approved')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.finance-v2.company-payables.show', [
            'date' => $this->date($request),
            'line' => $line,
            'shopPayments' => $shopPayments,
        ]);
    }

    public function approveCompanyPayable(Request $request, ShopAccountingEntryLine $line): RedirectResponse
    {
        abort_unless(FinanceAccess::canReviewCompanyPayables($request->user()), 403);

        $validated = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            $this->companyPayables->approve($line, (int) $request->user()->id, $validated['approved_amount'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Company payable approved.');
    }

    public function rejectCompanyPayable(Request $request, ShopAccountingEntryLine $line): RedirectResponse
    {
        abort_unless(FinanceAccess::canReviewCompanyPayables($request->user()), 403);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->companyPayables->reject($line, (int) $request->user()->id, $validated['rejection_reason']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Company payable rejected.');
    }

    public function settleAdjust(Request $request, ShopAccountingEntryLine $line): RedirectResponse
    {
        abort_unless(FinanceAccess::canSettleCompanyPayables($request->user()), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'shop_invoice_payment_request_id' => ['required', 'integer', 'exists:shop_invoice_payment_requests,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::query()->findOrFail((int) $validated['shop_invoice_payment_request_id']);

        try {
            $this->companyPayables->settleAgainstShopPayment(
                $line,
                $paymentRequest,
                (float) $validated['amount'],
                (int) $request->user()->id,
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Company payable adjusted against shop payment.');
    }

    public function settleDirect(Request $request, ShopAccountingEntryLine $line): RedirectResponse
    {
        abort_unless(FinanceAccess::canSettleCompanyPayables($request->user()), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['required', 'string', Rule::in(['cash', 'bank', 'upi', 'cheque'])],
            'payee' => ['nullable', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'settlement_date' => ['nullable', 'date'],
        ]);

        try {
            $this->companyPayables->settleDirectPayment(
                $line,
                (float) $validated['amount'],
                (int) $request->user()->id,
                $validated,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Direct company payment recorded.');
    }

    public function directPaymentsIndex(Request $request): View
    {
        abort_unless(FinanceAccess::canViewPayments($request->user()), 403);

        $invoices = PurchaseInvoice::query()
            ->with(['supplier', 'payments'])
            ->whereRaw('amount > COALESCE(paid_amount, 0) + COALESCE(discount_amount, 0)')
            ->latest('id')
            ->limit(80)
            ->get();

        return view('admin.finance-v2.direct-payments.index', [
            'date' => $this->date($request),
            'invoices' => $invoices,
        ]);
    }

    public function directPaymentsCreate(Request $request, PurchaseInvoice $invoice): View
    {
        abort_unless(FinanceAccess::canCreatePayments($request->user()), 403);
        $invoice->loadMissing(['supplier', 'payments']);

        $paid = round((float) ($invoice->payments->sum('amount') ?: $invoice->paid_amount), 2);
        $discount = round((float) ($invoice->payments->sum('discount_amount') ?: $invoice->discount_amount), 2);
        $outstanding = round(max(0, (float) $invoice->amount - $paid - $discount), 2);

        return view('admin.finance-v2.direct-payments.create', [
            'date' => $this->date($request),
            'invoice' => $invoice,
            'paid' => $paid,
            'outstanding' => $outstanding,
        ]);
    }

    public function directPaymentsStore(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        abort_unless(FinanceAccess::canCreatePayments($request->user()) || FinanceAccess::canSettlePayments($request->user()), 403);

        $invoice->loadMissing('payments');
        $paid = round((float) ($invoice->payments->sum('amount') ?: $invoice->paid_amount), 2);
        $discount = round((float) ($invoice->payments->sum('discount_amount') ?: $invoice->discount_amount), 2);
        $outstanding = round(max(0, (float) $invoice->amount - $paid - $discount), 2);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.$outstanding],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank', 'upi', 'cheque', 'online'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = round((float) $validated['amount'], 2);

        $payment = $invoice->payments()->create([
            'supplier_id' => $invoice->supplier_id,
            'payment_date' => $validated['payment_date'],
            'amount' => $amount,
            'discount_amount' => 0,
            'payment_method' => $validated['payment_method'],
            'payment_paid_by' => 'company',
            'note' => $validated['notes'] ?? $validated['reference'] ?? 'Finance V2 direct payment',
            'created_by' => $request->user()->id,
        ]);

        $invoice->forceFill([
            'paid_amount' => round($paid + $amount, 2),
            'payment_status' => ($paid + $amount + $discount) >= (float) $invoice->amount - 0.0001 ? 'paid' : 'partial',
        ])->save();

        $this->journalService->recordGreenLeafDirectPurchasePayment(
            invoice: $invoice->fresh(['purchaserCart', 'supplier']),
            amount: $amount,
            userId: (int) $request->user()->id,
            sourceEvent: 'finance-v2-direct-payment:'.$payment->id,
            paymentMode: $validated['payment_method'],
        );

        return redirect()
            ->route('admin.finance-v2.direct-payments.index', ['date' => $this->date($request)->toDateString()])
            ->with('success', 'Direct payment recorded against bill '.$invoice->invoice_number.'.');
    }

    private function date(Request $request): Carbon
    {
        return Carbon::parse($request->input('date', today()->toDateString()));
    }
}
