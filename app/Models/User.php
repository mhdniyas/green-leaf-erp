<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

#[Fillable(['public_uuid', 'name', 'email', 'password', 'shop_id', 'registration_status', 'approved_at', 'approved_by', 'own_purchase_purchaser_id'])]
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
}
