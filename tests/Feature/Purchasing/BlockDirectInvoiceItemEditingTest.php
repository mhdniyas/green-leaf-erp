<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BlockDirectInvoiceItemEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $staff;

    private Shop $shop;

    private Warehouse $warehouse;

    private Product $product;

    private ShopOrder $order;

    private ShopOrderItem $orderItem;

    private ShopInvoiceService $invoiceService;

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

        $this->purchaser = User::factory()->create([
            'email' => 'purchaser@example.com',
        ]);
        $this->purchaser->assignRole('purchaser');

        $this->staff = User::factory()->create([
            'email' => 'staff@example.com',
        ]);

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

        $this->product = Product::factory()->create([
            'name' => 'Tomato Premium',
            'sku' => 'TOM-PREM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-BLOCK-01',
            'delivery_status' => 'delivered',
            'business_date' => '2026-08-25',
        ]);

        $this->orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 100.0,
            'approved_qty' => 100.0,
            'loaded_qty' => 100.0,
            'product_grade' => 'A',
            'sorting_status' => 'loaded',
            'unit_price' => 50.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        $this->invoiceService = app(ShopInvoiceService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createInvoiceWithStatus(string $status, float $paidAmount = 0.0): array
    {
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $this->order->id,
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'business_date' => '2026-08-25',
            'subtotal' => 5000.0,
            'final_total' => 5000.0,
            'paid_amount' => $paidAmount,
            'balance_amount' => 5000.0 - $paidAmount,
            'status' => $status,
            'payment_status' => $paidAmount >= 5000.0 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'pending'),
            'delivery_status' => 'delivered',
            'finalized_at' => in_array($status, ['finalized', 'paid'], true) ? '2026-08-25 09:00:00' : null,
            'finalized_by' => in_array($status, ['finalized', 'paid'], true) ? $this->admin->id : null,
        ]);

        $item = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_grade' => 'A',
            'unit' => 'kg',
            'unit_price' => 50.0,
            'delivered_qty' => 100.0,
            'approved_qty' => 100.0,
            'line_subtotal' => 5000.0,
            'final_line_total' => 5000.0,
        ]);

        return [$invoice, $item];
    }

    public function test_01_generated_invoice_rejects_direct_quantity_editing_via_service(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('generated');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Direct editing of original invoice items is disabled for generated invoices. All quantity, price, and line adjustments must be made through Credit Notes.');

        $this->invoiceService->updateItemFinalQuantityAndPrice(
            $invoice,
            $item,
            80.0,
            50.0,
            $this->admin->id,
            'Trying to alter generated invoice quantity'
        );
    }

    public function test_02_generated_invoice_rejects_direct_quantity_editing_via_http_endpoint(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('generated');

        $response = $this->actingAs($this->admin)->patchJson(
            route('purchasing.shop-invoices.items.update', [$invoice, $item]),
            [
                'final_qty' => 80.0,
                'final_price' => 50.0,
                'note' => 'HTTP direct edit attempt',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice']);
        $this->assertStringContainsString('Credit Notes', $response->json('errors.invoice.0'));
    }

    public function test_03_finalized_invoice_rejects_direct_price_editing(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('finalized');

        $response = $this->actingAs($this->admin)->patchJson(
            route('purchasing.shop-invoices.items.update', [$invoice, $item]),
            [
                'final_qty' => 100.0,
                'final_price' => 45.0,
                'note' => 'Trying to lower finalized price',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice']);
    }

    public function test_04_paid_invoice_rejects_direct_editing(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('paid', 5000.0);

        $response = $this->actingAs($this->admin)->patchJson(
            route('purchasing.shop-invoices.items.update', [$invoice, $item]),
            [
                'final_qty' => 90.0,
                'final_price' => 50.0,
                'note' => 'Editing paid invoice',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice']);
    }

    public function test_05_rejected_request_leaves_original_invoice_and_item_values_completely_unchanged(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('generated');

        $this->actingAs($this->admin)->patchJson(
            route('purchasing.shop-invoices.items.update', [$invoice, $item]),
            [
                'final_qty' => 20.0,
                'final_price' => 10.0,
                'note' => 'Malicious edit attempt',
            ]
        );

        $freshInvoice = $invoice->fresh();
        $freshItem = $item->fresh();

        $this->assertSame(5000.0, (float) $freshInvoice->subtotal);
        $this->assertSame(5000.0, (float) $freshInvoice->final_total);
        $this->assertSame(100.0, (float) $freshItem->delivered_qty);
        $this->assertSame(50.0, (float) $freshItem->unit_price);
        $this->assertSame(5000.0, (float) $freshItem->final_line_total);
    }

    public function test_06_credit_note_creation_flow_continues_to_work_for_these_same_invoices(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('generated');

        $creditNoteService = app(ShopCreditNoteService::class);

        $creditNote = $creditNoteService->createDraft($invoice, [
            'direction' => 'credit',
            'reason' => 'damage_deduction',
            'notes' => 'Credit note for 10kg damage',
            'items' => [
                [
                    'shop_invoice_item_id' => $item->id,
                    'product_id' => $this->product->id,
                    'product_grade' => 'A',
                    'adjusted_quantity' => 10.0,
                    'adjusted_unit_price' => 50.0,
                    'line_credit_amount' => 500.0,
                    'inventory_action' => 'write_off_wastage',
                ],
            ],
        ], $this->admin->id);

        $this->assertInstanceOf(ShopCreditNote::class, $creditNote);
        $this->assertSame($invoice->id, $creditNote->shop_invoice_id);
        $this->assertSame(500.0, (float) $creditNote->total_credit_amount);

        // Original invoice values remain unchanged
        $freshInvoice = $invoice->fresh();
        $freshItem = $item->fresh();
        $this->assertSame(5000.0, (float) $freshInvoice->final_total);
        $this->assertSame(100.0, (float) $freshItem->delivered_qty);
    }

    public function test_07_authorization_checks_remain_intact_unauthorized_users_forbidden(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('generated');

        // Unauthenticated
        $response = $this->patchJson(
            route('purchasing.shop-invoices.items.update', [$invoice, $item]),
            ['final_qty' => 90.0, 'final_price' => 50.0]
        );
        $response->assertStatus(401);

        // Unauthorized staff without roles
        $response = $this->actingAs($this->staff)->patchJson(
            route('purchasing.shop-invoices.items.update', [$invoice, $item]),
            ['final_qty' => 90.0, 'final_price' => 50.0]
        );
        $response->assertStatus(403);
    }

    public function test_08_invoice_show_ui_displays_protected_notice_and_omits_direct_edit_buttons(): void
    {
        [$invoice, $item] = $this->createInvoiceWithStatus('generated');

        $response = $this->actingAs($this->admin)->get(route('purchasing.shop-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Original Billing Protected');
        $response->assertSee('All quantity, price, damage, or return corrections must be made through Credit Notes.');
        $response->assertSee('Add Credit Note');

        // Direct edit triggers must not be present on item rows
        $response->assertDontSee('data-item-id="'.$item->id.'"', false);
        $response->assertDontSee('data-action="'.route('purchasing.shop-invoices.items.update', [$invoice, $item]).'"', false);
    }
}
