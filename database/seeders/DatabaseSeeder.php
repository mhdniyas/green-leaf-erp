<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Run order matters:
     * 1. Roles & permissions first (users depend on them)
     * 2. Demo users second (assigns roles)
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DemoUserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}
