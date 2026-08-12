<?php

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseGradePrice extends Model
{
    protected $fillable = ['product_id', 'business_date', 'grade', 'purchase_price', 'price_unit', 'status', 'approved_by', 'approved_at'];

    protected $attributes = ['grade' => 'A', 'price_unit' => 'kg', 'status' => 'approved'];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'grade' => ProductGrade::class, 'purchase_price' => 'decimal:4', 'approved_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
