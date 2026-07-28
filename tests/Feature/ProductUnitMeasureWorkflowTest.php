<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductUnitMeasureWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_product_create_stores_multiple_order_units_and_uses_uuid_edit_route(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::factory()->create();

        $this
            ->actingAs($admin)
            ->post(route('inventory.products.store'), [
                'category_id' => $category->id,
                'name' => 'Tomato H',
                'sku' => 'TOMATO-H',
                'unit' => 'kg',
                'buffer_qty' => 0,
                'carryover_enabled' => '0',
                'units' => [
                    ['unit' => 'kg', 'conversion_to_base' => 1, 'is_base' => '1', 'is_orderable' => '1', 'label' => 'KG'],
                    ['unit' => 'box', 'conversion_to_base' => 12, 'is_base' => '0', 'is_orderable' => '1', 'label' => 'BOX'],
                    ['unit' => 'piece', 'conversion_to_base' => 0.25, 'is_base' => '0', 'is_orderable' => '1', 'label' => 'PCS'],
                ],
            ])
            ->assertRedirect(route('inventory.products.index'));

        $product = Product::query()->where('sku', 'TOMATO-H')->with('orderUnits')->sole();

        $this->assertNotNull($product->public_uuid);
        $this->assertSame($product->public_uuid, $product->getRouteKey());
        $this->assertSame(3, $product->orderUnits->count());
        $this->assertSame(12.0, (float) $product->orderUnits->firstWhere('unit', 'box')->conversion_to_base);
        $this->assertSame(0.25, (float) $product->orderUnits->firstWhere('unit', 'piece')->conversion_to_base);

        $this
            ->actingAs($admin)
            ->get(route('inventory.products.edit', $product))
            ->assertOk()
            ->assertSeeText('Units & Measures');

        $this
            ->actingAs($admin)
            ->get('/inventory/products/'.$product->id.'/edit')
            ->assertNotFound();
    }

    public function test_shop_owner_box_order_is_saved_as_base_quantity_for_purchaser_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));

        $shop = Shop::factory()->create();
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $product = Product::factory()->create([
            'name' => 'Box Tomato',
            'sku' => 'BOX-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX',
            'conversion_to_base' => 12,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 3],
                'item_units' => [$product->sku => 'box'],
            ])
            ->assertRedirect();

        $item = ShopOrder::query()->with('items.product')->sole()->items->sole();

        $this->assertSame('kg', $item->unit);
        $this->assertSame(36.0, (float) $item->requested_qty);
        $this->assertSame('box', $item->requested_unit);
        $this->assertSame(3.0, (float) $item->requested_unit_quantity);
        $this->assertSame(12.0, (float) $item->requested_unit_conversion_to_base);
    }

    public function test_shop_owner_can_order_same_product_with_multiple_box_measures(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));

        $shop = Shop::factory()->create();
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $product = Product::factory()->create([
            'name' => 'Multi Box Tomato',
            'sku' => 'MULTI-BOX-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        $box10 = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX 10 KG',
            'conversion_to_base' => 10,
            'is_base' => false,
            'is_orderable' => true,
        ]);
        $box5 = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX 5 KG',
            'conversion_to_base' => 5,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $line10 = $product->sku.'|'.$box10->public_uuid;
        $line5 = $product->sku.'|'.$box5->public_uuid;

        $this
            ->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [
                    $line10 => 1,
                    $line5 => 2,
                ],
                'item_units' => [
                    $line10 => 'box',
                    $line5 => 'box',
                ],
                'item_measures' => [
                    $line10 => $box10->public_uuid,
                    $line5 => $box5->public_uuid,
                ],
            ])
            ->assertRedirect();

        $items = ShopOrder::query()->with('items')->sole()->items->sortBy('requested_unit_conversion_to_base')->values();

        $this->assertCount(2, $items);
        $this->assertSame('BOX 5 KG', $items[0]->requested_unit_label);
        $this->assertSame(2.0, (float) $items[0]->requested_unit_quantity);
        $this->assertSame(10.0, (float) $items[0]->requested_qty);
        $this->assertSame('BOX 10 KG', $items[1]->requested_unit_label);
        $this->assertSame(1.0, (float) $items[1]->requested_unit_quantity);
        $this->assertSame(10.0, (float) $items[1]->requested_qty);
    }

    public function test_shop_owner_piece_order_without_kg_conversion_stays_as_piece_quantity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));

        $shop = Shop::factory()->create();
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');
        $product = Product::factory()->create([
            'name' => 'Piece Lettuce',
            'sku' => 'PIECE-LETTUCE',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'piece',
            'label' => 'PIECE',
            'conversion_to_base' => null,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 12],
                'item_units' => [$product->sku => 'piece'],
            ])
            ->assertRedirect();

        $item = ShopOrder::query()->with('items.product')->sole()->items->sole();

        $this->assertSame('piece', $item->unit);
        $this->assertSame(12.0, (float) $item->requested_qty);
        $this->assertSame('piece', $item->requested_unit);
        $this->assertSame(12.0, (float) $item->requested_unit_quantity);
        $this->assertNull($item->requested_unit_conversion_to_base);
    }

    public function test_admin_can_bulk_update_product_measures_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Bulk Tomato',
            'sku' => 'BULK-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee(route('inventory.products.measures.bulk'), false);

        $this
            ->actingAs($admin)
            ->get(route('inventory.products.measures.bulk'))
            ->assertOk()
            ->assertSeeText('Bulk Product Unit Measures')
            ->assertSee('BULK-TOMATO');

        $this
            ->actingAs($admin)
            ->put(route('inventory.products.measures.bulk.update'), [
                'products' => [
                    [
                        'public_uuid' => $product->public_uuid,
                        'base_unit' => 'kg',
                        'units' => [
                            'kg' => '1',
                            'box' => '12',
                            'piece' => '0.25',
                            'bag' => '',
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.products.measures.bulk'));

        $product->refresh()->load('orderUnits');

        $this->assertSame('Bulk Tomato', $product->name);
        $this->assertSame('BULK-TOMATO', $product->sku);
        $this->assertSame('kg', $product->unit);
        $this->assertSame(3, $product->orderUnits->count());
        $this->assertSame(12.0, (float) $product->orderUnits->firstWhere('unit', 'box')->conversion_to_base);
        $this->assertSame(0.25, (float) $product->orderUnits->firstWhere('unit', 'piece')->conversion_to_base);
        $this->assertNull($product->orderUnits->firstWhere('unit', 'bag'));
    }

    public function test_bulk_measures_requires_box_kg_and_allows_piece_without_kg(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'name' => 'Measure Beans',
            'sku' => 'MEASURE-BEANS',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($admin)
            ->put(route('inventory.products.measures.bulk.update'), [
                'products' => [
                    [
                        'public_uuid' => $product->public_uuid,
                        'base_unit' => 'kg',
                        'enabled_units' => ['box' => '1'],
                        'units' => ['kg' => '1', 'box' => '', 'piece' => ''],
                    ],
                ],
            ])
            ->assertSessionHasErrors('products.0.units.box');

        $this
            ->actingAs($admin)
            ->put(route('inventory.products.measures.bulk.update'), [
                'products' => [
                    [
                        'public_uuid' => $product->public_uuid,
                        'base_unit' => 'kg',
                        'enabled_units' => ['piece' => '1'],
                        'units' => ['kg' => '1', 'box' => '', 'piece' => ''],
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.products.measures.bulk'));

        $product->refresh()->load('orderUnits');

        $piece = $product->orderUnits->firstWhere('unit', 'piece');
        $this->assertNotNull($piece);
        $this->assertNull($piece->conversion_to_base);
        $this->assertNull($product->orderUnits->firstWhere('unit', 'box'));
    }

    public function test_bulk_measures_can_limit_shop_owner_visible_units(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'name' => 'Box Only Tomato',
            'sku' => 'BOX-ONLY-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($admin)
            ->put(route('inventory.products.measures.bulk.update'), [
                'products' => [
                    [
                        'public_uuid' => $product->public_uuid,
                        'base_unit' => 'kg',
                        'enabled_units' => ['box' => '1'],
                        'units' => ['kg' => '1', 'box' => '14'],
                        'visible_labels' => [
                            'KG' => '0',
                            'BOX 14 KG' => '1',
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.products.measures.bulk'));

        $product->refresh()->load('orderUnits');

        $this->assertFalse((bool) $product->orderUnits->firstWhere('label', 'KG')->is_orderable);
        $this->assertTrue((bool) $product->orderUnits->firstWhere('label', 'BOX 14 KG')->is_orderable);
    }

    public function test_admin_can_export_bulk_product_measures_as_json(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'name' => 'Export Tomato',
            'sku' => 'EXPORT-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'box',
            'label' => 'BOX 10 KG',
            'conversion_to_base' => 10,
            'is_base' => false,
            'is_orderable' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('inventory.products.measures.bulk.export-json', ['search' => 'EXPORT-TOMATO']))
            ->assertOk();

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('green-leaf-product-measures.v1', $payload['format']);
        $this->assertCount(1, $payload['products']);
        $this->assertSame('EXPORT-TOMATO', $payload['products'][0]['sku']);
        $this->assertSame('BOX 10 KG', $payload['products'][0]['measures'][1]['label']);
        $this->assertFalse($payload['products'][0]['measures'][1]['is_orderable']);
    }

    public function test_admin_can_import_bulk_product_measures_json_update(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'name' => 'Import Tomato',
            'sku' => 'IMPORT-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $payload = [
            'format' => 'green-leaf-product-measures.v1',
            'products' => [
                [
                    'sku' => 'IMPORT-TOMATO',
                    'base_unit' => 'kg',
                    'measures' => [
                        [
                            'unit' => 'kg',
                            'label' => 'KG',
                            'conversion_to_base' => 1,
                            'is_base' => true,
                            'is_orderable' => false,
                        ],
                        [
                            'unit' => 'box',
                            'label' => 'BOX 8 KG',
                            'conversion_to_base' => 8,
                            'is_base' => false,
                            'is_orderable' => true,
                        ],
                        [
                            'unit' => 'box',
                            'label' => 'BOX 12 KG',
                            'conversion_to_base' => 12,
                            'is_base' => false,
                            'is_orderable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $this
            ->actingAs($admin)
            ->post(route('inventory.products.measures.bulk.import-json'), [
                'import_file' => UploadedFile::fake()->createWithContent('measures.json', json_encode($payload)),
            ])
            ->assertRedirect(route('inventory.products.measures.bulk'))
            ->assertSessionHas('success', '1 product measures imported.');

        $product->refresh()->load('orderUnits');

        $this->assertSame(3, $product->orderUnits->count());
        $this->assertFalse((bool) $product->orderUnits->firstWhere('label', 'KG')->is_orderable);
        $this->assertSame(8.0, (float) $product->orderUnits->firstWhere('label', 'BOX 8 KG')->conversion_to_base);
        $this->assertSame(12.0, (float) $product->orderUnits->firstWhere('label', 'BOX 12 KG')->conversion_to_base);
        $this->assertTrue((bool) $product->orderUnits->firstWhere('label', 'BOX 12 KG')->is_orderable);
    }

    public function test_json_bulk_measures_normalizes_legacy_pcs_products_without_measure_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'name' => 'Legacy Coconut',
            'sku' => '170',
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('inventory.products.measures.bulk.export-json', ['search' => '170']))
            ->assertOk();

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('piece', $payload['products'][0]['base_unit']);
        $this->assertSame('piece', $payload['products'][0]['measures'][0]['unit']);
        $this->assertSame('PIECE', $payload['products'][0]['measures'][0]['label']);

        $payload['products'][0]['base_unit'] = 'pcs';
        $payload['products'][0]['measures'] = [];

        $this
            ->actingAs($admin)
            ->post(route('inventory.products.measures.bulk.import-json'), [
                'import_file' => UploadedFile::fake()->createWithContent('legacy-measures.json', json_encode($payload)),
            ])
            ->assertRedirect(route('inventory.products.measures.bulk'))
            ->assertSessionHas('success', '1 product measures imported.');

        $product->refresh()->load('orderUnits');

        $this->assertSame('piece', $product->unit);
        $this->assertSame('piece', $product->orderUnits->sole()->unit);
        $this->assertSame('PIECE', $product->orderUnits->sole()->label);
    }

    public function test_bulk_measures_ajax_save_returns_json_without_redirect(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'name' => 'Ajax Tomato',
            'sku' => 'AJAX-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'kg',
            'label' => 'KG',
            'conversion_to_base' => 1,
            'is_base' => true,
            'is_orderable' => true,
        ]);

        $this
            ->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('inventory.products.measures.bulk.update'), [
                'save_row' => 0,
                'products' => [
                    [
                        'public_uuid' => $product->public_uuid,
                        'base_unit' => 'kg',
                        'enabled_units' => ['box' => '1'],
                        'units' => ['kg' => '1', 'box' => '10'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'updated' => 1,
            ]);

        $this->assertSame(10.0, (float) $product->fresh('orderUnits')->orderUnits->firstWhere('unit', 'box')->conversion_to_base);
    }
}
