<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\Supplier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_workflow_seed_matches_current_demo_requirements(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            ['Market A', 'Market B'],
            Supplier::query()->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(
            ['SHOP_ASHIRWAD', 'SHOP_BUDEGERE', 'SHOP_CASIO', 'SHOP_DMART', 'SHOP_EASYDAY', 'SHOP_FOODWORLD', 'SHOP_GRANCITY', 'SHOP_LULU', 'SHOP_METRO', 'SHOP_MORE', 'SHOP_NILGIRIS', 'SHOP_RELIANCE', 'SHOP_SPAR', 'SHOP_STAR'],
            Shop::query()->orderBy('code')->pluck('code')->all()
        );

        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        $twoDaysBefore = today()->subDays(2)->toDateString();

        $twoDayOrder = ShopOrder::query()->where('order_number', 'RQ-DEMO-D2-CASIO')->firstOrFail();
        $this->assertSame($twoDaysBefore, $twoDayOrder->business_date?->toDateString());
        $this->assertSame('delivered', $twoDayOrder->delivery_status);
        $this->assertTrue($twoDayOrder->is_delivered);

        $yesterdayOrder = ShopOrder::query()->where('order_number', 'RQ-DEMO-D1-BUD')->firstOrFail();
        $this->assertSame($yesterday, $yesterdayOrder->business_date?->toDateString());
        $this->assertSame('delivered', $yesterdayOrder->delivery_status);
        $this->assertTrue($yesterdayOrder->is_delivered);

        $todayOrders = ShopOrder::query()
            ->whereDate('business_date', $today)
            ->pluck('order_number')
            ->all();

        $this->assertEqualsCanonicalizing([
            'RQ-DEMO-TODAY-ASH',
            'RQ-DEMO-TODAY-GRAND',
            'RQ-WEEK-05-BUD',
            'RQ-WEEK-05-CASIO',
            'RQ-WEEK-05-GRAND',
        ], $todayOrders);

        $marketAId = Supplier::query()->where('name', 'Market A')->value('id');
        $marketBId = Supplier::query()->where('name', 'Market B')->value('id');

        $twoDayPurchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-DEMO-D2-A')->firstOrFail();
        $this->assertSame($twoDaysBefore, $twoDayPurchaseOrder->order_date?->toDateString());
        $this->assertSame($marketAId, $twoDayPurchaseOrder->supplier_id);

        $yesterdayPurchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-DEMO-D1-B')->firstOrFail();
        $this->assertSame($yesterday, $yesterdayPurchaseOrder->order_date?->toDateString());
        $this->assertSame($marketBId, $yesterdayPurchaseOrder->supplier_id);

        $this->assertTrue(PurchaseOrder::query()->where('po_number', 'PO-DEMO-STANDALONE-002')->exists());
        $this->assertTrue(PurchaseOrder::query()->where('po_number', 'PO-DEMO-STANDALONE-003')->exists());
        $this->assertGreaterThanOrEqual(3, PurchaseOrder::query()->whereDate('order_date', $today)->count());
    }
}
