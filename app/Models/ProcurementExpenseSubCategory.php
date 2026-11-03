<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementExpenseSubCategory extends Model
{
    use HasFactory;

    protected $table = 'procurement_expense_sub_categories';

    protected $fillable = [
        'procurement_expense_category_id',
        'name',
        'code',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'procurement_expense_category_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcurementExpenseCategory::class, 'procurement_expense_category_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProcurementExpense::class, 'procurement_expense_sub_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
