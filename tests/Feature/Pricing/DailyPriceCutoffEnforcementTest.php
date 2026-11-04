<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class DailyPriceCutoffEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private User $purchaseManager;

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
        Role::findOrCreate('shop', 'web');

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Daily Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->purchaseManager = User::factory()->create(['name' => 'Purchase Manager']);
        $this->purchaseManager->assignRole('purchase');

        $this->category = Category::factory()->create([
            'name' => 'Fresh Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Green Tomato',
            'base_price' => 40.00,
            'vendor_price' => 40.00,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['code' => 'A', 'description' => 'Grade A price group', 'is_active' => true]
        );

        $this->shop = Shop::factory()->create([
            'name' => 'Outlet 1',
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
     * 1. Purchaser can update daily prices before cutoff (e.g. 8:59 AM).
     */
    public function test_purchaser_can_update_daily_prices_before_cutoff(): void
    {
        Carbon::setTestNow('2026-10-21 08:59:00');

        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 65.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(65.00, (float) $response->json('selling_price_a'));

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $this->product->id,
            'grade' => 'A',
            'price_a' => 65.00,
        ]);
    }

    /**
     * 2. Purchaser is blocked at exactly the cutoff time (09:00 AM inclusive).
     */
    public function test_purchaser_is_blocked_at_exact_cutoff_time(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');

        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 70.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Daily price updates are locked after 09:00 AM. Only administrators can change prices.');

        $this->assertDatabaseMissing('daily_price_approvals', [
            'product_id' => $this->product->id,
            'price_a' => 70.00,
        ]);
    }

    /**
     * 3. Purchaser is blocked after the cutoff time (e.g. 09:01 AM and 11:30 AM).
     */
    public function test_purchaser_is_blocked_after_cutoff_time(): void
    {
        Carbon::setTestNow('2026-10-21 09:01:00');

        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 75.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('success', false);

        // Also test web form submission receives 403
        $webResponse = $this->actingAs($this->purchaser)->post(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 75.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $webResponse->assertForbidden();
    }

    /**
     * 4. Admin can update daily prices after cutoff (e.g. 09:01 AM or any time).
     */
    public function test_admin_can_update_daily_prices_after_cutoff(): void
    {
        Carbon::setTestNow('2026-10-21 09:01:00');

        $response = $this->actingAs($this->admin)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 80.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(80.00, (float) $response->json('selling_price_a'));

        $this->assertDatabaseHas('daily_price_approvals', [
            'product_id' => $this->product->id,
            'grade' => 'A',
            'price_a' => 80.00,
        ]);
    }

    /**
     * 5. Purchase Manager (purchase role) is allowed before cutoff and blocked at/after cutoff.
     */
    public function test_purchase_manager_allowed_before_cutoff_and_denied_after(): void
    {
        // 8:59 AM: allowed
        Carbon::setTestNow('2026-10-21 08:59:00');
        $allowed = $this->actingAs($this->purchaseManager)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 55.00,
                    'grade' => 'A',
                ],
            ],
        ]);
        $allowed->assertOk();

        // 9:00 AM: denied
        Carbon::setTestNow('2026-10-21 09:00:00');
        $deniedExact = $this->actingAs($this->purchaseManager)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 56.00,
                    'grade' => 'A',
                ],
            ],
        ]);
        $deniedExact->assertForbidden();

        // 9:01 AM: denied
        Carbon::setTestNow('2026-10-21 09:01:00');
        $deniedAfter = $this->actingAs($this->purchaseManager)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 57.00,
                    'grade' => 'A',
                ],
            ],
        ]);
        $deniedAfter->assertForbidden();
    }

    /**
     * 6. Custom cutoff configured by Admin is dynamically respected.
     */
    public function test_custom_cutoff_configured_by_admin_is_dynamically_enforced(): void
    {
        // Admin configures 08:30:00 cutoff in business_settings
        BusinessSetting::updateOrCreate(
            ['key' => 'daily_price_cutoff_time'],
            ['value' => '08:30:00'],
        );

        // 08:29:59 AM: allowed
        Carbon::setTestNow('2026-10-21 08:29:59');
        $responseBefore = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 60.00,
                    'grade' => 'A',
                ],
            ],
        ]);
        $responseBefore->assertOk();

        // 08:30:00 AM: blocked with dynamic error message reflecting 08:30 AM
        Carbon::setTestNow('2026-10-21 08:30:00');
        $responseCutoff = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 62.00,
                    'grade' => 'A',
                ],
            ],
        ]);
        $responseCutoff->assertForbidden();
        $responseCutoff->assertJsonPath('message', 'Daily price updates are locked after 08:30 AM. Only administrators can change prices.');
    }

    /**
     * 7. Missing cutoff setting falls back safely to default 09:00:00.
     */
    public function test_missing_cutoff_setting_uses_nine_am_default(): void
    {
        BusinessSetting::where('key', 'daily_price_cutoff_time')->delete();

        $service = app(PurchaserBusinessDayService::class);
        $this->assertEquals('09:00:00', $service->dailyPriceCutoffTime());

        Carbon::setTestNow('2026-10-21 08:59:59');
        $this->assertTrue($service->canUserUpdateDailyPrices($this->purchaser));

        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->assertFalse($service->canUserUpdateDailyPrices($this->purchaser));
        $this->assertTrue($service->canUserUpdateDailyPrices($this->admin));
    }

    /**
     * 8. Grade A, Grade B, and Grade C updates are all protected by the cutoff.
     */
    public function test_grade_a_b_c_updates_are_all_blocked_after_cutoff(): void
    {
        Carbon::setTestNow('2026-10-21 09:15:00');

        // Attempt Grade A update
        $resA = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [['product_id' => $this->product->id, 'grade' => 'A', 'price' => 50.00]],
        ]);
        $resA->assertForbidden();

        // Attempt Grade B update
        $resB = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [['product_id' => $this->product->id, 'grade' => 'B', 'price' => 45.00, 'is_manual' => true]],
        ]);
        $resB->assertForbidden();

        // Attempt Grade C update
        $resC = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [['product_id' => $this->product->id, 'grade' => 'C', 'price' => 40.00, 'is_manual' => true]],
        ]);
        $resC->assertForbidden();
    }

    /**
     * 9. Date selection or business-day rollover cannot bypass current-time cutoff.
     */
    public function test_date_selection_cannot_bypass_current_time_cutoff(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');

        // Try historical date
        $resPast = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-15',
            'prices' => [['product_id' => $this->product->id, 'price' => 55.00]],
        ]);
        $resPast->assertForbidden();

        // Try future date
        $resFuture = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-22',
            'prices' => [['product_id' => $this->product->id, 'price' => 55.00]],
        ]);
        $resFuture->assertForbidden();

        // Try passing business_date parameter
        $resBusinessDate = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'business_date' => '2026-10-20',
            'prices' => [['product_id' => $this->product->id, 'price' => 55.00]],
        ]);
        $resBusinessDate->assertForbidden();
    }

    /**
     * 10. Rejected requests cause zero database changes.
     */
    public function test_rejected_requests_cause_zero_database_changes(): void
    {
        Carbon::setTestNow('2026-10-21 08:30:00');

        // Create baseline approval
        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 50.00,
            'price_b' => 45.00,
            'price_c' => 40.00,
            'purchase_price' => 35.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $approvalsCountBefore = DailyPriceApproval::count();

        // Advance to after cutoff
        Carbon::setTestNow('2026-10-21 09:30:00');

        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 99.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertForbidden();

        $this->assertEquals($approvalsCountBefore, DailyPriceApproval::count());

        $approval = DailyPriceApproval::where('product_id', $this->product->id)
            ->whereDate('business_date', '2026-10-21')
            ->first();

        $this->assertEquals(50.00, (float) $approval->price_a);
    }

    /**
     * 11. Timezone boundary behavior strictly follows Asia/Kolkata company timezone.
     */
    public function test_timezone_boundary_behavior_strictly_evaluates_asia_kolkata(): void
    {
        $service = app(PurchaserBusinessDayService::class);

        // 03:29:59 UTC = 08:59:59 IST (before 9:00 AM IST cutoff)
        $utcBefore = Carbon::parse('2026-10-21 03:29:59', 'UTC');
        $this->assertFalse($service->isDailyPriceCutoffPassed($utcBefore));
        $this->assertTrue($service->canUserUpdateDailyPrices($this->purchaser, $utcBefore));

        // 03:30:00 UTC = 09:00:00 IST (exact 9:00 AM IST cutoff)
        $utcAt = Carbon::parse('2026-10-21 03:30:00', 'UTC');
        $this->assertTrue($service->isDailyPriceCutoffPassed($utcAt));
        $this->assertFalse($service->canUserUpdateDailyPrices($this->purchaser, $utcAt));
        $this->assertTrue($service->canUserUpdateDailyPrices($this->admin, $utcAt));
    }

    /**
     * 12. Finalized invoices remain locked even when Admin overrides daily price after cutoff.
     */
    public function test_finalized_invoices_remain_unchanged_when_admin_updates_after_cutoff(): void
    {
        Carbon::setTestNow('2026-10-21 08:00:00');

        // Create initial approved price
        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => '2026-10-21',
            'grade' => 'A',
            'price_a' => 50.00,
            'price_b' => 45.00,
            'price_c' => 40.00,
            'purchase_price' => 35.00,
            'price_unit' => 'kg',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Create a finalized / locked invoice
        $finalizedInvoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-21',
            'status' => 'finalized',
            'finalized_at' => now(),
            'final_total' => 500.00,
        ]);

        $itemLocked = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $finalizedInvoice->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'unit_price' => 50.00,
            'approved_qty' => 10,
            'delivered_qty' => 10,
            'final_line_total' => 500.00,
        ]);

        $this->assertTrue($finalizedInvoice->isFinalLocked());

        // Fast forward to after cutoff
        Carbon::setTestNow('2026-10-21 09:30:00');

        // Admin updates price to 85.00
        $response = $this->actingAs($this->admin)->postJson(route('purchaser.daily-prices.update'), [
            'date' => '2026-10-21',
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'price' => 85.00,
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertOk();

        // Daily price was updated to 85.00
        $this->assertEquals(85.00, (float) DailyPriceApproval::where('product_id', $this->product->id)->whereDate('business_date', '2026-10-21')->value('price_a'));

        // Finalized invoice item MUST remain untouched at 50.00
        $this->assertEquals(50.00, (float) $itemLocked->fresh()->unit_price);
        $this->assertEquals(500.00, (float) $finalizedInvoice->fresh()->final_total);
    }
}
