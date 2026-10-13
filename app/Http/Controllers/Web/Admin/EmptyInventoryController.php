<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInventoryEmptyBatch;
use App\Models\InventoryEmptyProcess;
use App\Models\InventoryEmptyProcessItem;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmptyInventoryController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $active = InventoryEmptyProcess::whereIn('status', ['pending', 'running'])->latest()->first();

        return view('admin.inventory-empty.index', ['activeProcess' => $active, 'recordCount' => $this->productIds()->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['confirmation' => ['required', 'in:EMPTY WAREHOUSE']]);

        $ids = $this->productIds();
        if ($ids->isEmpty()) {
            return redirect()->route('admin.inventory-empty.index')->with('success', 'All inventory is already at zero stock. No products to clear.');
        }

        $process = DB::transaction(function () use ($request, $ids) {
            abort_if(InventoryEmptyProcess::lockForUpdate()->whereIn('status', ['pending', 'running'])->exists(), 409, 'An inventory empty process is already running.');
            $process = InventoryEmptyProcess::create(['status' => 'pending', 'total_records' => $ids->count(), 'started_by' => $request->user()->id]);
            foreach ($ids->chunk(500) as $chunk) {
                InventoryEmptyProcessItem::insert($chunk->map(fn ($id) => ['process_id' => $process->id, 'product_id' => $id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()])->all());
            }

            return $process;
        });
        ProcessInventoryEmptyBatch::dispatch($process->id);

        return redirect()->route('admin.inventory-empty.index', ['process' => $process->id]);
    }

    public function progress(Request $request, InventoryEmptyProcess $process): JsonResponse
    {
        $this->authorizeAdmin($request);
        $process->load('currentProduct:id,name');

        return response()->json([
            'status' => $process->status,
            'total' => $process->total_records,
            'processed' => $process->processed_records,
            'successful' => $process->successful_records,
            'failed' => $process->failed_records,
            'current_product' => $process->currentProduct?->name,
            'percent' => $process->total_records ? (int) round($process->processed_records / $process->total_records * 100) : 100,
            'is_active' => in_array($process->status, ['pending', 'running'], true),
        ]);
    }

    public function step(Request $request, InventoryEmptyProcess $process): JsonResponse
    {
        $this->authorizeAdmin($request);

        if (in_array($process->status, ['pending', 'running'], true)) {
            $batch = new ProcessInventoryEmptyBatch($process->id);
            $batch->processSingleBatch($process, app(StockAdjustmentService::class));
        }

        $process->refresh()->load('currentProduct:id,name');

        return response()->json([
            'status' => $process->status,
            'total' => $process->total_records,
            'processed' => $process->processed_records,
            'successful' => $process->successful_records,
            'failed' => $process->failed_records,
            'current_product' => $process->currentProduct?->name,
            'percent' => $process->total_records ? (int) round($process->processed_records / $process->total_records * 100) : 100,
            'is_active' => in_array($process->status, ['pending', 'running'], true),
        ]);
    }

    public function cancel(Request $request, InventoryEmptyProcess $process): RedirectResponse|JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(in_array($process->status, ['pending', 'running'], true), 422, 'Only active processes can be cancelled.');

        $adminName = $request->user()?->name ?? 'Admin';
        $process->update([
            'status' => 'cancelled',
            'error_message' => "Cancelled by admin {$adminName} on ".now()->toDateTimeString(),
            'completed_at' => now(),
            'current_product_id' => null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'cancelled',
                'message' => 'Empty warehouse process was cancelled.',
            ]);
        }

        return redirect()->route('admin.inventory-empty.index')->with('success', 'Empty warehouse process was cancelled.');
    }

    public function retry(Request $request, InventoryEmptyProcess $process): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(in_array($process->status, ['completed_with_errors', 'failed', 'cancelled'], true), 422);
        $count = $process->items()->whereIn('status', ['failed', 'pending'])->update(['status' => 'pending', 'error_message' => null]);
        $successfulCount = $process->items()->where('status', 'success')->count();
        $process->update([
            'status' => 'pending',
            'processed_records' => $successfulCount,
            'failed_records' => 0,
            'completed_at' => null,
            'error_message' => null,
        ]);
        ProcessInventoryEmptyBatch::dispatch($process->id);

        return back()->with('success', 'Process restarted.');
    }

    private function productIds(): Collection
    {
        return app(StockMovementRepository::class)
            ->currentStockByProductAndGrade()
            ->pluck('product_id')
            ->unique()
            ->values();
    }
}
