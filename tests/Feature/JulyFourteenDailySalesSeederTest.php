<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use Database\Seeders\JulyFourteenDailySalesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JulyFourteenDailySalesSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_seeds_july_fourteen_daily_sales_invoices_for_shop_owner_checking(): void
    {
        $this->seed(JulyFourteenDailySalesSeeder::class);

        $orders = ShopOrder::query()
            ->whereDate('business_date', '2026-07-14')
            ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
            ->withCount('items')
            ->get();

        $invoices = ShopInvoice::query()
            ->whereDate('business_date', '2026-07-14')
            ->where('invoice_number', 'like', 'SINV-20260714-SHOP_JUL14_%')
            ->orderBy('invoice_number')
            ->get();

        $this->assertCount(4, $orders);
        $this->assertSame([5], $orders->pluck('items_count')->unique()->values()->all());
        $this->assertCount(4, $invoices);
        $this->assertSame(['unpaid', 'unpaid', 'paid', 'partially_paid'], $invoices->pluck('payment_status')->all());
        $this->assertGreaterThan(0, (float) $invoices[2]->paid_amount);
        $this->assertGreaterThan(0, (float) $invoices[3]->paid_amount);

        $this->assertSame(2, JournalEntry::query()
            ->where('source_type', ShopInvoice::class)
            ->where('source_event', 'like', 'payment:paid-%')
            ->count());
    }

    public function test_it_can_be_rerun_without_duplicating_daily_sales_data(): void
    {
        $this->seed(JulyFourteenDailySalesSeeder::class);
        $this->seed(JulyFourteenDailySalesSeeder::class);

        $this->assertSame(4, ShopInvoice::query()
            ->whereDate('business_date', '2026-07-14')
            ->where('invoice_number', 'like', 'SINV-20260714-SHOP_JUL14_%')
            ->count());

        $this->assertSame(2, JournalEntry::query()
            ->where('source_type', ShopInvoice::class)
            ->where('source_event', 'like', 'payment:paid-%')
            ->count());
    }
}
