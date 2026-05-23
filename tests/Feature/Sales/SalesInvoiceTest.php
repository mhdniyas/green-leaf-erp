<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\SalesInvoiceStatus;
use App\Enums\Sales\SOStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $salesManager;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->salesManager = User::factory()->create();
        $this->salesManager->givePermissionTo([
            'sales.invoice.view',
            'sales.invoice.create',
            'sales.payment.record',
        ]);

        $this->cashier = User::factory()->create();
        $this->cashier->givePermissionTo([
            'sales.invoice.view',
            'sales.payment.record',
        ]);
    }

    public function test_can_view_invoices_index(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->salesManager)
            ->get(route('sales.invoices.index'));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
    }

    public function test_create_invoice_from_dispatched_order(): void
    {
        $customer = Customer::factory()->create(['payment_terms' => 'Net 15']);
        $order = SalesOrder::factory()->dispatched()
            ->for($customer)
            ->create(['created_by' => $this->salesManager->id]);

        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'grade' => 'A',
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.invoices.store'), [
                'sales_order_id' => $order->id,
            ]);

        $response->assertRedirect();

        $order->refresh();
        $this->assertEquals(SOStatus::Invoiced, $order->status);

        $this->assertDatabaseHas('sales_invoices', [
            'sales_order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => 500.00,
            'status' => SalesInvoiceStatus::Unpaid->value,
        ]);
    }

    public function test_cannot_create_duplicate_invoice_for_same_order(): void
    {
        $order = SalesOrder::factory()->dispatched()->create([
            'created_by' => $this->salesManager->id,
        ]);

        SalesInvoice::factory()->create([
            'sales_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.invoices.store'), [
                'sales_order_id' => $order->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_record_full_payment_marks_invoice_as_paid(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'amount' => 1000.00,
            'paid_amount' => 0,
            'status' => SalesInvoiceStatus::Unpaid,
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->cashier)
            ->post(route('sales.invoices.payments.store', $invoice), [
                'amount' => 1000.00,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect(route('sales.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals(1000.00, (float) $invoice->paid_amount);
        $this->assertEquals(SalesInvoiceStatus::Paid, $invoice->status);

        $this->assertDatabaseHas('payments', [
            'sales_invoice_id' => $invoice->id,
            'amount' => 1000.00,
            'payment_method' => 'cash',
        ]);
    }

    public function test_record_partial_payment_marks_invoice_as_partially_paid(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'amount' => 1000.00,
            'paid_amount' => 0,
            'status' => SalesInvoiceStatus::Unpaid,
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->cashier)
            ->post(route('sales.invoices.payments.store', $invoice), [
                'amount' => 400.00,
                'payment_method' => 'bank_transfer',
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect(route('sales.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals(400.00, (float) $invoice->paid_amount);
        $this->assertEquals(SalesInvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_payment_exceeding_outstanding_is_rejected(): void
    {
        $invoice = SalesInvoice::factory()->create([
            'amount' => 500.00,
            'paid_amount' => 0,
            'status' => SalesInvoiceStatus::Unpaid,
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->cashier)
            ->post(route('sales.invoices.payments.store', $invoice), [
                'amount' => 1000.00, // exceeds invoice amount
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect(route('sales.invoices.show', $invoice));
        $response->assertSessionHas('error');

        $invoice->refresh();
        $this->assertEquals(0, (float) $invoice->paid_amount);
    }

    public function test_cannot_record_payment_for_already_paid_invoice(): void
    {
        $invoice = SalesInvoice::factory()->paid()->create([
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->cashier)
            ->post(route('sales.invoices.payments.store', $invoice), [
                'amount' => 100.00,
                'payment_method' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertSessionHas('error');
    }

    public function test_unauthorized_user_cannot_view_invoices(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('sales.invoices.index'));

        $response->assertForbidden();
    }
}
