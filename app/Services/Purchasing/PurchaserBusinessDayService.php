<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\BusinessSetting;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseBusinessDayCarryForward;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaserBusinessDayService
{
    private const CUTOFF_SETTING_KEY = 'business_day_cutoff_time';

    private const AUTO_APPROVE_SHOP_ORDERS_KEY = 'auto_approve_shop_orders';

    public const AUTO_APPROVE_MANAGER_NOTE = 'Automatically approved by purchase setting.';

    private ?string $cachedCutoffTime = null;

    private ?bool $cachedAutoApproveShopOrders = null;

    /**
     * Check if the new Purchaser Business Day module is enabled for a given warehouse.
     */
    public function isWarehouseEnabled(int $warehouseId): bool
    {
        $settings = $this->getWarehouseSettings($warehouseId);

        return (bool) ($settings['enabled'] ?? false);
    }

    /**
     * Enable or disable the Purchaser Business Day module for a warehouse.
     */
    public function setWarehouseEnabled(int $warehouseId, bool $enabled = true): void
    {
        $settings = $this->getWarehouseSettings($warehouseId);
        $settings['enabled'] = $enabled;
        $this->updateWarehouseSettings($warehouseId, $settings);
    }

    /**
     * Get all configured Business Day settings for a warehouse.
     *
     * @return array{
     *     enabled: bool,
     *     purchasers_can_close: bool,
     *     purchasers_can_reopen: bool,
     *     reopen_requires_reason: bool,
     *     allow_close_with_pending: bool,
     *     require_digital_verification: bool,
     *     admin_override_reopen: bool
     * }
     */
    public function getWarehouseSettings(int $warehouseId): array
    {
        $settingJson = BusinessSetting::query()
            ->where('key', "purchaser_business_day_config_{$warehouseId}")
            ->value('value');

        $defaults = [
            'enabled' => false,
            'purchasers_can_close' => true,
            'purchasers_can_reopen' => true,
            'reopen_requires_reason' => true,
            'allow_close_with_pending' => true,
            'allow_carry_forward' => true,
            'require_digital_verification' => true,
            'admin_override_reopen' => true,
        ];

        if ($settingJson !== null && $settingJson !== '') {
            $decoded = json_decode((string) $settingJson, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        // Backward compatibility with simple toggle key
        $legacyEnabled = BusinessSetting::query()
            ->where('key', "purchaser_business_day_enabled_{$warehouseId}")
            ->value('value');

        if ($legacyEnabled !== null) {
            $defaults['enabled'] = filter_var($legacyEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        return $defaults;
    }

    /**
     * Update Business Day settings for a warehouse.
     *
     * @param  array<string, mixed>  $settings
     */
    public function updateWarehouseSettings(int $warehouseId, array $settings): void
    {
        $current = $this->getWarehouseSettings($warehouseId);

        $merged = [
            'enabled' => filter_var($settings['enabled'] ?? $current['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'purchasers_can_close' => filter_var($settings['purchasers_can_close'] ?? $current['purchasers_can_close'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'purchasers_can_reopen' => filter_var($settings['purchasers_can_reopen'] ?? $current['purchasers_can_reopen'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'reopen_requires_reason' => true, // strictly required
            'allow_close_with_pending' => filter_var($settings['allow_close_with_pending'] ?? $current['allow_close_with_pending'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'allow_carry_forward' => filter_var($settings['allow_carry_forward'] ?? $current['allow_carry_forward'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'require_digital_verification' => filter_var($settings['require_digital_verification'] ?? $current['require_digital_verification'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'admin_override_reopen' => filter_var($settings['admin_override_reopen'] ?? $current['admin_override_reopen'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        BusinessSetting::query()->updateOrCreate(
            ['key' => "purchaser_business_day_config_{$warehouseId}"],
            ['value' => json_encode($merged)],
        );

        // Keep simple toggle in sync
        BusinessSetting::query()->updateOrCreate(
            ['key' => "purchaser_business_day_enabled_{$warehouseId}"],
            ['value' => $merged['enabled'] ? '1' : '0'],
        );
    }

    /**
     * Get settings for all active warehouses.
     *
     * @return Collection<int, array{warehouse: Warehouse, settings: array<string, mixed>}>
     */
    public function getAllWarehouseSettings(): Collection
    {
        return Warehouse::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Warehouse $wh): array {
                $settings = $this->getWarehouseSettings((int) $wh->id);

                return [
                    'warehouse_id' => (int) $wh->id,
                    'warehouse_name' => $wh->name,
                    'warehouse_code' => $wh->code,
                    'warehouse' => $wh,
                    'settings' => $settings,
                    'enabled' => (bool) ($settings['enabled'] ?? false),
                    'purchasers_can_close' => (bool) ($settings['purchasers_can_close'] ?? true),
                    'purchasers_can_reopen' => (bool) ($settings['purchasers_can_reopen'] ?? true),
                    'reopen_requires_reason' => (bool) ($settings['reopen_requires_reason'] ?? true),
                    'allow_close_with_pending' => (bool) ($settings['allow_close_with_pending'] ?? true),
                    'allow_carry_forward' => (bool) ($settings['allow_carry_forward'] ?? true),
                    'require_digital_verification' => (bool) ($settings['require_digital_verification'] ?? true),
                    'admin_override_reopen' => (bool) ($settings['admin_override_reopen'] ?? true),
                ];
            });
    }

    /**
     * Get the active (open or reopened) Purchase Business Day for a warehouse.
     */
    public function getActiveForWarehouse(int $warehouseId): ?PurchaseBusinessDay
    {
        return PurchaseBusinessDay::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_REOPENED])
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a new business day for a warehouse.
     *
     * Prevents multiple concurrent active days for the same warehouse.
     *
     * @throws ValidationException
     */
    public function open(int $warehouseId, Carbon|string $businessDate, int $userId): PurchaseBusinessDay
    {
        $date = Carbon::parse($businessDate)->toDateString();

        return DB::transaction(function () use ($warehouseId, $date, $userId): PurchaseBusinessDay {
            // Lock and verify no active day exists for this warehouse
            $activeDay = PurchaseBusinessDay::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_REOPENED])
                ->lockForUpdate()
                ->first();

            if ($activeDay) {
                throw ValidationException::withMessages([
                    'warehouse_id' => "An active business day ({$activeDay->business_date->format('d-m-Y')}) is already open for this warehouse. Close it before opening a new one.",
                ]);
            }

            $day = PurchaseBusinessDay::create([
                'business_date' => $date,
                'warehouse_id' => $warehouseId,
                'status' => PurchaseBusinessDay::STATUS_OPEN,
                'opened_by' => $userId,
                'opened_at' => now(),
            ]);

            // Backfill unassigned purchasing records created on this date & warehouse
            GoodsReceived::query()
                ->whereNull('business_day_id')
                ->where('warehouse_id', $warehouseId)
                ->whereDate('received_at', $date)
                ->update(['business_day_id' => $day->id]);

            PurchaseOrder::query()
                ->whereNull('business_day_id')
                ->whereDate('order_date', $date)
                ->where(function ($q) use ($warehouseId): void {
                    $q->where('destination_shop_id', $warehouseId)
                        ->orWhereHas('goodsReceiveds', fn ($gq) => $gq->where('warehouse_id', $warehouseId));
                })
                ->update(['business_day_id' => $day->id]);

            app(DailyInventoryComparisonService::class)->reconcileBusinessDay($day->id, $userId);

            return $day;
        });
    }

    /**
     * Close an active business day.
     *
     * @throws ValidationException
     */
    public function close(PurchaseBusinessDay $day, int $userId, ?string $note = null, string $closeMode = 'carry_forward'): PurchaseBusinessDay
    {
        return DB::transaction(function () use ($day, $userId, $note, $closeMode): PurchaseBusinessDay {
            /** @var PurchaseBusinessDay $lockedDay */
            $lockedDay = PurchaseBusinessDay::query()
                ->whereKey($day->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDay->isClosed()) {
                throw ValidationException::withMessages([
                    'business_day' => 'This business day is already closed.',
                ]);
            }

            $comparisonRows = app(DailyInventoryComparisonService::class)->buildComparisonRows(
                $lockedDay->business_date->toDateString(),
                (int) $lockedDay->warehouse_id
            );

            $pendingSnapshot = $comparisonRows->filter(function (array $r): bool {
                $adv = (float) ($r['advance_qty'] ?? 0);
                $matched = (float) ($r['matched_bill_qty'] ?? 0);

                return max(0.0, $adv - $matched) > 0.0001 || ($r['unit_mismatch'] ?? false);
            })->map(function (array $r): array {
                $adv = (float) ($r['advance_qty'] ?? 0);
                $matched = (float) ($r['matched_bill_qty'] ?? 0);

                return [
                    'product_id' => $r['product_id'],
                    'product_name' => $r['product_name'],
                    'sku' => $r['sku'] ?? '',
                    'advance_qty' => $adv,
                    'matched_qty' => $matched,
                    'pending_qty' => max(0.0, $adv - $matched),
                    'unit' => $r['unit'] ?? 'kg',
                    'unit_mismatch' => (bool) ($r['unit_mismatch'] ?? false),
                ];
            })->values()->toArray();

            $effectiveCloseMode = empty($pendingSnapshot) ? PurchaseBusinessDay::CLOSE_MODE_CLEAN : $closeMode;

            // Create carry forward tasks if close mode is carry_forward
            if ($effectiveCloseMode === PurchaseBusinessDay::CLOSE_MODE_CARRY_FORWARD && ! empty($pendingSnapshot)) {
                foreach ($pendingSnapshot as $item) {
                    $pendingQty = (float) ($item['pending_qty'] ?? 0);
                    if ($pendingQty > 0.0001 || ! empty($item['unit_mismatch'])) {
                        $rawUnit = (string) ($item['unit'] ?? 'kg');
                        $unit = ProductUnit::normalizeUnit($rawUnit);

                        PurchaseBusinessDayCarryForward::updateOrCreate(
                            [
                                'origin_business_day_id' => $lockedDay->id,
                                'product_id' => (int) $item['product_id'],
                                'unit' => $unit,
                            ],
                            [
                                'warehouse_id' => $lockedDay->warehouse_id,
                                'pending_qty_at_close' => $pendingQty,
                                'status' => PurchaseBusinessDayCarryForward::STATUS_OPEN,
                                'created_by' => $userId,
                            ]
                        );
                    }
                }
            }

            $updateData = [
                'status' => PurchaseBusinessDay::STATUS_CLOSED,
                'close_mode' => $effectiveCloseMode,
                'closed_by' => $userId,
                'closed_at' => now(),
                'close_note' => $note,
                'close_pending_snapshot' => $pendingSnapshot,
            ];

            if ($lockedDay->first_close_pending_snapshot === null) {
                $updateData['first_close_pending_snapshot'] = $pendingSnapshot;
            }

            $lockedDay->update($updateData);

            return $lockedDay->fresh();
        });
    }

    /**
     * Recalculate canonical current pending quantity and auto-resolve/reopen carry forward tasks for a Business Day.
     */
    public function syncCarryForwardsForDay(int $businessDayId, ?int $userId = null): void
    {
        $carryForwards = PurchaseBusinessDayCarryForward::query()
            ->where('origin_business_day_id', $businessDayId)
            ->get();

        if ($carryForwards->isEmpty()) {
            return;
        }

        $day = PurchaseBusinessDay::find($businessDayId);
        if (! $day) {
            return;
        }

        $comparisonRows = app(DailyInventoryComparisonService::class)->buildComparisonRows(
            $day->business_date->toDateString(),
            (int) $day->warehouse_id
        );

        foreach ($carryForwards as $carry) {
            $row = $comparisonRows->first(function (array $r) use ($carry): bool {
                if ((int) $r['product_id'] !== (int) $carry->product_id) {
                    return false;
                }
                $rowUnit = ProductUnit::normalizeUnit((string) ($r['unit'] ?? 'kg'));

                return $rowUnit === ProductUnit::normalizeUnit($carry->unit);
            });

            $adv = $row ? (float) ($row['advance_qty'] ?? 0) : 0.0;
            $matched = $row ? (float) ($row['matched_bill_qty'] ?? 0) : 0.0;
            $currentPending = max(0.0, $adv - $matched);

            if ($currentPending <= 0.0001) {
                if ($carry->isOpen()) {
                    $carry->update([
                        'status' => PurchaseBusinessDayCarryForward::STATUS_RESOLVED,
                        'resolved_by' => $userId,
                        'resolved_at' => now(),
                    ]);
                }
            } else {
                if ($carry->isResolved()) {
                    $carry->update([
                        'status' => PurchaseBusinessDayCarryForward::STATUS_OPEN,
                        'resolved_by' => null,
                        'resolved_at' => null,
                    ]);
                }
            }
        }
    }

    /**
     * Reopen a closed business day with an audit reason.
     *
     * @throws ValidationException
     */
    public function reopen(PurchaseBusinessDay $day, int $userId, string $reason): PurchaseBusinessDay
    {
        $trimmedReason = trim($reason);
        if ($trimmedReason === '') {
            throw ValidationException::withMessages([
                'reopen_reason' => 'A valid reason is required to reopen a closed business day.',
            ]);
        }

        return DB::transaction(function () use ($day, $userId, $trimmedReason): PurchaseBusinessDay {
            /** @var PurchaseBusinessDay $lockedDay */
            $lockedDay = PurchaseBusinessDay::query()
                ->whereKey($day->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDay->isClosed()) {
                throw ValidationException::withMessages([
                    'business_day' => 'Only closed business days can be reopened.',
                ]);
            }

            // Verify no other active business day exists for this warehouse
            $existingActive = PurchaseBusinessDay::query()
                ->where('warehouse_id', $lockedDay->warehouse_id)
                ->where('id', '!=', $lockedDay->id)
                ->whereIn('status', [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_REOPENED])
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'warehouse_id' => "Cannot reopen because another active business day ({$existingActive->business_date->format('d-m-Y')}) is currently open for this warehouse.",
                ]);
            }

            $lockedDay->update([
                'status' => PurchaseBusinessDay::STATUS_REOPENED,
                'reopened_by' => $userId,
                'reopened_at' => now(),
                'reopen_reason' => $trimmedReason,
            ]);

            return $lockedDay->fresh();
        });
    }

    /**
     * Create a purchase bill directly against a Business Day with automatic matching.
     *
     * @param  array{
     *     supplier_id: int,
     *     bill_number?: string|null,
     *     received_at?: string|null,
     *     transport_cost?: float|null,
     *     labour_cost?: float|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         received_qty: float,
     *         received_unit?: string|null,
     *         unit_price?: float|null
     *     }>
     * } $payload
     *
     * @throws ValidationException
     */
    public function createBusinessDayBill(PurchaseBusinessDay $day, array $payload, int $userId): GoodsReceived
    {
        $day = $day->fresh() ?? $day;

        $clientSubmissionId = ! empty($payload['client_submission_id']) ? (string) $payload['client_submission_id'] : null;
        if ($clientSubmissionId !== null) {
            $existingGrn = GoodsReceived::query()->where('client_submission_id', $clientSubmissionId)->first();
            if ($existingGrn !== null) {
                return $existingGrn->fresh(['items.product', 'purchaseOrder.supplier', 'businessDay']);
            }
        }

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one product item is required to record a purchase bill.',
            ]);
        }

        $carryRecord = null;
        if ($day->isClosed()) {
            $carryUuid = (string) ($payload['carry_forward_uuid'] ?? '');
            if ($carryUuid === '') {
                throw ValidationException::withMessages([
                    'business_day_id' => "The selected Business Day ({$day->business_date->format('d-m-Y')}) is closed. Creating a bill requires an explicit carry_forward_uuid.",
                ]);
            }

            $carryRecord = PurchaseBusinessDayCarryForward::query()->where('uuid', $carryUuid)->first();
            if (! $carryRecord) {
                throw ValidationException::withMessages([
                    'carry_forward_uuid' => 'The specified carry forward task was not found.',
                ]);
            }

            $this->assertCarryForwardCompletionAllowed($day, $carryRecord, $items);
        } else {
            $this->assertBusinessDayNotClosed($day->id);
        }

        $supplierId = (int) ($payload['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            throw ValidationException::withMessages([
                'supplier_id' => 'A valid supplier/vendor must be selected.',
            ]);
        }

        return DB::transaction(function () use ($day, $payload, $items, $supplierId, $userId, $carryRecord): GoodsReceived {
            $receivedAt = ! empty($payload['received_at']) ? $payload['received_at'] : now()->toDateTimeString();
            $billNumber = ! empty($payload['bill_number'])
                ? trim((string) $payload['bill_number'])
                : 'BILL-'.$day->business_date->format('Ymd').'-'.Str::upper(Str::random(5));

            $transportCost = (float) ($payload['transport_cost'] ?? 0.0);
            $labourCost = (float) ($payload['labour_cost'] ?? 0.0);
            $notes = ! empty($payload['notes']) ? (string) $payload['notes'] : 'Business Day Purchase Bill';

            // 1. Create Purchase Order
            $poNumber = 'PO-BD-'.$day->business_date->format('Ymd').'-'.Str::upper(Str::random(6));
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $supplierId,
                'business_day_id' => $day->id,
                'po_number' => $poNumber,
                'status' => POStatus::Received,
                'order_date' => $day->business_date->toDateString(),
                'created_by' => $userId,
                'notes' => $notes,
                'purchase_grade' => 'A',
            ]);

            $grnItemsData = [];
            $grossAmount = 0.0;

            foreach ($items as $index => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $product = Product::findOrFail($productId);
                $qty = (float) ($item['received_qty'] ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.received_qty" => "Received quantity for {$product->name} must be greater than zero.",
                    ]);
                }

                $rawUnit = (string) ($item['received_unit'] ?? $product->unit ?? 'kg');
                $unit = ProductUnit::normalizeUnit($rawUnit);
                $unitPrice = (float) ($item['unit_price'] ?? 0.0);
                $lineTotal = round($qty * $unitPrice, 2);
                $grossAmount += $lineTotal;

                $poItem = $purchaseOrder->items()->create([
                    'product_id' => $productId,
                    'grade' => 'A',
                    'purchase_unit' => $unit,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $grnItemsData[] = [
                    'product_id' => $productId,
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => $qty,
                    'received_unit' => $unit,
                ];
            }

            $clientSubmissionId = ! empty($payload['client_submission_id']) ? (string) $payload['client_submission_id'] : null;
            $carryForwardUuid = $carryRecord ? $carryRecord->uuid : (! empty($payload['carry_forward_uuid']) ? (string) $payload['carry_forward_uuid'] : null);

            // 2. Record GRN via canonical Action (Updates stock exactly once & auto-matches)
            $goodsReceivedData = new GoodsReceivedData(
                purchaseOrderId: $purchaseOrder->id,
                receivedAt: $receivedAt,
                transportCost: $transportCost,
                labourCost: $labourCost,
                notes: $notes,
                items: $grnItemsData,
                billStatus: 'bill_available',
                billNumber: $billNumber,
                warehouseId: $day->warehouse_id,
                clientSubmissionId: $clientSubmissionId,
                receiptType: 'normal_purchase',
                businessDayId: $day->id,
                carryForwardUuid: $carryForwardUuid,
            );

            /** @var GoodsReceived $grn */
            $grn = app(RecordGoodsReceiptAction::class)->execute($goodsReceivedData, $userId);

            // 3. Create or attach PurchaseInvoice record for finance & vendor reports
            PurchaseInvoice::firstOrCreate(
                [
                    'goods_received_id' => $grn->id,
                    'invoice_number' => $billNumber,
                ],
                [
                    'supplier_id' => $supplierId,
                    'business_day_id' => $day->id,
                    'invoice_date' => $day->business_date->toDateString(),
                    'amount' => $grossAmount,
                    'status' => 'approved',
                    'notes' => $notes,
                ]
            );

            // 4. Sync any carry forwards for this business day
            $this->syncCarryForwardsForDay((int) $day->id, $userId);

            return $grn->fresh(['items.product', 'purchaseOrder.supplier', 'businessDay']);
        });
    }

    /**
     * Update an existing Business Day purchase bill, adjusting inventory and rerunning matching.
     *
     * @param  array{
     *     bill_number?: string|null,
     *     transport_cost?: float|null,
     *     labour_cost?: float|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         id?: int|null,
     *         product_id: int,
     *         received_qty: float,
     *         received_unit?: string|null,
     *         unit_price?: float|null
     *     }>
     * } $payload
     *
     * @throws ValidationException
     */
    public function updateBusinessDayBill(GoodsReceived $grn, array $payload, int $userId): GoodsReceived
    {
        $this->assertBusinessDayNotClosed($grn->business_day_id);

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one product item is required.',
            ]);
        }

        return DB::transaction(function () use ($grn, $payload, $items, $userId): GoodsReceived {
            // 1. Revert previous matches for this bill so matching can cleanly recalculate
            $existingMatches = AdvanceReceiveMatch::query()
                ->where('bill_goods_received_id', $grn->id)
                ->lockForUpdate()
                ->get();

            foreach ($existingMatches as $match) {
                // Restore StockBatch on BILL side
                $billBatch = null;
                if ($match->advance_stock_batch_id || $match->bill_goods_received_item_id) {
                    $billBatch = StockBatch::query()
                        ->where('goods_received_item_id', $match->bill_goods_received_item_id)
                        ->lockForUpdate()
                        ->first();
                }
                if ($billBatch) {
                    $billBatch->total_kg = round((float) $billBatch->total_kg + (float) $match->matched_qty, 3);
                    $billBatch->save();
                }

                $match->delete();
            }

            // 2. Update Items, quantities, units, and StockBatches
            $existingItems = $grn->items()->get()->keyBy('id');
            $grossAmount = 0.0;

            foreach ($items as $index => $itemInput) {
                $itemId = isset($itemInput['id']) ? (int) $itemInput['id'] : null;
                $productId = (int) ($itemInput['product_id'] ?? 0);
                $product = Product::findOrFail($productId);
                $newQty = round((float) ($itemInput['received_qty'] ?? 0), 3);
                if ($newQty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.received_qty" => "Quantity for {$product->name} must be greater than zero.",
                    ]);
                }

                $rawUnit = (string) ($itemInput['received_unit'] ?? $product->unit ?? 'kg');
                $unit = ProductUnit::normalizeUnit($rawUnit);
                $unitPrice = (float) ($itemInput['unit_price'] ?? 0.0);
                $lineTotal = round($newQty * $unitPrice, 2);
                $grossAmount += $lineTotal;

                /** @var GoodsReceivedItem|null $grnItem */
                $grnItem = $itemId && $existingItems->has($itemId)
                    ? $existingItems->get($itemId)
                    : ($existingItems->values()->get($index) ?? null);

                if ($grnItem) {
                    $grnItem->update([
                        'product_id' => $productId,
                        'received_qty' => $newQty,
                        'received_unit' => $unit,
                    ]);

                    if ($grnItem->purchaseOrderItem) {
                        $grnItem->purchaseOrderItem->update([
                            'product_id' => $productId,
                            'quantity' => $newQty,
                            'purchase_unit' => $unit,
                            'unit_price' => $unitPrice,
                            'line_total' => $lineTotal,
                        ]);
                    }

                    // Update corresponding StockBatch
                    $batch = StockBatch::query()
                        ->where('goods_received_id', $grn->id)
                        ->where('goods_received_item_id', $grnItem->id)
                        ->first();

                    if ($batch) {
                        $batch->update([
                            'product_id' => $productId,
                            'total_kg' => $newQty,
                        ]);
                    }
                }
            }

            // 3. Update GRN metadata
            $billNumber = ! empty($payload['bill_number']) ? trim((string) $payload['bill_number']) : $grn->bill_number;
            $grn->update([
                'bill_number' => $billNumber,
                'transport_cost' => array_key_exists('transport_cost', $payload) && $payload['transport_cost'] !== null
                    ? (float) $payload['transport_cost']
                    : (float) $grn->transport_cost,
                'labour_cost' => array_key_exists('labour_cost', $payload) && $payload['labour_cost'] !== null
                    ? (float) $payload['labour_cost']
                    : (float) $grn->labour_cost,
                'notes' => $payload['notes'] ?? $grn->notes,
                'updated_by' => $userId,
            ]);

            // 4. Update Invoice
            $invoice = PurchaseInvoice::where('goods_received_id', $grn->id)->first();
            if ($invoice) {
                $invoice->update([
                    'invoice_number' => $billNumber,
                    'amount' => $grossAmount,
                ]);
            }

            // 5. Re-run auto match
            app(DailyInventoryComparisonService::class)->autoMatchForGrn($grn, $userId);

            // 6. Sync any carry forwards for this business day
            if ($grn->business_day_id) {
                $this->syncCarryForwardsForDay((int) $grn->business_day_id, $userId);
            }

            return $grn->fresh(['items.product', 'purchaseOrder.supplier', 'businessDay']);
        });
    }

    /**
     * Assert that a business day is NOT closed for normal mutations.
     *
     * @throws ValidationException
     */
    public function assertBusinessDayNotClosed(?int $businessDayId): void
    {
        if ($businessDayId === null) {
            return;
        }

        $day = PurchaseBusinessDay::find($businessDayId);
        if ($day && $day->isClosed()) {
            throw ValidationException::withMessages([
                'business_day_id' => "The selected Business Day ({$day->business_date->format('d-m-Y')}) is closed. Reopen the business day to perform mutations.",
            ]);
        }
    }

    /**
     * Validate carry-forward completion bill creation on a closed business day.
     *
     * @param  array<int, array{
     *     product_id: int,
     *     received_qty: float,
     *     received_unit?: string|null,
     *     unit_price?: float|null
     * }>  $items
     *
     * @throws ValidationException
     */
    public function assertCarryForwardCompletionAllowed(
        PurchaseBusinessDay $day,
        PurchaseBusinessDayCarryForward $carry,
        array $items
    ): void {
        if (! $day->isClosed()) {
            throw ValidationException::withMessages([
                'business_day' => 'Carry forward completion assertion is only applicable to closed business days.',
            ]);
        }

        if ($day->close_mode !== PurchaseBusinessDay::CLOSE_MODE_CARRY_FORWARD) {
            throw ValidationException::withMessages([
                'business_day' => 'This business day was not closed with carry-forward mode.',
            ]);
        }

        if (! $carry->isOpen()) {
            throw ValidationException::withMessages([
                'carry_forward' => 'Carry forward task is already resolved.',
            ]);
        }

        if ((int) $carry->origin_business_day_id !== (int) $day->id) {
            throw ValidationException::withMessages([
                'carry_forward' => 'Carry forward task does not belong to the target business day.',
            ]);
        }

        if ((int) $carry->warehouse_id !== (int) $day->warehouse_id) {
            throw ValidationException::withMessages([
                'carry_forward' => 'Carry forward task does not belong to the target warehouse.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one item is required for carry forward completion.',
            ]);
        }

        $comparisonRows = app(DailyInventoryComparisonService::class)->buildComparisonRows(
            $day->business_date->toDateString(),
            (int) $day->warehouse_id
        );

        $primaryCarryMatched = false;

        foreach ($items as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = Product::find($productId);
            $productName = $product ? $product->name : "Product #{$productId}";

            $rawUnit = (string) ($item['received_unit'] ?? $product?->unit ?? 'kg');
            $itemUnit = ProductUnit::normalizeUnit($rawUnit);

            if ($productId === (int) $carry->product_id && $itemUnit === ProductUnit::normalizeUnit($carry->unit)) {
                $primaryCarryMatched = true;
            }

            // Find matching open carry forward record for this business day, warehouse, product, and unit
            $itemCarry = PurchaseBusinessDayCarryForward::query()
                ->where('origin_business_day_id', $day->id)
                ->where('warehouse_id', $day->warehouse_id)
                ->where('product_id', $productId)
                ->where('unit', $itemUnit)
                ->where('status', PurchaseBusinessDayCarryForward::STATUS_OPEN)
                ->first();

            if (! $itemCarry) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Product {$productName} does not have an active open carry-forward task for this closed business day.",
                ]);
            }

            // Check canonical current pending for this product & unit on this day
            $row = $comparisonRows->first(function (array $r) use ($productId, $itemUnit): bool {
                if ((int) $r['product_id'] !== $productId) {
                    return false;
                }
                $rowUnit = ProductUnit::normalizeUnit((string) ($r['unit'] ?? 'kg'));

                return $rowUnit === $itemUnit;
            });

            $adv = $row ? (float) ($row['advance_qty'] ?? 0) : 0.0;
            $matched = $row ? (float) ($row['matched_bill_qty'] ?? 0) : 0.0;
            $currentPending = max(0.0, $adv - $matched);

            if ($currentPending <= 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "Pending quantity for {$productName} is zero or already resolved.",
                ]);
            }
        }

        if (! $primaryCarryMatched) {
            throw ValidationException::withMessages([
                'carry_forward_uuid' => 'The selected carry forward task does not match any of the items in the purchase bill.',
            ]);
        }
    }

    /**
     * Resolve the appropriate Business Day for a new or existing record.
     */
    public function resolveBusinessDayForRecord(mixed $record, ?int $warehouseId = null): ?PurchaseBusinessDay
    {
        if (is_object($record) && isset($record->business_day_id) && $record->business_day_id !== null) {
            return PurchaseBusinessDay::find($record->business_day_id);
        }

        if ($warehouseId !== null && $this->isWarehouseEnabled($warehouseId)) {
            return $this->getActiveForWarehouse($warehouseId);
        }

        return null;
    }

    /**
     * Return the effective business date string (Y-m-d) for a record.
     */
    public function effectiveBusinessDate(mixed $record): string
    {
        if (is_object($record)) {
            if (! empty($record->business_day_id)) {
                $day = PurchaseBusinessDay::find($record->business_day_id);
                if ($day) {
                    return $day->business_date->toDateString();
                }
            }

            if (! empty($record->business_date)) {
                return Carbon::parse($record->business_date)->toDateString();
            }

            if (! empty($record->order_date)) {
                return Carbon::parse($record->order_date)->toDateString();
            }

            if (! empty($record->received_at)) {
                return Carbon::parse($record->received_at)->toDateString();
            }
        }

        return $this->operationalDate()->toDateString();
    }

    /**
     * Reusable matching compatibility check between an Advance and a Bill.
     *
     * Rules:
     * 1. If both records have business_day_id: Must match the exact same business_day_id.
     * 2. If one has business_day_id and the other does not: Strictly reject matching.
     * 3. If both business_day_id are NULL (Legacy): Fallback to same calendar date of received_at.
     */
    public function areEligibleForMatch(
        GoodsReceived|GoodsReceivedItem|array $advance,
        GoodsReceived|GoodsReceivedItem|array $bill
    ): bool {
        $advDayId = $this->extractBusinessDayId($advance);
        $billDayId = $this->extractBusinessDayId($bill);

        // Rule 1: Both have business_day_id -> Must be same business_day_id
        if ($advDayId !== null && $billDayId !== null) {
            return (int) $advDayId === (int) $billDayId;
        }

        // Rule 2: One has business_day_id and other does not -> Reject cross-matching
        if ($advDayId !== null || $billDayId !== null) {
            return false;
        }

        // Rule 3: Both NULL -> Fallback to same received_at calendar date
        $advDate = $this->extractReceivedDateString($advance);
        $billDate = $this->extractReceivedDateString($bill);

        return ! empty($advDate) && $advDate === $billDate;
    }

    private function extractBusinessDayId(mixed $item): ?int
    {
        if ($item instanceof GoodsReceivedItem) {
            return $item->goodsReceived?->business_day_id !== null
                ? (int) $item->goodsReceived->business_day_id
                : null;
        }

        if ($item instanceof GoodsReceived) {
            return $item->business_day_id !== null ? (int) $item->business_day_id : null;
        }

        if (is_array($item)) {
            return isset($item['business_day_id']) && $item['business_day_id'] !== null
                ? (int) $item['business_day_id']
                : null;
        }

        return null;
    }

    private function extractReceivedDateString(mixed $item): ?string
    {
        if ($item instanceof GoodsReceivedItem) {
            $grn = $item->goodsReceived;
            if ($grn && $grn->received_at) {
                return Carbon::parse($grn->received_at)->toDateString();
            }
        }

        if ($item instanceof GoodsReceived && $item->received_at) {
            return Carbon::parse($item->received_at)->toDateString();
        }

        if (is_array($item) && ! empty($item['received_at'])) {
            return Carbon::parse($item['received_at'])->toDateString();
        }

        return null;
    }

    // ── LEGACY CUTOFF TIME & OPERATIONAL DATE BEHAVIOR ──────────────────────

    public function operationalDate(?Carbon $moment = null): Carbon
    {
        $moment ??= now();

        $businessDate = $moment->copy()->startOfDay();

        if ($this->hasRolledOver($moment)) {
            return $businessDate->addDay();
        }

        return $businessDate;
    }

    public function currentCalendarDate(?Carbon $moment = null): Carbon
    {
        return ($moment ??= now())->copy()->startOfDay();
    }

    public function hasRolledOver(?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $moment->gte($this->rolloverStartsAt($moment));
    }

    public function isAdminUserAccess(): bool
    {
        if (! request() || ! request()->hasSession()) {
            return false;
        }

        return request()->session()->has('admin_impersonator_id')
            || (request()->user() && request()->user()->hasRole('admin'));
    }

    public function maxSelectableDate(?Carbon $moment = null): Carbon
    {
        if ($this->isAdminUserAccess()) {
            return now()->addYears(10);
        }

        return $this->operationalDate($moment);
    }

    public function isSelectableDate(Carbon|string $date, ?Carbon $moment = null): bool
    {
        if ($this->isAdminUserAccess()) {
            return true;
        }

        return Carbon::parse($date)->startOfDay()->lte($this->maxSelectableDate($moment));
    }

    public function warningStartsAt(Carbon|string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->setTime(16, 0);
    }

    public function rolloverStartsAt(Carbon|string $date): Carbon
    {
        [$hour, $minute, $second] = $this->cutoffTimeParts();

        return Carbon::parse($date)->startOfDay()->setTime($hour, $minute, $second);
    }

    public function isWarningWindowOpen(Carbon|string $date, ?Carbon $moment = null): bool
    {
        $moment ??= now();

        return $moment->gte($this->warningStartsAt($date));
    }

    public function cutoffTime(): string
    {
        if ($this->cachedCutoffTime !== null) {
            return $this->cachedCutoffTime;
        }

        return $this->cachedCutoffTime = BusinessSetting::query()
            ->where('key', self::CUTOFF_SETTING_KEY)
            ->value('value')
            ?? (string) config('business-day.cutoff_time', '21:30:00');
    }

    public function cutoffInputValue(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizedCutoffTime())
            ->format('H:i');
    }

    public function cutoffLabel(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizedCutoffTime())
            ->format('g:i A');
    }

    public function updateCutoffTime(string $time): void
    {
        $normalizedTime = strlen($time) === 5 ? "{$time}:00" : $time;

        BusinessSetting::query()->updateOrCreate(
            ['key' => self::CUTOFF_SETTING_KEY],
            ['value' => $normalizedTime],
        );

        $this->cachedCutoffTime = $normalizedTime;
    }

    public function autoApproveShopOrders(): bool
    {
        if ($this->cachedAutoApproveShopOrders !== null) {
            return $this->cachedAutoApproveShopOrders;
        }

        return $this->cachedAutoApproveShopOrders = filter_var(
            BusinessSetting::query()
                ->where('key', self::AUTO_APPROVE_SHOP_ORDERS_KEY)
                ->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function updateAutoApproveShopOrders(bool $enabled): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => self::AUTO_APPROVE_SHOP_ORDERS_KEY],
            ['value' => $enabled ? '1' : '0'],
        );

        $this->cachedAutoApproveShopOrders = $enabled;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function cutoffTimeParts(): array
    {
        return array_map(
            static fn (string $segment): int => (int) $segment,
            explode(':', $this->normalizedCutoffTime())
        );
    }

    private function normalizedCutoffTime(): string
    {
        $cutoffTime = $this->cutoffTime();

        return strlen($cutoffTime) === 5 ? "{$cutoffTime}:00" : $cutoffTime;
    }

    /**
     * Build an exhaustive, chronological activity audit timeline for a Business Day.
     *
     * @return Collection<int, array{
     *     timestamp: Carbon,
     *     type: string,
     *     title: string,
     *     user: string,
     *     description: string,
     *     icon: string,
     *     badge_color: string,
     *     metadata?: array<string, mixed>
     * }>
     */
    public function buildAuditTimeline(PurchaseBusinessDay $day): Collection
    {
        $events = collect();

        // 1. Day Opened
        if ($day->opened_at) {
            $events->push([
                'timestamp' => $day->opened_at,
                'type' => 'opened',
                'title' => 'Business Day Opened',
                'user' => $day->openedBy?->name ?? 'System',
                'description' => "Business Day for {$day->warehouse?->name} on {$day->business_date->format('d M Y')} was opened.",
                'icon' => 'calendar',
                'badge_color' => 'emerald',
            ]);
        }

        // 2. Goods Receipts (Advance and Purchase Bills)
        $goodsReceipts = GoodsReceived::query()
            ->with(['items.product', 'receivedBy', 'purchaseOrder.supplier'])
            ->where('business_day_id', $day->id)
            ->get();

        foreach ($goodsReceipts as $grn) {
            $isAdv = $grn->receipt_type === 'warehouse_advance';
            $itemsSummary = $grn->items->map(fn ($i) => "{$i->product?->name} ({$i->received_qty} {$i->received_unit})")->join(', ');
            $supplierName = $grn->purchaseOrder?->supplier?->name ?? 'Direct Supplier';

            $events->push([
                'timestamp' => $grn->received_at ?? $grn->created_at,
                'type' => $isAdv ? 'advance_received' : 'bill_added',
                'title' => $isAdv ? "Advance Received: {$grn->grn_number}" : 'Purchase Bill Recorded: '.($grn->bill_number ?? $grn->grn_number),
                'user' => $grn->receivedBy?->name ?? 'Purchaser',
                'description' => $isAdv
                    ? "Advance receipt {$grn->grn_number} created with {$grn->items->count()} items: {$itemsSummary}."
                    : "Purchase Bill recorded from {$supplierName} with {$grn->items->count()} items: {$itemsSummary}.",
                'icon' => $isAdv ? 'truck' : 'file-text',
                'badge_color' => $isAdv ? 'blue' : 'indigo',
                'metadata' => [
                    'grn_id' => $grn->id,
                    'grn_number' => $grn->grn_number,
                    'bill_number' => $grn->bill_number,
                    'items_count' => $grn->items->count(),
                ],
            ]);
        }

        // 3. Auto-Matches
        $matches = AdvanceReceiveMatch::query()
            ->with(['advanceGoodsReceived', 'billGoodsReceived', 'product', 'confirmedBy'])
            ->where('business_day_id', $day->id)
            ->get();

        foreach ($matches as $m) {
            $events->push([
                'timestamp' => $m->confirmed_at ?? $m->created_at,
                'type' => 'auto_match',
                'title' => "Auto-Match: {$m->product?->name}",
                'user' => $m->confirmedBy?->name ?? 'System Matcher',
                'description' => "Matched {$m->matched_qty} {$m->matched_unit} of {$m->product?->name} against Advance {$m->advanceGoodsReceived?->grn_number} and Bill ".($m->billGoodsReceived?->bill_number ?? $m->billGoodsReceived?->grn_number).'.',
                'icon' => 'check-circle-2',
                'badge_color' => 'teal',
            ]);
        }

        // 4. Day Closed
        if ($day->closed_at) {
            $desc = "Business Day verified and closed by {$day->closedBy?->name}.";
            if ($day->close_note) {
                $desc .= " Note: {$day->close_note}";
            }
            $events->push([
                'timestamp' => $day->closed_at,
                'type' => 'closed',
                'title' => 'Business Day Closed',
                'user' => $day->closedBy?->name ?? 'System',
                'description' => $desc,
                'icon' => 'lock',
                'badge_color' => 'slate',
                'metadata' => [
                    'close_note' => $day->close_note,
                    'pending_snapshot_count' => count($day->close_pending_snapshot ?? []),
                ],
            ]);
        }

        // 5. Day Reopened
        if ($day->reopened_at) {
            $events->push([
                'timestamp' => $day->reopened_at,
                'type' => 'reopened',
                'title' => 'Business Day Reopened',
                'user' => $day->reopenedBy?->name ?? 'Admin',
                'description' => "Business Day reopened. Reason: {$day->reopen_reason}",
                'icon' => 'unlock',
                'badge_color' => 'amber',
                'metadata' => [
                    'reopen_reason' => $day->reopen_reason,
                ],
            ]);
        }

        return $events->sortByDesc('timestamp')->values();
    }

    /**
     * Get monthly metrics and aggregation for Admin Oversight.
     *
     * @return array<string, mixed>
     */
    public function getMonthlyOversightSummary(string $month, ?int $warehouseId = null): array
    {
        $monthCarbon = Carbon::createFromFormat('Y-m', $month) ?: now();
        $start = $monthCarbon->copy()->startOfMonth()->toDateString();
        $end = $monthCarbon->copy()->endOfMonth()->toDateString();

        $query = PurchaseBusinessDay::query()
            ->whereBetween('business_date', [$start, $end])
            ->when($warehouseId !== null && $warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $days = $query->get();

        $openCount = $days->where('status', PurchaseBusinessDay::STATUS_OPEN)->count();
        $closedCount = $days->where('status', PurchaseBusinessDay::STATUS_CLOSED)->count();
        $reopenedCount = $days->where('status', PurchaseBusinessDay::STATUS_REOPENED)->count();
        $totalDays = $days->count();

        // Calculate comparison rows for all days in month
        $comparisonService = app(DailyInventoryComparisonService::class);
        $totalBills = 0;
        $totalAdvance = 0;
        $totalProducts = 0;
        $pendingProductsCount = 0;
        $unitIssuesCount = 0;
        $closedWithPendingCount = 0;

        foreach ($days as $day) {
            $rows = $comparisonService->buildComparisonRows($day->business_date->toDateString(), (int) $day->warehouse_id);
            $summary = $comparisonService->calculateSummary($rows);

            $totalProducts += $rows->count();
            $unitIssuesCount += (int) ($summary['unit_fix_count'] ?? 0);

            $pendingCount = $rows->filter(function (array $r): bool {
                $adv = (float) ($r['advance_qty'] ?? 0);
                $matched = (float) ($r['matched_bill_qty'] ?? 0);

                return max(0.0, $adv - $matched) > 0.0001 || ($r['unit_mismatch'] ?? false);
            })->count();

            $pendingProductsCount += $pendingCount;

            if ($day->isClosed() && ! empty($day->first_close_pending_snapshot)) {
                $closedWithPendingCount++;
            }
        }

        // Count bills and advances
        $dayIds = $days->pluck('id')->all();
        if (! empty($dayIds)) {
            $totalBills = GoodsReceived::query()
                ->whereIn('business_day_id', $dayIds)
                ->where(fn ($q) => $q->where('receipt_type', '!=', 'warehouse_advance')->orWhereNull('receipt_type'))
                ->where('status', '!=', 'cancelled')
                ->count();

            $totalAdvance = GoodsReceived::query()
                ->whereIn('business_day_id', $dayIds)
                ->where('receipt_type', 'warehouse_advance')
                ->where('status', '!=', 'cancelled')
                ->count();
        }

        return [
            'month' => $month,
            'total_days' => $totalDays,
            'open_count' => $openCount,
            'closed_count' => $closedCount,
            'reopened_count' => $reopenedCount,
            'total_bills' => $totalBills,
            'total_advance' => $totalAdvance,
            'total_products' => $totalProducts,
            'pending_products_count' => $pendingProductsCount,
            'unit_issues_count' => $unitIssuesCount,
            'closed_with_pending_count' => $closedWithPendingCount,
        ];
    }

    public function resetCache(): void
    {
        $this->cachedCutoffTime = null;
        $this->cachedAutoApproveShopOrders = null;
    }
}
