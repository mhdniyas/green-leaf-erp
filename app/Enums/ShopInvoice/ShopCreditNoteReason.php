<?php

declare(strict_types=1);

namespace App\Enums\ShopInvoice;

enum ShopCreditNoteReason: string
{
    case PriceCorrection = 'price_correction';
    case Shortage = 'shortage';
    case DamageWastage = 'damage_wastage';
    case ReturnToWarehouse = 'return_to_warehouse';
    case ExcessDelivered = 'excess_delivered';
    case GradeDowngrade = 'grade_downgrade';
    case CommercialDiscount = 'commercial_discount';
    case Goodwill = 'goodwill';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PriceCorrection => 'Price Correction',
            self::Shortage => 'Delivery Shortage',
            self::DamageWastage => 'Damaged / Wastage',
            self::ReturnToWarehouse => 'Returned to Warehouse',
            self::ExcessDelivered => 'Excess Delivered',
            self::GradeDowngrade => 'Grade Downgrade',
            self::CommercialDiscount => 'Commercial Discount',
            self::Goodwill => 'Goodwill / Commercial Discount',
            self::Other => 'Other Adjustment',
        };
    }
}
