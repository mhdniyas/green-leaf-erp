<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class ProcurementExpense extends Model
{
    public const CategoryVehicle = 'vehicle';

    public const CategoryLabour = 'labour';

    public const CategoryFood = 'food';

    public const CategoryFuel = 'fuel';

    public const CategoryTollParking = 'toll_parking';

    public const CategoryOther = 'other';

    public const FUNDING_PURCHASER_ADVANCE = 'purchaser_advance';

    public const FUNDING_COMPANY_CASH = 'company_cash';

    public const FUNDING_COMPANY_BANK = 'company_bank';

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        $default = [
            self::CategoryVehicle => 'Vehicle',
            self::CategoryLabour => 'Labour',
            self::CategoryFood => 'Food',
            self::CategoryFuel => 'Fuel',
            self::CategoryTollParking => 'Toll/Parking',
            self::CategoryOther => 'Other',
        ];

        try {
            if (Schema::hasTable('procurement_expense_categories')) {
                $dbCategories = ProcurementExpenseCategory::query()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->pluck('name', 'key')
                    ->toArray();

                if (! empty($dbCategories)) {
                    return $dbCategories;
                }
            }
        } catch (\Throwable) {
            // Fallback to default
        }

        return $default;
    }

    protected $fillable = [
        'user_id',
        'company_accounting_entry_id',
        'expense_date',
        'category',
        'funding_source',
        'procurement_expense_sub_category_id',
        'sub_category',
        'amount',
        'note',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'procurement_expense_sub_category_id' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function companyAccountingEntry(): BelongsTo
    {
        return $this->belongsTo(CompanyAccountingEntry::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(ProcurementExpenseSubCategory::class, 'procurement_expense_sub_category_id');
    }

    public function categoryDefinition(): BelongsTo
    {
        return $this->belongsTo(ProcurementExpenseCategory::class, 'category', 'key');
    }

    public function categoryLabel(): string
    {
        return $this->categoryDefinition?->name
            ?? self::categories()[$this->category]
            ?? str((string) $this->category)->replace('_', ' ')->title()->toString();
    }
}
