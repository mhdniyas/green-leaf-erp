<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\JournalEntryData;
use App\DTOs\Inventory\WastageEntryData;
use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\DTOs\Sales\PaymentData;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Sales\SOStatus;
use App\Models\Account;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\JournalService;
use App\Services\Inventory\WastageService;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Services\Sales\SalesInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->service = app(JournalService::class);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'sales.invoice.create',
            'sales.payment.record',
            'purchasing.order.approve',
            'inventory.wastage.record',
        ]);
    }

    public function test_balanced_journal_entry_succeeds(): void
    {
        $cashAcc = Account::where('code', '1010')->first();
        $equityAcc = Account::where('code', '3100')->first();

        $data = new JournalEntryData(
            entryDate: now()->format('Y-m-d'),
            reference: 'CAP-01',
            description: 'Capital investment',
            lines: [
                ['account_id' => $cashAcc->id, 'type' => 'debit', 'amount' => 50000.00],
                ['account_id' => $equityAcc->id, 'type' => 'credit', 'amount' => 50000.00],
            ]
        );

        $entry = $this->service->createEntry($data, $this->user->id);

        $this->assertModelExists($entry);
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAcc->id,
            'type' => 'debit',
            'amount' => 50000.00,
        ]);
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $entry->id,
            'account_id' => $equityAcc->id,
            'type' => 'credit',
            'amount' => 50000.00,
        ]);
    }

    public function test_unbalanced_journal_entry_fails(): void
    {
        $cashAcc = Account::where('code', '1010')->first();
        $equityAcc = Account::where('code', '3100')->first();

        $data = new JournalEntryData(
            entryDate: now()->format('Y-m-d'),
            reference: 'CAP-01',
            description: 'Capital investment',
            lines: [
                ['account_id' => $cashAcc->id, 'type' => 'debit', 'amount' => 50000.00],
                ['account_id' => $equityAcc->id, 'type' => 'credit', 'amount' => 45000.00], // Unbalanced!
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unbalanced journal entry');

        $this->service->createEntry($data, $this->user->id);
    }

    public function test_sales_invoice_creation_posts_journal_entry(): void
    {
        $so = SalesOrder::factory()
            ->has(SalesOrderItem::factory()->count(1)->state(['quantity' => 10, 'unit_price' => 150]), 'items')
            ->create(['status' => SOStatus::Confirmed]);

        $invoice = app(SalesInvoiceService::class)->createFromOrder($so, $this->user->id);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => $invoice->invoice_number,
            'description' => "Invoice generated for Sales Order #{$so->so_number}",
        ]);

        $arAccount = Account::where('code', '1100')->first();
        $salesAccount = Account::where('code', '4100')->first();

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $arAccount->id,
            'type' => 'debit',
            'amount' => $invoice->amount,
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $salesAccount->id,
            'type' => 'credit',
            'amount' => $invoice->amount,
        ]);
    }

    public function test_sales_payment_posts_journal_entry(): void
    {
        $so = SalesOrder::factory()
            ->has(SalesOrderItem::factory()->count(1)->state(['quantity' => 10, 'unit_price' => 150]), 'items')
            ->create(['status' => SOStatus::Confirmed]);
        $invoice = app(SalesInvoiceService::class)->createFromOrder($so, $this->user->id);

        $paymentData = new PaymentData(
            amount: (float) $invoice->amount,
            paymentMethod: 'cash',
            reference: 'REF-001',
            notes: 'Paid fully',
            paidAt: now()->format('Y-m-d'),
        );

        app(SalesInvoiceService::class)->recordPayment($invoice, $paymentData, $this->user->id);

        $this->assertDatabaseHas('journal_entries', [
            'description' => "Payment received for Invoice #{$invoice->invoice_number}",
        ]);

        $cashAccount = Account::where('code', '1010')->first();
        $arAccount = Account::where('code', '1100')->first();

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $cashAccount->id,
            'type' => 'debit',
            'amount' => $invoice->amount,
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $arAccount->id,
            'type' => 'credit',
            'amount' => $invoice->amount,
        ]);
    }

    public function test_purchase_invoice_and_payment_posts_journal_entry(): void
    {
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        $grn = GoodsReceived::factory()->create(['purchase_order_id' => $po->id]);

        $invoiceData = new PurchaseInvoiceData(
            goodsReceivedId: $grn->id,
            supplierId: $supplier->id,
            invoiceNumber: 'PINV-999',
            amount: 7500.00,
            status: 'pending',
            notes: 'Purchase invoice',
        );

        $invoice = app(PurchaseInvoiceService::class)->create($invoiceData);

        // Created invoice should post COGS (5100) debit and AP (2100) credit
        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'PINV-999',
        ]);

        $cogsAccount = Account::where('code', '5100')->first();
        $apAccount = Account::where('code', '2100')->first();

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $cogsAccount->id,
            'type' => 'debit',
            'amount' => 7500.00,
        ]);

        // Pay the invoice
        $this->actingAs($this->user);
        app(PurchaseInvoiceService::class)->updateStatus($invoice, InvoiceStatus::Paid->value);

        // Paid status transition should post AP (2100) debit and Bank (1020) credit
        $bankAccount = Account::where('code', '1020')->first();

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $apAccount->id,
            'type' => 'debit',
            'amount' => 7500.00,
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $bankAccount->id,
            'type' => 'credit',
            'amount' => 7500.00,
        ]);
    }

    public function test_wastage_posts_journal_entry(): void
    {
        $product = Product::factory()->create();
        $data = new WastageEntryData(
            productId: $product->id,
            batchId: null,
            grade: 'A',
            quantity: 10.0,
            costPerKg: 15.0,
            reason: WastageReason::Rotten,
            wastageDate: now()->format('Y-m-d'),
            notes: 'Wastage notes',
        );

        $wastage = app(WastageService::class)->record($data, $this->user->id);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => "WASTE-{$wastage->id}",
        ]);

        $wastageAccount = Account::where('code', '5200')->first();
        $cogsAccount = Account::where('code', '5100')->first();

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $wastageAccount->id,
            'type' => 'debit',
            'amount' => 150.00, // 10 kg * 15.00
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'account_id' => $cogsAccount->id,
            'type' => 'credit',
            'amount' => 150.00,
        ]);
    }
}
