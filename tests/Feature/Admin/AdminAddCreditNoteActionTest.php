<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\DamageLossReportService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminAddCreditNoteActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Shop $shop;

    private Warehouse $warehouse;

    private Product $tomato;

    private StockBatch $batch;

    private ShopOrder $order;

    private ShopOrderItem $orderItem;

    private ShopInvoice $invoice;

    private ShopInvoiceItem $invoiceItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        Carbon::setTestNow('2026-08-25 12:00:00');

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create();
        $this->staff->givePermissionTo('cashbook.reports.view');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Sorting Warehouse',
            'code' => 'WH_MAIN',
            'is_active' => true,
        ]);

        $this->shop = Shop::query()->create([
            'name' => 'Downtown Fresh Mart',
            'code' => 'DFM_01',
            'warehouse_tag' => 'DFM',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Premium',
            'sku' => 'TOM-PREM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BAT-TOM-001',
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'warehouse_receive_pending' => false,
            'total_kg' => 500.0,
            'cost_per_kg' => 50.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->batch->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 500.0,
            'balance_after' => 500.0,
            'unit_price' => 50.0,
            'cost_per_unit' => 50.0,
            'created_by' => $this->admin->id,
        ]);

        $this->order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-9001',
            'delivery_status' => 'delivered',
            'business_date' => '2026-08-25',
        ]);

        $this->orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 100.0,
            'approved_qty' => 100.0,
            'product_grade' => 'A',
            'sorting_status' => 'allocated',
            'unit_price' => 100.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        StockMovement::create([
            'batch_id' => $this->batch->id,
            'shop_order_item_id' => $this->orderItem->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 100.0,
            'balance_after' => 400.0,
            'unit_price' => 100.0,
            'cost_per_unit' => 50.0,
            'created_by' => $this->admin->id,
            'notes' => "Warehouse loadout completion — Order: {$this->order->order_number}",
        ]);

        $this->invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $this->order->id,
            'invoice_number' => 'INV-9001',
            'business_date' => '2026-08-25',
            'subtotal' => 10000.0,
            'final_total' => 10000.0,
            'paid_amount' => 0.0,
            'balance_amount' => 10000.0,
            'status' => 'finalized',
            'payment_status' => 'pending',
            'delivery_status' => 'delivered',
            'finalized_at' => '2026-08-25 09:00:00',
            'finalized_by' => $this->admin->id,
        ]);

        $this->invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $this->orderItem->id,
            'product_id' => $this->tomato->id,
            'product_name' => $this->tomato->name,
            'product_grade' => 'A',
            'unit_price' => 100.0,
            'delivered_qty' => 100.0,
            'approved_qty' => 100.0,
            'line_subtotal' => 10000.0,
            'final_line_total' => 10000.0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_01_add_credit_note_uses_csrf_post_selection_without_exposing_sequential_ids_in_html(): void
    {
        // Cancelled invoice on previous date (avoids composite unique constraint)
        ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'invoice_number' => 'INV-CANCELLED',
            'business_date' => '2026-08-24',
            'final_total' => 5000.0,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices'));
        $response->assertOk();

        // 1. Existing Invoice button preserved
        $response->assertSee(route('purchasing.shop-invoices.show', $this->invoice));

        // 2. Add Credit Note uses a CSRF-protected POST selection form
        $selectUrl = route('admin.cashbook.reports.shops.credit-notes.select');
        $response->assertSee($selectUrl);
        $response->assertSee('Credit Note');

        // 3. Sequential invoice ID is not exposed in the URL or in plain hidden fields
        $response->assertDontSee("credit-notes/create?invoice_id={$this->invoice->id}");
        $response->assertDontSee('name="invoice_id" value="'.$this->invoice->id.'"', false);

        // 4. Cancelled invoice shows disabled button with restriction title
        $response->assertSee('Cancelled invoices cannot receive credit notes');

        // 5. Non-admin user does not see the Add Credit Note action
        $staffResponse = $this->actingAs($this->staff)->get(route('admin.cashbook.reports.shops.invoices'));
        $staffResponse->assertOk();
        $staffResponse->assertDontSee($selectUrl);
    }

    public function test_02_post_select_stores_session_and_redirects_to_clean_url(): void
    {
        $encryptedRef = Crypt::encryptString((string) $this->invoice->id);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => $encryptedRef]
        );

        // Asserts redirect to clean creation URL without query params
        $cleanUrl = route('admin.cashbook.reports.shops.credit-notes.create');
        $response->assertRedirect($cleanUrl);
        $this->assertEquals($cleanUrl, $response->headers->get('Location'));

        // Follow redirect to clean URL
        $createResponse = $this->actingAs($this->admin)->get($cleanUrl);
        $createResponse->assertOk();
        $createResponse->assertSee('Add Credit Note');
        $createResponse->assertSee($this->invoice->invoice_number);
        $createResponse->assertSee($this->shop->name);
        $createResponse->assertSee('10,000.00'); // Original billed
        $createResponse->assertSee($this->tomato->name);
        $createResponse->assertSee('"product_grade":"A"', false);

        // Hidden input uses opaque selection_token, not sequential invoice_id
        $createResponse->assertSee('name="selection_token"', false);
        $createResponse->assertDontSee('name="invoice_id" value="'.$this->invoice->id.'"', false);
    }

    public function test_03_legacy_direct_url_with_invoice_id_redirects_to_clean_url(): void
    {
        // When accessed with query param ?invoice_id=..., it immediately redirects to the clean URL
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create', [
            'invoice_id' => $this->invoice->id,
        ]));

        $cleanUrl = route('admin.cashbook.reports.shops.credit-notes.create');
        $response->assertRedirect($cleanUrl);
        $this->assertEquals($cleanUrl, $response->headers->get('Location'));

        // Following the redirect yields the preselected invoice cleanly
        $followResponse = $this->actingAs($this->admin)->get($cleanUrl);
        $followResponse->assertOk();
        $followResponse->assertSee($this->invoice->invoice_number);
    }

    public function test_04_multiple_browser_tabs_independent_selections_do_not_collide(): void
    {
        // Create second shop and second invoice to simulate Tab 2
        $shop2 = Shop::query()->create([
            'name' => 'Metro Supermarket',
            'code' => 'METRO_02',
            'warehouse_tag' => 'METRO',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $invoice2 = ShopInvoice::factory()->create([
            'shop_id' => $shop2->id,
            'invoice_number' => 'INV-9002',
            'business_date' => '2026-08-25',
            'subtotal' => 5000.0,
            'final_total' => 5000.0,
            'paid_amount' => 0.0,
            'balance_amount' => 5000.0,
            'status' => 'finalized',
            'payment_status' => 'pending',
            'delivery_status' => 'delivered',
            'finalized_at' => '2026-08-25 09:00:00',
            'finalized_by' => $this->admin->id,
        ]);

        $invoiceItem2 = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice2->id,
            'shop_order_item_id' => $this->orderItem->id,
            'product_id' => $this->tomato->id,
            'product_name' => $this->tomato->name,
            'product_grade' => 'A',
            'unit_price' => 50.0,
            'delivered_qty' => 100.0,
            'approved_qty' => 100.0,
            'line_subtotal' => 5000.0,
            'final_line_total' => 5000.0,
        ]);

        // Tab 1 selects Invoice 1
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $this->invoice->id)]
        );
        $tab1Response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $tab1Token = $tab1Response->viewData('selectionToken');
        $this->assertNotEmpty($tab1Token);

        // Tab 2 selects Invoice 2 (in the same session)
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $invoice2->id)]
        );
        $tab2Response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $tab2Token = $tab2Response->viewData('selectionToken');
        $this->assertNotEmpty($tab2Token);
        $this->assertNotEquals($tab1Token, $tab2Token);

        // Multi-Tab Refresh Verification:
        // When Tab 1 refreshes after Invoice 2 was selected in Tab 2, Tab 1 retains Invoice 1 and does NOT show Invoice 2!
        $tab1Refresh = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create', [
            'selection_token' => $tab1Token,
        ]));
        $tab1Refresh->assertOk();
        $tab1Refresh->assertSee($this->invoice->invoice_number);
        $tab1Refresh->assertDontSee($invoice2->invoice_number);
        $this->assertEquals($this->invoice->id, $tab1Refresh->viewData('invoice')->id);
        $this->assertEquals($tab1Token, $tab1Refresh->viewData('selectionToken'));

        // When Tab 2 refreshes, Tab 2 retains Invoice 2 and does NOT show Invoice 1!
        $tab2Refresh = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create', [
            'selection_token' => $tab2Token,
        ]));
        $tab2Refresh->assertOk();
        $tab2Refresh->assertSee($invoice2->invoice_number);
        $tab2Refresh->assertDontSee($this->invoice->invoice_number);
        $this->assertEquals($invoice2->id, $tab2Refresh->viewData('invoice')->id);
        $this->assertEquals($tab2Token, $tab2Refresh->viewData('selectionToken'));

        // When a bare GET request is made without a token while multiple selections exist,
        // the server does NOT default to Tab 2's invoice! It renders the safe reselect view.
        $multiTabBareGet = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $multiTabBareGet->assertOk();
        $multiTabBareGet->assertSee('Invoice Selection Required');
        $multiTabBareGet->assertDontSee($this->invoice->invoice_number);
        $multiTabBareGet->assertDontSee($invoice2->invoice_number);

        // Tab 1 submits credit note for Invoice 1
        $submitTab1 = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'selection_token' => $tab1Token,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'price_correction',
                'action' => 'draft',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 90.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );
        $submitTab1->assertSessionHasNoErrors();
        $submitTab1->assertRedirect();

        // Verify Tab 1 note was linked to Invoice 1
        $note1 = ShopCreditNote::where('shop_invoice_id', $this->invoice->id)->first();
        $this->assertNotNull($note1);
        $this->assertEquals($this->invoice->id, $note1->shop_invoice_id);

        // Tab 2 submits credit note for Invoice 2
        $submitTab2 = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'selection_token' => $tab2Token,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'price_correction',
                'action' => 'draft',
                'items' => [
                    [
                        'shop_invoice_item_id' => $invoiceItem2->id,
                        'revised_quantity' => 80.0,
                        'revised_unit_price' => 50.0,
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );
        $submitTab2->assertSessionHasNoErrors();
        $submitTab2->assertRedirect();

        // Verify Tab 2 note was linked to Invoice 2
        $note2 = ShopCreditNote::where('shop_invoice_id', $invoice2->id)->first();
        $this->assertNotNull($note2);
        $this->assertEquals($invoice2->id, $note2->shop_invoice_id);
    }

    public function test_05_page_refresh_preserves_invoice_preselection(): void
    {
        // Initial selection
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $this->invoice->id)]
        );

        $cleanUrl = route('admin.cashbook.reports.shops.credit-notes.create');

        // First load
        $response1 = $this->actingAs($this->admin)->get($cleanUrl);
        $response1->assertOk();
        $response1->assertSee($this->invoice->invoice_number);

        // Page refresh (GET clean URL without query parameters)
        $response2 = $this->actingAs($this->admin)->get($cleanUrl);
        $response2->assertOk();
        $response2->assertSee($this->invoice->invoice_number);
        $response2->assertSee($this->shop->name);
    }

    public function test_06_validation_error_recovery_preserves_clean_url_and_invoice_selection(): void
    {
        // Select invoice
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $this->invoice->id)]
        );

        $createResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $token = $createResponse->viewData('selectionToken');

        // Submit with invalid input (no revisions made)
        $submitResponse = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'selection_token' => $token,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'price_correction',
                'action' => 'draft',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 100.0, // unchanged
                        'revised_unit_price' => 100.0, // unchanged
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );

        $cleanUrl = route('admin.cashbook.reports.shops.credit-notes.create');
        $submitResponse->assertRedirect($cleanUrl);
        $submitResponse->assertSessionHasErrors(['items']);

        // When following the redirect, the error banner and invoice preselection are preserved
        $followResponse = $this->followingRedirects()
            ->actingAs($this->admin)
            ->post(
                route('admin.cashbook.reports.shops.credit-notes.store'),
                [
                    'selection_token' => $token,
                    'credit_note_date' => '2026-08-25',
                    'reason_type' => 'price_correction',
                    'action' => 'draft',
                    'items' => [
                        [
                            'shop_invoice_item_id' => $this->invoiceItem->id,
                            'revised_quantity' => 100.0,
                            'revised_unit_price' => 100.0,
                            'inventory_action' => 'none',
                        ],
                    ],
                ]
            );

        $followResponse->assertOk();
        $followResponse->assertSee($this->invoice->invoice_number);
        $followResponse->assertSee('Please correct the following errors before saving:');
    }

    public function test_07_unauthorized_and_expired_selections_handled_safely(): void
    {
        // 1. Non-admin attempting to access clean create URL is blocked
        $staffResponse = $this->actingAs($this->staff)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $staffResponse->assertRedirect();

        // 2. Admin visiting clean create URL without an active selection sees the safe reselect view (HTTP 200)
        $emptySessionResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $emptySessionResponse->assertOk();
        $emptySessionResponse->assertSee('Invoice Selection Required');
        $emptySessionResponse->assertSee('Please select an invoice from the Invoices Report to create a credit note.');
        $emptySessionResponse->assertSee(route('admin.cashbook.reports.shops.invoices'));

        // 3. Visiting with an explicit invalid token displays safe reselect view
        $invalidTokenResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create', [
            'selection_token' => 'invalid-random-token-xyz',
        ]));
        $invalidTokenResponse->assertOk();
        $invalidTokenResponse->assertSee('The invoice selection has expired or is invalid.');

        // 4. Submitting a credit note with an invalid or expired selection token is safely rejected
        $invalidSubmit = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'selection_token' => 'invalid-random-token-xyz',
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'price_correction',
                'action' => 'draft',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 90.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );
        $invalidSubmit->assertSessionHasErrors(['invoice_id']);

        // 5. Selecting a cancelled invoice is rejected
        $cancelledInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'invoice_number' => 'INV-CANCELLED-2',
            'business_date' => '2026-08-23',
            'final_total' => 2000.0,
            'status' => 'cancelled',
        ]);

        $cancelSelectResponse = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $cancelledInvoice->id)]
        );
        $cancelSelectResponse->assertRedirect(route('admin.cashbook.reports.shops.invoices'));
        $cancelSelectResponse->assertSessionHas('error');
    }

    public function test_08_save_draft_with_selection_token_creates_draft_credit_note(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $this->invoice->id)]
        );

        $createResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $token = $createResponse->viewData('selectionToken');

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'selection_token' => $token,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'shortage',
                'notes' => 'Shortage of 10 kg upon delivery',
                'action' => 'draft',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 90.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.cashbook.reports.shops.credit-notes', ['search' => 'SCN-20260825-DFM_01-01']));

        $note = ShopCreditNote::where('shop_invoice_id', $this->invoice->id)->first();
        $this->assertNotNull($note);
        $this->assertEquals(ShopCreditNoteStatus::Draft, $note->status);
        $this->assertEquals(1000.00, (float) $note->total_credit_amount);

        // Raw invoice remains unchanged
        $this->assertEquals(10000.00, (float) $this->invoice->fresh()->final_total);
    }

    public function test_09_save_and_finalize_with_selection_token_and_stock_return(): void
    {
        $initialMovements = StockMovement::count();

        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.select'),
            ['invoice_ref' => Crypt::encryptString((string) $this->invoice->id)]
        );

        $createResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes.create'));
        $token = $createResponse->viewData('selectionToken');

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'selection_token' => $token,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'return_to_warehouse',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 85.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'return_to_stock',
                        'notes' => 'Physically returned 15 kg crate to central warehouse',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $note = ShopCreditNote::latest('id')->first();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $note->status);
        $this->assertEquals(ShopCreditNoteInventoryAction::ReturnToStock, $note->items->first()->inventory_action);

        // Verify stock movement ledger transaction was created
        $this->assertEquals($initialMovements + 1, StockMovement::count());
        $returnMovement = StockMovement::latest('id')->first();
        $this->assertEquals(StockMovementType::SaleReversal, $returnMovement->type);
        $this->assertEquals(15.0, (float) $returnMovement->quantity);
    }

    public function test_10_unsupported_deduct_extra_action_is_blocked_safely(): void
    {
        $initialNotesCount = ShopCreditNote::count();

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'other',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 80.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'deduct_from_stock',
                    ],
                ],
            ]
        );

        $response->assertSessionHasErrors(['items.0.inventory_action']);
        $this->assertEquals($initialNotesCount, ShopCreditNote::count());
    }

    public function test_11_original_invoice_remains_strictly_unchanged(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'price_correction',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 70.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $rawInvoice = ShopInvoice::findOrFail($this->invoice->id);
        $this->assertEquals(10000.00, (float) $rawInvoice->final_total);
        $this->assertEquals(0.00, (float) $rawInvoice->paid_amount);

        $rawItem = ShopInvoiceItem::findOrFail($this->invoiceItem->id);
        $this->assertEquals(100.00, (float) $rawItem->delivered_qty);
        $this->assertEquals(100.00, (float) $rawItem->unit_price);
        $this->assertEquals(10000.00, (float) $rawItem->final_line_total);
    }

    public function test_12_correct_final_bill_and_cashbook_reconciliation(): void
    {
        // Invoice: 10,000. Paid: 5,000. Credit Note: 2,000 (Qty: 80 -> -2000)
        $this->invoice->update([
            'paid_amount' => 5000.00,
            'balance_amount' => 5000.00,
            'payment_status' => 'partially_paid',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'price_correction',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 80.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'none',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(8000.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(3000.00, $freshInvoice->effectiveBalanceAmount());

        // Cashbook Shops Invoices Report summary reflects the exact adjusted values
        $reportResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices'));
        $reportResponse->assertOk();
        $summary = $reportResponse->viewData('summary');
        $this->assertEquals(10000.00, $summary['original_total']);
        $this->assertEquals(2000.00, $summary['approved_credits']);
        $this->assertEquals(8000.00, $summary['net_payable']);
        $this->assertEquals(5000.00, $summary['total_paid']);
        $this->assertEquals(3000.00, $summary['outstanding_balance']);
    }

    public function test_13_existing_report_buttons_and_filters_remain_functional(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.invoices', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
            'status' => 'finalized',
            'search' => 'INV-9001',
        ]));

        $response->assertOk();
        $invoices = $response->viewData('invoices');
        $this->assertCount(1, $invoices);
        $this->assertEquals($this->invoice->id, $invoices->first()->id);
    }

    public function test_14_move_to_damage_after_loadout_creates_wastage_entry_without_double_deducting_inventory(): void
    {
        $initialMovementsCount = StockMovement::count();
        $initialWastageCount = WastageEntry::count();

        // Admin writes off 15 kg as transit damage directly on delivered invoice
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'damage_wastage',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 85.0, // 15 kg reduced due to damage
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => 'transit_damage',
                        'notes' => 'Damaged during delivery transit',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $note = ShopCreditNote::latest('id')->first();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $note->status);
        $creditItem = $note->items->first();
        $this->assertEquals(ShopCreditNoteInventoryAction::WriteOffWastage, $creditItem->inventory_action);
        $this->assertEquals(15.0, (float) $creditItem->adjusted_quantity);

        // 1. WastageEntry was created for Damage & Loss reporting
        $this->assertEquals($initialWastageCount + 1, WastageEntry::count());
        $wastage = WastageEntry::latest('id')->first();
        $this->assertEquals($this->tomato->id, $wastage->product_id);
        $this->assertEquals($this->batch->id, $wastage->batch_id);
        $this->assertEquals($this->admin->id, $wastage->recorded_by);
        $this->assertEquals(15.0, (float) $wastage->quantity);
        $this->assertEquals(50.0, (float) $wastage->cost_per_kg); // Attributable batch cost
        $this->assertEquals(WastageReason::TransitDamage, $wastage->reason);
        $this->assertEquals('A', (string) $wastage->grade->value);
        $this->assertStringContainsString("Order: {$this->order->order_number}", (string) $wastage->notes);

        // 2. CRITICAL: Stock was ALREADY deducted on loadout, so NO new StockMovement deduction was added!
        // Prevents double deduction!
        $this->assertEquals($initialMovementsCount, StockMovement::count());

        // 3. Financial Final Bill payable updated correctly (10,000 - 1,500 = 8,500)
        $this->assertEquals(8500.00, $this->invoice->fresh()->effectiveFinalTotal());
        $this->assertEquals(8500.00, $this->invoice->fresh()->effectiveBalanceAmount());
    }

    public function test_15_physical_warehouse_return_followed_by_damage(): void
    {
        $initialMovements = StockMovement::count();
        $initialWastage = WastageEntry::count();

        // Step 1: Credit Note with physical return to warehouse (+10 kg)
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'return_to_warehouse',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 90.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'return_to_stock',
                        'notes' => 'Returned 10 kg to warehouse',
                    ],
                ],
            ]
        );

        // Reversal movement was created (+10 kg back to warehouse)
        $this->assertEquals($initialMovements + 1, StockMovement::count());
        $returnMovement = StockMovement::latest('id')->first();
        $this->assertEquals(StockMovementType::SaleReversal, $returnMovement->type);
        $this->assertEquals(10.0, (float) $returnMovement->quantity);

        // Step 2: Now that the stock is physically back in warehouse inventory, write it off via verified warehouse damage flow
        $stockLedgerService = app(StockLedgerService::class);
        $deducted = $stockLedgerService->consumeSortedStockForProduct(
            productId: $this->tomato->id,
            quantity: 10.0,
            userId: $this->admin->id,
            movementType: StockMovementType::Wastage,
            notes: 'Warehouse sorting identified returned crates as spoiled',
            warehouseId: $this->warehouse->id,
            grade: ProductGrade::GradeA,
            wastageDate: '2026-08-25',
            wastageReason: WastageReason::Rotten,
        );

        $this->assertEquals(10.0, $deducted);

        // Now warehouse damage movement was created (-10 kg)
        $this->assertEquals($initialMovements + 2, StockMovement::count());
        $damageMovement = StockMovement::latest('id')->first();
        $this->assertEquals(StockMovementType::Wastage, $damageMovement->type);
        $this->assertEquals(10.0, (float) $damageMovement->quantity);

        // And WastageEntry was created for the warehouse wastage
        $this->assertEquals($initialWastage + 1, WastageEntry::count());
        $wastage = WastageEntry::latest('id')->first();
        $this->assertEquals(WastageReason::Rotten, $wastage->reason);
        $this->assertEquals(10.0, (float) $wastage->quantity);
    }

    public function test_16_quantity_and_price_corrected_together_with_move_to_damage(): void
    {
        // Original: 100 kg @ ₹100 = ₹10,000
        // Corrected: 80 kg (20 kg damaged) @ ₹90/kg = ₹7,200
        // Expected Credit: ₹2,800
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'damage_wastage',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 80.0,
                        'revised_unit_price' => 90.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => 'rotten',
                        'notes' => 'Damaged & price renegotiation',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(7200.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(7200.00, $freshInvoice->effectiveBalanceAmount());

        $wastage = WastageEntry::latest('id')->first();
        $this->assertEquals(20.0, (float) $wastage->quantity);
        $this->assertEquals(WastageReason::Rotten, $wastage->reason);
    }

    public function test_17_grade_specific_damage(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'damage_wastage',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 75.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => 'transit_damage',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $wastage = WastageEntry::latest('id')->first();
        $this->assertEquals('A', (string) $wastage->grade->value);
    }

    public function test_18_multiple_items_with_different_inventory_actions(): void
    {
        // Add a second product to the same invoice
        $onion = Product::factory()->create([
            'name' => 'Onion Red',
            'sku' => 'ONI-RED-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $batchOnion = StockBatch::factory()->create([
            'product_id' => $onion->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BAT-ONI-001',
            'status' => BatchStatus::Sorted,
            'purchase_grade' => 'A',
            'warehouse_receive_pending' => false,
            'total_kg' => 200.0,
            'cost_per_kg' => 30.0,
        ]);

        $orderItemOnion = ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $onion->id,
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'product_grade' => 'A',
            'sorting_status' => 'allocated',
            'unit_price' => 60.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        StockMovement::create([
            'batch_id' => $batchOnion->id,
            'shop_order_item_id' => $orderItemOnion->id,
            'product_id' => $onion->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Out,
            'quantity' => 50.0,
            'balance_after' => 150.0,
            'unit_price' => 60.0,
            'cost_per_unit' => 30.0,
            'created_by' => $this->admin->id,
        ]);

        $invoiceItemOnion = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $orderItemOnion->id,
            'product_id' => $onion->id,
            'product_name' => $onion->name,
            'product_grade' => 'A',
            'unit_price' => 60.0,
            'delivered_qty' => 50.0,
            'approved_qty' => 50.0,
            'line_subtotal' => 3000.0,
            'final_line_total' => 3000.0,
        ]);

        $this->invoice->update([
            'subtotal' => 13000.0,
            'final_total' => 13000.0,
            'balance_amount' => 13000.0,
        ]);

        $initialMovements = StockMovement::count();
        $initialWastage = WastageEntry::count();

        // Tomato: Move to Damage (10 kg)
        // Onion: Return to Stock (15 kg)
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'damage_wastage',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 90.0, // 10 kg damaged
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => 'transit_damage',
                    ],
                    [
                        'shop_invoice_item_id' => $invoiceItemOnion->id,
                        'revised_quantity' => 35.0, // 15 kg returned
                        'revised_unit_price' => 60.0,
                        'inventory_action' => 'return_to_stock',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Wastage entry created for Tomato
        $this->assertEquals($initialWastage + 1, WastageEntry::count());
        $tomatoWastage = WastageEntry::where('product_id', $this->tomato->id)->latest('id')->first();
        $this->assertEquals(10.0, (float) $tomatoWastage->quantity);

        // 2. Stock return movement created for Onion
        $this->assertEquals($initialMovements + 1, StockMovement::count());
        $onionReturn = StockMovement::where('product_id', $onion->id)->latest('id')->first();
        $this->assertEquals(StockMovementType::SaleReversal, $onionReturn->type);
        $this->assertEquals(15.0, (float) $onionReturn->quantity);
    }

    public function test_19_duplicate_damage_submission_is_idempotent(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'damage_wastage',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 90.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => 'transit_damage',
                    ],
                ],
            ]
        );

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, WastageEntry::count());

        $note = ShopCreditNote::latest('id')->first();
        $item = $note->items->first();

        // Calling recordCreditNoteDamageItem directly on the already processed item is idempotent
        $stockLedger = app(StockLedgerService::class);
        $secondResult = $stockLedger->recordCreditNoteDamageItem($item, $note, $this->admin->id);

        $this->assertEquals(10.0, $secondResult);
        $this->assertEquals(1, WastageEntry::count()); // Still 1, not duplicated!
    }

    public function test_20_damage_loss_report_integration(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'damage_wastage',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 80.0, // 20 kg damaged
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => 'transit_damage',
                    ],
                ],
            ]
        );

        $reportService = app(DamageLossReportService::class);
        $reportData = $reportService->getReportData([
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
            'product_id' => $this->tomato->id,
        ]);

        $this->assertEquals(20.0, (float) $reportData['summary']->total_damaged_kg);
        $this->assertEquals(1000.00, (float) $reportData['summary']->total_damaged_cost); // 20 kg @ ₹50 cost = ₹1,000

        // Shop attribution verified
        $damageEntries = $reportData['damageEntries'];
        $firstEntry = collect($damageEntries)->first();
        $this->assertNotNull($firstEntry);
        $this->assertEquals($this->shop->id, $firstEntry->shop_id);
        $this->assertEquals($this->order->order_number, $firstEntry->order_number);
    }

    public function test_21_move_to_damage_requires_damage_reason(): void
    {
        $initialNotes = ShopCreditNote::count();

        // Attempt Move to Damage with no damage reason, generic header reason ('other'), and no notes
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.store'),
            [
                'invoice_id' => $this->invoice->id,
                'credit_note_date' => '2026-08-25',
                'reason_type' => 'other',
                'action' => 'finalize',
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 80.0,
                        'revised_unit_price' => 100.0,
                        'inventory_action' => 'write_off_wastage',
                        'damage_reason' => null,
                        'notes' => null,
                    ],
                ],
            ]
        );

        $response->assertSessionHasErrors(['items.0.damage_reason']);
        $this->assertEquals($initialNotes, ShopCreditNote::count());
    }
}
