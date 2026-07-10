<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'warehouse_tag',
        'shop_price_group_id',
        'status',
        'accounting_mode',
        'accounting_enabled',
        'reserve_amount',
        'approved_at',
        'address',
        'contact_name',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'accounting_enabled' => 'boolean',
            'reserve_amount' => 'decimal:2',
        ];
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(ShopPriceGroup::class, 'shop_price_group_id');
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

    public function ownerships(): HasMany
    {
        return $this->hasMany(ShopOwnership::class);
    }

    public function accountingCategories(): HasMany
    {
        return $this->hasMany(ShopAccountingCategory::class);
    }

    public function accountingEntries(): HasMany
    {
        return $this->hasMany(ShopAccountingEntry::class);
    }

    public function latestAccountingEntry(): HasOne
    {
        return $this->hasOne(ShopAccountingEntry::class)->latestOfMany('updated_at');
    }

    public function accountingInvoices(): HasMany
    {
        return $this->hasMany(ShopAccountingInvoice::class);
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
        $query
            ->active()
            ->where('accounting_enabled', true)
            ->whereIn('accounting_mode', ['owned', 'partnership']);
    }

    public function isOwnedAccountingEnabled(): bool
    {
        return (bool) $this->accounting_enabled
            && in_array((string) $this->accounting_mode, ['owned', 'partnership'], true);
    }
}
