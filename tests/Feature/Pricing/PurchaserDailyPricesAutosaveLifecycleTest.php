<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PurchaserDailyPricesAutosaveLifecycleTest extends TestCase
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

        Carbon::setTestNow('2026-10-21 08:30:00');

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
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. Page renders script with keepalive: true for all fetch calls.
     */
    public function test_view_configures_keepalive_on_fetch_requests(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('keepalive: true', $content);
        $this->assertStringContainsString('const inFlightRequests = {};', $content);
    }

    /**
     * 2. Page implements browser unload and visibility lifecycle guards.
     */
    public function test_view_registers_beforeunload_pagehide_and_visibilitychange_guards(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString("window.addEventListener('beforeunload'", $content);
        $this->assertStringContainsString("window.addEventListener('pagehide'", $content);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $content);
        $this->assertStringContainsString('flushPendingDebounces();', $content);
        $this->assertStringContainsString('hasPendingChanges()', $content);
    }

    /**
     * 3. Navigation elements are wired to await pending saves before proceeding.
     */
    public function test_view_intercepts_date_search_and_category_navigation(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('awaitPendingSavesThen', $content);
        $this->assertStringContainsString('header-date-input', $content);
        $this->assertStringContainsString('category-filter-select', $content);
        $this->assertStringContainsString('category-pill-link', $content);
        $this->assertStringContainsString('initNavigationSafety', $content);
    }

    /**
     * 4. Accurate save indicators and error feedback are wired in JavaScript.
     */
    public function test_view_displays_accurate_save_and_failure_states(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString("'Saving...'", $content);
        $this->assertStringContainsString("'Failed'", $content);
        $this->assertStringContainsString('showGlobalSaveInfo', $content);
        $this->assertStringContainsString('border-rose-300', $content);
        $this->assertStringContainsString('bg-rose-50', $content);
    }

    /**
     * 5. Switching grade tabs flushes pending debounces safely.
     */
    public function test_switch_grade_tab_flushes_pending_debounces(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('function switchGradeTab(grade)', $content);
        $this->assertStringContainsString('flushPendingDebounces();', $content);
    }

    /**
     * 6. Backend handles consecutive Grade A, Grade B and Grade C updates correctly.
     */
    public function test_consecutive_grade_updates_save_accurately(): void
    {
        // 1. Grade A update
        $resA = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'refresh_invoices_only' => false,
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'purchase_price' => 60.00,
                ],
            ],
        ]);

        $resA->assertOk();
        $resA->assertJson(['success' => true]);

        // 2. Grade B manual override
        $resB = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'B',
                    'price' => 58.00,
                    'is_manual' => true,
                ],
            ],
        ]);

        $resB->assertOk();
        $resB->assertJson(['success' => true]);

        // 3. Grade C manual override
        $resC = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'C',
                    'price' => 52.00,
                    'is_manual' => true,
                ],
            ],
        ]);

        $resC->assertOk();
        $resC->assertJson(['success' => true]);

        // 4. Reset Grade B back to default
        $resReset = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'grade' => 'B',
                    'reset_to_default' => true,
                ],
            ],
        ]);

        $resReset->assertOk();
        $resReset->assertJson(['success' => true]);

        $appC = DailyPriceApproval::query()
            ->where('product_id', $this->product->id)
            ->where('grade', 'C')
            ->whereDate('business_date', '2026-10-21')
            ->first();

        $this->assertNotNull($appC);
        $this->assertEquals(52.00, (float) $appC->price_a);
    }

    /**
     * 7. Script block in rendered page has balanced curly braces and declares handlers.
     */
    public function test_script_has_balanced_braces_and_declares_handlers(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily-prices', ['date' => '2026-10-21']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('function handlePriceInput', $content);
        $this->assertStringContainsString('function handleGradeInput', $content);
        $this->assertStringContainsString('function switchGradeTab', $content);
        $this->assertStringContainsString('function savePriceAuto', $content);
        $this->assertStringContainsString('function saveGradePrice', $content);

        // Extract script and verify brace balance
        preg_match_all('/<script>(.*?)<\/script>/s', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        // Find the main daily-prices script containing handlePriceInput
        $mainScript = null;
        foreach ($matches[1] as $s) {
            if (str_contains($s, 'handlePriceInput')) {
                $mainScript = $s;
                break;
            }
        }

        $this->assertNotNull($mainScript);

        // Strip comments and strings
        $clean = preg_replace('/\/\*.*?\*\/|\/\/.*?$/m', '', $mainScript);
        $clean = preg_replace('/\'(?:\\\\\'|[^\'])*\'|"(?:\\\\"|[^"])*"|`(?:\\\\`|[^`])*`/', '""', $clean);

        $openCount = substr_count($clean, '{');
        $closeCount = substr_count($clean, '}');
        $this->assertEquals($openCount, $closeCount, "Unbalanced braces in script: {$openCount} '{' vs {$closeCount} '}'");
    }
}
