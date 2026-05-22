<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Demo accounts for testing Phase 1.
     * All accounts use password: "password"
     *
     * @var array<int, array<string, string>>
     */
    private array $demoUsers = [
        [
            'name' => 'Admin User',
            'email' => 'admin@greenleaf.com',
            'role' => 'admin',
        ],
        [
            'name' => 'Inventory Manager',
            'email' => 'manager@greenleaf.com',
            'role' => 'inventory-manager',
        ],
        [
            'name' => 'Cashier',
            'email' => 'cashier@greenleaf.com',
            'role' => 'cashier',
        ],
        [
            'name' => 'Sales Manager',
            'email' => 'sales@greenleaf.com',
            'role' => 'sales-manager',
        ],
        [
            'name' => 'Accountant',
            'email' => 'accounts@greenleaf.com',
            'role' => 'accountant',
        ],
        [
            'name' => 'Viewer',
            'email' => 'viewer@greenleaf.com',
            'role' => 'viewer',
        ],
    ];

    public function run(): void
    {
        foreach ($this->demoUsers as $demo) {
            $user = User::updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$demo['role']]);

            $this->command->line("  ✓ {$demo['role']}: {$demo['email']}");
        }

        $this->command->info('✅ Demo users seeded successfully. Password: password');
    }
}
