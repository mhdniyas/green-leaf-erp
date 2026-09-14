<?php

namespace Tests\Feature;

use App\Models\ShopInvoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaserSalesSummaryOpenInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_invoice_is_included_in_sales_summary_api(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('purchaser');

        ShopInvoice::factory()->create([
            'business_date' => '2026-09-01',
            'status' => 'open',
            'final_total' => 3141.60,
            'paid_amount' => 0,
            'balance_amount' => 3141.60,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/purchaser/reports/sales-summary?range=custom&date_from=2026-09-01&date_to=2026-09-01');

        $response->assertOk()
            ->assertJsonPath('data.totals.total_sales', '3141.60')
            ->assertJsonPath('data.totals.total_invoices', 1);
    }
}
