<x-layouts.app title="Sales Summary">
    <div class="mx-auto w-full max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-end justify-between gap-3 bg-slate-950 px-4 py-4 text-white sm:px-5">
            <div>
                <p class="text-[10px] font-black uppercase text-emerald-400">Purchaser reports</p>
                <h1 class="mt-1 text-xl font-black">Sales Summary</h1>
                <p class="mt-1 text-xs font-semibold text-slate-400">{{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('purchaser.reports.sales-summary.csv', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">CSV</a>
                <a href="{{ route('purchaser.reports.sales-summary.excel', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">Excel</a>
                <a href="{{ route('purchaser.reports.sales-summary.pdf', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">PDF</a>
                <a href="{{ route('purchaser.reports.item-summary', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">Item Summary</a>
            </div>
        </header>

        @include('purchasing.purchaser.reports.partials.filters', ['routeName' => 'purchaser.reports.sales-summary'])

        <section class="grid grid-cols-2 border-b border-slate-200 md:grid-cols-5">
            @foreach ([
                ['Sales', '₹'.number_format((float) $report['totals']['total_sales'], 2)],
                ['Paid', '₹'.number_format((float) $report['totals']['paid_amount'], 2)],
                ['Outstanding', '₹'.number_format((float) $report['totals']['outstanding_amount'], 2)],
                ['Shops', $report['totals']['total_shops']],
                ['Invoices', $report['totals']['total_invoices']],
            ] as [$label, $value])
                <div class="border-r border-t border-slate-200 px-3 py-3 first:border-t-0 md:border-t-0">
                    <p class="text-[10px] font-black uppercase text-slate-400">{{ $label }}</p>
                    <p class="mt-1 truncate text-base font-black text-slate-950">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        @if ($report['shops'] === [])
            <div class="px-5 py-12 text-center text-sm font-semibold text-slate-500">No billable sales found for this period.</div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr><th class="px-5 py-3">Shop</th><th class="px-3 py-3 text-right">Invoices</th><th class="px-3 py-3 text-right">Sales</th><th class="px-3 py-3 text-right">Paid</th><th class="px-5 py-3 text-right">Outstanding</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report['shops'] as $shop)
                            <tr><td class="px-5 py-3"><p class="font-bold text-slate-950">{{ $shop['shop_name'] }}</p><p class="text-xs text-slate-400">{{ $shop['shop_code'] }}</p></td><td class="px-3 py-3 text-right font-semibold">{{ $shop['invoice_count'] }}</td><td class="px-3 py-3 text-right font-black">₹{{ number_format((float) $shop['total_sales'], 2) }}</td><td class="px-3 py-3 text-right font-semibold text-emerald-700">₹{{ number_format((float) $shop['paid_amount'], 2) }}</td><td class="px-5 py-3 text-right font-semibold text-amber-700">₹{{ number_format((float) $shop['outstanding_amount'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-200 md:hidden">
                @foreach ($report['shops'] as $shop)
                    <article class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3"><div><h2 class="font-black text-slate-950">{{ $shop['shop_name'] }}</h2><p class="text-xs font-semibold text-slate-400">{{ $shop['shop_code'] }} · {{ $shop['invoice_count'] }} invoices</p></div><p class="shrink-0 font-black text-slate-950">₹{{ number_format((float) $shop['total_sales'], 2) }}</p></div>
                        <div class="mt-2 flex justify-between text-xs font-bold"><span class="text-emerald-700">Paid ₹{{ number_format((float) $shop['paid_amount'], 2) }}</span><span class="text-amber-700">Due ₹{{ number_format((float) $shop['outstanding_amount'], 2) }}</span></div>
                    </article>
                @endforeach
            </div>
        @endif
        @include('purchasing.purchaser.reports.partials.pagination', ['pagination' => $report['pagination']])
    </div>
</x-layouts.app>
