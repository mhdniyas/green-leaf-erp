<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\Supplier;
use Database\Seeders\PurchaserDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_core_purchasing_reference_data_without_orders(): void
    {
        $this->seed(PurchaserDemoSeeder::class);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market A',
            'location' => 'Koyambedu Wholesale Market',
            'mobile_number' => '9876543210',
            'preferred_payment_method' => 'Cash',
            'credit_approved' => false,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market B',
            'credit_approved' => true,
            'credit_terms' => 'Net 1 day',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market C',
            'mobile_number' => '9876543212',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market D',
            'credit_approved' => true,
            'preferred_payment_method' => 'Online',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market E',
            'preferred_payment_method' => 'GPay',
        ]);

        $this->assertSame(0, ShopOrder::query()->count());
        $this->assertSame(0, PurchaseOrder::query()->count());
        $this->assertSame(0, PurchaseInvoice::query()->count());
        $this->assertSame(0, ShopInvoice::query()->count());
        $this->assertSame(0, PurchaserCart::query()->count());

        $this->assertGreaterThanOrEqual(5, Supplier::query()->count());
    }
}
