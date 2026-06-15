<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaseInvoiceRequest;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseInvoice::class);

        $date = $this->resolveReportDate($request);
        $search = trim($request->string('search')->toString());
        $paymentFilter = $this->resolvePaymentFilter($request->string('payment_type')->toString());

        $invoices = $this->buildInvoiceReportQuery($date, $search, $paymentFilter)
            ->get();

        $vendorSections = $invoices
            ->groupBy(fn (PurchaseInvoice $invoice): string => (string) ($invoice->supplier_id ?? 'unassigned'))
            ->map(function (Collection $vendorInvoices): array {
                /** @var PurchaseInvoice $latestInvoice */
                $latestInvoice = $vendorInvoices->sortByDesc('created_at')->first();
                $vendor = $latestInvoice->supplier;
                $totalAmount = round((float) $vendorInvoices->sum('amount'), 2);
                $paidAmount = round((float) $vendorInvoices->sum('paid_amount'), 2);
                $outstandingAmount = round(max(0, $totalAmount - $paidAmount), 2);
                $creditInvoices = $vendorInvoices
                    ->filter(fn (PurchaseInvoice $invoice): bool => strcasecmp((string) $invoice->payment_method, 'Credit') === 0)
                    ->count();

                return [
                    'vendor' => $vendor,
                    'invoice_count' => $vendorInvoices->count(),
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'outstanding_amount' => $outstandingAmount,
                    'credit_invoices' => $creditInvoices,
                    'current_status' => $outstandingAmount > 0 || $creditInvoices > 0 ? 'Attention Needed' : 'Settled',
                    'latest_invoice' => $latestInvoice,
                    'invoices' => $vendorInvoices->sortByDesc('created_at')->values(),
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();

        $summary = [
            'vendor_count' => $vendorSections->count(),
            'invoice_count' => $invoices->count(),
            'total_amount' => round((float) $invoices->sum('amount'), 2),
            'paid_amount' => round((float) $invoices->sum('paid_amount'), 2),
            'outstanding_amount' => round(max(0, (float) $invoices->sum('amount') - (float) $invoices->sum('paid_amount')), 2),
        ];

        return view('purchasing.invoices.index', [
            'date' => $date->format('Y-m-d'),
            'invoices' => $invoices,
            'vendorSections' => $vendorSections,
            'summary' => $summary,
            'search' => $search,
            'paymentFilter' => $paymentFilter,
            'canManageSuppliers' => $request->user()->hasRole('admin') || $request->user()->hasRole('purchase') || $request->user()->can('purchasing.supplier.update'),
        ]);
    }

    public function vendorReport(Request $request, Supplier $supplier): View
    {
        Gate::authorize('viewAny', PurchaseInvoice::class);

        $date = $this->resolveReportDate($request);
        $search = trim($request->string('search')->toString());
        $paymentFilter = $this->resolvePaymentFilter($request->string('payment_type')->toString());

        $historyQuery = $this->buildVendorHistoryQuery($supplier, $date, $search, $paymentFilter);
        $historyInvoices = (clone $historyQuery)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $historySummaryInvoices = (clone $historyQuery)->get();
        $latestInvoice = $historySummaryInvoices->sortByDesc('created_at')->first();
        $totalAmount = round((float) $historySummaryInvoices->sum('amount'), 2);
        $paidAmount = round((float) $historySummaryInvoices->sum('paid_amount'), 2);
        $outstandingAmount = round(max(0, $totalAmount - $paidAmount), 2);

        return view('purchasing.invoices.vendor-report', [
            'date' => $date->format('Y-m-d'),
            'vendor' => $supplier,
            'historyInvoices' => $historyInvoices,
            'historySummary' => [
                'invoice_count' => $historySummaryInvoices->count(),
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => $outstandingAmount,
                'credit_invoices' => $historySummaryInvoices->filter(fn (PurchaseInvoice $invoice): bool => strcasecmp((string) $invoice->payment_method, 'Credit') === 0)->count(),
                'latest_invoice' => $latestInvoice,
                'current_status' => $outstandingAmount > 0 || $supplier->credit_approved ? 'Active' : 'Settled',
            ],
            'search' => $search,
            'paymentFilter' => $paymentFilter,
            'canManageSuppliers' => $request->user()->hasRole('admin') || $request->user()->hasRole('purchase') || $request->user()->can('purchasing.supplier.update'),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', PurchaseInvoice::class);

        $goodsReceivedReference = trim((string) $request->string('goods_received'));

        if ($goodsReceivedReference === '' && $request->filled('goods_received_id')) {
            $legacyGoodsReceivedId = $request->integer('goods_received_id');

            if ($legacyGoodsReceivedId > 0) {
                /** @var GoodsReceived $legacyGrn */
                $legacyGrn = GoodsReceived::findOrFail($legacyGoodsReceivedId);

                return redirect()->route('purchasing.invoices.create', [
                    'goods_received' => $legacyGrn,
                ]);
            }
        }

        if ($goodsReceivedReference === '') {
            return redirect()->route('purchasing.grns.index')
                ->with('error', 'Please select a Goods Received Note to create an invoice.');
        }

        /** @var GoodsReceived $grn */
        $grn = GoodsReceived::query()
            ->where((new GoodsReceived)->getRouteKeyName(), $goodsReceivedReference)
            ->when(
                GoodsReceived::hasPublicUuidColumn(),
                fn ($query) => $query->orWhere('grn_number', $goodsReceivedReference)
            )
            ->firstOrFail();
        $grn->load(['purchaseOrder.supplier', 'items.product']);

        // Check if there is an existing invoice for this GRN
        $existingInvoice = PurchaseInvoice::where('goods_received_id', $grn->id)->first();
        if ($existingInvoice) {
            return redirect()->route('purchasing.invoices.show', $existingInvoice)
                ->with('warning', 'An invoice has already been created for this Goods Received Note.');
        }

        $suggestedInvoiceNumber = 'PINV-'.now()->format('Ymd-Hisv');

        return view('purchase-manager.invoices.create', compact('grn', 'suggestedInvoiceNumber'));
    }

    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->service->create(PurchaseInvoiceData::fromRequest($request));

        return redirect()->route('purchasing.invoices.show', $invoice)
            ->with('success', 'Purchase invoice created and matched successfully.');
    }

    public function show(PurchaseInvoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'supplier',
            'purchaserCart.items.product',
            'goodsReceived.items.product',
            'goodsReceived.purchaseOrder',
            'purchaserCart',
        ]);

        return view('purchasing.invoices.show', [
            'invoice' => $invoice,
            'paymentUpdateRouteName' => 'purchasing.invoices.update-payment',
            'billPdfRouteName' => 'purchasing.invoices.pdf',
            'backRouteName' => 'purchasing.invoices.index',
            'backRouteParameters' => ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')],
            'financeAudience' => 'manager',
        ]);
    }

    public function pdf(PurchaseInvoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'supplier',
            'purchaserCart.items.product',
            'goodsReceived.items.product',
            'goodsReceived.purchaseOrder',
        ]);

        return view('purchasing.invoices.pdf', compact('invoice'));
    }

    public function updateStatus(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,paid'],
        ]);

        $this->service->updateStatus($invoice, $request->string('status')->toString());

        return redirect()->route('purchasing.invoices.show', $invoice)
            ->with('success', 'Purchase invoice status updated successfully.');
    }

    public function updatePayment(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:Cash,Online,GPay,Credit'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $updatedInvoice = $this->service->updatePayment($invoice, [
            'payment_method' => $validated['payment_method'],
            'paid_amount' => (float) $validated['paid_amount'],
            'payment_note' => $validated['payment_note'] ?? null,
            'payment_details' => $validated['payment_details'] ?? null,
        ]);

        $remainingBalance = max(0, round((float) $updatedInvoice->amount - (float) $updatedInvoice->paid_amount, 2));
        $message = $remainingBalance > 0 || $updatedInvoice->payment_method === 'Credit'
            ? 'Payment updated. Invoice is not complete yet.'
            : 'Payment completed successfully.';

        return redirect()->back()->with('success', $message);
    }

    private function resolveReportDate(Request $request): Carbon
    {
        return Carbon::parse($request->input('date', now()->format('Y-m-d')));
    }

    private function resolvePaymentFilter(string $paymentFilter): string
    {
        return in_array($paymentFilter, ['all', 'cash', 'credit', 'gpay', 'online', 'both'], true)
            ? $paymentFilter
            : 'all';
    }

    private function buildInvoiceReportQuery(Carbon $date, string $search, string $paymentFilter): Builder
    {
        $query = PurchaseInvoice::query()
            ->with([
                'goodsReceived',
                'supplier.creditApprovalRequestedBy',
                'supplier.creditApprovedBy',
                'purchaserCart',
            ]);

        $this->applyDailyDateFilter($query, $date);
        $this->applySearchFilter($query, $search);
        $this->applyPaymentFilter($query, $paymentFilter);

        return $query->orderByDesc('created_at');
    }

    private function buildVendorHistoryQuery(Supplier $supplier, Carbon $date, string $search, string $paymentFilter): Builder
    {
        $query = PurchaseInvoice::query()
            ->with(['goodsReceived', 'purchaserCart', 'supplier'])
            ->whereBelongsTo($supplier);

        $this->applyHistoryDateFilter($query, $date);
        $this->applySearchFilter($query, $search);
        $this->applyPaymentFilter($query, $paymentFilter);

        return $query;
    }

    private function applyDailyDateFilter(Builder $query, Carbon $date): void
    {
        $query->where(function (Builder $invoiceQuery) use ($date): void {
            $invoiceQuery
                ->whereHas('purchaserCart', function (Builder $purchaserCartQuery) use ($date): void {
                    $purchaserCartQuery->whereDate('business_date', $date);
                })
                ->orWhere(function (Builder $manualInvoiceQuery) use ($date): void {
                    $manualInvoiceQuery
                        ->whereNull('purchaser_cart_id')
                        ->whereDate('created_at', $date);
                });
        });
    }

    private function applyHistoryDateFilter(Builder $query, Carbon $date): void
    {
        $query->where(function (Builder $invoiceQuery) use ($date): void {
            $invoiceQuery
                ->whereHas('purchaserCart', function (Builder $purchaserCartQuery) use ($date): void {
                    $purchaserCartQuery->whereDate('business_date', '<=', $date);
                })
                ->orWhere(function (Builder $manualInvoiceQuery) use ($date): void {
                    $manualInvoiceQuery
                        ->whereNull('purchaser_cart_id')
                        ->whereDate('created_at', '<=', $date);
                });
        });
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $invoiceQuery) use ($search): void {
            $invoiceQuery
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('payment_note', 'like', "%{$search}%")
                ->orWhere('payment_details', 'like', "%{$search}%")
                ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                    $supplierQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                })
                ->orWhereHas('purchaserCart', function (Builder $cartQuery) use ($search): void {
                    $cartQuery
                        ->where('cart_number', 'like', "%{$search}%")
                        ->orWhere('bill_number', 'like', "%{$search}%");
                });
        });
    }

    private function applyPaymentFilter(Builder $query, string $paymentFilter): void
    {
        match ($paymentFilter) {
            'cash' => $query->where('payment_method', 'Cash'),
            'credit' => $query->where('payment_method', 'Credit'),
            'online' => $query->where('payment_method', 'Online'),
            'gpay' => $query->where(function (Builder $paymentQuery): void {
                $paymentQuery
                    ->where('payment_method', 'GPay')
                    ->orWhere(function (Builder $legacyOnlineQuery): void {
                        $legacyOnlineQuery
                            ->where('payment_method', 'Online')
                            ->where(function (Builder $gpayTextQuery): void {
                                $gpayTextQuery
                                    ->where('payment_note', 'like', '%gpay%')
                                    ->orWhere('payment_details', 'like', '%gpay%');
                            });
                    });
            }),
            'both' => $query->whereIn('payment_method', ['Cash', 'GPay']),
            default => null,
        };
    }
}
