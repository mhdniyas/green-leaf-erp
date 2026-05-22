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

            // Accounting
            'accounting.ledger.view',
            'accounting.entry.create',
            'accounting.report.view',
            'accounting.report.export',

            // HR
            'hr.employee.view',
            'hr.employee.create',
            'hr.employee.update',
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Define roles with their permissions
        $roles = [
            'super-admin' => $permissions, // All permissions

            'admin' => $permissions, // All permissions (same as super-admin, but super-admin can forceDelete)

            'inventory-manager' => [
                'inventory.product.view', 'inventory.product.create', 'inventory.product.update',
                'inventory.category.view', 'inventory.category.create', 'inventory.category.update',
                'inventory.stock.view', 'inventory.stock.adjust',
                'inventory.sorting.view', 'inventory.sorting.process',
                'inventory.wastage.view', 'inventory.wastage.record',
            ],

            'inventory-staff' => [
                'inventory.product.view',
                'inventory.category.view',
                'inventory.stock.view',
                'inventory.sorting.view', 'inventory.sorting.process',
                'inventory.wastage.view', 'inventory.wastage.record',
            ],

            'sales-manager' => [
                'inventory.product.view', 'inventory.stock.view',
                'sales.customer.view', 'sales.customer.create', 'sales.customer.update',
                'sales.order.view', 'sales.order.create', 'sales.order.confirm', 'sales.order.cancel',
                'sales.invoice.view', 'sales.invoice.create',
                'sales.payment.record',
                'accounting.report.view',
            ],

            'cashier' => [
                'inventory.product.view', 'inventory.stock.view',
                'sales.customer.view',
                'sales.order.view', 'sales.order.create',
                'sales.invoice.view',
                'sales.payment.record',
            ],

            'purchasing-manager' => [
                'inventory.product.view', 'inventory.stock.view',
                'purchasing.supplier.view', 'purchasing.supplier.create', 'purchasing.supplier.update',
                'purchasing.order.view', 'purchasing.order.create', 'purchasing.order.approve',
                'purchasing.grn.view', 'purchasing.grn.create',
                'accounting.report.view',
            ],

            'accountant' => [
                'accounting.ledger.view', 'accounting.entry.create',
                'accounting.report.view', 'accounting.report.export',
                'sales.invoice.view', 'sales.payment.record',
                'purchasing.order.view', 'purchasing.grn.view',
            ],

            'hr-manager' => [
                'hr.employee.view', 'hr.employee.create', 'hr.employee.update',
                'hr.payroll.view', 'hr.payroll.process',
            ],

            'viewer' => [
                'inventory.product.view', 'inventory.stock.view',
                'sales.order.view', 'sales.invoice.view',
                'purchasing.order.view',
                'accounting.report.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        $this->command->info('✅ Roles and permissions seeded successfully.');
    }
}
