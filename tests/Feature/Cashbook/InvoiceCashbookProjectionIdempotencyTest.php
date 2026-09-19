<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCashbookProjectionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

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

        $this->shopOwner = User::factory()->create();
        $this->shopOwner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'Lulu Begur Veg Shop',
            'code' => 'AV_LULU_BEGUR',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopOwner->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_case_1_normal_invoice_projection(): void
    {
        $order = ShopOrder::query()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20260902-01',
            'business_date' => '2026-09-02',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260902-AV_LULU_BEGUR',
            'business_date' => '2026-09-02',
            'status' => 'approved',
            'subtotal' => 10000.0,
            'final_total' => 10000.0,
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $projectionService = app(InvoiceCashbookProjectionService::class);
        $tx = $projectionService->syncInvoice($invoice, $this->admin->id);

        $this->assertNotNull($tx);
        $this->assertEquals(10000.0, (float) $tx->amount);

        $activeCount = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-02')
            ->where('status', '!=', 'void')
            ->count();

        $this->assertEquals(1, $activeCount);
    }

    public function test_case_2_sync_twice_is_idempotent(): void
    {
        $order = ShopOrder::query()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20260902-02',
            'status' => 'approved',
            'business_date' => '2026-09-02',
            'created_by' => $this->admin->id,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260902-AV_LULU_BEGUR_2',
            'business_date' => '2026-09-02',
            'status' => 'approved',
            'subtotal' => 10000.0,
            'final_total' => 10000.0,
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $projectionService = app(InvoiceCashbookProjectionService::class);
        $projectionService->syncInvoice($invoice, $this->admin->id);

        // Sync again with updated total
        $invoice->update(['final_total' => 12500.0]);
        $projectionService->syncInvoice($invoice, $this->admin->id);

        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-02')
            ->where('status', '!=', 'void')
            ->get();

        $this->assertCount(1, $transactions);
        $this->assertEquals(12500.0, (float) $transactions->first()->amount);
    }

    public function test_case_3_legacy_unreferenced_matching_gl_bill_is_adopted_without_duplication(): void
    {
        $purchaseBillType = LedgerEntryType::where('code', 'purchase_bill')->firstOrFail();

        // Legacy unreferenced row exists
        $legacyTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_id' => $purchaseBillType->id,
            'amount' => 10000.0,
            'direction' => 'expense',
            'funding_source' => 'company',
            'affects_sales' => false,
            'affects_income' => false,
            'affects_expense' => true,
            'affects_pl' => true,
            'pl_delta' => -10000.0,
            'settlement_delta' => 0.0,
            'settlement_direction' => 'none',
            'petty_delta' => 0.0,
            'petty_direction' => 'none',
            'company_pending_delta' => 0.0,
            'company_pending_direction' => 'none',
            'status' => 'posted',
            'reference_type' => null,
            'reference_id' => null,
        ]);

        $order = ShopOrder::query()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20260902-03',
            'status' => 'approved',
            'business_date' => '2026-09-02',
            'created_by' => $this->admin->id,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260902-AV_LULU_BEGUR_3',
            'business_date' => '2026-09-02',
            'status' => 'approved',
            'subtotal' => 10000.0,
            'final_total' => 10000.0,
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $projectionService = app(InvoiceCashbookProjectionService::class);
        $projectionService->syncInvoice($invoice, $this->admin->id);

        $activeTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-02')
            ->where('status', '!=', 'void')
            ->get();

        // Exactly 1 active row remains (the legacy row was adopted)
        $this->assertCount(1, $activeTransactions);
        $this->assertEquals($legacyTx->id, $activeTransactions->first()->id);
        $this->assertEquals(ShopInvoice::class, $activeTransactions->first()->reference_type);
        $this->assertEquals((string) $invoice->id, (string) $activeTransactions->first()->reference_id);
    }

    public function test_case_4_manual_single_entry_for_purchase_bill_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.record-entry'), [
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_code' => 'purchase_bill',
            'amount' => 5000.0,
        ]);

        $response->assertStatus(422);

        $count = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-02')
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_case_5_historical_reporting_displays_gl_bill_exactly_once(): void
    {
        $order = ShopOrder::query()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20260902-05',
            'status' => 'approved',
            'business_date' => '2026-09-02',
            'created_by' => $this->admin->id,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260902-AV_LULU_BEGUR_5',
            'business_date' => '2026-09-02',
            'status' => 'approved',
            'subtotal' => 10000.0,
            'final_total' => 10000.0,
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        app(InvoiceCashbookProjectionService::class)->syncInvoice($invoice, $this->admin->id);

        $dailyLedgerService = app(DailyLedgerService::class);
        $snapshot = $dailyLedgerService->dailySummary((int) $this->shop->id, '2026-09-02');

        $this->assertEquals(10000.0, (float) $snapshot->total_expense);

        $response = $this->actingAs($this->shopOwner)->get(route('shop-owner.cashbook.show', [
            'date' => '2026-09-02',
        ]));

        $response->assertOk();
    }

    public function test_case_6_other_manual_expense_categories_work_normally(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.record-entry'), [
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-02',
            'entry_type_code' => 'vehicle',
            'amount' => 250.0,
            'funding_source' => 'company',
            'notes' => 'Vehicle fuel',
        ]);

        $response->assertOk();

        $vehicleTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-09-02')
            ->whereHas('entryType', fn ($q) => $q->where('code', 'vehicle'))
            ->first();

        $this->assertNotNull($vehicleTx);
        $this->assertEquals(250.0, (float) $vehicleTx->amount);
    }
}
