<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderRevision;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShopAccountingCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /**
     * Test that the shop owner gets a simplified sidebar.
     */
    public function test_shop_owner_gets_simplified_sidebar(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_1',
            'name' => 'Shop Test One',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        // Should see dashboard, cart, deliveries, finance, and approval history
        $response->assertSee('Dashboard');
        $response->assertSee('Cart');
        $response->assertDontSee('Daily Price Board');
        $response->assertSee('Deliveries');
        $response->assertSee('Finance');
        $response->assertSee('Approval History');
        $response->assertSee('Go to top');
        $response->assertSee('Go to bottom');
        $response->assertSee('app-dialog-root');
        $response->assertSee('window.showAppAlert');
        $response->assertSee('shop-owner-mobile-sidebar-open');
        $response->assertSee('shop-owner-mobile-sidebar');

        // Should not see inventory/purchasing group items which are reserved for other roles
        $response->assertDontSee('Sorting Checklist');
        $response->assertDontSee('Requisition Board');
        $response->assertDontSee('Suppliers');
    }

    public function test_shop_owner_daily_price_board_is_removed(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_PRICE_TEST',
            'name' => 'Price Board Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->get('/shop-owner/daily-prices');

        $response->assertNotFound();
    }

    public function test_shop_owner_cannot_access_internal_purchase_orders(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_2',
            'name' => 'Shop Test Two',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $supplier = Supplier::factory()->create();
        $draftPO = PurchaseOrder::create([
            'po_number' => 'PO-TEST-DRAFT',
            'supplier_id' => $supplier->id,
            'status' => POStatus::Draft,
            'order_date' => now()->format('Y-m-d'),
            'created_by' => $shopOwner->id,
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($shopOwner)->get(route('purchasing.orders.index'));
        $response->assertForbidden();
    }

    public function test_shop_owner_dashboard_shows_accepted_revision_status_label(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_REV_STATUS',
            'name' => 'Revision Status Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->addDay()->toDateString(),
            'created_by' => $shopOwner->id,
            'latest_revision_no' => 2,
        ]);
        $product = Product::factory()->create();

        ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'applied',
            'reason' => 'Need extra quantity',
            'requested_by' => $shopOwner->id,
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ])->items()->create([
            'product_id' => $product->id,
            'old_requested_qty' => 5.00,
            'new_requested_qty' => 8.00,
            'delta_qty' => 3.00,
            'final_approved_qty' => 8.00,
        ]);

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        $response->assertSee('Update #2 Accepted');
    }

    public function test_shop_owner_dashboard_uses_purchaser_style_mobile_bottom_nav(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_MOBILE_NAV',
            'name' => 'Mobile Nav Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        $response->assertSee('bottom-5 z-50 px-5 lg:hidden', false);
        $response->assertSee('h-[60px] max-w-md items-center gap-1 rounded-[2rem]', false);
        $response->assertSee('h-11 flex-1 items-center justify-center gap-1.5 rounded-[1.25rem]', false);
    }

    /**
     * Test the consolidated Finance dashboard and retired internal finance URLs.
     */
    public function test_shop_owner_uses_shop_finance_and_cannot_access_internal_finance(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_3',
            'name' => 'Shop Test Three',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.finance.index'));
        $response->assertOk();
        $response->assertSee('Finance');

        $this->actingAs($shopOwner)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.statement.export.csv'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.statement.export.pdf'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.accounts.index'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.reports.pnl'))->assertForbidden();
    }

    /**
     * Test Deliveries Dashboard is scoped to the user's shop.
     */
    public function test_deliveries_dashboard_scoped_to_shop_owner_shop(): void
    {
        $shopCasio = Shop::create(['code' => 'SHOP_CASIO_T', 'name' => 'Casio Test Outlet']);
        $shopBudegere = Shop::create(['code' => 'SHOP_BUDEGERE_T', 'name' => 'Budegere Test Outlet']);

        $casioOwner = User::factory()->create([
            'shop_id' => $shopCasio->id,
        ]);
        $casioOwner->assignRole('shop');

        $budegereOwner = User::factory()->create([
            'shop_id' => $shopBudegere->id,
        ]);
        $budegereOwner->assignRole('shop');

        // Create an order for Casio Test Outlet
        ShopOrder::create([
            'shop_id' => $shopCasio->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $casioOwner->id,
        ]);

        // Create an order for Budegere Test Outlet
        ShopOrder::create([
            'shop_id' => $shopBudegere->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $budegereOwner->id,
        ]);

        // Casio owner accesses dashboard
        $casioResponse = $this->actingAs($casioOwner)
            ->get(route('inventory.deliveries.dashboard'));

        $casioResponse->assertOk();
        $casioResponse->assertSee('Casio Test Outlet');
        $casioResponse->assertDontSee('Budegere Test Outlet');

        // Budegere owner accesses dashboard
        $budegereResponse = $this->actingAs($budegereOwner)
            ->get(route('inventory.deliveries.dashboard'));

        $budegereResponse->assertOk();
        $budegereResponse->assertSee('Budegere Test Outlet');
        $budegereResponse->assertDontSee('Casio Test Outlet');
    }

    public function test_shop_owner_delivery_details_uses_mobile_friendly_checkin_flow(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_DELIVERY_UI',
            'name' => 'Delivery Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $shopOwner->id,
            'is_allocation_completed' => true,
        ]);

        $response = $this->actingAs($shopOwner)
            ->get(route('shop-owner.deliveries.show', $order->order_number));

        $response->assertOk();
        $response->assertSee('Step 1');
        $response->assertSee('Receive Full Order');
        $response->assertSee('Confirm Delivery Check-In');
        $response->assertSee('Step 3');
        $response->assertSee('Shortage Summary');
    }

    public function test_shop_owner_marketplace_flow_uses_cart_language_and_hides_totals(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_MARKETPLACE_UI',
            'name' => 'Marketplace Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.create'));

        $response->assertOk();
        $response->assertSee('Marketplace');
        $response->assertSee('Add to Cart');
        $response->assertSee('Open Cart');
        $response->assertSee('Submit Daily Order');
        $response->assertDontSee('Estimated Total');
        $response->assertDontSee('Review Requisition');
    }

    public function test_standard_shop_owner_can_open_accounting_bills_and_request_payment(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_ACC_BILL',
            'name' => 'Bills Shop',
            'accounting_mode' => 'standard',
            'accounting_enabled' => false,
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $shopOwner->id,
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-BILLS-001',
            'business_date' => today()->toDateString(),
            'status' => 'payment_pending',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 250.00,
            'shortage_total' => 0.00,
            'discount_total' => 0.00,
            'final_total' => 250.00,
            'paid_amount' => 0.00,
            'balance_amount' => 250.00,
        ]);

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index'))
            ->assertOk()
            ->assertSee('Bills and balance to be paid')
            ->assertSee($invoice->invoice_number)
            ->assertSee('Send Payment Request');

        $this->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'invoice_id' => $invoice->id,
                'amount_mode' => 'balance_due',
                'shop_note' => 'Collect full bill today.',
            ])
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'bills']));

        $this->assertDatabaseHas('shop_invoice_payment_requests', [
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $shop->id,
            'requested_by' => $shopOwner->id,
            'request_type' => 'balance_due',
            'requested_amount' => 250.00,
            'status' => 'pending',
        ]);
    }

    public function test_shop_owner_cart_screen_shows_approval_history_copy(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_CART_UI',
            'name' => 'Cart Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.index'));

        $response->assertOk();
        $response->assertSee('Cart');
        $response->assertSee('Tomorrow Cart Snapshot');
        $response->assertSee('Approval History');
        $response->assertDontSee('Recent Orders');
    }

    public function test_shop_owner_marketplace_marks_empty_cart_validation_banner_for_js_dismissal(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_CART_ERROR_UI',
            'name' => 'Cart Error Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->from(route('shop-owner.orders.create'))
            ->followingRedirects()
            ->post(route('requisitions.store'), [
                'items' => [],
            ]);

        $response->assertOk();
        $response->assertSee('Requisition cannot be empty.');
        $response->assertSee('data-items-error-banner', false);
    }

    public function test_all_shop_owners_can_open_accounting_and_owned_shops_also_get_cashbook(): void
    {
        $ownedShop = Shop::create([
            'code' => 'OWNED_SHOP_NAV',
            'name' => 'Owned Shop Nav',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);
        $standardShop = Shop::create([
            'code' => 'STANDARD_SHOP_NAV',
            'name' => 'Standard Shop Nav',
            'accounting_mode' => 'standard',
            'accounting_enabled' => false,
        ]);

        $ownedUser = User::factory()->create(['shop_id' => $ownedShop->id]);
        $ownedUser->assignRole('shop');

        $standardUser = User::factory()->create(['shop_id' => $standardShop->id]);
        $standardUser->assignRole('shop');

        $this->actingAs($ownedUser)
            ->get(route('shop-owner.dashboard'))
            ->assertOk()
            ->assertSee('Accounting');

        $this->actingAs($standardUser)
            ->get(route('shop-owner.dashboard'))
            ->assertOk()
            ->assertSee('Accounting');

        $this->actingAs($standardUser)
            ->get(route('shop-owner.accounting.index'))
            ->assertOk()
            ->assertSee('Bills and balance to be paid')
            ->assertDontSee('Daily cashbook and expenses');
    }

    public function test_owned_shop_cashbook_uses_seeded_categories(): void
    {
        $this->seed(ShopAccountingCategorySeeder::class);

        $shop = Shop::create([
            'code' => 'SHOP-CASH-SEED',
            'name' => 'Seeded Accounting Shop',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
            'reserve_amount' => 725.50,
        ]);

        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');

        ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'final_total' => 880,
            'balance_amount' => 880,
        ]);

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'cashbook']))
            ->assertOk()
            ->assertSee('Daily shop ledger')
            ->assertSee('Add Income / Expense')
            ->assertSee('Reserve Cash')
            ->assertSee('725.50')
            ->assertSee('Sales Income - Cash')
            ->assertSee('Warehouse Delivery Invoice')
            ->assertSee('880.00')
            ->assertSee('Cash Purchase')
            ->assertSee('Staff Salary')
            ->assertSee('Other Income')
            ->assertSee('Other Expense')
            ->assertSee('cashbook-line-category-trigger', false)
            ->assertSee('name="opening_cash" value="725.50"', false);
    }

    public function test_owned_shop_owner_can_submit_entry_receive_recheck_and_resubmit(): void
    {
        $shop = Shop::create([
            'code' => 'OWNED_ACC_FLOW',
            'name' => 'Owned Accounting Shop',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);

        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');

        $admin = User::factory()->create();
        $admin->givePermissionTo('accounting.entry.review');

        $incomeCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'income',
            'name' => 'Sales Income',
            'is_active' => true,
        ]);
        $expenseCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Daily Expense',
            'is_active' => true,
        ]);

        $payload = [
            'business_date' => '2026-06-24',
            'submission_action' => 'submit',
            'opening_cash' => 500,
            'closing_cash' => 700,
            'notes' => 'Daily close prepared.',
            'lines' => [
                [
                    'shop_accounting_category_id' => $incomeCategory->id,
                    'amount' => 1500,
                    'description' => 'Cash sales',
                ],
                [
                    'shop_accounting_category_id' => $expenseCategory->id,
                    'amount' => 800,
                    'description' => 'Store spend',
                ],
            ],
        ];

        $this->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), $payload)
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry = ShopAccountingEntry::query()->where('shop_id', $shop->id)->firstOrFail();
        $this->assertSame('submitted', $entry->status);
        $this->assertSame($shopOwner->id, $entry->submitted_by);

        $this->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]), [
                'decision' => 'review_lines',
                'admin_note' => 'Please verify the closing cash.',
                'line_reviews' => [
                    $entry->lines()->where('description', 'Store spend')->firstOrFail()->id => [
                        'decision' => 'recheck',
                        'review_note' => 'Store spend bill is missing.',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $shop->code, 'tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('recheck_required', $entry->status);

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('Recheck Required')
            ->assertSee('Please verify the closing cash.')
            ->assertSee('These ledger items need correction')
            ->assertSee('Daily Expense')
            ->assertSee('Store spend')
            ->assertSee('Store spend bill is missing.');

        $this->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), [
                ...$payload,
                'closing_cash' => 750,
                'shop_reply_note' => 'Updated after recount.',
            ])
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('submitted', $entry->status);
        $this->assertSame('Updated after recount.', $entry->shop_reply_note);
        $this->assertNull($entry->reviewed_by);

        $this->actingAs($admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]), [
                'decision' => 'approve',
                'admin_note' => 'Approved after recount.',
            ])
            ->assertRedirect(route('admin.accounting.owned-shops.show', ['shop' => $shop->code, 'tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('approved', $entry->status);

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => '2026-06-24']))
            ->assertOk()
            ->assertSee('This day is already approved.')
            ->assertSee('Submit Updated Ledger Day');

        $this->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), [
                ...$payload,
                'closing_cash' => 775,
                'shop_reply_note' => 'Late expense added after approval.',
                'lines' => [
                    ...$payload['lines'],
                    [
                        'shop_accounting_category_id' => $expenseCategory->id,
                        'amount' => 120,
                        'description' => 'Late cleaning spend',
                    ],
                ],
            ])
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => '2026-06-24']));

        $entry->refresh();
        $this->assertSame('submitted', $entry->status);
        $this->assertSame('Late expense added after approval.', $entry->shop_reply_note);
        $this->assertNull($entry->reviewed_by);
        $this->assertNull($entry->admin_note);
        $this->assertSame(3, $entry->lines()->count());
    }

    public function test_shop_owner_must_add_notes_when_using_other_cashbook_category(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP-CASH-OTHER',
            'name' => 'Other Notes Shop',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);

        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        $shopOwner->assignRole('shop');

        $otherExpenseCategory = ShopAccountingCategory::create([
            'shop_id' => null,
            'type' => 'expense',
            'name' => 'Other',
            'is_active' => true,
        ]);

        $this->actingAs($shopOwner)
            ->from(route('shop-owner.accounting.index', ['tab' => 'cashbook']))
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-06-24',
                'submission_action' => 'save_draft',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $otherExpenseCategory->id,
                        'amount' => 250,
                        'description' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('shop-owner.accounting.index', ['tab' => 'cashbook']))
            ->assertSessionHasErrors('lines.0.description');
    }
}
