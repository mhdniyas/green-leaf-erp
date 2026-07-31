<x-layouts.accounting title="Shop Finance Detail">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">{{ $shop->client?->name ?? 'Direct Sales' }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $shop->name }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">{{ $month_start->format('d M Y') }} – {{ $month_end->format('d M Y') }}</p>
            </div>
        </section>

        @if($summary)
            <section class="grid gap-3 md:grid-cols-3">
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Bills', 'value' => $summary['bills'], 'href' => '#ledger'])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Expense', 'value' => $summary['expense'], 'href' => '#ledger'])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Salary', 'value' => $summary['salary'], 'href' => '#ledger'])
            </section>

            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-[1.15rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Received</p>
                    <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format((float) $summary['received'], 2) }}</p>
                </div>
                <div class="rounded-[1.15rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Credit</p>
                    <p class="mt-2 text-xl font-black text-cyan-700">Rs. {{ number_format((float) $summary['credit'], 2) }}</p>
                </div>
                <div class="rounded-[1.15rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Opening</p>
                    <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) $summary['opening_balance'], 2) }}</p>
                </div>
                <div class="rounded-[1.15rem] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Closing</p>
                    <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) $summary['closing_balance'], 2) }}</p>
                </div>
            </section>
        @endif

        <section id="ledger" class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Ledger</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Invoices, expenses, salary and pending balances</h2>
            </div>
            <div class="p-4 sm:p-5">
                @include('admin.finance-v2.partials.detail-table', ['rows' => $ledger_rows])
            </div>
        </section>
    </div>
</x-layouts.accounting>
