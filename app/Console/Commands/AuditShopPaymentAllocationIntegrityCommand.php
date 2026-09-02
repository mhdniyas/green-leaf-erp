<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditShopPaymentAllocationIntegrityCommand extends Command
{
    protected $signature = 'cashbook:audit-payment-allocation-integrity
        {--month= : Filter payment_date month (YYYY-MM)}
        {--shop-id= : Filter by shop id}
        {--only-errors : Show only ALLOCATION_ERROR / OVER_ALLOCATED / DUPLICATE_ALLOCATION rows}';

    protected $description = 'Read-only audit for payment allocation integrity, including mismatched states, duplicates, and over-allocation.';

    public function handle(): int
    {
        $month = $this->option('month');
        $shopId = $this->option('shop-id');
        $onlyErrors = (bool) $this->option('only-errors');

        if (filled($month)) {
            try {
                Carbon::createFromFormat('Y-m', (string) $month);
            } catch (\Throwable) {
                $this->error('Invalid --month format. Use YYYY-MM.');

                return self::FAILURE;
            }
        }

        $allocSums = DB::table('shop_payment_ledger_allocations')
            ->selectRaw('payment_request_id, SUM(amount) as actual_allocated')
            ->groupBy('payment_request_id');

        $dupGroups = DB::table('shop_payment_ledger_allocations')
            ->selectRaw('payment_request_id, shop_ledger_transaction_id, COUNT(*) as row_count')
            ->groupBy('payment_request_id', 'shop_ledger_transaction_id')
            ->havingRaw('COUNT(*) > 1');

        $dupCounts = DB::query()
            ->fromSub($dupGroups, 'dup')
            ->selectRaw('payment_request_id, SUM(row_count - 1) as duplicate_allocation_count')
            ->groupBy('payment_request_id');

        $query = DB::table('shop_invoice_payment_requests as p')
            ->join('shops as s', 's.id', '=', 'p.shop_id')
            ->leftJoinSub($allocSums, 'alloc', fn ($join) => $join->on('alloc.payment_request_id', '=', 'p.id'))
            ->leftJoinSub($dupCounts, 'dup', fn ($join) => $join->on('dup.payment_request_id', '=', 'p.id'))
            ->select([
                'p.id as payment_id',
                's.name as shop',
                'p.payment_date',
                'p.status as payment_status',
                'p.reconciliation_status',
                'p.cheque_status',
            ])
            ->selectRaw('ROUND(CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END, 2) as payment_amount')
            ->selectRaw('ROUND(COALESCE(alloc.actual_allocated, 0), 2) as actual_allocated_amount')
            ->selectRaw('ROUND(CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END - COALESCE(alloc.actual_allocated, 0), 2) as actual_remaining_amount')
            ->selectRaw('ROUND(GREATEST(COALESCE(alloc.actual_allocated, 0) - (CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END), 0), 2) as over_allocation_amount')
            ->selectRaw('COALESCE(dup.duplicate_allocation_count, 0) as duplicate_allocation_count')
            ->selectRaw("CASE WHEN p.cheque_status = 'pending' THEN 'PENDING_CHEQUE' WHEN ROUND(COALESCE(p.requested_amount, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) + 0.01 >= ROUND(COALESCE(p.requested_amount, 0), 2) THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END as stored_allocation_status")
            ->selectRaw("CASE WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND((CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END) - COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END as actual_allocation_status")
            ->selectRaw("CASE WHEN COALESCE(dup.duplicate_allocation_count, 0) > 0 THEN 'DUPLICATE_ALLOCATION' WHEN COALESCE(alloc.actual_allocated, 0) > (CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END) + 0.01 THEN 'OVER_ALLOCATED' WHEN p.cheque_status <> 'pending' AND (CASE WHEN ROUND(COALESCE(p.requested_amount, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) + 0.01 >= ROUND(COALESCE(p.requested_amount, 0), 2) THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END) <> (CASE WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND((CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END) - COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END) THEN 'ALLOCATION_ERROR' ELSE (CASE WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND((CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END) - COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END) END as integrity_flag")
            ->orderBy('p.payment_date')
            ->orderBy('p.id');

        if (filled($month)) {
            $start = Carbon::createFromFormat('Y-m', (string) $month)->startOfMonth()->toDateString();
            $end = Carbon::createFromFormat('Y-m', (string) $month)->endOfMonth()->toDateString();
            $query->whereBetween('p.payment_date', [$start, $end]);
        }

        if (filled($shopId)) {
            $query->where('p.shop_id', (int) $shopId);
        }

        if ($onlyErrors) {
            $query->where(function (Builder $inner): void {
                $inner
                    ->whereRaw('COALESCE(dup.duplicate_allocation_count, 0) > 0')
                    ->orWhereRaw('COALESCE(alloc.actual_allocated, 0) > (CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END) + 0.01')
                    ->orWhereRaw("p.cheque_status <> 'pending' AND (CASE WHEN ROUND(COALESCE(p.requested_amount, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) + 0.01 >= ROUND(COALESCE(p.requested_amount, 0), 2) THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END) <> (CASE WHEN ROUND(COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'OK' WHEN ROUND((CASE WHEN COALESCE(p.reconciled_amount, 0) > 0.01 THEN p.reconciled_amount WHEN COALESCE(p.approved_amount, 0) > 0.01 THEN p.approved_amount ELSE p.requested_amount END) - COALESCE(alloc.actual_allocated, 0), 2) <= 0.01 THEN 'FULLY_ALLOCATED' ELSE 'PARTIALLY_ALLOCATED' END)");
            });
        }

        $rows = $query->limit(1000)->get();

        if ($rows->isEmpty()) {
            $this->info('No payment rows matched the selected filters.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'Payment ID',
                'Shop',
                'Payment Date',
                'Payment Amount',
                'Stored Status',
                'Actual Allocated',
                'Actual Remaining',
                'Integrity Flag',
                'Duplicate Count',
                'Over Alloc',
            ],
            $rows->map(fn (object $row): array => [
                $row->payment_id,
                $row->shop,
                $row->payment_date,
                number_format((float) $row->payment_amount, 2, '.', ''),
                $row->stored_allocation_status,
                number_format((float) $row->actual_allocated_amount, 2, '.', ''),
                number_format((float) $row->actual_remaining_amount, 2, '.', ''),
                $row->integrity_flag,
                (int) $row->duplicate_allocation_count,
                number_format((float) $row->over_allocation_amount, 2, '.', ''),
            ])->all()
        );

        $summary = [
            'rows' => $rows->count(),
            'ok' => $rows->where('integrity_flag', 'OK')->count(),
            'partially_allocated' => $rows->where('integrity_flag', 'PARTIALLY_ALLOCATED')->count(),
            'fully_allocated' => $rows->where('integrity_flag', 'FULLY_ALLOCATED')->count(),
            'allocation_error' => $rows->where('integrity_flag', 'ALLOCATION_ERROR')->count(),
            'over_allocated' => $rows->where('integrity_flag', 'OVER_ALLOCATED')->count(),
            'duplicate_allocation' => $rows->where('integrity_flag', 'DUPLICATE_ALLOCATION')->count(),
        ];

        $this->info(sprintf(
            'Audited %d payments. OK: %d | Partial: %d | Full: %d | Errors: %d | Over: %d | Duplicates: %d',
            $summary['rows'],
            $summary['ok'],
            $summary['partially_allocated'],
            $summary['fully_allocated'],
            $summary['allocation_error'],
            $summary['over_allocated'],
            $summary['duplicate_allocation']
        ));

        return self::SUCCESS;
    }
}
