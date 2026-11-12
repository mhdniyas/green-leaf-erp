<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\ProductGrade;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditNote;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopCreditNoteStep1ProductPriceDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $accountsUser;

    private User $warehouseUser;

    private Shop $shopA;

    private Shop $shopB;

    private Warehouse $warehouse;

    private Product $productWithInvoice;

    private Product $productWithoutInvoice;

    private Product $inactiveProduct;

    private ShopInvoice $historicalInvoice;

    private ShopInvoiceItem $historicalInvoiceItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
        $this->superAdmin->givePermissionTo(['cashbook.access', 'cashbook.reports.view']);

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');
        $this->accountsUser->givePermissionTo(['cashbook.access', 'cashbook.reports.view']);

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');

        $priceGroupA = ShopPriceGroup::query()->create([
            'name' => 'A',
            'is_active' => true,
        ]);

        $priceGroupB = ShopPriceGroup::query()->create([
            'name' => 'B',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Central Warehouse',
            'code' => 'WH-CEN',
        ]);

        $this->shopA = Shop::factory()->create([
            'name' => 'Alpha Retail',
            'code' => 'SHP-ALP',
            'shop_price_group_id' => $priceGroupA->id,
        ]);

        $this->shopB = Shop::factory()->create([
            'name' => 'Beta Retail',
            'code' => 'SHP-BET',
            'shop_price_group_id' => $priceGroupB->id,
        ]);

        $this->productWithInvoice = Product::factory()->create([
            'name' => 'Fresh Red Onion',
            'sku' => 'VEG-ONI-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->productWithoutInvoice = Product::factory()->create([
            'name' => 'Green Cavendish Banana',
            'sku' => 'FRT-BAN-099',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->inactiveProduct = Product::factory()->create([
            'name' => 'Archived Seasonal Plum',
            'sku' => 'FRT-PLM-999',
            'unit' => 'kg',
            'is_active' => false,
        ]);

        $invoiceDate = today()->subDays(10)->toDateString();
        $order = ShopOrder::create([
            'shop_id' => $this->shopA->id,
            'state' => 'approved',
            'business_date' => $invoiceDate,
            'created_by' => $this->admin->id,
        ]);

        $this->historicalInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-HIST-1001',
            'business_date' => $invoiceDate,
            'status' => 'finalized',
            'final_total' => 5000.00,
            'balance_amount' => 5000.00,
            'generated_by' => $this->admin->id,
        ]);

        $this->historicalInvoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->historicalInvoice->id,
            'product_id' => $this->productWithInvoice->id,
            'product_name' => $this->productWithInvoice->name,
            'product_grade' => ProductGrade::GradeA->value,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'delivered_qty' => 100.00,
            'delivered_price_quantity' => 100.00,
            'unit_price' => 50.00,
            'final_line_total' => 5000.00,
        ]);
    }

    public function test_01_product_catalog_search_returns_active_products_and_enforces_office_auth(): void
    {
        $response = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
            ])
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'shop_id' => $this->shopA->id,
            ]);

        $items = collect($response->json('items'));
        $ids = $items->pluck('id')->all();

        $this->assertContains($this->productWithInvoice->id, $ids);
        $this->assertContains($this->productWithoutInvoice->id, $ids);
        $this->assertNotContains($this->inactiveProduct->id, $ids);

        $banana = $items->firstWhere('id', $this->productWithoutInvoice->id);
        $this->assertSame('Green Cavendish Banana', $banana['name']);
        $this->assertSame('FRT-BAN-099', $banana['sku']);
        $this->assertSame('kg', $banana['unit']);
        $this->assertSame(['A', 'B', 'C'], $banana['supported_grades']);

        $this->actingAs($this->warehouseUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
            ])
        )->assertForbidden();
    }

    public function test_02_product_catalog_search_includes_products_without_historical_invoices(): void
    {
        $this->assertDatabaseMissing('shop_invoice_items', [
            'product_id' => $this->productWithoutInvoice->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopB->id,
                'q' => 'Cavendish',
            ])
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('items'));
        $this->assertSame($this->productWithoutInvoice->id, $response->json('items.0.id'));
    }

    public function test_03_product_catalog_search_filters_by_name_and_sku(): void
    {
        $byName = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
                'q' => 'Red Onion',
            ])
        );

        $byName->assertOk();
        $this->assertSame([$this->productWithInvoice->id], collect($byName->json('items'))->pluck('id')->all());

        $bySku = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
                'q' => 'FRT-BAN-099',
            ])
        );

        $bySku->assertOk();
        $this->assertSame([$this->productWithoutInvoice->id], collect($bySku->json('items'))->pluck('id')->all());
    }

    public function test_04_product_catalog_search_supports_pagination(): void
    {
        $baselineCount = Product::query()->active()->count();

        foreach (range(1, 4) as $index) {
            Product::factory()->create([
                'name' => sprintf('Catalog Item %02d', $index),
                'sku' => sprintf('CAT-%03d', $index),
                'is_active' => true,
            ]);
        }

        $expectedTotal = $baselineCount + 4;
        $expectedLastPage = (int) ceil($expectedTotal / 2);

        $pageOne = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
                'per_page' => 2,
                'page' => 1,
            ])
        );

        $pageOne->assertOk()
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', $expectedTotal)
            ->assertJsonPath('pagination.last_page', $expectedLastPage)
            ->assertJsonPath('pagination.has_more', true);
        $this->assertCount(2, $pageOne->json('items'));

        $pageTwo = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.search-products', [
                'shop_id' => $this->shopA->id,
                'per_page' => 2,
                'page' => 2,
            ])
        );

        $pageTwo->assertOk()
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.has_more', true);

        $pageOneIds = collect($pageOne->json('items'))->pluck('id')->all();
        $pageTwoIds = collect($pageTwo->json('items'))->pluck('id')->all();
        $this->assertEmpty(array_intersect($pageOneIds, $pageTwoIds));
    }

    public function test_05_daily_price_lookup_prefers_shop_specific_special_price(): void
    {
        $businessDate = today()->toDateString();

        DailyPriceApproval::query()->create([
            'product_id' => $this->productWithoutInvoice->id,
            'business_date' => $businessDate,
            'grade' => 'A',
            'purchase_price' => 50.00,
            'price_unit' => 'kg',
            'price_a' => 80.00,
            'price_b' => 85.00,
            'price_c' => 90.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        ShopDailyProductPrice::query()->create([
            'business_date' => $businessDate,
            'shop_id' => $this->shopA->id,
            'product_id' => $this->productWithoutInvoice->id,
            'product_grade' => 'A',
            'selling_price' => 65.50,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        $specialResponse = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
                'business_date' => $businessDate,
            ])
        );

        $specialResponse->assertOk()
            ->assertJson([
                'success' => true,
                'available' => true,
                'price' => 65.5,
                'price_unit' => 'kg',
                'source' => 'special',
                'category_code' => 'SPECIAL',
                'grade' => 'A',
                'business_date' => $businessDate,
            ]);

        $normalResponse = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopB->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
                'business_date' => $businessDate,
            ])
        );

        $normalResponse->assertOk()
            ->assertJson([
                'success' => true,
                'available' => true,
                'price' => 85.0,
                'price_unit' => 'kg',
                'source' => 'normal',
                'category_code' => 'B',
                'grade' => 'A',
                'business_date' => $businessDate,
            ]);
    }

    public function test_06_daily_price_lookup_resolves_grades_a_b_c_and_rejects_invalid_grade(): void
    {
        $businessDate = today()->toDateString();

        foreach ([['A', 100.00], ['B', 75.00], ['C', 50.00]] as [$grade, $price]) {
            DailyPriceApproval::query()->create([
                'product_id' => $this->productWithInvoice->id,
                'business_date' => $businessDate,
                'grade' => $grade,
                'purchase_price' => $price - 10,
                'price_unit' => 'kg',
                'price_a' => $price,
                'price_b' => $price + 2,
                'price_c' => $price + 4,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        foreach (['A' => 100.0, 'B' => 75.0, 'C' => 50.0] as $grade => $expectedPrice) {
            $response = $this->actingAs($this->admin)->getJson(
                route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                    'shop_id' => $this->shopA->id,
                    'product_id' => $this->productWithInvoice->id,
                    'grade' => $grade,
                    'business_date' => $businessDate,
                ])
            );

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'available' => true,
                    'grade' => $grade,
                    'price' => $expectedPrice,
                    'source' => 'normal',
                    'category_code' => 'A',
                ]);
        }

        $invalidResponse = $this->actingAs($this->admin)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithInvoice->id,
                'grade' => 'Z',
                'business_date' => $businessDate,
            ])
        );

        $invalidResponse->assertStatus(422)
            ->assertJsonValidationErrors(['grade']);
    }

    public function test_07_business_date_defaults_to_today_and_create_edit_views_expose_override_flag(): void
    {
        $today = today()->toDateString();

        $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.reports.shops.credit-notes.drafts.create'))
            ->assertOk()
            ->assertViewHas('defaultDate', $today)
            ->assertViewHas('canOverrideBusinessDate', false);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.reports.shops.credit-notes.drafts.create'))
            ->assertOk()
            ->assertViewHas('defaultDate', $today)
            ->assertViewHas('canOverrideBusinessDate', true);

        $storeResponse = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                // Omit credit_note_date to verify server-side default
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $storeResponse->assertCreated()
            ->assertJson(['success' => true]);

        $draft = ShopCreditNote::findOrFail($storeResponse->json('credit_note_id'));
        $this->assertSame($today, $draft->credit_note_date->toDateString());

        $this->actingAs($this->accountsUser)
            ->get(route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $draft))
            ->assertOk()
            ->assertViewHas('canOverrideBusinessDate', false);

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $draft))
            ->assertOk()
            ->assertViewHas('canOverrideBusinessDate', true);
    }

    public function test_08_authorized_admin_can_select_valid_previous_business_date_and_accounts_can_save_default_date(): void
    {
        $previousDate = today()->subDays(3)->toDateString();

        $createResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $previousDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $createResponse->assertCreated();
        $draft = ShopCreditNote::findOrFail($createResponse->json('credit_note_id'));
        $this->assertSame($previousDate, $draft->credit_note_date->toDateString());

        // Non-admin accounts can update other fields when keeping the existing draft date unchanged
        $accountsUpdate = $this->actingAs($this->accountsUser)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $previousDate,
                'notes' => 'Updated by accounts without changing date',
                'items' => [$this->buildInvoiceItemPayload(15.0)],
            ]
        );

        $accountsUpdate->assertOk();
        $draft->refresh();
        $this->assertSame($previousDate, $draft->credit_note_date->toDateString());
        $this->assertSame('Updated by accounts without changing date', $draft->notes);

        // Authorized super_admin can change the business date to another valid open previous date
        $newerPreviousDate = today()->subDays(2)->toDateString();
        $superAdminUpdate = $this->actingAs($this->superAdmin)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $newerPreviousDate,
                'items' => [$this->buildInvoiceItemPayload(15.0)],
            ]
        );

        $superAdminUpdate->assertOk();
        $this->assertSame($newerPreviousDate, $draft->fresh()->credit_note_date->toDateString());
    }

    public function test_09_unauthorized_business_date_override_and_future_date_are_rejected(): void
    {
        $previousDate = today()->subDay()->toDateString();

        $createOverride = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $previousDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $createOverride->assertStatus(422)
            ->assertJsonValidationErrors(['credit_note_date']);

        // Future date rejected even for admin
        $futureDate = today()->addDay()->toDateString();
        $futureAttempt = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $futureDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $futureAttempt->assertStatus(422)
            ->assertJsonValidationErrors(['credit_note_date']);

        // Existing draft for update override check
        $draft = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => null,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'status' => ShopCreditNoteStatus::Draft,
            'credit_note_date' => today()->toDateString(),
            'credit_note_number' => 'DRAFT-SHP-ALP-STEP1-01',
            'created_by' => $this->accountsUser->id,
        ]);

        $updateOverride = $this->actingAs($this->accountsUser)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->subDays(2)->toDateString(),
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $updateOverride->assertStatus(422)
            ->assertJsonValidationErrors(['credit_note_date']);

        // Service-level guard also rejects unauthorized override
        $service = app(ShopCreditNoteService::class);
        $this->expectException(ValidationException::class);
        $service->createShopLevelDraft(
            $this->shopA,
            [
                'credit_note_date' => $previousDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ],
            $this->accountsUser
        );
    }

    public function test_10_closed_accounting_period_blocks_date_selection_even_for_admin(): void
    {
        $closedDate = today()->subDays(5)->toDateString();

        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(10)->toDateString(),
            'period_end' => today()->subDays(2)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'notes' => 'Closed test period',
        ]);

        $createResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $closedDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $createResponse->assertStatus(422)
            ->assertJsonValidationErrors(['credit_note_date']);

        $draft = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => null,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'status' => ShopCreditNoteStatus::Draft,
            'credit_note_date' => today()->toDateString(),
            'credit_note_number' => 'DRAFT-SHP-ALP-CLOSED-01',
            'created_by' => $this->admin->id,
        ]);

        $updateResponse = $this->actingAs($this->admin)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $draft),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $closedDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['credit_note_date']);

        $service = app(ShopCreditNoteService::class);
        $this->expectException(ValidationException::class);
        $service->createShopLevelDraft(
            $this->shopA,
            [
                'credit_note_date' => $closedDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ],
            $this->admin
        );
    }

    public function test_11_daily_price_lookup_uses_selected_business_date_not_system_date(): void
    {
        $today = today()->toDateString();
        $selectedDate = today()->subDays(4)->toDateString();

        DailyPriceApproval::query()->create([
            'product_id' => $this->productWithoutInvoice->id,
            'business_date' => $today,
            'grade' => 'A',
            'purchase_price' => 90.00,
            'price_unit' => 'kg',
            'price_a' => 120.00,
            'price_b' => 125.00,
            'price_c' => 130.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->productWithoutInvoice->id,
            'business_date' => $selectedDate,
            'grade' => 'A',
            'purchase_price' => 70.00,
            'price_unit' => 'kg',
            'price_a' => 92.50,
            'price_b' => 95.00,
            'price_c' => 97.50,
            'status' => 'approved',
            'approved_at' => now()->subDays(4),
        ]);

        $historicalLookup = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
                'business_date' => $selectedDate,
            ])
        );

        $historicalLookup->assertOk()
            ->assertJson([
                'success' => true,
                'available' => true,
                'business_date' => $selectedDate,
                'price' => 92.5,
                'source' => 'normal',
            ]);

        $defaultLookup = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
            ])
        );

        $defaultLookup->assertOk()
            ->assertJson([
                'success' => true,
                'available' => true,
                'business_date' => $today,
                'price' => 120.0,
                'source' => 'normal',
            ]);
    }

    public function test_12_missing_or_unapproved_daily_price_returns_null_price(): void
    {
        $missingDate = today()->subDays(6)->toDateString();

        $missingResponse = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
                'business_date' => $missingDate,
            ])
        );

        $missingResponse->assertOk()
            ->assertJson([
                'success' => true,
                'available' => false,
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
                'business_date' => $missingDate,
                'price' => null,
                'price_unit' => null,
                'source' => null,
                'category_code' => null,
            ]);
        $this->assertNotEmpty($missingResponse->json('message'));

        // Pending (unapproved) daily price must also return available: false, price: null
        DailyPriceApproval::query()->create([
            'product_id' => $this->productWithoutInvoice->id,
            'business_date' => today()->toDateString(),
            'grade' => 'A',
            'purchase_price' => 40.00,
            'price_unit' => 'kg',
            'price_a' => 60.00,
            'price_b' => 62.00,
            'price_c' => 64.00,
            'status' => 'pending',
            'approved_at' => null,
        ]);

        $unapprovedResponse = $this->actingAs($this->accountsUser)->getJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price', [
                'shop_id' => $this->shopA->id,
                'product_id' => $this->productWithoutInvoice->id,
                'grade' => 'A',
                'business_date' => today()->toDateString(),
            ])
        );

        $unapprovedResponse->assertOk()
            ->assertJson([
                'success' => true,
                'available' => false,
                'price' => null,
                'source' => null,
            ]);
    }

    public function test_13_linked_historical_invoice_date_does_not_override_selected_business_date(): void
    {
        $today = today()->toDateString();
        $selectedDate = today()->subDays(2)->toDateString();

        // 10-day-old invoice must NOT override default today date for accounts user
        $accountsResponse = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $accountsResponse->assertCreated();
        $accountsDraft = ShopCreditNote::findOrFail($accountsResponse->json('credit_note_id'));
        $this->assertSame($today, $accountsDraft->credit_note_date->toDateString());
        $this->assertNotSame($this->historicalInvoice->business_date->toDateString(), $accountsDraft->credit_note_date->toDateString());

        // 10-day-old invoice must NOT override Admin's selected business date
        $adminResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.store'),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => $selectedDate,
                'items' => [$this->buildInvoiceItemPayload()],
            ]
        );

        $adminResponse->assertCreated();
        $adminDraft = ShopCreditNote::findOrFail($adminResponse->json('credit_note_id'));
        $this->assertSame($selectedDate, $adminDraft->credit_note_date->toDateString());
        $this->assertSame($this->historicalInvoice->id, $adminDraft->items->first()->source_shop_invoice_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInvoiceItemPayload(float $qty = 10.0): array
    {
        return [
            'source_shop_invoice_id' => $this->historicalInvoice->id,
            'shop_invoice_item_id' => $this->historicalInvoiceItem->id,
            'product_id' => $this->productWithInvoice->id,
            'credit_quantity' => $qty,
            'credit_rate' => 50.00,
            'line_credit_amount' => round($qty * 50.00, 2),
            'inventory_action' => 'return_to_stock',
            'reason_type' => 'quality_issue',
        ];
    }
}
