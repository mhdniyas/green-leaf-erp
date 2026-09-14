<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCustomer;
use App\Models\WarehouseSale;
use App\Models\WarehouseSaleItem;
use App\Models\WarehouseSalePayment;
use App\Services\Inventory\StockLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseSaleService
{
    public function __construct(
        private readonly WarehouseSalesAccessService $accessService,
        private readonly StockLedgerService $stockLedgerService,
    ) {}

    /**
     * Generate unique sequential invoice number: WS-YYYYMMDD-XXXX.
     */
    public function generateInvoiceNumber(string $date): string
    {
        $dateStr = Carbon::parse($date)->format('Ymd');
        $prefix = "WS-{$dateStr}-";

        $count = WarehouseSale::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->count();

        $nextNum = $count + 1;

        // Ensure uniqueness even in high concurrency
        while (WarehouseSale::query()->where('invoice_number', $prefix.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT))->exists()) {
            $nextNum++;
        }

        return $prefix.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create and confirm a new warehouse sale.
     *
     * @param  array{
     *     warehouse_id: int,
     *     customer_id?: int|null,
     *     customer_name: string,
     *     customer_phone?: string|null,
     *     business_date: string,
     *     discount?: float|int|null,
     *     notes?: string|null,
     *     payment_method: string,
     *     money_holder_type?: string|null,
     *     money_holder_user_id?: int|null,
     *     company_account_id?: int|null,
     *     reference?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         grade?: string|null,
     *         qty: float|int,
     *         unit?: string|null,
     *         unit_price: float|int
     *     }>
     * }  $data
     */
    public function createSale(array $data, User $user): WarehouseSale
    {
        $warehouseId = (int) $data['warehouse_id'];

        // 1. Authorization check
        if (! $this->accessService->canUserSellFromWarehouse($user, $warehouseId)) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'You are not authorized to make sales from the selected warehouse.',
            ]);
        }

        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        $businessDate = Carbon::parse($data['business_date'])->toDateString();

        $itemsData = $data['items'] ?? [];
        if (empty($itemsData)) {
            throw ValidationException::withMessages([
                'items' => 'At least one sale item is required.',
            ]);
        }

        // Resolve customer type & snapshot
        $rawType = isset($data['customer_type']) && $data['customer_type'] !== '' ? (string) $data['customer_type'] : null;
        $shopId = ! empty($data['shop_id']) ? (int) $data['shop_id'] : null;
        $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;
        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $customerPhone = ! empty($data['customer_phone']) ? trim((string) $data['customer_phone']) : null;

        if ($rawType === WarehouseSale::CUSTOMER_TYPE_SHOP || $shopId !== null) {
            if (! $shopId) {
                throw ValidationException::withMessages([
                    'shop_id' => 'Please select a valid shop.',
                ]);
            }
            $shop = Shop::query()->findOrFail($shopId);
            $customerType = WarehouseSale::CUSTOMER_TYPE_SHOP;
            $customerName = $shop->name;
            $customerPhone = $shop->contact_phone ?: null;
            $customerId = null;
        } elseif ($rawType === WarehouseSale::CUSTOMER_TYPE_WALKING || $rawType === 'walkin') {
            $customerType = WarehouseSale::CUSTOMER_TYPE_WALKING;
            $shopId = null;
            $customerId = null;
            if ($customerName === '') {
                $customerName = 'Walking Customer';
            }
        } elseif ($customerId !== null) {
            $customer = WarehouseCustomer::query()->find($customerId);
            $customerType = WarehouseSale::CUSTOMER_TYPE_WALKING;
            $shopId = null;
            if ($customer) {
                $customerName = $customer->name;
                $customerPhone = $customer->phone ?: $customerPhone;
            } else {
                $customerName = $customerName !== '' ? $customerName : 'Walking Customer';
            }
        } elseif ($rawType === WarehouseSale::CUSTOMER_TYPE_CASH_SALES || ($rawType === null && $customerName === '')) {
            // Default: Cash Sales (anonymous grouped sales)
            $customerType = WarehouseSale::CUSTOMER_TYPE_CASH_SALES;
            $shopId = null;
            $customerId = null;
            $customerName = 'Cash Sales';
            $customerPhone = null;
        } else {
            // Backward compatibility: custom name supplied without explicit customer_type
            $customerType = WarehouseSale::CUSTOMER_TYPE_WALKING;
            $shopId = null;
            $customerId = null;
            $customerName = $customerName !== '' ? $customerName : 'Walking Customer';
        }

        return DB::transaction(function () use (
            $data,
            $user,
            $warehouse,
            $businessDate,
            $customerType,
            $shopId,
            $customerId,
            $customerName,
            $customerPhone
        ): WarehouseSale {
            // 2. Validate Items & Prices with Stock Availability Verification
            $rawItems = $data['items'] ?? [];
            if (empty($rawItems)) {
                throw ValidationException::withMessages([
                    'items' => 'At least one product item is required.',
                ]);
            }

            $validatedItems = [];
            $subtotal = 0.0;

            // 2. Lock inventory batches and validate stock for ALL items before deducting
            foreach ($rawItems as $idx => $rawItem) {
                $productId = (int) ($rawItem['product_id'] ?? 0);
                $qty = (float) ($rawItem['qty'] ?? ($rawItem['entered_qty'] ?? 0));
                $unitPrice = (float) ($rawItem['unit_price'] ?? 0);
                $unit = trim((string) ($rawItem['unit'] ?? 'kg'));
                $gradeStr = trim((string) ($rawItem['grade'] ?? 'A'));
                $grade = ProductGrade::tryFrom($gradeStr) ?? ProductGrade::GradeA;

                if ($productId <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.product_id" => 'Invalid product selected.',
                    ]);
                }

                if ($qty <= 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.qty" => 'Quantity must be greater than zero.',
                    ]);
                }

                if ($unitPrice < 0) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.unit_price" => 'Unit price cannot be negative.',
                    ]);
                }

                $product = Product::query()->find($productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.product_id" => 'Product not found.',
                    ]);
                }

                // Stock check skipped — sales are allowed even when stock is 0 (stock can go negative).

                $lineTotal = round($qty * $unitPrice, 2);
                $subtotal += $lineTotal;

                $validatedItems[] = [
                    'product' => $product,
                    'grade' => $grade,
                    'qty' => $qty,
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = max(0.0, (float) ($data['discount'] ?? 0));
            $totalAmount = max(0.0, round($subtotal - $discount, 2));

            $invoiceNumber = $this->generateInvoiceNumber($businessDate);

            // 3. Create Warehouse Sale Record
            $sale = WarehouseSale::query()->create([
                'invoice_number' => $invoiceNumber,
                'warehouse_id' => $warehouse->id,
                'customer_type' => $customerType,
                'shop_id' => $shopId,
                'customer_id' => $customerId,
                'customer_name_snapshot' => $customerName,
                'customer_phone_snapshot' => $customerPhone,
                'business_date' => $businessDate,
                'sold_by_user_id' => $user->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'balance_amount' => 0.0,
                'status' => WarehouseSale::STATUS_CONFIRMED,
                'notes' => $data['notes'] ?? null,
                'confirmed_at' => now(),
            ]);

            // 4. Create Sale Items and deduct stock
            foreach ($validatedItems as $item) {
                $saleItem = WarehouseSaleItem::query()->create([
                    'warehouse_sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'grade' => $item['grade']->value,
                    'entered_qty' => $item['qty'],
                    'entered_unit' => $item['unit'],
                    'normalized_qty' => $item['qty'],
                    'normalized_unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);

                // Deduct stock via StockLedgerService
                $startMovementId = (int) (StockMovement::query()->max('id') ?? 0);

                $consumed = $this->stockLedgerService->consumeSortedStockForProduct(
                    $item['product']->id,
                    $item['qty'],
                    $user->id,
                    StockMovementType::Sale,
                    "Warehouse Sale Invoice #{$sale->invoice_number} ({$warehouse->name})",
                    null,
                    $warehouse->id,
                    $item['grade']
                );

                // Note: consumed may be 0 if no stock batches exist; negative stock is allowed.

                // Associate the newly created stock movements with this warehouse sale item
                StockMovement::query()
                    ->where('id', '>', $startMovementId)
                    ->where('product_id', $item['product']->id)
                    ->where('type', StockMovementType::Sale->value)
                    ->whereNull('warehouse_sale_item_id')
                    ->update(['warehouse_sale_item_id' => $saleItem->id]);
            }

            // 5. Create Payment Record
            $paymentMethod = strtolower(trim((string) ($data['payment_method'] ?? 'cash')));
            $moneyHolderType = $paymentMethod === 'cash'
                ? strtolower(trim((string) ($data['money_holder_type'] ?? 'user')))
                : 'company';

            if (! in_array($moneyHolderType, ['company', 'user'], true)) {
                $moneyHolderType = 'company';
            }

            $moneyHolderUserId = ($paymentMethod === 'cash' && $moneyHolderType === 'user')
                ? (! empty($data['money_holder_user_id']) ? (int) $data['money_holder_user_id'] : (int) $user->id)
                : null;

            $companyAccountId = ! empty($data['company_account_id']) ? (int) $data['company_account_id'] : null;
            $paymentReference = ! empty($data['payment_reference'])
                ? trim((string) $data['payment_reference'])
                : (! empty($data['reference']) ? trim((string) $data['reference']) : null);

            WarehouseSalePayment::query()->create([
                'warehouse_sale_id' => $sale->id,
                'payment_method' => $paymentMethod,
                'amount' => $totalAmount,
                'money_holder_type' => $moneyHolderType,
                'money_holder_user_id' => $moneyHolderUserId,
                'company_account_id' => $companyAccountId,
                'reference' => $paymentReference,
                'status' => 'completed',
                'received_at' => now(),
            ]);

            return $sale->load(['items.product', 'payments.moneyHolderUser', 'warehouse', 'customer', 'soldBy']);
        });
    }

    /**
     * Cancel a confirmed warehouse sale and restore stock movements.
     */
    public function cancelSale(WarehouseSale $sale, User $user, ?string $reason = null): WarehouseSale
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'sale' => 'This sale is already cancelled.',
            ]);
        }

        return DB::transaction(function () use ($sale, $user, $reason): WarehouseSale {
            $sale->loadMissing(['items.stockMovements', 'warehouse']);

            // 1. Revert each inventory movement
            foreach ($sale->items as $item) {
                $movements = StockMovement::query()
                    ->where('warehouse_sale_item_id', $item->id)
                    ->where('type', StockMovementType::Sale->value)
                    ->get();

                foreach ($movements as $movement) {
                    StockMovement::query()->create([
                        'batch_id' => $movement->batch_id,
                        'product_id' => $movement->product_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'warehouse_sale_item_id' => $item->id,
                        'created_by' => $user->id,
                        'grade' => $movement->grade?->value ?? (string) $movement->grade,
                        'type' => StockMovementType::SaleReversal->value,
                        'quantity' => $movement->quantity,
                        'cost_per_unit' => $movement->cost_per_unit,
                        'notes' => "Reversal of sale invoice #{$sale->invoice_number}: ".($reason ?: 'Sale cancelled'),
                    ]);
                }
            }

            // 2. Mark payments as cancelled
            $sale->payments()->update(['status' => 'cancelled']);

            // 3. Mark sale as cancelled
            $sale->update([
                'status' => WarehouseSale::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);

            return $sale->fresh(['items.product', 'payments', 'warehouse', 'customer', 'soldBy', 'cancelledBy']);
        });
    }
}
