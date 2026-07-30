<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SeedTestPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_seed_test_page_renders_successfully(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $response = $this->get('/seedtest');

        $response->assertStatus(200);
        $response->assertSeeText('Seed Test Shop Data');
        $response->assertSeeText('Select Shop');
    }

    public function test_seeding_test_data_works_and_redirects_back(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create([
            'code' => 'AV_GM_MIDLAND',
            'name' => 'Midland GM',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->post('/seedtest', [
            'shop_id' => $shop->id,
            'carry_over' => 67189.00,
            'days' => [
                [
                    'date' => '2026-07-01',
                    'sales' => 19607.00,
                    'rent' => 1960.70,
                    'vehicle' => 550.00,
                    'cash_purchase' => 250.00,
                    'loan_given' => 2000.00,
                    'invoice_total' => 20889.00,
                ],
                [
                    'date' => '2026-07-02',
                    'sales' => 23984.00,
                    'rent' => 2398.40,
                    'vehicle' => 550.00,
                    'cash_purchase' => 250.00,
                    'loan_given' => 0.00,
                    'invoice_total' => 18868.00,
                ],
                [
                    'date' => '2026-07-03',
                    'sales' => 19548.00,
                    'rent' => 1954.80,
                    'vehicle' => 1100.00,
                    'cash_purchase' => 150.00,
                    'loan_given' => 0.00,
                    'invoice_total' => 19941.00,
                ],
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert database records exist
        $this->assertDatabaseHas('shop_accounting_period_closures', [
            'shop_id' => $shop->id,
            'period_start' => '2026-06-01 00:00:00',
            'period_end' => '2026-06-30 00:00:00',
        ]);

        $this->assertDatabaseHas('shop_credits', [
            'shop_id' => $shop->id,
            'business_date' => '2026-06-30 00:00:00',
            'amount' => '67189.00',
        ]);

        $this->assertDatabaseHas('shop_accounting_entries', [
            'shop_id' => $shop->id,
            'business_date' => '2026-07-01 00:00:00',
            'opening_cash' => '67189.00',
            'closing_cash' => '84835.30',
        ]);
    }
}
