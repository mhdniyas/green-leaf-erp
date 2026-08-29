<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Support\Carbon;

class CashFlowTransactionPresenter
{
    /**
     * Resolve presentation state, audit trail, and the single correct next action for a transaction.
     *
     * @return array<string, mixed>
     */
    public function present(ShopLedgerTransaction $transaction): array
    {
        $transaction->loadMissing(['entryType', 'shop', 'enteredBy', 'approvedBy', 'voidedBy', 'companyAccount']);

        $statement = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->with(['companyAccount', 'reconciledBy'])
            ->first();

        $setting = ShopLedgerEntrySetting::query()
            ->with('companyAccount')
            ->where('shop_id', $transaction->shop_id)
            ->where('entry_type_id', $transaction->entry_type_id)
            ->first();

        $code = (string) ($transaction->entryType?->code ?: ($transaction->entry_type_code ?? ''));
        $lowerCode = strtolower($code);
        $shopName = $transaction->shop?->name ?? 'Shop';
        $shopSlug = $transaction->shop?->slug ?: ($transaction->shop?->shop_id ?: 1);

        $paymentMethod = match (true) {
            str_contains($lowerCode, 'paytm') => 'Paytm',
            str_contains($lowerCode, 'card') => 'Card',
            str_contains($lowerCode, 'upi') => 'UPI',
            str_contains($lowerCode, 'cash') => 'Cash',
            default => $transaction->entryType?->name ?: ($code ?: 'Collection'),
        };

        $isCash = str_contains($lowerCode, 'cash')
            || $setting?->companyAccount?->account_type === 'cash'
            || $statement?->companyAccount?->account_type === 'cash'
            || $transaction->companyAccount?->account_type === 'cash';

        $destinationAccountName = $statement?->companyAccount?->name
            ?? $transaction->companyAccount?->name
            ?? $setting?->companyAccount?->name;

        $amount = (float) $transaction->amount;
        $businessDate = $transaction->business_date?->format('d M Y') ?: Carbon::parse($transaction->created_at)->format('d M Y');
        $rawBusinessDate = $transaction->business_date?->toDateString() ?: Carbon::parse($transaction->created_at)->toDateString();

        $isReversed = $transaction->status === 'reversed';
        $isVoid = in_array($transaction->status, ['void', 'voided'], true);
        $isSuperseded = $statement?->status === 'superseded';
        $isApproved = $transaction->status === 'approved';
        $isVerified = $statement && $statement->is_finalized && $statement->status === 'reconciled';
        $isDuplicate = $statement && $statement->duplicate_status === 'possible_duplicate';

        // Stage & Action resolution
        $stage = 'posted';
        $displayStatus = 'POSTED';
        $statusStyle = 'slate';
        $nextAction = null;
        $nextActionLabel = null;
        $nextActionUrl = null;
        $nextActionMethod = null;
        $confirmationType = null;
        $attentionReason = null;

        if ($isReversed) {
            $stage = 'reversed';
            $displayStatus = 'REVERSED';
            $statusStyle = 'rose';
        } elseif ($isVoid) {
            $stage = 'void';
            $displayStatus = 'VOID';
            $statusStyle = 'rose';
        } elseif ($isSuperseded) {
            $stage = 'superseded';
            $displayStatus = 'SUPERSEDED';
            $statusStyle = 'slate';
        } elseif ($isDuplicate) {
            $stage = 'exception';
            $displayStatus = 'NEEDS ATTENTION';
            $statusStyle = 'amber';
            $attentionReason = 'Possible duplicate bank statement entry detected.';
            $nextAction = 'resolve_issue';
            $nextActionLabel = 'RESOLVE ISSUE';
            $nextActionUrl = route('admin.cashbook.finance.reconciliation');
            $nextActionMethod = 'GET';
        } elseif (! $isApproved) {
            $stage = 'posted';
            $displayStatus = 'POSTED';
            $statusStyle = 'slate';
            $nextAction = 'approve';
            $nextActionLabel = 'APPROVE';
            $nextActionUrl = route('admin.cashbook.transaction.approve', $transaction->id);
            $nextActionMethod = 'POST';
            $confirmationType = 'approve';
        } elseif ($isVerified) {
            $stage = 'verified';
            $displayStatus = 'RECEIVED';
            $statusStyle = 'emerald';
        } elseif ($isCash) {
            $stage = 'approved_cash';
            $displayStatus = 'CASH WITH SHOP';
            $statusStyle = 'sky';
            $nextAction = 'verify_cash_received';
            $nextActionLabel = 'VERIFY CASH RECEIVED';
            $nextActionUrl = route('admin.cashbook.transaction.verify', $transaction->id);
            $nextActionMethod = 'POST';
            $confirmationType = 'cash';
        } else {
            $stage = 'approved_online';
            $displayStatus = 'NEEDS VERIFICATION';
            $statusStyle = 'amber';
            $nextAction = 'verify_received';
            $nextActionLabel = 'VERIFY RECEIVED';
            $nextActionUrl = route('admin.cashbook.transaction.verify', $transaction->id);
            $nextActionMethod = 'POST';
            $confirmationType = 'online';
        }

        // Location & destination labels
        $currentLocation = $isCash
            ? ($isVerified ? ($destinationAccountName ?: 'Company Cash Box') : '📍 '.$shopName.' Shop')
            : ($destinationAccountName ?: 'Company Bank Account');

        $destinationFormatted = $isCash
            ? ($isVerified ? '→ '.($destinationAccountName ?: 'Company Cash Box') : '📍 '.$shopName.' Shop')
            : '→ '.($destinationAccountName ?: 'Company Bank');

        // Flow Steps
        $flowSteps = [
            [
                'key' => 'recorded',
                'label' => 'Recorded',
                'state' => 'completed',
            ],
            [
                'key' => 'approved',
                'label' => 'Approved',
                'state' => $isApproved ? 'completed' : ($stage === 'posted' ? 'current' : 'pending'),
            ],
            [
                'key' => 'stage',
                'label' => $isCash ? 'Cash With Shop' : 'Needs Verification',
                'state' => in_array($stage, ['approved_online', 'approved_cash', 'exception'], true)
                    ? 'current'
                    : ($isVerified ? 'completed' : 'pending'),
            ],
            [
                'key' => 'company_received',
                'label' => 'Company Received',
                'state' => $isVerified ? 'completed' : 'pending',
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'state' => $isVerified ? 'completed' : 'pending',
            ],
        ];

        return [
            'id' => $transaction->id,
            'transaction' => $transaction,
            'shop_id' => $transaction->shop_id,
            'shop_name' => $shopName,
            'shop_slug' => $shopSlug,
            'payment_method' => $paymentMethod,
            'is_cash' => $isCash,
            'amount' => $amount,
            'business_date' => $businessDate,
            'raw_business_date' => $rawBusinessDate,
            'destination_account_name' => $destinationAccountName ?: ($isCash ? 'Main Cash Box' : 'Company Bank'),
            'destination_formatted' => $destinationFormatted,
            'current_location' => $currentLocation,
            'company_verified_receipt' => $isVerified ? 'Yes' : 'Not yet',
            'stage' => $stage,
            'display_status' => $displayStatus,
            'status_style' => $statusStyle,
            'can_act' => $nextAction !== null,
            'next_action' => $nextAction,
            'next_action_label' => $nextActionLabel,
            'next_action_url' => $nextActionUrl,
            'next_action_method' => $nextActionMethod,
            'confirmation_type' => $confirmationType,
            'attention_reason' => $attentionReason,
            'flow_steps' => $flowSteps,
            'can_admin_reverse' => $isVerified && ! $isReversed && ! $isVoid,
            'can_admin_correct' => $isVerified && ! $isReversed && ! $isVoid,
            'can_admin_edit' => ! $isVerified && ! $isReversed && ! $isVoid,
            'can_admin_delete' => ! $isVerified && ! $isReversed && ! $isVoid,
            'is_reversed' => $isReversed,
            'is_verified' => $isVerified,
            'reversal' => $isReversed ? [
                'reversed_by' => $transaction->voidedBy?->name ?? 'Admin',
                'reversed_at' => $transaction->voided_at ? Carbon::parse($transaction->voided_at)->format('d M Y · h:i A') : null,
                'reversal_reason' => $transaction->void_reason,
            ] : null,
            'audit' => [
                'entered_by' => $transaction->enteredBy?->name ?? 'Shop User',
                'entered_at' => $transaction->created_at?->format('d M Y · h:i A'),
                'approved_by' => $transaction->approvedBy?->name,
                'approved_at' => $transaction->approved_at ? Carbon::parse($transaction->approved_at)->format('d M Y · h:i A') : null,
                'verified_by' => $statement?->reconciledBy?->name,
                'verified_at' => $statement?->reconciled_at?->format('d M Y · h:i A'),
                'statement_ref' => $statement?->reference,
                'statement_uuid' => $statement?->public_uuid,
            ],
        ];
    }
}
