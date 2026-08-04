@php($cutoffLabel = app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->cutoffLabel())

@if (($deadlineAlert['show'] ?? false) === true)
    <section class="rounded-xl border border-rose-200 bg-rose-50/90 px-3 py-1.5 shadow-2xs">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0 truncate">
                <span class="inline-flex shrink-0 items-center rounded bg-rose-200/70 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-rose-800">
                    Action Required
                </span>
                <span class="truncate font-bold text-rose-950 text-xs">
                    @if (($deadlineAlert['credit_overdue_count'] ?? 0) > 0 && ($deadlineAlert['payment_overdue_count'] ?? 0) === 0)
                        {{ $deadlineAlert['credit_overdue_count'] }} supplier credit follow-up pending.
                    @elseif (($deadlineAlert['overdue_count'] ?? 0) > 0)
                        {{ $deadlineAlert['overdue_count'] }} overdue carts pending.
                    @else
                        Resolve carts before {{ $cutoffLabel }}.
                    @endif
                </span>
                @if (($deadlineAlert['bill_pending_count'] ?? 0) > 0)
                    <span class="hidden sm:inline-flex shrink-0 items-center rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                        Bill pending: {{ $deadlineAlert['bill_pending_count'] }}
                    </span>
                @endif
            </div>

            <a href="{{ route('purchaser.suppliers', ['date' => $deadlineAlert['operational_date'] ?? request('date')]) }}" class="inline-flex h-7 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-rose-700 px-2.5 text-[10px] font-black text-white hover:bg-rose-600 transition-all">
                <span>Vendor Hub</span>
                @if (($deadlineAlert['pending_total_count'] ?? 0) > 0)
                    <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-white px-1 text-[9px] font-black text-rose-700">
                        {{ $deadlineAlert['pending_total_count'] }}
                    </span>
                @endif
            </a>
        </div>
    </section>
@endif
