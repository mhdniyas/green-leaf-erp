<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\Finance\FinanceV2DashboardService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Support\FinanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceV2Controller extends Controller
{
    public function __construct(
        private readonly FinanceV2DashboardService $financeV2,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function dashboard(Request $request): View
    {
        $this->ensureDashboardAccess($request);

        return view('admin.finance-v2.dashboard', $this->financeV2->dashboard($this->date($request)));
    }

    public function greenLeaf(Request $request, string $section): View
    {
        $this->ensureDashboardAccess($request);
        abort_unless(in_array($section, ['purchase', 'expense', 'salary', 'credit-loan', 'balance'], true), 404);

        return view('admin.finance-v2.green-leaf-section', $this->financeV2->greenLeafSection($section, $this->date($request)));
    }

    public function clientsIndex(Request $request): View
    {
        $this->ensureDashboardAccess($request);
        $date = $this->date($request);
        $payload = $this->financeV2->dashboard($date);

        return view('admin.finance-v2.clients-index', [
            'date' => $payload['date'],
            'month_start' => $payload['month_start'],
            'month_end' => $payload['month_end'],
            'client_summaries' => $payload['client_summaries'],
            'company_position' => $payload['company_position'],
        ]);
    }

    public function clientShow(Request $request, Client $client): View
    {
        $this->ensureDashboardAccess($request);

        return view('admin.finance-v2.client', $this->financeV2->clientDashboard($client, $this->date($request)));
    }

    public function clientSection(Request $request, Client $client, string $section): View
    {
        $this->ensureDashboardAccess($request);
        abort_unless(in_array($section, ['purchase', 'expense', 'salary', 'credit-loan', 'balance'], true), 404);

        return view('admin.finance-v2.client-section', $this->financeV2->clientSection($client, $section, $this->date($request)));
    }

    public function aishwaryaVeg(Request $request): RedirectResponse
    {
        $this->ensureDashboardAccess($request);
        $client = $this->legacyAishwaryaClient();

        abort_unless($client instanceof Client, 404);

        return redirect()->route('admin.finance-v2.clients.show', [
            'client' => $client,
            'date' => $this->date($request)->toDateString(),
        ]);
    }

    public function aishwaryaVegSection(Request $request, string $section): RedirectResponse
    {
        $this->ensureDashboardAccess($request);
        $client = $this->legacyAishwaryaClient();

        abort_unless($client instanceof Client, 404);

        return redirect()->route('admin.finance-v2.clients.section', [
            'client' => $client,
            'section' => $section,
            'date' => $this->date($request)->toDateString(),
        ]);
    }

    public function shop(Request $request, Shop $shop): View
    {
        $this->ensureDashboardAccess($request);

        return view('admin.finance-v2.shop', $this->financeV2->shopDashboard($shop, $this->date($request)));
    }

    public function reports(Request $request): View
    {
        $this->ensureDashboardAccess($request);

        return view('admin.finance-v2.reports', $this->financeV2->reports($this->date($request)));
    }

    public function payments(Request $request): View
    {
        $this->ensurePaymentsView($request);

        return view('admin.finance-v2.payments.index', $this->financeV2->payments($this->date($request)));
    }

    public function createPayment(Request $request): View
    {
        abort_unless(FinanceAccess::canCreatePayments($request->user()), 403);

        return view('admin.finance-v2.payments.create', [
            ...$this->financeV2->createPayment($this->date($request)),
            'prefill_shop_id' => $request->integer('shop_id') ?: null,
            'prefill_requested_amount' => $request->input('requested_amount'),
            'prefill_payment_method' => $request->input('payment_method', 'cash'),
        ]);
    }

    public function shopPaymentContext(Request $request, Shop $shop): JsonResponse
    {
        abort_unless(FinanceAccess::canCreatePayments($request->user()), 403);

        $amount = round((float) $request->input('amount', 0), 2);

        return response()->json(
            $this->financeV2->shopPaymentCreateContext($shop, $this->date($request), $amount)
        );
    }

    public function storePayment(Request $request): RedirectResponse
    {
        abort_unless(FinanceAccess::canCreatePayments($request->user()), 403);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'requested_amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'online_upi', 'cheque'])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_date' => ['required', 'date'],
            'cheque_bank_name' => ['nullable', 'required_if:payment_method,cheque', 'string', 'max:120'],
            'cheque_date' => ['nullable', 'required_if:payment_method,cheque', 'date'],
            'shop_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shop = Shop::query()->findOrFail((int) $validated['shop_id']);
        $invoice = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->where('balance_amount', '>', 0)
            ->oldest('business_date')
            ->oldest('id')
            ->first();

        $paymentRequest = ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice?->id,
            'shop_id' => $shop->id,
            'requested_by' => $request->user()?->id,
            'request_type' => $invoice instanceof ShopInvoice ? 'admin_v2' : 'shop_balance',
            'payment_method' => $validated['payment_method'],
            'payment_reference' => filled($validated['payment_reference'] ?? null) ? trim((string) $validated['payment_reference']) : null,
            'payment_date' => $validated['payment_date'],
            'cheque_status' => $validated['payment_method'] === 'cheque' ? 'pending' : null,
            'cheque_bank_name' => $validated['cheque_bank_name'] ?? null,
            'cheque_date' => $validated['cheque_date'] ?? null,
            'requested_amount' => round((float) $validated['requested_amount'], 2),
            'applied_amount' => 0,
            'credit_amount' => 0,
            'status' => 'pending',
            'shop_note' => filled($validated['shop_note'] ?? null) ? trim((string) $validated['shop_note']) : 'Admin entered Finance V2 payment.',
        ]);

        return redirect()
            ->route('admin.finance-v2.payments.show', [
                'paymentRequest' => $paymentRequest,
                'date' => $paymentRequest->payment_date?->toDateString(),
            ])
            ->with('success', 'Payment created. Review pending bills before approval.');
    }

    public function showPayment(Request $request, ShopInvoicePaymentRequest $paymentRequest): View
    {
        $this->ensurePaymentsView($request);

        return view('admin.finance-v2.payments.show', $this->financeV2->paymentShow(
            $paymentRequest,
            $this->date($request),
            $this->shopInvoiceService->allocationPreviewForShopPayment($paymentRequest),
        ));
    }

    public function approvePayment(Request $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        abort_unless(FinanceAccess::canApprovePayments($request->user()), 403);

        $validated = $request->validate([
            'admin_verified_amount' => ['required', 'numeric', 'gt:0'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($paymentRequest->payment_method === 'cheque' && $paymentRequest->cheque_status !== 'cleared') {
            $paymentRequest->update([
                'admin_verified_amount' => round((float) $validated['admin_verified_amount'], 2),
                'cheque_status' => $paymentRequest->cheque_status ?: 'deposited',
                'admin_note' => filled($validated['admin_note'] ?? null) ? trim((string) $validated['admin_note']) : null,
            ]);

            return back()->withErrors(['cheque_status' => 'Cheque payments can only be approved after the cheque is cleared.']);
        }

        try {
            $this->shopInvoiceService->reviewPaymentRequestWithAmount(
                $paymentRequest,
                'approve',
                (int) $request->user()->id,
                $validated['admin_note'] ?? null,
                $validated['admin_verified_amount'],
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('admin.finance-v2.payments.show', ['paymentRequest' => $paymentRequest])
            ->with('success', 'Payment approved and allocated.');
    }

    public function rejectPayment(Request $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        abort_unless(FinanceAccess::canRejectPayments($request->user()), 403);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->shopInvoiceService->reviewPaymentRequestWithAmount(
                $paymentRequest,
                'reject',
                (int) $request->user()->id,
                $validated['admin_note'],
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('admin.finance-v2.payments.show', ['paymentRequest' => $paymentRequest])
            ->with('success', 'Payment rejected.');
    }

    public function updateCheque(Request $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        abort_unless(FinanceAccess::canApprovePayments($request->user()) || FinanceAccess::canRejectPayments($request->user()), 403);

        abort_unless($paymentRequest->payment_method === 'cheque', 404);

        $validated = $request->validate([
            'cheque_status' => ['required', 'string', Rule::in(['pending', 'deposited', 'cleared', 'bounced'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'cheque_status' => $validated['cheque_status'],
            'admin_note' => filled($validated['admin_note'] ?? null) ? trim((string) $validated['admin_note']) : $paymentRequest->admin_note,
        ];

        if ($validated['cheque_status'] === 'bounced') {
            $payload['status'] = 'rejected';
            $payload['reviewed_by'] = $request->user()?->id;
            $payload['reviewed_at'] = now();
        }

        $paymentRequest->update($payload);

        return back()->with('success', 'Cheque status updated.');
    }

    private function ensureDashboardAccess(Request $request): void
    {
        abort_unless(FinanceAccess::canViewDashboard($request->user()), 403);
    }

    private function ensurePaymentsView(Request $request): void
    {
        abort_unless(FinanceAccess::canViewPayments($request->user()), 403);
    }

    private function legacyAishwaryaClient(): ?Client
    {
        return Client::query()
            ->where('code', 'AISHWARYA_VEG')
            ->orWhere('name', 'Aishwarya Veg')
            ->first();
    }

    private function date(Request $request): Carbon
    {
        return Carbon::parse($request->input('date', today()->toDateString()));
    }
}
