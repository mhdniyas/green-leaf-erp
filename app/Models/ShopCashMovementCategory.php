<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopCashMovementCategory extends Model
{
    public const LOAN = 'Loan';

    public const ADVANCE_LOAN_FOR_SALARY = 'Advance Loan for Salary';

    protected $fillable = [
        'name',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function credits(): HasMany
    {
        return $this->hasMany(ShopCredit::class, 'shop_cash_movement_category_id');
    }

    public static function defaultCategory(): self
    {
        self::ensureLoanCategories();

        return self::query()->firstOrCreate(
            ['name' => self::LOAN],
            [
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }

    public static function ensureLoanCategories(): void
    {
        self::query()->where('is_default', true)->where('name', '!=', self::LOAN)->update(['is_default' => false]);

        self::query()->updateOrCreate(
            ['name' => self::LOAN],
            ['is_default' => true, 'is_active' => true],
        );

        self::query()->updateOrCreate(
            ['name' => self::ADVANCE_LOAN_FOR_SALARY],
            ['is_default' => false, 'is_active' => true],
        );
    }

    /**
     * @return Collection<int, self>
     */
    public static function loanCategoryOptions(): Collection
    {
        self::ensureLoanCategories();

        return self::query()
            ->whereIn('name', [self::LOAN, self::ADVANCE_LOAN_FOR_SALARY])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
