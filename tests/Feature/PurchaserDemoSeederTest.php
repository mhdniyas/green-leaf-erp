<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCorrectionRequest;
use App\Models\StockBatch;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaserDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_purchase_demo_data(): void
    {
        $this->seed();

        $today = Carbon::today()->toDateString();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market A',
            'location' => 'Koyambedu Wholesale Market',
            'mobile_number' => '9876543210',
            'preferred_payment_method' => 'Cash',
            'credit_approved' => false,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market B',
            'credit_approved' => true,
            'credit_terms' => 'Net 1 day',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market C',
            'mobile_number' => '9876543212',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market D',
            'credit_approved' => true,
            'preferred_payment_method' => 'Online',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Market E',
            'preferred_payment_method' => 'GPay',
        ]);

        $draftCart = PurchaserCart::query()
            ->where('cart_number', 'VC-DEMO-DRAFT-001')
            ->with('items')
            ->firstOrFail();

        $this->assertSame('draft', $draftCart->status);
        $this->assertSame($today, $draftCart->business_date?->toDateString());
        $this->assertSame(3, $draftCart->items->count());

        $submittedCart = PurchaserCart::query()
            ->where('cart_number', 'VC-DEMO-SUBMIT-002')
            ->firstOrFail();

        $this->assertSame('submitted', $submittedCart->status);
        $this->assertSame('credit_pending_approval', $submittedCart->payment_status);
        $this->assertNotNull($submittedCart->purchase_invoice_id);
        $this->assertNotNull($submittedCart->whatsapp_sent_at);

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), [
            'invoice_number' => 'PINV-DEMO-PUR-002',
            'payment_status' => 'credit_pending_approval',
        ]);

        $this->assertDatabaseHas((new PurchaserCart)->getTable(), [
            'cart_number' => 'VC-DEMO-OVERDUE-DRAFT-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), [
            'invoice_number' => 'PINV-DEMO-OVERDUE-RECEIPT-001',
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), [
            'invoice_number' => 'PINV-DEMO-PAYMENT-PENDING-001',
            'payment_status' => 'partial',
        ]);

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), [
            'invoice_number' => 'PINV-DEMO-COMPLETED-001',
            'payment_status' => 'paid',
            'payment_method' => 'Online',
        ]);

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), [
            'invoice_number' => 'PINV-DEMO-MARKET-C-001',
            'payment_status' => 'unpaid',
        ]);

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), [
            'invoice_number' => 'PINV-DEMO-MARKET-D-001',
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas((new PurchaserCart)->getTable(), [
            'cart_number' => 'VC-DEMO-MARKET-E-DRAFT-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas((new StockBatch)->getTable(), [
            'reference' => 'BATCH-GRN-DEMO-OVERDUE-RECEIPT-001-1',
            'warehouse_receive_pending' => true,
        ]);

        $this->assertDatabaseHas((new StockBatch)->getTable(), [
            'reference' => 'BATCH-GRN-DEMO-PAYMENT-PENDING-001-13',
            'warehouse_receive_pending' => false,
        ]);

        $this->assertDatabaseHas((new PurchaseOrder)->getTable(), [
            'po_number' => 'PO-DEMO-STANDALONE-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas((new PurchaseOrder)->getTable(), [
            'po_number' => 'PO-DEMO-STANDALONE-002',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas((new PurchaseOrder)->getTable(), [
            'po_number' => 'PO-DEMO-STANDALONE-003',
            'status' => 'sent_to_supplier',
        ]);

        $this->assertTrue(
            PurchaserCorrectionRequest::query()
                ->whereDate('business_date', $today)
                ->where('status', 'pending')
                ->exists()
        );

        $this->assertGreaterThanOrEqual(
            5,
            Supplier::query()
                ->whereHas('purchaserCarts', fn ($query) => $query->where('cart_number', 'like', 'VC-DEMO-%'))
                ->count()
        );
    }
}
