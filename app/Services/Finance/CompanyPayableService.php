<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\Shop;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Notifications\CompanyExpenseRequestSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class CompanyPayableService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    public function markCompanyPayableOnLine(ShopAccountingEntryLine $line): ShopAccountingEntryLine
    {
        if ($line->type !== 'expense' || $line->funding_source !== ShopAccountingEntryLine::FundingCompany) {
            return $line;
        }

        if (filled($line->company_payable_status) && $line->company_payable_status !== ShopAccountingEntryLine::PayablePending) {
            return $line;
        }

        $line->forceFill([
            'company_payable_status' => ShopAccountingEntryLine::PayablePending,
            'company_payable_amount' => round((float) $line->amount, 2),
            'company_approved_amount' => null,
            'company_settled_amount' => 0,
            'company_settlement_status' => ShopAccountingEntryLine::SettlementUnsettled,
            'company_rejection_reason' => null,
            'company_rejected_by' => null,
            'company_rejected_at' => null,
        ])->save();

        return $line->fresh();
    }

    public function notifyAdmins(ShopAccountingEntryLine $line): void
    {
        $line->loadMissing(['entry.shop', 'category']);
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new CompanyExpenseRequestSubmitted($line));
    }

    public function approve(ShopAccountingEntryLine $line, int $userId, float|int|string|null $approvedAmount = null): ShopAccountingEntryLine
    {
        return DB::transaction(function () use ($line, $userId, $approvedAmount): ShopAccountingEntryLine {
            $line = ShopAccountingEntryLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();

            if ($line->funding_source !== ShopAccountingEntryLine::FundingCompany) {
                throw ValidationException::withMessages(['line' => 'Only company-funded expenses can be approved as payables.']);
            }

            if ($line->company_payable_status === ShopAccountingEntryLine::PayableApproved) {
                throw ValidationException::withMessages(['line' => 'This company payable has already been approved.']);
            }

            if ($line->company_payable_status === ShopAccountingEntryLine::PayableRejected) {
                throw ValidationException::withMessages(['line' => 'Rejected company payables cannot be approved.']);
            }

            $amount = round((float) ($approvedAmount ?? $line->company_payable_amount ?? $line->amount), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['approved_amount' => 'Approved amount must be greater than zero.']);
            }

            $line->forceFill([
                'company_payable_status' => ShopAccountingEntryLine::PayableApproved,
                'company_approved_amount' => $amount,
                'company_payable_amount' => round((float) ($line->company_payable_amount ?? $line->amount), 2),
                'company_settled_amount' => round((float) ($line->company_settled_amount ?? 0), 2),
                'company_approved_by' => $userId,
                'company_approved_at' => now(),
                'company_settlement_status' => ShopAccountingEntryLine::SettlementUnsettled,
            ]);
            $line->refreshSettlementStatus();
            $line->save();

            $this->journalService->recordCompanyPayableApproval($line, $userId);

            activity()
                ->causedBy(User::query()->find($userId))
                ->performedOn($line)
                ->withProperties([
                    'old' => ['company_payable_status' => ShopAccountingEntryLine::PayablePending],
                    'new' => ['company_payable_status' => ShopAccountingEntryLine::PayableApproved, 'company_approved_amount' => $amount],
                    'shop_id' => $line->entry?->shop_id,
                ])
                ->event('company_payable_approved')
                ->log('Company payable approved');

            return $line->fresh(['entry.shop', 'category', 'settlements']);
        });
    }

    public function reject(ShopAccountingEntryLine $line, int $userId, string $reason): ShopAccountingEntryLine
    {
        return DB::transaction(function () use ($line, $userId, $reason): ShopAccountingEntryLine {
            $line = ShopAccountingEntryLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();

            if ($line->company_payable_status === ShopAccountingEntryLine::PayableRejected) {
                throw ValidationException::withMessages(['line' => 'This company payable has already been rejected.']);
            }

            if ((float) ($line->company_settled_amount ?? 0) > 0) {
                throw ValidationException::withMessages(['line' => 'Settled company payables cannot be rejected.']);
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['rejection_reason' => 'Rejection reason is required.']);
            }

            $line->forceFill([
                'company_payable_status' => ShopAccountingEntryLine::PayableRejected,
                'company_rejection_reason' => $reason,
                'company_rejected_by' => $userId,
                'company_rejected_at' => now(),
            ])->save();

            activity()
                ->causedBy(User::query()->find($userId))
                ->performedOn($line)
                ->withProperties(['reason' => $reason, 'shop_id' => $line->entry?->shop_id])
                ->event('company_payable_rejected')
                ->log('Company payable rejected');

            return $line->fresh(['entry.shop', 'category']);
        });
    }

    public function settleAgainstShopPayment(
        ShopAccountingEntryLine $line,
        ShopInvoicePaymentRequest $paymentRequest,
        float $amount,
        int $userId,
        ?string $notes = null,
    ): CompanyPayableSettlement {
        return DB::transaction(function () use ($line, $paymentRequest, $amount, $userId, $notes): CompanyPayableSettlement {
            $line = ShopAccountingEntryLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $this->assertSettleable($line, $amount);

            $settlement = CompanyPayableSettlement::query()->create([
                'shop_accounting_entry_line_id' => $line->id,
                'shop_id' => (int) ($line->entry?->shop_id ?: $paymentRequest->shop_id),
                'settlement_type' => CompanyPayableSettlement::TypeAdjustAgainstShopPayment,
                'amount' => round($amount, 2),
                'settlement_date' => now()->toDateString(),
                'shop_invoice_payment_request_id' => $paymentRequest->id,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $journal = $this->journalService->recordCompanyPayableAdjustment($settlement, $userId);
            $settlement->update(['journal_entry_id' => $journal->id]);

            $this->applySettlementAmount($line, $amount);

            activity()
                ->causedBy(User::query()->find($userId))
                ->performedOn($line)
                ->withProperties(['settlement_id' => $settlement->id, 'amount' => $amount])
                ->event('company_payable_settled_adjustment')
                ->log('Company payable adjusted against shop payment');

            return $settlement->fresh();
        });
    }

    /**
     * @param  array{payment_mode?: string, payee?: string, reference?: string|null, notes?: string|null, payment_account_id?: int|null, settlement_date?: string|null}  $payload
     */
    public function settleDirectPayment(ShopAccountingEntryLine $line, float $amount, int $userId, array $payload = []): CompanyPayableSettlement
    {
        return DB::transaction(function () use ($line, $amount, $userId, $payload): CompanyPayableSettlement {
            $line = ShopAccountingEntryLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->loadMissing('entry.shop');
            $this->assertSettleable($line, $amount);

            $shopId = (int) $line->entry?->shop_id;
            $paymentMode = (string) ($payload['payment_mode'] ?? 'cash');
            $category = CompanyAccountingCategory::query()
                ->where('type', 'expense')
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            $companyEntry = null;
            if ($category instanceof CompanyAccountingCategory) {
                $companyEntry = CompanyAccountingEntry::query()->create([
                    'company_accounting_category_id' => $category->id,
                    'type' => 'expense',
                    'business_date' => $payload['settlement_date'] ?? now()->toDateString(),
                    'payment_mode' => $paymentMode,
                    'amount' => round($amount, 2),
                    'description' => 'Company payable settlement for shop #'.$shopId.' line #'.$line->id,
                    'reference' => $payload['reference'] ?? null,
                    'payment_reference' => $payload['reference'] ?? null,
                    'status' => CompanyAccountingEntry::StatusFinal,
                    'created_by' => $userId,
                ]);
            }

            $settlement = CompanyPayableSettlement::query()->create([
                'shop_accounting_entry_line_id' => $line->id,
                'shop_id' => $shopId,
                'settlement_type' => CompanyPayableSettlement::TypeDirectCompanyPayment,
                'amount' => round($amount, 2),
                'settlement_date' => $payload['settlement_date'] ?? now()->toDateString(),
                'company_accounting_entry_id' => $companyEntry?->id,
                'payment_account_id' => $payload['payment_account_id'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? ($payload['payee'] ?? null),
                'created_by' => $userId,
            ]);

            $journal = $this->journalService->recordCompanyPayableDirectPayment($settlement, $userId, $paymentMode);
            $settlement->update(['journal_entry_id' => $journal->id]);

            $this->applySettlementAmount($line, $amount);

            activity()
                ->causedBy(User::query()->find($userId))
                ->performedOn($line)
                ->withProperties(['settlement_id' => $settlement->id, 'amount' => $amount])
                ->event('company_payable_settled_direct')
                ->log('Company payable paid directly');

            return $settlement->fresh();
        });
    }

    /**
     * @return Collection<int, ShopAccountingEntryLine>
     */
    public function pendingLines(?Shop $shop = null): Collection
    {
        return ShopAccountingEntryLine::query()
            ->with(['entry.shop.client', 'category'])
            ->where('funding_source', ShopAccountingEntryLine::FundingCompany)
            ->where('company_payable_status', ShopAccountingEntryLine::PayablePending)
            ->when($shop instanceof Shop, fn ($query) => $query->whereHas('entry', fn ($q) => $q->where('shop_id', $shop->id)))
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, ShopAccountingEntryLine>
     */
    public function linesForShop(Shop $shop): Collection
    {
        return ShopAccountingEntryLine::query()
            ->with(['entry', 'category'])
            ->where('funding_source', ShopAccountingEntryLine::FundingCompany)
            ->whereHas('entry', fn ($query) => $query->where('shop_id', $shop->id))
            ->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, ShopAccountingEntryLine>
     */
    public function openPayables(?Shop $shop = null): Collection
    {
        return ShopAccountingEntryLine::query()
            ->with(['entry.shop.client', 'category', 'settlements'])
            ->where('funding_source', ShopAccountingEntryLine::FundingCompany)
            ->whereIn('company_payable_status', [
                ShopAccountingEntryLine::PayablePending,
                ShopAccountingEntryLine::PayableApproved,
            ])
            ->when($shop instanceof Shop, fn ($query) => $query->whereHas('entry', fn ($q) => $q->where('shop_id', $shop->id)))
            ->latest('id')
            ->get();
    }

    private function assertSettleable(ShopAccountingEntryLine $line, float $amount): void
    {
        if ($line->company_payable_status !== ShopAccountingEntryLine::PayableApproved) {
            throw ValidationException::withMessages(['line' => 'Only approved company payables can be settled.']);
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Settlement amount must be greater than zero.']);
        }

        $remaining = $line->remainingCompanyPayableAmount();
        if ($amount > $remaining + 0.0001) {
            throw ValidationException::withMessages(['amount' => 'Cannot settle more than the remaining payable amount.']);
        }
    }

    private function applySettlementAmount(ShopAccountingEntryLine $line, float $amount): void
    {
        $line->company_settled_amount = round((float) ($line->company_settled_amount ?? 0) + $amount, 2);
        $line->refreshSettlementStatus();
        $line->save();
    }
}
