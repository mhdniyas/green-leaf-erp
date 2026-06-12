<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseGRNWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $operator;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->manager = User::factory()->create();
        $this->manager->assignRole('admin');

        // Warehouse operator with Create/Update permissions
        $this->operator = User::factory()->create();
        $this->operator->givePermissionTo([
            'purchasing.grn.view',
            'purchasing.grn.create',
        ]);

        $this->supplier = Supplier::factory()->create();
        $category = Category::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $category->id]);
    }

    public function test_enterprise_grn_workflow_recheck_correction_and_resubmission(): void
    {
        // 1. Create Purchase Order in Draft status
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Draft,
            'created_by' => $this->manager->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 100.000,
            'unit_price' => 12.5000,
        ]);

        // 2. Approve PO
        $response = $this->actingAs($this->manager)
            ->post(route('purchasing.orders.approve', $po));
        $response->assertRedirect(route('purchasing.orders.show', $po));
        $this->assertEquals(POStatus::Approved, $po->fresh()->status);

        // 3. Send PO to Supplier
        $response = $this->actingAs($this->manager)
            ->post(route('purchasing.orders.send', $po));
        $response->assertRedirect(route('purchasing.orders.show', $po));
        $this->assertEquals(POStatus::SentToSupplier, $po->fresh()->status);

        // 4. Warehouse Operator receives goods and creates GRN -> status approved
        $grnData = [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'transport_cost' => 50.00,
            'labour_cost' => 30.00,
            'notes' => 'Received first check',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 90.000, // variance of -10
                ],
            ],
        ];

        $response = $this->actingAs($this->operator)
            ->post(route('purchasing.grns.store'), $grnData);

        $grn = GoodsReceived::latest('id')->first();
        $response->assertRedirect(route('purchasing.grns.show', $grn));

        $this->assertEquals('approved', $grn->status);
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product->id,
            'total_kg' => 90.000,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'reference' => $grn->grn_number,
        ]);
        $this->assertEquals(POStatus::Closed, $po->fresh()->status);

        // 5. Admin sends the GRN for recheck
        $response = $this->actingAs($this->manager)
            ->post(route('purchasing.grns.recheck', $grn), [
                'remarks' => 'Shortage of 10kg needs verification from warehouse operator.',
            ]);

        $response->assertRedirect(route('purchasing.grns.show', $grn));
        $grn->refresh();

        $this->assertEquals('recheck_required', $grn->status);
        $this->assertEquals('Shortage of 10kg needs verification from warehouse operator.', $grn->rejection_remarks);
        $this->assertDatabaseMissing('journal_entries', [
            'reference' => $grn->grn_number,
        ]);

        // 6. Warehouse Operator corrects the GRN and resubmits
        $correctedGrnData = [
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'transport_cost' => 50.00,
            'labour_cost' => 30.00,
            'notes' => 'Corrected: verified 100kg received physically.',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 100.000, // corrected to match ordered qty
                ],
            ],
        ];

        // Access edit page first
        $response = $this->actingAs($this->operator)
            ->get(route('purchasing.grns.edit', $grn));
        $response->assertOk();

        // Update the GRN
        $response = $this->actingAs($this->operator)
            ->put(route('purchasing.grns.update', $grn), $correctedGrnData);
        $response->assertRedirect(route('purchasing.grns.show', $grn));

        $grn->refresh();
        $this->assertEquals('approved', $grn->status);
        $this->assertNull($grn->rejection_remarks);

        // 7. Resubmission recreates stock and finance using corrected quantity
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product->id,
            'total_kg' => 100.000,
            'transport_cost' => 50.00,
            'labour_cost' => 30.00,
            'status' => BatchStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => $grn->grn_number,
        ]);

        $journalEntry = JournalEntry::where('reference', $grn->grn_number)->first();
        $this->assertNotNull($journalEntry);

        // Assert double-entry transactions are balanced
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => Account::where('code', '1200')->first()->id,
            'type' => 'debit',
            'amount' => 1250.00,
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => Account::where('code', '2100')->first()->id,
            'type' => 'credit',
            'amount' => 1250.00,
        ]);

        $this->assertEquals(POStatus::Closed, $po->fresh()->status);
    }
}
