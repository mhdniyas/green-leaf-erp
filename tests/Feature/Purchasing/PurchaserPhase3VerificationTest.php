<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserPhase3VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function createPurchaser(): User
    {
        Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('purchaser');

        return $user;
    }

    public function test_load_more_json_endpoints_require_authentication(): void
    {
        $this->getJson(route('purchaser.shop-orders.index'))->assertUnauthorized();
        $this->getJson(route('purchaser.finance'))->assertUnauthorized();
        $this->getJson(route('purchaser.products'))->assertUnauthorized();
    }

    public function test_load_more_json_endpoints_return_expected_pagination_structure(): void
    {
        $purchaser = $this->createPurchaser();

        $this->actingAs($purchaser)
            ->getJson(route('purchaser.shop-orders.index'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'pagination' => ['current_page', 'per_page', 'total_records', 'last_page', 'has_more'],
                'status_counts',
                'total_orders',
            ]);

        $this->actingAs($purchaser)
            ->getJson(route('purchaser.finance'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'pagination' => ['current_page', 'per_page', 'total_records', 'last_page', 'has_more'],
                'finance_tabs' => ['today', 'old'],
            ]);

        $this->actingAs($purchaser)
            ->getJson(route('purchaser.products'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'pagination' => ['current_page', 'per_page', 'total_records', 'last_page', 'has_more'],
            ]);
    }

    public function test_supplier_show_json_endpoint_enforces_purchaser_scope(): void
    {
        $purchaser = $this->createPurchaser();
        $supplier = Supplier::factory()->create();

        $this->actingAs($purchaser)
            ->getJson(route('purchaser.suppliers.show', $supplier))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'pagination' => ['current_page', 'per_page', 'total_records', 'last_page', 'has_more'],
                'history_totals' => ['pending_amount', 'paid_amount', 'total_amount', 'discount_amount', 'item_count', 'record_count'],
            ]);
    }
}
