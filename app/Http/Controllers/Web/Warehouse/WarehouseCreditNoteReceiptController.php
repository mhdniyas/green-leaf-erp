<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopCreditNoteReceipt;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShopInvoices\ShopCreditNoteWarehouseReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseCreditNoteReceiptController extends Controller
{
    public function __construct(
        protected ShopCreditNoteWarehouseReceiptService $receiptService,
    ) {}

    /**
     * Display the warehouse-facing queue of Shop Credit Note items awaiting physical returns.
     */
    public function index(Request $request): View
    {
        $this->ensureAuthorized($request);

        $statusFilter = $request->input('status', 'pending');
        $searchQuery = trim((string) $request->input('q', ''));
        $shopId = $request->input('shop_id');
        $productId = $request->input('product_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = ShopCreditNoteItem::query()
            ->where('inventory_action', ShopCreditNoteInventoryAction::ReturnToStock)
            ->whereHas('creditNote', function ($q): void {
                $q->where('accounting_mode', ShopCreditNoteAccountingMode::ShopLevel)
                    ->whereIn('status', [ShopCreditNoteStatus::Approved, ShopCreditNoteStatus::Voided]);
            })
            ->with([
                'creditNote.shop',
                'product',
                'sourceInvoice',
                'receipts.warehouse',
                'receipts.receiver',
                'receipts.reversedByUser',
            ]);

        // Status filter
        if ($statusFilter === 'pending') {
            $query->where('receipt_status', ShopCreditNoteReceiptStatus::PendingReceipt);
        } elseif ($statusFilter === 'partially_received') {
            $query->where('receipt_status', ShopCreditNoteReceiptStatus::PartiallyReceived);
        } elseif ($statusFilter === 'verified') {
            $query->where('receipt_status', ShopCreditNoteReceiptStatus::ReceivedAndVerified);
        } elseif ($statusFilter === 'disputed') {
            $query->where('receipt_status', ShopCreditNoteReceiptStatus::Disputed);
        }

        // Shop filter
        if (filled($shopId)) {
            $query->whereHas('creditNote', function ($q) use ($shopId): void {
                $q->where('shop_id', (int) $shopId);
            });
        }

        // Product filter
        if (filled($productId)) {
            $query->where('product_id', (int) $productId);
        }

        // Date filter on credit note date
        if (filled($startDate)) {
            $query->whereHas('creditNote', function ($q) use ($startDate): void {
                $q->whereDate('credit_note_date', '>=', $startDate);
            });
        }
        if (filled($endDate)) {
            $query->whereHas('creditNote', function ($q) use ($endDate): void {
                $q->whereDate('credit_note_date', '<=', $endDate);
            });
        }

        // Search query
        if ($searchQuery !== '') {
            $query->where(function ($sub) use ($searchQuery): void {
                $sub->whereHas('creditNote', function ($cq) use ($searchQuery): void {
                    $cq->where('credit_note_number', 'like', "%{$searchQuery}%")
                        ->orWhereHas('shop', function ($sq) use ($searchQuery): void {
                            $sq->where('name', 'like', "%{$searchQuery}%")
                                ->orWhere('code', 'like', "%{$searchQuery}%");
                        });
                })
                    ->orWhereHas('product', function ($pq) use ($searchQuery): void {
                        $pq->where('name', 'like', "%{$searchQuery}%")
                            ->orWhere('sku', 'like', "%{$searchQuery}%");
                    })
                    ->orWhereHas('sourceInvoice', function ($iq) use ($searchQuery): void {
                        $iq->where('invoice_number', 'like', "%{$searchQuery}%");
                    });
            });
        }

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        // Calculate counts for status tabs
        $baseCountQuery = ShopCreditNoteItem::query()
            ->where('inventory_action', ShopCreditNoteInventoryAction::ReturnToStock)
            ->whereHas('creditNote', function ($q): void {
                $q->where('accounting_mode', ShopCreditNoteAccountingMode::ShopLevel)
                    ->whereIn('status', [ShopCreditNoteStatus::Approved, ShopCreditNoteStatus::Voided]);
            });

        $counts = [
            'all' => (clone $baseCountQuery)->count(),
            'pending' => (clone $baseCountQuery)->where('receipt_status', ShopCreditNoteReceiptStatus::PendingReceipt)->count(),
            'partially_received' => (clone $baseCountQuery)->where('receipt_status', ShopCreditNoteReceiptStatus::PartiallyReceived)->count(),
            'verified' => (clone $baseCountQuery)->where('receipt_status', ShopCreditNoteReceiptStatus::ReceivedAndVerified)->count(),
            'disputed' => (clone $baseCountQuery)->where('receipt_status', ShopCreditNoteReceiptStatus::Disputed)->count(),
        ];

        $warehouses = Warehouse::query()->orderBy('name')->get();
        $shops = Shop::query()->orderBy('name')->get(['id', 'name', 'code']);
        $products = Product::query()->orderBy('name')->get(['id', 'name']);
        $wastageReasons = WastageReason::cases();

        return view('warehouse-receiver.credit_notes.index', [
            'items' => $items,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'shopId' => $shopId,
            'productId' => $productId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'warehouses' => $warehouses,
            'shops' => $shops,
            'products' => $products,
            'wastageReasons' => $wastageReasons,
        ]);
    }

    /**
     * Confirm physical receipt of goods for a Credit Note item.
     */
    public function receive(Request $request, ShopCreditNoteItem $item): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorized($request);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'received_grade' => ['required', 'string', 'in:A,B,C'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $receipt = $this->receiptService->receivePhysicalReturn(
                item: $item,
                quantity: (float) $validated['quantity'],
                grade: ProductGrade::from($validated['received_grade']),
                warehouse: (int) $validated['warehouse_id'],
                actor: $user,
                remarks: $validated['remarks'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        $freshItem = $item->fresh(['receipts']);
        $msg = "Received {$receipt->received_quantity} kg of Grade {$receipt->received_grade} into {$receipt->warehouse?->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'receipt' => $receipt,
                'item_status' => $freshItem->receipt_status?->value ?? $freshItem->receipt_status,
                'received_quantity' => (float) $freshItem->received_quantity,
            ]);
        }

        return redirect()
            ->route('warehouse.receiver.credit-notes.index')
            ->with('success', $msg);
    }

    /**
     * Record damaged return goods for a Credit Note item into wastage.
     */
    public function damage(Request $request, ShopCreditNoteItem $item): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorized($request);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'damage_reason' => ['required', 'string', 'in:'.implode(',', array_column(WastageReason::cases(), 'value'))],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $receipt = $this->receiptService->receiveDamagedReturn(
                item: $item,
                quantity: (float) $validated['quantity'],
                damageReason: WastageReason::from($validated['damage_reason']),
                warehouse: (int) $validated['warehouse_id'],
                actor: $user,
                remarks: $validated['remarks'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        $freshItem = $item->fresh(['receipts']);
        $msg = "Recorded {$receipt->received_quantity} kg as damaged write-off into {$receipt->warehouse?->name}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'receipt' => $receipt,
                'item_status' => $freshItem->receipt_status?->value ?? $freshItem->receipt_status,
                'received_quantity' => (float) $freshItem->received_quantity,
            ]);
        }

        return redirect()
            ->route('warehouse.receiver.credit-notes.index')
            ->with('success', $msg);
    }

    /**
     * Mark an expected physical return as disputed.
     */
    public function dispute(Request $request, ShopCreditNoteItem $item): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorized($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $receipt = $this->receiptService->disputePhysicalReturn(
                item: $item,
                actor: $user,
                reason: $validated['reason'],
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        $freshItem = $item->fresh(['receipts']);
        $msg = "Dispute recorded for return item #{$item->id}: {$validated['reason']}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'receipt' => $receipt,
                'item_status' => $freshItem->receipt_status?->value ?? $freshItem->receipt_status,
            ]);
        }

        return redirect()
            ->route('warehouse.receiver.credit-notes.index')
            ->with('success', $msg);
    }

    /**
     * Reverse a previously verified or damaged physical receipt.
     */
    public function reverse(Request $request, ShopCreditNoteReceipt $receipt): JsonResponse|RedirectResponse
    {
        $user = $this->ensureAuthorized($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $reversed = $this->receiptService->reversePhysicalReturn(
                receipt: $receipt,
                actor: $user,
                reason: $validated['reason'],
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        $msg = "Warehouse return receipt #{$reversed->id} reversed successfully.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'receipt' => $reversed,
            ]);
        }

        return redirect()
            ->route('warehouse.receiver.credit-notes.index')
            ->with('success', $msg);
    }

    /**
     * Fetch receipt history for a Credit Note item via AJAX.
     */
    public function history(Request $request, ShopCreditNoteItem $item): JsonResponse
    {
        $this->ensureAuthorized($request);

        $receipts = $item->receipts()
            ->with(['warehouse', 'receiver', 'reversedByUser', 'stockMovement'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'item_id' => $item->id,
            'expected_quantity' => (float) $item->adjusted_quantity,
            'received_quantity' => (float) $item->received_quantity,
            'receipt_status' => $item->receipt_status?->value ?? $item->receipt_status,
            'receipts' => $receipts->map(function (ShopCreditNoteReceipt $r): array {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'received_quantity' => (float) $r->received_quantity,
                    'received_grade' => $r->received_grade,
                    'warehouse_name' => $r->warehouse?->name ?? '—',
                    'receiver_name' => $r->receiver?->name ?? '—',
                    'received_at' => optional($r->received_at)->format('d M Y, H:i'),
                    'remarks' => $r->remarks,
                    'stock_movement_id' => $r->stock_movement_id,
                    'is_reversed' => $r->isReversed(),
                    'reversed_at' => optional($r->reversed_at)->format('d M Y, H:i'),
                    'reversed_by_name' => $r->reversedByUser?->name,
                    'reversal_reason' => $r->reversal_reason,
                    'can_reverse' => ($r->isVerified() || $r->isDamaged()) && ! $r->isReversed(),
                ];
            }),
        ]);
    }

    /**
     * Enforce server-side authorization for warehouse receipt actions.
     */
    protected function ensureAuthorized(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $this->receiptService->isAuthorizedToReceive($user),
            403,
            'Unauthorized: You do not have permission to verify warehouse receipts.'
        );

        return $user;
    }
}
