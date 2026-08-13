<?php

declare(strict_types=1);

namespace App\Models\Cashbook;

use Illuminate\Database\Eloquent\Model;

class ShopDailyLedgerSnapshot extends Model
{
    protected $table = 'shop_daily_ledger_snapshots';

    protected $fillable = [
        'shop_id', 'business_date',
        'total_sales', 'total_income', 'total_expense', 'net_pl',
        'opening_petty', 'petty_in', 'petty_out', 'closing_petty',
        'opening_shop_position', 'settlement_increase', 'settlement_decrease', 'closing_shop_position',
        'opening_company_pending', 'company_pending_in', 'company_pending_out', 'closing_company_pending',
        'status', 'closed_at', 'closed_by',
    ];

    protected $casts = [
        'business_date'           => 'date:Y-m-d',
        'total_sales'             => 'decimal:2',
        'total_income'            => 'decimal:2',
        'total_expense'           => 'decimal:2',
        'net_pl'                  => 'decimal:2',
        'opening_petty'           => 'decimal:2',
        'petty_in'                => 'decimal:2',
        'petty_out'               => 'decimal:2',
        'closing_petty'           => 'decimal:2',
        'opening_shop_position'   => 'decimal:2',
        'settlement_increase'     => 'decimal:2',
        'settlement_decrease'     => 'decimal:2',
        'closing_shop_position'   => 'decimal:2',
        'opening_company_pending' => 'decimal:2',
        'company_pending_in'      => 'decimal:2',
        'company_pending_out'     => 'decimal:2',
        'closing_company_pending' => 'decimal:2',
        'closed_at'               => 'datetime',
    ];
}
