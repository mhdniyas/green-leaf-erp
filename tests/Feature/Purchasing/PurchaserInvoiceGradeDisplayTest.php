<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserInvoiceGradeDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create([
            'name' => 'Cavendish Banana',
            'unit' => 'kg',
        ]);
    }

    public function test_grade_a_appears_correctly_on_invoice_and_pdf(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-A-'.uniqid(),
            'bill_number' => 'INV-A-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 25.0,
            'unit_price' => 60.0,
            'line_total' => 1500.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-A-001',
            'amount' => 1500.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        $showResponse->assertDontSee('<th class="w-20 py-1 pr-1">GRADE</th>', false);
        $showResponse->assertSee('Cavendish Banana (A)');

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertDontSee('<th class="w-16 py-1 pr-1">GRADE</th>', false);
        $pdfResponse->assertSee('Cavendish Banana (A)');
    }

    public function test_grade_b_appears_correctly_on_invoice_and_pdf(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-B-'.uniqid(),
            'bill_number' => 'INV-B-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 15.0,
            'unit_price' => 45.0,
            'line_total' => 675.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-B-001',
            'amount' => 675.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        $showResponse->assertSee('Cavendish Banana (B)');

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Cavendish Banana (B)');
    }

    public function test_grade_c_appears_correctly_on_invoice_and_pdf(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-C-'.uniqid(),
            'bill_number' => 'INV-C-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'C',
            'quantity' => 10.0,
            'unit_price' => 30.0,
            'line_total' => 300.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-C-001',
            'amount' => 300.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        $showResponse->assertSee('Cavendish Banana (C)');

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Cavendish Banana (C)');
    }

    public function test_mixed_grade_displays_correctly(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-U-'.uniqid(),
            'bill_number' => 'INV-U-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'U',
            'quantity' => 50.0,
            'unit_price' => 35.0,
            'line_total' => 1750.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-U-001',
            'amount' => 1750.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        $showResponse->assertSee('Cavendish Banana (Mixed)');

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Cavendish Banana (Mixed)');
    }

    public function test_same_product_with_different_grades_stays_distinguishable(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-MULTI-'.uniqid(),
            'bill_number' => 'INV-MULTI-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 60.0,
            'line_total' => 1200.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 10.0,
            'unit_price' => 40.0,
            'line_total' => 400.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-MULTI-001',
            'amount' => 1600.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        $showResponse->assertSee('Cavendish Banana (A)');
        $showResponse->assertSee('Cavendish Banana (B)');
        $showResponse->assertSee('20.00');
        $showResponse->assertSee('10.00');
        $showResponse->assertSee('1,200.00');
        $showResponse->assertSee('400.00');

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Cavendish Banana (A)');
        $pdfResponse->assertSee('Cavendish Banana (B)');
    }

    public function test_existing_invoice_totals_remain_unchanged(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-TOTAL-'.uniqid(),
            'bill_number' => 'INV-TOTAL-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 50.0,
            'line_total' => 1000.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 10.0,
            'unit_price' => 30.0,
            'line_total' => 300.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-TOTAL-001',
            'amount' => 1300.0,
            'discount_amount' => 50.0,
            'paid_amount' => 500.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        // Subtotal
        $showResponse->assertSee('Rs. 1,300.00');
        // Discount
        $showResponse->assertSee('Rs. 50.00');
        // Total (1300 - 50 = 1250)
        $showResponse->assertSee('Rs. 1,250.00');
        // Paid
        $showResponse->assertSee('Rs. 500.00');
        // Balance (1250 - 500 = 750)
        $showResponse->assertSee('Rs. 750.00');
    }

    public function test_legacy_invoice_with_unspecified_grade_renders_safely(): void
    {
        $this->assertSame('Not specified', ProductGrade::formatForInvoice(null));
        $this->assertSame('Not specified', ProductGrade::formatForInvoice(''));
        $this->assertSame('Not specified', ProductGrade::formatForInvoice('UNKNOWN'));

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-LEGACY-'.uniqid(),
            'bill_number' => 'INV-LEGACY-001',
        ]);

        // Historical / legacy record with empty or unrecognized grade value
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => '',
            'quantity' => 10.0,
            'unit_price' => 20.0,
            'line_total' => 200.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-LEGACY-001',
            'amount' => 200.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $showResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $showResponse->assertOk();
        $showResponse->assertSee('Cavendish Banana');
        $showResponse->assertDontSee('Cavendish Banana (');

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Cavendish Banana');
        $pdfResponse->assertDontSee('Cavendish Banana (');
    }

    public function test_goods_received_items_grade_appears_correctly(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'unpaid',
            'business_date' => now(),
            'cart_number' => 'VC-GRN-'.uniqid(),
            'bill_number' => 'INV-GRN-001',
        ]);

        $goodsReceived = GoodsReceived::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'status' => 'approved',
            'bill_status' => 'matched',
            'bill_number' => 'INV-GRN-001',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $goodsReceived->id,
            'product_id' => $this->product->id,
            'grade' => 'C',
            'received_qty' => 40.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-GRN-001',
            'amount' => 1200.0,
            'status' => 'pending',
            'purchaser_submitted_by' => $this->purchaser->id,
        ]);

        $pdfResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.pdf', $invoice));

        $pdfResponse->assertOk();
        $pdfResponse->assertSee('Cavendish Banana (C)');
    }

    public function test_purchaser_bill_renders_grade_suffixes_and_badges_for_all_grades(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'business_date' => now(),
            'cart_number' => 'VC-BILL-'.uniqid(),
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 35.0,
            'line_total' => 700.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 20.0,
            'unit_price' => 30.0,
            'line_total' => 600.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'C',
            'quantity' => 10.0,
            'unit_price' => 25.0,
            'line_total' => 250.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'MIXED',
            'quantity' => 15.0,
            'unit_price' => 20.0,
            'line_total' => 300.0,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.bill', $cart));

        $response->assertOk();
        $response->assertSee('Cavendish Banana (A)');
        $response->assertSee('Cavendish Banana (B)');
        $response->assertSee('Cavendish Banana (C)');
        $response->assertSee('Cavendish Banana (Mixed)');
    }

    public function test_purchase_manager_invoice_shows_compact_table_and_grade_suffixes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => now(),
            'cart_number' => 'VC-PM-'.uniqid(),
            'bill_number' => 'INV-PM-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 15.0,
            'unit_price' => 45.0,
            'line_total' => 675.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-PM-001',
            'amount' => 675.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('purchasing.invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('<th class="w-20 py-1 pr-1">GRADE</th>', false);
        $response->assertSee('Cavendish Banana (B)');
    }

    public function test_invoice_with_multiple_grades_preserves_distinct_lines_rates_and_totals(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => now(),
            'cart_number' => 'VC-MULTI-'.uniqid(),
            'bill_number' => 'INV-MULTI-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 35.0,
            'line_total' => 700.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 20.0,
            'unit_price' => 30.0,
            'line_total' => 600.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'C',
            'quantity' => 10.0,
            'unit_price' => 25.0,
            'line_total' => 250.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'MIXED',
            'quantity' => 15.0,
            'unit_price' => 20.0,
            'line_total' => 300.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-MULTI-001',
            'amount' => 1850.0,
            'discount_amount' => 0.0,
            'paid_amount' => 0.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Cavendish Banana (A)');
        $response->assertSee('Cavendish Banana (B)');
        $response->assertSee('Cavendish Banana (C)');
        $response->assertSee('Cavendish Banana (Mixed)');
        $response->assertSee('₹35.00');
        $response->assertSee('₹30.00');
        $response->assertSee('₹25.00');
        $response->assertSee('₹20.00');
        $response->assertSee('Rs. 700.00');
        $response->assertSee('Rs. 600.00');
        $response->assertSee('Rs. 250.00');
        $response->assertSee('Rs. 300.00');
        $response->assertSee('Rs. 1,850.00');
    }

    public function test_invoice_fallback_reconstructs_cart_items_safely_when_relation_not_eager_loaded(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => now(),
            'cart_number' => 'VC-SAFE-'.uniqid(),
            'bill_number' => 'INV-SAFE-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 50.0,
            'unit_price' => 40.0,
            'line_total' => 2000.0,
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-SAFE-001',
            'amount' => 2000.0,
            'status' => 'pending',
        ]);

        $freshInvoice = PurchaseInvoice::find($invoice->id);
        $freshInvoice->setRelation('purchaserCart', null);

        $this->assertSame(2000.0, $freshInvoice->itemsGrossTotal());
        $this->assertFalse($freshInvoice->hasCalculationError());
    }

    public function test_purchaser_vendors_draft_cart_displays_grade_suffixes_for_items(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'business_date' => now(),
            'cart_number' => 'VC-DRAFT-'.uniqid(),
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 35.0,
            'line_total' => 700.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 20.0,
            'unit_price' => 30.0,
            'line_total' => 600.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'C',
            'quantity' => 10.0,
            'unit_price' => 25.0,
            'line_total' => 250.0,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'MIXED',
            'quantity' => 15.0,
            'unit_price' => 20.0,
            'line_total' => 300.0,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors', ['date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertSee('Cavendish Banana (A)');
        $response->assertSee('Cavendish Banana (B)');
        $response->assertSee('Cavendish Banana (C)');
        $response->assertSee('Cavendish Banana (Mixed)');
    }

    public function test_purchaser_vendors_pending_tab_displays_grade_suffixes_for_items(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => now(),
            'cart_number' => 'VC-PENDING-'.uniqid(),
            'bill_number' => 'INV-PENDING-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'B',
            'quantity' => 15.0,
            'unit_price' => 45.0,
            'line_total' => 675.0,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors.tabs.pending', ['date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertSee('Cavendish Banana (B)');
    }

    public function test_purchaser_vendors_completed_tab_displays_grade_suffixes_for_items(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'payment_status' => 'paid',
            'business_date' => now(),
            'cart_number' => 'VC-COMPLETED-'.uniqid(),
            'bill_number' => 'INV-COMPLETED-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'C',
            'quantity' => 10.0,
            'unit_price' => 30.0,
            'line_total' => 300.0,
        ]);

        $goodsReceived = GoodsReceived::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'status' => 'approved',
            'bill_status' => 'matched',
            'bill_number' => 'INV-COMPLETED-001',
        ]);

        StockBatch::factory()->create([
            'goods_received_id' => $goodsReceived->id,
            'product_id' => $this->product->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
        ]);

        PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-COMPLETED-001',
            'amount' => 300.0,
            'discount_amount' => 0.0,
            'paid_amount' => 300.0,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors.tabs.completed', ['date' => now()->toDateString()]));

        $response->assertOk();
        $response->assertSee('Cavendish Banana (C)');
    }

    public function test_purchaser_suppliers_show_item_summary_contains_grade_suffixes(): void
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'business_date' => now(),
            'cart_number' => 'VC-SUPPLIER-'.uniqid(),
            'bill_number' => 'INV-SUPP-001',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 25.0,
            'unit_price' => 60.0,
            'line_total' => 1500.0,
        ]);

        PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-SUPP-001',
            'amount' => 1500.0,
            'discount_amount' => 0.0,
            'paid_amount' => 1500.0,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.suppliers.show', $this->supplier));

        $response->assertOk();
        $response->assertSee('Cavendish Banana (A)');
    }
}
