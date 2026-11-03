<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementExpenseCategory extends Model
{
    use HasFactory;

    public const SUB_CATEGORY_MODE_NONE = 'none';

    public const SUB_CATEGORY_MODE_OPTIONAL = 'optional';

    public const SUB_CATEGORY_MODE_REQUIRED = 'required';

    protected $table = 'procurement_expense_categories';

    protected $fillable = [
        'key',
        'name',
        'sub_category_mode',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subCategories(): HasMany
    {
        return $this->hasMany(ProcurementExpenseSubCategory::class, 'procurement_expense_category_id');
    }

    public function activeSubCategories(): HasMany
    {
        return $this->hasMany(ProcurementExpenseSubCategory::class, 'procurement_expense_category_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProcurementExpense::class, 'category', 'key');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function requiresSubCategory(): bool
    {
        return $this->sub_category_mode === self::SUB_CATEGORY_MODE_REQUIRED;
    }

    public function allowsSubCategory(): bool
    {
        return in_array($this->sub_category_mode, [self::SUB_CATEGORY_MODE_OPTIONAL, self::SUB_CATEGORY_MODE_REQUIRED], true);
    }
}
