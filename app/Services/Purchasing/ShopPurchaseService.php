<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\BusinessSetting;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
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
use App\Services\Cashbook\CashbookShopSyncService;
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

        $items = $this->consolidateItems($payload['items'] ?? []);
        if (empty($items)) {
            throw new RuntimeException('Purchase must contain at least one item.');
        }

        $dateStr = (string) ($payload['business_date'] ?? $this->dailyLedgerService->resolveActiveBusinessDate($shop));
        $businessDate = Carbon::parse($dateStr)->startOfDay();

        $isShopOwnerFlow = ! empty($payload['shop_ledger_entry_setting_id'])
            || ! empty($payload['is_shop_owner_flow'])
            || ($payload['purchase_flow'] ?? null) === 'shop_owner';

        // Enforce daily verification scope locking and day progression only for central purchaser flow
        if (! $isShopOwnerFlow) {
            $this->verificationService->assertScopeNotFinalized((int) $shop->id, $dateStr, (int) $user->id);
            $this->verificationService->assertPreviousDayFinalized((int) $shop->id, $dateStr, (int) $user->id);
        }

        return DB::transaction(function () use ($shop, $payload, $user, $items, $businessDate, $dateStr): PurchaseInvoice {
            $supplier = $this->resolveSupplier($shop, $payload, $user);
            $paymentMethod = ucfirst(strtolower(trim((string) $payload['payment_method'])));
            $isCash = strcasecmp($paymentMethod, 'Cash') === 0;
            $isCredit = strcasecmp($paymentMethod, 'Credit') === 0;

            if (! $isCash && ! $isCredit) {
                $paymentMethod = 'Cash';
                $isCash = true;
            }

            $categorySetting = $this->resolveVendorPurchaseCategorySetting(
                $shop,
                $paymentMethod,
                ! empty($payload['shop_ledger_entry_setting_id']) ? (int) $payload['shop_ledger_entry_setting_id'] : null
            );
            $headerGroupId = $categorySetting?->header_group_id;

            $discountAmount = max(0.0, round((float) ($payload['discount_amount'] ?? 0), 2));
            $rawBillNumber = trim((string) ($payload['bill_number'] ?? ''));

            // 1. Create PurchaserCart with shop scope
            $cartNumber = PurchaserCart::generateCartNumber($businessDate);
            $cart = PurchaserCart::query()->create([
                'user_id' => $user->id,
                'supplier_id' => $supplier->id,
                'destination_shop_id' => $shop->id,
                'shop_ledger_entry_setting_id' => $categorySetting?->id,
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
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $lineTotal = (float) $itemData['line_total'];
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
                'shop_ledger_entry_setting_id' => $categorySetting?->id,
                'original_header_group_id' => $headerGroupId,
                'purchaser_cart_id' => $cart->id,
                'business_day_id' => $cart->business_day_id,
                'purchase_source' => 'shop',
                'invoice_number' => $finalInvoiceNumber,
                'original_business_date' => $businessDate->toDateString(),
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
                // Record in Shop Cashbook via DailyLedgerService
                $this->syncCashbookCashPurchase($shop, $invoice, $supplier, $dateStr, $netAmount, $user->id, $categorySetting);
            } elseif ($isCredit) {
                // Record Shop Vendor Liability
                ShopVendorPayable::query()->create([
                    'purchase_invoice_id' => $invoice->id,
                    'shop_id' => $shop->id,
                    'supplier_id' => $supplier->id,
                    'shop_ledger_entry_setting_id' => $categorySetting?->id,
                    'original_header_group_id' => $headerGroupId,
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

    /**
     * Update an existing Shop Purchase.
     *
     * @param  array{
     *     supplier_id?: ?int,
     *     new_supplier_name?: ?string,
     *     new_supplier_mobile?: ?string,
     *     bill_number?: ?string,
     *     payment_method?: ?string,
     *     discount_amount?: ?float,
     *     notes?: ?string,
     *     shop_ledger_entry_setting_id?: ?int,
     *     items?: array<int, array{
     *         product_id: int,
     *         quantity: float|int|string,
     *         unit_price: float|int|string,
     *         unit?: ?string,
     *         grade?: ?string
     *     }>
     * } $payload
     */
    public function updatePurchase(PurchaseInvoice $invoice, array $payload, User $user): PurchaseInvoice
    {
        if ($invoice->status === InvoiceStatus::Cancelled || $invoice->trashed()) {
            throw new RuntimeException('Cannot edit a cancelled purchase.');
        }

        /** @var Shop $shop */
        $shop = $invoice->shop ?? Shop::query()->findOrFail($invoice->shop_id);

        $dateStr = $invoice->purchaserCart?->business_date?->format('Y-m-d') ?? $invoice->created_at->format('Y-m-d');
        $settingId = $payload['shop_ledger_entry_setting_id'] ?? $invoice->shop_ledger_entry_setting_id;

        $categorySetting = $settingId
            ? ShopLedgerEntrySetting::query()->with(['entryType', 'headerGroup'])->where('shop_id', (int) $shop->id)->find((int) $settingId)
            : null;

        // Check cashbook edit policy on the date & category setting
        if ($categorySetting?->entry_type_id) {
            $this->dailyLedgerService->assertEditAllowed((int) $shop->id, $dateStr, (int) $categorySetting->entry_type_id);
        }

        return DB::transaction(function () use ($shop, $invoice, $payload, $user, $dateStr): PurchaseInvoice {
            $supplier = $this->resolveSupplier($shop, $payload, $user);

            $paymentMethod = isset($payload['payment_method'])
                ? ucfirst(strtolower(trim((string) $payload['payment_method'])))
                : $invoice->payment_method;

            $isCash = strcasecmp($paymentMethod, 'Cash') === 0;
            $isCredit = strcasecmp($paymentMethod, 'Credit') === 0;

            if (! $isCash && ! $isCredit) {
                $paymentMethod = 'Cash';
                $isCash = true;
            }

            $categorySetting = $this->resolveVendorPurchaseCategorySetting(
                $shop,
                $paymentMethod,
                ! empty($payload['shop_ledger_entry_setting_id']) ? (int) $payload['shop_ledger_entry_setting_id'] : (int) $invoice->shop_ledger_entry_setting_id
            );
            $headerGroupId = $categorySetting?->header_group_id ?? $invoice->original_header_group_id;

            $discountAmount = array_key_exists('discount_amount', $payload)
                ? max(0.0, round((float) $payload['discount_amount'], 2))
                : (float) $invoice->discount_amount;

            $rawBillNumber = array_key_exists('bill_number', $payload)
                ? trim((string) $payload['bill_number'])
                : $invoice->invoice_number;

            $notes = array_key_exists('notes', $payload) ? $payload['notes'] : $invoice->notes;

            $cart = $invoice->purchaserCart;
            $purchaseOrder = $cart?->purchaseOrder;
            $grn = $cart?->goodsReceived;

            // Handle items update if provided
            $cartItemModels = [];
            $grossSubtotal = 0.0;
            $productIds = [];

            if (isset($payload['items']) && is_array($payload['items']) && ! empty($payload['items'])) {
                $consolidatedItems = $this->consolidateItems($payload['items']);
                if (empty($consolidatedItems)) {
                    throw new RuntimeException('Purchase must contain at least one valid item with quantity greater than 0.');
                }

                // Remove old items
                if ($cart) {
                    $cart->items()->delete();
                }
                if ($purchaseOrder) {
                    $purchaseOrder->items()->delete();
                }
                if ($grn) {
                    $grn->items()->delete();
                }

                foreach ($consolidatedItems as $itemData) {
                    $productId = (int) $itemData['product_id'];
                    $quantity = (float) $itemData['quantity'];
                    $unitPrice = (float) $itemData['unit_price'];
                    $lineTotal = (float) $itemData['line_total'];
                    $grossSubtotal += $lineTotal;
                    $productIds[] = $productId;

                    if ($cart) {
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
                }

                $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

                if ($purchaseOrder && $grn) {
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
                }
            } else {
                $grossSubtotal = (float) $invoice->amount;
            }

            $grossAmount = round($grossSubtotal, 2);
            $netAmount = max(0.0, round($grossAmount - $discountAmount, 2));
            $paidAmount = $isCash ? $netAmount : 0.0;

            // Update Cart
            if ($cart) {
                $cart->update([
                    'supplier_id' => $supplier->id,
                    'shop_ledger_entry_setting_id' => $categorySetting?->id ?? $cart->shop_ledger_entry_setting_id,
                    'bill_number' => $rawBillNumber ?: $cart->bill_number,
                    'discount_amount' => $discountAmount,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $isCash ? 'paid' : 'pending',
                    'paid_amount' => $paidAmount,
                    'notes' => $notes,
                    'payment_made_at' => $isCash ? ($cart->payment_made_at ?: now()) : null,
                ]);
            }

            // Update PO & GRN
            if ($purchaseOrder) {
                $purchaseOrder->update([
                    'supplier_id' => $supplier->id,
                    'notes' => $notes ?: $purchaseOrder->notes,
                ]);
            }
            if ($grn) {
                $grn->update([
                    'notes' => $notes ?: $grn->notes,
                ]);
            }

            // Update Invoice
            $invoice->update([
                'supplier_id' => $supplier->id,
                'shop_ledger_entry_setting_id' => $categorySetting?->id ?? $invoice->shop_ledger_entry_setting_id,
                'original_header_group_id' => $invoice->original_header_group_id ?? $headerGroupId,
                'invoice_number' => $rawBillNumber ?: $invoice->invoice_number,
                'amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'status' => $isCash ? InvoiceStatus::Paid : InvoiceStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_paid_by' => $isCash ? 'purchaser' : 'vendor_credit',
                'payment_status' => $isCash ? 'paid' : 'pending',
                'paid_amount' => $paidAmount,
                'notes' => $notes,
            ]);

            // Synchronize Cashbook and ShopVendorPayable
            $existingPayable = ShopVendorPayable::query()->where('purchase_invoice_id', $invoice->id)->first();
            $mirroredTx = ShopLedgerTransaction::query()
                ->where('reference_type', PurchaseInvoice::class)
                ->where('reference_id', (string) $invoice->id)
                ->where('status', '!=', 'void')
                ->first();

            if ($isCash) {
                // If previously Credit, clean up payable
                if ($existingPayable) {
                    if ((float) $existingPayable->paid_amount > 0) {
                        throw new RuntimeException('Cannot switch payment method to Cash because payments have already been recorded on this payable.');
                    }
                    $existingPayable->delete();
                }

                // Sync cashbook mirrored transaction
                $shouldMirror = $categorySetting ? (bool) ($categorySetting->mirror_to_cashbook ?? true) : true;
                if ($shouldMirror) {
                    $this->syncCashbookCashPurchase($shop, $invoice, $supplier, $dateStr, $netAmount, $user->id, $categorySetting);
                } else {
                    if ($mirroredTx) {
                        $this->dailyLedgerService->deleteEntry((int) $mirroredTx->id);
                    }
                }
            } elseif ($isCredit) {
                // If previously Cash, remove cashbook mirrored transaction
                if ($mirroredTx) {
                    $this->dailyLedgerService->deleteEntry((int) $mirroredTx->id);
                }

                // Create or update payable
                if ($existingPayable) {
                    $existingPayable->update([
                        'supplier_id' => $supplier->id,
                        'shop_ledger_entry_setting_id' => $categorySetting?->id ?? $existingPayable->shop_ledger_entry_setting_id,
                        'original_header_group_id' => $existingPayable->original_header_group_id ?? $headerGroupId,
                        'original_amount' => $grossAmount,
                        'outstanding_amount' => max(0.0, round($netAmount - (float) $existingPayable->paid_amount, 2)),
                        'notes' => "Shop credit purchase from {$supplier->name} (Bill #{$invoice->invoice_number})",
                    ]);
                } else {
                    ShopVendorPayable::query()->create([
                        'purchase_invoice_id' => $invoice->id,
                        'shop_id' => $shop->id,
                        'supplier_id' => $supplier->id,
                        'shop_ledger_entry_setting_id' => $categorySetting?->id,
                        'original_header_group_id' => $headerGroupId,
                        'business_date' => Carbon::parse($dateStr)->startOfDay(),
                        'original_amount' => $grossAmount,
                        'paid_amount' => 0.0,
                        'outstanding_amount' => $netAmount,
                        'status' => 'unpaid',
                        'notes' => "Shop credit purchase from {$supplier->name} (Bill #{$invoice->invoice_number})",
                        'created_by' => $user->id,
                    ]);
                }
            }

            // Sync vendor price hints
            if (! empty($cartItemModels)) {
                $this->vendorPriceService->syncMany(
                    $supplier->id,
                    collect($cartItemModels)->map(fn (PurchaserCartItem $it): array => [
                        'product_id' => (int) $it->product_id,
                        'unit_price' => (float) $it->unit_price,
                    ])->all()
                );
            }

            return $invoice->fresh(['supplier', 'purchaserCart.items.product', 'shopVendorPayable']);
        });
    }

    /**
     * Safely cancel / reverse a Shop Purchase.
     */
    public function cancelPurchase(PurchaseInvoice $invoice, User $user, ?string $reason = null): void
    {
        if ($invoice->status === InvoiceStatus::Cancelled || $invoice->trashed()) {
            return;
        }

        $existingPayable = ShopVendorPayable::query()->where('purchase_invoice_id', $invoice->id)->first();
        if ($existingPayable && (float) $existingPayable->paid_amount > 0) {
            throw new RuntimeException('Cannot cancel purchase because payments have already been recorded on this payable.');
        }

        DB::transaction(function () use ($invoice, $user, $reason, $existingPayable): void {
            // 1. Remove/reverse mirrored cashbook transaction if present
            $mirroredTx = ShopLedgerTransaction::query()
                ->where('reference_type', PurchaseInvoice::class)
                ->where('reference_id', (string) $invoice->id)
                ->where('status', '!=', 'void')
                ->first();

            if ($mirroredTx) {
                $this->dailyLedgerService->deleteEntry((int) $mirroredTx->id);
            }

            // 2. Remove/reverse payable if present
            if ($existingPayable) {
                $existingPayable->delete();
            }

            // 3. Mark Invoice as cancelled and soft delete
            $invoice->update([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason ?? 'Cancelled by shop owner',
                'cancellation_note' => $reason,
            ]);
            $invoice->delete();

            // 4. Mark Cart as cancelled
            if ($invoice->purchaserCart) {
                $invoice->purchaserCart->update([
                    'status' => 'cancelled',
                ]);
            }
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
        ?ShopLedgerEntrySetting $categorySetting = null
    ): void {
        $setting = $categorySetting;

        if ($setting) {
            $entryType = $setting->entryType;
        } else {
            $entryType = LedgerEntryType::query()->where('code', 'vendor_purchase')->first()
                ?? LedgerEntryType::query()->where('code', 'cash_purchase')->first();

            if (! $entryType) {
                return;
            }

            $setting = ShopLedgerEntrySetting::query()
                ->where('shop_id', (int) $shop->id)
                ->where('entry_type_id', (int) $entryType->id)
                ->first();
        }

        if (! $entryType) {
            return;
        }

        // If mirror_to_cashbook is explicitly disabled, do not mirror
        if ($setting && $setting->mirror_to_cashbook === false) {
            return;
        }

        $rawFundingSource = $setting?->default_funding_source ?: 'sales';
        $fundingSource = match ($rawFundingSource) {
            'shop_cash', 'sales' => 'sales',
            'petty' => 'petty',
            'company' => 'company',
            'bank' => 'bank',
            default => 'sales',
        };

        // Check if cashbook transaction already exists for this invoice reference
        $existingTx = ShopLedgerTransaction::query()
            ->where('shop_id', (int) $shop->id)
            ->where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', (string) $invoice->id)
            ->where('status', '!=', 'void')
            ->first();

        if ($existingTx) {
            $this->dailyLedgerService->updateEntry(
                (int) $existingTx->id,
                $netAmount,
                $fundingSource,
                "Shop cash purchase from {$supplier->name} (Bill #{$invoice->invoice_number})",
                $userId
            );
        } else {
            $this->dailyLedgerService->recordEntry([
                'shop_id' => (int) $shop->id,
                'business_date' => $businessDate,
                'entry_type_id' => $entryType->id,
                'entry_type_code' => $entryType->code,
                'amount' => $netAmount,
                'funding_source' => $fundingSource,
                'reference_type' => PurchaseInvoice::class,
                'reference_id' => (string) $invoice->id,
                'notes' => "Shop cash purchase from {$supplier->name} (Bill #{$invoice->invoice_number})",
                'entered_by' => $userId,
            ]);
        }
    }

    /**
     * Consolidate incoming items by product_id and grade.
     * Combines quantities and line totals, calculating a blended unit price.
     *
     * @param  array<int, array{product_id: int|string, quantity?: float|int|string, qty?: float|int|string, unit_price?: float|int|string, total_price?: float|int|string, line_total?: float|int|string, unit?: ?string, grade?: ?string}>  $items
     * @return array<int, array{product_id: int, grade: string, unit: string, quantity: float, unit_price: float, line_total: float}>
     */
    public function consolidateItems(array $items): array
    {
        $consolidated = [];

        foreach ($items as $itemData) {
            $productId = (int) ($itemData['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $rawGrade = strtoupper(trim((string) ($itemData['grade'] ?? 'A')));
            $grade = in_array($rawGrade, ['A', 'B'], true) ? $rawGrade : 'A';

            $quantity = isset($itemData['quantity']) ? (float) $itemData['quantity'] : (isset($itemData['qty']) ? (float) $itemData['qty'] : 0.0);
            if ($quantity <= 0.0) {
                continue;
            }

            $lineTotal = 0.0;
            if (isset($itemData['total_price']) && (float) $itemData['total_price'] > 0) {
                $lineTotal = round((float) $itemData['total_price'], 2);
            } elseif (isset($itemData['unit_price'])) {
                $lineTotal = round($quantity * (float) $itemData['unit_price'], 2);
            } elseif (isset($itemData['line_total'])) {
                $lineTotal = round((float) $itemData['line_total'], 2);
            }

            $unit = trim((string) ($itemData['unit'] ?? 'kg')) ?: 'kg';
            $key = $productId.'-'.$grade;

            if (! isset($consolidated[$key])) {
                $consolidated[$key] = [
                    'product_id' => $productId,
                    'grade' => $grade,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            } else {
                $consolidated[$key]['quantity'] += $quantity;
                $consolidated[$key]['line_total'] += $lineTotal;
                if ($unit !== 'kg' && $consolidated[$key]['unit'] === 'kg') {
                    $consolidated[$key]['unit'] = $unit;
                }
            }
        }

        $result = [];
        foreach ($consolidated as $entry) {
            $qty = round($entry['quantity'], 4);
            $total = round($entry['line_total'], 2);
            $unitPrice = $qty > 0 ? round($total / $qty, 4) : 0.0;

            $result[] = [
                'product_id' => $entry['product_id'],
                'grade' => $entry['grade'],
                'unit' => $entry['unit'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $total,
            ];
        }

        return $result;
    }

    /**
     * @return array{value: int, unit: string, display: string}
     */
    public function getEditWindowConfig(?Shop $shop = null): array
    {
        $value = $shop?->vendor_purchase_edit_window_value;
        if ($value === null || (int) $value <= 0) {
            $value = (int) (BusinessSetting::query()->where('key', 'vendor_purchase_edit_window_value')->value('value') ?? 3);
        }
        $value = (int) $value;
        if ($value <= 0) {
            $value = 3;
        }

        $unit = $shop?->vendor_purchase_edit_window_unit;
        if (! $unit) {
            $unit = (string) (BusinessSetting::query()->where('key', 'vendor_purchase_edit_window_unit')->value('value') ?? 'days');
        }
        $unit = strtolower((string) $unit);
        if (! in_array($unit, ['hours', 'days'], true)) {
            $unit = 'days';
        }

        $displayUnit = $unit === 'hours' ? ($value === 1 ? 'Hour' : 'Hours') : ($value === 1 ? 'Day' : 'Days');

        return [
            'value' => $value,
            'unit' => $unit,
            'display' => "{$value} {$displayUnit}",
        ];
    }

    public function getEditWindowDisplayText(?Shop $shop = null): string
    {
        return $this->getEditWindowConfig($shop)['display'];
    }

    public function getCutoffDateTime(?Shop $shop = null): Carbon
    {
        $config = $this->getEditWindowConfig($shop);
        $now = now('Asia/Kolkata');

        if ($config['unit'] === 'hours') {
            return $now->copy()->subHours($config['value']);
        }

        return $now->copy()->startOfDay()->subDays($config['value']);
    }

    public function getCutoffDateString(?Shop $shop = null): string
    {
        return $this->getCutoffDateTime($shop)->toDateString();
    }

    public function isDateActionAllowed(string|Carbon $businessDate, ?User $user, ?Shop $shop = null): bool
    {
        if ($this->isAdminUser($user)) {
            return true;
        }

        $date = $businessDate instanceof Carbon ? $businessDate : Carbon::parse($businessDate);
        $config = $this->getEditWindowConfig($shop);

        if ($config['unit'] === 'hours') {
            $cutoff = $this->getCutoffDateTime($shop);

            return $date->copy()->endOfDay()->greaterThanOrEqualTo($cutoff);
        }

        $cutoffDateStr = $this->getCutoffDateString($shop);

        return $date->toDateString() >= $cutoffDateStr;
    }

    public function isInvoiceActionAllowed(PurchaseInvoice $invoice, ?User $user): bool
    {
        if ($this->isAdminUser($user)) {
            return true;
        }

        $dateStr = $invoice->purchaserCart?->business_date?->format('Y-m-d')
            ?? $invoice->original_business_date?->format('Y-m-d')
            ?? $invoice->created_at->format('Y-m-d');

        /** @var Shop|null $shop */
        $shop = $invoice->shop ?? ($invoice->shop_id ? Shop::query()->find($invoice->shop_id) : null);

        if (! $this->isDateActionAllowed($dateStr, $user, $shop)) {
            return false;
        }

        return true;
    }

    public function isAdminUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('main_admin') || $user->hasRole('super_admin') || (method_exists($user, 'isMainAdmin') && $user->isMainAdmin());
    }

    /**
     * Authoritatively resolve the ShopLedgerEntrySetting for a purchase based on shop and payment method.
     */
    public function resolveVendorPurchaseCategorySetting(Shop $shop, string $paymentMethod, ?int $explicitSettingId = null): ?ShopLedgerEntrySetting
    {
        $isCash = strcasecmp($paymentMethod, 'Cash') === 0;
        $targetType = $isCash ? 'cash' : 'credit';
        $targetCode = $isCash ? 'vendor_purchase_cash' : 'vendor_purchase_credit';

        // 1. First priority: match by vendor_purchase_payment_type on the shop's vendor purchase settings
        $setting = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup'])
            ->where('shop_id', (int) $shop->id)
            ->where('is_vendor_purchase', true)
            ->where('vendor_purchase_payment_type', $targetType)
            ->first();

        // 2. Second priority: match by entry type code
        if (! $setting) {
            $setting = ShopLedgerEntrySetting::query()
                ->with(['entryType', 'headerGroup'])
                ->where('shop_id', (int) $shop->id)
                ->whereHas('entryType', fn ($q) => $q->where('code', $targetCode))
                ->first();
        }

        // 3. If missing, ensure categories exist for shop and try again
        if (! $setting) {
            app(CashbookShopSyncService::class)->ensureVendorPurchaseForShop((int) $shop->id);

            $setting = ShopLedgerEntrySetting::query()
                ->with(['entryType', 'headerGroup'])
                ->where('shop_id', (int) $shop->id)
                ->where('is_vendor_purchase', true)
                ->where('vendor_purchase_payment_type', $targetType)
                ->first();
        }

        // 4. If explicit ID provided and belongs to shop, check if it's a valid fallback
        if (! $setting && $explicitSettingId) {
            $explicit = ShopLedgerEntrySetting::query()
                ->with(['entryType', 'headerGroup'])
                ->where('shop_id', (int) $shop->id)
                ->find($explicitSettingId);

            if ($explicit && $explicit->is_vendor_purchase) {
                $setting = $explicit;
            }
        }

        // 5. Final fallback to any vendor purchase setting on the shop
        if (! $setting) {
            $setting = ShopLedgerEntrySetting::query()
                ->with(['entryType', 'headerGroup'])
                ->where('shop_id', (int) $shop->id)
                ->where('is_vendor_purchase', true)
                ->first();
        }

        return $setting;
    }
}
