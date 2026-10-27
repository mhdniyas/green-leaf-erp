<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\DashboardSegregateRequest;
use App\Models\Product;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\ShopInvoices\ShopInvoiceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryDashboardOperationController extends Controller
{
    public function __construct(
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ProcessPartialSegregationAction $segregationAction,
        private readonly StockMovementRepository $stockMovementRepository,
    ) {}

    public function lockInvoice(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $shopOrder->loadMissing(['items.product', 'invoice.items']);

        $invoice = $shopOrder->invoice;

        if (! $invoice instanceof ShopInvoice) {
            $invoice = $this->shopInvoiceService->synchronizeOrderInvoice(
                $shopOrder,
                (int) $request->user()->id,
            );
        }

        if ($invoice->isFinalLocked()) {
            return redirect()->back()->with('success', 'Invoice is already locked.');
        }

        try {
            if (in_array((string) $shopOrder->delivery_status, ['pending_approval', 'delivered'], true) && $shopOrder->delivery_review_status === 'pending') {
                $this->resolveDeliveryReviewAction->approve(
                    $shopOrder,
                    $this->approvedDeliveredQuantities($shopOrder),
                    [],
                    [],
                    [],
                    [],
                    (int) $request->user()->id,
                    'Locked from delivery dashboard.',
                );

                return redirect()->back()->with('success', 'Invoice locked after approving delivery review.');
            }

            if ($shopOrder->delivery_review_status === 'approved' || in_array((string) $shopOrder->delivery_status, ['delivered', 'partially_delivered'], true)) {
                DB::transaction(function () use ($shopOrder, $invoice, $request): void {
                    $invoice = ShopInvoice::query()
                        ->with('items')
                        ->lockForUpdate()
                        ->findOrFail($invoice->id);

                    $invoice->update([
                        'delivery_status' => (float) $invoice->shortage_total > 0.0001 ? 'approved_after_discrepancy' : 'received_full',
                        'status' => 'finalized',
                        'delivery_confirmed_by' => $invoice->delivery_confirmed_by ?? (int) $request->user()->id,
                        'delivery_confirmed_at' => $invoice->delivery_confirmed_at ?? now(),
                        'delivery_note' => trim((string) ($invoice->delivery_note ?? '')."\nLocked from delivery dashboard."),
                    ]);

                    $this->shopInvoiceService->recalculate($invoice->fresh('items'));

                    $shopOrder->update([
                        'is_delivered' => true,
                        'delivered_at' => $shopOrder->delivered_at ?? now(),
                        'delivered_by' => $shopOrder->delivered_by ?? (int) $request->user()->id,
                        'delivery_review_status' => $shopOrder->delivery_review_status === 'not_started'
                            ? 'approved'
                            : $shopOrder->delivery_review_status,
                    ]);
                });

                return redirect()->back()->with('success', 'Invoice locked.');
            }
        } catch (ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->withErrors([
            'invoice' => 'Complete delivery check-in or admin delivery review before locking this invoice.',
        ]);
    }

    /**
     * Get real-time segregation modal data for a product (eligible batches, current grade balances).
     */
    public function segregationData(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                $user->can('inventory.sorting.process')
                || $user->can('warehouse.receive.sort')
                || $user->hasRole('admin')
                || $user->hasRole('warehouse_receiver')
                || $user->can('inventory.product.view')
            ),
            403
        );

        $warehouseId = $request->integer('warehouse_id') ?: null;
        if ($warehouseId && ! $user->canAccessWarehouse($warehouseId)) {
            abort(403, 'You do not have access to this warehouse.');
        }

        $date = $request->filled('date') ? Carbon::parse($request->string('date')->toString())->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        $batches = StockBatch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_receive_pending', false)
            ->where('status', BatchStatus::Pending)
            ->where(function ($q): void {
                $q->whereIn('purchase_grade', ['U', 'MIXED', 'Unsorted'])
                    ->orWhereNull('purchase_grade');
            })
            ->whereDate('received_at', '<=', $date)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('received_at', 'asc')
            ->orderBy('id', 'asc')
            ->with('warehouse')
            ->get()
            ->filter(fn (StockBatch $b): bool => $b->available_mixed_qty > 0.001)
            ->values();

        $batchesData = $batches->map(fn (StockBatch $b): array => [
            'id' => $b->id,
            'reference' => $b->reference,
            'received_at' => $b->received_at ? $b->received_at->format('d M Y') : 'N/A',
            'warehouse_id' => $b->warehouse_id,
            'warehouse_name' => $b->warehouse?->name ?? 'Warehouse',
            'available_mixed_qty' => round((float) $b->available_mixed_qty, 3),
            'cost_per_kg' => (float) $b->cost_per_kg,
        ]);

        $availableMixed = round((float) $batches->sum(fn (StockBatch $b): float => $b->available_mixed_qty), 3);
        $gradeA = $this->stockMovementRepository->stockForProductAndGrade($product->id, ProductGrade::GradeA, $warehouseId, $date);
        $gradeB = $this->stockMovementRepository->stockForProductAndGrade($product->id, ProductGrade::GradeB, $warehouseId, $date);
        $gradeC = $this->stockMovementRepository->stockForProductAndGrade($product->id, ProductGrade::GradeC, $warehouseId, $date);

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?? 'kg',
            ],
            'warehouse_id' => $warehouseId,
            'stock' => [
                'mixed' => $availableMixed,
                'grade_a' => $gradeA,
                'grade_b' => $gradeB,
                'grade_c' => $gradeC,
                'total' => round($availableMixed + $gradeA + $gradeB + $gradeC, 3),
            ],
            'batches' => $batchesData,
        ]);
    }

    /**
     * Process partial grade segregation from the deliveries dashboard.
     */
    public function segregate(DashboardSegregateRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $batchId = (int) $validated['batch_id'];
        $targetGrade = ProductGrade::tryResolve($validated['target_grade']) ?? ProductGrade::from($validated['target_grade']);
        $quantity = (float) $validated['quantity'];
        $notes = $validated['notes'] ?? null;
        $clientSubmissionId = $validated['client_submission_id'] ?? null;

        /** @var StockBatch $batch */
        $batch = StockBatch::with(['product', 'warehouse'])->findOrFail($batchId);

        if (! $request->user()->canAccessWarehouse($batch->warehouse_id)) {
            abort(403, 'You do not have access to this warehouse.');
        }

        if ($batch->warehouse_receive_pending || $batch->status === BatchStatus::Closed) {
            throw ValidationException::withMessages([
                'batch_id' => "Batch {$batch->reference} cannot be segregated.",
            ]);
        }

        try {
            $result = $this->segregationAction->execute(
                $batch,
                $targetGrade,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId
            );

            $productName = $batch->product?->name ?? 'Product';
            $unit = $batch->product?->unit ?? 'kg';
            $childRef = $result['child']?->reference ?? 'segregated';
            $msg = "Successfully segregated {$quantity} {$unit} of {$productName} into Grade {$targetGrade->value} (Batch {$childRef}).";

            if ($request->wantsJson() || $request->ajax()) {
                $warehouseId = (int) $batch->warehouse_id;
                $productId = (int) $batch->product_id;

                $freshBatches = StockBatch::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('warehouse_receive_pending', false)
                    ->where('status', '!=', BatchStatus::Closed)
                    ->where(function ($q): void {
                        $q->where('status', BatchStatus::Pending)
                            ->orWhereIn('purchase_grade', ['U', 'MIXED', 'Unsorted']);
                    })
                    ->get();

                $freshMixed = round((float) $freshBatches->sum(fn (StockBatch $b): float => $b->available_mixed_qty), 3);
                $freshA = $this->stockMovementRepository->stockForProductAndGrade($productId, ProductGrade::GradeA, $warehouseId);
                $freshB = $this->stockMovementRepository->stockForProductAndGrade($productId, ProductGrade::GradeB, $warehouseId);
                $freshC = $this->stockMovementRepository->stockForProductAndGrade($productId, ProductGrade::GradeC, $warehouseId);

                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'stock' => [
                        'mixed' => $freshMixed,
                        'grade_a' => $freshA,
                        'grade_b' => $freshB,
                        'grade_c' => $freshC,
                        'total' => round($freshMixed + $freshA + $freshB + $freshC, 3),
                    ],
                    'parent_batch' => [
                        'id' => $result['parent']->id,
                        'reference' => $result['parent']->reference,
                        'remaining_qty' => round((float) $result['parent']->remaining_qty, 3),
                        'available_mixed_qty' => round((float) $result['parent']->available_mixed_qty, 3),
                    ],
                    'child_batch' => $result['child'] ? [
                        'id' => $result['child']->id,
                        'reference' => $result['child']->reference,
                        'total_kg' => round((float) $result['child']->total_kg, 3),
                        'grade' => $result['child']->purchase_grade,
                    ] : null,
                ]);
            }

            return redirect()->back()->with('success', $msg);
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * @return array<int, float>
     */
    private function approvedDeliveredQuantities(ShopOrder $shopOrder): array
    {
        return $shopOrder->items
            ->mapWithKeys(function (ShopOrderItem $item): array {
                $quantity = $item->shop_reported_received_qty
                    ?? $item->delivered_qty
                    ?? $item->loaded_qty
                    ?? $item->approved_qty
                    ?? 0;

                return [(int) $item->id => (float) $quantity];
            })
            ->all();
    }
}
