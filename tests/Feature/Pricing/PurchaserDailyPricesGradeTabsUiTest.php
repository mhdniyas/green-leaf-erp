<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PurchaserDailyPricesGradeTabsUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Category $category;

    private Product $product;

    private ShopPriceGroup $groupA;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchaser', 'web');
        Role::findOrCreate('purchase', 'web');

        $this->admin = User::factory()->create(['name' => 'Super Admin']);
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Market Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->category = Category::factory()->create([
            'name' => 'Fresh Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Crisp Capsicum',
            'base_price' => 50.00,
            'vendor_price' => 50.00,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['code' => 'A', 'description' => 'Grade A price tier', 'is_active' => true]
        );

        $this->shop = Shop::factory()->create([
            'name' => 'Downtown Outlet',
            'shop_price_group_id' => $this->groupA->id,
            'status' => 'active',
            'allowed_product_grades' => ['allowed' => ['A', 'B', 'C']],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Grade A tab is selected by default on initial page load.
     */
    public function test_grade_a_is_selected_by_default(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $response->assertViewHas('activeGrade', 'A');

        // Verify Grade A tab button is active
        $response->assertSee('id="tab-btn-grade-a"', false);
        $response->assertSee('id="tab-btn-grade-b"', false);
        $response->assertSee('id="tab-btn-grade-c"', false);

        // Grade A panel is active, B and C are hidden
        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-a(?![^"]*hidden)[^"]*"[^>]*data-grade-panel="A"/s', $content);
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-b[^"]*hidden[^"]*"[^>]*data-grade-panel="B"/s', $content);
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-c[^"]*hidden[^"]*"[^>]*data-grade-panel="C"/s', $content);

        // Header A is active, Header B and C are hidden
        $this->assertMatchesRegularExpression('/id="grade-header-a"[^>]*class="grade-header\s+grid-cols-/s', $content);
        $this->assertMatchesRegularExpression('/id="grade-header-b"[^>]*class="grade-header\s+hidden\s+grid-cols-/s', $content);
        $this->assertMatchesRegularExpression('/id="grade-header-c"[^>]*class="grade-header\s+hidden\s+grid-cols-/s', $content);

        // Grade A input has tabindex="0", while Grade B and C inputs have tabindex="-1"
        $this->assertStringContainsString('id="price-'.$this->product->id.'"', $content);
        $this->assertStringContainsString('tabindex="0"', $content);
        $this->assertStringContainsString('id="price-b-'.$this->product->id.'"', $content);
        $this->assertStringContainsString('tabindex="-1"', $content);
    }

    /**
     * 2. Grade B activates when grade=B is requested.
     */
    public function test_grade_b_activates_correctly(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', [
            'date' => '2026-10-21',
            'grade' => 'B',
        ]));

        $response->assertOk();
        $response->assertViewHas('activeGrade', 'B');

        $content = $response->getContent();

        // Grade B panel is active, A and C are hidden
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-a[^"]*hidden[^"]*"[^>]*data-grade-panel="A"/s', $content);
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-b(?![^"]*hidden)[^"]*"[^>]*data-grade-panel="B"/s', $content);
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-c[^"]*hidden[^"]*"[^>]*data-grade-panel="C"/s', $content);

        // Header B is active, Header A and C are hidden
        $this->assertMatchesRegularExpression('/id="grade-header-a"[^>]*class="grade-header\s+hidden\s+grid-cols-/s', $content);
        $this->assertMatchesRegularExpression('/id="grade-header-b"[^>]*class="grade-header\s+grid-cols-/s', $content);
        $this->assertMatchesRegularExpression('/id="grade-header-c"[^>]*class="grade-header\s+hidden\s+grid-cols-/s', $content);

        // Grade B input has tabindex="0"
        $this->assertMatchesRegularExpression('/id="price-b-'.$this->product->id.'".*?tabindex="0"/s', $content);
        // Grade A input has tabindex="-1"
        $this->assertMatchesRegularExpression('/id="price-'.$this->product->id.'".*?tabindex="-1"/s', $content);

        // Verify Grade B reference and percentage columns appear
        $response->assertSee('GRADE A REF');
        $response->assertSee('CALCULATED');
    }

    /**
     * 3. Grade C activates when grade=C is requested.
     */
    public function test_grade_c_activates_correctly(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', [
            'date' => '2026-10-21',
            'grade' => 'C',
        ]));

        $response->assertOk();
        $response->assertViewHas('activeGrade', 'C');

        $content = $response->getContent();

        // Grade C panel is active, A and B are hidden
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-a[^"]*hidden[^"]*"[^>]*data-grade-panel="A"/s', $content);
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-b[^"]*hidden[^"]*"[^>]*data-grade-panel="B"/s', $content);
        $this->assertMatchesRegularExpression('/class="[^"]*grade-panel-c(?![^"]*hidden)[^"]*"[^>]*data-grade-panel="C"/s', $content);

        // Header C is active, Header A and B are hidden
        $this->assertMatchesRegularExpression('/id="grade-header-a"[^>]*class="grade-header\s+hidden\s+grid-cols-/s', $content);
        $this->assertMatchesRegularExpression('/id="grade-header-b"[^>]*class="grade-header\s+hidden\s+grid-cols-/s', $content);
        $this->assertMatchesRegularExpression('/id="grade-header-c"[^>]*class="grade-header\s+grid-cols-/s', $content);

        // Grade C input has tabindex="0"
        $this->assertMatchesRegularExpression('/id="price-c-'.$this->product->id.'".*?tabindex="0"/s', $content);
        // Grade A input has tabindex="-1"
        $this->assertMatchesRegularExpression('/id="price-'.$this->product->id.'".*?tabindex="-1"/s', $content);
    }

    /**
     * 4. Non-admin user after cutoff sees disabled inputs and cutoff banner.
     */
    public function test_non_admin_user_after_cutoff_sees_disabled_inputs_and_banner(): void
    {
        Carbon::setTestNow('2026-10-21 09:30:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $response->assertViewHas('canUpdatePrices', false);
        $response->assertViewHas('isDailyPriceCutoffPassed', true);

        // Banner is displayed
        $response->assertSee('Daily price editing closed at 09:00 AM. Only Admin can update prices.');
        $response->assertSee('cutoff-lock-banner');

        // Inputs are disabled and readonly
        $content = $response->getContent();
        $this->assertStringContainsString('disabled readonly', $content);
    }

    /**
     * 5. Admin user after cutoff sees enabled inputs and admin override banner.
     */
    public function test_admin_user_after_cutoff_sees_enabled_inputs_and_admin_banner(): void
    {
        Carbon::setTestNow('2026-10-21 09:30:00');

        $response = $this->actingAs($this->admin)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $response->assertViewHas('canUpdatePrices', true);
        $response->assertViewHas('isAdmin', true);
        $response->assertViewHas('isDailyPriceCutoffPassed', true);

        // Admin override banner is displayed
        $response->assertSee('Admin Override Active');
        $response->assertSee('You are authorized to update prices as an administrator.');
        $response->assertDontSee('cutoff-lock-banner');

        // Inputs are not disabled for Admin
        $content = $response->getContent();
        $this->assertStringNotContainsString('disabled readonly', $content);
    }

    /**
     * 6. Dynamic cutoff time configured in settings is shown accurately in UI.
     */
    public function test_dynamic_cutoff_time_is_reflected_in_banner(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'daily_price_cutoff_time'],
            ['value' => '08:30:00'],
        );

        Carbon::setTestNow('2026-10-21 08:45:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $response->assertSee('Daily price editing closed at 08:30 AM. Only Admin can update prices.');
    }

    /**
     * 7. CSS rules guarantee inactive panels and headers are hidden with display: none !important.
     */
    public function test_panel_and_header_css_guarantees_only_selected_grade_is_displayed(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        // Enforce that .grade-panel.hidden has display: none !important
        $this->assertStringContainsString('.grade-panel.hidden', $content);
        $this->assertStringContainsString('.grade-header.hidden', $content);
        $this->assertMatchesRegularExpression('/\.grade-panel\.hidden\s*\{\s*display:\s*none\s*!important;\s*\}/', $content);
        $this->assertMatchesRegularExpression('/\.grade-header\.hidden\s*\{\s*display:\s*none\s*!important;\s*\}/', $content);
    }

    /**
     * 8. Grade tabs are hidden and clean single card layout is used when no shop has multi-grade enabled.
     */
    public function test_grade_tabs_are_hidden_when_no_shop_has_multi_grade_enabled(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        // Explicitly disable multi grade on shop and globally
        $this->shop->update(['allowed_product_grades' => ['allowed' => ['A']]]);
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '0'],
        );

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $response->assertViewHas('hasMultiGradeEnabled', false);
        $response->assertViewHas('showGradeTabs', false);

        // Grade tab buttons and Grade B/C panels are NOT rendered
        $response->assertDontSee('id="tab-btn-grade-a"', false);
        $response->assertDontSee('id="tab-btn-grade-b"', false);
        $response->assertDontSee('id="tab-btn-grade-c"', false);
        $response->assertDontSee('data-grade-panel="B"', false);
        $response->assertDontSee('data-grade-panel="C"', false);

        // Cutoff indicator is still displayed
        $response->assertSee('Daily Cutoff:');
        $response->assertSee('09:00 AM');
    }

    /**
     * 9. Master git card grid and widths are maintained for single card styling.
     */
    public function test_master_git_card_grid_and_widths_are_rendered(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        // Check CSS definitions for master git single card layout
        $this->assertStringContainsString('grid-template-columns: minmax(105px, 1.25fr) 56px 84px 82px;', $content);
        $this->assertStringContainsString('width: 82px;', $content);
        $this->assertStringContainsString('width: 58px;', $content);
        $this->assertStringNotContainsString('mobile-area-product', $content);
    }
}
