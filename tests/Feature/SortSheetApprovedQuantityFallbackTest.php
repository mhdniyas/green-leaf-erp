<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SortSheetApprovedQuantityFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_late_request_items_with_a_missing_approved_quantity_are_included_in_the_sort_sheet(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('sort.sheet.view'));

        $shop = Shop::factory()->create();
        $product = Product::factory()->create();
        $order = ShopOrder::factory()->approved()->late()->for($shop)->create([
            'business_date' => '2026-08-24',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => null,
            'unit' => $product->unit,
        ]);

        $this->actingAs($user)
            ->get(route('sort-sheet.generate', ['date' => '2026-08-24']))
            ->assertOk()
            ->assertViewHas('matrix', fn (array $matrix): bool => ($matrix[$product->id][$shop->id] ?? null) === 15.0);
    }
}
