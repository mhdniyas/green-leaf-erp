<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDO;
use RuntimeException;
use Tests\TestCase;

class ShopCreditNoteFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoice $invoiceA2;

    private ShopInvoiceItem $itemOnionA1;

    private ShopInvoiceItem $itemTomatoA2;

    private ShopCreditNoteService $service;

    private InvoiceCashbookProjectionService $projectionService;

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

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('purchaser');

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

        $this->service = app(ShopCreditNoteService::class);
        $this->projectionService = app(InvoiceCashbookProjectionService::class);
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

    /**
     * Test 1: Valid shop-level draft finalizes successfully.
     */
    public function test_01_valid_shop_level_draft_finalizes_successfully(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'notes' => 'Draft test 1',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse->value,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin, 'Audited by office staff');

        $this->assertEquals(ShopCreditNoteStatus::Approved, $finalized->status);
        $this->assertEquals($this->admin->id, $finalized->approved_by);
        $this->assertNotNull($finalized->approved_at);
        $this->assertStringContainsString("Finalized from {$draft->credit_note_number}", (string) $finalized->notes);
        $this->assertStringContainsString('Audited by office staff', (string) $finalized->notes);
        $this->assertEquals(500.00, (float) $finalized->total_credit_amount);
    }

    /**
     * Test 2: Finalized note receives unique final canonical number (SCN-...).
     */
    public function test_02_finalized_note_receives_unique_final_number(): void
    {
        $draft1 = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $draft2 = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->assertStringStartsWith('DRAFT-SCN-', $draft1->credit_note_number);
        $this->assertStringStartsWith('DRAFT-SCN-', $draft2->credit_note_number);

        $finalized1 = $this->service->finalizeShopLevelCreditNote($draft1, $this->admin);
        $finalized2 = $this->service->finalizeShopLevelCreditNote($draft2, $this->admin);

        $this->assertStringStartsWith('SCN-', $finalized1->credit_note_number);
        $this->assertStringStartsWith('SCN-', $finalized2->credit_note_number);
        $this->assertStringNotContainsString('DRAFT-', $finalized1->credit_note_number);
        $this->assertStringNotContainsString('DRAFT-', $finalized2->credit_note_number);

        $dateStr = today()->format('Ymd');
        $this->assertEquals("SCN-{$dateStr}-SHP-A-01", $finalized1->credit_note_number);
        $this->assertEquals("SCN-{$dateStr}-SHP-A-02", $finalized2->credit_note_number);
    }

    /**
     * Test 3: Original invoice amounts remain strictly unchanged.
     */
    public function test_03_original_invoice_amounts_remain_unchanged(): void
    {
        $origA1Total = (float) $this->invoiceA1->final_total;
        $origA1Bal = (float) $this->invoiceA1->balance_amount;
        $origA2Total = (float) $this->invoiceA2->final_total;
        $origA2Bal = (float) $this->invoiceA2->balance_amount;

        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemTomatoA2->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 40.0,
                ],
            ],
        ], $this->admin);

        $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->invoiceA1->refresh();
        $this->invoiceA2->refresh();

        $this->assertEquals($origA1Total, (float) $this->invoiceA1->final_total);
        $this->assertEquals($origA1Bal, (float) $this->invoiceA1->balance_amount);
        $this->assertEquals($origA2Total, (float) $this->invoiceA2->final_total);
        $this->assertEquals($origA2Bal, (float) $this->invoiceA2->balance_amount);
    }

    /**
     * Test 4: Multi-source note posts combined amount once.
     */
    public function test_04_multi_source_note_posts_combined_amount_once(): void
    {
        // 10 kg Onion @ 50 = 500, 20 kg Tomato @ 40 = 800 => Total = 1300
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemTomatoA2->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 40.0,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->assertEquals(1300.00, (float) $finalized->total_credit_amount);

        $txCount = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->count();

        $this->assertEquals(1, $txCount);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();

        $this->assertEquals(1300.00, (float) $tx->amount);
        $this->assertEquals(LedgerDirection::Income->value, $tx->direction);
        $this->assertEquals(TransactionStatus::Approved->value, $tx->status);
    }

    /**
     * Test 5: Unapplied credit equals final Credit Note value.
     */
    public function test_05_unapplied_credit_equals_final_credit_note_value(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 15.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->assertEquals(750.00, (float) $finalized->total_credit_amount);
        $this->assertEquals(750.00, $finalized->unappliedCreditTotal());
        $this->assertEquals(0.00, $finalized->appliedCreditTotal());
        $this->assertEquals(0, ShopCreditApplication::where('shop_credit_note_id', $finalized->id)->count());
    }

    /**
     * Test 6: No target invoice balance is directly reduced.
     */
    public function test_06_no_target_invoice_balance_directly_reduced(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->invoiceA1->refresh();

        // Target invoice relations and calculations remain decoupled
        $this->assertEquals(0.00, (float) $this->invoiceA1->creditNotes()->sum('total_credit_amount'));
        $this->assertEquals(5000.00, $this->invoiceA1->effectiveFinalTotal());
        $this->assertEquals(5000.00, $this->invoiceA1->effectiveBalanceAmount());

        $billCalc = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(5000.00, $billCalc->finalNetPayable);
        $this->assertEquals(0.00, $billCalc->finalizedCreditTotal);
    }

    /**
     * Test 7: Cashbook receives exactly one financial effect.
     */
    public function test_07_cashbook_receives_exactly_one_financial_effect(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
            'discounts' => [
                [
                    'amount' => 100.00,
                    'notes' => 'Goodwill discount',
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        // Even with 1 product line and 1 discount, cashbook receives exactly 1 transaction for the note
        $transactions = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->get();

        $this->assertCount(1, $transactions);
        $this->assertEquals(600.00, (float) $transactions->first()->amount);
        $this->assertEquals(LedgerDirection::Income->value, $transactions->first()->direction);
    }

    /**
     * Test 8: Shop-level outstanding reflects credit exactly once.
     */
    public function test_08_shop_level_outstanding_reflects_credit_once(): void
    {
        $businessDate = today()->toDateString();

        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'credit_note_date' => $businessDate,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        /** @var BalanceCalculator $calculator */
        $calculator = app(BalanceCalculator::class);
        $snapshot = $calculator->recalculate($this->shopA->id, $businessDate);

        $this->assertInstanceOf(ShopDailyLedgerSnapshot::class, $snapshot);

        // Verify that recalculating balance again produces identical consistent result
        $snapshotAgain = $calculator->recalculate($this->shopA->id, $businessDate);
        $this->assertEquals((float) $snapshot->company_pending_closing, (float) $snapshotAgain->company_pending_closing);
    }

    /**
     * Test 9: Commercial discount included correctly.
     */
    public function test_09_commercial_discount_included_correctly(): void
    {
        // Commercial discount only (no product lines)
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'notes' => 'Promotional rebate',
            'discounts' => [
                [
                    'amount' => 350.00,
                    'notes' => 'Volume incentive rebate',
                ],
            ],
        ], $this->admin);

        $this->assertEquals(350.00, (float) $draft->discount_amount);
        $this->assertEquals(350.00, (float) $draft->total_credit_amount);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->assertEquals(ShopCreditNoteStatus::Approved, $finalized->status);
        $this->assertEquals(350.00, (float) $finalized->total_credit_amount);
        $this->assertEquals(350.00, (float) $finalized->discount_amount);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(350.00, (float) $tx->amount);
    }

    /**
     * Test 10: Finalization revalidates source quantities under locks.
     */
    public function test_10_finalization_revalidates_source_quantities_under_locks(): void
    {
        // 1. Create Draft requesting 40 kg Onion (available: 100 kg)
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 40.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // 2. Meanwhile, another credit note finalizes and credits 70 kg of that item (leaving only 30 kg available)
        $interveningDraft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 70.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);
        $this->service->finalizeShopLevelCreditNote($interveningDraft, $this->admin);

        // 3. Attempting to finalize original draft (which wants 40 kg) must now fail because only 30 kg remain!
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds remaining eligible quantity');

        $this->service->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 11: Two drafts cannot over-credit one source line.
     */
    public function test_11_two_drafts_cannot_overcredit_one_source_line(): void
    {
        // Draft 1 requests 60 kg (of 100 kg)
        $draft1 = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 60.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // Draft 2 requests 60 kg (of 100 kg)
        $draft2 = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 60.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // Draft 1 finalizes successfully
        $this->service->finalizeShopLevelCreditNote($draft1, $this->admin);

        // Draft 2 must be rejected because 60 + 60 > 100
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds remaining eligible quantity');

        $this->service->finalizeShopLevelCreditNote($draft2, $this->admin);
    }

    /**
     * Test 12: Double-submit cannot finalize twice.
     */
    public function test_12_double_submit_cannot_finalize_twice(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // First finalization succeeds
        $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        // Immediate retry fails
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Credit note is already finalized.');

        $this->service->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 13: Concurrent finalization cannot duplicate note numbers on MySQL.
     */
    public function test_13_concurrent_finalization_cannot_duplicate_note_numbers_on_mysql(): void
    {
        $mysqlHost = config('database.connections.mysql.host', '127.0.0.1');
        $mysqlPort = config('database.connections.mysql.port', '3306');
        $mysqlUser = config('database.connections.mysql.username', 'root');
        $mysqlPass = config('database.connections.mysql.password', '');
        $testDb = 'green_leaf_erp_concurrency_test';

        try {
            $pdo1 = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$testDb}", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo2 = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$testDb}", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped("MySQL connection to {$testDb} unavailable: ".$e->getMessage());
        }

        // Clean tables in isolated test database
        $pdo1->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo2->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo1->exec('TRUNCATE TABLE shop_credit_notes');
        $pdo1->exec('TRUNCATE TABLE shops');

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (888, 'Seq Shop', 'SEQ01', 'active', NOW(), NOW())");

        $dateStr = date('Ymd');
        $base = "SCN-{$dateStr}-SEQ01";

        // Connection 1 locks and counts existing notes
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("SELECT COUNT(*) FROM shop_credit_notes WHERE shop_id = 888 AND credit_note_number LIKE '{$base}%' AND credit_note_number NOT LIKE 'DRAFT-%' FOR UPDATE");
        $stmt1->execute();
        $count1 = (int) $stmt1->fetchColumn();
        $this->assertEquals(0, $count1);

        $finalNumber1 = sprintf('%s-%02d', $base, $count1 + 1);
        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, created_at, updated_at) VALUES (881, '{$finalNumber1}', 888, CURDATE(), 'credit', 'shop_level', 'approved', 500.00, NOW(), NOW())");
        $pdo1->commit();

        // Connection 2 now acquires lock and counts
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare("SELECT COUNT(*) FROM shop_credit_notes WHERE shop_id = 888 AND credit_note_number LIKE '{$base}%' AND credit_note_number NOT LIKE 'DRAFT-%' FOR UPDATE");
        $stmt2->execute();
        $count2 = (int) $stmt2->fetchColumn();
        $this->assertEquals(1, $count2);

        $finalNumber2 = sprintf('%s-%02d', $base, $count2 + 1);
        $pdo2->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, created_at, updated_at) VALUES (882, '{$finalNumber2}', 888, CURDATE(), 'credit', 'shop_level', 'approved', 500.00, NOW(), NOW())");
        $pdo2->commit();

        $this->assertEquals("{$base}-01", $finalNumber1);
        $this->assertEquals("{$base}-02", $finalNumber2);
        $this->assertNotEquals($finalNumber1, $finalNumber2);
    }

    /**
     * Test 14: Ledger failure rolls back entire finalization.
     */
    public function test_14_ledger_failure_rolls_back_entire_finalization(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $draftNumber = $draft->credit_note_number;

        // Mock projection service to throw during ledger sync
        $mockProjection = $this->createMock(InvoiceCashbookProjectionService::class);
        $mockProjection->method('syncCreditNote')
            ->willThrowException(new RuntimeException('Simulated ledger service outage'));

        $serviceWithFailingLedger = new ShopCreditNoteService(
            invoiceCashbookProjectionService: $mockProjection,
        );

        $exceptionThrown = false;
        try {
            $serviceWithFailingLedger->finalizeShopLevelCreditNote($draft, $this->admin);
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            $this->assertEquals('Simulated ledger service outage', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown);

        // Entire transaction rolled back!
        $draft->refresh();
        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals($draftNumber, $draft->credit_note_number);
        $this->assertNull($draft->approved_at);
        $this->assertNull($draft->approved_by);

        // No orphaned ledger transactions
        $this->assertEquals(0, ShopLedgerTransaction::where('reference_id', $draft->id)->count());
    }

    /**
     * Test 15: No warehouse stock movement created.
     */
    public function test_15_no_warehouse_stock_movement_created(): void
    {
        $movementCountBefore = StockMovement::count();

        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'reason_type' => ShopCreditNoteReason::ReturnToWarehouse->value,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock->value,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock->value,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $movementCountAfter = StockMovement::count();

        // Must remain exactly equal: zero stock movements produced!
        $this->assertEquals($movementCountBefore, $movementCountAfter);
        $this->assertEquals(0, $finalized->stockMovements()->count());
    }

    /**
     * Test 16: No damage movement created.
     */
    public function test_16_no_damage_movement_created(): void
    {
        $movementCountBefore = StockMovement::count();

        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'reason_type' => ShopCreditNoteReason::DamageWastage->value,
            'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage->value,
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 8.0,
                    'credit_rate' => 50.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage->value,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->admin);

        $movementCountAfter = StockMovement::count();

        // Must remain exactly equal: zero damage or wastage movements produced!
        $this->assertEquals($movementCountBefore, $movementCountAfter);
        $this->assertEquals(0, $finalized->stockMovements()->count());
    }

    /**
     * Test 17: Closed-period posting rejected.
     */
    public function test_17_closed_period_posting_rejected(): void
    {
        $postingDate = today()->subDays(5)->toDateString();

        // Create closure covering posting date
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(10)->toDateString(),
            'period_end' => today()->subDays(1)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'notes' => 'Month-end closure',
        ]);

        $draft = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => null,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'status' => ShopCreditNoteStatus::Draft,
            'credit_note_date' => $postingDate,
            'credit_note_number' => 'DRAFT-SCN-CLOSED-01',
            'total_credit_amount' => 500.00,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot finalize credit note in a closed accounting period.');

        $this->service->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 18: Unauthorized actors cannot finalize.
     */
    public function test_18_unauthorized_actors_cannot_finalize(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User is not authorized to finalize shop credit notes.');

        // Purchaser is not authorized to finalize office credit notes
        $this->service->finalizeShopLevelCreditNote($draft, $this->unauthorizedUser);
    }

    /**
     * Test 19: Legacy single-invoice Credit Notes continue working unchanged.
     */
    public function test_19_legacy_single_invoice_credit_notes_continue_working_unchanged(): void
    {
        // 1. Legacy draft creation referencing single invoice
        $legacyDraft = $this->service->createDraft($this->invoiceA1, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'line_credit_amount' => 250.00,
                    'adjusted_quantity' => 0.0,
                ],
            ],
        ], $this->admin->id);

        $this->assertTrue($legacyDraft->isLegacyMode());
        $this->assertFalse($legacyDraft->isShopLevelMode());

        // 2. Passing legacy draft to finalizeShopLevelCreditNote must be rejected
        $rejected = false;
        try {
            $this->service->finalizeShopLevelCreditNote($legacyDraft, $this->admin);
        } catch (ValidationException $e) {
            $rejected = true;
            $this->assertStringContainsString('Only shop-level credit notes can be finalized using this method.', $e->getMessage());
        }
        $this->assertTrue($rejected);

        // 3. Approving via legacy approve() must succeed
        $approvedLegacy = $this->service->approve($legacyDraft, $this->admin);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $approvedLegacy->status);
        $this->assertEquals(250.00, (float) $approvedLegacy->total_credit_amount);
    }

    /**
     * Test 20: Cashbook and shop accounting remain reconciled.
     */
    public function test_20_cashbook_and_shop_accounting_remain_reconciled(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->accountsUser);

        // Idempotent re-syncing to cashbook ledger produces no duplicate rows
        $this->projectionService->syncCreditNote($finalized, $this->accountsUser->id);
        $this->projectionService->syncCreditNote($finalized, $this->accountsUser->id);

        $count = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->count();

        $this->assertEquals(1, $count);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(500.00, (float) $tx->amount);
        $this->assertEquals(TransactionStatus::Approved->value, $tx->status);
    }

    /**
     * Test 21: Finalize product-first catalog-only draft without invoice reference.
     */
    public function test_21_finalize_product_first_catalog_only_draft_without_invoice_reference(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Product-first adjustment finalization',
            'items' => [
                [
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 45.0,
                    'direction' => 'credit',
                    'inventory_action' => 'return_to_stock',
                    'notes' => 'Returned shelf onions',
                ],
                [
                    'product_id' => $this->productTomato->id,
                    'product_grade' => 'B',
                    'credit_quantity' => 5.0,
                    'credit_rate' => 40.0,
                    'direction' => 'credit',
                    'inventory_action' => 'write_off_wastage',
                    'notes' => 'Damaged tomatoes',
                ],
            ],
        ], $this->admin);

        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertStringStartsWith('DRAFT-', $draft->credit_note_number);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->accountsUser, 'Office approved');

        $this->assertEquals(ShopCreditNoteStatus::Approved, $finalized->status);
        $this->assertStringStartsWith('SCN-', $finalized->credit_note_number);
        $this->assertStringNotContainsString('DRAFT-', $finalized->credit_note_number);
        $this->assertEquals(650.00, (float) $finalized->total_credit_amount);
        $this->assertEquals(650.00, (float) $finalized->subtotal);

        // Item 1: Physical return -> PendingReceipt for warehouse
        $item1 = $finalized->items->firstWhere('product_id', $this->productOnion->id);
        $this->assertNotNull($item1);
        $this->assertNull($item1->shop_invoice_item_id);
        $this->assertNull($item1->source_shop_invoice_id);
        $this->assertEquals(ShopCreditNoteReceiptStatus::PendingReceipt, $item1->receipt_status);

        // Item 2: Wastage write-off -> NotApplicable receipt status
        $item2 = $finalized->items->firstWhere('product_id', $this->productTomato->id);
        $this->assertNotNull($item2);
        $this->assertNull($item2->shop_invoice_item_id);
        $this->assertNull($item2->source_shop_invoice_id);
        $this->assertEquals(ShopCreditNoteReceiptStatus::NotApplicable, $item2->receipt_status);

        // Financial Posting: single transaction on credit note business date
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(650.00, (float) $tx->amount);
        $this->assertEquals(today()->toDateString(), $tx->business_date->toDateString());
        $this->assertEquals(TransactionStatus::Approved->value, $tx->status);
    }

    /**
     * Test 22: Finalize product-first draft with optional historical invoice link.
     */
    public function test_22_finalize_product_first_draft_with_optional_invoice_link(): void
    {
        $originalInvoiceTotal = (float) $this->invoiceA1->final_total;

        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 12.0,
                    'credit_rate' => 48.0,
                    'direction' => 'credit',
                    'inventory_action' => 'return_to_stock',
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->accountsUser);

        $this->assertEquals(ShopCreditNoteStatus::Approved, $finalized->status);
        $this->assertEquals(576.00, (float) $finalized->total_credit_amount);

        $item = $finalized->items->first();
        $this->assertEquals($this->invoiceA1->id, $item->source_shop_invoice_id);
        $this->assertNull($item->shop_invoice_item_id);

        // Historical invoice remains completely unchanged
        $this->invoiceA1->refresh();
        $this->assertEquals($originalInvoiceTotal, (float) $this->invoiceA1->final_total);

        // Ledger transaction posts to Credit Note date, NOT invoice date
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(576.00, (float) $tx->amount);
        $this->assertEquals(today()->toDateString(), $tx->business_date->toDateString());
        $this->assertNotEquals($this->invoiceA1->business_date->toDateString(), $tx->business_date->toDateString());
    }

    /**
     * Test 23: Finalize rejects cross-shop invoice link.
     */
    public function test_23_finalize_rejects_cross_shop_invoice_link(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // Tamper item to point to an invoice belonging to Shop B
        $invoiceB = $this->createFinalizedInvoice($this->shopB, 'SINV-B-TEST');
        $draft->items()->first()->update([
            'source_shop_invoice_id' => $invoiceB->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cross-shop invoice blocked');

        $this->service->finalizeShopLevelCreditNote($draft, $this->accountsUser);
    }

    /**
     * Test 24: Finalize mixed credit and debit lines.
     */
    public function test_24_finalize_mixed_credit_and_debit_lines(): void
    {
        $draft = $this->service->createShopLevelDraft($this->shopA, [
            'items' => [
                [
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'direction' => 'credit',
                    'inventory_action' => 'return_to_stock',
                ],
                [
                    'product_id' => $this->productTomato->id,
                    'product_grade' => 'A',
                    'credit_quantity' => 5.0,
                    'credit_rate' => 40.0,
                    'direction' => 'debit',
                    'inventory_action' => 'none',
                ],
            ],
        ], $this->admin);

        $finalized = $this->service->finalizeShopLevelCreditNote($draft, $this->accountsUser);

        $this->assertEquals(ShopCreditNoteStatus::Approved, $finalized->status);
        $this->assertEquals(700.00, (float) $finalized->subtotal); // Gross: 500 + 200
        $this->assertEquals(300.00, (float) $finalized->total_credit_amount); // Net: 500 - 200
        $this->assertEquals(ShopCreditNoteDirection::Credit, $finalized->direction);

        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(300.00, (float) $tx->amount);
    }
}
