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
            'purchasing.grn.approve',
            'purchasing.price.view',
            'purchasing.price.update',

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
            'admin.daily-progress.view',
            'admin.activity-log.view',

            // Warehouse checklist
            'warehouse.checklist.view',
            'warehouse.checklist.toggle',
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
            ],

            'purchase' => [
                'inventory.product.view',
                'inventory.stock.view',
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
                'accounting.report.view',
            ],

            'warehouse' => [
                'inventory.product.view',
                'inventory.stock.view',
                'inventory.sorting.view',
                'inventory.sorting.process',
                'inventory.wastage.view',
                'inventory.wastage.record',
                'purchasing.grn.view',
                'purchasing.grn.create',
                'sales.order.view',
                'warehouse.checklist.view',
                'warehouse.checklist.toggle',
            ],
        ];

        // Clean up legacy roles that are no longer supported
        Role::whereNotIn('name', array_keys($roles))->delete();

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        $this->command->info('✅ Roles and permissions seeded successfully.');
    }
}
