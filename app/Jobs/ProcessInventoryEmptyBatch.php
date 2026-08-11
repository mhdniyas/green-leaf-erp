<?php
declare(strict_types=1);
namespace App\Jobs;
use App\Models\InventoryEmptyProcess;
use App\Models\InventoryEmptyProcessItem;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
class ProcessInventoryEmptyBatch implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=3; public int $timeout=120;
    public function backoff(): array { return [1, 5, 10]; }
    public function __construct(public int $processId) {}
    public function handle(StockAdjustmentService $stock): void {
        $process=InventoryEmptyProcess::find($this->processId); if (! $process || !in_array($process->status,['pending','running'],true)) return;
        $process->update(['status'=>'running','started_at'=>$process->started_at ?? now()]);
        $items=InventoryEmptyProcessItem::where('process_id',$process->id)->where('status','pending')->orderBy('id')->limit(10)->get();
        foreach($items as $item) {
            try { DB::transaction(function() use($process,$item,$stock) { $locked=InventoryEmptyProcessItem::lockForUpdate()->findOrFail($item->id); if($locked->status!=='pending') return; $process->update(['current_product_id'=>$locked->product_id]); $stock->emptyProductInventory($locked->product_id,$process->started_by); $locked->update(['status'=>'success']); $process->increment('processed_records'); $process->increment('successful_records'); }); }
            catch(\Throwable $e) { $item->update(['status'=>'failed','error_message'=>$e->getMessage()]); $process->increment('processed_records'); $process->increment('failed_records'); }
        }
        $process->refresh();
        if (InventoryEmptyProcessItem::where('process_id',$process->id)->where('status','pending')->exists()) { self::dispatch($process->id); return; }
        $process->update(['status'=>$process->failed_records ? 'completed_with_errors' : 'completed','current_product_id'=>null,'completed_at'=>now()]);
    }
    public function failed(\Throwable $exception): void { InventoryEmptyProcess::whereKey($this->processId)->update(['status'=>'failed','error_message'=>$exception->getMessage(),'completed_at'=>now()]); }
}
