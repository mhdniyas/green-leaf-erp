<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellingPriceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_selling_price_migrations_up_and_down_lifecycle(): void
    {
        // 1. Verify all 4 tables/columns exist in migrated state
        $this->assertTrue(Schema::hasTable('selling_price_categories'));
        $this->assertTrue(Schema::hasColumn('products', 'selling_price_category_id'));
        $this->assertTrue(Schema::hasTable('product_selling_prices'));
        $this->assertTrue(Schema::hasTable('shop_product_selling_prices'));

        // 2. Rollback the 4 selling price migrations (3 batches: 90, 89, 88)
        Artisan::call('migrate:rollback', ['--step' => 4]);

        // Verify tables and added columns are cleanly dropped
        $this->assertFalse(Schema::hasTable('shop_product_selling_prices'));
        $this->assertFalse(Schema::hasTable('product_selling_prices'));
        $this->assertFalse(Schema::hasColumn('products', 'selling_price_category_id'));
        $this->assertFalse(Schema::hasTable('selling_price_categories'));

        // 3. Re-migrate forward
        Artisan::call('migrate');

        // Verify tables and added columns are cleanly restored
        $this->assertTrue(Schema::hasTable('selling_price_categories'));
        $this->assertTrue(Schema::hasColumn('products', 'selling_price_category_id'));
        $this->assertTrue(Schema::hasTable('product_selling_prices'));
        $this->assertTrue(Schema::hasTable('shop_product_selling_prices'));
    }
}
