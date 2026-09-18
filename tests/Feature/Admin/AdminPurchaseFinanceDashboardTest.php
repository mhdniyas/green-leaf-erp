<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPurchaseFinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Supplier $supplier;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Alice Procurement']);
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create(['name' => 'Kashmir Apple Co']);
        $this->category = Category::factory()->create(['name' => 'Fresh Fruits']);
        $this->product = Product::factory()->create([
            'name' => 'Royal Gala Apple',
            'category_id' => $this->category->id,
            'unit' => 'kg',
        ]);
    }

    public function test_purchase_finance_dashboard_defaults_to_monthly_view(): void
    {
        $currentMonth = today('Asia/Kolkata')->format('Y-m');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertStatus(200);
        $response->assertSee('Purchase Finance');
        $response->assertSee('Purchase Financial Summary');
        $response->assertSee('MONTH FINANCE VIEW');
        $response->assertSee($currentMonth);
    }

    public function test_purchase_finance_dashboard_navigates_to_specific_month(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'month' => '2026-08',
            'period_mode' => 'month',
        ]));

        $response->assertStatus(200);
        $response->assertSee('August 2026');
        $response->assertSee('01 Aug 2026 – 31 Aug 2026');
    }

    public function test_purchase_finance_dashboard_supports_day_and_custom_period_modes(): void
    {
        // Day mode
        $dayResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period_mode' => 'day',
            'date' => '2026-08-15',
        ]));

        $dayResponse->assertStatus(200);
        $dayResponse->assertSee('15 Aug 2026');
        $dayResponse->assertSee('DAY FINANCE VIEW');

        // Custom mode
        $customResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'period_mode' => 'custom',
            'from' => '2026-08-10',
            'to' => '2026-08-20',
        ]));

        $customResponse->assertStatus(200);
        $customResponse->assertSee('10 Aug 2026 – 20 Aug 2026');
        $customResponse->assertSee('CUSTOM FINANCE VIEW');
    }

    public function test_purchase_finance_dashboard_displays_correct_metrics(): void
    {
        $today = now('Asia/Kolkata')->format('Y-m-d');
        $currentMonth = now('Asia/Kolkata')->format('Y-m');

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
            'cart_number' => 'CART-001-TEST',
            'discount_amount' => 0.0,
            'payment_method' => 'cash',
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit' => 'kg',
            'unit_price' => 150.00,
            'line_total' => 1500.00,
        ]);

        PurchaseInvoice::factory()->for($this->supplier)->create([
            'purchaser_cart_id' => $cart->id,
            'amount' => 1500.00,
            'discount_amount' => 0.00,
            'paid_amount' => 1500.00,
            'payment_method' => 'cash',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase', [
            'month' => $currentMonth,
            'period_mode' => 'month',
        ]));

        $response->assertStatus(200);
        $response->assertSee('1,500.00');
        $response->assertSee('Alice Procurement');
        $response->assertSee('Kashmir Apple Co');
        $response->assertSee('Fresh Fruits');
    }

    public function test_sidebar_renders_purchase_section_with_all_subsections(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase'));

        $response->assertStatus(200);
        $response->assertSee('Purchase');
        $response->assertSee(route('admin.cashbook.finance.purchase'));
        $response->assertSee(route('admin.cashbook.finance.purchase.reports'));
        $response->assertSee(route('admin.cashbook.finance.purchase.purchasers'));
        $response->assertSee(route('admin.cashbook.finance.purchase.vendors'));
        $response->assertSee(route('admin.cashbook.finance.purchase.invoices'));
        $response->assertSee(route('admin.cashbook.finance.purchase.categories'));
    }

    public function test_purchase_reports_nested_sidebar_renders_all_sub_reports_when_active(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases'));

        $response->assertStatus(200);
        $response->assertSee('Credit Purchases');
        $response->assertSee(route('admin.cashbook.finance.purchase.reports.credit-purchases'));
        $response->assertSee(route('admin.cashbook.finance.purchase.reports.daily'));
        $response->assertSee(route('admin.cashbook.finance.purchase.purchaser-expenses'));
        $response->assertSee(route('admin.cashbook.finance.purchase.reports.purchasers'));
        $response->assertSee(route('admin.cashbook.finance.purchase.reports.prices'));
        $response->assertSee(route('admin.cashbook.finance.purchase.reports.changed-items'));
        $response->assertSee(route('admin.cashbook.finance.purchase.reports.purchaser-prices'));
        $response->assertSee(route('admin.cashbook.finance.purchase.product-allotments.index'));
        $response->assertSee(route('admin.cashbook.purchaser-business-days.index'));
    }

    public function test_credit_purchases_report_renders_with_monthly_filters_and_period_modes(): void
    {
        // Monthly view
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases', [
            'month' => '2026-08',
            'period_mode' => 'month',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Credit Purchase Report');
        $response->assertSee('August 2026');
        $response->assertSee('Supplier Credit Payables');

        // Day view
        $dayResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases', [
            'period_mode' => 'day',
            'date' => '2026-08-15',
        ]));

        $dayResponse->assertStatus(200);
        $dayResponse->assertSee('15 Aug 2026');

        // Custom range
        $customResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.credit-purchases', [
            'period_mode' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-15',
            'status' => 'unpaid',
            'search' => 'Kashmir',
        ]));

        $customResponse->assertStatus(200);
        $customResponse->assertSee('01 Aug 2026 – 15 Aug 2026');
    }

    public function test_purchase_subsections_render_properly(): void
    {
        $subsections = [
            'purchasers' => route('admin.cashbook.finance.purchase.purchasers'),
            'vendors' => route('admin.cashbook.finance.purchase.vendors'),
            'categories' => route('admin.cashbook.finance.purchase.categories'),
            'invoices' => route('admin.cashbook.finance.purchase.invoices'),
        ];

        foreach ($subsections as $name => $url) {
            $response = $this->actingAs($this->admin)->get($url);
            $response->assertStatus(200);
            $response->assertSee(ucfirst($name));
            $response->assertSeeInOrder(['Month', 'Day', 'Custom']);
        }
    }

    public function test_purchase_subsections_standardized_period_filters_and_navigation(): void
    {
        $routes = [
            route('admin.cashbook.finance.purchase.purchasers'),
            route('admin.cashbook.finance.purchase.vendors'),
            route('admin.cashbook.finance.purchase.categories'),
            route('admin.cashbook.finance.purchase.invoices'),
        ];

        foreach ($routes as $url) {
            // Month mode
            $monthResp = $this->actingAs($this->admin)->get($url.'?period_mode=month&month=2026-08');
            $monthResp->assertOk()
                ->assertSee('August 2026')
                ->assertSee('month=2026-07', false)
                ->assertSee('month=2026-09', false);

            // Day mode
            $dayResp = $this->actingAs($this->admin)->get($url.'?period_mode=day&date=2026-08-15');
            $dayResp->assertOk()
                ->assertSee('value="2026-08-15"', false)
                ->assertSee('Apply Day');

            // Custom mode
            $customResp = $this->actingAs($this->admin)->get($url.'?period_mode=custom&from=2026-08-01&to=2026-08-15');
            $customResp->assertOk()
                ->assertSee('value="2026-08-01"', false)
                ->assertSee('value="2026-08-15"', false)
                ->assertSee('Apply Range');

            // Backward compatibility with legacy parameters
            $legacyResp = $this->actingAs($this->admin)->get($url.'?period=month&start_date=2026-08-01&end_date=2026-08-31');
            $legacyResp->assertOk()
                ->assertSee('August 2026');
        }
    }
}
