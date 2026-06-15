<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\POStatus;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyPriceBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        foreach (['A' => 10, 'B' => 12, 'C' => 15] as $name => $margin) {
            ShopPriceGroup::query()->firstOrCreate(
                ['name' => $name],
                [
                    'default_margin_percent' => $margin,
                    'is_active' => true,
                ]
            );
        }
    }

    public function test_purchase_manager_can_view_price_proposal_board(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('purchasing.prices.index'));

        $response->assertOk();
        $response->assertSee('Price Proposal Board');
        $response->assertSee('Proposal Update');
    }

    public function test_purchase_manager_can_search_proposals(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $matchingProduct = Product::factory()->create([
            'name' => 'Searchable Tomato',
            'sku' => 'SEARCH-TOM-001',
        ]);
        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Hidden Apple',
            'sku' => 'HIDDEN-APP-001',
        ]);

        $supplier = Supplier::factory()->create();
        $purchaseDate = Carbon::today()->toDateString();

        foreach ([
            [$matchingProduct, 22.0],
            [$nonMatchingProduct, 18.0],
        ] as [$product, $price]) {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $supplier->id,
                'status' => POStatus::Received,
            ]);

            $poItem = PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_price' => $price,
                'price_basis' => 'per_kg',
            ]);

            $grn = GoodsReceived::create([
                'purchase_order_id' => $po->id,
                'grn_number' => 'GRN-SEARCH-'.uniqid(),
                'status' => 'approved',
                'received_by' => $manager->id,
                'approved_by' => $manager->id,
                'updated_by' => $manager->id,
                'received_at' => $purchaseDate,
                'approved_at' => now(),
                'transport_cost' => 0,
                'labour_cost' => 0,
            ]);

            $grn->items()->create([
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $product->id,
                'received_qty' => 10,
                'variance' => 0,
            ]);
        }

        $response = $this->actingAs($manager)
            ->get(route('purchasing.prices.index', [
                'date' => $purchaseDate,
                'search' => 'Tomato',
            ]));

        $response->assertOk();
        $response->assertSee($matchingProduct->name);
        $response->assertDontSee($nonMatchingProduct->name);
        $response->assertSee('min-w-[980px]', false);
    }

    public function test_purchase_manager_update_keeps_prices_as_pending_admin_approval(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $product = Product::factory()->create([
            'name' => 'Proposal Product',
        ]);

        $purchaseDate = Carbon::today()->toDateString();
        $businessDate = Carbon::tomorrow()->toDateString();

        $approval = DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => $businessDate,
            'purchase_price' => 20.00,
            'price_a' => 22.00,
            'price_b' => 23.00,
            'price_c' => 24.00,
            'status' => 'approved',
            'approved_by' => $manager->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($manager)
            ->post(route('purchasing.prices.update'), [
                'date' => $purchaseDate,
                'reason' => 'Purchase cost changed',
                'prices' => [
                    $approval->id => [
                        'price_a' => 25.00,
                        'price_b' => 26.00,
                        'price_c' => 27.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('purchasing.prices.index', ['date' => $purchaseDate]));

        $approval->refresh();
        $this->assertSame('pending', $approval->status);
        $this->assertSame(25.00, (float) $approval->price_a);
        $this->assertNull($approval->approved_by);
        $this->assertNull($approval->approved_at);
    }

    public function test_shop_owner_cannot_access_price_proposal_board(): void
    {
        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->get(route('purchasing.prices.index'));

        $response->assertForbidden();
    }
}
