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

        return view('purchasing.grns.index', compact('grns'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', GoodsReceived::class);

        $poId = $request->integer('purchase_order_id');
        if (! $poId) {
            return redirect()->route('purchasing.orders.index')
                ->with('error', 'Please select a Purchase Order to receive goods.');
        }

        /** @var PurchaseOrder $po */
        $po = PurchaseOrder::where('status', POStatus::Approved)->findOrFail($poId);
        $po->load(['supplier', 'items.product']);

        return view('purchasing.grns.create', compact('po'));
    }

    public function store(StoreGoodsReceivedRequest $request): RedirectResponse
    {
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

        return view('purchasing.grns.show', compact('grn'));
    }
}
