<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeMapDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    private CompanyAccount $bankHdfc;

    private CompanyAccount $bankKotak;

    private User $purchaser;

    private Supplier $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->bankHdfc = CompanyAccount::create([
            'name' => 'HDFC BANK',
            'bank_name' => 'HDFC BANK',
            'account_type' => 'bank',
            'opening_balance' => 500000.00,
            'current_balance' => 500000.00,
            'enabled' => true,
        ]);

        $this->bankKotak = CompanyAccount::create([
            'name' => 'KOTAK BANK',
            'bank_name' => 'KOTAK BANK',
            'account_type' => 'bank',
            'opening_balance' => 200000.00,
            'current_balance' => 200000.00,
            'enabled' => true,
        ]);

        $this->purchaser = User::factory()->create(['name' => 'Purchaser Ashraf']);
        $this->purchaser->assignRole('purchaser');

        $this->vendor = Supplier::factory()->create(['name' => 'Banana Annan']);
    }

    public function test_tree_service_builds_valid_canvas_map_nodes_and_edges(): void
    {
        // 1. Company funding to purchaser: 300,000
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-02',
            'type' => 'in',
            'amount' => 300000.00,
            'company_account_id' => $this->bankHdfc->id,
            'description' => 'Mandi cash advance',
        ]);

        // 2. Purchaser cash purchases: 120,000 to Banana Annan
        $inv = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->vendor->id,
            'amount' => 120000.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Approved,
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-03',
            'type' => 'out',
            'amount' => 120000.00,
            'purchase_invoice_id' => $inv->id,
            'description' => 'Morning banana purchase',
        ]);

        // 3. Purchaser return to company: 20,000
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'business_date' => '2026-09-05',
            'type' => 'out',
            'amount' => 20000.00,
            'company_account_id' => $this->bankHdfc->id,
            'description' => 'Return unused cash advance',
        ]);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09');

        $this->assertArrayHasKey('map_data', $result);
        $mapData = $result['map_data'];

        $this->assertArrayHasKey('nodes', $mapData);
        $this->assertArrayHasKey('edges', $mapData);

        $nodes = collect($mapData['nodes']);
        $edges = collect($mapData['edges']);

        // Assert Level 0 Root Node exists
        $rootNode = $nodes->firstWhere('id', 'root');
        $this->assertNotNull($rootNode);
        $this->assertSame('GREEN LEAF / MAIN COMPANY', $rootNode['label']);

        // Assert Level 1 Branch Nodes exist
        $this->assertNotNull($nodes->firstWhere('id', 'branch_banks'));
        $this->assertNotNull($nodes->firstWhere('id', 'branch_purchasers'));

        // Assert Level 2 Purchaser Node exists with correct name and holding
        $pNode = $nodes->firstWhere('id', "purchaser_{$this->purchaser->id}");
        $this->assertNotNull($pNode);
        $this->assertSame('Purchaser Ashraf', $pNode['label']);
        // Holding = 300,000 - 120,000 - 20,000 = 160,000
        $this->assertEquals(160000.0, $pNode['holding']);

        // Assert Edges
        // 1. High-level funding edge: branch_banks -> branch_purchasers
        $fundingLevel1 = $edges->firstWhere('id', 'edge_banks_purchasers');
        $this->assertNotNull($fundingLevel1);
        $this->assertEquals(300000.0, $fundingLevel1['amount']);

        // 2. High-level return edge: branch_purchasers -> branch_banks
        $returnLevel1 = $edges->firstWhere('id', 'edge_purchasers_banks_return');
        $this->assertNotNull($returnLevel1);
        $this->assertEquals(20000.0, $returnLevel1['amount']);

        // 3. High-level purchase edge: branch_purchasers -> branch_vendors
        $purchaseLevel1 = $edges->firstWhere('id', 'edge_purchasers_vendors');
        $this->assertNotNull($purchaseLevel1);
        $this->assertEquals(120000.0, $purchaseLevel1['amount']);

        // 4. Entity-level cross flow: purchaser -> vendor
        $entityBuyEdge = $edges->first(fn ($e) => str_starts_with($e['id'], "edge_purchaser_{$this->purchaser->id}_vendor_"));
        $this->assertNotNull($entityBuyEdge);
        $this->assertEquals(120000.0, $entityBuyEdge['amount']);
        $this->assertSame('Banana Annan', $entityBuyEdge['to_label']);
    }

    public function test_edge_drilldown_endpoint_returns_filtered_transaction_list(): void
    {
        // 1. Internal transfer: HDFC -> Kotak: 75,000
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankHdfc->id,
            'transaction_date' => '2026-09-04',
            'direction' => 'out',
            'amount' => 75000.00,
            'narration' => 'Inter-bank fund sweep',
            'counterpart_type' => CompanyAccount::class,
            'counterpart_id' => $this->bankKotak->id,
            'source' => 'manual',
        ]);
        CompanyAccountStatementEntry::create([
            'company_account_id' => $this->bankKotak->id,
            'transaction_date' => '2026-09-04',
            'direction' => 'in',
            'amount' => 75000.00,
            'narration' => 'Inter-bank fund sweep',
            'counterpart_type' => CompanyAccount::class,
            'counterpart_id' => $this->bankHdfc->id,
            'source' => 'manual',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.cash-flow-tree.edge-drilldown', [
                'month' => '2026-09',
                'from_id' => "bank_{$this->bankHdfc->id}",
                'to_id' => "bank_{$this->bankKotak->id}",
            ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $this->assertEquals(75000.0, (float) $response->json('total_amount'));
        $response->assertJsonCount(1, 'movements');
        $response->assertJsonPath('movements.0.movement_type', 'internal_bank_transfer');
    }
}
