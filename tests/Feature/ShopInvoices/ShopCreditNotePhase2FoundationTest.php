<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopCreditNotePhase2FoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopInvoice $invoice;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'code' => 'KTYM02',
            'name' => 'Kottayam South Shop',
            'status' => 'active',
            'accounting_enabled' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->product = Product::factory()->create([
            'name' => 'Tomato Grade A',
            'sku' => 'TOM-GRA-002',
            'unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261106-002',
            'business_date' => '2026-11-06',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'delivered_qty' => 50,
            'unit' => 'kg',
            'unit_cost' => 30.00,
        ]);

        $this->invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20261106-KTYM02',
            'business_date' => '2026-11-06',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 2000.00,
            'shortage_total' => 0.00,
            'discount_total' => 0.00,
            'final_total' => 2000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 2000.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $this->product->id,
            'product_name' => 'Tomato Grade A',
            'product_grade' => 'A',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 50.00,
            'price_quantity' => 50.0000,
            'delivered_qty' => 50.00,
            'delivered_price_quantity' => 50.0000,
            'unit_price' => 40.00,
            'line_subtotal' => 2000.00,
            'final_line_total' => 2000.00,
        ]);
    }

    /**
     * 1 & 3: Accounting mode defaults to 'legacy_invoice_linked' at database level.
     */
    public function test_accounting_mode_defaults_to_legacy_invoice_linked(): void
    {
        // Direct DB insert without specifying accounting_mode
        $id = DB::table('shop_credit_notes')->insertGetId([
            'credit_note_number' => 'SCN-DEFAULT-001',
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'credit_note_date' => '2026-11-06',
            'direction' => 'credit',
            'reason_type' => 'price_correction',
            'status' => 'draft',
            'inventory_action' => 'none',
            'subtotal' => 100.00,
            'total_credit_amount' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $raw = DB::table('shop_credit_notes')->where('id', $id)->first();
        $this->assertSame('legacy_invoice_linked', $raw->accounting_mode);

        $model = ShopCreditNote::find($id);
        $this->assertInstanceOf(ShopCreditNote::class, $model);
        $this->assertSame(ShopCreditNoteAccountingMode::LegacyInvoiceLinked, $model->accounting_mode);
        $this->assertTrue($model->isLegacyMode());
        $this->assertFalse($model->isShopLevelMode());
    }

    /**
     * 4 & 5: Explicit 'shop_level' mode can be stored with a null shop_invoice_id.
     */
    public function test_shop_level_credit_note_can_have_null_invoice_reference(): void
    {
        $creditNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-SHOPLEVEL-001',
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => null,
            'credit_note_date' => '2026-11-06',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::Other,
            'status' => ShopCreditNoteStatus::Draft,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'subtotal' => 500.00,
            'total_credit_amount' => 500.00,
            'notes' => 'Shop-level credit note without single invoice attachment',
            'created_by' => $this->admin->id,
        ]);

        $this->assertNull($creditNote->shop_invoice_id);
        $this->assertNull($creditNote->invoice);
        $this->assertSame(ShopCreditNoteAccountingMode::ShopLevel, $creditNote->accounting_mode);
        $this->assertTrue($creditNote->isShopLevelMode());
        $this->assertFalse($creditNote->isLegacyMode());

        $raw = DB::table('shop_credit_notes')->where('id', $creditNote->id)->first();
        $this->assertNull($raw->shop_invoice_id);
        $this->assertSame('shop_level', $raw->accounting_mode);
    }

    /**
     * 6: Existing legacy invoice references remain intact.
     */
    public function test_legacy_invoice_references_remain_intact(): void
    {
        $legacyNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-LEGACY-002',
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'credit_note_date' => '2026-11-06',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::LegacyInvoiceLinked,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 200.00,
            'total_credit_amount' => 200.00,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $this->assertSame($this->invoice->id, $legacyNote->shop_invoice_id);
        $this->assertTrue($legacyNote->invoice->is($this->invoice));
        $this->assertTrue($this->invoice->creditNotes->contains($legacyNote));
    }

    /**
     * 7 & 11: Credit application model relationships and nullable reversal fields.
     */
    public function test_credit_application_relationships_and_reversal_fields(): void
    {
        $creditNote = ShopCreditNote::factory()->shopLevel()->create([
            'shop_id' => $this->shop->id,
            'total_credit_amount' => 1000.00,
            'status' => ShopCreditNoteStatus::Approved,
        ]);

        $targetInvoice = $this->invoice;

        $application = ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $targetInvoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 450.00,
            'applied_at' => now(),
            'applied_by' => $this->admin->id,
            'notes' => 'Allocated to invoice #'.($targetInvoice->invoice_number),
            'reversed_at' => null,
            'reversed_by' => null,
            'reversal_reason' => null,
        ]);

        // Relationships
        $this->assertTrue($application->creditNote->is($creditNote));
        $this->assertTrue($application->invoice->is($targetInvoice));
        $this->assertTrue($application->shop->is($this->shop));
        $this->assertTrue($application->appliedByUser->is($this->admin));
        $this->assertNull($application->reversedByUser);
        $this->assertFalse($application->isReversed());
        $this->assertTrue($application->isActive());

        // Reverse application
        $reverser = User::factory()->create();
        $application->update([
            'reversed_at' => now(),
            'reversed_by' => $reverser->id,
            'reversal_reason' => 'Disputed allocation reversal',
        ]);

        $application->refresh();

        $this->assertTrue($application->isReversed());
        $this->assertFalse($application->isActive());
        $this->assertTrue($application->reversedByUser->is($reverser));
        $this->assertSame('Disputed allocation reversal', $application->reversal_reason);
    }

    /**
     * 8: Applied amount cannot be zero or negative (CHECK constraint enforcement).
     */
    public function test_applied_amount_cannot_be_zero_or_negative(): void
    {
        $creditNote = ShopCreditNote::factory()->create(['shop_id' => $this->shop->id]);

        $this->expectException(QueryException::class);

        // Attempting to insert zero or negative amount must violate database CHECK constraint
        DB::table('shop_credit_applications')->insert([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $this->invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 0.00,
            'applied_at' => now(),
        ]);
    }

    /**
     * 9 & 10: Financial application records cannot be deleted through parent cascade (RESTRICT foreign keys).
     */
    public function test_parent_deletion_is_restricted_when_credit_applications_exist(): void
    {
        $creditNote = ShopCreditNote::factory()->create(['shop_id' => $this->shop->id]);

        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $this->invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 300.00,
            'applied_at' => now(),
            'applied_by' => $this->admin->id,
        ]);

        // Attempting to delete the credit note directly via DB query must fail with foreign key violation
        $this->expectException(QueryException::class);
        DB::table('shop_credit_notes')->where('id', $creditNote->id)->delete();
    }

    /**
     * 10: Target invoice deletion is restricted when credit applications exist.
     */
    public function test_invoice_deletion_is_restricted_when_credit_applications_exist(): void
    {
        $creditNote = ShopCreditNote::factory()->create(['shop_id' => $this->shop->id]);

        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $this->invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 250.00,
            'applied_at' => now(),
            'applied_by' => $this->admin->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('shop_invoices')->where('id', $this->invoice->id)->delete();
    }

    /**
     * 12: Credit note application totals derivation on ShopCreditNote.
     */
    public function test_credit_note_derives_applied_and_unapplied_totals(): void
    {
        $creditNote = ShopCreditNote::factory()->shopLevel()->create([
            'shop_id' => $this->shop->id,
            'total_credit_amount' => 1500.00,
            'status' => ShopCreditNoteStatus::Approved,
        ]);

        $this->assertEquals(0.00, $creditNote->appliedCreditTotal());
        $this->assertEquals(1500.00, $creditNote->unappliedCreditTotal());

        // Create active application for ₹600
        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $this->invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 600.00,
            'applied_at' => now(),
            'applied_by' => $this->admin->id,
        ]);

        $freshNote = $creditNote->fresh(['activeApplications']);
        $this->assertEquals(600.00, $freshNote->appliedCreditTotal());
        $this->assertEquals(900.00, $freshNote->unappliedCreditTotal());

        // Create a reversed application for ₹200 (must NOT be counted in applied total)
        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $this->invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 200.00,
            'applied_at' => now(),
            'applied_by' => $this->admin->id,
            'reversed_at' => now(),
            'reversed_by' => $this->admin->id,
            'reversal_reason' => 'Reversed test allocation',
        ]);

        $freshNote2 = $creditNote->fresh(['activeApplications']);
        $this->assertEquals(600.00, $freshNote2->appliedCreditTotal());
        $this->assertEquals(900.00, $freshNote2->unappliedCreditTotal());
    }

    /**
     * 13 & 14: Migration rollback precondition safety tests.
     */
    public function test_migration_down_blocked_when_applications_exist(): void
    {
        $creditNote = ShopCreditNote::factory()->create(['shop_id' => $this->shop->id]);

        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $this->invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 150.00,
            'applied_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_06_180000_upgrade_shop_credit_notes_for_phase2_foundation.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot rollback migration: shop_credit_applications records exist.');

        $migration->down();
    }

    public function test_migration_down_blocked_when_null_invoice_notes_exist(): void
    {
        ShopCreditNote::factory()->shopLevel()->create([
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => null,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
        ]);

        $migration = require database_path('migrations/2026_11_06_180000_upgrade_shop_credit_notes_for_phase2_foundation.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot rollback migration: shop_credit_notes with null shop_invoice_id or non-legacy accounting mode exist.');

        $migration->down();
    }

    public function test_migration_rollback_succeeds_safely_when_only_legacy_records_exist(): void
    {
        // Only legacy records with valid invoice IDs exist
        ShopCreditNote::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'accounting_mode' => ShopCreditNoteAccountingMode::LegacyInvoiceLinked,
        ]);

        $migration = require database_path('migrations/2026_11_06_180000_upgrade_shop_credit_notes_for_phase2_foundation.php');

        // Rollback down() should execute cleanly
        $migration->down();

        $this->assertFalse(Schema::hasTable('shop_credit_applications'));
        $this->assertFalse(Schema::hasColumn('shop_credit_notes', 'accounting_mode'));

        // Re-run up() to restore schema for test lifecycle
        $migration->up();
        $this->assertTrue(Schema::hasTable('shop_credit_applications'));
        $this->assertTrue(Schema::hasColumn('shop_credit_notes', 'accounting_mode'));
    }
}
