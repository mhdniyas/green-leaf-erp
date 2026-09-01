<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\DTOs\Finance\JournalEntryData;
use App\Models\Account;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\DirectCompanySale;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\SalesInvoice;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Models\WastageEntry;
use App\Repositories\Finance\JournalEntryRepository;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Support\ChartOfAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class JournalService
{
    public function __construct(
        private readonly JournalEntryRepository $repository,
    ) {}

    /**
     * Admin correction or cancellation of a journal entry preserving complete accounting immutability.
     *
     * In accordance with canonical double-entry accounting:
     * 1. The original JournalEntry and its transactions are locked and left immutable.
     * 2. A balanced reversal JournalEntry is created to cleanly invert the debit and credit lines.
     * 3. Dependent purchaser credits, statement matches, and reconciliation allocations are reversed.
     * 4. If amount > 0, a corrected replacement entry and counterpart are created.
     * 5. If amount == 0, the entry is cancelled through reversal without creating a replacement.
     *
     * @param  array{
     *     amount?: float|numeric-string,
     *     entry_date?: string,
     *     purchaser_id?: int|numeric-string|null,
     *     company_account_id?: int|numeric-string|null,
     *     payment_source?: string|null,
     *     reference?: string|null,
     *     description?: string|null,
     *     reason?: string|null,
     *     ip?: string|null
     * }  $data
     * @return array{original: JournalEntry, reversal: JournalEntry, replacement: ?JournalEntry}
     */
    public function correctJournalEntry(JournalEntry $journalEntry, array $data, User $actor): array
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages([
                'reason' => 'A valid correction reason (at least 3 characters) is required.',
            ]);
        }

        return DB::transaction(function () use ($journalEntry, $data, $actor, $reason): array {
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::query()
                ->with(['transactions.account', 'statementEntries', 'reconciliations.paymentRequest'])
                ->whereKey($journalEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Validate that the entry has not already been reversed
            $alreadyReversed = str_starts_with((string) $journalEntry->source_event, 'reversal:')
                || $journalEntry->source_event === 'reversal'
                || str_contains((string) $journalEntry->description, '[REVERSED]')
                || JournalEntry::query()->where('source_event', "reversal:{$journalEntry->id}")->exists();

            if ($alreadyReversed) {
                throw ValidationException::withMessages([
                    'journal_entry' => 'This journal entry has already been reversed and cannot be edited again.',
                ]);
            }

            $oldValues = [
                'id' => $journalEntry->id,
                'entry_date' => $journalEntry->entry_date?->toDateString(),
                'reference' => $journalEntry->reference,
                'description' => $journalEntry->description,
                'primary_amount' => $journalEntry->primary_amount,
                'source_type' => $journalEntry->source_type,
                'source_id' => $journalEntry->source_id,
                'source_event' => $journalEntry->source_event,
                'is_finalized' => $journalEntry->is_finalized,
                'reconciliation_status' => $journalEntry->reconciliation_status,
            ];

            $newAmount = round((float) ($data['amount'] ?? $journalEntry->primary_amount), 2);
            $entryDate = ! empty($data['entry_date']) ? (string) $data['entry_date'] : $journalEntry->entry_date->toDateString();
            $description = filled($data['description'] ?? null) ? trim((string) $data['description']) : (string) $journalEntry->description;
            $reference = filled($data['reference'] ?? null) ? trim((string) $data['reference']) : (string) $journalEntry->reference;
            $ipAddress = $data['ip'] ?? request()->ip();

            // 2. Purchaser Credit Safety & Atomic Final Position Evaluation (if source is PurchaserCredit)
            $originalCredit = null;
            $confirmShortfall = filter_var($data['confirm_shortfall'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($journalEntry->source_type === PurchaserCredit::class && $journalEntry->source_id) {
                $originalCredit = PurchaserCredit::query()->whereKey($journalEntry->source_id)->lockForUpdate()->first();
                if ($originalCredit instanceof PurchaserCredit) {
                    $originalCredit->loadMissing('purchaser');
                    $targetPurchaserId = ! empty($data['purchaser_id']) ? (int) $data['purchaser_id'] : (int) $originalCredit->purchaser_id;
                    $targetPurchaser = User::query()->whereKey($targetPurchaserId)->first();

                    if (! $targetPurchaser || ! $targetPurchaser->hasRole('purchaser')) {
                        throw ValidationException::withMessages([
                            'purchaser_id' => 'Selected user is not a valid purchaser.',
                        ]);
                    }

                    if ($originalCredit->type === 'in') {
                        $currentAdvance = (float) PurchaserCredit::query()
                            ->where('purchaser_id', $originalCredit->purchaser_id)
                            ->lockForUpdate()
                            ->sum(DB::raw("CASE WHEN type = 'in' THEN amount ELSE -amount END"));

                        $utilizedAmount = (float) PurchaserCredit::query()
                            ->where('purchaser_id', $originalCredit->purchaser_id)
                            ->where('type', 'out')
                            ->whereNotNull('purchase_invoice_id')
                            ->sum('amount');

                        if ($targetPurchaserId === (int) $originalCredit->purchaser_id) {
                            // Same purchaser: evaluate atomic final position = current - original + replacement
                            $finalPosition = round($currentAdvance - (float) $originalCredit->amount + $newAmount, 2);

                            if ($finalPosition < -0.009 && ! $confirmShortfall) {
                                $shortfall = abs($finalPosition);
                                throw ValidationException::withMessages([
                                    'amount' => 'Reducing funding from ₹'.number_format((float) $originalCredit->amount, 2).' to ₹'.number_format($newAmount, 2).' creates a purchaser advance deficit of ₹'.number_format($shortfall, 2).' (utilized spend on bills is ₹'.number_format($utilizedAmount, 2).'). Please review the impact and confirm to proceed.',
                                ]);
                            }
                        } else {
                            // Purchaser reassigned: Purchaser A loses original funding; Purchaser B gets replacement funding
                            $originalPurchaserFinal = round($currentAdvance - (float) $originalCredit->amount, 2);
                            if ($originalPurchaserFinal < -0.009 && ! $confirmShortfall) {
                                $shortfall = abs($originalPurchaserFinal);
                                throw ValidationException::withMessages([
                                    'purchaser_id' => "Reassigning funding to {$targetPurchaser->name} removes ₹".number_format((float) $originalCredit->amount, 2)." from {$originalCredit->purchaser?->name}, creating an advance deficit of ₹".number_format($shortfall, 2)." on {$originalCredit->purchaser?->name} (purchase bills will remain with {$originalCredit->purchaser?->name}). Please review and confirm to proceed.",
                                ]);
                            }
                        }
                    } elseif ($originalCredit->type === 'out') {
                        $currentAdvance = (float) PurchaserCredit::query()
                            ->where('purchaser_id', $originalCredit->purchaser_id)
                            ->lockForUpdate()
                            ->sum(DB::raw("CASE WHEN type = 'in' THEN amount ELSE -amount END"));

                        if ($targetPurchaserId === (int) $originalCredit->purchaser_id) {
                            $finalPosition = round($currentAdvance + (float) $originalCredit->amount - $newAmount, 2);

                            if ($finalPosition < -0.009 && ! $confirmShortfall) {
                                $shortfall = abs($finalPosition);
                                throw ValidationException::withMessages([
                                    'amount' => 'Correcting return amount creates an advance deficit of ₹'.number_format($shortfall, 2).'. Please review and confirm to proceed.',
                                ]);
                            }
                        } else {
                            $targetPurchaserBal = (float) PurchaserCredit::query()
                                ->where('purchaser_id', $targetPurchaserId)
                                ->lockForUpdate()
                                ->sum(DB::raw("CASE WHEN type = 'in' THEN amount ELSE -amount END"));

                            if ($targetPurchaserBal - $newAmount < -0.009 && ! $confirmShortfall) {
                                $shortfall = abs($targetPurchaserBal - $newAmount);
                                throw ValidationException::withMessages([
                                    'amount' => 'Return amount of ₹'.number_format($newAmount, 2)." exceeds {$targetPurchaser->name}'s available advance by ₹".number_format($shortfall, 2).'. Please review and confirm to proceed.',
                                ]);
                            }
                        }
                    }
                }
            }

            // 3. Create Balanced Double-Entry Reversal
            $reversalLines = [];
            foreach ($journalEntry->transactions as $line) {
                $reversalLines[] = [
                    'account_id' => $line->account_id,
                    'type' => $line->type === 'debit' ? 'credit' : 'debit',
                    'amount' => (float) $line->amount,
                ];
            }

            $reversalData = new JournalEntryData(
                entryDate: now()->toDateString(),
                reference: "REV-JE-{$journalEntry->id}",
                description: "Reversal of JE #{$journalEntry->id} ({$journalEntry->formatted_reference}): {$reason}",
                lines: $reversalLines,
                sourceType: $journalEntry->source_type,
                sourceId: $journalEntry->source_id,
                sourceEvent: "reversal:{$journalEntry->id}",
            );
            $reversalEntry = $this->createEntry($reversalData, $actor->id);

            // 4. Reverse Linked Purchaser Credit
            if ($originalCredit instanceof PurchaserCredit) {
                $offsetCreditType = $originalCredit->type === 'in' ? 'out' : 'in';
                PurchaserCredit::query()->create([
                    'purchaser_id' => $originalCredit->purchaser_id,
                    'type' => $offsetCreditType,
                    'amount' => (float) $originalCredit->amount,
                    'business_date' => now()->toDateString(),
                    'description' => "Reversal offset for movement #{$originalCredit->id}: {$reason}",
                    'payment_source' => $originalCredit->payment_source,
                    'company_account_id' => $originalCredit->company_account_id,
                    'reference' => "REV-PURCH-{$originalCredit->id}",
                    'purchase_invoice_id' => null,
                    'created_by' => $actor->id,
                ]);

                $cleanOriginalCreditDesc = preg_replace('/\s*\[REVERSED[^\]]*\]/i', '', (string) $originalCredit->description);
                $originalCredit->update([
                    'description' => trim(($cleanOriginalCreditDesc ?: 'Purchaser movement')." [REVERSED: {$reason}]"),
                ]);
            }

            // 5. Reverse Linked Statement Matches & Reconciliations
            $linkedStatements = CompanyAccountStatementEntry::query()
                ->where(function ($q) use ($journalEntry): void {
                    $q->where('journal_entry_id', $journalEntry->id);
                    if ($journalEntry->source_type && $journalEntry->source_id) {
                        $q->orWhere(fn ($sq) => $sq->where('source_type', $journalEntry->source_type)->where('source_id', $journalEntry->source_id));
                    }
                })
                ->lockForUpdate()
                ->get();

            $hadManualCounterpart = false;
            $oldCounterpartAccountId = null;

            foreach ($linkedStatements as $stmt) {
                $isImported = $stmt->source === 'imported'
                    || ! empty($stmt->import_file_name)
                    || ! empty($stmt->import_fingerprint);

                if ($isImported) {
                    $stmt->update([
                        'status' => 'unmatched',
                        'matched_amount' => 0,
                        'journal_entry_id' => null,
                        'source_type' => null,
                        'source_id' => null,
                        'is_finalized' => false,
                        'finalized_at' => null,
                        'reconciled_by' => null,
                        'reconciled_at' => null,
                        'notes' => trim(($stmt->notes ?? '')." [Unmatched: Journal #{$journalEntry->id} reversed by {$actor->name}]"),
                    ]);
                } else {
                    $hadManualCounterpart = true;
                    $oldCounterpartAccountId = $stmt->company_account_id;

                    if ($stmt->company_account_id) {
                        $oldAccount = CompanyAccount::query()->whereKey($stmt->company_account_id)->lockForUpdate()->first();
                        if ($oldAccount instanceof CompanyAccount) {
                            if (in_array($stmt->direction, ['out', 'debit'], true)) {
                                $oldAccount->increment('current_balance', (float) $stmt->amount);
                            } elseif (in_array($stmt->direction, ['in', 'credit'], true)) {
                                $oldAccount->decrement('current_balance', (float) $stmt->amount);
                            }
                        }
                    }

                    $stmt->update([
                        'status' => 'reversed',
                        'is_finalized' => false,
                        'finalized_at' => null,
                        'matched_amount' => 0,
                        'journal_entry_id' => null,
                        'source_type' => null,
                        'source_id' => null,
                        'notes' => trim(($stmt->notes ?? '')." [Reversed: Journal #{$journalEntry->id} reversed by {$actor->name}]"),
                    ]);
                }
            }

            // Reverse linked reconciliations
            $linkedReconciliations = CompanyPaymentReconciliation::query()->where('journal_entry_id', $journalEntry->id)->lockForUpdate()->get();
            $paymentsToRefresh = $linkedReconciliations->pluck('paymentRequest')->filter();
            $linkedReconciliations->each->delete();
            foreach ($paymentsToRefresh as $payment) {
                app(CompanyPaymentReconciliationService::class)->refreshPaymentReconciliationTotals($payment);
            }

            // Mark original journal entry description with reversal info
            $cleanJournalDesc = preg_replace('/\s*\[REVERSED[^\]]*\]/i', '', (string) $journalEntry->description);
            $journalEntry->update([
                'description' => trim(($cleanJournalDesc ?: 'Journal Entry')." [REVERSED by JE #{$reversalEntry->id}: {$reason}]"),
            ]);

            // 6. Create Replacement Entry if $newAmount > 0
            $replacementJournal = null;
            if ($newAmount > 0.0001) {
                if ($journalEntry->source_type === PurchaserCredit::class && $originalCredit instanceof PurchaserCredit) {
                    $targetPurchaserId = ! empty($data['purchaser_id']) ? (int) $data['purchaser_id'] : (int) $originalCredit->purchaser_id;
                    $targetPurchaser = User::query()->whereKey($targetPurchaserId)->first();

                    if (! $targetPurchaser || ! $targetPurchaser->hasRole('purchaser')) {
                        throw ValidationException::withMessages([
                            'purchaser_id' => 'Selected user is not a valid purchaser.',
                        ]);
                    }

                    if ($originalCredit->type === 'out') {
                        $targetPurchaserBal = (float) PurchaserCredit::query()
                            ->where('purchaser_id', $targetPurchaserId)
                            ->sum(DB::raw("CASE WHEN type = 'in' THEN amount ELSE -amount END"));

                        if ($targetPurchaserBal - $newAmount < -0.009 && ! $confirmShortfall) {
                            throw ValidationException::withMessages([
                                'amount' => 'Return amount exceeds purchaser available advance.',
                            ]);
                        }
                    }

                    $targetAccountId = ! empty($data['company_account_id'])
                        ? (int) $data['company_account_id']
                        : ($originalCredit->company_account_id ?: $oldCounterpartAccountId);

                    $targetAccount = CompanyAccount::query()->whereKey($targetAccountId)->where('enabled', true)->first();
                    $paymentSource = $data['payment_source'] ?? ($targetAccount?->account_type === 'cash' ? 'Cash' : 'Bank');

                    $replacementCredit = PurchaserCredit::query()->create([
                        'purchaser_id' => $targetPurchaserId,
                        'type' => $originalCredit->type,
                        'amount' => $newAmount,
                        'business_date' => $entryDate,
                        'payment_source' => $paymentSource,
                        'company_account_id' => $targetAccount?->id,
                        'reference' => $reference ?: "PURCH-FUND-REPL-{$originalCredit->id}",
                        'description' => $description ?: "Replacement funding for #{$originalCredit->id}",
                        'purchase_invoice_id' => null,
                        'created_by' => $actor->id,
                    ]);

                    $replacementJournal = $this->recordPurchaserCredit($replacementCredit);
                    $replacementJournal->update([
                        'entry_date' => $entryDate,
                        'reference' => $reference ?: "PURCH-FUND-REPL-{$originalCredit->id}",
                        'description' => ($description ?: "Replacement funding for #{$originalCredit->id}")." [Replacement for JE #{$journalEntry->id}]",
                    ]);

                    // If manual counterpart originally existed or account is provided, create and reconcile new counterpart
                    if ($hadManualCounterpart && $targetAccount instanceof CompanyAccount) {
                        $replacementStatement = app(CompanyPaymentReconciliationService::class)->createStatementEntry([
                            'company_account_id' => $targetAccount->id,
                            'transaction_date' => $entryDate,
                            'direction' => $originalCredit->type === 'in' ? 'out' : 'in',
                            'amount' => $newAmount,
                            'reference' => $reference ?: "CASH-{$replacementCredit->id}",
                            'narration' => $description ?: 'Cash funding replacement for purchaser '.$targetPurchaser->name,
                            'source' => 'manual',
                            'source_type' => PurchaserCredit::class,
                            'source_id' => $replacementCredit->id,
                            'notes' => "Replacement counterpart for corrected funding #{$originalCredit->id}. Reason: {$reason}",
                        ], $actor->id);

                        app(CompanyPaymentReconciliationService::class)->reconcileStatementJournal(
                            $replacementStatement,
                            $replacementJournal,
                            $newAmount,
                            $actor->id,
                        );
                    }
                } else {
                    $replacementLines = [];
                    $oldPrimary = (float) ($oldValues['primary_amount'] ?: 1);
                    $ratio = $oldPrimary > 0 ? ($newAmount / $oldPrimary) : 1.0;
                    foreach ($journalEntry->transactions as $line) {
                        $replacementLines[] = [
                            'account_id' => $line->account_id,
                            'type' => $line->type,
                            'amount' => round((float) $line->amount * $ratio, 2),
                        ];
                    }

                    $replacementData = new JournalEntryData(
                        entryDate: $entryDate,
                        reference: $reference ?: "REPL-JE-{$journalEntry->id}",
                        description: ($description ?: (string) $journalEntry->description)." [Replacement for JE #{$journalEntry->id}]",
                        lines: $replacementLines,
                        sourceType: $journalEntry->source_type,
                        sourceId: $journalEntry->source_id,
                        sourceEvent: $journalEntry->source_event ? "replacement:{$journalEntry->source_event}" : "replacement:{$journalEntry->id}",
                    );
                    $replacementJournal = $this->createEntry($replacementData, $actor->id);
                }
            }

            $newValues = [
                'amount' => $newAmount,
                'entry_date' => $entryDate,
                'reference' => $reference,
                'description' => $description,
                'purchaser_id' => $data['purchaser_id'] ?? null,
                'company_account_id' => $data['company_account_id'] ?? null,
                'reversal_journal_id' => $reversalEntry->id,
                'replacement_journal_id' => $replacementJournal?->id,
                'reason' => $reason,
                'ip' => $ipAddress,
            ];

            Log::info('Journal entry corrected by admin', [
                'original_journal_entry_id' => $journalEntry->id,
                'reversal_journal_entry_id' => $reversalEntry->id,
                'replacement_journal_entry_id' => $replacementJournal?->id,
                'actor_id' => $actor->id,
                'ip' => $ipAddress,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);

            if (function_exists('activity')) {
                activity('finance_journal')
                    ->causedBy($actor)
                    ->performedOn($journalEntry)
                    ->withProperties([
                        'action' => $newAmount > 0 ? 'correct_journal_entry' : 'cancel_journal_entry',
                        'original_journal_id' => $journalEntry->id,
                        'reversal_journal_id' => $reversalEntry->id,
                        'replacement_journal_id' => $replacementJournal?->id,
                        'old_values' => $oldValues,
                        'new_values' => $newValues,
                        'reason' => $reason,
                        'ip' => $ipAddress,
                    ])
                    ->log("Journal Entry #{$journalEntry->id} ({$journalEntry->formatted_reference}) ".($newAmount > 0 ? 'corrected' : 'cancelled')." by {$actor->name}. Reason: {$reason}");
            }

            return [
                'original' => $journalEntry->fresh(['transactions.account', 'statementEntries']),
                'reversal' => $reversalEntry->fresh(['transactions.account']),
                'replacement' => $replacementJournal?->fresh(['transactions.account', 'statementEntries']),
            ];
        });
    }

    /**
     * Create a balanced journal entry.
     */
    public function createEntry(JournalEntryData $data, int $userId): JournalEntry
    {
        $lines = $this->validatedLines($data->lines);

        return DB::transaction(function () use ($data, $lines, $userId): JournalEntry {
            $existingEntry = $this->entryForSource($data->sourceType, $data->sourceId, $data->sourceEvent);
            if ($existingEntry instanceof JournalEntry) {
                return $existingEntry;
            }

            /** @var JournalEntry $entry */
            $entry = $this->repository->create(array_merge($data->toArray(), [
                'created_by' => $userId,
            ]));

            foreach ($lines as $line) {
                $entry->transactions()->create([
                    'account_id' => $line['account_id'],
                    'type' => strtolower($line['type']),
                    'amount' => $line['amount'],
                ]);
            }

            return $entry->fresh('transactions.account');
        });
    }

    /**
     * Record sales invoice entry.
     * Debit AR (1100), Credit Sales Revenue (4100)
     */
    public function recordSalesInvoice(SalesInvoice $invoice): JournalEntry
    {
        $existingEntry = $this->entryForSource(SalesInvoice::class, $invoice->id, 'invoice')
            ?? $this->entryForReference($invoice->invoice_number);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $arAccountId = $this->getAccountIdByCode('1100');
        $salesAccountId = $this->getAccountIdByCode('4100');

        $lines = [
            ['account_id' => $arAccountId, 'type' => 'debit', 'amount' => (float) $invoice->amount],
            ['account_id' => $salesAccountId, 'type' => 'credit', 'amount' => (float) $invoice->amount],
        ];

        $data = new JournalEntryData(
            entryDate: $invoice->created_at->format('Y-m-d'),
            reference: $invoice->invoice_number,
            description: "Invoice generated for Sales Order #{$invoice->salesOrder->so_number}",
            lines: $lines,
            sourceType: SalesInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: 'invoice'
        );

        return $this->createEntry($data, $invoice->created_by);
    }

    /** Record a direct company receipt: debit cash/bank, credit sales revenue. */
    public function recordDirectCompanySale(DirectCompanySale $sale, CompanyAccount $companyAccount, int $userId): JournalEntry
    {
        $amount = round((float) $sale->amount, 2);
        if ($amount <= 0.0 || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
            throw new RuntimeException('Direct company sale requires a positive amount and enabled cash or bank account.');
        }

        return $this->createEntry(new JournalEntryData(
            entryDate: $sale->business_date->toDateString(),
            reference: $sale->reference ?: 'DIRECT-SALE-'.$sale->id,
            description: 'Direct company sale'.($sale->customer_name ? ' to '.$sale->customer_name : ''),
            lines: [
                ['account_id' => $companyAccount->account_type === 'cash' ? $this->getAccountIdByCode('1010') : $this->getAccountIdByCode('1020'), 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $this->getAccountIdByCode('4100'), 'type' => 'credit', 'amount' => $amount],
            ],
            sourceType: DirectCompanySale::class,
            sourceId: $sale->id,
            sourceEvent: 'direct-company-sale',
        ), $userId);
    }

    /**
     * Record payment received for sales invoice.
     * Debit Cash (1010) / Bank (1020), Credit AR (1100)
     */
    public function recordSalesPayment(Payment $payment): JournalEntry
    {
        $payment->load('salesInvoice');

        $existingEntry = $this->entryForSource(Payment::class, $payment->id, 'payment')
            ?? $this->entryForReference("PAY-{$payment->id}");
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $method = $payment->payment_method;
        $methodStr = $method instanceof \BackedEnum ? $method->value : ($method ?? 'cash');
        $cashMethod = strtolower((string) $methodStr) === 'cash';
        $debitAccountId = $cashMethod
            ? $this->getAccountIdByCode('1010')
            : $this->getAccountIdByCode('1020');

        $arAccountId = $this->getAccountIdByCode('1100');

        $lines = [
            ['account_id' => $debitAccountId, 'type' => 'debit', 'amount' => (float) $payment->amount],
            ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => (float) $payment->amount],
        ];

        $data = new JournalEntryData(
            entryDate: $payment->paid_at->format('Y-m-d'),
            reference: "PAY-{$payment->id}",
            description: "Payment received for Invoice #{$payment->salesInvoice->invoice_number}",
            lines: $lines,
            sourceType: Payment::class,
            sourceId: $payment->id,
            sourceEvent: 'payment'
        );

        return $this->createEntry($data, $payment->created_by);
    }

    /**
     * Record income received from a shop invoice.
     * Debit Cash (1010), Credit Sales Revenue (4100).
     */
    public function recordShopInvoicePayment(ShopInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null): JournalEntry
    {
        return $this->recordShopInvoicePaymentForMode($invoice, $amount, $userId, $sourceEvent);
    }

    public function recordShopInvoicePaymentForMode(ShopInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null, ?string $paymentMode = null): JournalEntry
    {
        $amount = round($amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Shop invoice payment journal amount must be positive.');
        }

        $cashAccountId = $this->cashAccountIdForPaymentMode($paymentMode);
        $salesAccountId = $this->getAccountIdByCode('4100');

        $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);
        $event = $sourceEvent ?? "payment:paid-{$paidAmountCents}";

        $lines = [
            ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $salesAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $invoice->business_date->format('Y-m-d'),
            reference: "SHOP-PAY-{$invoice->invoice_number}-{$paidAmountCents}",
            description: "Shop invoice payment approved for Invoice #{$invoice->invoice_number}",
            lines: $lines,
            sourceType: ShopInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: $event
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Record cash received against a shop/client balance without allocating an invoice.
     * Debit Cash (1010), Credit AR (1100).
     */
    public function recordShopClientBalancePayment(ShopInvoicePaymentRequest $paymentRequest, int|ShopInvoice $userIdOrReferenceInvoice, ?int $userId = null): JournalEntry
    {
        $paymentRequest->loadMissing('shop');
        $referenceInvoice = $userIdOrReferenceInvoice instanceof ShopInvoice ? $userIdOrReferenceInvoice : null;
        $userId = $referenceInvoice instanceof ShopInvoice ? (int) $userId : (int) $userIdOrReferenceInvoice;
        $amount = round((float) $paymentRequest->approved_amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Shop client balance payment journal amount must be positive.');
        }

        $event = 'client-balance-payment:'.$paymentRequest->id;
        $existingEntry = $this->entryForSource(ShopInvoicePaymentRequest::class, $paymentRequest->id, $event);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $cashAccountId = $this->cashAccountIdForPaymentMode($paymentRequest->payment_method);
        $arAccountId = $this->getAccountIdByCode('1100');

        $lines = [
            ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: ($referenceInvoice?->business_date ?? $paymentRequest->reviewed_at ?? now())->format('Y-m-d'),
            reference: "SHOP-CLIENT-BAL-{$paymentRequest->id}",
            description: 'Shop client balance payment received from '.($paymentRequest->shop?->name ?? 'shop'),
            lines: $lines,
            sourceType: ShopInvoicePaymentRequest::class,
            sourceId: $paymentRequest->id,
            sourceEvent: $event,
        );

        return $this->createEntry($data, $userId);
    }

    public function recordShopPaymentRequest(ShopInvoicePaymentRequest $paymentRequest, int $userId): JournalEntry
    {
        $paymentRequest->loadMissing('shop');
        $amount = round((float) $paymentRequest->requested_amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Shop payment request journal amount must be positive.');
        }

        $event = 'shop-payment-request:'.$paymentRequest->id;
        $existingEntry = $this->entryForSource(ShopInvoicePaymentRequest::class, $paymentRequest->id, $event);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $cashAccountId = $this->cashAccountIdForPaymentMode($paymentRequest->payment_method);
        $arAccountId = $this->getAccountIdByCode('1100');
        $businessDate = ($paymentRequest->payment_date ?? $paymentRequest->reviewed_at ?? now())->format('Y-m-d');

        $lines = [
            ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $businessDate,
            reference: 'SHOP-PAYMENT-'.$paymentRequest->id,
            description: 'Shop payment received from '.($paymentRequest->shop?->name ?? 'shop'),
            lines: $lines,
            sourceType: ShopInvoicePaymentRequest::class,
            sourceId: $paymentRequest->id,
            sourceEvent: $event,
        );

        return $this->createEntry($data, $userId);
    }

    public function recordShopCollection(ShopLedgerTransaction $transaction, CompanyAccount $companyAccount, int $userId, ?float $verifiedAmount = null): JournalEntry
    {
        $amount = round((float) ($verifiedAmount ?? $transaction->amount), 2);
        if ($amount <= 0.00 || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank', 'wallet'], true)) {
            throw new RuntimeException('Shop collection journal requires a positive amount and enabled company account.');
        }

        $reversalCount = JournalEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('source_event', 'like', 'reversal:%')
            ->count();

        $event = $reversalCount > 0
            ? 'shop-collection:'.$transaction->id.':v'.($reversalCount + 1)
            : 'shop-collection:'.$transaction->id;

        $existingEntry = $this->entryForSource(ShopLedgerTransaction::class, $transaction->id, $event);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $cashAccountId = $companyAccount->account_type === 'cash'
            ? $this->getAccountIdByCode('1010')
            : $this->getAccountIdByCode('1020');
        $arAccountId = $this->getAccountIdByCode('1100');

        $lines = $transaction->direction === 'income'
            ? [
                ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => $amount],
            ]
            : [
                ['account_id' => $arAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ];

        $data = new JournalEntryData(
            entryDate: $transaction->business_date->toDateString(),
            reference: $reversalCount > 0 ? 'SHOP-COLLECT-'.$transaction->id.'-V'.($reversalCount + 1) : 'SHOP-COLLECT-'.$transaction->id,
            description: 'Verified shop collection: '.($transaction->entryType?->name ?? 'Collection').' for '.($transaction->shop?->name ?? 'Shop #'.$transaction->shop_id),
            lines: $lines,
            sourceType: ShopLedgerTransaction::class,
            sourceId: $transaction->id,
            sourceEvent: $event,
        );

        return $this->createEntry($data, $userId);
    }

    public function recordShopCollectionReversal(ShopLedgerTransaction $transaction, CompanyAccount $companyAccount, int $userId, ?string $reason = null): JournalEntry
    {
        $amount = round((float) $transaction->amount, 2);
        if ($amount <= 0.00 || ! in_array($companyAccount->account_type, ['cash', 'bank', 'wallet'], true)) {
            throw new RuntimeException('Shop collection reversal journal requires a positive amount.');
        }

        $reversalCount = JournalEntry::where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('source_event', 'like', 'reversal:%')
            ->count();

        $event = $reversalCount > 0
            ? 'reversal:'.$transaction->id.':v'.($reversalCount + 1)
            : 'reversal:'.$transaction->id;

        $existingEntry = $this->entryForSource(ShopLedgerTransaction::class, $transaction->id, $event);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $cashAccountId = $companyAccount->account_type === 'cash'
            ? $this->getAccountIdByCode('1010')
            : $this->getAccountIdByCode('1020');
        $arAccountId = $this->getAccountIdByCode('1100');

        $lines = $transaction->direction === 'income'
            ? [
                ['account_id' => $arAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ]
            : [
                ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => $amount],
            ];

        $data = new JournalEntryData(
            entryDate: $transaction->business_date ? $transaction->business_date->toDateString() : now()->toDateString(),
            reference: $reversalCount > 0 ? 'REV-SHOP-COLLECT-'.$transaction->id.'-V'.($reversalCount + 1) : 'REV-SHOP-COLLECT-'.$transaction->id,
            description: trim('Reversal: '.($reason ?: 'Reversed verified shop collection #'.$transaction->id)),
            lines: $lines,
            sourceType: ShopLedgerTransaction::class,
            sourceId: $transaction->id,
            sourceEvent: $event,
        );

        return $this->createEntry($data, $userId);
    }

    public function recordCompanyAccountingEntry(CompanyAccountingEntry $entry, int $userId): JournalEntry
    {
        $entry->loadMissing('category.account');
        $amount = round((float) $entry->amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Company accounting journal amount must be positive.');
        }

        if (! $entry->category?->account instanceof Account) {
            throw new RuntimeException('Company accounting category must map to an active ledger account.');
        }

        $cashAccountId = $this->cashAccountIdForPaymentMode($entry->payment_mode);
        $categoryAccountId = (int) $entry->category->account->id;
        $lines = $entry->type === 'income'
            ? [
                ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $categoryAccountId, 'type' => 'credit', 'amount' => $amount],
            ]
            : [
                ['account_id' => $categoryAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ];

        $data = new JournalEntryData(
            entryDate: $entry->business_date->format('Y-m-d'),
            reference: $entry->reference ?: "MAIN-ACCOUNT-{$entry->id}",
            description: $entry->description ?: $entry->category->name,
            lines: $lines,
            sourceType: CompanyAccountingEntry::class,
            sourceId: $entry->id,
            sourceEvent: 'final',
        );

        return $this->createEntry($data, $userId);
    }

    public function recordCompanyAccountingReversal(CompanyAccountingEntry $entry, int $userId, ?string $note = null): JournalEntry
    {
        $entry->loadMissing('category.account');
        $amount = round((float) $entry->amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Company accounting reversal amount must be positive.');
        }

        if (! $entry->category?->account instanceof Account) {
            throw new RuntimeException('Company accounting category must map to an active ledger account.');
        }

        $cashAccountId = $this->cashAccountIdForPaymentMode($entry->payment_mode);
        $categoryAccountId = (int) $entry->category->account->id;
        $lines = $entry->type === 'income'
            ? [
                ['account_id' => $categoryAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ]
            : [
                ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $categoryAccountId, 'type' => 'credit', 'amount' => $amount],
            ];

        $data = new JournalEntryData(
            entryDate: now()->toDateString(),
            reference: "REV-MAIN-ACCOUNT-{$entry->id}",
            description: trim('Reversal: '.($note ?: $entry->description ?: $entry->category->name)),
            lines: $lines,
            sourceType: CompanyAccountingEntry::class,
            sourceId: $entry->id,
            sourceEvent: 'reversal',
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Purchaser credit (cash advance given to purchaser).
     * Debit Purchaser Advances (1300), Credit Cash (1010) / Bank (1020).
     */
    public function recordPurchaserCredit(PurchaserCredit $credit, bool $updateExisting = false): JournalEntry
    {
        $credit->loadMissing('purchaser');
        $amount = round((float) $credit->amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Purchaser funding journal amount must be greater than zero.');
        }

        $advanceAccountId = $this->getAccountIdByCode('1300');
        $cashAccountId = $this->cashAccountIdForPaymentMode($credit->payment_source);

        if ($credit->type === 'in') {
            $lines = [
                ['account_id' => $advanceAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ];
            $event = 'purchaser_funding';
            $desc = 'Company funding given to purchaser '.($credit->purchaser?->name ?? '#'.$credit->purchaser_id);
        } else {
            $lines = [
                ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $advanceAccountId, 'type' => 'credit', 'amount' => $amount],
            ];
            $event = 'purchaser_funding_return';
            $desc = 'Purchaser return of funding to company '.($credit->purchaser?->name ?? '#'.$credit->purchaser_id);
        }

        $data = new JournalEntryData(
            entryDate: $credit->business_date->format('Y-m-d'),
            reference: $credit->reference ?: "PURCH-FUND-{$credit->id}",
            description: $credit->description ?: $desc,
            lines: $lines,
            sourceType: PurchaserCredit::class,
            sourceId: $credit->id,
            sourceEvent: $event,
        );

        if ($updateExisting) {
            return DB::transaction(function () use ($data, $credit): JournalEntry {
                $lines = $this->validatedLines($data->lines);
                $entry = JournalEntry::query()
                    ->where('source_type', PurchaserCredit::class)
                    ->where('source_id', $credit->id)
                    ->whereIn('source_event', ['purchaser_funding', 'purchaser_funding_return'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $entry->update($data->toArray());
                $entry->transactions()->delete();
                $entry->transactions()->createMany($lines);

                return $entry->fresh('transactions.account');
            });
        }

        return $this->createEntry($data, (int) ($credit->created_by ?: 1));
    }

    /**
     * Record company money given to a shop's petty cash as an advance, never an expense.
     */
    public function recordShopPettyFunding(ShopLedgerTransaction $transaction, CompanyAccount $companyAccount, int $userId): JournalEntry
    {
        $amount = round((float) $transaction->amount, 2);

        if ($amount <= 0.0 || $transaction->funding_source !== 'company') {
            throw new RuntimeException('Shop petty funding journal requires a positive company-funded petty transaction.');
        }

        if (! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
            throw new RuntimeException('Selected company account is not available for petty funding.');
        }

        $advanceAccountId = $this->getAccountIdByCode('1500');
        $cashAccountId = $companyAccount->account_type === 'cash'
            ? $this->getAccountIdByCode('1010')
            : $this->getAccountIdByCode('1020');

        return $this->createEntry(new JournalEntryData(
            entryDate: $transaction->business_date->toDateString(),
            reference: 'SHOP-PETTY-'.$transaction->id,
            description: 'Company petty funding for shop #'.$transaction->shop_id,
            lines: [
                ['account_id' => $advanceAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ],
            sourceType: ShopLedgerTransaction::class,
            sourceId: $transaction->id,
            sourceEvent: 'shop_petty_funding',
        ), $userId);
    }

    /**
     * Record cash paid for purchaser daily purchase invoice.
     * Debit Graded Inventory (1200), Credit Purchaser Advances (1300).
     */
    public function recordPurchaserDailyPurchasePayment(PurchaseInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null, ?string $paymentMode = null): JournalEntry
    {
        $amount = round($amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Purchaser daily purchase payment journal amount must be positive.');
        }

        $invoice->loadMissing(['purchaserCart', 'supplier']);

        $inventoryAccountId = $this->getAccountIdByCode('1200');
        $advanceAccountId = $this->getAccountIdByCode('1300');
        $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);
        $event = $sourceEvent ?? "purchaser_daily_purchase_payment:paid-{$paidAmountCents}";
        $businessDate = $invoice->purchaserCart?->business_date?->format('Y-m-d')
            ?? $invoice->created_at?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $lines = [
            ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $advanceAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $businessDate,
            reference: "PURCH-DAILY-PAY-{$invoice->id}-{$paidAmountCents}",
            description: 'Purchaser daily purchase payment for invoice #'.($invoice->invoice_number ?: $invoice->id),
            lines: $lines,
            sourceType: PurchaseInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: $event
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Record cash paid for Green Leaf direct purchase invoice.
     * Debit Graded Inventory (1200), Credit Cash (1010).
     */
    public function recordGreenLeafDirectPurchasePayment(PurchaseInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null, ?string $paymentMode = null): JournalEntry
    {
        $amount = round($amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Direct purchase payment journal amount must be positive.');
        }

        $invoice->loadMissing(['purchaserCart', 'supplier']);

        $inventoryAccountId = $this->getAccountIdByCode('1200');
        $cashAccountId = $this->cashAccountIdForPaymentMode($paymentMode ?? $invoice->payment_method);
        $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);
        $event = $sourceEvent ?? "green_leaf_direct_purchase_payment:paid-{$paidAmountCents}";
        $businessDate = $invoice->purchaserCart?->business_date?->format('Y-m-d')
            ?? $invoice->created_at?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $lines = [
            ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $businessDate,
            reference: "GL-DIRECT-PAY-{$invoice->id}-{$paidAmountCents}",
            description: 'Green Leaf Direct Purchase payment for invoice #'.($invoice->invoice_number ?: $invoice->id),
            lines: $lines,
            sourceType: PurchaseInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: $event
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Record company cash/bank paid to settle vendor credit.
     * Debit Accounts Payable (2100), Credit Cash (1010) / Bank (1020).
     */
    public function recordCompanyVendorCreditPayment(PurchaseInvoice $invoice, float $amount, int $userId, ?string $sourceEvent = null, ?string $paymentMode = null): JournalEntry
    {
        $amount = round($amount, 2);

        if ($amount <= 0.00) {
            throw new RuntimeException('Company vendor credit payment journal amount must be positive.');
        }

        $invoice->loadMissing(['purchaserCart', 'supplier']);

        $payableAccountId = $this->getAccountIdByCode('2100');
        $cashAccountId = $this->cashAccountIdForPaymentMode($paymentMode ?? $invoice->payment_method);
        $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);
        $event = $sourceEvent ?? "company_vendor_credit_payment:paid-{$paidAmountCents}";
        $businessDate = $invoice->purchaserCart?->business_date?->format('Y-m-d')
            ?? $invoice->created_at?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $lines = [
            ['account_id' => $payableAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $businessDate,
            reference: "COMPANY-VENDOR-CREDIT-PAY-{$invoice->id}-{$paidAmountCents}",
            description: 'Company paid vendor credit invoice #'.($invoice->invoice_number ?: $invoice->id),
            lines: $lines,
            sourceType: PurchaseInvoice::class,
            sourceId: $invoice->id,
            sourceEvent: $event
        );

        return $this->createEntry($data, $userId);
    }

    public function recordVendorSettlement(VendorSettlement $settlement, int $userId): JournalEntry
    {
        $settlement->loadMissing('supplier');
        $cash = round((float) $settlement->actual_payment_amount, 2);
        $discount = round((float) $settlement->settlement_discount_amount, 2);
        $advanceUsed = round((float) $settlement->vendor_advance_used_amount, 2);
        $newAdvance = round((float) $settlement->new_vendor_advance_amount, 2);
        $payable = round($cash + $discount + $advanceUsed - $newAdvance, 2);

        if ($payable <= 0.0) {
            throw new RuntimeException('Vendor settlement must settle a positive payable amount.');
        }

        $lines = [
            ['account_id' => $this->getAccountIdByCode('2100'), 'type' => 'debit', 'amount' => $payable],
        ];
        if ($newAdvance > 0.0) {
            $lines[] = ['account_id' => $this->getAccountIdByCode('1400'), 'type' => 'debit', 'amount' => $newAdvance];
        }
        if ($cash > 0.0) {
            $lines[] = ['account_id' => $this->cashAccountIdForPaymentMode($settlement->payment_method), 'type' => 'credit', 'amount' => $cash];
        }
        if ($discount > 0.0) {
            $lines[] = ['account_id' => $this->getAccountIdByCode('4200'), 'type' => 'credit', 'amount' => $discount];
        }
        if ($advanceUsed > 0.0) {
            $lines[] = ['account_id' => $this->getAccountIdByCode('1400'), 'type' => 'credit', 'amount' => $advanceUsed];
        }

        return $this->createEntry(new JournalEntryData(
            entryDate: $settlement->payment_date->toDateString(),
            reference: 'VENDOR-SETTLEMENT-'.$settlement->id,
            description: 'Vendor settlement for '.($settlement->supplier?->name ?? 'supplier'),
            lines: $lines,
            sourceType: VendorSettlement::class,
            sourceId: $settlement->id,
            sourceEvent: 'vendor-settlement:'.$settlement->id,
        ), $userId);
    }

    /**
     * Record wastage expense entry.
     * Debit Wastage Expense (5200), Credit COGS (5100)
     */
    public function recordWastage(WastageEntry $wastage): ?JournalEntry
    {
        $amount = round((float) $wastage->quantity * (float) $wastage->cost_per_kg, 2);

        if ($amount <= 0) {
            activity()
                ->performedOn($wastage)
                ->event('wastage.journal_skipped')
                ->withProperties([
                    'reason' => 'Zero or unavailable unit cost; financial journal entry skipped.',
                    'quantity' => (float) $wastage->quantity,
                    'cost_per_kg' => (float) $wastage->cost_per_kg,
                ])
                ->log('Wastage journal entry skipped due to zero cost.');

            return null;
        }

        $wastage->load('product');

        $wastageAccountId = $this->getAccountIdByCode('5200');
        $cogsAccountId = $this->getAccountIdByCode('5100');

        $lines = [
            ['account_id' => $wastageAccountId, 'type' => 'debit', 'amount' => $amount],
            ['account_id' => $cogsAccountId, 'type' => 'credit', 'amount' => $amount],
        ];

        $data = new JournalEntryData(
            entryDate: $wastage->wastage_date->format('Y-m-d'),
            reference: "WASTE-{$wastage->id}",
            description: "Wastage logged: {$wastage->quantity} kg of {$wastage->product->name} (Grade {$wastage->grade->value}) - Reason: {$wastage->reason->value}",
            lines: $lines,
            sourceType: WastageEntry::class,
            sourceId: $wastage->id,
            sourceEvent: 'wastage'
        );

        return $this->createEntry($data, $wastage->recorded_by);
    }

    /**
     * Record Goods Received Note entry upon approval.
     * Debit Graded Inventory (1200), Credit Goods Received Not Invoiced (2150)
     */
    public function recordGoodsReceipt(GoodsReceived $grn): ?JournalEntry
    {
        $grn->load(['purchaseOrder.supplier', 'items.purchaseOrderItem']);

        $existingEntry = $this->entryForSource(GoodsReceived::class, $grn->id, 'receipt')
            ?? $this->entryForReference($grn->grn_number);
        if ($existingEntry instanceof JournalEntry) {
            return $existingEntry;
        }

        $inventoryAccountId = $this->getAccountIdByCode('1200');
        $grniAccountId = $this->getAccountIdByCode('2150');

        // Calculate material cost of received items
        $materialCost = 0.00;
        foreach ($grn->items as $item) {
            $unitPrice = $item->purchaseOrderItem ? (float) $item->purchaseOrderItem->unit_price : 0.00;
            $materialCost += (float) $item->received_qty * $unitPrice;
        }
        $materialCost = round($materialCost, 2);

        if ($materialCost <= 0) {
            return null;
        }

        $lines = [
            ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $materialCost],
            ['account_id' => $grniAccountId, 'type' => 'credit', 'amount' => $materialCost],
        ];

        $userId = auth()->id() ?? User::first()?->id ?? 1;

        $supplierName = $grn->purchaseOrder?->supplier?->name ?? 'Advance Goods Receipt';

        $data = new JournalEntryData(
            entryDate: $grn->received_at->format('Y-m-d'),
            reference: $grn->grn_number,
            description: "Goods Received Note #{$grn->grn_number} approved for: {$supplierName}",
            lines: $lines,
            sourceType: GoodsReceived::class,
            sourceId: $grn->id,
            sourceEvent: 'receipt'
        );

        return $this->createEntry($data, (int) $userId);
    }

    /**
     * Debit expense (5900), Credit Company Payable to Shops (2200).
     */
    public function recordCompanyPayableApproval(ShopAccountingEntryLine $line, int $userId, ?string $sourceEvent = null): JournalEntry
    {
        $amount = round((float) ($line->company_approved_amount ?? $line->company_payable_amount ?? $line->amount), 2);
        if ($amount <= 0) {
            throw new RuntimeException('Company payable approval amount must be positive.');
        }

        $expenseAccountId = $this->getAccountIdByCode('5900');
        $payableAccountId = $this->getAccountIdByCode('2200');
        $event = $sourceEvent ?? 'company-payable-approved';

        $data = new JournalEntryData(
            entryDate: now()->toDateString(),
            reference: 'CP-APPR-'.$line->id,
            description: 'Company payable approved for shop expense line #'.$line->id,
            lines: [
                ['account_id' => $expenseAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $payableAccountId, 'type' => 'credit', 'amount' => $amount],
            ],
            sourceType: ShopAccountingEntryLine::class,
            sourceId: $line->id,
            sourceEvent: $event,
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Debit Company Payable to Shops (2200), Credit Accounts Receivable (1100).
     */
    public function recordCompanyPayableAdjustment(CompanyPayableSettlement $settlement, int $userId): JournalEntry
    {
        $amount = round((float) $settlement->amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Company payable adjustment amount must be positive.');
        }

        $payableAccountId = $this->getAccountIdByCode('2200');
        $arAccountId = $this->getAccountIdByCode('1100');

        $data = new JournalEntryData(
            entryDate: $settlement->settlement_date?->toDateString() ?? now()->toDateString(),
            reference: 'CP-ADJ-'.$settlement->id,
            description: 'Company payable adjusted against shop receivable #'.$settlement->shop_accounting_entry_line_id,
            lines: [
                ['account_id' => $payableAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $arAccountId, 'type' => 'credit', 'amount' => $amount],
            ],
            sourceType: CompanyPayableSettlement::class,
            sourceId: $settlement->id,
            sourceEvent: 'adjust_against_shop_payment',
        );

        return $this->createEntry($data, $userId);
    }

    /**
     * Debit Company Payable to Shops (2200), Credit Cash/Bank.
     */
    public function recordCompanyPayableDirectPayment(CompanyPayableSettlement $settlement, int $userId, ?string $paymentMode = null): JournalEntry
    {
        $amount = round((float) $settlement->amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Company payable direct payment amount must be positive.');
        }

        $payableAccountId = $this->getAccountIdByCode('2200');
        $cashAccountId = $settlement->payment_account_id
            ? (int) $settlement->payment_account_id
            : $this->cashAccountIdForPaymentMode($paymentMode);

        $data = new JournalEntryData(
            entryDate: $settlement->settlement_date?->toDateString() ?? now()->toDateString(),
            reference: 'CP-PAY-'.$settlement->id,
            description: 'Direct company payment for payable line #'.$settlement->shop_accounting_entry_line_id,
            lines: [
                ['account_id' => $payableAccountId, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $amount],
            ],
            sourceType: CompanyPayableSettlement::class,
            sourceId: $settlement->id,
            sourceEvent: 'direct_company_payment',
        );

        return $this->createEntry($data, $userId);
    }

    private function getAccountIdByCode(string $code): int
    {
        $account = Account::query()->where('code', $code)->where('is_active', true)->first();

        if (! $account) {
            $defaultAccount = ChartOfAccounts::find($code);

            if ($defaultAccount !== null) {
                $account = Account::query()->updateOrCreate(
                    ['code' => $defaultAccount['code']],
                    [
                        'name' => $defaultAccount['name'],
                        'type' => $defaultAccount['type'],
                        'is_active' => $defaultAccount['is_active'],
                        'parent_id' => $defaultAccount['parent_id'],
                    ],
                );
            }
        }

        if (! $account) {
            throw new RuntimeException("Chart of Accounts is missing account code: {$code}. Please seed the Chart of Accounts.");
        }

        return (int) $account->id;
    }

    private function cashAccountIdForPaymentMode(?string $paymentMode): int
    {
        $mode = str((string) ($paymentMode ?: 'cash'))->lower()->replace([' ', '-'], '_')->toString();

        return match ($mode) {
            'cash' => $this->getAccountIdByCode('1010'),
            default => $this->getAccountIdByCode('1020'),
        };
    }

    private function entryForReference(string $reference): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('reference', $reference)
            ->with('transactions.account')
            ->first();
    }

    private function entryForSource(?string $sourceType, ?int $sourceId, ?string $sourceEvent): ?JournalEntry
    {
        if ($sourceType === null || $sourceId === null || $sourceEvent === null) {
            return null;
        }

        return JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('source_event', $sourceEvent)
            ->with('transactions.account')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{account_id:int, type:string, amount:float}>
     */
    private function validatedLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new RuntimeException('A journal entry must contain at least one debit and one credit line.');
        }

        $normalizedLines = [];
        $accountIds = [];
        $debitCents = 0;
        $creditCents = 0;

        foreach ($lines as $index => $line) {
            if (! is_array($line) || ! isset($line['account_id'], $line['type'], $line['amount'])) {
                throw new RuntimeException("Malformed journal line at index {$index}.");
            }

            $accountId = filter_var($line['account_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $type = strtolower(trim((string) $line['type']));
            $amount = is_numeric($line['amount']) ? (float) $line['amount'] : null;

            if ($accountId === false || ! in_array($type, ['debit', 'credit'], true)) {
                throw new RuntimeException("Invalid journal line at index {$index}.");
            }

            if ($amount === null || ! is_finite($amount) || $amount <= 0) {
                throw new RuntimeException("Journal line {$index} must have a positive amount.");
            }

            $amountCents = (int) round($amount * 100);
            if ($amountCents <= 0 || abs(($amount * 100) - $amountCents) > 0.0001) {
                throw new RuntimeException("Journal line {$index} amount must have no more than two decimal places.");
            }

            $accountIds[] = $accountId;
            $normalizedLines[] = [
                'account_id' => $accountId,
                'type' => $type,
                'amount' => round($amountCents / 100, 2),
            ];

            if ($type === 'debit') {
                $debitCents += $amountCents;
            } else {
                $creditCents += $amountCents;
            }
        }

        $activeAccountCount = Account::query()
            ->whereIn('id', array_unique($accountIds))
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])
            ->count();

        if ($activeAccountCount !== count(array_unique($accountIds))) {
            throw new RuntimeException('Journal entries may only use existing active accounts.');
        }

        if ($debitCents === 0 || $creditCents === 0 || $debitCents !== $creditCents) {
            throw new RuntimeException("Unbalanced journal entry. Debits ({$debitCents}) must equal Credits ({$creditCents}).");
        }

        return $normalizedLines;
    }
}
