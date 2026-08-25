<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseProductFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private Category $vegCategory;

    private Category $fruitCategory;

    private Product $tomato;

    private Product $onion;

    private Product $apple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->regularUser = User::factory()->create(['email' => 'staff@greenleaf.test']);

        $warehouse = Warehouse::factory()->create(['code' => 'MAIN-WH']);

        $this->vegCategory = Category::factory()->create(['name' => 'Vegetables']);
        $this->fruitCategory = Category::factory()->create(['name' => 'Fruits']);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'category_id' => $this->vegCategory->id,
            'default_warehouse_id' => $warehouse->id,
        ]);
        $this->onion = Product::factory()->create([
            'name' => 'Onion',
            'category_id' => $this->vegCategory->id,
            'default_warehouse_id' => $warehouse->id,
        ]);
        $this->apple = Product::factory()->create([
            'name' => 'Apple',
            'category_id' => $this->fruitCategory->id,
            'default_warehouse_id' => $warehouse->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_product_filters(): void
    {
        $this->get(route('admin.cashbook.finance.purchase.product-filters.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin.cashbook.finance.purchase.product-filters.create'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.cashbook.finance.purchase.product-filters.store'), [
            'name' => 'Test Filter',
            'product_ids' => [$this->tomato->id],
        ])->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.cashbook.finance.purchase.product-filters.index'))
            ->assertRedirect();

        $this->actingAs($this->regularUser)
            ->post(route('admin.cashbook.finance.purchase.product-filters.store'), [
                'name' => 'Test Filter',
                'product_ids' => [$this->tomato->id],
            ])->assertForbidden();
    }

    public function test_admin_can_view_product_filters_index(): void
    {
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Vegetable Staples',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id, $this->onion->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.product-filters.index'));

        $response->assertOk()
            ->assertSee('Product Filters')
            ->assertSee('Vegetable Staples')
            ->assertSee('2 products')
            ->assertSee('Create Product Filter');
    }

    public function test_admin_can_view_create_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.product-filters.create'));

        $response->assertOk()
            ->assertSee('Create Product Filter')
            ->assertSee('Vegetables')
            ->assertSee('Fruits')
            ->assertSee('Tomato')
            ->assertSee('Apple');
    }

    public function test_admin_can_create_saved_product_filter(): void
    {
        $payload = [
            'name' => 'Salad Products',
            'product_ids' => [$this->tomato->id, $this->apple->id],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.product-filters.store'), $payload);

        $filter = PurchaseProductFilter::query()->where('name', 'Salad Products')->first();
        $this->assertNotNull($filter);
        $this->assertSame($this->admin->id, $filter->created_by);
        $this->assertCount(2, $filter->products);
        $this->assertTrue($filter->products->contains('id', $this->tomato->id));
        $this->assertTrue($filter->products->contains('id', $this->apple->id));

        $response->assertRedirect(route('admin.cashbook.finance.purchase.product-filters.index'))
            ->assertSessionHas('success');
    }

    public function test_creation_requires_name_and_at_least_one_product(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.product-filters.store'), [
                'name' => '',
                'product_ids' => [],
            ])
            ->assertSessionHasErrors(['name', 'product_ids']);

        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.product-filters.store'), [
                'name' => 'No Products Filter',
                'product_ids' => [],
            ])
            ->assertSessionHasErrors(['product_ids']);
    }

    public function test_creation_validates_product_ids_exist(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cashbook.finance.purchase.product-filters.store'), [
                'name' => 'Invalid Products',
                'product_ids' => [999999],
            ])
            ->assertSessionHasErrors(['product_ids.0']);
    }

    public function test_admin_can_view_edit_page(): void
    {
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Vegetable Staples',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase.product-filters.edit', $filter));

        $response->assertOk()
            ->assertSee('Edit Product Filter')
            ->assertSee('Vegetable Staples')
            ->assertSee('Tomato');
    }

    public function test_admin_can_update_product_filter(): void
    {
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Old Name',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.cashbook.finance.purchase.product-filters.update', $filter), [
                'name' => 'Updated Name',
                'product_ids' => [$this->onion->id, $this->apple->id],
            ]);

        $response->assertRedirect(route('admin.cashbook.finance.purchase.product-filters.index'))
            ->assertSessionHas('success');

        $filter->refresh();
        $this->assertSame('Updated Name', $filter->name);
        $this->assertCount(2, $filter->products);
        $this->assertFalse($filter->products->contains('id', $this->tomato->id));
        $this->assertTrue($filter->products->contains('id', $this->onion->id));
        $this->assertTrue($filter->products->contains('id', $this->apple->id));
    }

    public function test_update_requires_at_least_one_product(): void
    {
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Existing',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.cashbook.finance.purchase.product-filters.update', $filter), [
                'name' => 'Existing',
                'product_ids' => [],
            ])
            ->assertSessionHasErrors(['product_ids']);
    }

    public function test_admin_can_delete_product_filter(): void
    {
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'To Delete',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.cashbook.finance.purchase.product-filters.destroy', $filter));

        $response->assertRedirect(route('admin.cashbook.finance.purchase.product-filters.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('purchase_product_filters', ['id' => $filter->id]);
    }

    public function test_filter_snapshot_membership_new_category_products_do_not_enter_filter(): void
    {
        // Create filter containing all vegetables existing right now
        $vegProductIds = Product::query()->where('category_id', $this->vegCategory->id)->pluck('id')->all();
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Snapshot Veggies',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync($vegProductIds);

        // Later, a new product is added to the Vegetables category
        $newVeg = Product::factory()->create([
            'name' => 'Carrot',
            'category_id' => $this->vegCategory->id,
        ]);

        $filter->refresh();
        $filterProductIds = $filter->products->pluck('id')->all();

        // The filter must only contain the original snapshot, not the newly added product
        $this->assertNotContains($newVeg->id, $filterProductIds);
        $this->assertContains($this->tomato->id, $filterProductIds);
        $this->assertContains($this->onion->id, $filterProductIds);
    }

    public function test_filtering_purchase_dashboard_with_product_filter(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Daily Veg Supplier']);
        $purchaser = User::factory()->create(['name' => 'Ram Purchaser']);

        $tomatoInvoice = $this->createPurchaseInvoice($supplier, $purchaser, '2026-08-25', 'Cash', [
            ['product' => $this->tomato, 'qty' => 10, 'unit_price' => 20, 'line_total' => 200],
        ]);
        $appleInvoice = $this->createPurchaseInvoice($supplier, $purchaser, '2026-08-25', 'Cash', [
            ['product' => $this->apple, 'qty' => 5, 'unit_price' => 100, 'line_total' => 500],
        ]);

        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Tomato Only',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);

        // Unfiltered dashboard returns both (₹700.00)
        $all = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase', ['period' => 'today']));
        $all->assertOk()->assertSee('₹700.00')->assertSee($tomatoInvoice->invoice_number)->assertSee($appleInvoice->invoice_number);

        // Filtered dashboard returns only tomato (₹200.00)
        $filtered = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase', [
                'period' => 'today',
                'product_filter' => $filter->uuid,
            ]));
        $filtered->assertOk()
            ->assertSee('₹200.00')
            ->assertSee($tomatoInvoice->invoice_number)
            ->assertDontSee($appleInvoice->invoice_number)
            ->assertDontSee('₹700.00')
            ->assertDontSee('₹500.00');
    }

    public function test_mixed_invoice_item_level_apportionment_with_product_filter(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Mixed Fresh Farm']);
        $purchaser = User::factory()->create(['name' => 'Suresh Purchaser']);

        // Single invoice with BOTH Tomato (₹300) and Apple (₹700) = Total ₹1,000 Cash
        $mixedInvoice = $this->createPurchaseInvoice($supplier, $purchaser, '2026-08-25', 'Cash', [
            ['product' => $this->tomato, 'qty' => 15, 'unit_price' => 20, 'line_total' => 300],
            ['product' => $this->apple, 'qty' => 7, 'unit_price' => 100, 'line_total' => 700],
        ]);

        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Tomato Only',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase', [
                'period' => 'today',
                'product_filter' => $filter->uuid,
            ]));

        // Total purchase should ONLY reflect the matching line item (₹300.00), not the full invoice ₹1,000.00
        $response->assertOk()
            ->assertSee('₹300.00')
            ->assertDontSee('₹1,000.00')
            ->assertDontSee('₹700.00')
            ->assertSee($mixedInvoice->invoice_number);
    }

    public function test_invalid_uuid_returns_validation_error(): void
    {
        $randomUuid = (string) Str::uuid();

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase', [
                'period' => 'today',
                'product_filter' => $randomUuid,
            ]))
            ->assertSessionHasErrors(['product_filter']);
    }

    public function test_deleted_filter_uuid_returns_validation_error(): void
    {
        $filter = PurchaseProductFilter::query()->create([
            'name' => 'Deleted Filter',
            'created_by' => $this->admin->id,
        ]);
        $filter->products()->sync([$this->tomato->id]);
        $uuid = $filter->uuid;
        $filter->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase', [
                'period' => 'today',
                'product_filter' => $uuid,
            ]))
            ->assertSessionHasErrors(['product_filter']);
    }

    public function test_legacy_produce_type_falls_back_to_all_products(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'All Supplier']);
        $purchaser = User::factory()->create(['name' => 'Ravi Purchaser']);

        $tomatoInvoice = $this->createPurchaseInvoice($supplier, $purchaser, '2026-08-25', 'Cash', [
            ['product' => $this->tomato, 'qty' => 10, 'unit_price' => 20, 'line_total' => 200],
        ]);
        $appleInvoice = $this->createPurchaseInvoice($supplier, $purchaser, '2026-08-25', 'Cash', [
            ['product' => $this->apple, 'qty' => 5, 'unit_price' => 100, 'line_total' => 500],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.finance.purchase', [
                'period' => 'today',
                'produce_type' => 'vegetables', // Legacy query param
            ]));

        $response->assertOk()
            ->assertSee('₹700.00')
            ->assertSee($tomatoInvoice->invoice_number)
            ->assertSee($appleInvoice->invoice_number);
    }

    /**
     * @param  array<int, array{product: Product, qty: float|int, unit_price: float|int, line_total: float|int}>  $items
     */
    private function createPurchaseInvoice(Supplier $supplier, User $purchaser, string $businessDate, string $paymentMethod, array $items): PurchaseInvoice
    {
        $totalAmount = array_sum(array_column($items, 'line_total'));

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'CART-'.strtoupper(Str::random(8)),
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $items[0]['product']->default_warehouse_id,
            'business_date' => $businessDate,
            'payment_method' => $paymentMethod,
            'status' => 'completed',
            'is_invoiced' => true,
        ]);

        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);

        foreach ($items as $item) {
            PurchaserCartItem::query()->create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $item['product']->id,
                'grade' => 'A',
                'quantity' => $item['qty'],
                'declared_quantity' => $item['qty'],
                'actual_quantity' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'status' => 'received',
            ]);
        }

        $invoice = PurchaseInvoice::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'goods_received_id' => $goodsReceived->id,
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-'.strtoupper(Str::random(8)),
            'amount' => $totalAmount,
            'discount_amount' => 0,
            'paid_amount' => strtolower($paymentMethod) === 'cash' ? $totalAmount : 0,
            'payment_method' => $paymentMethod,
            'payment_status' => strtolower($paymentMethod) === 'cash' ? 'paid' : 'unpaid',
            'created_at' => $businessDate.' 10:00:00',
        ]);

        $cart->update(['purchase_invoice_id' => $invoice->id]);

        return $invoice;
    }
}
