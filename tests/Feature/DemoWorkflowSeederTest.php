<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_core_products_shops_staff_accounts_and_default_warehouses(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['SHOP_ASHIRWAD', 'SHOP_BUDEGERE', 'SHOP_CASIO', 'SHOP_DMART', 'SHOP_EASYDAY', 'SHOP_FOODWORLD', 'SHOP_GRANCITY', 'SHOP_LULU', 'SHOP_METRO', 'SHOP_MORE', 'SHOP_NILGIRIS', 'SHOP_RELIANCE', 'SHOP_SPAR', 'SHOP_STAR'],
            Shop::query()->orderBy('code')->pluck('code')->all()
        );

        $this->assertGreaterThan(0, Product::query()->count());

        $this->assertEqualsCanonicalizing(
            ['Fruit Warehouse', 'Vegetable Warehouse'],
            Warehouse::query()->orderBy('name')->pluck('name')->all()
        );

        $this->assertEqualsCanonicalizing(
            ['1200', '2100', '4100', '5100'],
            DB::table('accounts')
                ->whereIn('code', ['1200', '2100', '4100', '5100'])
                ->orderBy('code')
                ->pluck('code')
                ->all()
        );

        $this->assertEqualsCanonicalizing([
            'admin@greenleaf.com',
            'hr@greenleaf.com',
            'purchase@greenleaf.com',
            'purchaser@greenleaf.com',
            'purchaser2@greenleaf.com',
            'receiver@greenleaf.com',
        ], User::query()->whereNull('shop_id')->orderBy('email')->pluck('email')->all());

        $this->assertDatabaseMissing('users', ['email' => 'warehouse@greenleaf.com']);
    }
}
