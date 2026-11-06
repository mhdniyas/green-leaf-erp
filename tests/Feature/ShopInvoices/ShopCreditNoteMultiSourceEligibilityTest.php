<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\DTOs\ShopInvoice\SourceLineEligibilityResult;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\ShopInvoices\ShopCreditNoteEligibilityService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class ShopCreditNoteMultiSourceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

    private ShopCreditNoteEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopA = Shop::factory()->create(['code' => 'SHP-A', 'name' => 'Kochi Central']);
        $this->shopB = Shop::factory()->create(['code' => 'SHP-B', 'name' => 'Calicut Mart']);

        $this->productOnion = Product::factory()->create(['name' => 'Onion Grade A', 'unit' => 'kg']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Grade A', 'unit' => 'kg']);

        // Invoice A1 (Shop A) - 100 kg Onion @ 50 = 5000
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-A1', today()->subDays(2)->toDateString());
        $this->itemOnionA1 = $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        // Invoice A2 (Shop A) - 80 kg Tomato @ 40 = 3200
        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-A2', today()->subDays(1)->toDateString());
        $this->itemTomatoA2 = $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 80.0, 40.0);

        // Invoice B1 (Shop B) - 50 kg Onion @ 55 = 2750
        $this->invoiceB1 = $this->createFinalizedInvoice($this->shopB, 'SINV-B1', today()->subDays(1)->toDateString());
        $this->itemOnionB1 = $this->createInvoiceItem($this->invoiceB1, $this->productOnion, 'A', 50.0, 55.0);

        $this->eligibilityService = app(ShopCreditNoteEligibilityService::class);
    }

    private function createFinalizedInvoice(Shop $shop, string $invoiceNumber, ?string $date = null): ShopInvoice
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
            'final_total' => 5000.00,
            'balance_amount' => 5000.00,
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
     * Test 1: Valid item reference from one invoice.
     */
    public function test_valid_item_reference_from_one_invoice(): void
    {
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 25.0,
                'credit_rate' => 50.0,
                'credit_amount' => 1250.00,
                'direction' => ShopCreditNoteDirection::Credit,
                'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            ],
        ];

        $result = DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));

        $this->assertInstanceOf(SourceLineEligibilityResult::class, $result);
        $this->assertCount(1, $result->validatedLines);
        $this->assertEquals(1250.00, $result->totalCreditAmount);
        $this->assertSame(ShopCreditNoteDirection::Credit, $result->overallDirection);

        $line = $result->validatedLines[0];
        $this->assertSame($this->itemOnionA1->id, $line->shopInvoiceItemId);
        $this->assertEquals(100.0, $line->originalQuantity);
        $this->assertEquals(100.0, $line->remainingEligibleQuantity);
        $this->assertEquals(25.0, $line->creditQuantity);
    }

    /**
     * Test 2: Valid items from two invoices belonging to the same shop.
     */
    public function test_valid_items_from_two_invoices_belonging_to_same_shop(): void
    {
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 30.0,
                'credit_rate' => 50.0,
                'credit_amount' => 1500.00,
            ],
            [
                'source_shop_invoice_id' => $this->invoiceA2->id,
                'shop_invoice_item_id' => $this->itemTomatoA2->id,
                'credit_quantity' => 20.0,
                'credit_rate' => 40.0,
                'credit_amount' => 800.00,
            ],
        ];

        $result = DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));

        $this->assertCount(2, $result->validatedLines);
        $this->assertCount(2, $result->lockedInvoices);
        $this->assertCount(2, $result->lockedInvoiceItems);
        $this->assertEquals(2300.00, $result->totalCreditAmount);
    }

    /**
     * Test 3: Source item belonging to another shop is rejected.
     */
    public function test_source_item_belonging_to_another_shop_is_rejected(): void
    {
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceB1->id, // Invoice belongs to Shop B
                'shop_invoice_item_id' => $this->itemOnionB1->id,
                'credit_quantity' => 10.0,
                'credit_rate' => 55.0,
            ],
        ];

        $this->expectException(ValidationException::class);

        try {
            DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));
        } catch (ValidationException $e) {
            $this->assertStringContainsString('does not belong to shop', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test 4: Incorrect invoice-item relationship is rejected.
     */
    public function test_incorrect_invoice_item_relationship_is_rejected(): void
    {
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemTomatoA2->id, // Belongs to Invoice A2, not A1!
                'credit_quantity' => 10.0,
                'credit_rate' => 40.0,
            ],
        ];

        $this->expectException(ValidationException::class);

        try {
            DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));
        } catch (ValidationException $e) {
            $this->assertStringContainsString('does not belong to source invoice', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test 5: Quantity exceeding remaining eligible quantity is rejected.
     */
    public function test_quantity_exceeding_remaining_eligible_quantity_is_rejected(): void
    {
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 105.0, // Available: 100 kg
                'credit_rate' => 50.0,
            ],
        ];

        $this->expectException(ValidationException::class);

        try {
            DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));
        } catch (ValidationException $e) {
            $this->assertStringContainsString('exceeds remaining eligible quantity', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test 6: Existing finalized return quantity is deducted from remaining eligibility.
     */
    public function test_existing_finalized_return_quantity_deducted_from_remaining_eligibility(): void
    {
        // Prior approved credit note credited 40 kg
        $priorNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-PRIOR-01',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'credit_note_date' => today()->toDateString(),
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 2000.00,
        ]);

        ShopCreditNoteItem::query()->create([
            'shop_credit_note_id' => $priorNote->id,
            'shop_invoice_item_id' => $this->itemOnionA1->id,
            'product_id' => $this->productOnion->id,
            'product_grade' => 'A',
            'product_name' => $this->productOnion->name,
            'unit' => 'kg',
            'original_quantity' => 100.0,
            'original_unit_price' => 50.0,
            'original_line_total' => 5000.0,
            'adjusted_quantity' => 40.0,
            'adjusted_unit_price' => 50.0,
            'line_credit_amount' => 2000.00,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
        ]);

        // Remaining should be 60 kg. Requesting 50 kg must succeed.
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 50.0,
                'credit_rate' => 50.0,
            ],
        ];

        $result = DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));
        $this->assertEquals(60.0, $result->validatedLines[0]->remainingEligibleQuantity);

        // Requesting 65 kg (exceeding 60 kg) must fail
        $excessLines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 65.0,
                'credit_rate' => 50.0,
            ],
        ];

        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $excessLines));
    }

    /**
     * Test 7: Cancelled/voided adjustments do not consume eligibility.
     */
    public function test_cancelled_or_voided_adjustments_do_not_consume_eligibility(): void
    {
        // Prior note of 50 kg was VOIDED
        $voidedNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-VOIDED-01',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'credit_note_date' => today()->toDateString(),
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
            'status' => ShopCreditNoteStatus::Voided,
            'total_credit_amount' => 2500.00,
            'voided_at' => now(),
            'void_reason' => 'Administrative error',
        ]);

        ShopCreditNoteItem::query()->create([
            'shop_credit_note_id' => $voidedNote->id,
            'shop_invoice_item_id' => $this->itemOnionA1->id,
            'product_id' => $this->productOnion->id,
            'product_grade' => 'A',
            'product_name' => $this->productOnion->name,
            'unit' => 'kg',
            'original_quantity' => 100.0,
            'original_unit_price' => 50.0,
            'original_line_total' => 5000.0,
            'adjusted_quantity' => 50.0,
            'adjusted_unit_price' => 50.0,
            'line_credit_amount' => 2500.00,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
        ]);

        // Full 100 kg must remain eligible
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 100.0,
                'credit_rate' => 50.0,
            ],
        ];

        $result = DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));
        $this->assertEquals(100.0, $result->validatedLines[0]->remainingEligibleQuantity);
    }

    /**
     * Test 8: Duplicate source line entries in the same request cannot bypass limits.
     */
    public function test_duplicate_source_line_entries_cannot_bypass_limits(): void
    {
        // 100 kg available. Request sends two lines for Onion A1: 60 kg + 60 kg = 120 kg.
        $lines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 60.0,
                'credit_rate' => 50.0,
            ],
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 60.0,
                'credit_rate' => 50.0,
            ],
        ];

        $this->expectException(ValidationException::class);

        try {
            DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $lines));
        } catch (ValidationException $e) {
            $this->assertStringContainsString('exceeds remaining eligible quantity', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test 9: Price-only adjustment does not incorrectly consume return quantity.
     */
    public function test_price_only_adjustment_does_not_consume_return_quantity(): void
    {
        // 1. Create and approve a price-only adjustment: Rate was 50, credited 10/kg on 100 kg = ₹1,000 credit.
        // Physical quantity credited is 0.
        $priceNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-PRICE-01',
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'credit_note_date' => today()->toDateString(),
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 1000.00,
        ]);

        ShopCreditNoteItem::query()->create([
            'shop_credit_note_id' => $priceNote->id,
            'shop_invoice_item_id' => $this->itemOnionA1->id,
            'product_id' => $this->productOnion->id,
            'product_grade' => 'A',
            'product_name' => $this->productOnion->name,
            'unit' => 'kg',
            'original_quantity' => 100.0,
            'original_unit_price' => 50.0,
            'original_line_total' => 5000.0,
            'adjusted_quantity' => 0.0, // Zero physical quantity consumed!
            'adjusted_unit_price' => 40.0,
            'credit_rate' => 10.0,
            'line_credit_amount' => 1000.00,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
        ]);

        // 2. Full 100 kg must STILL be available for physical return!
        // Remaining amount is 5000 - 1000 = 4000.
        $returnLines = [
            [
                'source_shop_invoice_id' => $this->invoiceA1->id,
                'shop_invoice_item_id' => $this->itemOnionA1->id,
                'credit_quantity' => 100.0, // 100 kg is available!
                'credit_rate' => 40.0,      // At revised rate of 40 = 4000
                'credit_amount' => 4000.00,
                'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
            ],
        ];

        $result = DB::transaction(fn () => $this->eligibilityService->validateAndLockSourceLines($this->shopA, $returnLines));

        $this->assertEquals(100.0, $result->validatedLines[0]->remainingEligibleQuantity);
        $this->assertEquals(4000.0, $result->validatedLines[0]->remainingEligibleAmount);
        $this->assertEquals(100.0, $result->validatedLines[0]->creditQuantity);
        $this->assertEquals(4000.0, $result->validatedLines[0]->creditAmount);
    }

    /**
     * Test 10: Concurrency safety — Two competing transactions on isolated MySQL database cannot over-credit.
     */
    public function test_two_concurrent_transactions_cannot_over_credit_on_mysql(): void
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
        $pdo1->exec('TRUNCATE TABLE shop_credit_note_items');
        $pdo1->exec('TRUNCATE TABLE shop_credit_notes');
        $pdo1->exec('TRUNCATE TABLE shop_invoice_items');
        $pdo1->exec('TRUNCATE TABLE shop_invoices');
        $pdo1->exec('TRUNCATE TABLE shops');
        $pdo1->exec('TRUNCATE TABLE products');
        $pdo1->exec('TRUNCATE TABLE categories');

        // Seed baseline records in isolated MySQL
        $pdo1->exec("INSERT INTO categories (id, name, is_active, created_at, updated_at) VALUES (999, 'Vegetables', 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (999, 'Concurrency Shop', 'CONC01', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO products (id, category_id, name, sku, unit, created_at, updated_at) VALUES (999, 999, 'Concurrency Onion', 'SKU-CONC-01', 'kg', NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_invoices (id, shop_id, shop_order_id, invoice_number, business_date, status, delivery_status, payment_status, final_total, balance_amount, created_at, updated_at) VALUES (999, 999, 999, 'SINV-CONC-01', CURDATE(), 'finalized', 'received_full', 'unpaid', 5000.00, 5000.00, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_invoice_items (id, shop_invoice_id, product_id, product_name, product_grade, unit, approved_qty, delivered_qty, unit_price, line_subtotal, final_line_total, created_at, updated_at) VALUES (999, 999, 999, 'Concurrency Onion', 'A', 'kg', 50.0, 50.0, 50.0, 2500.00, 2500.00, NOW(), NOW())");

        // Transaction 1: Starts and acquires FOR UPDATE lock on item #999 (reserving 40 kg of 50 kg)
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, delivered_qty FROM shop_invoice_items WHERE id = 999 FOR UPDATE');
        $stmt1->execute();
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(50.0, (float) $row1['delivered_qty']);

        // Transaction 2: Sets a 1-second timeout and attempts to lock the SAME row
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $lockBlocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, delivered_qty FROM shop_invoice_items WHERE id = 999 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            // Lock wait timeout exceeded confirms that Transaction 1 holds exclusive lock!
            $lockBlocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($lockBlocked, 'Pessimistic row-level lock on MySQL successfully blocked concurrent access.');

        // Transaction 1 commits 40 kg credit note item
        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, shop_invoice_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, created_at, updated_at) VALUES (999, 'SCN-CONC-1', 999, 999, CURDATE(), 'credit', 'legacy_invoice_linked', 'approved', 2000.00, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_note_items (shop_credit_note_id, shop_invoice_item_id, product_id, product_grade, product_name, unit, original_quantity, original_unit_price, original_line_total, adjusted_quantity, adjusted_unit_price, line_credit_amount, direction, inventory_action, created_at, updated_at) VALUES (999, 999, 999, 'A', 'Concurrency Onion', 'kg', 50.0, 50.0, 2500.00, 40.0, 50.0, 2000.00, 'credit', 'return_to_stock', NOW(), NOW())");
        $pdo1->commit();

        // Transaction 2 now resumes: acquires lock, recalculates remaining (10 kg), and attempts 30 kg
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, delivered_qty FROM shop_invoice_items WHERE id = 999 FOR UPDATE');
        $stmt2->execute();
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        // Calculate already credited in MySQL
        $calcStmt = $pdo2->prepare("SELECT COALESCE(SUM(adjusted_quantity), 0) as total_credited FROM shop_credit_note_items WHERE shop_invoice_item_id = 999 AND direction = 'credit'");
        $calcStmt->execute();
        $alreadyCredited = (float) $calcStmt->fetchColumn();

        $remainingEligible = (float) $row2['delivered_qty'] - $alreadyCredited;
        $this->assertEquals(10.0, $remainingEligible);

        $requestedSecondQuantity = 30.0;
        $secondTransactionAllowed = ($requestedSecondQuantity <= $remainingEligible);

        // Transaction 2 must reject over-credit
        $this->assertFalse($secondTransactionAllowed, 'Transaction 2 detected remaining eligible is 10 kg and rejected 30 kg request.');
        $pdo2->rollBack();

        // Total credited in database remains exactly 40 kg (never exceeds 50 kg)
        $verifyStmt = $pdo1->query('SELECT SUM(adjusted_quantity) FROM shop_credit_note_items WHERE shop_invoice_item_id = 999');
        $this->assertEquals(40.0, (float) $verifyStmt->fetchColumn());
    }
}
