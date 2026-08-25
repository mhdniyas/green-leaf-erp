<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Shop;
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

    public function createPayment(Request $request): RedirectResponse
    {
        abort_unless(FinanceAccess::canCreatePayments($request->user()), 403);

        $shop = Shop::query()->find($request->integer('shop_id'));
        if (! $shop instanceof Shop) {
            return redirect()
                ->route('admin.cashbook.all-shops')
                ->with('warning', 'Select a shop, then use Accept Payment from its Cashbook ledger page.');
        }

        return redirect()
            ->route('admin.cashbook.shop.accept-payment', ['shop' => $shop->public_uuid])
            ->with('warning', 'Shop payments are now recorded from the Cashbook shop workspace.');
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

        $validated = $request->validate(['shop_id' => ['required', 'integer', 'exists:shops,id']]);
        $shop = Shop::query()->findOrFail((int) $validated['shop_id']);

        return redirect()
            ->route('admin.cashbook.shop.accept-payment', ['shop' => $shop->public_uuid])
            ->with('warning', 'Shop payments are now recorded from the Cashbook shop workspace.');
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
