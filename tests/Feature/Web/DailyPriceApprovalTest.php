<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Purchasing\POStatus;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyPriceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->product = Product::factory()->create([
            'name' => 'Apple Tomato',
            'base_price' => 20.00,
        ]);

        // Ensure default price groups are present
        foreach (['A' => 10, 'B' => 12, 'C' => 15] as $name => $margin) {
            ShopPriceGroup::firstOrCreate(
                ['name' => $name],
                [
                    'default_margin_percent' => $margin,
                    'is_active' => true,
                ]
            );
        }
    }

    public function test_admin_can_view_pending_price_approvals(): void
    {
        $date = Carbon::tomorrow()->format('Y-m-d');

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $date,
            'purchase_price' => 22.00,
            'price_a' => 24.20,
            'price_b' => 24.64,
            'price_c' => 25.30,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.price-approvals.index', ['date' => $date]));

        $response->assertOk();
        $response->assertViewIs('admin.price-approvals.index');
        $response->assertSee('Apple Tomato');
        $response->assertSee('Rs. 22.00');
    }

    public function test_price_approvals_are_sorted_by_price_variance(): void
    {
        $date = Carbon::tomorrow()->format('Y-m-d');

        $productLow = Product::factory()->create(['name' => 'Low Var Product', 'base_price' => 20.00]);
        $productHigh = Product::factory()->create(['name' => 'High Var Product', 'base_price' => 20.00]);

        // Low variance: base price 20 -> proposed 21 (diff = 1)
        DailyPriceApproval::create([
            'product_id' => $productLow->id,
            'business_date' => $date,
            'purchase_price' => 21.00,
            'price_a' => 23.10,
            'price_b' => 23.52,
            'price_c' => 24.15,
            'status' => 'pending',
        ]);

        // High variance: base price 20 -> proposed 35 (diff = 15)
        DailyPriceApproval::create([
            'product_id' => $productHigh->id,
            'business_date' => $date,
            'purchase_price' => 35.00,
            'price_a' => 38.50,
            'price_b' => 39.20,
            'price_c' => 40.25,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.price-approvals.index', ['date' => $date]));

        $response->assertOk();
        $items = $response->viewData('items');

        $this->assertCount(2, $items);
        // Product High should be first in sorted items because of larger variance
        $this->assertEquals($productHigh->id, $items[0]['product']->id);
        $this->assertEquals($productLow->id, $items[1]['product']->id);
    }

    public function test_admin_can_approve_pending_prices(): void
    {
        $date = Carbon::tomorrow()->format('Y-m-d');

        $approval = DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $date,
            'purchase_price' => 20.00,
            'price_a' => 22.00,
            'price_b' => 22.40,
            'price_c' => 23.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.price-approvals.approve'), [
                'date' => $date,
                'approvals' => [$approval->id],
                'price_a' => [$approval->id => 25.00], // custom price edit
                'price_b' => [$approval->id => 26.00],
                'price_c' => [$approval->id => 27.00],
            ]);

        $response->assertRedirect(route('admin.price-approvals.index', ['date' => $date]));

        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
        $this->assertEquals(25.00, $approval->price_a);
        $this->assertSame(20.0, (float) $this->product->fresh()->vendor_price);

        // Assert that the active prices are saved in daily_product_prices
        $groupA = ShopPriceGroup::where('name', 'A')->first();
        $groupB = ShopPriceGroup::where('name', 'B')->first();
        $groupC = ShopPriceGroup::where('name', 'C')->first();

        // Group A: Grade A (25.00), Grade B (25.00 * 0.9 = 22.50), Grade C (25.00 * 0.8 = 20.00)
        $this->assertDatabaseHas('daily_product_prices', [
            'product_id' => $this->product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => ProductGrade::GradeA->value,
            'selling_price' => 25.00,
        ]);

        $this->assertDatabaseHas('daily_product_prices', [
            'product_id' => $this->product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => ProductGrade::GradeB->value,
            'selling_price' => 22.50,
        ]);

        // Group B: Grade A (26.00)
        $this->assertDatabaseHas('daily_product_prices', [
            'product_id' => $this->product->id,
            'shop_price_group_id' => $groupB->id,
            'grade' => ProductGrade::GradeA->value,
            'selling_price' => 26.00,
        ]);
    }

    public function test_unauthorized_user_cannot_access_price_approvals(): void
    {
        $response = $this->actingAs($this->purchaser)
            ->get(route('admin.price-approvals.index'));

        $response->assertForbidden();

        $responsePost = $this->actingAs($this->purchaser)
            ->post(route('admin.price-approvals.approve'));

        $responsePost->assertForbidden();
    }

    public function test_admin_can_view_approved_rows_and_publish_history(): void
    {
        $date = Carbon::tomorrow()->format('Y-m-d');
        $groupA = ShopPriceGroup::where('name', 'A')->firstOrFail();
        $dailyPrice = DailyProductPrice::create([
            'product_id' => $this->product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => ProductGrade::GradeA->value,
            'selling_price' => 22.00,
            'price_source' => 'manual',
            'margin_percent' => null,
            'manual_override' => true,
            'changed_by' => $this->admin->id,
        ]);

        $approved = DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $date,
            'purchase_price' => 20.00,
            'price_a' => 22.00,
            'price_b' => 23.00,
            'price_c' => 24.00,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        DailyProductPriceRevision::create([
            'daily_product_price_id' => $dailyPrice->id,
            'product_id' => $this->product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => ProductGrade::GradeA->value,
            'old_price' => 21.00,
            'new_price' => 22.00,
            'old_margin_percent' => null,
            'new_margin_percent' => null,
            'change_type' => 'manual',
            'reason' => 'Admin approved proposed daily price',
            'changed_by' => $this->admin->id,
            'changed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.price-approvals.index', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Approved Rows');
        $response->assertSee('Publish History');
        $response->assertSee($approved->product->name);
        $response->assertSee('Category A');
    }

    public function test_receiver_created_grn_creates_pending_daily_price_approval(): void
    {
        $receiver = User::factory()->create();
        $receiver->assignRole('warehouse_receiver');

        $date = today()->format('Y-m-d');
        $po = PurchaseOrder::factory()->create([
            'order_date' => $date,
            'status' => POStatus::SentToSupplier,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 30.00,
        ]);
        $response = $this->actingAs($receiver)
            ->post(route('purchasing.grns.store'), [
                'purchase_order_id' => $po->id,
                'received_at' => $date,
                'transport_cost' => 0.00,
                'labour_cost' => 0.00,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $this->product->id,
                        'received_qty' => 100,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // Assert a pending DailyPriceApproval is created for tomorrow's business date
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');
        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $this->product->id,
            'business_date' => $tomorrow.' 00:00:00',
            'purchase_price' => 30.0000,
            'status' => 'pending',
        ]);
    }
}
