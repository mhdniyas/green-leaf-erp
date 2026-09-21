<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherExpense extends Model
{
    public const CategoryTravel = 'travel';

    public const CategoryCommunication = 'communication';

    public const CategoryRepair = 'repair';

    public const CategoryLoading = 'loading';

    public const CategoryMiscellaneous = 'miscellaneous';

    public const CategoryOther = 'other';

    public const FUNDING_PURCHASER_ADVANCE = 'purchaser_advance';

    public const FUNDING_COMPANY_CASH = 'company_cash';

    public const FUNDING_COMPANY_BANK = 'company_bank';

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            self::CategoryTravel => 'Travel',
            self::CategoryCommunication => 'Communication',
            self::CategoryRepair => 'Repair',
            self::CategoryLoading => 'Loading',
            self::CategoryMiscellaneous => 'Miscellaneous',
            self::CategoryOther => 'Other',
        ];
    }

    protected $fillable = [
        'user_id',
        'company_accounting_entry_id',
        'expense_date',
        'category',
        'funding_source',
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
