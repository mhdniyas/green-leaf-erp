<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cashbook\InvoiceCashbookProjectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcileCashbookInvoicesCommand extends Command
{
    protected $signature = 'cashbook:reconcile-invoices
        {--date= : Single invoice business date to check, YYYY-MM-DD}
        {--from= : First invoice business date to check, YYYY-MM-DD}
        {--to= : Last invoice business date to check, YYYY-MM-DD}
        {--apply : Apply corrections instead of reporting only}
        {--user-id= : User id to stamp on created or voided rows}';

    protected $description = 'Audit and optionally repair cashbook purchase bill projections for shop invoices.';

    public function handle(InvoiceCashbookProjectionService $projectionService): int
    {
        [$from, $to] = $this->dateRange();
        $apply = (bool) $this->option('apply');
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;

        $summary = $projectionService->reconcile($from, $to, $apply, $userId);

        if ($summary['mismatches'] !== []) {
            $this->table(
                [
                    'Invoice ID',
                    'Invoice No',
                    'Shop ID',
                    'Date',
                    'Invoice Total',
                    'Cashbook Tx',
                    'Cashbook Amount',
                    'Cashbook Status',
                    'Expected Status',
                ],
                array_map(fn (array $row): array => [
                    $row['invoice_id'],
                    $row['invoice_number'],
                    $row['shop_id'],
                    $row['business_date'],
                    number_format((float) $row['invoice_final_total'], 2, '.', ''),
                    $row['cashbook_transaction_id'] ?? 'missing',
                    $row['cashbook_amount'] === null ? 'missing' : number_format((float) $row['cashbook_amount'], 2, '.', ''),
                    $row['cashbook_status'] ?? 'missing',
                    $row['expected_status'],
                ], $summary['mismatches'])
            );
        }

        $this->info(sprintf(
            'Checked %d invoice(s). Mismatches: %d. Created: %d. Updated: %d. Voided: %d. Unchanged: %d. Failed: %d. Mode: %s.',
            $summary['checked'],
            count($summary['mismatches']),
            $summary['created'],
            $summary['updated'],
            $summary['voided'],
            $summary['unchanged'],
            $summary['failed'],
            $apply ? 'apply' : 'report-only'
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function dateRange(): array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date) {
            return [Carbon::parse($date)->toDateString(), Carbon::parse($date)->toDateString()];
        }

        if (! $from && ! $to) {
            return [null, null];
        }

        $startDate = Carbon::parse($from ?: $to)->toDateString();
        $endDate = Carbon::parse($to ?: $from)->toDateString();

        if ($startDate > $endDate) {
            return [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }
}
