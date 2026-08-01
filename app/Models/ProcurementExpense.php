<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementExpense extends Model
{
    public const CategoryVehicle = 'vehicle';

    public const CategoryLabour = 'labour';

    public const CategoryFood = 'food';

    public const CategoryFuel = 'fuel';

    public const CategoryTollParking = 'toll_parking';

    public const CategoryOther = 'other';

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CategoryVehicle => 'Vehicle',
            self::CategoryLabour => 'Labour',
            self::CategoryFood => 'Food',
            self::CategoryFuel => 'Fuel',
            self::CategoryTollParking => 'Toll/Parking',
            self::CategoryOther => 'Other',
        ];
    }

    protected $fillable = [
        'user_id',
        'company_accounting_entry_id',
        'expense_date',
        'category',
        'amount',
        'note',
    ];

    protected $casts = [
        'expense_date' => 'date',
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

    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? str((string) $this->category)->replace('_', ' ')->title()->toString();
    }
}
