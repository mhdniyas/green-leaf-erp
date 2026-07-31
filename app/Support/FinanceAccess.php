<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

class FinanceAccess
{
    public const DashboardView = 'finance.dashboard.view';

    public const PaymentsView = 'finance.payments.view';

    public const PaymentsCreate = 'finance.payments.create';

    public const PaymentsApprove = 'finance.payments.approve';

    public const PaymentsReject = 'finance.payments.reject';

    public const PaymentsSettle = 'finance.payments.settle';

    public const CompanyPayablesView = 'finance.company-payables.view';

    public const CompanyPayablesReview = 'finance.company-payables.review';

    public const CompanyPayablesSettle = 'finance.company-payables.settle';

    public const PettyView = 'finance.petty.view';

    public const PettyManage = 'finance.petty.manage';

    public const JournalsView = 'finance.journals.view';

    public const JournalsReverse = 'finance.journals.reverse';

    public const ShopAccountingView = 'shop.accounting.view';

    public const ShopAccountingCreate = 'shop.accounting.create';

    public const ShopAccountingSubmit = 'shop.accounting.submit';

    public const ShopCompanyPayablesView = 'shop.company-payables.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DashboardView,
            self::PaymentsView,
            self::PaymentsCreate,
            self::PaymentsApprove,
            self::PaymentsReject,
            self::PaymentsSettle,
            self::CompanyPayablesView,
            self::CompanyPayablesReview,
            self::CompanyPayablesSettle,
            self::PettyView,
            self::PettyManage,
            self::JournalsView,
            self::JournalsReverse,
            self::ShopAccountingView,
            self::ShopAccountingCreate,
            self::ShopAccountingSubmit,
            self::ShopCompanyPayablesView,
        ];
    }

    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null && ($user->hasRole('admin') || $user->can($permission));
    }

    public static function canViewDashboard(?User $user): bool
    {
        return self::allows($user, self::DashboardView)
            || AccountingAccess::canViewDashboard($user);
    }

    public static function canViewPayments(?User $user): bool
    {
        return self::allows($user, self::PaymentsView) || self::canViewDashboard($user);
    }

    public static function canCreatePayments(?User $user): bool
    {
        return self::allows($user, self::PaymentsCreate);
    }

    public static function canApprovePayments(?User $user): bool
    {
        return self::allows($user, self::PaymentsApprove);
    }

    public static function canRejectPayments(?User $user): bool
    {
        return self::allows($user, self::PaymentsReject);
    }

    public static function canSettlePayments(?User $user): bool
    {
        return self::allows($user, self::PaymentsSettle);
    }

    public static function canViewCompanyPayables(?User $user): bool
    {
        return self::allows($user, self::CompanyPayablesView) || self::canViewDashboard($user);
    }

    public static function canReviewCompanyPayables(?User $user): bool
    {
        return self::allows($user, self::CompanyPayablesReview);
    }

    public static function canSettleCompanyPayables(?User $user): bool
    {
        return self::allows($user, self::CompanyPayablesSettle);
    }
}
