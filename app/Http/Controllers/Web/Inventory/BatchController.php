<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\DTOs\Inventory\SortingData;
use App\DTOs\Inventory\StockBatchData;
use App\Exceptions\Inventory\BatchAlreadySortedException;
use App\Exceptions\Inventory\SortingQuantityMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\SortBatchRequest;
use App\Http\Requests\Web\Inventory\StoreStockBatchRequest;
use App\Models\StockBatch;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\InventorySortingSettingsService;
use App\Services\Inventory\StockBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function __construct(
        private readonly StockBatchService $service,
        private readonly ProductRepository $products,
        private readonly ProcessBatchSortingAction $sortingAction,
        private readonly InventorySortingSettingsService $sortingSettings,
    ) {}

    public function index(Request $request): View
    {
        $date = $request->input('date');

        $query = StockBatch::with(['product', 'createdBy', 'wastageEntries']);
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
        $batch->load(['product', 'stockMovements', 'wastageEntries', 'createdBy']);

        return view('inventory.batches.show', compact('batch'));
    }

    public function sort(StockBatch $batch): View
    {
        abort_unless($batch->canBeSorted(), 422, 'This batch has already been sorted.');

        $batch->load(['product']);

        return view('inventory.batches.sort', [
            'batch' => $batch,
            'sortAllAsGradeA' => $this->sortingSettings->sortAllAsGradeA(),
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

    public function destroy(StockBatch $batch): RedirectResponse
    {
        $this->service->delete($batch);

        return redirect()->route('inventory.batches.index')
            ->with('success', 'Batch deleted.');
    }
}
