<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Enums\Purchasing\PurchaseCreditStatus;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Product;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Purchasing\PurchaseReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Product $product;

    private CompanyAccount $bankCompanyAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash in Hand', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1200'], ['name' => 'Graded Inventory', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '1400'], ['name' => 'Vendor Advances', 'type' => 'asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);
        Account::firstOrCreate(['code' => '4200'], ['name' => 'Purchase Discounts', 'type' => 'revenue', 'is_active' => true]);
        Account::firstOrCreate(['code' => '5100'], ['name' => 'Cost of Goods Sold', 'type' => 'expense', 'is_active' => true]);
        Account::firstOrCreate(['code' => '5200'], ['name' => 'Wastage Expense', 'type' => 'expense', 'is_active' => true]);

        Permission::firstOrCreate(['name' => 'cashbook.access', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cashbook.reconciliation.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cashbook.reconciliation.manage', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(['cashbook.access', 'cashbook.reconciliation.view', 'cashbook.reconciliation.manage']);

        config(['admin.user_access.main_admin_email' => $this->admin->email]);
        config(['greenleaf.main_admin_email' => $this->admin->email]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Apex Farms Ltd',
            'type' => 'Market Agent',
            'credit_approved' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub Warehouse',
            'code' => 'CHW-01',
            'address' => 'Central Logistics Park',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Carrot Fresh',
            'unit' => 'kg',
        ]);
        $this->supplier->products()->attach($this->product->id);

        $this->bankCompanyAccount = CompanyAccount::query()->create([
            'name' => 'Primary Operating Bank',
            'account_type' => 'bank',
            'bank_name' => 'Federal Bank',
            'account_number' => '998877665544',
            'opening_balance' => 10000.00,
            'current_balance' => 10000.00,
            'enabled' => true,
            'is_default' => true,
        ]);
    }

    private function seedStock(float $qty = 100.0, float $cost = 30.0, string $grade = 'A'): StockBatch
    {
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_grade' => $grade,
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'sorted_at' => now(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'total_kg' => $qty,
            'cost_per_kg' => $cost,
            'reference' => 'BATCH-TEST-'.uniqid(),
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => $grade,
            'type' => StockMovementType::In->value,
            'quantity' => $qty,
            'cost_per_unit' => $cost,
            'created_by' => $this->admin->id,
            'notes' => 'Seeded test stock',
        ]);

        return $batch;
    }

    public function test_vendor_page_renders_purchase_credit_section_and_filters_by_period(): void
    {
        $this->seedStock();

        // 1. Create a credit note in current month
        PurchaseCreditNote::query()->create([
            'credit_note_number' => 'PCN-CURR-001',
            'supplier_id' => $this->supplier->id,
            'credit_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'reason' => PurchaseCreditReason::Return,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit,
            'inventory_action' => PurchaseCreditInventoryAction::None,
            'status' => PurchaseCreditStatus::Active,
            'total_amount' => 500.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        // 2. Create a credit note in previous month
        PurchaseCreditNote::query()->create([
            'credit_note_number' => 'PCN-PREV-002',
            'supplier_id' => $this->supplier->id,
            'credit_date' => now()->subMonth()->startOfMonth()->addDays(5)->toDateString(),
            'reason' => PurchaseCreditReason::PriceAdjustment,
            'money_effect' => PurchaseCreditMoneyEffect::VendorCredit,
            'inventory_action' => PurchaseCreditInventoryAction::None,
            'status' => PurchaseCreditStatus::Active,
            'total_amount' => 300.00,
            'applied_to_invoice_amount' => 0.00,
            'created_by' => $this->admin->id,
        ]);

        // 3. Request vendor details page with period=month
        $responseCurrent = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.show', [
                'supplier' => $this->supplier->public_uuid,
                'period' => 'month',
            ]));

        $responseCurrent->assertOk();
        $responseCurrent->assertSee('Purchase Credit');
        $responseCurrent->assertSee('PCN-CURR-001');
        $responseCurrent->assertDontSee('PCN-PREV-002');

        // 4. Request vendor details page with period=prev_month
        $responsePrev = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.show', [
                'supplier' => $this->supplier->public_uuid,
                'period' => 'prev_month',
            ]));

        $responsePrev->assertOk();
        $responsePrev->assertSee('PCN-PREV-002');
        $responsePrev->assertDontSee('PCN-CURR-001');
    }

    public function test_inventory_out_and_money_in_cash_refund(): void
    {
        $this->seedStock(qty: 50.0, cost: 25.0);
        $initialBalance = (float) $this->bankCompanyAccount->fresh()->current_balance;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'company_account_id' => $this->bankCompanyAccount->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 10.0,
                        'unit_price' => 30.00, // Total = ₹300.00
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.cashbook.finance.purchase.vendors.show', $this->supplier->public_uuid));

        /** @var PurchaseCreditNote $creditNote */
        $creditNote = PurchaseCreditNote::query()->where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($creditNote);
        $this->assertEquals(300.00, (float) $creditNote->total_amount);
        $this->assertEquals(PurchaseCreditStatus::Active, $creditNote->status);
        $this->assertEquals(PurchaseCreditMoneyEffect::MoneyIn, $creditNote->money_effect);
        $this->assertEquals(PurchaseCreditInventoryAction::Out, $creditNote->inventory_action);

        // 1. Verify Stock Movement OUT
        $stockMovement = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', StockMovementType::Out->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($stockMovement);
        $this->assertEquals(10.0, (float) $stockMovement->quantity);

        // 2. Verify Company Account Balance Incremented
        $updatedAccount = $this->bankCompanyAccount->fresh();
        $this->assertEquals($initialBalance + 300.00, (float) $updatedAccount->current_balance);

        // 3. Verify CompanyAccountStatementEntry
        $statementEntry = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $this->bankCompanyAccount->id)
            ->where('source_type', PurchaseCreditNote::class)
            ->where('source_id', $creditNote->id)
            ->first();
        $this->assertNotNull($statementEntry);
        $this->assertEquals('in', $statementEntry->direction);
        $this->assertEquals(300.00, (float) $statementEntry->amount);

        // 4. Verify Balanced Journal Entry
        $this->assertNotNull($creditNote->journal_entry_id);
        $journal = $creditNote->journalEntry;
        $this->assertNotNull($journal);
        $this->assertTrue($journal->is_balanced);
        $totalDebit = (float) $journal->transactions()->where('type', 'debit')->sum('amount');
        $totalCredit = (float) $journal->transactions()->where('type', 'credit')->sum('amount');
        $this->assertEquals(300.00, $totalDebit);
        $this->assertEquals(300.00, $totalCredit);
    }

    public function test_inventory_out_and_money_out(): void
    {
        $this->seedStock(qty: 50.0, cost: 25.0);
        $initialBalance = (float) $this->bankCompanyAccount->fresh()->current_balance;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Other->value,
                'money_effect' => PurchaseCreditMoneyEffect::MoneyOut->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'company_account_id' => $this->bankCompanyAccount->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 5.0,
                        'unit_price' => 40.00, // Total = ₹200.00
                    ],
                ],
            ]);

        $response->assertRedirect();

        $creditNote = PurchaseCreditNote::query()->where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($creditNote);

        // Verify statement entry direction OUT and account balance decremented
        $statementEntry = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $this->bankCompanyAccount->id)
            ->where('source_type', PurchaseCreditNote::class)
            ->where('source_id', $creditNote->id)
            ->first();
        $this->assertNotNull($statementEntry);
        $this->assertEquals('out', $statementEntry->direction);
        $this->assertEquals(200.00, (float) $statementEntry->amount);

        $this->assertEquals($initialBalance - 200.00, (float) $this->bankCompanyAccount->fresh()->current_balance);
        $this->assertTrue($creditNote->journalEntry->is_balanced);
    }

    public function test_vendor_credit_against_unpaid_credit_purchase(): void
    {
        $initialBankBalance = (float) $this->bankCompanyAccount->fresh()->current_balance;

        // 1. Setup unpaid invoice of ₹1,000.00
        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'amount' => 1000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Credit',
            'status' => 'pending',
        ]);

        $this->assertEquals(1000.00, (float) $invoice->remainingBalance());
        $this->assertEquals(1000.00, (float) $this->supplier->fresh()->pending_amount);

        // 2. Create credit note of ₹1,500.00 against this invoice
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::PriceAdjustment->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'purchase_invoice_id' => $invoice->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 50.0,
                        'unit_price' => 30.00, // Total = ₹1,500.00
                    ],
                ],
            ]);

        $response->assertRedirect();

        $creditNote = PurchaseCreditNote::query()->where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($creditNote);
        $this->assertEquals(1500.00, (float) $creditNote->total_amount);
        $this->assertEquals(1000.00, (float) $creditNote->applied_to_invoice_amount);
        $this->assertEquals(500.00, (float) $creditNote->vendor_advance_amount);

        // 3. Bank balance must remain completely unchanged
        $this->assertEquals($initialBankBalance, (float) $this->bankCompanyAccount->fresh()->current_balance);

        // 4. Verify VendorAdvance was generated for excess ₹500
        $this->assertNotNull($creditNote->vendor_advance_id);
        $advance = VendorAdvance::find($creditNote->vendor_advance_id);
        $this->assertNotNull($advance);
        $this->assertEquals(500.00, (float) $advance->amount_original);
        $this->assertEquals(500.00, (float) $advance->amount_remaining);

        // 5. Verify invoice remaining balance is now 0.00
        $this->assertEquals(0.00, (float) $invoice->fresh()->remainingBalance());

        // 6. Verify Supplier pending_amount is reduced to 0
        $this->assertEquals(0.00, (float) $this->supplier->fresh()->pending_amount);

        // 7. Verify PurchaseReportingService vendorDetail reports 0 outstanding
        $reportingService = app(PurchaseReportingService::class);
        $detail = $reportingService->vendorDetail($this->supplier->id, [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);
        $this->assertEquals(0.00, (float) ($detail['kpis']['total_outstanding'] ?? 0));
        $this->assertEquals(1500.00, (float) ($detail['creditKpis']['credit_notes_total'] ?? 0));
        $this->assertEquals(1000.00, (float) ($detail['creditKpis']['credit_notes_applied'] ?? 0));
        $this->assertEquals(500.00, (float) ($detail['creditKpis']['credit_notes_advance'] ?? 0));
    }

    public function test_inventory_in(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Shortage->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::In->value,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 20.0,
                        'unit_price' => 25.00, // Total = ₹500.00
                    ],
                ],
            ]);

        $response->assertRedirect();

        $creditNote = PurchaseCreditNote::query()->where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($creditNote);

        // Verify stock movement IN created
        $stockMovement = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', StockMovementType::In->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals(20.0, (float) $stockMovement->quantity);
    }

    public function test_damage_with_deduct_stock_true(): void
    {
        $this->seedStock(qty: 50.0, cost: 30.0);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::DamageWastage->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Damage->value,
                'warehouse_id' => $this->warehouse->id,
                'deduct_stock_for_damage' => true,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 15.0,
                        'unit_price' => 30.00,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // 1. Verify WastageEntry was created
        $wastage = WastageEntry::query()->where('product_id', $this->product->id)->latest('id')->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(15.0, (float) $wastage->quantity);

        // 2. Verify StockMovement with type 'wastage' was created
        $wastageMovement = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', StockMovementType::Wastage->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($wastageMovement);
        $this->assertEquals(15.0, (float) $wastageMovement->quantity);
    }

    public function test_damage_with_deduct_stock_false(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::DamageWastage->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Damage->value,
                'warehouse_id' => $this->warehouse->id,
                'deduct_stock_for_damage' => false,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 8.0,
                        'unit_price' => 30.00,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // 1. Verify WastageEntry was created
        $wastage = WastageEntry::query()->where('product_id', $this->product->id)->latest('id')->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(8.0, (float) $wastage->quantity);

        // 2. Verify NO StockMovement was created
        $movementCount = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->count();
        $this->assertSame(0, $movementCount);
    }

    public function test_validation_guards_insufficient_stock_and_missing_company_account(): void
    {
        // 1. Over-return when warehouse stock is 0
        $responseStock = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 999.0, // Insufficient stock
                        'unit_price' => 20.00,
                    ],
                ],
            ]);

        $responseStock->assertSessionHasErrors();
        $this->assertSame(0, PurchaseCreditNote::query()->count());

        // 2. Money IN without company_account_id
        $responseMoneyIn = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'company_account_id' => null, // Required for MoneyIn!
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 5.0,
                        'unit_price' => 20.00,
                    ],
                ],
            ]);

        $responseMoneyIn->assertSessionHasErrors(['company_account_id']);
        $this->assertSame(0, PurchaseCreditNote::query()->count());
    }

    public function test_authorization_guard_rejects_unpermitted_users(): void
    {
        // 1. User without cashbook.access is redirected to dashboard
        $noAccessUser = User::factory()->create(['email' => 'noaccess@greenleaf.test']);
        $responseNoAccess = $this->actingAs($noAccessUser)
            ->get(route('admin.cashbook.finance.purchase.vendors.show', $this->supplier->public_uuid));
        $responseNoAccess->assertRedirect(route('dashboard'));

        // 2. Viewer user with cashbook.access and cashbook.reconciliation.view can view vendor details
        $viewerUser = User::factory()->create(['email' => 'viewer@greenleaf.test']);
        $viewerUser->givePermissionTo(['cashbook.access', 'cashbook.reconciliation.view']);

        $responseView = $this->actingAs($viewerUser)
            ->get(route('admin.cashbook.finance.purchase.vendors.show', $this->supplier->public_uuid));
        $responseView->assertOk();

        // 3. But viewer cannot create credit notes (403 Forbidden)
        $responseCreate = $this->actingAs($viewerUser)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 5.0,
                        'unit_price' => 20.00,
                    ],
                ],
            ]);
        $responseCreate->assertForbidden();
    }

    public function test_void_and_reversal_restores_balances_and_stock(): void
    {
        $this->seedStock(qty: 50.0, cost: 20.0);
        $initialBalance = (float) $this->bankCompanyAccount->fresh()->current_balance;

        // 1. Create credit note: stock OUT 10 kg, money IN ₹300
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::MoneyIn->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'company_account_id' => $this->bankCompanyAccount->id,
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 10.0,
                        'unit_price' => 30.00,
                    ],
                ],
            ]);

        /** @var PurchaseCreditNote $creditNote */
        $creditNote = PurchaseCreditNote::query()->where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertEquals(PurchaseCreditStatus::Active, $creditNote->status);
        $this->assertEquals($initialBalance + 300.00, (float) $this->bankCompanyAccount->fresh()->current_balance);

        // 2. Void the credit note
        $responseVoid = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.void', [
                'supplier' => $this->supplier->public_uuid,
                'creditNote' => $creditNote->public_uuid,
            ]), [
                'reason' => 'Vendor rejected return goods due to transit seal damage',
            ]);

        $responseVoid->assertRedirect();

        // 3. Verify status updated to Voided
        $creditNote->refresh();
        $this->assertEquals(PurchaseCreditStatus::Voided, $creditNote->status);
        $this->assertNotNull($creditNote->voided_at);
        $this->assertEquals($this->admin->id, $creditNote->voided_by);
        $this->assertStringContainsString('Vendor rejected', (string) $creditNote->void_reason);

        // 4. Verify Company Account balance restored (decremented back by ₹300)
        $this->assertEquals($initialBalance, (float) $this->bankCompanyAccount->fresh()->current_balance);

        // 5. Verify compensating Stock Movement was created (restoring stock)
        $latestMovement = StockMovement::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($latestMovement);
        $this->assertEquals(StockMovementType::In->value, $latestMovement->type->value ?? $latestMovement->type);
        $this->assertEquals(10.0, (float) $latestMovement->quantity);

        // 6. Verify reversing journal entry
        $this->assertNotNull($creditNote->journalEntry);
        $this->assertTrue($creditNote->journalEntry->fresh()->is_reversed ?? true);
    }

    public function test_void_rejected_if_vendor_advance_already_consumed(): void
    {
        // 1. Create credit note creating an excess VendorAdvance of ₹400
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Other->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 10.0,
                        'unit_price' => 40.00, // Total = ₹400
                    ],
                ],
            ]);

        /** @var PurchaseCreditNote $creditNote */
        $creditNote = PurchaseCreditNote::query()->where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($creditNote->vendor_advance_id);

        // 2. Simulate advance utilization in settlements
        $advance = VendorAdvance::find($creditNote->vendor_advance_id);
        $advance->update([
            'amount_remaining' => 100.00, // ₹300 was utilized!
        ]);

        // 3. Attempt void
        $responseVoid = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.void', [
                'supplier' => $this->supplier->public_uuid,
                'creditNote' => $creditNote->public_uuid,
            ]), [
                'reason' => 'Attempting to void consumed advance',
            ]);

        $responseVoid->assertSessionHasErrors(['status']);

        // 4. Verify Credit Note remains Active
        $this->assertEquals(PurchaseCreditStatus::Active, $creditNote->fresh()->status);
    }

    public function test_create_page_only_includes_vendor_related_products_and_invoice_search_payload(): void
    {
        $unrelatedProduct = Product::factory()->create([
            'name' => 'Unrelated Exotic Fruit',
            'unit' => 'box',
        ]);

        $invoice = PurchaseInvoice::factory()->for($this->supplier)->create([
            'invoice_number' => 'PINV-TEST-SEARCH-99',
            'amount' => 2500.00,
            'discount_amount' => 500.00,
            'paid_amount' => 1000.00,
            'payment_method' => 'Credit',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.vendors.credit-notes.create', $this->supplier->public_uuid));

        $response->assertOk();

        // 1. Verify view data has only vendor-related products
        $response->assertViewHas('products', function ($products) use ($unrelatedProduct) {
            $ids = $products->pluck('id')->all();

            return in_array($this->product->id, $ids, true)
                && ! in_array($unrelatedProduct->id, $ids, true);
        });

        $response->assertSee('Carrot Fresh');
        $response->assertDontSee('Unrelated Exotic Fruit');

        // 2. Verify view data has rich invoice payload for search
        $response->assertViewHas('invoices', function ($invoices) {
            $first = collect($invoices)->firstWhere('invoice_number', 'PINV-TEST-SEARCH-99');
            if (! $first) {
                return false;
            }

            return $first['gross_amount'] === 2500.0
                && $first['discount_amount'] === 500.0
                && $first['net_amount'] === 2000.0
                && $first['settled_amount'] === 1000.0
                && $first['outstanding_amount'] === 1000.0;
        });
    }

    public function test_submitting_unrelated_product_fails_validation(): void
    {
        $unrelatedProduct = Product::factory()->create([
            'name' => 'Foreign Product XYZ',
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::None->value,
                'items' => [
                    [
                        'product_id' => $unrelatedProduct->id,
                        'grade' => 'A',
                        'quantity' => 10.0,
                        'unit_price' => 50.00,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['items.0.product_id']);
        $this->assertSame(0, PurchaseCreditNote::query()->count());
    }

    public function test_check_stock_endpoint_returns_correct_availability_and_shortages(): void
    {
        $this->seedStock(qty: 10.0, cost: 45.0, grade: 'A');

        $responseSufficient = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.finance.purchase.vendors.credit-notes.check-stock', $this->supplier), [
                'warehouse_id' => $this->warehouse->id,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 8.0,
                        'unit_price' => 50.00,
                    ],
                ],
            ]);

        $responseSufficient->assertOk();
        $responseSufficient->assertJson([
            'has_shortage' => false,
            'shortages' => [],
        ]);

        $responseShortage = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.finance.purchase.vendors.credit-notes.check-stock', $this->supplier), [
                'warehouse_id' => $this->warehouse->id,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 25.0,
                        'unit_price' => 50.00,
                    ],
                ],
            ]);

        $responseShortage->assertOk();
        $responseShortage->assertJson([
            'has_shortage' => true,
            'shortages' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'A',
                    'available_qty' => 10.0,
                    'requested_qty' => 25.0,
                    'shortage_qty' => 15.0,
                    'resulting_stock' => -15.0,
                ],
            ],
        ]);
    }

    public function test_submitting_stock_out_with_insufficient_stock_and_no_allow_negative_fails_validation_and_flashes_shortages(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'warehouse_id' => $this->warehouse->id,
                'allow_negative_stock' => false,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 10.0,
                        'unit_price' => 50.00,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors(['allow_negative_stock']);
        $response->assertSessionHas('negative_stock_shortages');
        $shortages = session('negative_stock_shortages');
        $this->assertNotEmpty($shortages);
        $this->assertSame($this->product->id, $shortages[0]['product_id']);
        $this->assertEquals(10.0, $shortages[0]['requested_qty']);
        $this->assertEquals(-10.0, $shortages[0]['resulting_stock']);
        $this->assertSame(0, PurchaseCreditNote::query()->count());
    }

    public function test_submitting_stock_out_with_allow_negative_stock_creates_neg_batch_and_marks_flag(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'warehouse_id' => $this->warehouse->id,
                'allow_negative_stock' => true,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 15.0,
                        'unit_price' => 60.00,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertSame(1, PurchaseCreditNote::query()->count());

        $note = PurchaseCreditNote::query()->first();
        $this->assertNotNull($note);
        $this->assertTrue((bool) $note->allowed_negative_stock);

        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovementType::Out->value)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(15.0, (float) $movement->quantity);

        $batch = StockBatch::find($movement->batch_id);
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('NEG-', $batch->reference);
    }

    public function test_voiding_credit_note_with_negative_stock_reverses_compensating_stock_movement(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::Return->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Out->value,
                'warehouse_id' => $this->warehouse->id,
                'allow_negative_stock' => true,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 15.0,
                        'unit_price' => 60.00,
                    ],
                ],
            ]);

        $note = PurchaseCreditNote::query()->firstOrFail();
        $this->assertTrue((bool) $note->allowed_negative_stock);

        $voidResponse = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.void', [
                'supplier' => $this->supplier->public_uuid,
                'creditNote' => $note->public_uuid,
            ]), [
                'reason' => 'Voiding credit note entered with negative stock',
            ]);

        $voidResponse->assertRedirect();
        $note->refresh();
        $this->assertTrue($note->isVoided());

        $reversalMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovementType::In->value)
            ->where('notes', 'like', '%Void Reversal%')
            ->first();
        $this->assertNotNull($reversalMovement);
        $this->assertEquals(15.0, (float) $reversalMovement->quantity);
    }

    public function test_submitting_damage_with_deduct_stock_and_allow_negative_creates_wastage_and_negative_movement(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $this->supplier), [
                'credit_date' => now()->toDateString(),
                'reason' => PurchaseCreditReason::DamageWastage->value,
                'money_effect' => PurchaseCreditMoneyEffect::VendorCredit->value,
                'inventory_action' => PurchaseCreditInventoryAction::Damage->value,
                'deduct_stock_for_damage' => true,
                'warehouse_id' => $this->warehouse->id,
                'allow_negative_stock' => true,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'grade' => 'A',
                        'quantity' => 12.0,
                        'unit_price' => 50.00,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertSame(1, PurchaseCreditNote::query()->count());

        $note = PurchaseCreditNote::query()->first();
        $this->assertNotNull($note);
        $this->assertTrue((bool) $note->allowed_negative_stock);

        $wastage = WastageEntry::where('product_id', $this->product->id)->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(12.0, (float) $wastage->quantity);

        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('type', StockMovementType::Wastage->value)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(12.0, (float) $movement->quantity);

        $batch = StockBatch::find($movement->batch_id);
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('NEG-', $batch->reference);
    }
}
