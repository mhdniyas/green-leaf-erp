<?php

declare(strict_types=1);

namespace App\Enums\Purchasing;

enum ShopPurchaserDailyVerificationStatus: string
{
    case Open = 'open';
    case Ready = 'ready';
    case UserVerified = 'user_verified';
    case SecondVerified = 'second_verified';
    case Finalized = 'finalized';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Ready => 'Ready for Verification',
            self::UserVerified => 'User Verified',
            self::SecondVerified => 'Second Verified',
            self::Finalized => 'Finalized',
            self::Reopened => 'Reopened',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-slate-100 text-slate-700 border-slate-200',
            self::Ready => 'bg-amber-50 text-amber-800 border-amber-200',
            self::UserVerified => 'bg-blue-50 text-blue-800 border-blue-200',
            self::SecondVerified => 'bg-indigo-50 text-indigo-800 border-indigo-200',
            self::Finalized => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Reopened => 'bg-rose-50 text-rose-800 border-rose-200',
        };
    }

    public function isFinalized(): bool
    {
        return $this === self::Finalized;
    }
}
