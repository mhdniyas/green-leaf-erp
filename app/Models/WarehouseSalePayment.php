<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\CompanyAccount;
use Database\Factories\WarehouseSalePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseSalePayment extends Model
{
    /** @use HasFactory<WarehouseSalePaymentFactory> */
    use HasFactory;

    public const MONEY_HOLDER_COMPANY = 'company';

    public const MONEY_HOLDER_USER = 'user';

    protected $fillable = [
        'warehouse_sale_id',
        'payment_method',
        'amount',
        'money_holder_type',
        'money_holder_user_id',
        'company_account_id',
        'reference',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function warehouseSale(): BelongsTo
    {
        return $this->belongsTo(WarehouseSale::class);
    }

    public function moneyHolderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'money_holder_user_id');
    }

    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function isCash(): bool
    {
        return strtolower($this->payment_method) === 'cash';
    }

    public function isHeldByUser(): bool
    {
        return $this->isCash() && $this->money_holder_type === self::MONEY_HOLDER_USER;
    }

    public function isHeldByCompany(): bool
    {
        return $this->isCash() && $this->money_holder_type === self::MONEY_HOLDER_COMPANY;
    }
}
