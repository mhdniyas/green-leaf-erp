<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\GoodsReceived;
use App\Models\ProcurementExpense;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPurchaseDailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_and_admin_can_access_daily_report(): void
    {
        $this->get(route('admin.cashbook.finance.purchase.reports.daily'))
            ->assertRedirect(route('login'));

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.daily'));

        $response->assertOk()
            ->assertSee('Daily Report')
            ->assertSee('Daily Purchase Report')
            ->assertSee('Credit Purchase Report')
            ->assertSee('Purchaser Report')
            ->assertSee('Price Report')
            ->assertSee('Changed Items')
            ->assertSee('Purchaser Price')
            ->assertSee('Total Sales')
            ->assertSee('Total Purchase')
            ->assertSee('Purchaser Expenses')
            ->assertSee('Total Cost')
            ->assertSee('Difference');
    }

    public function test_daily_report_calculates_sales_purchase_expenses_and_difference_correctly(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'code' => 'MAIN-WH', 'is_active' => true]);
        $product = Product::factory()->create(['name' => 'Fresh Onion', 'default_warehouse_id' => $warehouse->id]);
        $purchaser = User::factory()->create(['name' => 'John Purchaser']);
        $purchaser->assignRole('purchaser');
        $supplier = Supplier::factory()->create(['name' => 'Star Farmer']);
        $shop = Shop::factory()->create(['name' => 'Shop Alpha', 'code' => 'ALPHA']);

        $date = '2026-08-25';

        // 1. Create Purchase of 100 kg @ ₹20 = ₹2,000
        $this->createPurchase($product, $date, 100, 20, $purchaser, $supplier);

        // 2. Create Purchaser Expense = ₹150 (Fuel)
        ProcurementExpense::query()->create([
            'user_id' => $purchaser->id,
            'expense_date' => $date,
            'category' => 'fuel',
            'amount' => 150.00,
            'note' => 'Market transport fuel',
        ]);

        // 3. Create Shop Sale = ₹2,500
        $this->createShopSale($shop, $product, $date, 100, 25.00);

        // Expected for date 2026-08-25:
        // Sales = ₹2,500.00
        // Purchase = ₹2,000.00
        // Purchaser Expenses = ₹150.00
        // Total Cost = ₹2,150.00
        // Difference = ₹2,500 - ₹2,150 = ₹350.00

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.daily', ['period' => 'today']));

        $response->assertOk()
            ->assertSee('25 Aug 2026')
            ->assertSee('2,500.00')
            ->assertSee('2,000.00')
            ->assertSee('150.00')
            ->assertSee('2,150.00')
            ->assertSee('350.00')
            ->assertSee('Shop Alpha')
            ->assertSee('Main Warehouse')
            ->assertSee('John Purchaser')
            ->assertSee('Star Farmer')
            ->assertSee('Market transport fuel')
            ->assertDontSee('Net Profit')
            ->assertDontSee('Gross Profit');
    }

    public function test_daily_report_supports_date_and_purchaser_and_warehouse_filters(): void
    {
        $warehouse1 = Warehouse::factory()->create(['name' => 'Veg Warehouse', 'code' => 'VEG-WH', 'is_active' => true]);
        $warehouse2 = Warehouse::factory()->create(['name' => 'Fruit Warehouse', 'code' => 'FRT-WH', 'is_active' => true]);

        $veg = Product::factory()->create(['name' => 'Tomato', 'default_warehouse_id' => $warehouse1->id]);
        $fruit = Product::factory()->create(['name' => 'Mango', 'default_warehouse_id' => $warehouse2->id]);

        $purchaser1 = User::factory()->create(['name' => 'Purchaser One']);
        $purchaser2 = User::factory()->create(['name' => 'Purchaser Two']);
        $purchaser1->assignRole('purchaser');
        $purchaser2->assignRole('purchaser');

        $supplier = Supplier::factory()->create(['name' => 'General Supplier']);
        $shop = Shop::factory()->create(['name' => 'Shop Beta', 'code' => 'BETA']);

        // Day 1: 2026-08-24 (Yesterday)
        $this->createPurchase($veg, '2026-08-24', 50, 10, $purchaser1, $supplier); // ₹500
        $this->createShopSale($shop, $veg, '2026-08-24', 50, 15); // ₹750

        // Day 2: 2026-08-25 (Today)
        $this->createPurchase($fruit, '2026-08-25', 40, 20, $purchaser2, $supplier); // ₹800
        $this->createShopSale($shop, $fruit, '2026-08-25', 40, 30); // ₹1200

        // Filter: Yesterday only
        $yesterdayResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.daily', ['period' => 'yesterday']));
        $yesterdayResponse->assertOk()
            ->assertSee('24 Aug 2026')
            ->assertSee('500.00')
            ->assertSee('750.00')
            ->assertDontSee('25 Aug 2026');

        // Filter: Warehouse 1 (Veg)
        $vegResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.daily', [
            'period' => 'month',
            'warehouse_id' => $warehouse1->id,
        ]));
        $vegResponse->assertOk()
            ->assertSee('24 Aug 2026')
            ->assertSee('500.00')
            ->assertDontSee('800.00');

        // Filter: Purchaser 2
        $purchaser2Response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.purchase.reports.daily', [
            'period' => 'month',
            'purchaser_id' => $purchaser2->id,
        ]));
        $purchaser2Response->assertOk()
            ->assertSee('25 Aug 2026')
            ->assertSee('800.00')
            ->assertDontSee('500.00');
    }

    private function createPurchase(Product $product, string $date, float $quantity, float $unitPrice, User $purchaser, Supplier $supplier): PurchaseInvoice
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $date,
            'status' => 'submitted',
            'cart_number' => 'VC-'.str()->upper(str()->random(12)),
            'payment_method' => 'Cash',
        ]);
        $goodsReceived = GoodsReceived::factory()->create(['purchaser_cart_id' => $cart->id]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $supplier->id,
            'purchaser_cart_id' => $cart->id,
            'invoice_number' => 'PUR-'.str()->upper(str()->random(10)),
            'amount' => $quantity * $unitPrice,
            'discount_amount' => 0,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'payment_paid_by' => 'purchaser',
            'paid_amount' => $quantity * $unitPrice,
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);

        return $invoice;
    }

    private function createShopSale(Shop $shop, Product $product, string $date, float $quantity, float $unitPrice): ShopInvoice
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'delivered',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'unit' => 'kg',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'business_date' => $date,
            'status' => 'paid',
            'subtotal' => $quantity * $unitPrice,
            'final_total' => $quantity * $unitPrice,
            'paid_amount' => $quantity * $unitPrice,
            'balance_amount' => 0,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $quantity,
            'delivered_qty' => $quantity,
            'unit_price' => $unitPrice,
            'line_subtotal' => $quantity * $unitPrice,
            'final_line_total' => $quantity * $unitPrice,
        ]);

        return $invoice;
    }
}
