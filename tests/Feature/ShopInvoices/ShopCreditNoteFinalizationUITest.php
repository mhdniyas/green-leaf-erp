<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCreditNoteFinalizationUITest extends TestCase
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

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditApplicationService $applicationService;

    private ShopFinalBillService $finalBillService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');
        $this->accountsUser->givePermissionTo('cashbook.reports.view');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');
        $this->warehouseUser->givePermissionTo('cashbook.reports.view');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');
        $this->purchaserUser->givePermissionTo('cashbook.reports.view');

        $this->shopA = Shop::factory()->create([
            'code' => 'SHP-A',
            'name' => 'Kochi Central',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopB = Shop::factory()->create([
            'code' => 'SHP-B',
            'name' => 'Calicut Mart',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->productOnion = Product::factory()->create(['name' => 'Onion Grade A', 'unit' => 'kg']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Grade A', 'unit' => 'kg']);

        // Invoice A1 (Shop A) - 100 kg Onion @ 50 = 5000 (2 days ago)
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-A1', today()->subDays(2)->toDateString(), 5000.00);
        $this->itemOnionA1 = $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        // Invoice A2 (Shop A) - 80 kg Tomato @ 40 = 3200 (1 day ago)
        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-A2', today()->subDays(1)->toDateString(), 3200.00);
        $this->itemTomatoA2 = $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 80.0, 40.0);

        // Invoice B1 (Shop B) - 50 kg Onion @ 55 = 2750 (1 day ago)
        $this->invoiceB1 = $this->createFinalizedInvoice($this->shopB, 'SINV-B1', today()->subDays(1)->toDateString(), 2750.00);
        $this->itemOnionB1 = $this->createInvoiceItem($this->invoiceB1, $this->productOnion, 'A', 50.0, 55.0);

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->finalBillService = app(ShopFinalBillService::class);
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
            'subtotal' => $total,
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

        return ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_grade' => $grade,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'delivered_qty' => $qty,
            'unit_price' => $price,
            'line_subtotal' => round($qty * $price, 2),
            'final_line_total' => round($qty * $price, 2),
        ]);
    }

    private function createStandardDraft(?Shop $shop = null, float $qty = 10.0, float $rate = 50.0): ShopCreditNote
    {
        $targetShop = $shop ?? $this->shopA;

        return $this->creditNoteService->createShopLevelDraft($targetShop, [
            'notes' => 'Test draft for finalization',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => $qty,
                    'credit_rate' => $rate,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse->value,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock->value,
                ],
            ],
        ], $this->admin);
    }

    /**
     * 1. Authorized user (accounts/admin) can finalize a valid draft.
     */
    public function test_01_authorized_user_can_finalize_a_valid_draft(): void
    {
        $draft = $this->createStandardDraft();

        $response = $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft),
            ['finalization_note' => 'Approved on audit by accounts']
        );

        $response->assertRedirect(route('admin.cashbook.reports.shops.credit-notes.show', $draft->fresh()));
        $fresh = $draft->fresh();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $fresh->status);
        $this->assertEquals($this->accountsUser->id, $fresh->approved_by);

        // Verify single ledger credit transaction created
        $ledgerTransactions = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $draft->id)
            ->get();
        $this->assertCount(1, $ledgerTransactions);
        $this->assertEquals(500.00, (float) $ledgerTransactions->first()->amount);
    }

    /**
     * 2. Unauthorized finalization rejected (403 for warehouse/purchaser).
     */
    public function test_02_unauthorized_finalization_rejected(): void
    {
        $draft = $this->createStandardDraft();

        $response = $this->actingAs($this->warehouseUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft),
            ['finalization_note' => 'Unauthorized attempt']
        );

        $response->assertForbidden();
        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->fresh()->status);

        // Purchaser also rejected
        $responsePurchaser = $this->actingAs($this->purchaserUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );
        $responsePurchaser->assertForbidden();
    }

    /**
     * 3. Confirmation uses server-calculated amounts, regardless of user tampering.
     */
    public function test_03_confirmation_uses_server_calculated_amounts(): void
    {
        $draft = $this->createStandardDraft(qty: 15.0, rate: 45.0); // 15 * 45 = 675.00

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft),
            ['total_credit_amount' => 1.00] // Client tries to override amount
        );

        $response->assertRedirect();
        $fresh = $draft->fresh();
        $this->assertEquals(675.00, (float) $fresh->total_credit_amount);

        $tx = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $draft->id)
            ->first();
        $this->assertEquals(675.00, (float) $tx->amount);
    }

    /**
     * 4. Final credit note number shown correctly (SCN-...).
     */
    public function test_04_final_credit_note_number_shown_correctly(): void
    {
        $draft = $this->createStandardDraft();
        $this->assertStringStartsWith('DRAFT-SCN-', $draft->credit_note_number);

        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );

        $fresh = $draft->fresh();
        $this->assertStringStartsWith('SCN-', $fresh->credit_note_number);
        $this->assertStringNotContainsString('DRAFT-', $fresh->credit_note_number);

        // Renders on show page
        $showResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $fresh)
        );
        $showResponse->assertOk();
        $showResponse->assertSee($fresh->credit_note_number);
    }

    /**
     * 5. Double submission does not duplicate finalization.
     */
    public function test_05_double_submission_does_not_duplicate_finalization(): void
    {
        $draft = $this->createStandardDraft();

        // First finalization
        $res1 = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );
        $res1->assertRedirect();

        // Second finalization call via AJAX
        $res2 = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft->fresh())
        );
        $res2->assertStatus(422);

        // Ensure exactly 1 ledger transaction exists
        $txCount = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $draft->id)
            ->count();
        $this->assertEquals(1, $txCount);
    }

    /**
     * 6. Finalized notes cannot be edited as drafts.
     */
    public function test_06_finalized_notes_cannot_be_edited_as_drafts(): void
    {
        $draft = $this->createStandardDraft();
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );

        // Attempting to update approved credit note
        $updateResponse = $this->actingAs($this->admin)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft->fresh()),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->toDateString(),
                'expected_lock_version' => 1,
                'items' => [
                    [
                        'source_shop_invoice_id' => $this->invoiceA1->id,
                        'shop_invoice_item_id' => $this->itemOnionA1->id,
                        'credit_quantity' => 20.0,
                        'credit_rate' => 50.0,
                    ],
                ],
            ]
        );

        $updateResponse->assertStatus(422);
    }

    /**
     * 7. Multi-source details render correctly on show page.
     */
    public function test_07_multi_source_details_render_correctly_on_show_page(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Multi source draft test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse->value,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock->value,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemTomatoA2->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 40.0,
                    'reason_type' => ShopCreditNoteReason::DamageWastage->value,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage->value,
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $finalized)
        );

        $response->assertOk();
        $response->assertSee('Kochi Central');
        $response->assertSee('SINV-A1');
        $response->assertSee('SINV-A2');
        $response->assertSee('Onion Grade A');
        $response->assertSee('Tomato Grade A');
        $response->assertSee('₹700.00'); // 500 + 200
    }

    /**
     * 8. Discount totals render correctly on show page.
     */
    public function test_08_discount_totals_render_correctly_on_show_page(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Draft with discount',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 50.0,
                ],
            ],
            'discounts' => [
                [
                    'line_credit_amount' => 125.00,
                    'notes' => 'Festive Commercial Discount',
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $finalized)
        );

        $response->assertOk();
        $response->assertSee('Festive Commercial Discount');
        $response->assertSee('125.00');
        $response->assertSee('₹375.00'); // 250 + 125
    }

    /**
     * 9. Eligible same-shop invoice search works (outstanding-invoices endpoint).
     */
    public function test_09_eligible_same_shop_invoice_search_works(): void
    {
        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.outstanding-invoices', $finalized)
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'credit_note_id' => $finalized->id,
        ]);

        $data = $response->json();
        $invoiceNumbers = array_column($data['invoices'], 'invoice_number');
        $this->assertContains('SINV-A1', $invoiceNumbers);
        $this->assertContains('SINV-A2', $invoiceNumbers);
        $this->assertNotContains('SINV-B1', $invoiceNumbers); // Shop B excluded
    }

    /**
     * 10. Partial credit application works.
     */
    public function test_10_partial_credit_application_works(): void
    {
        $draft = $this->createStandardDraft(); // 500.00 credit
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 200.00,
                        'notes' => 'Partial application of credit',
                    ],
                ],
            ]
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'applied_batch_total' => 200.00,
            'applied_total' => 200.00,
            'unapplied_total' => 300.00,
        ]);

        $this->assertEquals(200.00, $finalized->fresh()->appliedCreditTotal());
        $this->assertEquals(300.00, $finalized->fresh()->unappliedCreditTotal());

        // Target invoice effective remaining balance reduced
        $finalBill = $this->finalBillService->calculate($this->invoiceA1->fresh());
        $this->assertEquals(4800.00, round($finalBill->remainingBalance, 2));
    }

    /**
     * 11. Multi-invoice allocation works in a single batch.
     */
    public function test_11_multi_invoice_allocation_works(): void
    {
        $draft = $this->createStandardDraft(); // 500.00 credit
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 200.00,
                    ],
                    [
                        'invoice_id' => $this->invoiceA2->id,
                        'amount' => 300.00,
                    ],
                ],
            ]
        );

        $response->assertOk();
        $this->assertEquals(500.00, $finalized->fresh()->appliedCreditTotal());
        $this->assertEquals(0.00, $finalized->fresh()->unappliedCreditTotal());
        $this->assertEquals(2, ShopCreditApplication::where('shop_credit_note_id', $finalized->id)->count());
    }

    /**
     * 12. Over-application rejected (exceeding invoice balance or unapplied credit).
     */
    public function test_12_over_application_rejected_exceeding_invoice_balance_or_unapplied_credit(): void
    {
        $draft = $this->createStandardDraft(); // 500.00 credit
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Case A: Exceeding unapplied credit
        $responseA = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 600.00, // exceeds 500 credit
                    ],
                ],
            ]
        );
        $responseA->assertStatus(422);

        // Case B: Exceeding invoice outstanding balance
        // Create a small invoice with balance 100
        $smallInvoice = $this->createFinalizedInvoice($this->shopA, 'SINV-SMALL', today()->toDateString(), 100.00);

        $responseB = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $smallInvoice->id,
                        'amount' => 150.00, // exceeds 100 invoice balance
                    ],
                ],
            ]
        );
        $responseB->assertStatus(422);
    }

    /**
     * 13. Cross-shop application rejected.
     */
    public function test_13_cross_shop_application_rejected(): void
    {
        $draft = $this->createStandardDraft(); // For Shop A
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceB1->id, // Belongs to Shop B!
                        'amount' => 100.00,
                    ],
                ],
            ]
        );

        $response->assertStatus(422);
    }

    /**
     * 14. Concurrent allocation handled safely via idempotency key.
     */
    public function test_14_concurrent_allocation_handled_safely(): void
    {
        $draft = $this->createStandardDraft(); // 500.00 credit
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $payload = [
            'idempotency_key' => 'idem-test-key-ui-14',
            'allocations' => [
                [
                    'invoice_id' => $this->invoiceA1->id,
                    'amount' => 150.00,
                ],
            ],
        ];

        $res1 = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            $payload
        );
        $res1->assertOk();

        // Second call with same idempotency key
        $res2 = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            $payload
        );
        $res2->assertOk();

        // Exactly one application record created
        $this->assertEquals(150.00, $finalized->fresh()->appliedCreditTotal());
    }

    /**
     * 15. Applied and unapplied balances display correctly.
     */
    public function test_15_applied_and_unapplied_balances_display_correctly(): void
    {
        $draft = $this->createStandardDraft(); // 500.00 credit
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->applicationService->applyCredit($finalized, $this->invoiceA1, 200.00, $this->admin);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $finalized)
        );

        $response->assertOk();
        $response->assertSee('200.00'); // Applied
        $response->assertSee('300.00'); // Unapplied
    }

    /**
     * 16. Application history displays correctly on show page.
     */
    public function test_16_application_history_displays_correctly(): void
    {
        $draft = $this->createStandardDraft(); // 500.00 credit
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->applicationService->applyCredit($finalized, $this->invoiceA1, 200.00, $this->admin);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $finalized)
        );

        $response->assertOk();
        $response->assertSee('SINV-A1');
        $response->assertSee('200.00');
    }

    /**
     * 17. No additional Cashbook transaction created on credit allocation.
     */
    public function test_17_no_additional_cashbook_transaction_on_allocation(): void
    {
        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $txCountBefore = ShopLedgerTransaction::count();

        $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 250.00,
                    ],
                ],
            ]
        );

        $this->assertEquals($txCountBefore, ShopLedgerTransaction::count());
    }

    /**
     * 18. Original invoice totals unchanged after credit allocation.
     */
    public function test_18_original_invoice_totals_unchanged(): void
    {
        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $invBefore = $this->invoiceA1->fresh();
        $origSubtotal = (float) $invBefore->subtotal;
        $origFinalTotal = (float) $invBefore->final_total;

        $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 150.00,
                    ],
                ],
            ]
        );

        $invAfter = $this->invoiceA1->fresh();
        $this->assertEquals($origSubtotal, (float) $invAfter->subtotal);
        $this->assertEquals($origFinalTotal, (float) $invAfter->final_total);
    }

    /**
     * 19. Zero inventory movements from finalization or allocation.
     */
    public function test_19_zero_inventory_movements_from_finalization_or_allocation(): void
    {
        $mvBefore = StockMovement::count();

        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $this->assertEquals($mvBefore, StockMovement::count());

        $this->applicationService->applyCredit($finalized, $this->invoiceA1, 200.00, $this->admin);
        $this->assertEquals($mvBefore, StockMovement::count());
    }

    /**
     * 20. Legacy Credit Note UI remains functional.
     */
    public function test_20_legacy_credit_note_ui_remains_functional(): void
    {
        // Create a legacy invoice-linked credit note
        $legacyNote = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'accounting_mode' => ShopCreditNoteAccountingMode::LegacyInvoiceLinked,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 100.00,
        ]);

        // Attempting to access show on legacy note redirects with notice
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $legacyNote)
        );
        $response->assertRedirect(route('admin.cashbook.reports.shops.credit-notes'));
        $response->assertSessionHas('error');

        // Main credit notes list loads 200 OK
        $listResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes')
        );
        $listResponse->assertOk();
    }

    /**
     * 21. Finalize modal displays effective business date in create view.
     */
    public function test_21_finalize_modal_displays_effective_business_date(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.drafts.create')
        );

        $response->assertOk();
        $response->assertSee('Effective Business Date:');
        $response->assertSee('x-text="creditNoteDate"', false);
    }

    /**
     * 22. Invoices search filters by invoice date and includes date in results.
     */
    public function test_22_invoices_search_filters_by_date(): void
    {
        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $targetDate = $this->invoiceA1->business_date->toDateString();

        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.outstanding-invoices', [
                'creditNote' => $finalized,
                'date' => $targetDate,
            ])
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $invoices = $response->json('invoices');
        $this->assertNotEmpty($invoices);
        $this->assertEquals($this->invoiceA1->invoice_number, $invoices[0]['invoice_number']);
        $this->assertEquals($targetDate, $invoices[0]['invoice_date']);
    }

    /**
     * 23. Admin can view all shop invoices including settled ones when include_all is requested.
     */
    public function test_23_admin_can_view_all_shop_invoices_including_settled(): void
    {
        $paidInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => today()->subDays(9)->toDateString(),
            'status' => 'paid',
            'final_total' => 2000.00,
            'paid_amount' => 2000.00,
            'balance_amount' => 0.00,
        ]);

        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Regular accounts user without include_all does not get settled invoice
        $userResponse = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.outstanding-invoices', [
                'creditNote' => $finalized,
            ])
        );
        $userInvoices = collect($userResponse->json('invoices'))->pluck('invoice_number')->all();
        $this->assertNotContains($paidInvoice->invoice_number, $userInvoices);

        // Admin with include_all gets all shop invoices including settled
        $adminResponse = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.outstanding-invoices', [
                'creditNote' => $finalized,
                'include_all' => '1',
            ])
        );
        $adminResponse->assertOk();
        $adminInvoices = collect($adminResponse->json('invoices'))->pluck('invoice_number')->all();
        $this->assertContains($paidInvoice->invoice_number, $adminInvoices);
    }

    /**
     * 24. Admin can apply credit to any invoice with specific applied_at date.
     */
    public function test_24_admin_can_apply_credit_to_any_invoice_with_date(): void
    {
        $paidInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => today()->subDays(10)->toDateString(),
            'status' => 'paid',
            'final_total' => 1000.00,
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
        ]);

        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $customDate = today()->subDay()->toDateString();

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $paidInvoice->id,
                        'amount' => 150.00,
                        'notes' => 'Admin override settlement against historical paid invoice',
                    ],
                ],
                'applied_at' => $customDate,
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $application = ShopCreditApplication::where('shop_credit_note_id', $finalized->id)
            ->where('shop_invoice_id', $paidInvoice->id)
            ->first();

        $this->assertNotNull($application);
        $this->assertEquals(150.00, (float) $application->amount);
        $this->assertEquals($customDate, $application->applied_at->toDateString());
    }

    /**
     * 25. Non-admin cannot apply credit to a settled invoice.
     */
    public function test_25_non_admin_cannot_apply_credit_to_settled_invoice(): void
    {
        $paidInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'business_date' => today()->subDays(11)->toDateString(),
            'status' => 'paid',
            'final_total' => 1000.00,
            'paid_amount' => 1000.00,
            'balance_amount' => 0.00,
        ]);

        $draft = $this->createStandardDraft();
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $finalized),
            [
                'allocations' => [
                    [
                        'invoice_id' => $paidInvoice->id,
                        'amount' => 150.00,
                    ],
                ],
            ]
        );

        $response->assertStatus(422);
    }
}
