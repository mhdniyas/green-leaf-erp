<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaserDashboardController extends Controller
{
    private function ensurePurchaser(Request $request): void
    {
        if (! $request->user()->hasRole('purchaser')) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        if (Carbon::parse($date)->isFuture()) {
            return redirect()
                ->route('purchaser.dashboard', ['date' => Carbon::today()->format('Y-m-d')])
                ->withErrors(['Future purchase dates are not allowed.']);
        }

        $userId = $request->user()->id;

        // 1. Load active products and suppliers for selectors
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        // 2. Load all draft purchases (GRNs with status 'draft') logged by this purchaser today
        $draftGrns = GoodsReceived::where('status', 'draft')
            ->where('received_by', $userId)
            ->whereDate('received_at', $date)
            ->with(['purchaseOrder.supplier', 'items.product', 'items.purchaseOrderItem'])
            ->get();

        // 3. Load all submitted purchases (GRNs with status != 'draft') logged by this purchaser today (History)
        $historyGrns = GoodsReceived::where('status', '!=', 'draft')
            ->where('received_by', $userId)
            ->whereDate('received_at', $date)
            ->with(['purchaseOrder.supplier', 'items.product', 'items.purchaseOrderItem'])
            ->get();

        // 4. Load approved shop demands (requirements) to show what needs to be bought
        $orderItems = ShopOrderItem::whereHas('order', function ($query) use ($date): void {
            $query->whereDate('business_date', $date)->where('state', 'approved');
        })->with(['product', 'order.shop'])->get();

        // Aggregate bought quantities per product across DRAFT and SUBMITTED receipts today
        $draftBought = [];
        $submittedBought = [];

        $draftItemsList = [];
        foreach ($draftGrns as $grn) {
            foreach ($grn->items as $item) {
                $productId = $item->product_id;
                $qty = (float) $item->received_qty;
                $draftBought[$productId] = ($draftBought[$productId] ?? 0.0) + $qty;

                $draftItemsList[] = [
                    'id' => $item->id,
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'unit' => $item->product->unit,
                    'quantity' => $qty,
                    'unit_price' => (float) ($item->purchaseOrderItem?->unit_price ?? 0),
                    'supplier_name' => $grn->purchaseOrder->supplier->name ?? 'Unknown',
                ];
            }
        }

        foreach ($historyGrns as $grn) {
            foreach ($grn->items as $item) {
                $productId = $item->product_id;
                $qty = (float) $item->received_qty;
                $submittedBought[$productId] = ($submittedBought[$productId] ?? 0.0) + $qty;
            }
        }

        $submittedPurchaseSummaries = $historyGrns
            ->flatMap(function (GoodsReceived $grn) {
                return $grn->items->map(function (GoodsReceivedItem $item) use ($grn): array {
                    return [
                        'product_id' => (int) $item->product_id,
                        'product_name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'unit' => $item->product->unit,
                        'quantity' => (float) $item->received_qty,
                        'unit_price' => (float) ($item->purchaseOrderItem?->unit_price ?? 0),
                        'status' => (string) $grn->status,
                        'supplier_name' => $grn->purchaseOrder->supplier->name ?? 'Unknown',
                    ];
                });
            })
            ->groupBy('product_id')
            ->map(function ($items): array {
                $first = $items->first();
                $totalQuantity = (float) $items->sum('quantity');
                $totalValue = (float) $items->sum(fn (array $item): float => $item['quantity'] * $item['unit_price']);
                $status = collect($items)->pluck('status')->contains('approved') ? 'approved' : 'pending_approval';

                return [
                    'product_name' => $first['product_name'],
                    'sku' => $first['sku'],
                    'unit' => $first['unit'],
                    'total_quantity' => $totalQuantity,
                    'average_price' => $totalQuantity > 0 ? round($totalValue / $totalQuantity, 2) : 0.0,
                    'supplier_names' => collect($items)->pluck('supplier_name')->unique()->values()->all(),
                    'status' => $status,
                ];
            })
            ->sortBy('product_name')
            ->values();

        // 5. Consolidate requirements for Daily Order, Bought Items, Remaining to Buy, and History
        $requirements = $orderItems->groupBy('product_id')->map(function ($items, $productId) use ($draftBought, $submittedBought) {
            $product = $items->first()->product;
            $totalNeeded = (float) $items->sum('approved_qty');

            $dbought = $draftBought[$productId] ?? 0.0;
            $sbought = $submittedBought[$productId] ?? 0.0;
            $totalBought = $dbought + $sbought;
            $remaining = max(0.00, $totalNeeded - $totalBought);

            $shopSplit = $items->map(fn ($item) => [
                'shop_name' => $item->order->shop->name,
                'quantity' => (float) $item->approved_qty,
                'unit' => $item->unit,
            ])->values()->all();

            return [
                'product_id' => $productId,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'total_needed' => $totalNeeded,
                'draft_bought' => $dbought,
                'submitted_bought' => $sbought,
                'total_bought' => $totalBought,
                'remaining' => $remaining,
                'status' => match (true) {
                    $remaining <= 0 => 'full',
                    $totalBought > 0 => 'partial',
                    default => 'pending',
                },
                'shop_split' => $shopSplit,
            ];
        })->sort(function (array $left, array $right): int {
            $priority = [
                'pending' => 0,
                'partial' => 1,
                'full' => 2,
            ];

            $leftPriority = $priority[$left['status']] ?? 99;
            $rightPriority = $priority[$right['status']] ?? 99;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return strcmp($left['product_name'], $right['product_name']);
        })->values();

        return view('purchasing.purchaser_dashboard', compact(
            'date',
            'requirements',
            'draftItemsList',
            'submittedPurchaseSummaries',
            'products',
            'suppliers'
        ));
    }

    public function recordDraftPurchase(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date', Rule::date()->todayOrBefore()],
        ]);

        $productId = (int) $request->input('product_id');
        $supplierId = (int) $request->input('supplier_id');
        $quantity = (float) $request->input('quantity');
        $unitPrice = (float) $request->input('unit_price');
        $date = $request->input('date');
        $userId = $request->user()->id;

        try {
            DB::transaction(function () use ($productId, $supplierId, $quantity, $unitPrice, $date, $userId): void {
                // 1. Calculate how much is needed for this product today
                $totalNeeded = (float) ShopOrderItem::whereHas('order', function ($query) use ($date): void {
                    $query->whereDate('business_date', $date)->where('state', 'approved');
                })->where('product_id', $productId)->sum('approved_qty');

                // 2. Calculate how much has already been purchased/logged today (excluding extra/add-on receipts)
                $alreadyBought = (float) GoodsReceivedItem::where('product_id', $productId)
                    ->whereHas('goodsReceived', function ($query) use ($date): void {
                        $query->whereDate('received_at', $date)
                            ->where('is_extra', false);
                    })
                    ->sum('received_qty');

                $remainingNeeded = max(0.0, $totalNeeded - $alreadyBought);

                $regularQty = min($quantity, $remainingNeeded);
                $extraQty = $quantity - $regularQty;

                if ($regularQty > 0) {
                    // Find or create a regular draft Purchase Order for this supplier and date
                    $po = PurchaseOrder::whereDate('order_date', $date)
                        ->where('supplier_id', $supplierId)
                        ->where('fulfillment_type', 'warehouse')
                        ->whereDoesntHave('goodsReceiveds', function ($query): void {
                            $query->where('is_extra', true);
                        })
                        ->first();

                    if (! $po) {
                        $dateStrStr = Carbon::parse($date)->format('Ymd');
                        do {
                            $suffix = strtoupper(bin2hex(random_bytes(2)));
                            $poNumber = "PO-PURCH-{$dateStrStr}-{$suffix}";
                        } while (PurchaseOrder::where('po_number', $poNumber)->exists());

                        $po = PurchaseOrder::create([
                            'supplier_id' => $supplierId,
                            'po_number' => $poNumber,
                            'status' => POStatus::Draft,
                            'fulfillment_type' => 'warehouse',
                            'order_date' => $date,
                            'created_by' => $userId,
                            'notes' => 'Draft PO created by purchaser',
                        ]);
                    }

                    // Create or update Purchase Order Item
                    $poItem = $po->items()->where('product_id', $productId)->first();
                    if ($poItem) {
                        $poItem->update([
                            'quantity' => (float) $poItem->quantity + $regularQty,
                            'unit_price' => $unitPrice,
                        ]);
                    } else {
                        $poItem = $po->items()->create([
                            'product_id' => $productId,
                            'quantity' => $regularQty,
                            'unit_price' => $unitPrice,
                            'purchase_unit' => 'kg',
                            'price_basis' => 'per_kg',
                        ]);
                    }

                    // Find or create a regular draft Goods Received Note for this PO
                    $grn = GoodsReceived::where('purchase_order_id', $po->id)
                        ->where('status', 'draft')
                        ->where('is_extra', false)
                        ->first();

                    if (! $grn) {
                        $grnNumber = 'GRN-DRAFT-'.Carbon::parse($date)->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
                        $grn = GoodsReceived::create([
                            'purchase_order_id' => $po->id,
                            'grn_number' => $grnNumber,
                            'received_at' => $date,
                            'received_by' => $userId,
                            'status' => 'draft',
                            'is_extra' => false,
                            'transport_cost' => 0.00,
                            'labour_cost' => 0.00,
                            'notes' => 'Draft receipt logged by purchaser',
                        ]);
                    }

                    // Create Goods Received Item
                    $grn->items()->create([
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $productId,
                        'received_qty' => $regularQty,
                        'variance' => 0.00,
                    ]);
                }

                if ($extraQty > 0) {
                    // Create a NEW Purchase Order for the extra (add-on)
                    $dateStrStr = Carbon::parse($date)->format('Ymd');
                    do {
                        $suffix = strtoupper(bin2hex(random_bytes(2)));
                        $poNumber = "PO-ADDON-{$dateStrStr}-{$suffix}";
                    } while (PurchaseOrder::where('po_number', $poNumber)->exists());

                    $extraPo = PurchaseOrder::create([
                        'supplier_id' => $supplierId,
                        'po_number' => $poNumber,
                        'status' => POStatus::Draft,
                        'fulfillment_type' => 'warehouse',
                        'order_date' => $date,
                        'created_by' => $userId,
                        'notes' => 'Draft Add-on PO created by purchaser',
                    ]);

                    $extraPoItem = $extraPo->items()->create([
                        'product_id' => $productId,
                        'quantity' => $extraQty,
                        'unit_price' => $unitPrice,
                        'purchase_unit' => 'kg',
                        'price_basis' => 'per_kg',
                    ]);

                    // Create a NEW draft Goods Received Note for this extra PO
                    $grnNumber = 'GRN-ADDON-'.Carbon::parse($date)->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
                    $extraGrn = GoodsReceived::create([
                        'purchase_order_id' => $extraPo->id,
                        'grn_number' => $grnNumber,
                        'received_at' => $date,
                        'received_by' => $userId,
                        'status' => 'draft',
                        'is_extra' => true,
                        'transport_cost' => 0.00,
                        'labour_cost' => 0.00,
                        'notes' => 'Draft Add-on receipt logged by purchaser',
                    ]);

                    $extraGrn->items()->create([
                        'purchase_order_item_id' => $extraPoItem->id,
                        'product_id' => $productId,
                        'received_qty' => $extraQty,
                        'variance' => 0.00,
                    ]);
                }
            });

            return redirect()->back()->with('success', 'Purchase item logged in draft list successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withErrors([$e->getMessage()]);
        }
    }

    public function deleteDraftPurchase(int $id, Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        try {
            DB::transaction(function () use ($id): void {
                $grnItem = GoodsReceivedItem::findOrFail($id);
                $grn = $grnItem->goodsReceived;

                if ($grn->status !== 'draft') {
                    throw new \InvalidArgumentException('Only draft purchases can be deleted.');
                }

                // Decrement PO item quantity
                $poItem = $grnItem->purchaseOrderItem;
                if ($poItem) {
                    $newQty = (float) $poItem->quantity - (float) $grnItem->received_qty;
                    if ($newQty <= 0) {
                        $poItem->delete();
                    } else {
                        $poItem->update([
                            'quantity' => $newQty,
                        ]);
                    }
                }

                $grnItem->delete();

                // Clean up empty GRN / PO
                if ($grn->items()->count() === 0) {
                    $grn->delete();
                    $po = $grn->purchaseOrder;
                    if ($po && $po->items()->count() === 0) {
                        $po->delete();
                    }
                }
            });

            return redirect()->back()->with('success', 'Draft purchase item removed.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    public function submitPurchases(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'date' => ['required', 'date', Rule::date()->todayOrBefore()],
        ]);

        $date = $validated['date'];
        $userId = $request->user()->id;

        try {
            DB::transaction(function () use ($date, $userId): void {
                $draftGrns = GoodsReceived::where('status', 'draft')
                    ->where('received_by', $userId)
                    ->whereDate('received_at', $date)
                    ->get();

                if ($draftGrns->isEmpty()) {
                    throw new \InvalidArgumentException('No draft purchases found to submit.');
                }

                foreach ($draftGrns as $grn) {
                    $grn->update([
                        'status' => 'pending_approval',
                        'notes' => 'Submitted by purchaser for approval',
                    ]);

                    $po = $grn->purchaseOrder;
                    if ($po) {
                        $po->update([
                            'status' => POStatus::Received,
                        ]);
                    }
                }
            });

            return redirect()->back()->with('success', 'All draft purchases have been successfully submitted to the Purchase Manager.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }
}
