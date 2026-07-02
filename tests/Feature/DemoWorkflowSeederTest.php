<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_only_creates_core_products_shops_and_staff_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['SHOP_ASHIRWAD', 'SHOP_BUDEGERE', 'SHOP_CASIO', 'SHOP_DMART', 'SHOP_EASYDAY', 'SHOP_FOODWORLD', 'SHOP_GRANCITY', 'SHOP_LULU', 'SHOP_METRO', 'SHOP_MORE', 'SHOP_NILGIRIS', 'SHOP_RELIANCE', 'SHOP_SPAR', 'SHOP_STAR'],
            Shop::query()->orderBy('code')->pluck('code')->all()
        );

        $this->assertGreaterThan(0, Product::query()->count());

        $this->assertEqualsCanonicalizing([
            'admin@greenleaf.com',
            'purchase@greenleaf.com',
            'purchaser@greenleaf.com',
            'purchaser2@greenleaf.com',
            'receiver@greenleaf.com',
        ], User::query()->whereNull('shop_id')->orderBy('email')->pluck('email')->all());

        $this->assertDatabaseMissing('users', ['email' => 'warehouse@greenleaf.com']);
    }
}
