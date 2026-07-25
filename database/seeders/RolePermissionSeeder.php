<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * ERP roles and their permissions.
     *
     * Convention: {module}.{resource}.{action}
     */
    public function run(): void
    {
        // Reset cached roles/permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Inventory
            'inventory.product.view',
            'inventory.product.create',
            'inventory.product.update',
            'inventory.product.delete',
            'inventory.category.view',
            'inventory.category.create',
            'inventory.category.update',
            'inventory.stock.view',
            'inventory.stock.adjust',
            'inventory.sorting.view',
            'inventory.sorting.process',
            'inventory.wastage.view',
            'inventory.wastage.record',

            // Sales
            'sales.customer.view',
            'sales.customer.create',
            'sales.customer.update',
            'sales.customer.delete',
            'sales.order.view',
            'sales.order.create',
            'sales.order.confirm',
            'sales.order.cancel',
            'sales.invoice.view',
            'sales.invoice.create',
            'sales.payment.record',

            // Purchasing
            'purchasing.supplier.view',
            'purchasing.supplier.create',
            'purchasing.supplier.update',
            'purchasing.order.view',
            'purchasing.order.create',
            'purchasing.order.approve',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'purchasing.price.view',
            'purchasing.price.update',

            // Accounting
            'accounting.ledger.view',
            'accounting.entry.create',
            'accounting.report.view',
            'accounting.report.export',
            'accounting.dashboard.view',
            'accounting.owned-shop.manage',
            'accounting.entry.review',
            'accounting.invoice.generate',
            'accounting.invoice.approve',
            'accounting.purchaser-cash.manage',

            // HR
            'hr.employee.view',
            'hr.employee.create',
            'hr.employee.update',
            'hr.attendance.view',
            'hr.attendance.manage',
            'hr.attendance.mark-owned-shop',
            'hr.leave.view',
            'hr.leave.manage',
            'hr.leave.submit-owned-shop',
            'hr.payroll.view',
            'hr.payroll.process',

            // Admin
            'admin.user.view',
            'admin.user.create',
            'admin.user.update',
            'admin.user.delete',
            'admin.role.view',
            'admin.role.manage',
            'admin.settings.view',
            'admin.settings.update',
            'admin.daily-progress.view',
            'admin.activity-log.view',

            // Warehouse receive
            'warehouse.receive.view',
            'warehouse.receive.confirm',

            // Warehouse checklist
            'warehouse.checklist.view',
            'warehouse.checklist.toggle',

            // Sort Sheet
            'sort.sheet.view',
            'sort.sheet.generate',
            'sort.sheet.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Define roles with their permissions
        $roles = [
            'admin' => $permissions, // All permissions

            'shop' => [
                'inventory.product.view',
                'sales.order.view',
                'sales.order.create',
                'sales.order.cancel',
                'hr.attendance.mark-owned-shop',
                'hr.leave.submit-owned-shop',
            ],

            'purchase' => [
                'inventory.product.view',
                'inventory.stock.view',
                'purchasing.order.view',
                'purchasing.order.approve',
                'purchasing.grn.view',
                'purchasing.grn.create',
                'purchasing.price.view',
                'purchasing.price.update',
                'sort.sheet.view',
                'sort.sheet.generate',
                'sort.sheet.export',
            ],

            'purchaser' => [
                'inventory.product.view',
                'purchasing.supplier.view',
                'purchasing.order.view',
                'purchasing.order.create',
                'purchasing.grn.view',
                'purchasing.grn.create',
            ],

            'warehouse_receiver' => [
                'inventory.product.view',
                'inventory.stock.view',
                'inventory.sorting.view',
                'inventory.sorting.process',
                'inventory.wastage.view',
                'inventory.wastage.record',
                'purchasing.grn.view',
                'purchasing.grn.create',
                'sales.order.view',
                'warehouse.receive.view',
                'warehouse.receive.confirm',
                'warehouse.checklist.view',
                'warehouse.checklist.toggle',
                'sort.sheet.view',
                'sort.sheet.generate',
                'sort.sheet.export',
            ],

            'hr_manager' => [
                'hr.employee.view',
                'hr.employee.create',
                'hr.employee.update',
                'hr.attendance.view',
                'hr.attendance.manage',
                'hr.leave.view',
                'hr.leave.manage',
                'hr.payroll.view',
                'hr.payroll.process',
            ],
        ];

        // Clean up legacy roles that are no longer supported
        Role::whereNotIn('name', array_keys($roles))->delete();

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        $this->command?->info('✅ Roles and permissions seeded successfully.');
    }
}
