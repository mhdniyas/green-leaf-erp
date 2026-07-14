<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

class AccountingAccess
{
    public const DashboardView = 'accounting.dashboard.view';

    public const OwnedShopManage = 'accounting.owned-shop.manage';

    public const EntryReview = 'accounting.entry.review';

    public const InvoiceGenerate = 'accounting.invoice.generate';

    public const InvoiceApprove = 'accounting.invoice.approve';

    public const PurchaserCashManage = 'accounting.purchaser-cash.manage';

    public const ReportExport = 'accounting.report.export';

    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null && ($user->hasRole('admin') || $user->can($permission));
    }

    public static function canViewDashboard(?User $user): bool
    {
        return self::allows($user, self::DashboardView);
    }

    public static function canManageOwnedShops(?User $user): bool
    {
        return self::allows($user, self::OwnedShopManage);
    }

    public static function canReviewEntries(?User $user): bool
    {
        return self::allows($user, self::EntryReview);
    }

    public static function canGenerateInvoices(?User $user): bool
    {
        return self::allows($user, self::InvoiceGenerate);
    }

    public static function canManagePurchaserCash(?User $user): bool
    {
        return self::allows($user, self::PurchaserCashManage);
    }
}
