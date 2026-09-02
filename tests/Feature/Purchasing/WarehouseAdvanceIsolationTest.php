<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\GoodsReceivedService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseAdvanceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $warehouseUser;

    private User $purchaserUser;

    private Warehouse $warehouse;

    private Product $tomatoProduct;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');
        $this->warehouseUser->givePermissionTo([
            'inventory.stock.view',
            'inventory.product.view',
            'purchasing.order.view',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
        ]);

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);

        $this->warehouseUser->warehouses()->attach($this->warehouse->id);

        $this->tomatoProduct = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-001',
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'base_price' => 20.0,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Tomato Farm Supplier',
        ]);
    }

    public function test_legacy_advance_and_commercial_grn_backfill_logic(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-001',
            'status' => POStatus::Received,
            'order_date' => now()->toDateString(),
            'created_by' => $this->purchaserUser->id,
        ]);

        // 1. Legacy Advance GRN
        $advanceGrnId = DB::table('goods_received')->insertGetId([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'purchaser_cart_id' => null,
            'grn_number' => 'GRN-ADV-001',
            'bill_status' => 'bill_pending',
            'receipt_type' => null,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Legacy Commercial GRN (linked to PO)
        $commercialGrnId = DB::table('goods_received')->insertGetId([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => null,
            'grn_number' => 'GRN-COMM-001',
            'bill_status' => 'bill_available',
            'receipt_type' => null,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Ambiguous GRN (null PO, null cart, but bill_status = 'bill_available')
        $ambiguousGrnId = DB::table('goods_received')->insertGetId([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'purchaser_cart_id' => null,
            'grn_number' => 'GRN-AMBIG-001',
            'bill_status' => 'bill_available',
            'receipt_type' => null,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Execute Backfill Logic
        DB::table('goods_received')
            ->whereNull('purchase_order_id')
            ->whereNull('purchaser_cart_id')
            ->where('bill_status', 'bill_pending')
            ->update(['receipt_type' => 'warehouse_advance']);

        DB::table('goods_received')
            ->where(function ($query): void {
                $query->whereNotNull('purchase_order_id')
                    ->orWhereNotNull('purchaser_cart_id');
            })
            ->update(['receipt_type' => 'normal_purchase']);

        $advanceGrn = GoodsReceived::find($advanceGrnId);
        $commercialGrn = GoodsReceived::find($commercialGrnId);
        $ambiguousGrn = GoodsReceived::find($ambiguousGrnId);

        $this->assertSame('warehouse_advance', $advanceGrn->receipt_type);
        $this->assertTrue($advanceGrn->isWarehouseAdvance());
        $this->assertFalse($advanceGrn->isNormalPurchase());

        $this->assertSame('normal_purchase', $commercialGrn->receipt_type);
        $this->assertTrue($commercialGrn->isNormalPurchase());
        $this->assertFalse($commercialGrn->isWarehouseAdvance());

        $this->assertNull($ambiguousGrn->receipt_type);
    }

    public function test_advance_creation_sets_warehouse_advance_and_creates_zero_commercial_records(): void
    {
        $initialInvoiceCount = PurchaseInvoice::count();
        $initialPoCount = PurchaseOrder::count();
        $initialCartCount = PurchaserCart::count();

        Sanctum::actingAs($this->warehouseUser);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'bill_status' => 'bill_pending',
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->tomatoProduct->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                ],
            ],
            'notes' => 'Direct Advance from Truck',
        ]);

        $response->assertStatus(201);

        $grnId = $response->json('data.id');
        $grn = GoodsReceived::findOrFail($grnId);

        // 1. Explicit receipt_type check
        $this->assertSame('warehouse_advance', $grn->receipt_type);
        $this->assertTrue($grn->isWarehouseAdvance());
        $this->assertFalse($grn->isNormalPurchase());
        $this->assertNull($grn->purchase_order_id);
        $this->assertNull($grn->purchaser_cart_id);

        // 2. Strict commercial isolation check
        $this->assertSame($initialInvoiceCount, PurchaseInvoice::count(), 'Advance Receive must NOT create PurchaseInvoice.');
        $this->assertSame($initialPoCount, PurchaseOrder::count(), 'Advance Receive must NOT create PurchaseOrder.');
        $this->assertSame($initialCartCount, PurchaserCart::count(), 'Advance Receive must NOT create PurchaserCart.');

        // 3. StockBatch created for physical warehouse operations
        $this->assertCount(1, $grn->stockBatches);
        $this->assertEquals(100.0, (float) $grn->stockBatches->first()->total_kg);
    }

    public function test_normal_purchaser_cart_submission_sets_normal_purchase_receipt_type(): void
    {
        $cart = PurchaserCart::create([
            'user_id' => $this->purchaserUser->id,
            'supplier_id' => $this->supplier->id,
            'cart_number' => 'VC-20260828-TEST',
            'business_date' => now()->toDateString(),
            'status' => 'draft',
            'destination_shop_id' => null,
        ]);

        $cart->items()->create([
            'product_id' => $this->tomatoProduct->id,
            'grade' => 'A',
            'quantity' => 50.0,
            'unit_price' => 20.0,
            'line_total' => 1000.0,
        ]);

        $response = $this->actingAs($this->purchaserUser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => now()->toDateString(),
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Credit',
            'bill_number' => 'BILL-TEST-001',
            'items' => [
                (string) $cart->items->first()->id => [
                    'unit_price' => 20.0,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $cart->refresh();
        $this->assertSame('submitted', $cart->status);
        $this->assertNotNull($cart->purchase_order_id);
        $this->assertNotNull($cart->goods_received_id);
        $this->assertNotNull($cart->purchase_invoice_id);

        $commercialGrn = GoodsReceived::find($cart->goods_received_id);
        $this->assertSame('normal_purchase', $commercialGrn->receipt_type);
        $this->assertTrue($commercialGrn->isNormalPurchase());
        $this->assertFalse($commercialGrn->isWarehouseAdvance());
    }

    public function test_warehouse_advance_excluded_from_cashbook_bill_pending_report(): void
    {
        // 1. Create a normal purchase GRN that has bill_pending
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-002',
            'status' => POStatus::Received,
            'order_date' => now()->toDateString(),
            'created_by' => $this->purchaserUser->id,
        ]);
        $po->items()->create([
            'product_id' => $this->tomatoProduct->id,
            'quantity' => 10.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 200.0,
        ]);

        $normalPendingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-NORM-PEND-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);
        $normalPendingGrn->items()->create([
            'product_id' => $this->tomatoProduct->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // 2. Create a warehouse advance GRN
        $advanceGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-PEND-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'section' => 'bill_pending',
            'date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('GRN-NORM-PEND-01');
        $response->assertDontSee('GRN-ADV-PEND-01');
    }

    public function test_warehouse_advance_accessible_in_advance_candidates_and_open_advance_queries(): void
    {
        $advanceGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-CAND-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);

        $item = $advanceGrn->items()->create([
            'product_id' => $this->tomatoProduct->id,
            'received_qty' => 75.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        $advanceGrn->stockBatches()->create([
            'goods_received_item_id' => $item->id,
            'product_id' => $this->tomatoProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => 'BATCH-TEST-ADV-01',
            'total_kg' => 75.0,
            'available_kg' => 75.0,
            'cost_per_kg' => 20.0,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted->value,
            'received_at' => now()->toDateString(),
            'created_by' => $this->warehouseUser->id,
        ]);

        // 1. Scoped query check
        $advances = GoodsReceived::query()->warehouseAdvance()->get();
        $this->assertTrue($advances->contains('id', $advanceGrn->id));

        // 2. Advance candidates service check
        $service = app(AdvanceReceiveReconciliationService::class);
        $candidates = $service->getOpenAdvanceCandidatesForProduct($this->tomatoProduct->id, $this->warehouse->id);

        $this->assertNotEmpty($candidates);
        $candidateIds = array_column($candidates, 'advance_goods_received_id');
        $this->assertContains($advanceGrn->id, $candidateIds);
    }

    public function test_warehouse_advance_excluded_from_purchase_invoices_report(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-003',
            'status' => POStatus::Received,
            'order_date' => now()->toDateString(),
            'created_by' => $this->purchaserUser->id,
        ]);

        $commercialGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-COMM-003',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);

        $invoice = PurchaseInvoice::create([
            'goods_received_id' => $commercialGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-COMM-003',
            'amount' => 500.0,
            'status' => 'pending',
            'payment_method' => 'Credit',
            'created_at' => now(),
        ]);

        $advanceGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-003',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('purchasing.invoices.index', [
            'tab' => 'credit',
            'date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('INV-COMM-003');
        $response->assertDontSee('GRN-ADV-003');
    }

    public function test_goods_received_service_paginate_filters_by_receipt_type(): void
    {
        $advanceGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-PAGINATE-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-004',
            'status' => POStatus::Received,
            'order_date' => now()->toDateString(),
            'created_by' => $this->purchaserUser->id,
        ]);

        $commercialGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-COMM-PAGINATE-01',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->warehouseUser->id,
            'received_at' => now()->toDateString(),
        ]);

        $service = app(GoodsReceivedService::class);

        $advanceResults = $service->paginate(['receipt_type' => 'warehouse_advance'], 50);
        $commercialResults = $service->paginate(['receipt_type' => 'normal_purchase'], 50);

        $this->assertTrue($advanceResults->getCollection()->contains('id', $advanceGrn->id));
        $this->assertFalse($advanceResults->getCollection()->contains('id', $commercialGrn->id));

        $this->assertTrue($commercialResults->getCollection()->contains('id', $commercialGrn->id));
        $this->assertFalse($commercialResults->getCollection()->contains('id', $advanceGrn->id));
    }
}
