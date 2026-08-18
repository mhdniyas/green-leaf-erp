<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOwnerPaymentsCompanyPayableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::findOrCreate('shop', 'web');
        Permission::findOrCreate('sales.order.create', 'web');
    }

    public function test_shop_owner_can_view_status_of_last_payments_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
        ]);
        ShopOwnerAssignment::factory()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);

        $paymentRequest = ShopInvoicePaymentRequest::create([
            'shop_id' => $shop->id,
            'requested_amount' => 5000.00,
            'payment_method' => 'cash',
            'payment_reference' => 'REF123456',
            'shop_note' => 'Payment for cashbook daily balance',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('shop-owner.payments.index'));

        $response->assertOk()
            ->assertSee('Status of Last Payments')
            ->assertSee('5,000.00')
            ->assertSee('REF123456');
    }

    public function test_owned_shop_displays_daily_payable_balances_and_submitted_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        ShopOwnerAssignment::factory()->create([
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);

        $response = $this->actingAs($user)->get(route('shop-owner.payments.index'));

        $response->assertOk()
            ->assertSee('Daily Payable Balances')
            ->assertSee('Status of Last Payments');
    }

    public function test_owned_shop_can_submit_positive_payment_even_without_positive_closing_balance(): void
    {
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $paymentRequest = app(ShopInvoiceService::class)->requestShopBalancePayment(
            $shop,
            Carbon::parse('2026-08-18'),
            0,
            [
                'amount' => 1500,
                'payment_method' => 'online_upi',
                'payment_reference' => 'UTR-FLOATING-1500',
            ],
            (int) $user->id,
        );

        $this->assertSame('pending', $paymentRequest->status);
        $this->assertSame('floating', $paymentRequest->reconciliation_status);
        $this->assertSame(1500.00, (float) $paymentRequest->requested_amount);
        $this->assertSame(1500.00, (float) $paymentRequest->floating_amount);
    }
}
