<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Enums\Purchasing\PurchaseCreditStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseCreditNoteRequest;
use App\Http\Requests\Purchasing\VoidPurchaseCreditNoteRequest;
use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaseCreditNoteService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseCreditNoteController extends Controller
{
    public function __construct(
        private readonly PurchaseCreditNoteService $creditNoteService,
    ) {}

    public function index(Request $request, PurchaserBusinessDayService $businessDayService): View|JsonResponse
    {
        $rawPreset = trim((string) $request->input('date_preset', ''));
        if ($rawPreset === '') {
            $rawPreset = ($request->filled('start_date') || $request->filled('end_date')) ? 'custom' : 'today';
        }

        $allowedPresets = ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'custom', 'all_time'];
        if (! in_array($rawPreset, $allowedPresets, true)) {
            $rawPreset = 'today';
        }

        $startDate = null;
        $endDate = null;
        $calendarToday = \Illuminate\Support\Carbon::now('Asia/Kolkata')->startOfDay();
        $today = $businessDayService->operationalDate(\Illuminate\Support\Carbon::now('Asia/Kolkata'));

        switch ($rawPreset) {
            case 'today':
                $startDate = $calendarToday->toDateString();
                $endDate = $today->toDateString();
                break;
            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                $startDate = $yesterday->toDateString();
                $endDate = $yesterday->toDateString();
                break;
            case 'last_7_days':
                $startDate = $today->copy()->subDays(6)->toDateString();
                $endDate = $today->toDateString();
                break;
            case 'last_30_days':
                $startDate = $today->copy()->subDays(29)->toDateString();
                $endDate = $today->toDateString();
                break;
            case 'this_month':
                $startDate = $today->copy()->startOfMonth()->toDateString();
                $endDate = $today->copy()->endOfMonth()->toDateString();
                break;
            case 'last_month':
                $lastMonth = $today->copy()->subMonthNoOverflow();
                $startDate = $lastMonth->copy()->startOfMonth()->toDateString();
                $endDate = $lastMonth->copy()->endOfMonth()->toDateString();
                break;
            case 'custom':
                $validatedDates = $request->validate([
                    'start_date' => ['nullable', 'date_format:Y-m-d'],
                    'end_date' => ['nullable', 'date_format:Y-m-d'],
                ]);
                $startDate = $validatedDates['start_date'] ?? null;
                $endDate = $validatedDates['end_date'] ?? null;
                if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
                    throw ValidationException::withMessages([
                        'end_date' => 'To Date must be on or after From Date.',
                    ]);
                }
                break;
            case 'all_time':
                $startDate = null;
                $endDate = null;
                break;
        }

        $rawPurchaser = trim((string) $request->input('purchaser_id', ''));
        $purchaserFilter = $rawPurchaser === 'unassigned'
            ? 'unassigned'
            : (is_numeric($rawPurchaser) && (int) $rawPurchaser > 0 ? (string) ((int) $rawPurchaser) : '');

        $filters = [
            'supplier_id' => $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null,
            'purchaser_id' => $purchaserFilter,
            'date_preset' => $rawPreset,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $request->input('status'),
            'utilization_status' => $request->input('utilization_status'),
            'reason' => $request->input('reason'),
            'money_effect' => $request->input('money_effect'),
            'q' => trim((string) $request->input('q', '')),
        ];

        $baseQuery = PurchaseCreditNote::query()
            ->when($filters['supplier_id'] !== null, fn (Builder $q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when($filters['purchaser_id'] === 'unassigned', function (Builder $q): void {
                $q->whereDoesntHave('purchaseInvoice', fn (Builder $iq) => $iq->whereNotNull('purchaser_cart_id'))
                    ->whereDoesntHave('items', fn (Builder $itq) => $itq->whereHas('purchaserCartItem.cart'));
            })
            ->when(is_numeric($filters['purchaser_id']), function (Builder $q) use ($filters): void {
                $pid = (int) $filters['purchaser_id'];
                $q->where(function (Builder $sub) use ($pid): void {
                    $sub->whereHas('purchaseInvoice.purchaserCart', fn (Builder $cq) => $cq->where('user_id', $pid))
                        ->orWhereHas('items.purchaserCartItem.cart', fn (Builder $cq) => $cq->where('user_id', $pid));
                });
            })
            ->when($filters['start_date'], fn (Builder $q) => $q->whereDate('credit_date', '>=', (string) $filters['start_date']))
            ->when($filters['end_date'], fn (Builder $q) => $q->whereDate('credit_date', '<=', (string) $filters['end_date']))
            ->when($filters['reason'], fn (Builder $q) => $q->where('reason', (string) $filters['reason']))
            ->when($filters['money_effect'], fn (Builder $q) => $q->where('money_effect', (string) $filters['money_effect']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $search = $filters['q'];
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('credit_note_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"));
                });
            });

        $hasV2Cols = Schema::hasColumn('purchase_credit_notes', 'utilized_amount');

        $summaryRow = (clone $baseQuery)
            ->where('status', PurchaseCreditStatus::Active->value)
            ->selectRaw(
                'COUNT(*) as active_count, '.
                'COALESCE(SUM(CASE WHEN money_effect = ? THEN total_amount ELSE 0 END), 0) as vendor_credit_total, '.
                'COALESCE(SUM(CASE WHEN money_effect = ? THEN total_amount ELSE 0 END), 0) as money_in_total, '.
                'COALESCE(SUM(CASE WHEN money_effect = ? THEN total_amount ELSE 0 END), 0) as money_out_total',
                [
                    PurchaseCreditMoneyEffect::VendorCredit->value,
                    PurchaseCreditMoneyEffect::MoneyIn->value,
                    PurchaseCreditMoneyEffect::MoneyOut->value,
                ]
            )
            ->toBase()
            ->first();

        // Current all-time available & utilized credit balances (kept separate from date-filtered transaction totals)
        $creditBalancesQuery = PurchaseCreditNote::query()
            ->when($filters['supplier_id'] !== null, fn (Builder $q) => $q->where('purchase_credit_notes.supplier_id', $filters['supplier_id']))
            ->where('purchase_credit_notes.status', PurchaseCreditStatus::Active->value)
            ->where('purchase_credit_notes.money_effect', PurchaseCreditMoneyEffect::VendorCredit->value);

        if ($hasV2Cols) {
            $creditBalanceRow = $creditBalancesQuery
                ->leftJoin('vendor_advances as va', 'va.id', '=', 'purchase_credit_notes.vendor_advance_id')
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN purchase_credit_notes.accounting_version = 'v2' THEN (CASE WHEN purchase_credit_notes.total_amount > purchase_credit_notes.utilized_amount THEN purchase_credit_notes.total_amount - purchase_credit_notes.utilized_amount ELSE 0 END) WHEN va.amount_remaining > 0 THEN va.amount_remaining ELSE 0 END), 0) as available_credits, ".
                    "COALESCE(SUM(CASE WHEN purchase_credit_notes.accounting_version = 'v2' THEN purchase_credit_notes.utilized_amount ELSE (CASE WHEN purchase_credit_notes.total_amount > COALESCE(va.amount_remaining, 0) THEN purchase_credit_notes.total_amount - COALESCE(va.amount_remaining, 0) ELSE 0 END) END), 0) as utilized_credits, ".
                    "COALESCE(SUM(CASE WHEN (purchase_credit_notes.accounting_version = 'v2' AND purchase_credit_notes.total_amount > purchase_credit_notes.utilized_amount) OR (purchase_credit_notes.accounting_version != 'v2' AND va.amount_remaining > 0) THEN 1 ELSE 0 END), 0) as open_credit_notes_count"
                )
                ->toBase()
                ->first();
        } else {
            $creditBalanceRow = $creditBalancesQuery
                ->leftJoin('vendor_advances as va', 'va.id', '=', 'purchase_credit_notes.vendor_advance_id')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN va.amount_remaining > 0 THEN va.amount_remaining ELSE 0 END), 0) as available_credits, '.
                    'COALESCE(SUM(CASE WHEN purchase_credit_notes.total_amount > COALESCE(va.amount_remaining, 0) THEN purchase_credit_notes.total_amount - COALESCE(va.amount_remaining, 0) ELSE 0 END), 0) as utilized_credits, '.
                    'COALESCE(SUM(CASE WHEN va.amount_remaining > 0 THEN 1 ELSE 0 END), 0) as open_credit_notes_count'
                )
                ->toBase()
                ->first();
        }

        $summary = [
            'vendor_credit_total' => round((float) ($summaryRow->vendor_credit_total ?? 0), 2),
            'available_credits' => round((float) ($creditBalanceRow->available_credits ?? 0), 2),
            'utilized_credits' => round((float) ($creditBalanceRow->utilized_credits ?? 0), 2),
            'open_credit_notes_count' => (int) ($creditBalanceRow->open_credit_notes_count ?? 0),
            'money_in_total' => round((float) ($summaryRow->money_in_total ?? 0), 2),
            'money_out_total' => round((float) ($summaryRow->money_out_total ?? 0), 2),
            'total_count' => (int) ($summaryRow->active_count ?? 0),
        ];

        $creditNotes = (clone $baseQuery)
            ->when($filters['status'], fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when($filters['utilization_status'], function (Builder $q) use ($filters, $hasV2Cols): void {
                $uStatus = (string) $filters['utilization_status'];
                if ($uStatus === 'voided') {
                    $q->where('status', PurchaseCreditStatus::Voided->value);

                    return;
                }

                $q->where('status', PurchaseCreditStatus::Active->value)
                    ->where('money_effect', PurchaseCreditMoneyEffect::VendorCredit->value);

                if (! $hasV2Cols) {
                    return;
                }

                if ($uStatus === 'open') {
                    $q->where('accounting_version', 'v2')->where('utilized_amount', '<=', 0.009);
                } elseif ($uStatus === 'partially_utilized') {
                    $q->where('accounting_version', 'v2')
                        ->where('utilized_amount', '>', 0.009)
                        ->whereColumn('utilized_amount', '<', 'total_amount');
                } elseif ($uStatus === 'fully_utilized') {
                    $q->where(function (Builder $sub): void {
                        $sub->where(fn (Builder $v2) => $v2->where('accounting_version', 'v2')->whereColumn('utilized_amount', '>=', 'total_amount'))
                            ->orWhere(fn (Builder $v1) => $v1->where('accounting_version', '!=', 'v2'));
                    });
                }
            })
            ->with([
                'supplier:id,public_uuid,name',
                'purchaseInvoice:id,invoice_number,purchaser_cart_id',
                'purchaseInvoice.purchaserCart:id,user_id',
                'purchaseInvoice.purchaserCart.user:id,name',
                'vendorAdvance:id,amount_remaining',
            ])
            ->withCount('items')
            ->orderByDesc('credit_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $hasActiveFilters = $filters['supplier_id'] !== null
            || $filters['purchaser_id'] !== ''
            || $filters['date_preset'] !== 'today'
            || ! empty($filters['status'])
            || ! empty($filters['utilization_status'])
            || ! empty($filters['reason'])
            || ! empty($filters['money_effect'])
            || $filters['q'] !== '';

        if ($request->ajax() || $request->wantsJson() || $request->boolean('async')) {
            return response()->json([
                'summary' => $summary,
                'filters' => $filters,
                'has_active_filters' => $hasActiveFilters,
                'table_html' => view('admin.cashbook.purchase-credit-notes.partials.table', compact('creditNotes'))->render(),
            ]);
        }

        $selectedSupplierModel = $filters['supplier_id'] !== null
            ? Supplier::query()->select(['id', 'public_uuid', 'name', 'contact', 'mobile_number'])->find($filters['supplier_id'])
            : null;
        $selectedSupplier = $selectedSupplierModel ? $this->serializeVendor($selectedSupplierModel) : null;

        $selectedPurchaser = is_numeric($filters['purchaser_id'])
            ? User::query()->select(['id', 'name', 'email'])->find((int) $filters['purchaser_id'])
            : null;

        $statuses = PurchaseCreditStatus::cases();
        $reasons = PurchaseCreditReason::cases();
        $moneyEffects = PurchaseCreditMoneyEffect::cases();

        return view('admin.cashbook.purchase-credit-notes.index', compact(
            'creditNotes',
            'summary',
            'filters',
            'hasActiveFilters',
            'selectedSupplier',
            'selectedPurchaser',
            'statuses',
            'reasons',
            'moneyEffects',
        ));
    }

    public function globalCreate(Request $request): View
    {
        $supplierUuid = trim((string) $request->query('supplier', ''));
        if ($supplierUuid !== '') {
            $supplier = Supplier::query()
                ->where('public_uuid', $supplierUuid)
                ->orWhere('id', is_numeric($supplierUuid) ? (int) $supplierUuid : 0)
                ->firstOrFail();

            return $this->create($request, $supplier);
        }

        return view('admin.cashbook.purchase-credit-notes.select-vendor');
    }

    public function searchVendors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $vendorsQuery = Supplier::query()
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            });

        if ($search !== '') {
            $lowerSearch = mb_strtolower($search);
            $vendorsQuery->orderByRaw(
                'CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 WHEN LOWER(name) LIKE ? THEN 2 ELSE 3 END',
                [$lowerSearch, "{$lowerSearch}%", "%{$lowerSearch}%"]
            );
        }

        $vendors = $vendorsQuery
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'public_uuid', 'name', 'contact', 'mobile_number']);

        return response()->json([
            'items' => $vendors->map(fn (Supplier $s): array => $this->serializeVendor($s))->values(),
        ]);
    }

    public function searchPurchasers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $purchasersQuery = User::query()
            ->where(function (Builder $q): void {
                $q->whereHas('roles', fn (Builder $rq) => $rq->where('name', 'purchaser'))
                    ->orWhereIn('id', function ($sub): void {
                        $sub->select('user_id')->from('purchaser_carts')->whereNotNull('user_id');
                    });
            })
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        if ($search !== '') {
            $lowerSearch = mb_strtolower($search);
            $purchasersQuery->orderByRaw(
                'CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 ELSE 2 END',
                [$lowerSearch, "{$lowerSearch}%"]
            );
        }

        $purchasers = $purchasersQuery
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'items' => $purchasers->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values(),
        ]);
    }

    public function vendorSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        $supplier = Supplier::query()
            ->select(['id', 'public_uuid', 'name', 'contact', 'mobile_number'])
            ->findOrFail((int) $validated['supplier_id']);

        return response()->json([
            'supplier' => $this->serializeVendor($supplier),
            'summary' => $this->creditNoteService->getVendorFinancialSummary($supplier),
            'more_details_url' => route('admin.cashbook.finance.purchase.vendors.show', [
                'supplier' => $supplier->public_uuid,
            ]),
        ]);
    }

    /**
     * @return array{id: int, public_uuid: string, name: string, primary_phone: ?string, secondary_phone: ?string}
     */
    private function serializeVendor(Supplier $supplier): array
    {
        $mobile = $this->cleanPhone($supplier->mobile_number);
        $contact = $this->cleanPhone($supplier->contact);

        $primaryPhone = $mobile !== '' ? $mobile : ($contact !== '' ? $contact : null);
        $secondaryPhone = null;

        if ($mobile !== '' && $contact !== '' && $this->normalizePhoneForCompare($mobile) !== $this->normalizePhoneForCompare($contact)) {
            $secondaryPhone = $contact;
        }

        return [
            'id' => $supplier->id,
            'public_uuid' => $supplier->public_uuid,
            'name' => $supplier->name,
            'primary_phone' => $primaryPhone,
            'secondary_phone' => $secondaryPhone,
        ];
    }

    private function cleanPhone(?string $phone): string
    {
        return trim((string) ($phone ?? ''));
    }

    private function normalizePhoneForCompare(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, -10);
        } elseif (strlen($digits) > 10 && str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        return $digits;
    }

    public function create(Request $request, Supplier $supplier): View
    {
        $rawInvoices = PurchaseInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->notCancelled()
            ->with([
                'purchaserCart:id,business_date',
                'vendorSettlementAllocations',
                'purchaseCreditNotes' => fn ($q) => $q->active(),
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $invoices = $rawInvoices->map(function (PurchaseInvoice $inv): array {
            $gross = round((float) $inv->amount, 2);
            $discount = round((float) ($inv->discount_amount ?? 0), 2);
            $net = max(0.0, round($gross - $discount, 2));
            $outstanding = round((float) $inv->remainingBalance(), 2);
            $settled = max(0.0, round($net - $outstanding, 2));
            $date = $inv->purchaserCart?->business_date
                ? Carbon::parse($inv->purchaserCart->business_date)->format('d M Y')
                : ($inv->created_at ? $inv->created_at->format('d M Y') : '—');
            $rawDate = $inv->purchaserCart?->business_date
                ? Carbon::parse($inv->purchaserCart->business_date)->format('Y-m-d')
                : ($inv->created_at ? $inv->created_at->format('Y-m-d') : '');
            $paymentClass = (str_contains(strtolower((string) $inv->payment_method), 'cash')) ? 'Cash' : 'Credit';

            return [
                'id' => $inv->id,
                'public_uuid' => $inv->public_uuid,
                'invoice_number' => $inv->invoice_number,
                'date' => $date,
                'raw_date' => $rawDate,
                'payment_class' => $paymentClass,
                'payment_method' => $inv->payment_method ?: 'Credit',
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'net_amount' => $net,
                'settled_amount' => $settled,
                'outstanding_amount' => $outstanding,
            ];
        })->values()->all();

        $products = $supplier->relatedProducts();
        $products->loadMissing('orderUnits');

        $productCatalog = $products->map(function ($product): array {
            $baseUnit = trim((string) ($product->unit ?: 'kg'));
            $units = [
                [
                    'unit' => $baseUnit,
                    'label' => strtoupper($baseUnit).' (Base)',
                    'conversion_to_base' => 1.0,
                ],
            ];

            foreach ($product->orderUnits as $orderUnit) {
                $unitCode = trim((string) $orderUnit->unit);
                if ($unitCode === '' || $unitCode === $baseUnit) {
                    continue;
                }
                $units[] = [
                    'unit' => $unitCode,
                    'label' => trim((string) ($orderUnit->label ?: $unitCode))." ({$orderUnit->conversion_to_base} {$baseUnit})",
                    'conversion_to_base' => (float) $orderUnit->conversion_to_base,
                ];
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'base_unit' => $baseUnit,
                'units' => $units,
            ];
        })->values()->all();

        $vendorInfo = $this->serializeVendor($supplier);
        $vendorSummary = $this->creditNoteService->getVendorFinancialSummary($supplier);
        $defaultCreditDate = now('Asia/Kolkata')->toDateString();
        $submissionToken = (string) Str::uuid();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'account_type']);

        $reasons = PurchaseCreditReason::cases();
        $moneyEffects = PurchaseCreditMoneyEffect::cases();
        $inventoryActions = PurchaseCreditInventoryAction::cases();
        $wastageReasons = WastageReason::cases();
        $grades = ProductGrade::cases();
        $isV2Enabled = (bool) config('greenleaf.purchase_credit_notes_v2_enabled', false);

        return view('admin.cashbook.finance.purchase.credit-notes.create', compact(
            'supplier',
            'vendorInfo',
            'vendorSummary',
            'defaultCreditDate',
            'submissionToken',
            'invoices',
            'products',
            'productCatalog',
            'warehouses',
            'companyAccounts',
            'reasons',
            'moneyEffects',
            'inventoryActions',
            'wastageReasons',
            'grades',
            'isV2Enabled',
        ));
    }

    public function store(StorePurchaseCreditNoteRequest $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validated();
        $payload = array_merge($validated, [
            'supplier_id' => $supplier->id,
        ]);

        $creditNote = $this->creditNoteService->create($payload, $request->user());

        return redirect()
            ->route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplier->public_uuid])
            ->with('status', "Purchase Credit Note {$creditNote->credit_note_number} created successfully.");
    }

    public function show(Request $request, Supplier $supplier, PurchaseCreditNote $creditNote): View
    {
        abort_unless((int) $creditNote->supplier_id === (int) $supplier->id, 404);

        $creditNote->loadMissing([
            'items.product',
            'purchaseInvoice',
            'companyAccount',
            'warehouse',
            'vendorAdvance',
            'creditAllocations.settlement',
            'creditAllocations.purchaseInvoice',
            'journalEntry.transactions.account',
            'createdBy',
            'voidedBy',
        ]);

        return view('admin.cashbook.finance.purchase.credit-notes.show', compact('supplier', 'creditNote'));
    }

    public function void(VoidPurchaseCreditNoteRequest $request, Supplier $supplier, PurchaseCreditNote $creditNote): RedirectResponse
    {
        abort_unless((int) $creditNote->supplier_id === (int) $supplier->id, 404);

        $this->creditNoteService->void($creditNote, $request->validated('reason'), $request->user());

        return redirect()
            ->back()
            ->with('status', "Purchase Credit Note {$creditNote->credit_note_number} has been voided successfully.");
    }

    public function checkStock(Request $request, Supplier $supplier): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $inventoryAction = (string) $request->input('inventory_action', PurchaseCreditInventoryAction::Out->value);
        $deductStockForDamage = filter_var($request->input('deduct_stock_for_damage', true), FILTER_VALIDATE_BOOLEAN);
        $items = (array) $request->input('items', []);

        if ($warehouseId === null || empty($items)) {
            return response()->json([
                'has_shortage' => false,
                'shortages' => [],
            ]);
        }

        $stockCheck = $this->creditNoteService->checkStockAvailability(
            warehouseId: $warehouseId,
            items: $items,
            inventoryAction: $inventoryAction,
            deductStockForDamage: $deductStockForDamage,
        );

        $stockLedger = app(StockLedgerService::class);
        $itemsStock = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $grade = ProductGrade::tryResolve((string) ($item['grade'] ?? 'A')) ?? ProductGrade::GradeA;
            $key = "{$productId}_{$grade->value}";
            if (! array_key_exists($key, $itemsStock)) {
                $itemsStock[$key] = round((float) $stockLedger->availableStockForProduct($productId, $warehouseId, $grade), 3);
            }
        }

        return response()->json(array_merge($stockCheck, [
            'items_stock' => $itemsStock,
        ]));
    }

    public function returnableItems(Request $request, Supplier $supplier, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        abort_unless((int) $purchaseInvoice->supplier_id === (int) $supplier->id, 404);

        $purchaseInvoice->loadMissing([
            'purchaserCart.items.product:id,name,unit',
            'goodsReceived.items.product:id,name,unit',
            'purchaseCreditNotes' => fn ($q) => $q->active()->with('items'),
        ]);

        $creditedTotals = [];
        foreach ($purchaseInvoice->purchaseCreditNotes as $pcn) {
            foreach ($pcn->items as $pcnItem) {
                $key = ((int) $pcnItem->product_id).'|'.((string) $pcnItem->grade);
                $creditedTotals[$key] = ($creditedTotals[$key] ?? 0.0) + (float) $pcnItem->quantity;
            }
        }

        $lines = [];
        if ($purchaseInvoice->purchaserCart && $purchaseInvoice->purchaserCart->items->isNotEmpty()) {
            foreach ($purchaseInvoice->purchaserCart->items as $cartItem) {
                $grade = (string) ($cartItem->grade ?: 'A');
                $key = ((int) $cartItem->product_id).'|'.$grade;
                $purchasedQty = (float) $cartItem->quantity;
                $alreadyCredited = $creditedTotals[$key] ?? 0.0;
                $returnableQty = max(0.0, round($purchasedQty - $alreadyCredited, 3));

                $lines[] = [
                    'purchaser_cart_item_id' => $cartItem->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product?->name ?? "Product #{$cartItem->product_id}",
                    'unit' => $cartItem->product?->unit ?? 'kg',
                    'grade' => $grade,
                    'purchased_qty' => $purchasedQty,
                    'already_credited_qty' => $alreadyCredited,
                    'returnable_qty' => $returnableQty,
                    'unit_price' => (float) $cartItem->unit_price,
                ];
            }
        } elseif ($purchaseInvoice->goodsReceived && $purchaseInvoice->goodsReceived->items->isNotEmpty()) {
            foreach ($purchaseInvoice->goodsReceived->items as $grnItem) {
                $grade = (string) ($grnItem->grade ?: 'A');
                $key = ((int) $grnItem->product_id).'|'.$grade;
                $purchasedQty = (float) $grnItem->received_qty;
                $alreadyCredited = $creditedTotals[$key] ?? 0.0;
                $returnableQty = max(0.0, round($purchasedQty - $alreadyCredited, 3));
                $unitPrice = (float) ($grnItem->purchaseOrderItem?->unit_price ?? 0.0);

                $lines[] = [
                    'purchaser_cart_item_id' => null,
                    'product_id' => $grnItem->product_id,
                    'product_name' => $grnItem->product?->name ?? "Product #{$grnItem->product_id}",
                    'unit' => $grnItem->product?->unit ?? 'kg',
                    'grade' => $grade,
                    'purchased_qty' => $purchasedQty,
                    'already_credited_qty' => $alreadyCredited,
                    'returnable_qty' => $returnableQty,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $purchaseInvoice->id,
                'public_uuid' => $purchaseInvoice->public_uuid,
                'invoice_number' => $purchaseInvoice->invoice_number,
                'amount' => (float) $purchaseInvoice->amount,
                'remaining_balance' => (float) $purchaseInvoice->remainingBalance(),
            ],
            'items' => $lines,
        ]);
    }
}
