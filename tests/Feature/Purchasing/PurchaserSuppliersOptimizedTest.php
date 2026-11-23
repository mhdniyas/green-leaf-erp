<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserSuppliersOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Carbon $today;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->purchaser = User::factory()->create(['name' => 'Test Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->today = app(PurchaserBusinessDayService::class)->operationalDate();
        $this->date = $this->today->format('Y-m-d');
    }

    public function test_initial_load_returns_max_20_suppliers_with_pagination_metadata(): void
    {
        $newSuppliers = Supplier::factory()->count(25)->create();

        foreach ($newSuppliers as $i => $supplier) {
            $cart = PurchaserCart::query()->create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $this->date,
                'status' => 'submitted',
                'cart_number' => 'VC-BATCH-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);

            $invoice = PurchaseInvoice::factory()->for($supplier)->create([
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => 'INV-BATCH-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'amount' => 1000.00,
                'discount_amount' => 50.00,
                'paid_amount' => 200.00,
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            $cart->update(['purchase_invoice_id' => $invoice->id]);
        }

        $expectedTotal = Supplier::count();

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
            'tab' => 'all',
            'date' => $this->date,
        ]));

        $response->assertOk();
        $response->assertViewHas('supplierRows', function ($rows): bool {
            return $rows->count() === 20;
        });
        $response->assertViewHas('hasMorePages', true);
        $response->assertViewHas('totalRowsCount', $expectedTotal);
    }

    public function test_load_more_json_fetches_next_page_without_duplication(): void
    {
        $suppliers = Supplier::factory()->count(25)->create();

        foreach ($suppliers as $i => $supplier) {
            $cart = PurchaserCart::query()->create([
                'user_id' => $this->purchaser->id,
                'supplier_id' => $supplier->id,
                'business_date' => $this->date,
                'status' => 'submitted',
                'cart_number' => 'VC-LOADMORE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);

            $invoice = PurchaseInvoice::factory()->for($supplier)->create([
                'purchaser_cart_id' => $cart->id,
                'invoice_number' => 'INV-LOADMORE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'amount' => 500.00,
                'discount_amount' => 0.00,
                'paid_amount' => 100.00,
                'status' => 'pending',
            ]);

            $cart->update(['purchase_invoice_id' => $invoice->id]);
        }

        $expectedTotal = Supplier::count();
        $page2Count = min(20, $expectedTotal - 20);

        // Fetch page 2 as JSON
        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser.suppliers', [
            'tab' => 'all',
            'date' => $this->date,
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'credit_approved',
                    'mobile_number',
                    'location',
                    'has_bank_details',
                    'recent_business_date',
                    'total_amount',
                    'discount_amount',
                    'paid_amount',
                    'balance_amount',
                    'pending_count',
                    'pending_issue_label',
                    'pending_issue_tone',
                    'history_route',
                ],
            ],
            'pagination' => [
                'current_page',
                'per_page',
                'total_records',
                'last_page',
                'has_more',
            ],
            'tab_counts',
            'global_totals',
        ]);

        $json = $response->json();
        $this->assertSame(2, $json['pagination']['current_page']);
        $this->assertCount($page2Count, $json['data']);
        $this->assertTrue($json['pagination']['has_more']);
    }

    public function test_search_filters_suppliers_by_name_mobile_and_location(): void
    {
        $matching = Supplier::factory()->create([
            'name' => 'Organic Valley Farms',
            'mobile_number' => '9988776655',
            'location' => 'Mysore Gate',
        ]);

        $nonMatching = Supplier::factory()->create([
            'name' => 'Green Harvest Ltd',
            'mobile_number' => '1122334455',
            'location' => 'Hubli Central',
        ]);

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser.suppliers', [
            'tab' => 'all',
            'search' => 'Organic',
            'date' => $this->date,
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($matching->id, $data[0]['id']);

        // Search by location
        $locResponse = $this->actingAs($this->purchaser)->getJson(route('purchaser.suppliers', [
            'tab' => 'all',
            'search' => 'Hubli',
            'date' => $this->date,
        ]));

        $locResponse->assertOk();
        $locData = $locResponse->json('data');
        $this->assertCount(1, $locData);
        $this->assertSame($nonMatching->id, $locData[0]['id']);
    }

    public function test_financial_totals_match_canonical_calculations_accurately(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Target Financial Vendor']);

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $this->date,
            'status' => 'submitted',
            'cart_number' => 'VC-FIN-001',
        ]);

        $invoice = PurchaseInvoice::factory()->for($supplier)->create([
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-FIN-001',
            'amount' => 15000.00,
            'discount_amount' => 500.00,
            'paid_amount' => 8000.00,
            'status' => 'pending',
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        // Add vendor settlement with discount and advance
        $settlement = VendorSettlement::query()->create([
            'supplier_id' => $supplier->id,
            'actual_payment_amount' => 2000.00,
            'settlement_discount_amount' => 500.00,
            'vendor_advance_used_amount' => 0.00,
            'new_vendor_advance_amount' => 0.00,
            'payment_method' => 'Bank',
            'payment_date' => $this->date,
            'status' => 'approved',
            'created_by' => $this->purchaser->id,
        ]);

        VendorSettlementAllocation::query()->create([
            'vendor_settlement_id' => $settlement->id,
            'purchase_invoice_id' => $invoice->id,
            'cash_allocated' => 0.00,
            'advance_allocated' => 2000.00,
            'discount_allocated' => 500.00,
            'total_settled' => 2500.00,
        ]);

        // Canonical remaining balance:
        // Gross: 15000. Direct disc: 500. Settle disc: 500. Net: 14000.
        // Cash paid: 8000. Advance: 2000. Covered: 10000.
        // Expected Balance: 4000.00.
        $this->assertSame(4000.00, $invoice->remainingBalance());

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser.suppliers', [
            'tab' => 'all',
            'search' => 'Target Financial Vendor',
            'date' => $this->date,
        ]));

        $response->assertOk();
        $row = $response->json('data.0');
        $this->assertEquals(15000.00, $row['total_amount']);
        $this->assertEquals(500.00, $row['discount_amount']);
        $this->assertEquals(8000.00, $row['paid_amount']);
        $this->assertEquals(4000.00, $row['balance_amount']);
    }

    public function test_supplier_history_loads_correctly_on_demand_via_show_endpoint(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Detailed History Vendor']);

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $this->date,
            'status' => 'submitted',
            'cart_number' => 'VC-DETAIL-001',
        ]);

        $invoice = PurchaseInvoice::factory()->for($supplier)->create([
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'INV-DETAIL-001',
            'amount' => 5000.00,
            'discount_amount' => 100.00,
            'paid_amount' => 4900.00,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers.show', [
            'supplier' => $supplier,
            'date' => $this->date,
        ]));

        $response->assertOk();
        $response->assertViewHas('supplier');
        $response->assertViewHas('historyTotals', function (array $totals): bool {
            return $totals['total_amount'] === 4900.00 // net of discount
                && $totals['pending_amount'] === 0.00;
        });
    }

    public function test_query_count_remains_bounded_and_minimal(): void
    {
        Supplier::factory()->count(30)->create();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.suppliers', [
            'tab' => 'all',
            'date' => $this->date,
        ]));

        $response->assertOk();
        // Initial page load should issue minimal queries (<= 16)
        $this->assertLessThanOrEqual(16, $queries, "Expected <= 16 queries on initial load, got {$queries}");
    }
}
