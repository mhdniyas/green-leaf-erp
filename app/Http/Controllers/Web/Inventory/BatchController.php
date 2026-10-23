<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\DTOs\Inventory\SortingData;
use App\DTOs\Inventory\StockBatchData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Exceptions\Inventory\BatchAlreadySortedException;
use App\Exceptions\Inventory\SortingQuantityMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\ReverseSegregationRequest;
use App\Http\Requests\Web\Inventory\SegregateBatchRequest;
use App\Http\Requests\Web\Inventory\SortBatchRequest;
use App\Http\Requests\Web\Inventory\StoreStockBatchRequest;
use App\Models\StockBatch;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\InventorySortingSettingsService;
use App\Services\Inventory\StockBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function __construct(
        private readonly StockBatchService $service,
        private readonly ProductRepository $products,
        private readonly ProcessBatchSortingAction $sortingAction,
        private readonly ProcessPartialSegregationAction $segregationAction,
        private readonly InventorySortingSettingsService $sortingSettings,
    ) {}

    public function index(Request $request): View
    {
        $date = $request->input('date');

        $query = StockBatch::with(['product', 'warehouse', 'createdBy', 'wastageEntries', 'segregatedBatches']);
        if ($date) {
            $query->whereDate('received_at', $date);
        }

        $batches = $query->orderByDesc('received_at')->paginate(20);

        return view('inventory.batches.index', compact('batches', 'date'));
    }

    public function create(): View
    {
        $products = $this->products->findAllActive();

        return view('inventory.batches.create', compact('products'));
    }

    public function store(StoreStockBatchRequest $request): RedirectResponse
    {
        $batch = $this->service->create(StockBatchData::fromRequest($request), $request->user()->id);

        return redirect()->route('inventory.batches.show', $batch)
            ->with('success', "Batch {$batch->reference} created. Ready for sorting.");
    }

    public function show(StockBatch $batch): View
    {
        $batch->load(['product', 'warehouse', 'stockMovements', 'wastageEntries', 'createdBy', 'parentBatch', 'segregatedBatches.stockMovements']);

        return view('inventory.batches.show', compact('batch'));
    }

    public function sort(StockBatch $batch): View
    {
        abort_unless($batch->canBeSorted(), 422, 'This batch has already been sorted.');

        $batch->load(['product', 'warehouse', 'segregatedBatches']);

        return view('inventory.batches.sort', [
            'batch' => $batch,
            'sortAllAsGradeA' => $batch->purchase_grade === 'MIXED' ? false : $this->sortingSettings->sortAllAsGradeA(),
        ]);
    }

    public function processSort(SortBatchRequest $request, StockBatch $batch): RedirectResponse
    {
        abort_unless($batch->canBeSorted(), 422, 'This batch has already been sorted.');

        try {
            $this->sortingAction->execute($batch, SortingData::fromRequest($request), $request->user()->id);

            return redirect()->route('inventory.batches.show', $batch)
                ->with('success', "Batch {$batch->reference} sorted successfully! Stock levels updated.");
        } catch (BatchAlreadySortedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (SortingQuantityMismatchException $e) {
            return back()->withErrors(['total' => $e->getMessage()])->withInput();
        }
    }

    public function segregate(SegregateBatchRequest $request, StockBatch $batch): RedirectResponse
    {
        abort_unless(! $batch->warehouse_receive_pending && $batch->status !== BatchStatus::Closed, 422, 'This batch cannot be segregated.');

        $validated = $request->validated();
        $targetGrade = ProductGrade::tryResolve($validated['target_grade']) ?? ProductGrade::from($validated['target_grade']);
        $quantity = (float) $validated['quantity'];
        $notes = $validated['notes'] ?? null;
        $clientSubmissionId = $validated['client_submission_id'] ?? null;
        $reason = isset($validated['reason']) ? WastageReason::tryFrom($validated['reason']) : null;

        try {
            $result = $this->segregationAction->execute(
                $batch,
                $targetGrade,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId,
                $reason
            );

            $msg = $result['child']
                ? "Partially segregated {$quantity} kg into Grade {$targetGrade->value} (Batch {$result['child']->reference})."
                : "Recorded {$quantity} kg unsellable damage from batch {$batch->reference}.";

            $redirectUrl = $result['parent']->canBeSorted()
                ? route('inventory.batches.sort', $result['parent'])
                : route('inventory.batches.show', $result['parent']);

            return redirect()->to($redirectUrl)
                ->with('success', $msg);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function reverseSegregation(ReverseSegregationRequest $request, StockBatch $batch): RedirectResponse
    {
        $validated = $request->validated();
        $quantity = (float) $validated['quantity'];
        $notes = $validated['notes'] ?? null;
        $clientSubmissionId = $validated['client_submission_id'] ?? null;

        try {
            $result = $this->segregationAction->reverse(
                $batch,
                $quantity,
                (int) $request->user()->id,
                $notes,
                $clientSubmissionId
            );

            return redirect()->route('inventory.batches.show', $result['parent'])
                ->with('success', "Reversed {$quantity} kg from batch {$batch->reference} back to parent batch {$result['parent']->reference}.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    public function destroy(StockBatch $batch): RedirectResponse
    {
        if ($batch->segregatedBatches()->exists()) {
            return back()->with('error', 'Cannot delete batch with active segregated child batches. Reverse segregations first.');
        }

        if ($batch->stockMovements()->whereIn('type', [
            StockMovementType::Out->value,
            StockMovementType::Sale->value,
        ])->exists()) {
            return back()->with('error', 'Cannot delete batch with recorded stock dispatches.');
        }

        $this->service->delete($batch);

        return redirect()->route('inventory.batches.index')
            ->with('success', 'Batch deleted.');
    }
}
