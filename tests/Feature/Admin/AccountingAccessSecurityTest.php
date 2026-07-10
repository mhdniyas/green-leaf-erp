<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingInvoice;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Shop $ownedShop;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->ownedShop = Shop::query()->create([
            'code' => 'OWN-AUDIT-001',
            'name' => 'Owned Audit Shop',
            'status' => 'active',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_accounting_access_matrix_uses_explicit_accounting_permissions(): void
    {
        $adminPermissionUser = User::factory()->create();
        $adminPermissionUser->givePermissionTo('admin.user.view');

        $accountingViewUser = User::factory()->create();
        $accountingViewUser->givePermissionTo(['accounting.dashboard.view', 'accounting.ledger.view']);

        $accountingManageUser = User::factory()->create();
        $accountingManageUser->givePermissionTo([
            'accounting.dashboard.view',
            'accounting.owned-shop.manage',
            'accounting.purchaser-cash.manage',
        ]);

        $shopUser = User::factory()->create(['shop_id' => $this->ownedShop->id]);
        $shopUser->assignRole('shop');

        $purchaseUser = User::factory()->create();
        $purchaseUser->assignRole('purchase');

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole('warehouse_receiver');

        $hrManager = User::factory()->create();
        $hrManager->assignRole('hr_manager');

        $noRoleUser = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.index'))
            ->assertOk();

        $this->actingAs($adminPermissionUser)
            ->get(route('admin.accounting.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($accountingViewUser)
            ->get(route('admin.accounting.index'))
            ->assertOk()
            ->assertDontSee(route('admin.accounting.owned-shops.index'), false)
            ->assertDontSee(route('admin.accounting.purchasers.index'), false);

        $this->actingAs($accountingViewUser)
            ->get(route('admin.accounting.daily-sales'))
            ->assertOk();

        $this->actingAs($accountingViewUser)
            ->get(route('admin.accounting.vendor-reports'))
            ->assertOk();

        $this->actingAs($accountingViewUser)
            ->get(route('admin.accounting.owned-shops.index'))
            ->assertRedirect(route('admin.accounting.index', ['date' => today()->toDateString()]));

        $this->actingAs($accountingViewUser)
            ->get(route('admin.accounting.purchasers.index'))
            ->assertRedirect(route('admin.accounting.index', ['date' => today()->toDateString()]));

        $this->actingAs($accountingManageUser)
            ->get(route('admin.accounting.owned-shops.index'))
            ->assertOk();

        $this->actingAs($accountingManageUser)
            ->get(route('admin.accounting.purchasers.index'))
            ->assertOk();

        foreach ([$shopUser, $purchaseUser, $purchaser, $warehouseUser, $hrManager, $noRoleUser] as $user) {
            $this->actingAs($user)
                ->get(route('admin.accounting.index'))
                ->assertRedirect(route('dashboard'));
        }
    }

    public function test_accounting_view_user_cannot_perform_accounting_write_actions(): void
    {
        $accountingViewUser = User::factory()->create();
        $accountingViewUser->givePermissionTo(['accounting.dashboard.view', 'accounting.ledger.view']);

        $entry = ShopAccountingEntry::query()->create([
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-07-06',
            'status' => 'submitted',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        $this->actingAs($accountingViewUser)
            ->post(route('admin.accounting.daily-workflow.invoices'), ['date' => '2026-07-06'])
            ->assertForbidden();

        $this->actingAs($accountingViewUser)
            ->post(route('admin.accounting.owned-shops.categories.store', $this->ownedShop), [
                'scope' => 'shop',
                'type' => 'income',
                'name' => 'Tampered Income',
            ])
            ->assertForbidden();

        $this->actingAs($accountingViewUser)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $entry]), [
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->actingAs($accountingViewUser)
            ->post(route('admin.accounting.purchasers.credits.store', $purchaser), [
                'amount' => 100,
                'business_date' => '2026-07-06',
            ])
            ->assertForbidden();
    }

    public function test_admin_accounting_nested_routes_reject_mismatched_child_records(): void
    {
        $otherShop = Shop::query()->create([
            'code' => 'OWN-AUDIT-002',
            'name' => 'Other Owned Audit Shop',
            'status' => 'active',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);

        $otherEntry = ShopAccountingEntry::query()->create([
            'shop_id' => $otherShop->id,
            'business_date' => '2026-07-06',
            'status' => 'submitted',
            'opening_cash' => 0,
            'closing_cash' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $otherInvoice = ShopAccountingInvoice::query()->create([
            'shop_id' => $otherShop->id,
            'invoice_number' => 'SAI-OTHER-001',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-06',
            'status' => 'generated',
            'total_income' => 1000,
            'total_expense' => 200,
            'net_amount' => 800,
            'generated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.accounting.owned-shops.entries.review', ['shop' => $this->ownedShop, 'entry' => $otherEntry]), [
                'decision' => 'approve',
            ])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.accounting.owned-shops.invoices.show', ['shop' => $this->ownedShop, 'invoice' => $otherInvoice]))
            ->assertNotFound();
    }

    public function test_accounting_entry_forms_reject_tampered_category_ids_from_another_shop(): void
    {
        $otherShop = Shop::query()->create([
            'code' => 'OWN-AUDIT-CAT-002',
            'name' => 'Other Category Shop',
            'status' => 'active',
            'accounting_mode' => 'owned',
            'accounting_enabled' => true,
        ]);
        $otherShopCategory = ShopAccountingCategory::query()->create([
            'shop_id' => $otherShop->id,
            'type' => 'income',
            'name' => 'Other Shop Income',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.owned-shops.entries.store', $this->ownedShop), [
                'business_date' => '2026-07-08',
                'status' => 'draft',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $otherShopCategory->id,
                        'amount' => 100,
                        'description' => 'Tampered category',
                    ],
                ],
            ])
            ->assertForbidden();

        $shopOwner = User::factory()->create(['shop_id' => $this->ownedShop->id]);
        $shopOwner->assignRole('shop');

        $this->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.entries.store'), [
                'business_date' => '2026-07-08',
                'submission_action' => 'submit',
                'lines' => [
                    [
                        'shop_accounting_category_id' => $otherShopCategory->id,
                        'amount' => 100,
                        'description' => 'Tampered category',
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('shop_accounting_entries', [
            'shop_id' => $this->ownedShop->id,
            'business_date' => '2026-07-08',
        ]);
    }

    public function test_shop_owner_cannot_access_or_pay_another_shops_invoice(): void
    {
        $otherShop = Shop::query()->create([
            'code' => 'SHOP-AUDIT-002',
            'name' => 'Other Shop',
            'status' => 'active',
        ]);

        $shopOwner = User::factory()->create(['shop_id' => $this->ownedShop->id]);
        $shopOwner->assignRole('shop');

        $ownInvoice = $this->shopInvoiceFor($this->ownedShop, 'SINV-OWN-AUDIT');
        $otherInvoice = $this->shopInvoiceFor($otherShop, 'SINV-OTHER-AUDIT');

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.finance.show', $otherInvoice))
            ->assertForbidden();

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.finance.pdf', $otherInvoice))
            ->assertForbidden();

        $this->actingAs($shopOwner)
            ->post(route('shop-owner.accounting.payment-requests.store'), [
                'invoice_id' => $otherInvoice->id,
                'amount_mode' => 'balance_due',
            ])
            ->assertNotFound();

        $this->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.history'))
            ->assertOk()
            ->assertSee($ownInvoice->invoice_number)
            ->assertDontSee($otherInvoice->invoice_number);
    }

    public function test_purchaser_cannot_access_or_update_another_purchasers_invoice(): void
    {
        $firstPurchaser = User::factory()->create();
        $firstPurchaser->assignRole('purchaser');

        $secondPurchaser = User::factory()->create();
        $secondPurchaser->assignRole('purchaser');

        $firstInvoice = $this->purchaseInvoiceFor($firstPurchaser, 'PINV-FIRST-AUDIT');
        $secondInvoice = $this->purchaseInvoiceFor($secondPurchaser, 'PINV-SECOND-AUDIT');

        $this->actingAs($firstPurchaser)
            ->get(route('purchaser.invoices.show', $secondInvoice))
            ->assertNotFound();

        $this->actingAs($firstPurchaser)
            ->get(route('purchaser.invoices.pdf', $secondInvoice))
            ->assertNotFound();

        $this->actingAs($firstPurchaser)
            ->patch(route('purchaser.invoices.payment', $secondInvoice), [
                'payment_method' => 'Cash',
                'paid_amount' => 50,
            ])
            ->assertNotFound();

        $this->actingAs($firstPurchaser)
            ->get(route('purchaser.invoices.show', $firstInvoice))
            ->assertOk();
    }

    public function test_finance_exports_require_view_and_export_permissions(): void
    {
        $viewOnlyUser = User::factory()->create();
        $viewOnlyUser->givePermissionTo('accounting.ledger.view');

        $exportOnlyUser = User::factory()->create();
        $exportOnlyUser->givePermissionTo('accounting.report.export');

        $exportUser = User::factory()->create();
        $exportUser->givePermissionTo(['accounting.ledger.view', 'accounting.report.export']);

        $this->actingAs($viewOnlyUser)
            ->get(route('finance.vendors.index'))
            ->assertOk();

        $this->actingAs($viewOnlyUser)
            ->get(route('finance.vendors.excel'))
            ->assertForbidden();

        $this->actingAs($viewOnlyUser)
            ->get(route('finance.statement.export.csv'))
            ->assertForbidden();

        $this->actingAs($exportOnlyUser)
            ->get(route('finance.vendors.excel'))
            ->assertForbidden();

        $this->actingAs($exportUser)
            ->get(route('finance.vendors.excel'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($exportUser)
            ->get(route('finance.statement.export.pdf'))
            ->assertRedirect(route('finance.vendors.index'));
    }

    private function shopInvoiceFor(Shop $shop, string $invoiceNumber): ShopInvoice
    {
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => '2026-07-06',
            'created_by' => $this->admin->id,
        ]);

        return ShopInvoice::query()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => '2026-07-06',
            'status' => 'generated',
            'delivery_status' => 'delivered',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
            'generated_by' => $this->admin->id,
        ]);
    }

    private function purchaseInvoiceFor(User $purchaser, string $invoiceNumber): PurchaseInvoice
    {
        $supplier = Supplier::factory()->create();
        $goodsReceived = GoodsReceived::factory()->create();
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => '2026-07-06',
            'cart_number' => 'VC-'.$invoiceNumber,
            'status' => 'submitted',
            'goods_received_id' => $goodsReceived->id,
            'payment_method' => 'Cash',
            'payment_status' => 'unpaid',
            'paid_amount' => 0,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => $invoiceNumber,
            'amount' => 100,
            'status' => InvoiceStatus::Pending,
            'payment_method' => 'Cash',
            'payment_status' => 'unpaid',
            'paid_amount' => 0,
            'purchaser_submitted_by' => $purchaser->id,
            'purchaser_submitted_at' => now(),
        ]);

        $cart->update(['purchase_invoice_id' => $invoice->id]);

        return $invoice;
    }
}
