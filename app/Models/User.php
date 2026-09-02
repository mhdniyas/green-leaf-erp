<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['public_uuid', 'name', 'email', 'password', 'shop_id', 'registration_status', 'approved_at', 'approved_by', 'own_purchase_purchaser_id', 'assigned_category_ids', 'vendor_visibility'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasPermissions, HasRoles, LogsActivity, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'approved_at' => 'datetime',
            'assigned_category_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! $user->public_uuid) {
                $user->public_uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the shop associated with the user.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Warehouses explicitly assigned to this user.
     *
     * @return BelongsToMany<Warehouse, $this>
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function hasAllWarehouseAccess(): bool
    {
        return $this->can('warehouse.loadout.all');
    }

    public function canAccessWarehouse(Warehouse|int $warehouse): bool
    {
        if ($this->hasAllWarehouseAccess()) {
            return true;
        }

        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->getKey() : $warehouse;

        return $this->warehouses()->whereKey($warehouseId)->exists();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gte(now()->subMinutes(5));
    }

    public function isPendingRegistration(): bool
    {
        return $this->registration_status === 'pending';
    }

    public function hasApprovedRegistration(): bool
    {
        return $this->registration_status === 'approved';
    }

    public function isMainAdmin(): bool
    {
        $mainAdminEmail = Str::lower((string) config('admin.user_access.main_admin_email'));

        return $this->hasRole('admin')
            && $mainAdminEmail !== ''
            && Str::lower((string) $this->email) === $mainAdminEmail;
    }

    public function purchaserCredits(): HasMany
    {
        return $this->hasMany(PurchaserCredit::class, 'purchaser_id');
    }

    public function ownPurchasePurchaser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'own_purchase_purchaser_id');
    }

    public function linkedAdminForOwnPurchase(): HasOne
    {
        return $this->hasOne(self::class, 'own_purchase_purchaser_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function ownedShopAssignments(): HasMany
    {
        return $this->hasMany(ShopOwnerAssignment::class);
    }

    /**
     * @return array<int, int>
     */
    public function assignedCategoryIds(): array
    {
        $raw = $this->assigned_category_ids;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw)));
    }

    public function hasAssignedCategoryFilter(): bool
    {
        return count($this->assignedCategoryIds()) > 0;
    }

    public function vendorVisibility(): string
    {
        return strtolower((string) ($this->vendor_visibility ?? 'all'));
    }

    public function showsAllVendors(): bool
    {
        return $this->vendorVisibility() === 'all';
    }

    public function showsRelatedVendorsOnly(): bool
    {
        return $this->vendorVisibility() === 'related';
    }

    /**
     * @return Builder<Supplier>
     */
    public function scopedSuppliersQuery(): Builder
    {
        $query = Supplier::query();

        if (! $this->hasRole('purchaser') || $this->showsAllVendors()) {
            return $query;
        }

        $assignedCategoryIds = $this->assignedCategoryIds();
        $totalActiveCategories = Category::query()->where('is_active', true)->count();
        $isNarrowedCategoryFilter = count($assignedCategoryIds) > 0 && count($assignedCategoryIds) < $totalActiveCategories;
        $userId = (int) $this->id;

        return $query->where(function (Builder $q) use ($assignedCategoryIds, $isNarrowedCategoryFilter, $userId): void {
            $hasSubClause = false;

            if ($isNarrowedCategoryFilter) {
                $q->whereHas('products', fn (Builder $pq) => $pq->whereIn('category_id', $assignedCategoryIds));
                $hasSubClause = true;
            }

            if ($hasSubClause) {
                $q->orWhereHas('purchaserCarts', fn (Builder $cq) => $cq->where('user_id', $userId))
                    ->orWhereHas('purchaseOrders', fn (Builder $poq) => $poq->where('created_by', $userId))
                    ->orWhereHas('purchaseInvoices', fn (Builder $piq) => $piq->where('purchaser_submitted_by', $userId)->orWhereHas('purchaserCart', fn (Builder $cq) => $cq->where('user_id', $userId)));
            } else {
                $q->where(function (Builder $directQ) use ($userId): void {
                    $directQ->whereHas('purchaserCarts', fn (Builder $cq) => $cq->where('user_id', $userId))
                        ->orWhereHas('purchaseOrders', fn (Builder $poq) => $poq->where('created_by', $userId))
                        ->orWhereHas('purchaseInvoices', fn (Builder $piq) => $piq->where('purchaser_submitted_by', $userId)->orWhereHas('purchaserCart', fn (Builder $cq) => $cq->where('user_id', $userId)));
                });
            }
        });
    }
}
