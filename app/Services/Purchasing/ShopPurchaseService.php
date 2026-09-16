<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopVendorPayable;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\DailyLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ShopPurchaseService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly VendorPriceService $vendorPriceService,
        private readonly ShopPurchaserDailyVerificationService $verificationService,
    ) {}

    /**
     * Create a Shop Purchase (reusing PurchaserCart -> PO -> GRN -> PurchaseInvoice).
     *
     * @param  array{
     *     supplier_id?: ?int,
     *     new_supplier_name?: ?string,
     *     new_supplier_mobile?: ?string,
     *     business_date?: ?string,
     *     bill_number?: ?string,
     *     payment_method: string,
     *     discount_amount?: ?float,
     *     notes?: ?string,
     *     items: array<int, array{
     *         product_id: int,
     *         quantity: float|int|string,
     *         unit_price: float|int|string,
     *         unit?: ?string,
     *         grade?: ?string
     *     }>
     * } $payload
     */
    public function recordPurchase(Shop $shop, array $payload, User $user): PurchaseInvoice
    {
        if (! $shop->isPurchasingEnabled()) {
            throw new RuntimeException("Shop Purchasing is disabled for {$shop->name}.");
        }

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw new RuntimeException('Purchase must contain at least one item.');
        }

        $dateStr = (string) ($payload['business_date'] ?? $this->dailyLedgerService->resolveActiveBusinessDate($shop));
        $businessDate = Carbon::parse($dateStr)->startOfDay();

        // Enforce daily verification scope locking and day progression
        $this->verificationService->assertScopeNotFinalized((int) $shop->id, $dateStr, (int) $user->id);
        $this->verificationService->assertPreviousDayFinalized((int) $shop->id, $dateStr, (int) $user->id);

        return DB::transaction(function () use ($shop, $payload, $user, $items, $businessDate, $dateStr): PurchaseInvoice {
            $supplier = $this->resolveSupplier($shop, $payload, $user);
            $paymentMethod = ucfirst(strtolower(trim((string) $payload['payment_method'])));
            $isCash = strcasecmp($paymentMethod, 'Cash') === 0;
            $isCredit = strcasecmp($paymentMethod, 'Credit') === 0;

            if (! $isCash && ! $isCredit) {
                $paymentMethod = 'Cash';
                $isCash = true;
            }

            $discountAmount = max(0.0, round((float) ($payload['discount_amount'] ?? 0), 2));
            $rawBillNumber = trim((string) ($payload['bill_number'] ?? ''));

            // 1. Create PurchaserCart with shop scope
            $cartNumber = PurchaserCart::generateCartNumber($businessDate);
            $cart = PurchaserCart::query()->create([
                'user_id' => $user->id,
                'supplier_id' => $supplier->id,
                'destination_shop_id' => $shop->id,
                'business_date' => $businessDate,
                'status' => 'submitted',
                'purchase_source' => 'shop',
                'purchase_grade' => 'A',
                'cart_number' => $cartNumber,
                'bill_number' => $rawBillNumber ?: 'SHOP-'.$cartNumber,
                'discount_amount' => $discountAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $isCash ? 'paid' : 'pending',
                'paid_amount' => 0.0, // will compute after items
                'notes' => $payload['notes'] ?? null,
                'submitted_at' => now(),
                'bill_received_at' => now(),
                'goods_received_at' => now(),
                'payment_made_at' => $isCash ? now() : null,
            ]);

            // 2. Create Cart Items & compute totals
            $grossSubtotal = 0.0;
            $productIds = [];
            $cartItemModels = [];

            foreach ($items as $itemData) {
                $productId = (int) $itemData['product_id'];
                $quantity = max(0.0, (float) $itemData['quantity']);
                $unitPrice = max(0.0, (float) $itemData['unit_price']);
                $lineTotal = round($quantity * $unitPrice, 2);
                $grossSubtotal += $lineTotal;
                $productIds[] = $productId;

                /** @var PurchaserCartItem $cartItem */
                $cartItem = $cart->items()->create([
                    'product_id' => $productId,
                    'grade' => $itemData['grade'] ?? 'A',
                    'unit' => $itemData['unit'] ?? 'kg',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $cartItemModels[] = $cartItem;
            }

            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
            $grossAmount = round($grossSubtotal, 2);
            $netAmount = max(0.0, round($grossAmount - $discountAmount, 2));
            $paidAmount = $isCash ? $netAmount : 0.0;

            $cart->update(['paid_amount' => $paidAmount]);

            // 3. Create PO & GRN
            $poNumber = 'SPO-'.$businessDate->format('Ymd').'-'.Str::upper(Str::random(6));
            $purchaseOrder = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->id,
                'destination_shop_id' => $shop->id,
                'purchaser_cart_id' => $cart->id,
                'business_day_id' => $cart->business_day_id,
                'po_number' => $poNumber,
                'status' => POStatus::Received,
                'fulfillment_type' => 'shop',
                'order_date' => $businessDate,
                'created_by' => $user->id,
                'notes' => $payload['notes'] ?? 'Shop Purchase Order',
                'purchase_grade' => 'A',
            ]);

            $grnNumber = 'SGRN-'.$businessDate->format('Ymd').'-'.Str::upper(Str::random(6));
            $grn = GoodsReceived::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchaser_cart_id' => $cart->id,
                'business_day_id' => $cart->business_day_id,
                'grn_number' => $grnNumber,
                'status' => 'approved',
                'receipt_type' => 'normal_purchase',
                'received_by' => $user->id,
                'received_at' => $businessDate,
                'notes' => $payload['notes'] ?? 'Shop Goods Received',
                'is_extra' => false,
                'purchase_grade' => 'A',
            ]);

            foreach ($cartItemModels as $cartItem) {
                $product = $products->get((int) $cartItem->product_id);
                $isPerKg = ($product?->unit ?? 'kg') === 'kg';

                $poItem = $purchaseOrder->items()->create([
                    'product_id' => $cartItem->product_id,
                    'grade' => $cartItem->grade ?? 'A',
                    'purchase_unit' => $cartItem->unit ?: ($product?->unit ?? 'kg'),
                    'packet_qty' => $isPerKg ? null : $cartItem->quantity,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'price_basis' => $isPerKg ? 'per_kg' : 'per_unit',
                ]);

                $grn->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $cartItem->product_id,
                    'grade' => $cartItem->grade ?? 'A',
                    'received_qty' => $cartItem->quantity,
                    'received_unit' => $poItem->purchase_unit ?: ($product?->unit ?? 'kg'),
                    'variance' => 0,
                ]);
            }

            // 4. Create PurchaseInvoice
            $finalInvoiceNumber = $rawBillNumber ?: 'SPINV-'.$businessDate->format('Ymd').'-'.$cart->cart_number;
            $invoice = PurchaseInvoice::query()->create([
                'goods_received_id' => $grn->id,
                'supplier_id' => $supplier->id,
                'shop_id' => $shop->id,
                'purchaser_cart_id' => $cart->id,
                'business_day_id' => $cart->business_day_id,
                'purchase_source' => 'shop',
                'invoice_number' => $finalInvoiceNumber,
                'amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'status' => $isCash ? InvoiceStatus::Paid : InvoiceStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_paid_by' => $isCash ? 'purchaser' : 'vendor_credit',
                'payment_status' => $isCash ? 'paid' : 'pending',
                'paid_amount' => $paidAmount,
                'purchaser_submitted_by' => $user->id,
                'purchaser_submitted_at' => now(),
                'notes' => $payload['notes'] ?? null,
            ]);

            $cart->update([
                'purchase_order_id' => $purchaseOrder->id,
                'goods_received_id' => $grn->id,
                'purchase_invoice_id' => $invoice->id,
            ]);

            // 5. Handle Cash vs Credit specific logic
            if ($isCash) {
                // Record in Shop Cashbook via DailyLedgerService using stable code
                $this->syncCashbookCashPurchase($shop, $invoice, $supplier, $dateStr, $netAmount, $user->id, $cartItemModels, $products);
            } elseif ($isCredit) {
                // Record Shop Vendor Liability
                ShopVendorPayable::query()->create([
                    'purchase_invoice_id' => $invoice->id,
                    'shop_id' => $shop->id,
                    'supplier_id' => $supplier->id,
                    'business_date' => $businessDate,
                    'original_amount' => $grossAmount,
                    'paid_amount' => 0.0,
                    'outstanding_amount' => $netAmount,
                    'status' => 'unpaid',
                    'notes' => "Shop credit purchase from {$supplier->name} (Bill #{$invoice->invoice_number})",
                    'created_by' => $user->id,
                ]);
            }

            // Sync vendor price hints
            $this->vendorPriceService->syncMany(
                $supplier->id,
                collect($cartItemModels)->map(fn (PurchaserCartItem $it): array => [
                    'product_id' => (int) $it->product_id,
                    'unit_price' => (float) $it->unit_price,
                ])->all()
            );

            return $invoice->fresh(['supplier', 'purchaserCart.items.product', 'shopVendorPayable']);
        });
    }

    private function resolveSupplier(Shop $shop, array $payload, User $user): Supplier
    {
        $supplierId = ! empty($payload['supplier_id']) ? (int) $payload['supplier_id'] : null;

        if ($supplierId) {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()->findOrFail($supplierId);
        } else {
            $name = trim((string) ($payload['new_supplier_name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Supplier name is required when creating a new vendor.');
            }

            $mobile = trim((string) ($payload['new_supplier_mobile'] ?? ''));

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->firstOrCreate(
                ['name' => $name],
                [
                    'mobile_number' => $mobile ?: null,
                    'type' => 'local',
                    'category' => 'shop_vendor',
                    'credit_approved' => true,
                ]
            );
        }

        // Link supplier to shop if not already linked
        if (! $shop->suppliers()->where('supplier_id', $supplier->id)->exists()) {
            $shop->suppliers()->attach($supplier->id, ['is_active' => true]);
        }

        return $supplier;
    }

    private function syncCashbookCashPurchase(
        Shop $shop,
        PurchaseInvoice $invoice,
        Supplier $supplier,
        string $businessDate,
        float $netAmount,
        int $userId,
        array $cartItems,
        $products
    ): void {
        $entryType = LedgerEntryType::query()->where('code', 'vendor_purchase')->first()
            ?? LedgerEntryType::query()->where('code', 'cash_purchase')->first();

        if (! $entryType) {
            return;
        }

        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', (int) $shop->id)
            ->where('entry_type_id', (int) $entryType->id)
            ->first();

        $headerGroupId = $setting?->header_group_id;
        $headerGroup = $headerGroupId
            ? ShopLedgerHeaderGroup::query()->where('id', $headerGroupId)->where('shop_id', (int) $shop->id)->first()
            : null;

        if (! $headerGroup) {
            $headerGroup = ShopLedgerHeaderGroup::query()
                ->where('shop_id', (int) $shop->id)
                ->where('product_tagging_enabled', true)
                ->first() ?? ShopLedgerHeaderGroup::query()
                ->where('shop_id', (int) $shop->id)
                ->where('type', 'expense')
                ->first();
        }

        $fundingSource = $setting?->default_funding_source ?: 'sales';

        // Check if cashbook transaction already exists for this invoice reference
        $this->dailyLedgerService->recordEntry([
            'shop_id' => (int) $shop->id,
            'business_date' => $businessDate,
            'entry_type_code' => $entryType->code,
            'amount' => $netAmount,
            'funding_source' => $fundingSource,
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => (string) $invoice->id,
            'notes' => "Shop cash purchase from {$supplier->name} (Bill #{$invoice->invoice_number})",
            'entered_by' => $userId,
        ]);

        // If a header group exists, record product entries
        if ($headerGroup) {
            foreach ($cartItems as $cartItem) {
                $product = $products->get((int) $cartItem->product_id);
                ShopLedgerProductEntry::query()->updateOrCreate(
                    [
                        'shop_id' => (int) $shop->id,
                        'business_date' => $businessDate,
                        'header_group_id' => (int) $headerGroup->id,
                        'product_id' => (int) $cartItem->product_id,
                    ],
                    [
                        'product_name' => $product?->name ?? 'Product #'.$cartItem->product_id,
                        'product_sku' => $product?->sku ?? null,
                        'quantity' => (float) $cartItem->quantity,
                        'unit' => (string) ($cartItem->unit ?: ($product?->unit ?? 'kg')),
                        'amount' => (float) $cartItem->line_total,
                        'entered_by' => $userId,
                    ]
                );
            }
        }
    }
}
