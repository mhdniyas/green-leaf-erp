<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Enums\Purchasing\ShopPurchaserDailyVerificationStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ShopPurchaserDailyVerification extends Model
{
    use LogsActivity;

    protected $table = 'shop_purchaser_daily_verifications';

    protected $fillable = [
        'uuid',
        'shop_id',
        'business_date',
        'purchaser_user_id',
        'status',
        'verified_by',
        'verified_at',
        'second_verified_by',
        'second_verified_at',
        'finalized_by',
        'finalized_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'summary_snapshot',
        'checklist_snapshot',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d',
        'status' => ShopPurchaserDailyVerificationStatus::class,
        'verified_at' => 'datetime',
        'second_verified_at' => 'datetime',
        'finalized_at' => 'datetime',
        'reopened_at' => 'datetime',
        'summary_snapshot' => 'array',
        'checklist_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ShopPurchaserDailyVerification $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('shop_purchaser_verification');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function secondVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_verified_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isOpen(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::Open;
    }

    public function isReady(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::Ready;
    }

    public function isUserVerified(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::UserVerified;
    }

    public function isSecondVerified(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::SecondVerified;
    }

    public function isFinalized(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::Finalized;
    }

    public function isReopened(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::Reopened;
    }

    public function canBeUserVerified(): bool
    {
        return in_array($this->status, [
            ShopPurchaserDailyVerificationStatus::Open,
            ShopPurchaserDailyVerificationStatus::Ready,
            ShopPurchaserDailyVerificationStatus::Reopened,
        ], true);
    }

    public function canBeSecondVerified(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::UserVerified;
    }

    public function canBeFinalized(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::SecondVerified;
    }

    public function canBeReopened(): bool
    {
        return $this->status === ShopPurchaserDailyVerificationStatus::Finalized;
    }
}
