<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Requisition\MultiGradeOrderGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiGradeGlobalSettingToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private Shop $ashirwadShop;

    private Shop $standardShop;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $shopRole = Role::firstOrCreate(['name' => 'shop']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $salesOrderPerm = Permission::findOrCreate('sales.order.create');
        $shopRole->givePermissionTo($salesOrderPerm);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test_toggle@greenleaf.com'],
            ['name' => 'Toggle Admin', 'password' => bcrypt('password')]
        );
        $this->admin->syncRoles(['admin']);

        $this->regularUser = User::firstOrCreate(
            ['email' => 'regular_user_toggle@greenleaf.com'],
            ['name' => 'Regular User', 'password' => bcrypt('password')]
        );
        $this->regularUser->syncRoles([]);

        $purchaser = User::firstOrCreate(
            ['email' => 'purchaser_toggle@greenleaf.com'],
            ['name' => 'Default Purchaser', 'password' => bcrypt('password')]
        );
        $purchaser->syncRoles(['purchaser']);

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'default_purchaser_user_id'],
            ['value' => (string) $purchaser->id]
        );

        $this->ashirwadShop = Shop::create([
            'name' => 'Ashirwad',
            'code' => 'AV_ASHIRWAD',
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->standardShop = Shop::create([
            'name' => 'Lulu Budhigere',
            'code' => 'AV_LULU_BUDHIGERE',
            'status' => 'active',
            'allowed_product_grades' => null,
        ]);

        $category = Category::factory()->create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato Local',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        // Default setting to null for clean testing
        BusinessSetting::query()->where('key', 'enable_multi_grade_ordering')->update(['value' => null]);
    }

    protected function tearDown(): void
    {
        // Ensure multi-grade setting is restored to null after testing
        BusinessSetting::query()->where('key', 'enable_multi_grade_ordering')->update(['value' => null]);

        parent::tearDown();
    }

    public function test_company_settings_page_renders_multi_grade_toggle_with_current_state(): void
    {
        // 1. When NULL, displays Disabled (OFF)
        BusinessSetting::query()->where('key', 'enable_multi_grade_ordering')->update(['value' => null]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'operations']));

        $response->assertOk()
            ->assertSee('Enable Multi-Grade Shop Ordering')
            ->assertSee('Disabled (OFF)');

        // 2. When '1', displays Active (ON)
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1']
        );

        $responseOn = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'operations']));

        $responseOn->assertOk()
            ->assertSee('Enable Multi-Grade Shop Ordering')
            ->assertSee('Active (ON)');
    }

    public function test_admin_can_enable_multi_grade_ordering(): void
    {
        BusinessSetting::query()->where('key', 'enable_multi_grade_ordering')->update(['value' => '0']);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf ERP',
                'default_purchaser_user_id' => BusinessSetting::where('key', 'default_purchaser_user_id')->value('value'),
                'enable_multi_grade_ordering' => '1',
                'active_tab' => 'operations',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit', ['tab' => 'operations']))
            ->assertSessionHas('success');

        $this->assertSame('1', BusinessSetting::where('key', 'enable_multi_grade_ordering')->value('value'));
        $this->assertTrue(app(MultiGradeOrderGate::class)->isFeatureEnabled());
    }

    public function test_admin_can_disable_multi_grade_ordering(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1']
        );

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf ERP',
                'default_purchaser_user_id' => BusinessSetting::where('key', 'default_purchaser_user_id')->value('value'),
                'enable_multi_grade_ordering' => '0',
                'active_tab' => 'operations',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit', ['tab' => 'operations']))
            ->assertSessionHas('success');

        $this->assertSame('0', BusinessSetting::where('key', 'enable_multi_grade_ordering')->value('value'));
        $this->assertFalse(app(MultiGradeOrderGate::class)->isFeatureEnabled());
    }

    public function test_company_settings_can_persist_large_warehouse_business_day_settings_without_truncation(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf ERP',
                'default_purchaser_user_id' => BusinessSetting::where('key', 'default_purchaser_user_id')->value('value'),
                'enable_multi_grade_ordering' => '1',
                'active_tab' => 'operations',
                'purchaser_business_day_verification_enabled' => '1',
                'purchaser_business_day_require_daily_submission' => '1',
                'business_day_warehouse_settings' => [
                    $warehouse->id => [
                        'enabled' => '1',
                        'require_daily_submission' => '1',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.company-settings.edit', ['tab' => 'operations']))
            ->assertSessionHas('success');

        $savedConfig = BusinessSetting::where('key', "purchaser_business_day_config_{$warehouse->id}")->value('value');
        $this->assertNotNull($savedConfig);
        $this->assertGreaterThan(255, strlen($savedConfig));
    }

    public function test_non_admin_cannot_access_or_update_multi_grade_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.company-settings.edit'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->regularUser)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Hacked Company',
                'enable_multi_grade_ordering' => '1',
            ])
            ->assertForbidden();
    }

    public function test_ashirwad_ordering_visibility_reflects_toggle_state_and_double_lock(): void
    {
        $gate = app(MultiGradeOrderGate::class);
        $ashirwadOwner = User::where('shop_id', $this->ashirwadShop->id)->first() ?? User::factory()->create([
            'shop_id' => $this->ashirwadShop->id,
            'email' => 'ashirwad_test_owner@greenleaf.com',
        ]);
        $ashirwadOwner->syncRoles(['shop']);

        $standardOwner = User::where('shop_id', $this->standardShop->id)->first() ?? User::factory()->create([
            'shop_id' => $this->standardShop->id,
            'email' => 'standard_test_owner@greenleaf.com',
        ]);
        $standardOwner->syncRoles(['shop']);

        // Scenario 1: Global OFF -> Ashirwad sees Grade A only
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '0']
        );

        $this->assertFalse($gate->isFeatureEnabled());
        $this->assertSame(['A'], $gate->allowedGradesForShop($this->ashirwadShop));
        $this->assertFalse($gate->canShopOrderGrade($this->ashirwadShop, 'B'));
        $this->assertFalse($gate->canShopOrderGrade($this->ashirwadShop, 'C'));

        $responseOff = $this->actingAs($ashirwadOwner)
            ->get(route('shop-owner.orders.create'));
        $responseOff->assertOk();
        $this->assertStringNotContainsString('item_grades[', $responseOff->getContent());

        // Scenario 2: Global ON -> Ashirwad sees Grade A, B, and C
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1']
        );

        $this->assertTrue($gate->isFeatureEnabled());
        $this->assertSame(['A', 'B', 'C'], $gate->allowedGradesForShop($this->ashirwadShop));
        $this->assertTrue($gate->canShopOrderGrade($this->ashirwadShop, 'B'));
        $this->assertTrue($gate->canShopOrderGrade($this->ashirwadShop, 'C'));

        $responseOn = $this->actingAs($ashirwadOwner)
            ->get(route('shop-owner.orders.create'));
        $responseOn->assertOk();
        $this->assertStringContainsString('Grade B', $responseOn->getContent());
        $this->assertStringContainsString('Grade C', $responseOn->getContent());
        $this->assertStringContainsString('item_grades[', $responseOn->getContent());

        // Scenario 3: Global ON -> Standard shop (Grade A only) remains Grade A only
        $this->assertSame(['A'], $gate->allowedGradesForShop($this->standardShop));
        $this->assertFalse($gate->canShopOrderGrade($this->standardShop, 'B'));

        $responseStandard = $this->actingAs($standardOwner)
            ->get(route('shop-owner.orders.create'));
        $responseStandard->assertOk();
        $this->assertStringNotContainsString('item_grades[', $responseStandard->getContent());

        // Scenario 4: Switching back to OFF -> Ashirwad immediately loses Grade B/C
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '0']
        );

        $this->assertFalse($gate->isFeatureEnabled());
        $this->assertSame(['A'], $gate->allowedGradesForShop($this->ashirwadShop));

        $responseOffAgain = $this->actingAs($ashirwadOwner)
            ->get(route('shop-owner.orders.create'));
        $responseOffAgain->assertOk();
        $this->assertStringNotContainsString('item_grades[', $responseOffAgain->getContent());
    }
}
