<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Purchasing\ShopPurchaserDailyVerificationStatus;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\Purchasing\ShopPurchaserDailyVerification;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ShopPurchaserDailyVerificationService
{
    /**
     * Get or initialize the purchaser daily verification record for a given scope.
     */
    public function getOrCreateVerification(Shop $shop, string $businessDate, User|int $purchaser): ShopPurchaserDailyVerification
    {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : $purchaser;
        $date = Carbon::parse($businessDate)->toDateString();

        return ShopPurchaserDailyVerification::query()->firstOrCreate(
            [
                'shop_id' => (int) $shop->id,
                'business_date' => $date,
                'purchaser_user_id' => $purchaserId,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'status' => ShopPurchaserDailyVerificationStatus::Open,
            ]
        );
    }

    /**
     * Automatically evaluate all purchase bills for the purchaser scope.
     *
     * @return array{
     *     verification: ShopPurchaserDailyVerification,
     *     total_bills: int,
     *     complete_bills_count: int,
     *     incomplete_bills_count: int,
     *     carried_forward_count: int,
     *     cash_bills_count: int,
     *     credit_bills_count: int,
     *     cash_total: float,
     *     credit_total: float,
     *     total_amount: float,
     *     all_clear: bool,
     *     complete_bills: Collection<int, PurchaseInvoice>,
     *     incomplete_bills: Collection<int, array{invoice: PurchaseInvoice, failures: array<int, string>}>,
     *     carried_forward_bills: Collection<int, PurchaseInvoice>,
     *     can_verify: bool
     * }
     */
    public function evaluateBills(Shop $shop, string $businessDate, User|int $purchaser): array
    {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : $purchaser;
        $date = Carbon::parse($businessDate)->toDateString();
        $verification = $this->getOrCreateVerification($shop, $date, $purchaserId);

        $invoices = PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart.items.product', 'goodsReceived.purchaseOrder.items', 'shopVendorPayable'])
            ->where(function ($q) use ($shop): void {
                $q->where('shop_id', $shop->id)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('destination_shop_id', $shop->id));
            })
            ->where(function ($q) use ($purchaserId): void {
                $q->where('purchaser_submitted_by', $purchaserId)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('user_id', $purchaserId));
            })
            ->where(function ($q) use ($date): void {
                $q->whereHas('purchaserCart', fn ($cq) => $cq->whereDate('business_date', $date))
                    ->orWhere(function ($sq) use ($date): void {
                        $sq->whereNull('purchaser_cart_id')
                            ->where(function ($dsq) use ($date): void {
                                $dsq->whereDate('original_business_date', $date)
                                    ->orWhere(function ($cdsq) use ($date): void {
                                        $cdsq->whereNull('original_business_date')
                                            ->whereDate('created_at', $date);
                                    });
                            });
                    });
            })
            ->notCancelled()
            ->orderBy('id')
            ->get();

        $completeBills = collect();
        $incompleteBills = collect();
        $carriedForwardBills = collect();

        $cashTotal = 0.0;
        $creditTotal = 0.0;
        $cashBillsCount = 0;
        $creditBillsCount = 0;

        foreach ($invoices as $invoice) {
            $isCash = strcasecmp((string) $invoice->payment_method, 'Cash') === 0;
            $amount = (float) $invoice->amount;

            if ($isCash) {
                $cashBillsCount++;
                $cashTotal += $amount;
            } else {
                $creditBillsCount++;
                $creditTotal += $amount;
            }

            if ($invoice->is_carried_forward) {
                $carriedForwardBills->push($invoice);

                continue;
            }

            $failures = $this->validateInvoice($invoice, (int) $shop->id, $date);

            if (empty($failures)) {
                $completeBills->push($invoice);
            } else {
                $incompleteBills->push([
                    'invoice' => $invoice,
                    'failures' => $failures,
                ]);
            }
        }

        $totalBills = $invoices->count();
        $completeCount = $completeBills->count();
        $incompleteCount = $incompleteBills->count();
        $carriedCount = $carriedForwardBills->count();
        $totalAmount = round($cashTotal + $creditTotal, 2);

        $allClear = ($incompleteCount === 0);
        $canVerify = $allClear && $verification->canBeUserVerified();

        // Update status to 'ready' if open/reopened and all clear
        if ($allClear && in_array($verification->status, [ShopPurchaserDailyVerificationStatus::Open, ShopPurchaserDailyVerificationStatus::Reopened], true)) {
            $verification->update(['status' => ShopPurchaserDailyVerificationStatus::Ready]);
        } elseif (! $allClear && $verification->status === ShopPurchaserDailyVerificationStatus::Ready) {
            $verification->update(['status' => ShopPurchaserDailyVerificationStatus::Open]);
        }

        return [
            'verification' => $verification->fresh(),
            'total_bills' => $totalBills,
            'complete_bills_count' => $completeCount,
            'incomplete_bills_count' => $incompleteCount,
            'carried_forward_count' => $carriedCount,
            'cash_bills_count' => $cashBillsCount,
            'credit_bills_count' => $creditBillsCount,
            'cash_total' => round($cashTotal, 2),
            'credit_total' => round($creditTotal, 2),
            'total_amount' => $totalAmount,
            'all_clear' => $allClear,
            'complete_bills' => $completeBills,
            'incomplete_bills' => $incompleteBills,
            'carried_forward_bills' => $carriedForwardBills,
            'can_verify' => $canVerify,
        ];
    }

    /**
     * Perform strict automated validation on an individual purchase invoice.
     *
     * @return array<int, string>
     */
    public function validateInvoice(PurchaseInvoice $invoice, int $shopId, string $businessDate): array
    {
        $failures = [];

        // 1. Vendor
        if (! $invoice->supplier_id || ! $invoice->supplier) {
            $failures[] = 'Missing vendor assignment.';
        }

        // 2. Shop scope
        $invoiceShopId = (int) ($invoice->shop_id ?: $invoice->purchaserCart?->destination_shop_id);
        if ($invoiceShopId !== $shopId) {
            $failures[] = "Shop mismatch: bill belongs to shop #{$invoiceShopId}, expected #{$shopId}.";
        }

        // 3. Business date
        $billBusinessDate = $invoice->purchaserCart?->business_date?->toDateString()
            ?: ($invoice->original_business_date ? Carbon::parse($invoice->original_business_date)->toDateString() : $invoice->created_at?->toDateString());

        if ($billBusinessDate && $billBusinessDate !== $businessDate) {
            $failures[] = "Business date mismatch: bill date ({$billBusinessDate}) does not match ({$businessDate}).";
        }

        // 4. Payment method
        $pm = ucfirst(strtolower(trim((string) $invoice->payment_method)));
        if (! in_array($pm, ['Cash', 'Credit'], true)) {
            $failures[] = "Invalid payment method: '{$invoice->payment_method}'. Must be Cash or Credit.";
        }

        // 5. Items validation
        $items = $invoice->purchaserCart?->items;
        if (! $items || $items->isEmpty()) {
            $items = $invoice->goodsReceived?->items;
        }

        if (! $items || $items->isEmpty()) {
            $failures[] = 'Bill contains no product line items.';
        } else {
            $lineSum = 0.0;
            foreach ($items as $idx => $item) {
                $lineNum = $idx + 1;
                $qty = (float) ($item->quantity ?? $item->received_qty ?? 0);
                $unitPrice = (float) ($item->unit_price ?? $item->purchaseOrderItem?->unit_price ?? 0);
                $unit = trim((string) ($item->unit ?? $item->purchase_unit ?? $item->received_unit ?? $item->product?->unit ?? ''));

                if (! $item->product_id) {
                    $failures[] = "Item #{$lineNum}: Missing product selection.";
                }
                if ($qty <= 0) {
                    $failures[] = "Item #{$lineNum}: Quantity must be greater than zero.";
                }
                if ($unit === '') {
                    $failures[] = "Item #{$lineNum}: Measurement unit is missing.";
                }
                if ($unitPrice < 0) {
                    $failures[] = "Item #{$lineNum}: Unit price cannot be negative.";
                }

                $lineSum += round($qty * $unitPrice, 2);
            }

            $discount = (float) ($invoice->discount_amount ?? 0);
            $expectedNet = max(0.0, round($lineSum - $discount, 2));
            $actualAmount = (float) $invoice->amount;

            if (abs($actualAmount - $lineSum) > 0.05 && abs($actualAmount - $expectedNet) > 0.05) {
                $failures[] = "Amount mismatch: items total (₹{$lineSum}) does not match bill amount (₹{$actualAmount}).";
            }
        }

        return $failures;
    }

    /**
     * Explicitly carry forward an incomplete purchase bill with reason and audit.
     */
    public function carryForwardBill(PurchaseInvoice $invoice, User $actor, string $reason): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): PurchaseInvoice {
            /** @var PurchaseInvoice $lockedInvoice */
            $lockedInvoice = PurchaseInvoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertScopeNotFinalizedForInvoice($lockedInvoice);

            $trimmedReason = trim($reason);
            if ($trimmedReason === '') {
                throw new RuntimeException('A valid carry-forward reason is required.');
            }

            $date = $lockedInvoice->purchaserCart?->business_date?->toDateString()
                ?: $lockedInvoice->original_business_date?->toDateString()
                ?: $lockedInvoice->created_at->toDateString();
            $originalDate = $lockedInvoice->original_business_date ?: $date;

            $lockedInvoice->update([
                'is_carried_forward' => true,
                'carry_forward_reason' => $trimmedReason,
                'carried_forward_by' => $actor->id,
                'carried_forward_at' => now(),
                'original_business_date' => $originalDate,
            ]);

            activity('shop_purchaser_verification')
                ->performedOn($lockedInvoice)
                ->causedBy($actor)
                ->event('bill_carried_forward')
                ->withProperties([
                    'invoice_id' => $lockedInvoice->id,
                    'invoice_number' => $lockedInvoice->invoice_number,
                    'shop_id' => $lockedInvoice->shop_id,
                    'reason' => $trimmedReason,
                    'carried_forward_by' => $actor->id,
                    'carried_forward_at' => now()->toIso8601String(),
                ])
                ->log("Purchase bill {$lockedInvoice->invoice_number} marked as carried forward: {$trimmedReason}");

            return $lockedInvoice->fresh();
        });
    }

    /**
     * User Verify step by the purchaser.
     */
    public function verifyMyDay(Shop $shop, string $businessDate, User $purchaser): ShopPurchaserDailyVerification
    {
        return DB::transaction(function () use ($shop, $businessDate, $purchaser): ShopPurchaserDailyVerification {
            $purchaserId = (int) $purchaser->id;
            $date = Carbon::parse($businessDate)->toDateString();

            // Lock or create verification row
            $verification = ShopPurchaserDailyVerification::query()
                ->where('shop_id', (int) $shop->id)
                ->where('business_date', $date)
                ->where('purchaser_user_id', $purchaserId)
                ->lockForUpdate()
                ->first();

            if (! $verification) {
                $verification = $this->getOrCreateVerification($shop, $date, $purchaserId);
                $verification = ShopPurchaserDailyVerification::query()
                    ->whereKey($verification->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            // State transition checks
            if ($verification->status === ShopPurchaserDailyVerificationStatus::UserVerified) {
                throw new RuntimeException('Purchasing day has already been verified by purchaser.');
            }

            if ($verification->status === ShopPurchaserDailyVerificationStatus::SecondVerified) {
                throw new RuntimeException('Purchasing day is already second-verified. Cannot re-verify directly.');
            }

            if ($verification->status === ShopPurchaserDailyVerificationStatus::Finalized) {
                throw new RuntimeException('Purchasing day is finalized. It must be reopened by an admin before re-verifying.');
            }

            if (! $verification->canBeUserVerified()) {
                throw new RuntimeException("Cannot verify day in '{$verification->status->label()}' status.");
            }

            $evaluation = $this->evaluateBills($shop, $businessDate, $purchaser);

            if ($evaluation['incomplete_bills_count'] > 0) {
                $errorDetails = [];
                foreach ($evaluation['incomplete_bills'] as $item) {
                    /** @var PurchaseInvoice $inv */
                    $inv = $item['invoice'];
                    $errorDetails[] = "Bill {$inv->invoice_number}: ".implode('; ', $item['failures']);
                }
                throw new RuntimeException(
                    "Cannot verify day: {$evaluation['incomplete_bills_count']} bill(s) have incomplete data. Please complete or carry forward them.\n".implode("\n", $errorDetails)
                );
            }

            $summarySnapshot = [
                'total_bills' => $evaluation['total_bills'],
                'complete_bills_count' => $evaluation['complete_bills_count'],
                'incomplete_bills_count' => $evaluation['incomplete_bills_count'],
                'carried_forward_count' => $evaluation['carried_forward_count'],
                'cash_bills_count' => $evaluation['cash_bills_count'],
                'credit_bills_count' => $evaluation['credit_bills_count'],
                'cash_total' => $evaluation['cash_total'],
                'credit_total' => $evaluation['credit_total'],
                'total_amount' => $evaluation['total_amount'],
            ];

            $checklistSnapshot = [
                'vendor_validated' => true,
                'products_validated' => true,
                'quantities_validated' => true,
                'rates_validated' => true,
                'totals_validated' => true,
                'payment_methods_validated' => true,
                'shop_scope_validated' => true,
                'business_date_validated' => true,
                'verified_at' => now()->toIso8601String(),
            ];

            $verification->update([
                'status' => ShopPurchaserDailyVerificationStatus::UserVerified,
                'verified_by' => $purchaser->id,
                'verified_at' => now(),
                'summary_snapshot' => $summarySnapshot,
                'checklist_snapshot' => $checklistSnapshot,
            ]);

            activity('shop_purchaser_verification')
                ->performedOn($verification)
                ->causedBy($purchaser)
                ->event('purchaser_verified')
                ->withProperties([
                    'shop_id' => $shop->id,
                    'business_date' => $date,
                    'purchaser_user_id' => $purchaser->id,
                    'status' => ShopPurchaserDailyVerificationStatus::UserVerified->value,
                    'summary_snapshot' => $summarySnapshot,
                    'checklist_snapshot' => $checklistSnapshot,
                ])
                ->log("Purchasing day on {$date} verified by purchaser {$purchaser->name}");

            return $verification->fresh();
        });
    }

    /**
     * Second Verification by an authorized user (different from the purchaser).
     */
    public function secondVerifyDay(ShopPurchaserDailyVerification $verification, User $secondVerifier): ShopPurchaserDailyVerification
    {
        return DB::transaction(function () use ($verification, $secondVerifier): ShopPurchaserDailyVerification {
            /** @var ShopPurchaserDailyVerification $locked */
            $locked = ShopPurchaserDailyVerification::query()
                ->whereKey($verification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($secondVerifier->id === (int) $locked->purchaser_user_id) {
                throw new RuntimeException('Purchaser cannot second-verify their own purchasing day.');
            }

            if ($locked->status === ShopPurchaserDailyVerificationStatus::SecondVerified) {
                throw new RuntimeException('Purchasing day is already second-verified.');
            }

            if ($locked->status === ShopPurchaserDailyVerificationStatus::Finalized) {
                throw new RuntimeException('Purchasing day is finalized.');
            }

            if ($locked->status !== ShopPurchaserDailyVerificationStatus::UserVerified) {
                throw new RuntimeException("Purchasing day must be in 'User Verified' status before second verification (current status: {$locked->status->label()}).");
            }

            $locked->update([
                'status' => ShopPurchaserDailyVerificationStatus::SecondVerified,
                'second_verified_by' => $secondVerifier->id,
                'second_verified_at' => now(),
            ]);

            activity('shop_purchaser_verification')
                ->performedOn($locked)
                ->causedBy($secondVerifier)
                ->event('second_verified')
                ->withProperties([
                    'shop_id' => $locked->shop_id,
                    'business_date' => $locked->business_date->toDateString(),
                    'purchaser_user_id' => $locked->purchaser_user_id,
                    'second_verified_by' => $secondVerifier->id,
                    'status' => ShopPurchaserDailyVerificationStatus::SecondVerified->value,
                ])
                ->log("Purchasing day on {$locked->business_date->toDateString()} second-verified by {$secondVerifier->name}");

            return $locked->fresh();
        });
    }

    /**
     * Finalize & lock the purchaser scope.
     */
    public function finalizeDay(ShopPurchaserDailyVerification $verification, User $actor): ShopPurchaserDailyVerification
    {
        return DB::transaction(function () use ($verification, $actor): ShopPurchaserDailyVerification {
            /** @var ShopPurchaserDailyVerification $locked */
            $locked = ShopPurchaserDailyVerification::query()
                ->whereKey($verification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === ShopPurchaserDailyVerificationStatus::Finalized) {
                throw new RuntimeException('Purchasing day is already finalized.');
            }

            if ($locked->status !== ShopPurchaserDailyVerificationStatus::SecondVerified) {
                throw new RuntimeException("Purchasing day must have second verification completed before finalization (current status: {$locked->status->label()}).");
            }

            $locked->update([
                'status' => ShopPurchaserDailyVerificationStatus::Finalized,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ]);

            activity('shop_purchaser_verification')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('finalized')
                ->withProperties([
                    'shop_id' => $locked->shop_id,
                    'business_date' => $locked->business_date->toDateString(),
                    'purchaser_user_id' => $locked->purchaser_user_id,
                    'finalized_by' => $actor->id,
                    'status' => ShopPurchaserDailyVerificationStatus::Finalized->value,
                ])
                ->log("Purchasing day on {$locked->business_date->toDateString()} finalized and locked by {$actor->name}");

            return $locked->fresh();
        });
    }

    /**
     * Admin Reopen of a finalized purchaser scope with mandatory reason.
     */
    public function reopenDay(ShopPurchaserDailyVerification $verification, User $admin, string $reason): ShopPurchaserDailyVerification
    {
        return DB::transaction(function () use ($verification, $admin, $reason): ShopPurchaserDailyVerification {
            /** @var ShopPurchaserDailyVerification $locked */
            $locked = ShopPurchaserDailyVerification::query()
                ->whereKey($verification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ShopPurchaserDailyVerificationStatus::Finalized) {
                throw new RuntimeException("Only finalized purchasing days can be reopened (current status: {$locked->status->label()}).");
            }

            $trimmedReason = trim($reason);
            if ($trimmedReason === '') {
                throw new RuntimeException('Reopen reason is required to reopen a finalized day.');
            }

            $prevVerifiedBy = $locked->verified_by;
            $prevVerifiedAt = $locked->verified_at;
            $prevSecondVerifiedBy = $locked->second_verified_by;
            $prevSecondVerifiedAt = $locked->second_verified_at;
            $prevFinalizedBy = $locked->finalized_by;
            $prevFinalizedAt = $locked->finalized_at;

            $locked->update([
                'status' => ShopPurchaserDailyVerificationStatus::Reopened,
                'reopened_by' => $admin->id,
                'reopened_at' => now(),
                'reopen_reason' => $trimmedReason,
                'verified_by' => null,
                'verified_at' => null,
                'second_verified_by' => null,
                'second_verified_at' => null,
                'finalized_by' => null,
                'finalized_at' => null,
            ]);

            activity('shop_purchaser_verification')
                ->performedOn($locked)
                ->causedBy($admin)
                ->event('reopened')
                ->withProperties([
                    'shop_id' => $locked->shop_id,
                    'business_date' => $locked->business_date->toDateString(),
                    'purchaser_user_id' => $locked->purchaser_user_id,
                    'reopened_by' => $admin->id,
                    'reopen_reason' => $trimmedReason,
                    'status' => ShopPurchaserDailyVerificationStatus::Reopened->value,
                    'previous_cycle' => [
                        'verified_by' => $prevVerifiedBy,
                        'verified_at' => $prevVerifiedAt?->toIso8601String(),
                        'second_verified_by' => $prevSecondVerifiedBy,
                        'second_verified_at' => $prevSecondVerifiedAt?->toIso8601String(),
                        'finalized_by' => $prevFinalizedBy,
                        'finalized_at' => $prevFinalizedAt?->toIso8601String(),
                    ],
                ])
                ->log("Purchasing day on {$locked->business_date->toDateString()} reopened by admin {$admin->name}: {$trimmedReason}");

            return $locked->fresh();
        });
    }

    /**
     * Ensure the purchaser scope for an invoice is NOT finalized.
     */
    public function assertScopeNotFinalizedForInvoice(PurchaseInvoice $invoice): void
    {
        $invoice->loadMissing('purchaserCart');

        $isShop = $invoice->isShopPurchase()
            || ($invoice->purchaserCart && ($invoice->purchaserCart->destination_shop_id !== null || $invoice->purchaserCart->purchase_source === 'shop'));

        if (! $isShop) {
            return;
        }

        $shopId = (int) ($invoice->shop_id ?: $invoice->purchaserCart?->destination_shop_id ?: 0);
        $businessDate = $invoice->purchaserCart?->business_date?->toDateString()
            ?: $invoice->original_business_date?->toDateString()
            ?: $invoice->created_at?->toDateString();
        $purchaserUserId = (int) ($invoice->purchaser_submitted_by ?: $invoice->purchaserCart?->user_id ?: 0);

        if ($shopId > 0 && $businessDate && $purchaserUserId > 0) {
            $this->assertScopeNotFinalized($shopId, $businessDate, $purchaserUserId);
        }
    }

    /**
     * Ensure the purchaser scope for a cart is NOT finalized.
     */
    public function assertScopeNotFinalizedForCart(PurchaserCart $cart): void
    {
        $isShop = $cart->destination_shop_id !== null || $cart->purchase_source === 'shop';

        if (! $isShop) {
            return;
        }

        $shopId = (int) ($cart->destination_shop_id ?: 0);
        $businessDate = $cart->business_date?->toDateString() ?: $cart->created_at?->toDateString();
        $purchaserUserId = (int) ($cart->user_id ?: 0);

        if ($shopId > 0 && $businessDate && $purchaserUserId > 0) {
            $this->assertScopeNotFinalized($shopId, $businessDate, $purchaserUserId);
        }
    }

    /**
     * Ensure the purchaser scope for shop + date is NOT finalized.
     */
    public function assertScopeNotFinalized(int $shopId, string $businessDate, int $purchaserUserId): void
    {
        $date = Carbon::parse($businessDate)->toDateString();

        $verification = ShopPurchaserDailyVerification::query()
            ->where('shop_id', $shopId)
            ->where('business_date', $date)
            ->where('purchaser_user_id', $purchaserUserId)
            ->first();

        if ($verification && $verification->isFinalized()) {
            throw new RuntimeException(
                "Purchasing for {$date} has been finalized for this purchaser. Modifications are locked. Contact an administrator to reopen."
            );
        }
    }

    /**
     * Ensure purchaser has finalized previous business days before creating purchases on a new business day.
     */
    public function assertPreviousDayFinalized(int $shopId, string $currentBusinessDate, int $purchaserUserId): void
    {
        $currentDate = Carbon::parse($currentBusinessDate)->toDateString();

        // Find all distinct business days prior to current date where this purchaser has purchase bills in this shop
        $priorDates = PurchaseInvoice::query()
            ->where(function ($q) use ($shopId): void {
                $q->where('shop_id', $shopId)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('destination_shop_id', $shopId));
            })
            ->where(function ($q) use ($purchaserUserId): void {
                $q->where('purchaser_submitted_by', $purchaserUserId)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('user_id', $purchaserUserId));
            })
            ->where(function ($q) use ($currentDate): void {
                $q->whereHas('purchaserCart', fn ($cq) => $cq->whereDate('business_date', '<', $currentDate))
                    ->orWhere(function ($sq) use ($currentDate): void {
                        $sq->whereNull('purchaser_cart_id')
                            ->where(function ($dsq) use ($currentDate): void {
                                $dsq->whereDate('original_business_date', '<', $currentDate)
                                    ->orWhere(function ($cdsq) use ($currentDate): void {
                                        $cdsq->whereNull('original_business_date')
                                            ->whereDate('created_at', '<', $currentDate);
                                    });
                            });
                    });
            })
            ->notCancelled()
            ->get()
            ->map(fn (PurchaseInvoice $inv) => $inv->purchaserCart?->business_date?->toDateString() ?: ($inv->original_business_date ? Carbon::parse($inv->original_business_date)->toDateString() : $inv->created_at->toDateString()))
            ->unique()
            ->values();

        foreach ($priorDates as $priorDate) {
            $verification = ShopPurchaserDailyVerification::query()
                ->where('shop_id', $shopId)
                ->where('business_date', $priorDate)
                ->where('purchaser_user_id', $purchaserUserId)
                ->first();

            if (! $verification || ! $verification->isFinalized()) {
                throw new RuntimeException(
                    "Previous purchasing day ({$priorDate}) is not finalized. Please verify and finalize your previous day before starting a new business day."
                );
            }
        }
    }

    /**
     * Admin summary of all purchasers for a given shop and date.
     *
     * @return Collection<int, array{
     *     purchaser: User,
     *     verification: ?ShopPurchaserDailyVerification,
     *     total_bills: int,
     *     cash_total: float,
     *     credit_total: float,
     *     pending_count: int,
     *     status: ShopPurchaserDailyVerificationStatus,
     *     verified_at: ?Carbon,
     *     second_verified_at: ?Carbon,
     *     finalized_at: ?Carbon,
     *     reopened_at: ?Carbon
     * }>
     */
    public function getAdminDailyStatus(Shop $shop, string $businessDate): Collection
    {
        $date = Carbon::parse($businessDate)->toDateString();

        // Find all purchasers who have bills on this date or an existing verification record
        $purchaserIds = PurchaseInvoice::query()
            ->where(function ($q) use ($shop): void {
                $q->where('shop_id', $shop->id)
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('destination_shop_id', $shop->id));
            })
            ->where(function ($q) use ($date): void {
                $q->whereHas('purchaserCart', fn ($cq) => $cq->whereDate('business_date', $date))
                    ->orWhere(function ($sq) use ($date): void {
                        $sq->whereNull('purchaser_cart_id')
                            ->where(function ($dsq) use ($date): void {
                                $dsq->whereDate('original_business_date', $date)
                                    ->orWhere(function ($cdsq) use ($date): void {
                                        $cdsq->whereNull('original_business_date')
                                            ->whereDate('created_at', $date);
                                    });
                            });
                    });
            })
            ->notCancelled()
            ->pluck('purchaser_submitted_by')
            ->merge(
                ShopPurchaserDailyVerification::query()
                    ->where('shop_id', $shop->id)
                    ->where('business_date', $date)
                    ->pluck('purchaser_user_id')
            )
            ->filter()
            ->unique()
            ->values();

        $users = User::query()->whereIn('id', $purchaserIds)->get()->keyBy('id');
        $verifications = ShopPurchaserDailyVerification::query()
            ->with(['verifiedBy', 'secondVerifiedBy', 'finalizedBy', 'reopenedBy'])
            ->where('shop_id', $shop->id)
            ->where('business_date', $date)
            ->get()
            ->keyBy('purchaser_user_id');

        $rows = collect();

        foreach ($purchaserIds as $purchaserId) {
            $user = $users->get($purchaserId);
            if (! $user) {
                continue;
            }

            $evaluation = $this->evaluateBills($shop, $date, $user);
            $verification = $verifications->get($purchaserId) ?? $evaluation['verification'];

            $rows->push([
                'purchaser' => $user,
                'verification' => $verification,
                'total_bills' => $evaluation['total_bills'],
                'cash_total' => $evaluation['cash_total'],
                'credit_total' => $evaluation['credit_total'],
                'pending_count' => $evaluation['incomplete_bills_count'],
                'status' => $verification->status,
                'verified_by_name' => $verification->verifiedBy?->name,
                'verified_at' => $verification->verified_at,
                'second_verified_by_name' => $verification->secondVerifiedBy?->name,
                'second_verified_at' => $verification->second_verified_at,
                'finalized_by_name' => $verification->finalizedBy?->name,
                'finalized_at' => $verification->finalized_at,
                'reopened_by_name' => $verification->reopenedBy?->name,
                'reopened_at' => $verification->reopened_at,
                'reopen_reason' => $verification->reopen_reason,
            ]);
        }

        return $rows;
    }
}
