<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\DailyPriceApproval;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\WastageEntry;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopInvoiceFinalizeOnBehalfZeroCostWastageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Product $product;

    private ShopPriceGroup $priceGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Ensure chart of accounts for wastage
        Account::firstOrCreate(['code' => '5200'], ['name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true]);
        Account::firstOrCreate(['code' => '5100'], ['name' => 'Cost of Goods Sold', 'type' => 'expense', 'is_active' => true]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->priceGroup = ShopPriceGroup::factory()->create(['name' => 'A']);
        $this->shop = Shop::factory()->create([
            'shop_price_group_id' => $this->priceGroup->id,
            'status' => 'approved',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Cauliflower',
            'unit' => 'kg',
            'base_price' => 10.0,
            'is_active' => true,
        ]);
    }

    public function test_finalize_on_behalf_with_shortage_wastage_and_zero_cost_does_not_throw_500(): void
    {
        $businessDate = today()->toDateString();

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => $businessDate,
            'purchase_price' => 5,
            'price_unit' => 'kg',
            'price_a' => 10.0,
            'price_b' => 10.0,
            'price_c' => 10.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 4.0,
            'approved_qty' => 4.0,
            'loaded_qty' => 4.0,
            'shop_reported_received_qty' => 2.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 4.0,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 10.0,
            'locked_price_source' => 'manual',
            'unit_price' => 10.0,
            'unit_cost' => 0.0, // Zero unit cost
            'line_total' => 40.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($order->fresh(['items.product']), (int) $this->admin->id);
        $invoice = ShopInvoice::query()->where('shop_order_id', $order->id)->firstOrFail();

        $initialJournalEntryCount = JournalEntry::query()->where('source_type', WastageEntry::class)->count();

        $payload = [
            'approved_delivered_qty' => [
                (string) $orderItem->id => 2.0,
            ],
            'item_inventory_actions' => [
                (string) $orderItem->id => 'wastage',
            ],
            'delivery_discrepancy_types' => [
                (string) $orderItem->id => 'wastage_damage',
            ],
            'delivery_discrepancy_notes' => [
                (string) $orderItem->id => 'Damaged in transit',
            ],
            'review_note' => 'Admin finalized delivery with zero-cost shortage wastage.',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('purchasing.shop-invoices.finalize-on-behalf', $invoice), $payload);

        $response->assertRedirect(route('purchasing.shop-invoices.show', $invoice->invoice_number));
        $response->assertSessionHas('success');

        // 1. Order and invoice are successfully finalized
        $order->refresh();
        $this->assertSame('approved', $order->delivery_review_status);
        $this->assertSame('partially_delivered', $order->delivery_status);

        // 2. WastageEntry was created for the physical quantity
        $wastage = WastageEntry::query()->where('product_id', $this->product->id)->latest('id')->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(2.0, (float) $wastage->quantity);
        $this->assertEquals(0.0, (float) $wastage->cost_per_kg);

        // 3. No zero-amount JournalEntry was created
        $this->assertSame(
            $initialJournalEntryCount,
            JournalEntry::query()->where('source_type', WastageEntry::class)->count(),
            'Zero-amount wastage must not create financial journal entries.'
        );
    }

    public function test_finalize_on_behalf_with_positive_cost_creates_journal_entry(): void
    {
        $businessDate = today()->toDateString();

        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'cost_per_kg' => 25.0,
            'received_at' => $businessDate,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => $businessDate,
            'purchase_price' => 5,
            'price_unit' => 'kg',
            'price_a' => 10.0,
            'price_b' => 10.0,
            'price_c' => 10.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate,
            'delivery_status' => 'in_transit',
            'delivery_review_status' => 'not_started',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'shop_reported_received_qty' => 3.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 5.0,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 10.0,
            'locked_price_source' => 'manual',
            'unit_price' => 10.0,
            'unit_cost' => 25.0, // Positive unit cost
            'line_total' => 50.0,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        app(ShopInvoiceService::class)->synchronizeOrderInvoice($order->fresh(['items.product']), (int) $this->admin->id);
        $invoice = ShopInvoice::query()->where('shop_order_id', $order->id)->firstOrFail();

        $payload = [
            'approved_delivered_qty' => [
                (string) $orderItem->id => 3.0,
            ],
            'item_inventory_actions' => [
                (string) $orderItem->id => 'wastage',
            ],
            'delivery_discrepancy_types' => [
                (string) $orderItem->id => 'wastage_damage',
            ],
            'delivery_discrepancy_notes' => [
                (string) $orderItem->id => 'Damaged in transit',
            ],
            'review_note' => 'Admin finalized delivery with positive cost wastage.',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('purchasing.shop-invoices.finalize-on-behalf', $invoice), $payload);

        $response->assertRedirect(route('purchasing.shop-invoices.show', $invoice->invoice_number));

        // WastageEntry created with cost
        $wastage = WastageEntry::query()->where('product_id', $this->product->id)->latest('id')->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(2.0, (float) $wastage->quantity);
        $this->assertEquals(25.0, (float) $wastage->cost_per_kg);

        // Journal entry created for 2.0 * 25.0 = 50.0
        $journalEntry = JournalEntry::query()
            ->where('source_type', WastageEntry::class)
            ->where('source_id', $wastage->id)
            ->first();

        $this->assertNotNull($journalEntry, 'Positive-cost wastage must create a financial journal entry.');
        $this->assertEquals(50.0, (float) $journalEntry->total_debit);
    }
}
