<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\ProductGrade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ShopDailyProductPrice extends Model
{
    use HasFactory;

    protected $attributes = [
        'product_grade' => 'A',
    ];

    protected $fillable = [
        'business_date',
        'shop_id',
        'product_id',
        'product_grade',
        'selling_price',
        'price_unit',
        'status',
        'reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'product_grade' => 'string',
        'selling_price' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function setProductGradeAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['product_grade'] = ProductGrade::GradeA->value;

            return;
        }

        $resolved = ProductGrade::tryResolve($value);

        if (! $resolved || ! in_array($resolved, ProductGrade::commercialGrades(), true)) {
            throw new InvalidArgumentException(
                "Invalid product grade [{$value}]. Allowed commercial grades are: A, B, C."
            );
        }

        $this->attributes['product_grade'] = $resolved->value;
    }

    public function productGrade(): ProductGrade
    {
        return ProductGrade::tryResolve($this->product_grade) ?? ProductGrade::GradeA;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeForGrade($query, mixed $grade)
    {
        $resolved = ProductGrade::tryResolve($grade);

        return $query->where('product_grade', $resolved?->value ?? (string) $grade);
    }
}
