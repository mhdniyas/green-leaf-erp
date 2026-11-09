<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteReceiptStatus: string
{
    case NotApplicable = 'not_applicable';
    case PendingReceipt = 'pending_receipt';
    case PartiallyReceived = 'partially_received';
    case ReceivedAndVerified = 'received_and_verified';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Not Applicable',
            self::PendingReceipt => 'Pending Receipt',
            self::PartiallyReceived => 'Partially Received',
            self::ReceivedAndVerified => 'Received & Verified',
            self::Disputed => 'Not Received / Disputed',
        };
    }

    public static function tryResolve(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'pending_receipt', 'pending' => self::PendingReceipt,
            'partially_received', 'partial' => self::PartiallyReceived,
            'received_and_verified', 'received', 'verified' => self::ReceivedAndVerified,
            'disputed', 'not_received' => self::Disputed,
            'not_applicable', 'none' => self::NotApplicable,
            default => self::tryFrom($value),
        };
    }

    public function isPending(): bool
    {
        return $this === self::PendingReceipt;
    }

    public function isPartiallyReceived(): bool
    {
        return $this === self::PartiallyReceived;
    }

    public function isReceived(): bool
    {
        return $this === self::ReceivedAndVerified;
    }

    public function isDisputed(): bool
    {
        return $this === self::Disputed;
    }

    public function isNotApplicable(): bool
    {
        return $this === self::NotApplicable;
    }
}
