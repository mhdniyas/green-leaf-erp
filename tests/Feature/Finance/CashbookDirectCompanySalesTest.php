<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\DailyPriceApproval;
use App\Models\DirectCompanySale;
use App\Models\DirectCompanySaleItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderLoadoutState;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookDirectCompanySalesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private CompanyAccount $cashAccount;

    private CompanyAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $group = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $group->id, 'status' => 'active']);
        BusinessSetting::query()->updateOrCreate(['key' => 'default_direct_sale_shop_id'], ['value' => (string) $this->shop->id]);
        $this->cashAccount = CompanyAccount::query()->create(['name' => 'Company Cash', 'account_type' => 'cash', 'enabled' => true, 'is_default' => true]);
        $this->bankAccount = CompanyAccount::query()->create(['name' => 'Company Bank', 'account_type' => 'bank', 'enabled' => true]);
    }

    public function test_configured_direct_sales_shop_is_required(): void
    {
        BusinessSetting::query()->where('key', 'default_direct_sale_shop_id')->delete();
        $product = $this->pricedProduct('Tomato');

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload([$this->line($product)]))
            ->assertSessionHasErrors('default_direct_sale_shop_id');

        $this->assertSame(0, DirectCompanySale::query()->count());
        $this->assertSame(0, DirectCompanySaleItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Out)->count());
    }

    public function test_itemized_sale_deducts_each_product_from_its_default_warehouse(): void
    {
        $vegetableWarehouse = Warehouse::factory()->create(['name' => 'Vegetable Warehouse']);
        $fruitWarehouse = Warehouse::factory()->create(['name' => 'Fruit Warehouse']);
        $tomato = $this->pricedProduct('Tomato', $vegetableWarehouse, baseUnit: 'kg', price: 100);
        $apple = $this->pricedProduct('Apple', $fruitWarehouse, baseUnit: 'kg', boxConversion: 10, price: 50);
        $this->seedStock($tomato, $vegetableWarehouse, 100);
        $this->seedStock($apple, $fruitWarehouse, 100);

        $beforeTomato = $this->stockBalance($tomato, $vegetableWarehouse);
        $beforeApple = $this->stockBalance($apple, $fruitWarehouse);

        $sale = $this->postSale([
            $this->line($tomato, quantity: 1.5, unit: 'kg', unitRate: 100),
            $this->line($apple, quantity: 2, unit: 'box', unitRate: 500),
        ]);

        $this->assertSame(1, DirectCompanySale::query()->count());
        $this->assertSame(2, $sale->items()->count());
        $this->assertEquals(98.5, $this->stockBalance($tomato, $vegetableWarehouse));
        $this->assertEquals(80.0, $this->stockBalance($apple, $fruitWarehouse));
        $this->assertEquals($beforeTomato - 1.5, $this->stockBalance($tomato, $vegetableWarehouse));
        $this->assertEquals($beforeApple - 20.0, $this->stockBalance($apple, $fruitWarehouse));
        $this->assertDatabaseHas('direct_company_sale_items', ['product_id' => $tomato->id, 'warehouse_id' => $vegetableWarehouse->id, 'base_quantity' => 1.5]);
        $this->assertDatabaseHas('direct_company_sale_items', ['product_id' => $apple->id, 'warehouse_id' => $fruitWarehouse->id, 'base_quantity' => 20.0]);
        $this->assertSame(2, StockMovement::query()->where('type', StockMovementType::Out)->count());
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame('in', CompanyAccountStatementEntry::query()->firstOrFail()->direction);
        $this->assertFalse(CompanyAccountStatementEntry::query()->firstOrFail()->is_finalized);
        $this->assertSame(0, ShopOrder::query()->count());
        $this->assertSame(0, ShopInvoice::query()->count());
        $this->assertSame(0, ShopOrderLoadoutState::query()->count());
    }

    public function test_approved_shop_special_price_overrides_normal_price(): void
    {
        $product = $this->pricedProduct('Mushroom', price: 100);
        ShopDailyProductPrice::query()->create([
            'business_date' => '2026-08-22',
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'selling_price' => 80,
            'price_unit' => 'kg',
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $sale = $this->postSale([$this->line($product, quantity: 1, unit: 'kg', unitRate: 80)]);
        $item = $sale->items()->firstOrFail();

        $this->assertSame('special', $item->price_source);
        $this->assertEquals(80.0, (float) $item->unit_rate);
        $this->assertEquals(80.0, (float) $sale->amount);
    }

    public function test_forged_browser_price_is_rejected_before_inventory_changes(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Tomato', $warehouse, price: 100);
        $this->seedStock($product, $warehouse, 50);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload([$this->line($product, unitRate: 1)]))
            ->assertSessionHasErrors('items.0.unit_rate');

        $this->assertSame(0, DirectCompanySale::query()->count());
        $this->assertSame(50.0, $this->stockBalance($product, $warehouse));
    }

    public function test_missing_default_warehouse_blocks_whole_transaction(): void
    {
        $validWarehouse = Warehouse::factory()->create();
        $validProduct = $this->pricedProduct('Tomato', $validWarehouse);
        $missingWarehouseProduct = $this->pricedProduct('Mushroom');
        $missingWarehouseProduct->update(['default_warehouse_id' => null]);
        $this->seedStock($validProduct, $validWarehouse, 50);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload([
                $this->line($validProduct),
                $this->line($missingWarehouseProduct),
            ]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, DirectCompanySale::query()->count());
        $this->assertSame(0, DirectCompanySaleItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Out)->count());
        $this->assertSame(50.0, $this->stockBalance($validProduct, $validWarehouse));
    }

    public function test_invalid_unit_blocks_whole_transaction(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Tomato', $warehouse);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload([$this->line($product, unit: 'crate')]))
            ->assertSessionHasErrors('items.0.unit');

        $this->assertSame(0, DirectCompanySale::query()->count());
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Out)->count());
    }

    public function test_second_item_inventory_failure_rolls_back_first_item_and_sale(): void
    {
        $firstWarehouse = Warehouse::factory()->create();
        $secondWarehouse = Warehouse::factory()->create();
        $first = $this->pricedProduct('Tomato', $firstWarehouse);
        $second = $this->pricedProduct('Apple', $secondWarehouse);
        $this->seedStock($first, $firstWarehouse, 50);
        $this->seedStock($second, $secondWarehouse, 50);
        $realStockLedgerService = app(StockLedgerService::class);
        $mock = Mockery::mock(StockLedgerService::class);
        $mock->shouldReceive('consumeStockForProductAllowingNegative')
            ->once()
            ->ordered()
            ->andReturnUsing(fn (...$arguments): float => $realStockLedgerService->consumeStockForProductAllowingNegative(...$arguments));
        $mock->shouldReceive('consumeStockForProductAllowingNegative')
            ->once()
            ->ordered()
            ->andThrow(new RuntimeException('stock failure'));
        $this->app->instance(StockLedgerService::class, $mock);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin)->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload([
                $this->line($first),
                $this->line($second),
            ]));
            $this->fail('Expected stock failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('stock failure', $exception->getMessage());
        }

        $this->assertSame(0, DirectCompanySale::query()->count());
        $this->assertSame(0, DirectCompanySaleItem::query()->count());
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Out)->count());
        $this->assertSame(50.0, $this->stockBalance($first, $firstWarehouse));
    }

    public function test_double_submit_reuses_sale_and_deducts_inventory_once(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Tomato', $warehouse);
        $this->seedStock($product, $warehouse, 50);
        $payload = $this->payload([$this->line($product)], requestUuid: (string) Str::uuid());

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.direct-sales.store'), $payload)->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.direct-sales.store'), $payload)->assertRedirect();

        $this->assertSame(1, DirectCompanySale::query()->count());
        $this->assertSame(1, DirectCompanySaleItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Out)->count());
        $this->assertSame(49.0, $this->stockBalance($product, $warehouse));
    }

    public function test_negative_stock_behavior_is_preserved(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Tomato', $warehouse);

        $this->postSale([$this->line($product, quantity: 3)]);

        $movement = StockMovement::query()->where('type', StockMovementType::Out)->firstOrFail();
        $this->assertSame($warehouse->id, $movement->warehouse_id);
        $this->assertStringContainsString('negative stock allowed', (string) $movement->notes);
        $this->assertSame(-3.0, $this->stockBalance($product, $warehouse));
    }

    public function test_direct_sale_creates_one_pending_cashbook_receipt_then_finalizes_into_all_transactions(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Tomato', $warehouse, price: 120);
        $this->seedStock($product, $warehouse, 10);

        $sale = $this->postSale([$this->line($product, unitRate: 120)]);
        $sale->load(['journalEntry.transactions.account', 'cashbookMovement']);

        $this->assertSame($this->cashAccount->id, $sale->company_account_id);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('unreconciled', $sale->reconciliation_status);
        $this->assertSame(1, JournalEntry::query()->whereKey($sale->journal_entry_id)->count());
        $this->assertSame('1010', $sale->journalEntry->transactions->firstWhere('type', 'debit')->account->code);
        $this->assertSame('4100', $sale->journalEntry->transactions->firstWhere('type', 'credit')->account->code);
        $this->assertFalse($sale->cashbookMovement->is_finalized);
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'))->assertOk()->assertDontSee('Direct company sale');

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.reconciliation.match-journal', $sale->cashbookMovement->secureRouteKey()), [
            'journal_entry_id' => $sale->journal_entry_id,
            'cleared_amount' => $sale->amount,
        ])->assertRedirect();

        $this->assertTrue($sale->fresh()->is_finalized);
        $this->assertTrue($sale->cashbookMovement->fresh()->is_finalized);
        $this->actingAs($this->admin)->get(route('admin.cashbook.finance.journal'))->assertOk()->assertSee('Direct company sale');
    }

    public function test_bank_sale_requires_enabled_selected_bank_and_keeps_retry_idempotent(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Apple', $warehouse);
        $this->seedStock($product, $warehouse, 10);
        $payload = $this->payload([$this->line($product)], requestUuid: (string) Str::uuid(), paymentMethod: 'bank', companyAccountUuid: $this->bankAccount->public_uuid);

        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.direct-sales.store'), $payload)->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.direct-sales.store'), $payload)->assertRedirect();

        $sale = DirectCompanySale::query()->firstOrFail();
        $this->assertSame($this->bankAccount->id, $sale->company_account_id);
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame(1, CompanyAccountStatementEntry::query()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::Out)->count());

        $this->bankAccount->update(['enabled' => false]);
        $this->actingAs($this->admin)->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload([$this->line($product)], paymentMethod: 'bank', companyAccountUuid: $this->bankAccount->public_uuid))->assertSessionHasErrors('company_account_uuid');
    }

    public function test_month_history_and_uuid_bill_support_itemized_and_legacy_sales(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = $this->pricedProduct('Tomato', $warehouse);
        $this->seedStock($product, $warehouse, 10);
        $sale = $this->postSale([$this->line($product)]);

        $legacy = DirectCompanySale::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'business_date' => '2026-07-31',
            'amount' => 250,
            'payment_method' => 'bank',
            'company_account_id' => $this->bankAccount->id,
            'reference' => 'LEGACY-JULY',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.direct-sales', ['month' => '2026-08', 'search' => '']))
            ->assertOk()
            ->assertSee('DIRECT-SALE-'.$sale->id)
            ->assertDontSee('LEGACY-JULY');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.direct-sales.bill', $sale))
            ->assertOk()
            ->assertSee('DIRECT-SALE-'.$sale->id)
            ->assertSee('Tomato');

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.direct-sales.bill', $legacy))
            ->assertOk()
            ->assertSee('Legacy amount-only sale');
    }

    public function test_legacy_amount_only_direct_sale_remains_readable_and_unchanged(): void
    {
        $account = CompanyAccount::query()->create(['name' => 'Legacy Bank', 'account_type' => 'bank', 'enabled' => true]);
        $legacy = DirectCompanySale::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'business_date' => '2026-08-21',
            'customer_name' => 'Legacy Customer',
            'amount' => 250,
            'payment_method' => 'bank',
            'company_account_id' => $account->id,
            'reference' => 'LEGACY-1',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.direct-sales.show', $legacy))
            ->assertOk()
            ->assertSee('Legacy amount-only sale')
            ->assertSee('No item rows');

        $this->assertSame(0, DirectCompanySaleItem::query()->where('direct_company_sale_id', $legacy->id)->count());
        $this->assertSame('LEGACY-1', $legacy->fresh()->reference);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovementType::Out)->count());
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function postSale(array $items): DirectCompanySale
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.direct-sales.store'), $this->payload($items))
            ->assertRedirect();

        return DirectCompanySale::query()->latest('id')->firstOrFail();
    }

    private function pricedProduct(string $name, ?Warehouse $warehouse = null, string $baseUnit = 'kg', ?float $boxConversion = null, float $price = 100): Product
    {
        $warehouse ??= Warehouse::factory()->create();

        $product = Product::factory()->create([
            'name' => $name,
            'unit' => $baseUnit,
            'default_warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);

        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => $baseUnit,
            'label' => strtoupper($baseUnit),
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        if ($boxConversion !== null) {
            ProductUnit::query()->create([
                'product_id' => $product->id,
                'unit' => 'box',
                'label' => 'BOX',
                'conversion_to_base' => $boxConversion,
                'is_base' => false,
                'is_orderable' => true,
            ]);
        }

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-08-22',
            'purchase_price' => 50,
            'price_unit' => $baseUnit,
            'price_a' => $price,
            'price_b' => $price,
            'price_c' => $price,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        return $product->fresh(['orderUnits']) ?? $product;
    }

    private function seedStock(Product $product, Warehouse $warehouse, float $quantity): void
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->admin->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'sorted_at' => now(),
            'purchase_grade' => ProductGrade::GradeA,
        ]);

        StockMovement::query()->create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => $quantity,
            'cost_per_unit' => 10,
        ]);
    }

    private function stockBalance(Product $product, Warehouse $warehouse): float
    {
        $inTypes = [StockMovementType::In->value, StockMovementType::Adjustment->value];
        $outTypes = [StockMovementType::Out->value, StockMovementType::Wastage->value, StockMovementType::Sale->value];

        return (float) StockMovement::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?) THEN -quantity ELSE 0 END), 0) AS balance',
                [...$inTypes, ...$outTypes],
            )
            ->value('balance');
    }

    /**
     * @return array<string, mixed>
     */
    private function line(Product $product, float $quantity = 1, string $unit = 'kg', float $unitRate = 100): array
    {
        return [
            'product_uuid' => $product->public_uuid,
            'unit' => $unit,
            'quantity' => $quantity,
            'unit_rate' => $unitRate,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items, ?string $requestUuid = null, string $paymentMethod = 'cash', ?string $companyAccountUuid = null): array
    {
        return [
            'business_date' => '2026-08-22',
            'customer_name' => 'Counter Customer',
            'reference' => 'DIRECT-'.Str::random(6),
            'note' => 'Phase 1 sale',
            'request_uuid' => $requestUuid ?? (string) Str::uuid(),
            'payment_method' => $paymentMethod,
            'company_account_uuid' => $companyAccountUuid,
            'items' => $items,
        ];
    }
}
