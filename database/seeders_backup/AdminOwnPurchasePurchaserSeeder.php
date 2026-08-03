<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminOwnPurchasePurchaserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        User::query()
            ->where('email', 'like', 'admin-own-purchase-%@greenleaf.local')
            ->get()
            ->each(function (User $legacyPurchaser): void {
                if ($legacyPurchaser->hasRole('purchaser')) {
                    $legacyPurchaser->removeRole('purchaser');
                }
            });

        User::role('admin')
            ->orderBy('id')
            ->get()
            ->each(function (User $admin): void {
                if (! $admin->hasRole('purchaser')) {
                    $admin->assignRole('purchaser');
                }

                $admin->update([
                    'own_purchase_purchaser_id' => $admin->id,
                ]);
            });

        $this->command?->info('Admin own-purchase purchaser access linked successfully.');
    }
}
