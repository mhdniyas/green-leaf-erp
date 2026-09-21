<?php

declare(strict_types=1);

namespace App\Queries\Purchasing\V2;

use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;

class PurchaserV2ReportQuery
{
    /**
     * Get isolated daily purchasing report data for the given operational date and grade.
     *
     * @return array{
     *     metrics: array{
     *         total_spend: float,
     *         total_paid: float,
     *         total_balance: float,
     *         distinct_products_count: int,
     *         total_quantity_bought: float,
     *         total_bills_count: int,
     *         paid_bills_count: int,
     *         credit_bills_count: int,
     *         total_approved_demand_qty: float,
     *         demand_fulfillment_pct: float
     *     },
     *     items: array<int, array{
     *         product_id: int,
     *         name: string,
     *         sku: string,
     *         category_name: string,
     *         unit: string,
     *         grade: string,
     *         quantity: float,
     *         avg_price: float,
     *         total_amount: float,
     *         approved_demand_qty: float,
     *         fulfillment_pct: float,
     *         suppliers: array<int, string>
     *     }>,
     *     bills: array<int, array{
     *         cart_id: int,
     *         cart_number: string,
     *         supplier_id: ?int,
     *         supplier_name: string,
     *         supplier_location: ?string,
     *         supplier_mobile: ?string,
     *         invoice_number: string,
     *         items_count: int,
     *         gross_amount: float,
     *         discount_amount: float,
     *         net_amount: float,
     *         paid_amount: float,
     *         balance_amount: float,
     *         payment_method: string,
     *         payment_status: string,
     *         bill_url: string,
     *         created_at: string
     *     }>
     * }
     */
    public function getDailyReport(
        Carbon $date,
        string $grade,
        User $user,
        ?string $search = null,
    ): array {
        $search = trim((string) $search);

        // 1. Fetch submitted carts for this user, date and grade with eager loading
        $carts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->where('status', 'submitted')
            ->when(in_array($grade, ['A', 'B'], true), fn ($q) => $q->where('purchase_grade', $grade))
            ->with([
                'supplier',
                'items.product.category',
                'purchaseInvoice',
            ])
            ->latest('updated_at')
            ->get();

        // 2. Fetch approved shop demand for the day
        $approvedDemand = ShopOrderItem::query()
            ->whereHas('order', function ($q) use ($date): void {
                $q->whereDate('business_date', $date)->where('state', 'approved');
            })
            ->when(in_array($grade, ['A', 'B'], true), fn ($q) => $q->where('product_grade', $grade))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(approved_qty) as total_approved')
            ->pluck('total_approved', 'product_id')
            ->map(fn ($qty): float => (float) $qty)
            ->all();

        // 3. Batch load invoices linked by purchaser_cart_id to prevent N+1
        $cartIds = $carts->pluck('id')->all();
        $invoicesByCartId = PurchaseInvoice::query()
            ->whereIn('purchaser_cart_id', $cartIds)
            ->get()
            ->keyBy('purchaser_cart_id');

        // 4. Aggregate Products & Bills
        $itemsMap = [];
        $totalQuantityBought = 0.0;
        $totalSpend = 0.0;
        $totalPaid = 0.0;
        $totalBalance = 0.0;
        $paidBillsCount = 0;
        $creditBillsCount = 0;
        $billsList = [];

        foreach ($carts as $cart) {
            $invoice = $cart->purchaseInvoice ?: $invoicesByCartId->get($cart->id);
            $supplierName = $cart->supplier?->name ?? 'Spot / Direct Vendor';
            $invoiceNumber = $invoice?->invoice_number ?? $cart->cart_number;

            $grossAmount = $invoice
                ? (float) $invoice->amount
                : (float) $cart->items->sum('line_total');

            $discountAmount = (float) ($invoice?->discount_amount ?? $cart->discount_amount ?? 0);
            $netAmount = max(0.0, round($grossAmount - $discountAmount, 2));

            $paidAmount = $invoice
                ? (float) $invoice->paid_amount
                : ($cart->payment_status === 'paid' ? $netAmount : 0.0);

            $balanceAmount = max(0.0, round($netAmount - $paidAmount, 2));
            $paymentMethod = (string) ($invoice?->payment_method ?? $cart->payment_method ?? 'Cash');
            $paymentStatus = (string) ($invoice?->payment_status ?? $cart->payment_status ?? ($balanceAmount <= 0 ? 'paid' : 'pending'));

            $totalSpend += $netAmount;
            $totalPaid += $paidAmount;
            $totalBalance += $balanceAmount;

            if ($balanceAmount <= 0.01 || strcasecmp($paymentStatus, 'paid') === 0) {
                $paidBillsCount++;
            } else {
                $creditBillsCount++;
            }

            // Prepare Bill row
            $billsList[] = [
                'cart_id' => (int) $cart->id,
                'cart_number' => $cart->cart_number,
                'supplier_id' => $cart->supplier_id ? (int) $cart->supplier_id : null,
                'supplier_name' => $supplierName,
                'supplier_location' => $cart->supplier?->location,
                'supplier_mobile' => $cart->supplier?->mobile_number,
                'invoice_number' => $invoiceNumber,
                'items_count' => $cart->items->count(),
                'gross_amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'bill_url' => route('purchaser.bill', ['cart' => $cart]),
                'created_at' => $cart->updated_at?->format('h:i A') ?? '',
            ];

            // Process Items within cart
            foreach ($cart->items as $item) {
                $productId = (int) $item->product_id;
                $productName = $item->product?->name ?? "Product #{$productId}";
                $sku = (string) ($item->product?->sku ?? '');
                $categoryName = (string) ($item->product?->category?->name ?? 'Produce');
                $unit = (string) ($item->unit ?? $item->product?->unit ?? 'kg');
                $qty = (float) $item->quantity;
                $lineTotal = (float) ($item->line_total ?: ($qty * (float) $item->unit_price));

                $totalQuantityBought += $qty;

                if (! isset($itemsMap[$productId])) {
                    $appDemand = $approvedDemand[$productId] ?? 0.0;
                    $itemsMap[$productId] = [
                        'product_id' => $productId,
                        'name' => $productName,
                        'sku' => $sku,
                        'category_name' => $categoryName,
                        'unit' => $unit,
                        'grade' => (string) ($item->grade ?? $cart->purchase_grade ?? $grade),
                        'quantity' => 0.0,
                        'total_amount' => 0.0,
                        'approved_demand_qty' => $appDemand,
                        'suppliers' => [],
                    ];
                }

                $itemsMap[$productId]['quantity'] += $qty;
                $itemsMap[$productId]['total_amount'] += $lineTotal;

                if (! in_array($supplierName, $itemsMap[$productId]['suppliers'], true)) {
                    $itemsMap[$productId]['suppliers'][] = $supplierName;
                }
            }
        }

        // Finalize Item calculations & search filtering
        $finalItems = [];
        foreach ($itemsMap as $prod) {
            $qty = $prod['quantity'];
            $amt = $prod['total_amount'];
            $appDemand = $prod['approved_demand_qty'];
            $avgPrice = $qty > 0 ? round($amt / $qty, 2) : 0.0;
            $fulfillmentPct = $appDemand > 0 ? min(100.0, round(($qty / $appDemand) * 100, 1)) : 100.0;

            if ($search !== '') {
                $matchesProduct = stripos($prod['name'], $search) !== false || stripos($prod['sku'], $search) !== false;
                $matchesSupplier = false;
                foreach ($prod['suppliers'] as $sup) {
                    if (stripos($sup, $search) !== false) {
                        $matchesSupplier = true;
                        break;
                    }
                }
                if (! $matchesProduct && ! $matchesSupplier) {
                    continue;
                }
            }

            $finalItems[] = [
                'product_id' => $prod['product_id'],
                'name' => $prod['name'],
                'sku' => $prod['sku'],
                'category_name' => $prod['category_name'],
                'unit' => $prod['unit'],
                'grade' => $prod['grade'],
                'quantity' => round($qty, 2),
                'avg_price' => $avgPrice,
                'total_amount' => round($amt, 2),
                'approved_demand_qty' => round($appDemand, 2),
                'fulfillment_pct' => $fulfillmentPct,
                'suppliers' => $prod['suppliers'],
            ];
        }

        // Filter bills if search query is provided
        if ($search !== '') {
            $billsList = array_values(array_filter($billsList, function ($b) use ($search): bool {
                return stripos($b['supplier_name'], $search) !== false
                    || stripos($b['invoice_number'], $search) !== false
                    || stripos($b['cart_number'], $search) !== false
                    || stripos((string) $b['supplier_location'], $search) !== false;
            }));
        }

        // Total approved demand for the day
        $totalApprovedDemandQty = array_sum($approvedDemand);
        $demandFulfillmentPct = $totalApprovedDemandQty > 0
            ? min(100.0, round(($totalQuantityBought / $totalApprovedDemandQty) * 100, 1))
            : 100.0;

        return [
            'metrics' => [
                'total_spend' => round($totalSpend, 2),
                'total_paid' => round($totalPaid, 2),
                'total_balance' => round($totalBalance, 2),
                'distinct_products_count' => count($itemsMap),
                'total_quantity_bought' => round($totalQuantityBought, 2),
                'total_bills_count' => count($carts),
                'paid_bills_count' => $paidBillsCount,
                'credit_bills_count' => $creditBillsCount,
                'total_approved_demand_qty' => round($totalApprovedDemandQty, 2),
                'demand_fulfillment_pct' => $demandFulfillmentPct,
            ],
            'items' => $finalItems,
            'bills' => $billsList,
        ];
    }
}
