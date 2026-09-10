<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeUnallocatedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unallocated_and_manual_journals_are_captured_under_needs_review(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        // 1. Unlinked vendor purchase: 18,500
        $supplier = Supplier::factory()->create(['name' => 'Temp Supplier']);
        $inv = PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'amount' => 18500.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);
        $supplier->delete(); // Supplier deleted -> creates unlinked vendor purchase

        PurchaserCredit::create([
            'purchaser_id' => $purchaser->id,
            'business_date' => '2026-09-08',
            'type' => 'out',
            'amount' => 18500.00,
            'purchase_invoice_id' => $inv->id,
            'description' => 'Purchase with unlinked supplier',
        ]);

        // 2. Manual Journal Entry: 142,500
        $account = Account::firstOrCreate(
            ['code' => '9999'],
            ['name' => 'Suspense / Adjustment', 'type' => 'asset']
        );

        $journal = JournalEntry::create([
            'reference' => 'MJ-2026-001',
            'entry_date' => '2026-09-12',
            'description' => 'Manual accounting adjustment for legacy difference',
            'source_type' => null,
            'source_event' => 'manual_adjustment',
            'created_by' => $purchaser->id,
        ]);

        JournalTransaction::create([
            'journal_entry_id' => $journal->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'amount' => 142500.00,
        ]);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09');

        $reviewBranch = collect($result['tree']->children)->firstWhere('id', 'branch_review');
        $this->assertNotNull($reviewBranch);
        $this->assertSame('OTHER / NEEDS REVIEW', $reviewBranch->title);

        // Verify that the total amount in review contains both
        // 18,500 + 142,500 = 161,000
        $this->assertEquals(161000.0, $reviewBranch->closingBalance);

        // Verify sub-items
        $manualJournalsNode = collect($reviewBranch->children)->firstWhere('id', 'review_manual_journals');
        $this->assertNotNull($manualJournalsNode);
        $this->assertEquals(142500.0, $manualJournalsNode->closingBalance);

        $unlinkedVendorsNode = collect($reviewBranch->children)->firstWhere('id', 'review_unlinked_vendors');
        $this->assertNotNull($unlinkedVendorsNode);
        $this->assertEquals(18500.0, $unlinkedVendorsNode->closingBalance);
    }
}
