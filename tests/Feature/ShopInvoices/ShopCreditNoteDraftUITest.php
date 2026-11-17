<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCreditNoteDraftUITest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $warehouseUser;

    private User $purchaserUser;

    private Shop $shopA;

    private Shop $shopB;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoice $invoiceA2;

    private ShopInvoice $invoiceB1;

    private ShopInvoiceItem $itemOnionA1;

    private ShopInvoiceItem $itemTomatoA2;

    private ShopInvoiceItem $itemOnionB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');

        $this->shopA = Shop::factory()->create(['code' => 'SHP-A', 'name' => 'Kochi Central', 'accounting_enabled' => true]);
        $this->shopB = Shop::factory()->create(['code' => 'SHP-B', 'name' => 'Calicut Mart', 'accounting_enabled' => true]);

        $this->productOnion = Product::factory()->create(['name' => 'Onion Grade A', 'sku' => 'ONION-A', 'unit' => 'kg']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Grade A', 'sku' => 'TOMATO-A', 'unit' => 'kg']);

        // Invoice A1 (Shop A) - 100 kg Onion @ 50 = 5000 (2 days ago)
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-A1', today()->subDays(2)->toDateString(), 5000.00);
        $this->itemOnionA1 = $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        // Invoice A2 (Shop A) - 80 kg Tomato @ 40 = 3200 (1 day ago)
        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-A2', today()->subDays(1)->toDateString(), 3200.00);
        $this->itemTomatoA2 = $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 80.0, 40.0);

        // Invoice B1 (Shop B) - 50 kg Onion @ 55 = 2750 (1 day ago)
        $this->invoiceB1 = $this->createFinalizedInvoice($this->shopB, 'SINV-B1', today()->subDays(1)->toDateString(), 2750.00);
        $this->itemOnionB1 = $this->createInvoiceItem($this->invoiceB1, $this->productOnion, 'A', 50.0, 55.0);
    }

    private function createFinalizedInvoice(Shop $shop, string $invoiceNumber, ?string $date = null, float $total = 5000.00): ShopInvoice
    {
        $businessDate = $date ?? today()->toDateString();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
        ]);

        return ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'final_total' => $total,
            'balance_amount' => $total,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);
    }

    private function createInvoiceItem(ShopInvoice $invoice, Product $product, string $grade, float $qty, float $price): ShopInvoiceItem
    {
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $invoice->shop_order_id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'requested_qty' => $qty,
            'approved_qty' => $qty,
            'loaded_qty' => $qty,
            'delivered_qty' => $qty,
            'unit' => 'kg',
            'unit_cost' => $price * 0.8,
        ]);

        return ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_grade' => $grade,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'price_quantity' => $qty,
            'delivered_qty' => $qty,
            'delivered_price_quantity' => $qty,
            'unit_price' => $price,
            'line_subtotal' => round($qty * $price, 2),
            'final_line_total' => round($qty * $price, 2),
        ]);
    }

    /**
     * Test 1: Authorized office user opens shop-first creation page.
     */
    public function test_01_authorized_office_user_opens_shop_first_creation_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.drafts.create'));
        $response->assertOk();
        $response->assertViewIs('admin.cashbook.reports.shops.create_shop_credit_note');
        $response->assertSee('Create Shop Credit Note');

        $this->accountsUser->givePermissionTo(['cashbook.access', 'cashbook.reports.view']);
        $accResponse = $this->actingAs($this->accountsUser)->get(route('admin.cashbook.reports.shops.credit-notes.drafts.create'));
        $accResponse->assertOk();
    }

    /**
     * Test 2: Unauthorized access rejected.
     */
    public function test_02_unauthorized_access_rejected(): void
    {
        // Warehouse receiver should be 403 Forbidden for JSON requests
        $response = $this->actingAs($this->warehouseUser)->getJson(route('admin.cashbook.reports.shops.credit-notes.drafts.create'));
        $response->assertForbidden();

        // Purchaser should be 403 Forbidden
        $purchaserResponse = $this->actingAs($this->purchaserUser)->getJson(route('admin.cashbook.reports.shops.credit-notes.drafts.create'));
        $purchaserResponse->assertForbidden();

        // Unauthenticated guest should redirect to login
        $guestResponse = $this->get(route('admin.cashbook.reports.shops.credit-notes.drafts.create'));
        $guestResponse->assertRedirect();
    }

    /**
     * Test 3: Shop filtering works in search.
     */
    public function test_03_shop_filtering_works_in_search(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
            ])
        );

        $response->assertOk();
        $items = $response->json('items');
        $this->assertNotEmpty($items);

        // Every item must belong to Shop A's invoices
        foreach ($items as $item) {
            $this->assertContains($item['source_shop_invoice_id'], [$this->invoiceA1->id, $this->invoiceA2->id]);
            $this->assertNotEquals($this->invoiceB1->id, $item['source_shop_invoice_id']);
        }
    }

    /**
     * Test 4: Product search works.
     */
    public function test_04_product_search_works(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'q' => 'Tomato',
            ])
        );

        $response->assertOk();
        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertEquals($this->productTomato->id, $items[0]['product_id']);
        $this->assertEquals($this->itemTomatoA2->id, $items[0]['shop_invoice_item_id']);
    }

    /**
     * Test 5: Month and date filter works.
     */
    public function test_05_month_and_date_filter_works(): void
    {
        $month = now()->format('Y-m');

        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'month' => $month,
            ])
        );

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('count'));

        // Querying an out-of-range month returns zero
        $pastMonth = now()->subYears(3)->format('Y-m');
        $emptyResponse = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'month' => $pastMonth,
            ])
        );

        $emptyResponse->assertOk();
        $this->assertCount(0, $emptyResponse->json('items'));
    }

    /**
     * Test 6: Historical invoice references display correctly.
     */
    public function test_06_historical_invoice_references_display_correctly(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'q' => 'Onion',
            ])
        );

        $response->assertOk();
        $line = collect($response->json('items'))->firstWhere('invoice_number', 'SINV-A1');
        $this->assertNotNull($line, 'Expected invoice SINV-A1 in search results');

        $this->assertEquals('SINV-A1', $line['invoice_number']);
        $this->assertEquals(today()->subDays(2)->toDateString(), $line['invoice_date']);
        $this->assertEquals('Onion Grade A', $line['product_name']);
        $this->assertEquals('A', $line['product_grade']);
        $this->assertEquals(100.0, (float) $line['original_quantity']);
        $this->assertEquals(50.0, (float) $line['original_unit_price']);
        $this->assertEquals(0.0, (float) $line['previously_credited_quantity']);
        $this->assertEquals(100.0, (float) $line['remaining_eligible_quantity']);
        $this->assertTrue($line['is_eligible']);
    }

    /**
     * Test 7: Multi-invoice selection works and creates draft.
     */
    public function test_07_multi_invoice_selection_works(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Multi-invoice draft claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 25.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 1250.00,
                    'inventory_action' => 'return_to_stock',
                    'reason_type' => 'return_to_warehouse',
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemTomatoA2->id,
                    'product_id' => $this->productTomato->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 40.0,
                    'line_credit_amount' => 800.00,
                    'inventory_action' => 'write_off_wastage',
                    'reason_type' => 'damage_wastage',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $response->assertJson(['success' => true]);

        $draftId = $response->json('credit_note_id');
        $draft = ShopCreditNote::findOrFail($draftId);

        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals(ShopCreditNoteAccountingMode::ShopLevel, $draft->accounting_mode);
        $this->assertEquals(2050.00, (float) $draft->total_credit_amount);
        $this->assertCount(2, $draft->items);
        $this->assertStringStartsWith('DRAFT-', $draft->credit_note_number);
        $this->assertStringContainsString('SHP-A', $draft->credit_note_number);
    }

    /**
     * Test 8: Cross-shop source invoice blocked.
     */
    public function test_08_cross_shop_source_invoice_blocked(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceB1->id, // Belongs to Shop B!
                    'shop_invoice_item_id' => $this->itemOnionB1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 55.0,
                    'line_credit_amount' => 550.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['shop_id']);
    }

    /**
     * Test 9: Editable credit rate works.
     */
    public function test_09_editable_credit_rate_works(): void
    {
        // Onion was originally 50.0. Office inputs 35.0 credit rate.
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 1400.00,
                    'inventory_action' => 'return_to_stock',
                    'reason_type' => 'grade_downgrade',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $draft = ShopCreditNote::findOrFail($response->json('credit_note_id'));
        $item = $draft->items->first();

        $this->assertEquals(35.00, (float) $item->credit_rate);
        $this->assertEquals(1400.00, (float) $item->line_credit_amount);
        $this->assertEquals(1400.00, (float) $draft->total_credit_amount);
    }

    /**
     * Test 10: Grade A -> B expected return entry works.
     */
    public function test_10_grade_a_to_b_expected_return_entry_works(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'expected_returned_grade' => 'B',
                    'credit_quantity' => 30.0,
                    'credit_rate' => 32.0,
                    'line_credit_amount' => 960.00,
                    'inventory_action' => 'return_to_stock',
                    'reason_type' => 'grade_downgrade',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $draft = ShopCreditNote::findOrFail($response->json('credit_note_id'));
        $item = $draft->items->first();

        $this->assertEquals('A', $item->product_grade);
        $this->assertEquals('B', $item->received_grade);
        $this->assertEquals(ShopCreditNoteInventoryAction::ReturnToStock, $item->inventory_action);
    }

    /**
     * Test 11: Damage at shop selection maps to correct backend action.
     */
    public function test_11_damage_at_shop_selection_maps_to_correct_backend_action(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 15.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 750.00,
                    'inventory_action' => 'write_off_wastage',
                    'reason_type' => 'damage_wastage',
                    'damage_reason' => 'rotten',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $draft = ShopCreditNote::findOrFail($response->json('credit_note_id'));
        $item = $draft->items->first();

        $this->assertEquals(ShopCreditNoteInventoryAction::WriteOffWastage, $item->inventory_action);
        $this->assertStringContainsString('damage_reason: rotten', (string) $item->notes);
    }

    /**
     * Test 12: Returned damaged selection does not generate duplicate financial adjustment.
     */
    public function test_12_returned_damaged_selection_does_not_generate_duplicate_financial_adjustment(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 45.0,
                    'line_credit_amount' => 900.00,
                    'inventory_action' => 'return_to_stock',
                    'reason_type' => 'damage_wastage',
                    'notes' => 'Arriving damaged at warehouse',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $draft = ShopCreditNote::findOrFail($response->json('credit_note_id'));

        // Total credit is exactly 900, not double
        $this->assertEquals(900.00, (float) $draft->total_credit_amount);
        $this->assertEquals(1, $draft->items()->count());
    }

    /**
     * Test 13: Discount-only draft saves.
     */
    public function test_13_discount_only_draft_saves(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Commercial settlement discount',
            'discounts' => [
                [
                    'line_credit_amount' => 350.00,
                    'notes' => 'Special promotion discount',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $draft = ShopCreditNote::findOrFail($response->json('credit_note_id'));

        $this->assertEquals(350.00, (float) $draft->total_credit_amount);
        $this->assertEquals(350.00, (float) $draft->discount_amount);
        $this->assertEquals(ShopCreditNoteReason::CommercialDiscount, $draft->reason_type);
        $this->assertCount(1, $draft->items);
        $this->assertEquals(ShopCreditNoteLineType::CommercialDiscount, $draft->items->first()->line_type);
    }

    /**
     * Test 14: Mixed return, damage and discount draft saves.
     */
    public function test_14_mixed_return_damage_and_discount_draft_saves(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => 'return_to_stock',
                    'reason_type' => 'return_to_warehouse',
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemTomatoA2->id,
                    'product_id' => $this->productTomato->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 40.0,
                    'line_credit_amount' => 200.00,
                    'inventory_action' => 'write_off_wastage',
                    'reason_type' => 'damage_wastage',
                ],
            ],
            'discounts' => [
                [
                    'line_credit_amount' => 150.00,
                    'notes' => 'Transport inconvenience',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );

        $response->assertCreated();
        $draft = ShopCreditNote::findOrFail($response->json('credit_note_id'));

        // 500 + 200 + 150 = 850
        $this->assertEquals(850.00, (float) $draft->total_credit_amount);
        $this->assertEquals(150.00, (float) $draft->discount_amount);
        $this->assertCount(3, $draft->items);
    }

    /**
     * Test 15: Draft edit and reopening work.
     */
    public function test_15_draft_edit_and_reopening_work(): void
    {
        // 1. Create initial draft
        $createPayload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 500.00,
                ],
            ],
        ];

        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $createPayload
        );
        $draftId = $res->json('credit_note_id');
        $draft = ShopCreditNote::findOrFail($draftId);

        // 2. Open edit page
        $editPageRes = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $draft)
        );
        $editPageRes->assertOk();
        $editPageRes->assertSee($draft->credit_note_number);

        // 3. Update draft: change quantity to 20 kg
        $updatePayload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'expected_lock_version' => 1,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 1000.00,
                ],
            ],
        ];

        $updateRes = $this->actingAs($this->admin)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            $updatePayload
        );

        $updateRes->assertOk();
        $draft->refresh();
        $this->assertEquals(1000.00, (float) $draft->total_credit_amount);
        $this->assertEquals(2, $draft->lock_version);
    }

    /**
     * Test 16: Invalid quantity and over-credit errors display correctly.
     */
    public function test_16_invalid_quantity_and_over_credit_errors_display_correctly(): void
    {
        // Negative quantity
        $negPayload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => -5.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ];

        $negRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $negPayload
        );
        $negRes->assertStatus(422);

        // Exceeding original delivered quantity (100 kg billed, requesting 250 kg)
        $overPayload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 250.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 12500.00,
                ],
            ],
        ];

        $overRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $overPayload
        );
        $overRes->assertStatus(422);
    }

    /**
     * Test 17: Concurrent edit conflict is handled.
     */
    public function test_17_concurrent_edit_conflict_is_handled(): void
    {
        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ];

        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );
        $draft = ShopCreditNote::findOrFail($res->json('credit_note_id'));

        // Modify lock version directly in database to simulate another user saving first
        $draft->increment('lock_version');

        // Attempt update passing old lock version 1
        $updatePayload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'expected_lock_version' => 1,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 15.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ];

        $conflictRes = $this->actingAs($this->admin)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            $updatePayload
        );

        $conflictRes->assertStatus(409);
        $conflictRes->assertJson(['concurrency' => true]);
    }

    /**
     * Test 18: Draft save produces zero stock movements.
     */
    public function test_18_draft_save_produces_zero_stock_movements(): void
    {
        $movementCountBefore = StockMovement::count();

        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 25.0,
                    'credit_rate' => 50.0,
                    'inventory_action' => 'return_to_stock',
                ],
            ],
        ];

        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );
        $res->assertCreated();

        $movementCountAfter = StockMovement::count();
        $this->assertEquals($movementCountBefore, $movementCountAfter);
    }

    /**
     * Test 19: Draft save produces zero Cashbook entries.
     */
    public function test_19_draft_save_produces_zero_cashbook_entries(): void
    {
        $ledgerCountBefore = ShopLedgerTransaction::count();

        $payload = [
            'shop_id' => $this->shopA->id,
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'credit_quantity' => 25.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ];

        $res = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            $payload
        );
        $res->assertCreated();

        $ledgerCountAfter = ShopLedgerTransaction::count();
        $this->assertEquals($ledgerCountBefore, $ledgerCountAfter);
    }

    /**
     * Test 20: Legacy Credit Note creation remains unaffected.
     */
    public function test_20_legacy_credit_note_creation_remains_unaffected(): void
    {
        // Legacy select invoice
        $selectRes = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_id' => $this->invoiceA1->id]
        );
        $selectRes->assertRedirect(route('admin.cashbook.reports.shops.credit-notes.create'));

        // Legacy create credit note view
        $createRes = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.create')
        );
        $createRes->assertOk();
        $createRes->assertViewIs('admin.cashbook.reports.shops.create_credit_note');
        $createRes->assertSee($this->invoiceA1->invoice_number);
    }

    /**
     * Test 21: Search includes real finalized statuses (payment_pending, paid) and excludes unfinalized or cancelled invoices.
     */
    public function test_21_search_includes_real_finalized_statuses_and_excludes_unfinalized_or_cancelled(): void
    {
        $orderPending = ShopOrder::factory()->create(['shop_id' => $this->shopA->id, 'business_date' => today()->subDays(3)->toDateString()]);
        $invoicePending = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_order_id' => $orderPending->id,
            'invoice_number' => 'SINV-PEND-1',
            'business_date' => today()->subDays(3)->toDateString(),
            'status' => 'payment_pending',
            'final_total' => 1000.00,
            'balance_amount' => 1000.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);
        $this->createInvoiceItem($invoicePending, $this->productOnion, 'A', 20.0, 50.0);

        $orderPaid = ShopOrder::factory()->create(['shop_id' => $this->shopA->id, 'business_date' => today()->subDays(4)->toDateString()]);
        $invoicePaid = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_order_id' => $orderPaid->id,
            'invoice_number' => 'SINV-PAID-1',
            'business_date' => today()->subDays(4)->toDateString(),
            'status' => 'paid',
            'payment_status' => 'paid',
            'final_total' => 800.00,
            'balance_amount' => 0.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => null,
        ]);
        $this->createInvoiceItem($invoicePaid, $this->productTomato, 'A', 20.0, 40.0);

        $orderGenerated = ShopOrder::factory()->create(['shop_id' => $this->shopA->id, 'business_date' => today()->subDays(5)->toDateString()]);
        $invoiceGenerated = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_order_id' => $orderGenerated->id,
            'invoice_number' => 'SINV-GEN-1',
            'business_date' => today()->subDays(5)->toDateString(),
            'status' => 'generated',
            'final_total' => 500.00,
            'balance_amount' => 500.00,
            'finalized_at' => null,
        ]);
        $this->createInvoiceItem($invoiceGenerated, $this->productOnion, 'A', 10.0, 50.0);

        $orderCancelled = ShopOrder::factory()->create(['shop_id' => $this->shopA->id, 'business_date' => today()->subDays(6)->toDateString()]);
        $invoiceCancelled = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_order_id' => $orderCancelled->id,
            'invoice_number' => 'SINV-CANC-1',
            'business_date' => today()->subDays(6)->toDateString(),
            'status' => 'cancelled',
            'final_total' => 500.00,
            'balance_amount' => 500.00,
            'finalized_at' => now(),
        ]);
        $this->createInvoiceItem($invoiceCancelled, $this->productOnion, 'A', 10.0, 50.0);

        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
            ])
        );

        $response->assertOk();
        $invoiceNumbers = collect($response->json('items'))->pluck('invoice_number')->all();

        $this->assertContains('SINV-PEND-1', $invoiceNumbers);
        $this->assertContains('SINV-PAID-1', $invoiceNumbers);
        $this->assertContains('SINV-A1', $invoiceNumbers);
        $this->assertNotContains('SINV-GEN-1', $invoiceNumbers);
        $this->assertNotContains('SINV-CANC-1', $invoiceNumbers);
    }

    /**
     * Test 22: Prior-month finalized invoices return when month filter is empty and match invoice number search.
     */
    public function test_22_search_returns_prior_month_invoices_when_month_is_empty_and_matches_invoice_number(): void
    {
        $priorDate = now()->subMonths(2)->startOfMonth()->addDays(5)->toDateString();
        $priorOrder = ShopOrder::factory()->create(['shop_id' => $this->shopA->id, 'business_date' => $priorDate]);
        $priorInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_order_id' => $priorOrder->id,
            'invoice_number' => 'SINV-HIST-100',
            'business_date' => $priorDate,
            'status' => 'payment_pending',
            'final_total' => 1500.00,
            'balance_amount' => 1500.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now()->subMonths(2),
        ]);
        $this->createInvoiceItem($priorInvoice, $this->productTomato, 'A', 30.0, 50.0);

        // No month param => historical prior-month invoice appears
        $allMonthsRes = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
            ])
        );
        $allMonthsRes->assertOk();
        $this->assertContains('SINV-HIST-100', collect($allMonthsRes->json('items'))->pluck('invoice_number')->all());

        // Search by invoice number across months
        $byInvoiceRes = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'q' => 'SINV-HIST-100',
            ])
        );
        $byInvoiceRes->assertOk();
        $this->assertCount(1, $byInvoiceRes->json('items'));
        $this->assertEquals('SINV-HIST-100', $byInvoiceRes->json('items.0.invoice_number'));

        // Current month filter excludes the prior-month invoice
        $currentMonthRes = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'month' => now()->format('Y-m'),
            ])
        );
        $currentMonthRes->assertOk();
        $this->assertNotContains('SINV-HIST-100', collect($currentMonthRes->json('items'))->pluck('invoice_number')->all());
    }

    /**
     * Test 23: Creation view defaults searchMonth to empty (all months) and provides filter reset control.
     */
    public function test_23_create_page_defaults_search_month_to_empty_and_renders_clear_filters(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.drafts.create')
        );

        $response->assertOk();
        $response->assertSee("searchMonth: '',", false);
        $response->assertSee('clearSearchFilters()', false);
    }

    /**
     * Test 24: Fully credited items are flagged ineligible unless excluded for the editing draft.
     */
    public function test_24_search_marks_fully_credited_items_ineligible(): void
    {
        $draftRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->toDateString(),
                'items' => [
                    [
                        'source_shop_invoice_id' => $this->invoiceA1->id,
                        'shop_invoice_item_id' => $this->itemOnionA1->id,
                        'product_id' => $this->productOnion->id,
                        'credit_quantity' => 100.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ]
        );
        $draftRes->assertCreated();
        $creditNoteId = (int) $draftRes->json('credit_note_id');

        $draft = ShopCreditNote::findOrFail($creditNoteId);

        $finalizeRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );
        $finalizeRes->assertOk();

        $searchRes = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines', [
                'shop_id' => $this->shopA->id,
                'q' => 'SINV-A1',
            ])
        );
        $searchRes->assertOk();
        $line = collect($searchRes->json('items'))->firstWhere('shop_invoice_item_id', $this->itemOnionA1->id);
        $this->assertNotNull($line);
        $this->assertFalse($line['is_eligible']);
        $this->assertEquals(0.0, (float) $line['remaining_eligible_quantity']);
    }

    /**
     * Test 25: Store draft accepts catalog product without invoice reference.
     */
    public function test_25_store_draft_accepts_catalog_product_without_invoice_reference(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->toDateString(),
                'notes' => 'Product-only draft via HTTP',
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 15.0,
                        'credit_rate' => 45.0,
                        'direction' => 'credit',
                        'inventory_action' => 'return_to_stock',
                    ],
                ],
            ]
        );

        $response->assertCreated();
        $response->assertJson(['success' => true]);

        $creditNoteId = (int) $response->json('credit_note_id');
        $draft = ShopCreditNote::with('items')->findOrFail($creditNoteId);

        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals(ShopCreditNoteAccountingMode::ShopLevel, $draft->accounting_mode);
        $this->assertEquals(675.00, (float) $draft->total_credit_amount);
        $this->assertCount(1, $draft->items);

        $item = $draft->items->first();
        $this->assertNull($item->source_shop_invoice_id);
        $this->assertNull($item->shop_invoice_item_id);
        $this->assertEquals($this->productOnion->id, $item->product_id);
        $this->assertEquals('A', $item->product_grade);
        $this->assertEquals(15.0, (float) $item->adjusted_quantity);
        $this->assertEquals(45.0, (float) $item->credit_rate);
    }

    /**
     * Test 26: Update draft with catalog product lines.
     */
    public function test_26_update_draft_with_catalog_product_lines(): void
    {
        $creditNoteService = app(ShopCreditNoteService::class);
        $draft = $creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            [
                'expected_lock_version' => 1,
                'notes' => 'Updated catalog product lines',
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 25.0,
                        'credit_rate' => 48.0,
                        'direction' => 'credit',
                        'inventory_action' => 'return_to_stock',
                    ],
                ],
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $draft->refresh();
        $this->assertEquals(1200.00, (float) $draft->total_credit_amount);
        $this->assertEquals(2, $draft->lock_version);
        $this->assertCount(1, $draft->items);
        $this->assertEquals(25.0, (float) $draft->items->first()->adjusted_quantity);
    }

    /**
     * Test 27: Store draft validates required fields for product-only lines.
     */
    public function test_27_store_draft_validates_required_fields_for_product_only_lines(): void
    {
        // 1. Missing product_id when no invoice item
        $res1 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'items' => [
                    [
                        'credit_quantity' => 10.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ]
        );
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['items.0.product_id']);

        // 2. Invalid product grade
        $res2 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'INVALID_GRADE',
                        'credit_quantity' => 10.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ]
        );
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['items.0.product_grade']);

        // 3. Non-positive rate
        $res3 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 10.0,
                        'credit_rate' => 0.0,
                    ],
                ],
            ]
        );
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['items.0.credit_rate']);
    }

    /**
     * Test 28: End-to-end product-first draft creation and finalization via HTTP.
     */
    public function test_28_end_to_end_product_first_draft_and_finalization_via_http(): void
    {
        // 1. Search products via catalog search endpoint
        $searchRes = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
                'q' => 'Onion',
            ])
        );
        $searchRes->assertOk();
        $this->assertGreaterThanOrEqual(1, count($searchRes->json('items')));
        $this->assertEquals($this->productOnion->id, $searchRes->json('items.0.id'));

        // 2. Create product-first draft with 2 catalog products (no invoice referenced)
        $storeRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->toDateString(),
                'notes' => 'Product-first claim via UI',
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 15.0,
                        'credit_rate' => 50.0,
                        'direction' => 'credit',
                        'inventory_action' => 'return_to_stock',
                    ],
                    [
                        'product_id' => $this->productTomato->id,
                        'product_grade' => 'B',
                        'credit_quantity' => 10.0,
                        'credit_rate' => 40.0,
                        'direction' => 'credit',
                        'inventory_action' => 'write_off_wastage',
                    ],
                ],
            ]
        );
        $storeRes->assertCreated();
        $draftId = (int) $storeRes->json('credit_note_id');
        $draft = ShopCreditNote::findOrFail($draftId);

        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals(1150.00, (float) $draft->total_credit_amount);
        $this->assertCount(2, $draft->items);

        // 3. Finalize the draft
        $finalizeRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft),
            ['notes' => 'Finalized via UI']
        );
        $finalizeRes->assertOk();
        $finalizeRes->assertJson(['success' => true]);

        $draft->refresh();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $draft->status);
        $this->assertStringStartsWith('SCN-', $draft->credit_note_number);
        $this->assertEquals(1150.00, (float) $draft->total_credit_amount);

        // Assert pending receipt for physical return
        $returnItem = $draft->items->firstWhere('product_id', $this->productOnion->id);
        $this->assertEquals(ShopCreditNoteReceiptStatus::PendingReceipt, $returnItem->receipt_status);

        // Assert wastage recorded for damage item
        $damageItem = $draft->items->firstWhere('product_id', $this->productTomato->id);
        $this->assertEquals(ShopCreditNoteReceiptStatus::NotApplicable, $damageItem->receipt_status);
        $this->assertNotNull($damageItem->wastage_entry_id);
    }

    /**
     * Test 29: Product-first draft with optional invoice link via HTTP.
     */
    public function test_29_product_first_draft_with_optional_invoice_link_via_http(): void
    {
        $originalInvoiceTotal = (float) $this->invoiceA1->final_total;

        $storeRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->toDateString(),
                'items' => [
                    [
                        'source_shop_invoice_id' => $this->invoiceA1->id,
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 10.0,
                        'credit_rate' => 45.0,
                        'direction' => 'credit',
                        'inventory_action' => 'return_to_stock',
                    ],
                ],
            ]
        );
        $storeRes->assertCreated();
        $draftId = (int) $storeRes->json('credit_note_id');
        $draft = ShopCreditNote::findOrFail($draftId);

        $this->assertCount(1, $draft->items);
        $item = $draft->items->first();
        $this->assertEquals($this->invoiceA1->id, $item->source_shop_invoice_id);
        $this->assertNull($item->shop_invoice_item_id);

        // Finalize draft
        $finalizeRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );
        $finalizeRes->assertOk();

        // Historical invoice total remains unchanged
        $this->invoiceA1->refresh();
        $this->assertEquals($originalInvoiceTotal, (float) $this->invoiceA1->final_total);
    }

    /**
     * Test 30: Draft routes resolve by both numeric ID and credit_note_number string.
     */
    public function test_30_draft_routes_resolve_by_numeric_id_and_credit_note_number(): void
    {
        $creditNoteService = app(ShopCreditNoteService::class);
        $draft = $creditNoteService->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // 1. Resolve edit view using numeric database ID
        $editByIdRes = $this->actingAs($this->admin)->get(
            "/admin/cashbook/reports/shops/credit-notes/drafts/{$draft->id}/edit"
        );
        $editByIdRes->assertOk();
        $editByIdRes->assertSee($draft->credit_note_number);

        // 2. Resolve edit view using string credit_note_number
        $editByNumRes = $this->actingAs($this->admin)->get(
            "/admin/cashbook/reports/shops/credit-notes/drafts/{$draft->credit_note_number}/edit"
        );
        $editByNumRes->assertOk();
        $editByNumRes->assertSee($draft->credit_note_number);

        // 3. Resolve finalize endpoint using numeric database ID (the exact scenario from the alert)
        $finalizeByIdRes = $this->actingAs($this->admin)->postJson(
            "/admin/cashbook/reports/shops/credit-notes/drafts/{$draft->id}/finalize",
            ['notes' => 'Finalized via numeric ID']
        );
        $finalizeByIdRes->assertOk();
        $finalizeByIdRes->assertJson(['success' => true]);

        $draft->refresh();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $draft->status);
    }

    /**
     * Test 31: Store and update responses return dynamic finalize_url.
     */
    public function test_31_store_and_update_responses_return_dynamic_finalize_url(): void
    {
        $storeRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 5.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ]
        );

        $storeRes->assertCreated();
        $this->assertNotEmpty($storeRes->json('finalize_url'));
        $this->assertStringContainsString('/finalize', $storeRes->json('finalize_url'));

        $draftId = (int) $storeRes->json('credit_note_id');
        $draft = ShopCreditNote::findOrFail($draftId);

        $updateRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            [
                'expected_lock_version' => 1,
                'items' => [
                    [
                        'product_id' => $this->productOnion->id,
                        'product_grade' => 'A',
                        'credit_quantity' => 8.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ]
        );

        $updateRes->assertOk();
        $this->assertNotEmpty($updateRes->json('finalize_url'));
        $this->assertStringContainsString('/finalize', $updateRes->json('finalize_url'));
    }
}
