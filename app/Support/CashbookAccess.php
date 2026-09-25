<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CashbookAccess
{
    public const Access = 'cashbook.access';

    public const DashboardView = 'cashbook.dashboard.view';

    public const ShopsView = 'cashbook.shops.view';

    public const ShopsManage = 'cashbook.shops.manage';

    public const MoneyFlowView = 'cashbook.money-flow.view';

    public const MoneyFlowManage = 'cashbook.money-flow.manage';

    public const AccountBalanceView = 'cashbook.account-balance.view';

    public const AccountBalanceManage = 'cashbook.account-balance.manage';

    public const ReconciliationView = 'cashbook.reconciliation.view';

    public const ReconciliationManage = 'cashbook.reconciliation.manage';

    public const InventoryView = 'cashbook.inventory.view';

    public const InventoryManage = 'cashbook.inventory.manage';

    public const ReportsView = 'cashbook.reports.view';

    public const SettingsView = 'cashbook.settings.view';

    public const SettingsManage = 'cashbook.settings.manage';

    public const UserAccessManage = 'cashbook.user-access.manage';

    // Legacy permissions retained for backward compatibility
    public const LegacyMonthlyReportView = 'cashbook.monthly-report.view';

    public const LegacyMonthlyReportExport = 'cashbook.monthly-report.export';

    public const LegacyMonthlyReportSettingsView = 'cashbook.monthly-report.settings.view';

    public const LegacyMonthlyReportSettingsManage = 'cashbook.monthly-report.settings.manage';

    /**
     * @return array<string, array{label: string, view_permission: ?string, manage_permission: ?string, route: string, description: string}>
     */
    public static function sections(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'view_permission' => self::DashboardView,
                'manage_permission' => null,
                'route' => 'admin.cashbook.index',
                'description' => 'Cashbook overview and summary',
            ],
            'shops' => [
                'label' => 'All Shops',
                'view_permission' => self::ShopsView,
                'manage_permission' => self::ShopsManage,
                'route' => 'admin.cashbook.all-shops',
                'description' => 'Shop ledgers, daily transactions, allocations',
            ],
            'money-flow' => [
                'label' => 'Money Flow',
                'view_permission' => self::MoneyFlowView,
                'manage_permission' => self::MoneyFlowManage,
                'route' => 'admin.cashbook.money-flow',
                'description' => 'Money flow tracking and transaction ledger',
            ],
            'account-balance' => [
                'label' => 'Account Balance',
                'view_permission' => self::AccountBalanceView,
                'manage_permission' => self::AccountBalanceManage,
                'route' => 'admin.cashbook.account-balance',
                'description' => 'Bank accounts and closing balances',
            ],
            'reconciliation' => [
                'label' => 'Reconciliation',
                'view_permission' => self::ReconciliationView,
                'manage_permission' => self::ReconciliationManage,
                'route' => 'admin.cashbook.finance.reconciliation',
                'description' => 'Bank statement classification and matching',
            ],
            'inventory' => [
                'label' => 'Inventory',
                'view_permission' => self::InventoryView,
                'manage_permission' => self::InventoryManage,
                'route' => 'admin.cashbook.inventory',
                'description' => 'Inventory reports, advances, and auto-match',
            ],
            'reports' => [
                'label' => 'Reports',
                'view_permission' => self::ReportsView,
                'manage_permission' => null,
                'route' => 'admin.cashbook.reports',
                'description' => 'Analytics, GL bills, and monthly statements',
            ],
            'settings' => [
                'label' => 'Settings',
                'view_permission' => self::SettingsView,
                'manage_permission' => self::SettingsManage,
                'route' => 'admin.cashbook.settings',
                'description' => 'Cashbook configuration and categories',
            ],
            'user-access' => [
                'label' => 'User Access',
                'view_permission' => null,
                'manage_permission' => self::UserAccessManage,
                'route' => 'admin.cashbook.user-access.index',
                'description' => 'Manage Cashbook permissions for system users',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allNewPermissions(): array
    {
        return [
            self::Access,
            self::DashboardView,
            self::ShopsView,
            self::ShopsManage,
            self::MoneyFlowView,
            self::MoneyFlowManage,
            self::AccountBalanceView,
            self::AccountBalanceManage,
            self::ReconciliationView,
            self::ReconciliationManage,
            self::InventoryView,
            self::InventoryManage,
            self::ReportsView,
            self::SettingsView,
            self::SettingsManage,
            self::UserAccessManage,
        ];
    }

    /**
     * @return list<string>
     */
    public static function fullAccessPermissions(): array
    {
        return [
            self::Access,
            self::DashboardView,
            self::ShopsView,
            self::ShopsManage,
            self::MoneyFlowView,
            self::MoneyFlowManage,
            self::AccountBalanceView,
            self::AccountBalanceManage,
            self::ReconciliationView,
            self::ReconciliationManage,
            self::InventoryView,
            self::InventoryManage,
            self::ReportsView,
            self::SettingsView,
            self::SettingsManage,
        ];
    }

    /**
     * @return list<string>
     */
    public static function legacyPermissions(): array
    {
        return [
            self::LegacyMonthlyReportView,
            self::LegacyMonthlyReportExport,
            self::LegacyMonthlyReportSettingsView,
            self::LegacyMonthlyReportSettingsManage,
        ];
    }

    /**
     * @return list<string>
     */
    public static function normalViewPermissions(): array
    {
        return [
            self::DashboardView,
            self::ShopsView,
            self::MoneyFlowView,
            self::AccountBalanceView,
            self::ReconciliationView,
            self::InventoryView,
            self::ReportsView,
            self::SettingsView,
        ];
    }

    /**
     * @return list<string>
     */
    public static function normalManagePermissions(): array
    {
        return [
            self::ShopsManage,
            self::MoneyFlowManage,
            self::AccountBalanceManage,
            self::ReconciliationManage,
            self::InventoryManage,
            self::SettingsManage,
        ];
    }

    public static function allows(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('admin') || $user->isMainAdmin()) {
            return true;
        }

        if ($user->can($permission)) {
            return true;
        }

        if (str_ends_with($permission, '.view')) {
            $managePermission = substr($permission, 0, -5).'.manage';
            if ($user->can($managePermission)) {
                return true;
            }
        }

        return false;
    }

    public static function canAccessCashbook(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole('admin') || $user->isMainAdmin()) {
            return true;
        }

        if ($user->hasRole('shop') && ($user->shop_id || $user->ownedShopAssignments()->exists())) {
            return true;
        }

        if ($user->can(self::Access)) {
            return true;
        }

        foreach (self::allNewPermissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        foreach (self::legacyPermissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{label: string, route: string, description: string}>
     */
    public static function accessibleSections(User $user): array
    {
        $sections = self::sections();
        $accessible = [];

        foreach ($sections as $key => $section) {
            $canView = false;
            if ($section['view_permission'] && self::allows($user, $section['view_permission'])) {
                $canView = true;
            }
            if ($section['manage_permission'] && self::allows($user, $section['manage_permission'])) {
                $canView = true;
            }
            if ($key === 'dashboard' && self::canAccessCashbook($user)) {
                $canView = true;
            }

            if ($canView) {
                $accessible[$key] = [
                    'label' => $section['label'],
                    'route' => $section['route'],
                    'description' => $section['description'],
                ];
            }
        }

        return $accessible;
    }

    public static function userAccessMode(User $user): string
    {
        if ($user->hasRole('admin') || $user->isMainAdmin()) {
            return 'full_access';
        }

        $normalViews = self::normalViewPermissions();
        $normalManages = self::normalManagePermissions();

        $userDirectPermissions = $user->permissions->pluck('name')->all();
        $userAllPermissions = $user->getAllPermissions()->pluck('name')->all();
        $permissions = array_unique(array_merge($userDirectPermissions, $userAllPermissions));

        $cashbookPermissions = array_filter($permissions, fn ($p) => str_starts_with((string) $p, 'cashbook.'));

        if (empty($cashbookPermissions)) {
            return 'no_access';
        }

        $hasAllNormalViews = count(array_intersect($normalViews, $cashbookPermissions)) === count($normalViews);
        $hasAllNormalManages = count(array_intersect($normalManages, $cashbookPermissions)) === count($normalManages);
        $hasAnyManages = count(array_intersect($normalManages, $cashbookPermissions)) > 0;

        if ($hasAllNormalViews && $hasAllNormalManages) {
            return 'full_access';
        }

        if (! $hasAnyManages && count(array_intersect($normalViews, $cashbookPermissions)) > 0) {
            return 'read_only';
        }

        return 'custom';
    }

    /**
     * Safely updates ONLY Cashbook-related permissions on the user.
     *
     * @param  list<string>  $newCashbookPermissions
     */
    public static function updateUserPermissions(User $user, array $newCashbookPermissions): void
    {
        // 1. Get all current direct permissions that are NOT cashbook permissions
        $nonCashbookPermissions = $user->permissions
            ->reject(fn ($p) => str_starts_with((string) $p->name, 'cashbook.'))
            ->pluck('name')
            ->all();

        // 2. Filter requested cashbook permissions to only valid defined ones
        $validNewCashbookPermissions = array_intersect(
            $newCashbookPermissions,
            array_merge(self::allNewPermissions(), self::legacyPermissions())
        );

        // Manage implies View
        foreach (self::sections() as $section) {
            if ($section['manage_permission'] && in_array($section['manage_permission'], $validNewCashbookPermissions, true)) {
                if ($section['view_permission'] && ! in_array($section['view_permission'], $validNewCashbookPermissions, true)) {
                    $validNewCashbookPermissions[] = $section['view_permission'];
                }
            }
        }

        // 3. If user has any cashbook view or manage permission, ensure cashbook.access is included
        if (! empty($validNewCashbookPermissions) && ! in_array(self::Access, $validNewCashbookPermissions, true)) {
            $validNewCashbookPermissions[] = self::Access;
        }

        // 4. Combine non-cashbook permissions + filtered cashbook permissions
        $allToSync = array_values(array_unique(array_merge($nonCashbookPermissions, $validNewCashbookPermissions)));

        // Ensure permissions exist in DB first
        foreach ($validNewCashbookPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $user->syncPermissions($allToSync);
        $user->unsetRelation('permissions');
        $user->unsetRelation('roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
