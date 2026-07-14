@php($cutoffLabel = app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->cutoffLabel())

@if (($deadlineAlert['show'] ?? false) === true)
    <section class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 shadow-sm lg:rounded-[2rem]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Purchaser Action Required</p>
                <p class="mt-1 text-sm font-black text-rose-900">
                    @if (($deadlineAlert['overdue_count'] ?? 0) > 0)
                        {{ $deadlineAlert['overdue_count'] }} overdue carts from older business dates still need warehouse confirmation or payment follow-up before the active purchase day stays clean.
                    @else
                        Resolve carts before the {{ $cutoffLabel }} business-day rollover.
                    @endif
                </p>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-bold text-rose-800">
                    @if (($deadlineAlert['vendor_missing_count'] ?? 0) > 0)
                        <span class="rounded-full bg-white px-3 py-1">Vendor pending: {{ $deadlineAlert['vendor_missing_count'] }}</span>
                    @endif
                    @if (($deadlineAlert['bill_pending_count'] ?? 0) > 0)
                        <span class="rounded-full bg-white px-3 py-1">Bill pending: {{ $deadlineAlert['bill_pending_count'] }}</span>
                    @endif
                    @if (($deadlineAlert['overdue_count'] ?? 0) > 0)
                        <span class="rounded-full bg-white px-3 py-1">Overdue: {{ $deadlineAlert['overdue_count'] }}</span>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('purchaser.suppliers', ['date' => $deadlineAlert['operational_date'] ?? request('date')]) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-rose-700 px-4 text-xs font-black text-white hover:bg-rose-600">
                    <span>Vendor Hub</span>
                    @if (($deadlineAlert['pending_total_count'] ?? 0) > 0)
                        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-white px-1.5 py-0.5 text-[10px] font-black text-rose-700">
                            {{ $deadlineAlert['pending_total_count'] }}
                        </span>
                    @endif
                </a>
            </div>
        </div>
    </section>
@endif
