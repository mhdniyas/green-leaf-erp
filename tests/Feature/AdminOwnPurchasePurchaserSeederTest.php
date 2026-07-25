<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\ShopOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AdminOwnPurchasePurchaserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOwnPurchasePurchaserSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_makes_admin_the_own_purchase_purchaser_identity(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin Buyer',
            'email' => 'admin.buyer@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::findByName('admin'));

        $this->seed(AdminOwnPurchasePurchaserSeeder::class);
        $this->seed(AdminOwnPurchasePurchaserSeeder::class);

        $admin->refresh();
        $linkedPurchaser = $admin->ownPurchasePurchaser;

        $this->assertNotNull($linkedPurchaser);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasRole('purchaser'));
        $this->assertTrue($linkedPurchaser->hasRole('purchaser'));
        $this->assertTrue($linkedPurchaser->is($admin));
        $this->assertSame(0, User::query()
            ->where('email', 'admin-own-purchase-'.$admin->id.'@greenleaf.local')
            ->count());

        $ledgerUrl = route('admin.accounting.purchasers.show', $linkedPurchaser->public_uuid);

        $this->assertStringContainsString($linkedPurchaser->public_uuid, $ledgerUrl);
        $this->assertNotSame('/admin/accounting/purchasers/'.$linkedPurchaser->id, parse_url($ledgerUrl, PHP_URL_PATH));
    }

    public function test_admin_can_open_purchaser_buying_window_without_switching_accounts(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin Buyer',
            'email' => 'admin.buyer@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::findByName('admin'));

        $this->seed(AdminOwnPurchasePurchaserSeeder::class);
        $linkedPurchaser = $admin->fresh('ownPurchasePurchaser')->ownPurchasePurchaser;
        $admin = $admin->fresh();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.accounting.purchasers.buy', $linkedPurchaser->public_uuid));

        $response->assertRedirect(route('admin.accounting.purchasers.direct-purchase.create', ['date' => today()->toDateString()]));
        $this->assertAuthenticatedAs($admin);

        $this
            ->get(route('admin.accounting.purchasers.direct-purchase.create', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Green Leaf Direct Purchase');
    }

    public function test_admin_direct_purchase_creates_approved_tagged_demand_for_purchaser_flow(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin Buyer',
            'email' => 'admin.buyer@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::findByName('admin'));

        $this->seed(AdminOwnPurchasePurchaserSeeder::class);
        $admin = $admin->fresh();

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $businessDate = today()->toDateString();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.accounting.purchasers.direct-purchase.store'), [
                'business_date' => $businessDate,
                'items' => [
                    $product->sku => 12.5,
                ],
            ]);

        $order = ShopOrder::query()
            ->with('items.product')
            ->where('order_source', 'admin_direct_purchase')
            ->firstOrFail();

        $response->assertRedirect(route('purchaser.vendors', ['date' => $businessDate]));
        $this->assertNull($order->shop_id);
        $this->assertSame('approved', $order->state);
        $this->assertTrue($order->isAdminDirectPurchase());
        $this->assertSame('Green Leaf Direct Purchase', $order->demandSourceLabel());
        $this->assertSame(12.5, (float) $order->items->first()->requested_qty);
        $this->assertSame(12.5, (float) $order->items->first()->approved_qty);

        $this
            ->get(route('purchaser.daily', ['date' => $businessDate]))
            ->assertOk()
            ->assertSee('Tomato')
            ->assertSee('Green Leaf Direct Purchase');
    }

    public function test_green_leaf_direct_purchase_invoice_payment_posts_cash_flow_journal(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin Buyer',
            'email' => 'admin.buyer@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::findByName('admin'));

        $this->seed(AdminOwnPurchasePurchaserSeeder::class);
        $admin = $admin->fresh();

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $supplier = Supplier::factory()->create(['name' => 'Direct Vendor']);
        $businessDate = today()->toDateString();

        $this
            ->actingAs($admin)
            ->post(route('admin.accounting.purchasers.direct-purchase.store'), [
                'business_date' => $businessDate,
                'items' => [
                    $product->sku => 10,
                ],
            ])
            ->assertRedirect(route('purchaser.vendors', ['date' => $businessDate]));

        $this
            ->post(route('purchaser.cart-items.store'), [
                'business_date' => $businessDate,
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_price' => 10,
                'purchase_source' => 'green_leaf_direct_purchase',
                'return_to' => 'daily',
            ])
            ->assertRedirect();

        $cart = PurchaserCart::query()
            ->where('user_id', $admin->id)
            ->whereDate('business_date', $businessDate)
            ->with('items')
            ->firstOrFail();

        $this->assertSame('green_leaf_direct_purchase', $cart->purchase_source);

        $this
            ->post(route('purchaser.carts.submit'), [
                'business_date' => $businessDate,
                'cart_id' => $cart->id,
                'supplier_id' => $supplier->id,
                'bill_number' => 'GL-DIRECT-001',
                'payment_method' => 'Cash',
                'paid_amount' => 100,
                'discount_amount' => 0,
                'payment_note' => null,
                'payment_details' => null,
                'notes' => null,
                'items' => [
                    $cart->items->first()->id => [
                        'unit_price' => 10,
                    ],
                ],
            ])
            ->assertRedirect(route('purchaser.vendors', ['date' => $businessDate, 'tab' => 'pending']));

        $invoice = PurchaseInvoice::query()->where('invoice_number', 'GL-DIRECT-001')->firstOrFail();

        $this->assertTrue($invoice->isGreenLeafDirectPurchase());
        $this->assertSame('green_leaf_direct_purchase', $invoice->purchase_source);

        $journal = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->where('source_event', 'green_leaf_direct_purchase_payment:paid-10000')
            ->with('transactions.account')
            ->firstOrFail();

        $this->assertSame('Green Leaf Direct Purchase payment for invoice #GL-DIRECT-001', $journal->description);
        $this->assertTrue($journal->transactions->contains(fn ($transaction): bool => $transaction->account?->code === '1010' && $transaction->type === 'credit' && (float) $transaction->amount === 100.0));
    }

    public function test_credit_bill_submit_does_not_debit_purchaser_until_settlement(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = User::query()->create([
            'name' => 'Purchaser',
            'email' => 'purchaser.credit@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $purchaser->assignRole(Role::findByName('purchaser'));

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOMATO-CREDIT-001',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $supplier = Supplier::factory()->create([
            'name' => 'Credit Vendor',
            'credit_approved' => true,
        ]);
        $businessDate = today()->toDateString();
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $businessDate,
            'status' => 'draft',
            'cart_number' => PurchaserCart::generateCartNumber($businessDate),
        ]);
        $item = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 20,
            'line_total' => 200,
        ]);

        $this
            ->actingAs($purchaser)
            ->post(route('purchaser.carts.submit'), [
                'business_date' => $businessDate,
                'cart_id' => $cart->id,
                'supplier_id' => $supplier->id,
                'bill_number' => 'CREDIT-SUBMIT-001',
                'payment_method' => 'Credit',
                'paid_amount' => 200,
                'discount_amount' => 0,
                'payment_note' => null,
                'payment_details' => null,
                'notes' => null,
                'items' => [
                    $item->id => [
                        'unit_price' => 20,
                    ],
                ],
            ])
            ->assertRedirect(route('purchaser.vendors', ['date' => $businessDate, 'tab' => 'pending']));

        $invoice = PurchaseInvoice::query()->where('invoice_number', 'CREDIT-SUBMIT-001')->firstOrFail();

        $this->assertSame('Credit', $invoice->payment_method);
        $this->assertSame('vendor_credit', $invoice->payment_paid_by);
        $this->assertSame('credit_pending_approval', $invoice->payment_status);
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame(0, PurchaserCredit::query()->where('purchase_invoice_id', $invoice->id)->count());
    }

    public function test_purchaser_settlement_of_credit_bill_debits_purchaser(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $purchaser = User::query()->create([
            'name' => 'Purchaser',
            'email' => 'purchaser.settlement@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $purchaser->assignRole(Role::findByName('purchaser'));

        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'business_date' => today()->toDateString(),
            'status' => 'submitted',
            'cart_number' => PurchaserCart::generateCartNumber(today()->toDateString()),
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'paid_amount' => 0,
        ]);
        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'purchaser_submitted_by' => $purchaser->id,
            'invoice_number' => 'CREDIT-SETTLE-001',
            'amount' => 500,
            'discount_amount' => 0,
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'payment_status' => 'credit_pending_approval',
            'paid_amount' => 0,
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $this
            ->actingAs($purchaser)
            ->patch(route('purchaser.invoices.payment', $invoice), [
                'return_to' => 'suppliers',
                'date' => today()->toDateString(),
                'payment_method' => 'Cash',
                'payment_paid_by' => 'purchaser',
                'discount_amount' => 0,
                'additional_paid_amount' => 500,
                'payment_note' => 'Purchaser settled supplier credit.',
                'payment_details' => null,
            ])
            ->assertRedirect(route('purchaser.suppliers', ['date' => today()->toDateString()]));

        $invoice->refresh();

        $this->assertSame('purchaser', $invoice->payment_paid_by);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertDatabaseHas('purchaser_credits', [
            'purchaser_id' => $purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => '500.00',
        ]);
    }

    public function test_legacy_generated_admin_purchase_users_no_longer_act_as_purchasers(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Admin Buyer',
            'email' => 'admin.buyer@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::findByName('admin'));

        $legacyPurchaser = User::query()->create([
            'name' => 'Admin Buyer Own Purchase',
            'email' => 'admin-own-purchase-'.$admin->id.'@greenleaf.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $legacyPurchaser->assignRole(Role::findByName('purchaser'));

        $this->seed(AdminOwnPurchasePurchaserSeeder::class);

        $this->assertFalse($legacyPurchaser->fresh()->hasRole('purchaser'));
        $this->assertTrue($admin->fresh()->hasRole('purchaser'));
    }
}
