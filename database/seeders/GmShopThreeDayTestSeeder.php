<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopLoanEntry;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 3 days of real GM Midland and Bazaro shop data for finance/admin dashboard testing.
 */
class GmShopThreeDayTestSeeder extends Seeder
{
    private const SHOPS = [
        'AV_GM_MIDLAND' => [
            'carry_over' => 67189.00,
            'days' => [
                [
                    'date' => '2026-07-01',
                    'sales' => 19607.00,
                    'rent' => 1960.70,
                    'vehicle' => 550.00,
                    'cash_purchase' => 250.00,
                    'loan_given' => 2000.00,
                    'invoice_total' => 20889.00,
                ],
                [
                    'date' => '2026-07-02',
                    'sales' => 23984.00,
                    'rent' => 2398.40,
                    'vehicle' => 550.00,
                    'cash_purchase' => 250.00,
                    'loan_given' => 0.00,
                    'invoice_total' => 18868.00,
                ],
                [
                    'date' => '2026-07-03',
                    'sales' => 19548.00,
                    'rent' => 1954.80,
                    'vehicle' => 1100.00,
                    'cash_purchase' => 150.00,
                    'loan_given' => 0.00,
                    'invoice_total' => 19941.00,
                ],
            ]
        ],
        'AV_LULU_BEGUR' => [
            'carry_over' => 377366.00,
            'days' => [
                [
                    'date' => '2026-07-01',
                    'sales' => 60522.00,
                    'rent' => 7262.0,
                    'loan_given' => 2000.00,
                    'cash_purchase' => 1150.00,
                    'invoice_total' => 50315.00,
                ],
                [
                    'date' => '2026-07-02',
                    'sales' => 57825.00,
                    'rent' => 6939.0,
                    'loan_given' => 2000.00,
                    'cash_purchase' => 650.00,
                    'invoice_total' => 49069.00,
                ],
                [
                    'date' => '2026-07-03',
                    'sales' => 44607.00,
                    'rent' => 5352.0,
                    'loan_given' => 2000.00,
                    'cash_purchase' => 400.00,
                    'invoice_total' => 49063.00,
                ],
            ]
        ]
    ];

    public function run(): void
    {
        $admin = User::role('admin')->first();

        if (!$admin instanceof User) {
            $this->command?->error('No admin user found. Run EssentialUserSeeder first.');

            return;
        }

        foreach (self::SHOPS as $code => $config) {
            $shop = Shop::query()->where('code', $code)->first();

            if (!$shop instanceof Shop) {
                $this->command?->warn("Shop {$code} not found. Skipping.");
                continue;
            }

            $shopOwner = User::role('shop')->where('shop_id', $shop->id)->first() ?? $admin;

            DB::transaction(function () use ($shop, $admin, $shopOwner, $config): void {
                $this->seedJuneCarryOver($shop, $admin, $config['carry_over']);

                $runningCash = $config['carry_over'];
                foreach ($config['days'] as $day) {
                    $this->seedDay($shop, $admin, $shopOwner, $day, $runningCash);
                }
            });

            $this->command?->info("✅ {$shop->name} 3-day test data seeded successfully.");
        }
    }

    /**
     * June period closure.
     */
    private function seedJuneCarryOver(Shop $shop, User $admin, float $amount): void
    {
        // Period closure for June
        ShopAccountingPeriodClosure::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
            ],
            [
                'closed_by' => $admin->id,
                'closed_at' => Carbon::parse('2026-07-01 09:00:00'),
                'notes' => 'June closing. Carry-over balance to collect from shop.',
            ],
        );

        // Carry-over credit entry
        ShopCredit::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'business_date' => '2026-06-30',
                'description' => 'June month-end carry-over balance to collect',
            ],
            [
                'type' => 'in',
                'is_petty_cash' => false,
                'amount' => $amount,
                'status' => 'approved',
                'created_by' => $admin->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::parse('2026-07-01 09:00:00'),
            ],
        );

        $this->command?->info("  → June carry-over for {$shop->name}: ₹" . number_format($amount, 2) . " seeded.");
    }

    /**
     * Seed a single business day.
     */
    private function seedDay(Shop $shop, User $admin, User $shopOwner, array $day, float &$runningCash): void
    {
        $date = $day['date'];

        // Shop Order (approved + delivered)
        if (($day['invoice_total'] ?? 0.0) > 0) {
            $order = $this->seedShopOrder($shop, $admin, $shopOwner, $date, $day['invoice_total']);
            // Shop Invoice
            $this->seedInvoice($shop, $admin, $order, $date, $day['invoice_total']);
        }

        // Daily accounting entry (cashbook)
        $this->seedAccountingEntry($shop, $admin, $shopOwner, $date, $day, $runningCash);

        // Loan given
        if (($day['loan_given'] ?? 0.0) > 0) {
            $this->seedLoanGiven($shop, $admin, $date, $day['loan_given']);
        }

        $this->command?->info(sprintf(
            '  → %s: Invoice ₹%s | Sales ₹%s | Loan given ₹%s',
            $date,
            number_format($day['invoice_total'], 2),
            number_format($day['sales'], 2),
            number_format($day['loan_given'] ?? 0.0, 2),
        ));
    }

    private function seedShopOrder(Shop $shop, User $admin, User $shopOwner, string $date, float $invoiceTotal): ShopOrder
    {
        $businessDate = Carbon::parse($date);
        $dailyKey = 'gm-test-seed:' . $shop->id . ':' . $date;

        $order = ShopOrder::query()->updateOrCreate(
            ['shop_daily_order_key' => $dailyKey],
            [
                'shop_id' => $shop->id,
                'order_source' => 'shop_owner',
                'state' => 'approved',
                'delivery_status' => 'delivered',
                'delivery_review_status' => 'approved',
                'payment_status' => 'unpaid',
                'business_date' => $date,
                'submitted_at' => $businessDate->copy()->subDay()->setTime(19, 0),
                'deadline_at' => $businessDate->copy()->subDay()->setTime(21, 0),
                'created_by' => $shopOwner->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => $businessDate->copy()->setTime(8, 0),
                'is_delivered' => true,
                'delivered_at' => $businessDate->copy()->setTime(10, 0),
                'delivered_by' => $admin->id,
                'is_late' => false,
                'manager_note' => 'Seeded 3-day test data.',
            ],
        );

        // Seed a representative order item (Tomato H, SKU 1)
        $product = Product::query()->where('sku', '1')->first()
            ?? Product::query()->where('is_active', true)->first();

        if ($product instanceof Product) {
            $qty = round($invoiceTotal / 40, 2);

            $order->items()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'product_grade' => 'A',
                    'requested_qty' => $qty,
                    'approved_qty' => $qty,
                    'unit' => $product->unit,
                    'requested_unit' => $product->unit,
                    'requested_unit_label' => strtoupper((string) $product->unit),
                    'requested_unit_quantity' => $qty,
                    'requested_unit_conversion_to_base' => 1,
                    'locked_selling_price' => 40.00,
                    'locked_price_source' => 'seed',
                    'line_total' => $invoiceTotal,
                    'fulfillment_type' => 'warehouse',
                    'sorting_status' => 'loaded',
                ],
            );
        }

        return $order;
    }

    private function seedInvoice(Shop $shop, User $admin, ShopOrder $order, string $date, float $total): ShopInvoice
    {
        $prefix = str_replace('AV_', '', $shop->code);
        $invoiceNumber = "SINV-{$prefix}-" . str_replace('-', '', $date);

        $invoice = ShopInvoice::query()->updateOrCreate(
            ['shop_order_id' => $order->id],
            [
                'shop_id' => $shop->id,
                'invoice_number' => $invoiceNumber,
                'business_date' => $date,
                'status' => 'finalized',
                'delivery_status' => 'received_full',
                'payment_status' => 'unpaid',
                'subtotal' => $total,
                'shortage_total' => 0,
                'excess_total' => 0,
                'discount_total' => 0,
                'final_total' => $total,
                'paid_amount' => 0,
                'balance_amount' => $total,
                'generated_by' => $admin->id,
                'delivery_confirmed_by' => $admin->id,
                'delivery_confirmed_at' => Carbon::parse($date)->setTime(11, 0),
            ],
        );

        $product = Product::query()->where('sku', '1')->first()
            ?? Product::query()->where('is_active', true)->first();

        if ($product instanceof Product) {
            $qty = round($total / 40, 2);

            ShopInvoiceItem::query()->updateOrCreate(
                ['shop_invoice_id' => $invoice->id, 'product_id' => $product->id],
                [
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'approved_qty' => $qty,
                    'delivered_qty' => $qty,
                    'shortage_qty' => 0,
                    'excess_qty' => 0,
                    'unit_price' => 40.00,
                    'line_subtotal' => $total,
                    'shortage_amount' => 0,
                    'excess_amount' => 0,
                    'final_line_total' => $total,
                ],
            );
        }

        return $invoice;
    }

    private function seedAccountingEntry(Shop $shop, User $admin, User $shopOwner, string $date, array $day, float &$runningCash): void
    {
        $dailyKey = ShopAccountingEntry::dailyEntryKey($shop->id, $date);
        $opening = $runningCash;
        $closing = $runningCash + $day['sales'] - $day['rent'];
        $runningCash = $closing;

        $entry = ShopAccountingEntry::query()->updateOrCreate(
            ['daily_entry_key' => $dailyKey],
            [
                'shop_id' => $shop->id,
                'business_date' => $date,
                'entry_type' => ShopAccountingEntry::TypeDaily,
                'status' => 'approved',
                'opening_cash' => $opening,
                'closing_cash' => $closing,
                'notes' => 'Seeded 3-day test data.',
                'created_by' => $shopOwner->id,
                'updated_by' => $shopOwner->id,
                'submitted_by' => $shopOwner->id,
                'submitted_at' => Carbon::parse($date)->setTime(20, 0),
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::parse($date)->setTime(21, 0),
            ],
        );

        $entry->lines()->delete();

        $salesCat = $this->category($shop, 'income', 'Sales');
        $rentCat = $this->category($shop, 'expense', 'Rent');
        $vehicleCat = $this->category($shop, 'expense', 'Vehicle');
        $cashPurCat = $this->category($shop, 'expense', 'Cash Purchase');

        $lines = [
            ['category' => $salesCat, 'type' => 'income', 'cash_effect' => true, 'amount' => $day['sales'], 'desc' => 'Daily sales income'],
            ['category' => $rentCat, 'type' => 'expense', 'cash_effect' => true, 'amount' => $day['rent'], 'desc' => 'Rent expense'],
            ['category' => $vehicleCat, 'type' => 'expense', 'cash_effect' => true, 'amount' => $day['vehicle'] ?? 0.0, 'desc' => 'Vehicle expense', 'is_loan' => true],
            ['category' => $cashPurCat, 'type' => 'expense', 'cash_effect' => true, 'amount' => $day['cash_purchase'] ?? 0.0, 'desc' => 'Cash purchase (from loan)', 'is_loan' => true],
        ];

        foreach ($lines as $line) {
            if (!$line['category'] instanceof ShopAccountingCategory) {
                continue;
            }

            ShopAccountingEntryLine::query()->create([
                'shop_accounting_entry_id' => $entry->id,
                'shop_accounting_category_id' => $line['category']->id,
                'type' => $line['type'],
                'cash_effect' => $line['cash_effect'],
                'is_loan_entry' => $line['is_loan'] ?? false,
                'amount' => $line['amount'],
                'description' => $line['desc'],
                'review_status' => 'approved',
            ]);
        }
    }

    private function seedLoanGiven(Shop $shop, User $admin, string $date, float $amount): void
    {
        ShopLoanEntry::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'business_date' => $date,
                'type' => ShopLoanEntry::TypeCashGiven,
            ],
            [
                'title' => 'Cash Given',
                'amount' => $amount,
                'status' => 'approved',
                'created_by' => $admin->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => Carbon::parse($date)->setTime(15, 0),
            ]
        );
    }

    private function category(Shop $shop, string $type, string $name): ?ShopAccountingCategory
    {
        return ShopAccountingCategory::query()
            ->where(function ($q) use ($shop): void {
                $q->whereNull('shop_id')
                    ->orWhere('shop_id', $shop->id);
            })
            ->where('type', $type)
            ->where('name', $name)
            ->first();
    }
}
