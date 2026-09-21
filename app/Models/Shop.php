<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use RuntimeException;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'public_uuid',
        'name',
        'warehouse_tag',
        'shop_price_group_id',
        'client_id',
        'status',
        'accounting_mode',
        'accounting_enabled',
        'reserve_amount',
        'default_petty_cash_amount',
        'approved_at',
        'address',
        'contact_name',
        'contact_phone',
        'allow_grade_b_purchase',
        'shop_purchasing_enabled',
        'allow_vendor_creation',
        'vendor_purchase_edit_window_value',
        'vendor_purchase_edit_window_unit',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'accounting_enabled' => 'boolean',
            'allow_grade_b_purchase' => 'boolean',
            'shop_purchasing_enabled' => 'boolean',
            'allow_vendor_creation' => 'boolean',
            'vendor_purchase_edit_window_value' => 'integer',
            'vendor_purchase_edit_window_unit' => 'string',
            'reserve_amount' => 'decimal:2',
            'default_petty_cash_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $shop): void {
            $shop->public_uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $shop): void {
            if ($shop->isDirty('public_uuid')) {
                throw new RuntimeException('Shop routing identity cannot be changed.');
            }
        });
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(ShopPriceGroup::class, 'shop_price_group_id');
    }

    public function dailyProductPrices(): HasMany
    {
        return $this->hasMany(ShopDailyProductPrice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the users associated with the shop.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the orders placed by the shop.
     *
     * @return HasMany<ShopOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ShopOrder::class);
    }

    /**
     * Get the presets defined for the shop.
     *
     * @return HasMany<ShopPreset, $this>
     */
    public function presets(): HasMany
    {
        return $this->hasMany(ShopPreset::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ShopInvoice::class);
    }

    public function accountingCategories(): HasMany
    {
        return $this->hasMany(ShopAccountingCategory::class);
    }

    public function accountingEntries(): HasMany
    {
        return $this->hasMany(ShopAccountingEntry::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ShopCredit::class);
    }

    public function loanEntries(): HasMany
    {
        return $this->hasMany(ShopLoanEntry::class);
    }

    public function loanCategorySettings(): HasMany
    {
        return $this->hasMany(ShopLoanCategorySetting::class);
    }

    public function pettyCashExpenses(): HasMany
    {
        return $this->hasMany(ShopPettyCashExpense::class);
    }

    public function payrollPayments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function shopStaffPayments(): HasMany
    {
        return $this->hasMany(ShopStaffPayment::class);
    }

    public function ledgerEntrySettings(): HasMany
    {
        return $this->hasMany(ShopLedgerEntrySetting::class, 'shop_id');
    }

    public function advanceRequests(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceRequest::class);
    }

    public function contractWorkerPayments(): HasMany
    {
        return $this->hasMany(ContractWorkerPayment::class);
    }

    public function latestAccountingEntry(): HasOne
    {
        return $this->hasOne(ShopAccountingEntry::class)->latestOfMany('updated_at');
    }

    public function latestClosingAccountingEntry(): HasOne
    {
        return $this->hasOne(ShopAccountingEntry::class)->ofMany([
            'business_date' => 'max',
            'id' => 'max',
        ]);
    }

    public function accountingPeriodClosures(): HasMany
    {
        return $this->hasMany(ShopAccountingPeriodClosure::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'default_shop_id');
    }

    public function employeeAttendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function ownerAssignments(): HasMany
    {
        return $this->hasMany(ShopOwnerAssignment::class);
    }

    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'shop_employee_assignments')
            ->withPivot(['effective_from', 'effective_to', 'status', 'notes', 'assigned_by'])
            ->withTimestamps();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', 'active');
    }

    #[Scope]
    protected function ownedForStaff(Builder $query): void
    {
        $query->active()->cashbookEligible();
    }

    #[Scope]
    protected function cashbookEligible(Builder $query): void
    {
        $query
            ->where('accounting_enabled', true)
            ->where(function (Builder $query): void {
                $query->whereNotNull('client_id')
                    ->orWhere('accounting_mode', 'owned');
            });
    }

    #[Scope]
    protected function clientAccounting(Builder $query): void
    {
        $query->cashbookEligible()->whereNotNull('client_id');
    }

    #[Scope]
    protected function directOwned(Builder $query): void
    {
        $query->cashbookEligible()
            ->whereNull('client_id')
            ->where('accounting_mode', 'owned');
    }

    public function isOwnedAccountingEnabled(): bool
    {
        return (bool) $this->accounting_enabled
            && ((string) $this->accounting_mode === 'owned' || $this->client_id !== null);
    }

    public function isPurchasingEnabled(): bool
    {
        return (bool) $this->shop_purchasing_enabled;
    }

    public function isVendorCreationAllowed(): bool
    {
        return (bool) $this->allow_vendor_creation;
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'shop_suppliers')
            ->withPivot(['is_active', 'credit_approved'])
            ->withTimestamps();
    }

    public function vendorPayables(): HasMany
    {
        return $this->hasMany(ShopVendorPayable::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function isClientShop(): bool
    {
        return $this->client_id !== null;
    }

    public function getShopIdAttribute(): int
    {
        return $this->id;
    }
}
