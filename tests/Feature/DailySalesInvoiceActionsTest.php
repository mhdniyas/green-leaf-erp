<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\ShopInvoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailySalesInvoiceActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_daily_sales_invoices_have_show_and_approve_payment_actions(): void
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
            ->assertSee('Approve payment')
            ->assertSee(route('purchasing.shop-invoices.show', $invoice), false)
            ->assertSee(route('purchasing.shop-invoices.payment-approval', $invoice), false);
    }

    public function test_approving_payment_from_daily_sales_returns_to_report_and_updates_invoice(): void
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

        $dailySalesUrl = route('admin.accounting.daily-sales', [
            'date' => '2026-07-14',
            'status' => 'all',
            'tab' => 'invoices',
        ]);
        $csrfToken = 'daily-sales-payment-token';

        $response = $this
            ->actingAs($admin)
            ->withSession(['_token' => $csrfToken])
            ->from($dailySalesUrl)
            ->patch(route('purchasing.shop-invoices.payment-approval', $invoice), [
                '_token' => $csrfToken,
                'discount_total' => 0,
                'paid_amount' => 1713.80,
                'payment_note' => 'Collected from daily sales report.',
            ]);

        $response
            ->assertRedirect($dailySalesUrl)
            ->assertSessionHas('success', 'Daily invoice payment approval updated.');

        $invoice->refresh();

        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('1713.80', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->balance_amount);

        $journalEntry = JournalEntry::query()
            ->where('source_type', ShopInvoice::class)
            ->where('source_id', $invoice->id)
            ->where('source_event', 'payment:paid-171380')
            ->firstOrFail();

        $cashAccount = Account::query()->where('code', '1010')->firstOrFail();
        $salesRevenueAccount = Account::query()->where('code', '4100')->firstOrFail();

        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount->id,
            'type' => 'debit',
            'amount' => '1713.80',
        ]);

        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $salesRevenueAccount->id,
            'type' => 'credit',
            'amount' => '1713.80',
        ]);
    }
}
