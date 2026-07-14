<x-layouts.admin title="Daily Operational Progress">
    @php
        $carbonDate = \Illuminate\Support\Carbon::parse($date);
        $prevDate = $carbonDate->copy()->subDay()->format('Y-m-d');
        $nextDate = $carbonDate->copy()->addDay()->format('Y-m-d');
        $todayDate = \Illuminate\Support\Carbon::today()->format('Y-m-d');
        $toneClasses = [
            'slate' => 'border-slate-200 bg-white text-slate-900',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-900',
            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-900',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-900',
            'teal' => 'border-teal-200 bg-teal-50 text-teal-900',
            'rose' => 'border-rose-200 bg-rose-50 text-rose-900',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.16),_transparent_34%),linear-gradient(135deg,_#0f172a_0%,_#111827_45%,_#1e293b_100%)] p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.3em] text-sky-200">Operations Flow</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Daily Progress Across All Shops</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                        One board for the full day: purchase intake, warehouse readiness, order approvals, orders out for delivery, completed deliveries, and any flagged discrepancies.
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.daily-progress') }}" class="flex flex-wrap items-center gap-2 self-start rounded-2xl border border-white/15 bg-white/10 p-2 backdrop-blur">
                    <a href="{{ route('admin.daily-progress', ['date' => $prevDate]) }}" class="rounded-xl border border-white/10 bg-white px-3 py-2 text-slate-700 transition hover:bg-slate-50" title="Previous Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                    </a>

                    <input
                        type="date"
                        name="date"
                        value="{{ $date }}"
                        onchange="this.form.submit()"
                        class="rounded-xl border border-white/10 bg-white px-3 py-2 text-sm font-black text-slate-800 focus:border-sky-300 focus:outline-none focus:ring-0"
                    >

                    @if($date !== $todayDate)
                        <a href="{{ route('admin.daily-progress', ['date' => $todayDate]) }}" class="rounded-xl border border-white/10 bg-white px-3 py-2 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-50">
                            Today
                        </a>
                    @endif

                    <a href="{{ route('admin.daily-progress', ['date' => $nextDate]) }}" class="rounded-xl border border-white/10 bg-white px-3 py-2 text-slate-700 transition hover:bg-slate-50" title="Next Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Shops Active</p>
                    <p class="mt-3 text-3xl font-black text-white">{{ $shopsWithOrdersCount }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-300">of {{ count($shopProgressRows) }} shops have orders on {{ $carbonDate->format('d M Y') }}</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Orders</p>
                    <p class="mt-3 text-3xl font-black text-white">{{ $totalOrdersCount }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-300">{{ $approvedOrdersCount }} approved and {{ $outForDeliveryOrdersCount }} out for delivery</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Delivered</p>
                    <p class="mt-3 text-3xl font-black text-white">{{ $deliveredOrdersCount }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-300">Cash collected Rs. {{ number_format($totalCashCollected, 2) }}</p>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-300">Flags</p>
                    <p class="mt-3 text-3xl font-black text-white">{{ $discrepancyOrdersCount }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-300">Shortage Rs. {{ number_format($totalShortagesValue, 2) }} · Variance Rs. {{ number_format($totalCashDiscrepancies, 2) }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-7">
            @foreach($flowStages as $stage)
                <div class="{{ $toneClasses[$stage['tone']] ?? $toneClasses['slate'] }} rounded-[1.75rem] border p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] opacity-70">{{ $stage['label'] }}</p>
                    <p class="mt-3 text-3xl font-black">{{ $stage['count'] }}</p>
                    <p class="mt-2 text-xs font-semibold opacity-80">{{ $stage['meta'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-6 py-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-black tracking-tight text-slate-900">Shop Delivery Progress</h2>
                    <p class="mt-1 text-sm text-slate-500">Every active shop is listed, including shops with no order for the selected date.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-[11px] font-bold text-slate-500">
                    <span class="rounded-full bg-slate-100 px-3 py-1">Pending batches: {{ $pendingBatchesCount }}</span>
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-700">Flagged orders: {{ $discrepancyOrdersCount }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead class="bg-slate-50/70 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Shop</th>
                            <th class="px-4 py-4 text-center">Orders</th>
                            <th class="px-4 py-4 text-center">Approved</th>
                            <th class="px-4 py-4 text-center">Out For Delivery</th>
                            <th class="px-4 py-4 text-center">Delivered</th>
                            <th class="px-4 py-4 text-center">Flag</th>
                            <th class="px-4 py-4">Status</th>
                            <th class="px-4 py-4 text-right">Shortage</th>
                            <th class="px-6 py-4 text-right">Cash Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                        @foreach($shopProgressRows as $row)
                            @php
                                $hasFlag = $row['discrepancy_orders'] > 0;
                                $variance = (float) $row['cash_discrepancy_total'];
                                $progressWidth = max(6, $row['progress_percent']);
                            @endphp
                            <tr class="align-top transition hover:bg-slate-50/70">
                                <td class="px-6 py-5">
                                    <div class="flex min-w-[220px] flex-col gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-900">{{ $row['shop']->name }}</p>
                                            <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $row['shop']->code }}</p>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                @class([
                                                    'h-full rounded-full',
                                                    'bg-rose-500' => $hasFlag,
                                                    'bg-teal-500' => ! $hasFlag && $row['delivered_orders'] === $row['total_orders'] && $row['total_orders'] > 0,
                                                    'bg-sky-500' => ! $hasFlag && $row['out_for_delivery_orders'] > 0,
                                                    'bg-emerald-500' => ! $hasFlag && $row['approved_orders'] > 0,
                                                    'bg-slate-300' => $row['total_orders'] === 0,
                                                ])
                                                style="width: {{ $progressWidth }}%;"
                                            ></div>
                                        </div>
                                        @if($row['latest_order_number'])
                                            <a href="{{ route('requisitions.show', $row['latest_order_number']) }}" class="text-[11px] font-black text-emerald-700 underline decoration-emerald-200 underline-offset-4 hover:text-emerald-800">
                                                Latest: {{ $row['latest_order_number'] }}
                                            </a>
                                        @else
                                            <p class="text-[11px] font-semibold text-slate-400">No requisition for this date</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-5 text-center text-lg font-black text-slate-900">{{ $row['total_orders'] }}</td>
                                <td class="px-4 py-5 text-center text-lg font-black text-emerald-700">{{ $row['approved_orders'] }}</td>
                                <td class="px-4 py-5 text-center text-lg font-black text-sky-700">{{ $row['out_for_delivery_orders'] }}</td>
                                <td class="px-4 py-5 text-center text-lg font-black text-teal-700">{{ $row['delivered_orders'] }}</td>
                                <td class="px-4 py-5 text-center">
                                    @if($hasFlag)
                                        <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-rose-700">
                                            Flag
                                        </span>
                                        @if($row['pending_review_orders'] > 0)
                                            <p class="mt-2 text-[11px] font-bold text-amber-600">{{ $row['pending_review_orders'] }} review pending</p>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700">
                                            Clear
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-5">
                                    <span @class([
                                        'inline-flex rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em]',
                                        'bg-slate-100 text-slate-500' => $row['total_orders'] === 0,
                                        'bg-rose-50 text-rose-700' => $hasFlag,
                                        'bg-teal-50 text-teal-700' => ! $hasFlag && $row['delivered_orders'] === $row['total_orders'] && $row['total_orders'] > 0,
                                        'bg-sky-50 text-sky-700' => ! $hasFlag && $row['out_for_delivery_orders'] > 0,
                                        'bg-emerald-50 text-emerald-700' => ! $hasFlag && $row['approved_orders'] > 0,
                                        'bg-amber-50 text-amber-700' => ! $hasFlag && $row['approved_orders'] === 0 && $row['total_orders'] > 0,
                                    ])>
                                        {{ $row['status_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-5 text-right font-black {{ $row['shortage_total'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    {{ $row['shortage_total'] > 0 ? 'Rs. '.number_format($row['shortage_total'], 2) : 'Nil' }}
                                </td>
                                <td class="px-6 py-5 text-right font-black">
                                    <span class="{{ $variance > 0.01 ? 'text-amber-700' : ($variance < -0.01 ? 'text-sky-700' : 'text-emerald-700') }}">
                                        {{ abs($variance) > 0.01 ? 'Rs. '.number_format(abs($variance), 2) : 'Nil' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.admin>
