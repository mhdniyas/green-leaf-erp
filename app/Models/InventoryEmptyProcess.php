<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class InventoryEmptyProcess extends Model {
    protected $fillable=['status','total_records','processed_records','successful_records','failed_records','current_product_id','started_by','started_at','completed_at','error_message'];
    protected function casts(): array { return ['started_at'=>'datetime','completed_at'=>'datetime']; }
    public function items(): HasMany { return $this->hasMany(InventoryEmptyProcessItem::class,'process_id'); }
    public function currentProduct(): BelongsTo { return $this->belongsTo(Product::class,'current_product_id'); }
}
