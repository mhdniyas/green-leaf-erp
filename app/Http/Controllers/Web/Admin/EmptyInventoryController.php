<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInventoryEmptyBatch;
use App\Models\InventoryEmptyProcess;
use App\Models\InventoryEmptyProcessItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $process = DB::transaction(function () use ($request) {
            abort_if(InventoryEmptyProcess::lockForUpdate()->whereIn('status', ['pending', 'running'])->exists(), 409, 'An inventory empty process is already running.');
            $ids = $this->productIds();
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

        return response()->json(['status' => $process->status, 'total' => $process->total_records, 'processed' => $process->processed_records, 'successful' => $process->successful_records, 'failed' => $process->failed_records, 'current_product' => $process->currentProduct?->name, 'percent' => $process->total_records ? (int) round($process->processed_records / $process->total_records * 100) : 100]);
    }

    public function retry(Request $request, InventoryEmptyProcess $process): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(in_array($process->status, ['completed_with_errors', 'failed'], true), 422);
        $count = $process->items()->where('status', 'failed')->update(['status' => 'pending', 'error_message' => null]);
        $process->update(['status' => 'pending', 'processed_records' => $process->processed_records - $count, 'failed_records' => 0, 'completed_at' => null]);
        ProcessInventoryEmptyBatch::dispatch($process->id);

        return back();
    }

    private function productIds()
    {
        return StockMovement::query()->distinct()->pluck('product_id')->merge(StockBatch::query()->distinct()->pluck('product_id'))->unique()->values();
    }
}
