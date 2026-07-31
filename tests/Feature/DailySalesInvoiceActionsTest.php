<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ShopInvoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailySalesInvoiceActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_daily_sales_invoices_link_payment_actions_to_finance_v2(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $invoice = ShopInvoice::factory()->create([
            'invoice_number' => 'SINV-20260714-ACTIONS',
            'business_date' => '2026-07-14',
            'subtotal' => 1471.80,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 1471.80,
            'paid_amount' => 0,
            'balance_amount' => 1471.80,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.accounting.daily-sales', [
                'date' => '2026-07-14',
                'status' => 'all',
                'tab' => 'invoices',
            ]));

        $response
            ->assertOk()
            ->assertSee('Show invoice')
            ->assertSee('Finance payment')
            ->assertSee(route('purchasing.shop-invoices.show', $invoice), false)
            ->assertSee(route('admin.finance-v2.payments.create', [
                'date' => '2026-07-14',
                'shop_id' => $invoice->shop_id,
                'requested_amount' => 1471.8,
            ]))
            ->assertDontSee(route('admin.accounting.shop-invoices.payment', $invoice), false);
    }

    public function test_legacy_daily_sales_payment_route_redirects_to_finance_v2_without_updating_invoice(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $invoice = ShopInvoice::factory()->create([
            'business_date' => '2026-07-14',
            'subtotal' => 1713.80,
            'shortage_total' => 0,
            'discount_total' => 0,
            'final_total' => 1713.80,
            'paid_amount' => 0,
            'balance_amount' => 1713.80,
            'payment_status' => 'unpaid',
        ]);
        $product = Product::factory()->create(['base_price' => 1713.80]);
        $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'approved_qty' => 1,
            'delivered_qty' => 1,
            'shortage_qty' => 0,
            'unit_price' => 1713.80,
            'line_subtotal' => 1713.80,
            'shortage_amount' => 0,
            'final_line_total' => 1713.80,
        ]);

        $csrfToken = 'daily-sales-payment-token';

        $response = $this
            ->actingAs($admin)
            ->withSession(['_token' => $csrfToken])
            ->patch(route('admin.accounting.shop-invoices.payment', $invoice), [
                '_token' => $csrfToken,
                'discount_total' => 0,
                'paid_amount' => 1713.80,
                'payment_note' => 'Collected from daily sales report.',
            ]);

        $response
            ->assertRedirect(route('admin.finance-v2.payments.create', [
                'date' => '2026-07-14',
                'shop_id' => $invoice->shop_id,
                'requested_amount' => 1713.8,
            ]))
            ->assertSessionHas('warning', 'Payment approvals are handled from Finance V2 Payments.');

        $invoice->refresh();

        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1713.80', $invoice->balance_amount);
        $this->assertSame(0, JournalEntry::query()
            ->where('source_type', ShopInvoice::class)
            ->where('source_id', $invoice->id)
            ->count());
    }

    public function test_admin_applies_audited_discount_before_payment(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $invoice = ShopInvoice::factory()->create([
            'business_date' => '2026-07-14',
            'subtotal' => 2000,
            'shortage_total' => 0,
            'excess_total' => 0,
            'discount_total' => 0,
            'final_total' => 2000,
            'paid_amount' => 0,
            'balance_amount' => 2000,
            'payment_status' => 'unpaid',
            'delivery_status' => 'received_full',
        ]);
        $product = Product::factory()->create(['base_price' => 2000]);
        $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'approved_qty' => 1,
            'delivered_qty' => 1,
            'shortage_qty' => 0,
            'excess_qty' => 0,
            'unit_price' => 2000,
            'line_subtotal' => 2000,
            'shortage_amount' => 0,
            'excess_amount' => 0,
            'final_line_total' => 2000,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.accounting.shop-invoices.discount', $invoice), [
                'discount_total' => 250,
                'discount_note' => 'Approved quality issue discount.',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('success', 'Shop invoice discount applied and bill balance recalculated.');

        $invoice->refresh();

        $this->assertSame('250.00', $invoice->discount_total);
        $this->assertSame('Approved quality issue discount.', $invoice->discount_note);
        $this->assertSame($admin->id, $invoice->discount_approved_by);
        $this->assertNotNull($invoice->discount_approved_at);
        $this->assertSame('1750.00', $invoice->final_total);
        $this->assertSame('1750.00', $invoice->balance_amount);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame(0, JournalEntry::query()
            ->where('source_type', ShopInvoice::class)
            ->where('source_id', $invoice->id)
            ->count());
    }

    public function test_shop_invoice_detail_exposes_admin_billing_actions_after_delivery_approval(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));

        $invoice = ShopInvoice::factory()->create([
            'invoice_number' => 'SINV-20260714-DETAIL',
            'business_date' => '2026-07-14',
            'delivery_status' => 'received_full',
            'status' => 'payment_pending',
            'subtotal' => 1200,
            'shortage_total' => 0,
            'excess_total' => 0,
            'discount_total' => 100,
            'discount_note' => 'Opening discount',
            'final_total' => 1100,
            'paid_amount' => 400,
            'balance_amount' => 700,
            'payment_status' => 'partially_paid',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));

        $response
            ->assertOk()
            ->assertSee('Admin Billing Actions')
            ->assertSee('Apply Discount')
            ->assertSee('Finance payment')
            ->assertSee(route('admin.accounting.shop-invoices.discount', $invoice), false)
            ->assertSee(route('admin.finance-v2.payments.create', [
                'date' => '2026-07-14',
                'shop_id' => $invoice->shop_id,
                'requested_amount' => 700.0,
            ]))
            ->assertDontSee(route('admin.accounting.shop-invoices.payment', $invoice), false)
            ->assertSee('Opening discount');
    }
}
