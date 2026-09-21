<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashbookMonthlyReportExpenseMapping extends Model
{
    protected $table = 'cashbook_monthly_report_expense_mappings';

    protected $fillable = [
        'source_type',
        'source_key',
        'report_bucket',
        'created_by',
        'updated_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
