<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StoreGoodsReceivedRequest;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\GoodsReceivedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoodsReceivedController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', GoodsReceived::class);

        $grns = $this->service->paginate(20);

        return view('purchase-manager.grns.index', compact('grns'));
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

        $grn->load(['purchaseOrder.supplier', 'items.product', 'receivedBy']);

        return view('purchase-manager.grns.show', compact('grn'));
    }

    public function approve(GoodsReceived $grn, Request $request): RedirectResponse
    {
        Gate::authorize('approve', $grn);

        $this->service->approve($grn, (int) $request->user()->id);

        return redirect()->route('purchasing.grns.show', $grn)
            ->with('success', 'Goods Received Note approved successfully and stock updated in inventory.');
    }

    public function reject(GoodsReceived $grn, Request $request): RedirectResponse
    {
        Gate::authorize('reject', $grn);

        $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $this->service->reject($grn, $request->input('remarks'), (int) $request->user()->id);

        return redirect()->route('purchasing.grns.show', $grn)
            ->with('warning', 'Goods Received Note rejected and returned to warehouse.');
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
            ->with('success', 'Goods Received Note updated successfully.');
    }
}
