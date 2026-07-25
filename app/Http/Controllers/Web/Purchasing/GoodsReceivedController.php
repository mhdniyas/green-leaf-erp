<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StoreGoodsReceivedRequest;
use App\Http\Requests\Web\Purchasing\UpdatePendingDailyPriceApprovalRequest;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\GoodsReceivedService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoodsReceivedController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedService $service,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
        private readonly PriceBoardService $priceBoardService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GoodsReceived::class);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());

        $submittedGrns = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with([
                'purchaseOrder.supplier',
                'items.product',
                'items.purchaseOrderItem',
                'receivedBy',
            ])
            ->orderBy('is_extra')
            ->orderBy('grn_number')
            ->get();

        $approvedGrns = GoodsReceived::query()
            ->where('status', 'approved')
            ->whereDate('received_at', $date)
            ->with([
                'purchaseOrder.supplier',
                'items.product',
                'items.purchaseOrderItem',
                'receivedBy',
                'approvedBy',
                'updatedBy',
            ])
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();

        $recentReceipts = GoodsReceived::query()
            ->where('status', '!=', 'draft')
            ->whereDate('received_at', $date)
            ->with([
                'purchaseOrder.supplier',
                'receivedBy',
                'approvedBy',
                'updatedBy',
                'items.product',
            ])
            ->orderByRaw("CASE status WHEN 'pending_approval' THEN 0 WHEN 'recheck_required' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->get();

        $submittedProductGroups = $this->buildProductGroups($submittedGrns);

        $pricingBusinessDate = Carbon::parse($date)->toDateString();
        $this->priceBoardService->ensureDefaultPriceGroups();
        $pendingPriceApprovals = $this->priceBoardService
            ->ensurePendingApprovalsForPurchaseDate($date)
            ->where('status', 'pending')
            ->values();

        return view('purchase-manager.grns.index', [
            'date' => $date,
            'pricingBusinessDate' => $pricingBusinessDate,
            'submittedGrns' => $submittedGrns,
            'submittedProductGroups' => $submittedProductGroups,
            'approvedGrns' => $approvedGrns,
            'pendingPriceApprovals' => $pendingPriceApprovals,
            'priceGroups' => $this->priceBoardService->ensureDefaultPriceGroups()->whereIn('name', ['A', 'B', 'C'])->values(),
            'recentReceipts' => $recentReceipts,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', GoodsReceived::class);

        $purchaseOrderReference = trim((string) $request->string('purchase_order'));

        if ($purchaseOrderReference === '' && $request->filled('purchase_order_id')) {
            $legacyPurchaseOrderId = $request->integer('purchase_order_id');

            if ($legacyPurchaseOrderId > 0) {
                /** @var PurchaseOrder $legacyPurchaseOrder */
                $legacyPurchaseOrder = PurchaseOrder::whereIn('status', [
                    POStatus::SentToSupplier,
                    POStatus::PartiallyReceived,
                    POStatus::Received,
                ])->findOrFail($legacyPurchaseOrderId);

                return redirect()->route('purchasing.grns.create', [
                    'purchase_order' => $legacyPurchaseOrder,
                ]);
            }
        }

        if ($purchaseOrderReference === '') {
            return redirect()->route('purchasing.orders.index')
                ->with('error', 'Please select a Purchase Order to receive goods.');
        }

        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::whereIn('status', [
            POStatus::SentToSupplier,
            POStatus::PartiallyReceived,
            POStatus::Received,
        ])->where(
            (new PurchaseOrder)->getRouteKeyName(),
            $purchaseOrderReference
        )->firstOrFail();
        $po->load(['supplier', 'items.product']);

        return view('purchase-manager.grns.create', compact('po'));
    }

    public function store(StoreGoodsReceivedRequest $request): RedirectResponse
    {
        $poId = (int) $request->input('purchase_order_id');
        PurchaseOrder::whereIn('status', [
            POStatus::SentToSupplier,
            POStatus::PartiallyReceived,
            POStatus::Received,
        ])->findOrFail($poId);

        $grn = $this->service->create(
            GoodsReceivedData::fromRequest($request),
            (int) $request->user()->id
        );

        return redirect()->route('purchasing.grns.show', $grn)
            ->with('success', 'Goods Received Note recorded successfully.');
    }

    public function show(GoodsReceived $grn): View
    {
        Gate::authorize('view', $grn);

        $grn->load(['purchaseOrder.supplier', 'items.product', 'receivedBy', 'approvedBy', 'updatedBy']);

        return view('purchase-manager.grns.show', compact('grn'));
    }

    public function approveSubmitted(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', GoodsReceived::class);

        abort_unless(
            $request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'),
            403
        );

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $validated['date'];
        $userId = (int) $request->user()->id;

        $pendingGrns = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with(['items.purchaseOrderItem', 'items.product', 'purchaseOrder'])
            ->get();

        if ($pendingGrns->isEmpty()) {
            return redirect()
                ->route('purchasing.grns.index', ['date' => $date])
                ->withErrors(['No submitted purchases found for this date.']);
        }

        DB::transaction(function () use ($pendingGrns, $userId): void {
            foreach ($pendingGrns as $grn) {
                $this->approveGoodsReceiptAction->execute($grn, $userId);
            }
        });

        return redirect()
            ->route('purchasing.grns.index', ['date' => $date])
            ->with('success', "Submitted purchases approved. Update category prices next for {$pendingGrns->count()} receipt(s).");
    }

    public function updateProposedPrices(UpdatePendingDailyPriceApprovalRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            foreach ($validated['prices'] as $approvalId => $proposal) {
                $approval = DailyPriceApproval::query()->findOrFail((int) $approvalId);

                if ($approval->status !== 'pending') {
                    continue;
                }

                $approval->update([
                    'price_a' => round((float) $proposal['price_a'], 2),
                    'price_b' => round((float) $proposal['price_b'], 2),
                    'price_c' => round((float) $proposal['price_c'], 2),
                ]);
            }
        });

        return redirect()
            ->route('purchasing.grns.index', ['date' => $validated['date']])
            ->with('success', 'Proposed category prices updated. Admin approval is required before invoices are generated.');
    }

    public function markForRecheck(GoodsReceived $grn, Request $request): RedirectResponse
    {
        Gate::authorize('recheck', $grn);

        $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $this->service->markForRecheck($grn, $request->input('remarks'), (int) $request->user()->id);

        return redirect()->route('purchasing.grns.show', $grn)
            ->with('warning', 'Goods Received Note sent back for recheck. Receiver can update and resubmit it.');
    }

    public function edit(GoodsReceived $grn): View
    {
        Gate::authorize('update', $grn);

        $grn->load(['purchaseOrder.supplier', 'purchaseOrder.items.product', 'items']);

        return view('purchase-manager.grns.edit', compact('grn'));
    }

    public function update(GoodsReceived $grn, StoreGoodsReceivedRequest $request): RedirectResponse
    {
        Gate::authorize('update', $grn);

        $this->service->update(
            $grn,
            GoodsReceivedData::fromRequest($request),
            (int) $request->user()->id
        );

        return redirect()->route('purchasing.grns.show', $grn)
            ->with('success', 'Goods Received Note resubmitted and approved successfully.');
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     product_id: int,
     *     product_name: string,
     *     sku: string,
     *     unit: string,
     *     total_qty: float,
     *     avg_price: float,
     *     is_extra: bool,
     *     suppliers: array<int, string>,
     *     purchasers: array<int, string>,
     *     receipt_count: int,
     *     lines: array<int, array{
     *         grn_number: string,
     *         supplier: string,
     *         purchaser: string,
     *         received_qty: float,
     *         unit: string,
     *         unit_price: float
     *     }>
     * }>
     */
    private function buildProductGroups(Collection $grns): Collection
    {
        $groups = [];

        foreach ($grns as $grn) {
            foreach ($grn->items as $item) {
                $product = $item->product;

                if (! $product) {
                    continue;
                }

                $key = $product->id.'-'.((bool) $grn->is_extra ? 'extra' : 'regular');
                $qty = (float) $item->received_qty;
                $unitPrice = (float) ($item->purchaseOrderItem?->costPerKgForReceivedQuantity($qty) ?? $item->purchaseOrderItem?->unit_price ?? 0);

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => $key,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'unit' => $product->unit,
                        'total_qty' => 0.0,
                        'weighted_sum' => 0.0,
                        'avg_price' => 0.0,
                        'is_extra' => (bool) $grn->is_extra,
                        'suppliers' => [],
                        'purchasers' => [],
                        'receipt_count' => 0,
                        'lines' => [],
                    ];
                }

                $groups[$key]['total_qty'] += $qty;
                $groups[$key]['weighted_sum'] += $qty * $unitPrice;
                $groups[$key]['suppliers'][$grn->purchaseOrder->supplier?->name ?? 'Unknown Supplier'] = $grn->purchaseOrder->supplier?->name ?? 'Unknown Supplier';
                $groups[$key]['purchasers'][$grn->receivedBy?->name ?? 'Unknown Purchaser'] = $grn->receivedBy?->name ?? 'Unknown Purchaser';
                $groups[$key]['receipt_count']++;
                $groups[$key]['lines'][] = [
                    'grn_number' => $grn->grn_number,
                    'supplier' => $grn->purchaseOrder->supplier?->name ?? 'Unknown Supplier',
                    'purchaser' => $grn->receivedBy?->name ?? 'Unknown Purchaser',
                    'received_qty' => $qty,
                    'unit' => $product->unit,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        return collect($groups)
            ->map(function (array $group): array {
                $group['avg_price'] = $group['total_qty'] > 0
                    ? round($group['weighted_sum'] / $group['total_qty'], 2)
                    : 0.0;
                $group['suppliers'] = array_values($group['suppliers']);
                $group['purchasers'] = array_values($group['purchasers']);
                unset($group['weighted_sum']);

                return $group;
            })
            ->sortBy([
                ['is_extra', 'asc'],
                ['product_name', 'asc'],
            ])
            ->values();
    }
}
