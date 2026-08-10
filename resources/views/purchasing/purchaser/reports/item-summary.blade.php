<x-layouts.app title="Item Summary">
    <div class="mx-auto w-full max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-end justify-between gap-3 bg-slate-950 px-4 py-4 text-white sm:px-5">
            <div><p class="text-[10px] font-black uppercase text-emerald-400">Purchaser reports</p><h1 class="mt-1 text-xl font-black">Item Summary</h1><p class="mt-1 text-xs font-semibold text-slate-400">{{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('purchaser.reports.item-summary.csv', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">CSV</a>
                <a href="{{ route('purchaser.reports.item-summary.excel', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">Excel</a>
                <a href="{{ route('purchaser.reports.item-summary.pdf', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">PDF</a>
                <a href="{{ route('purchaser.reports.sales-summary', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200">Sales Summary</a>
            </div>
        </header>

        @include('purchasing.purchaser.reports.partials.filters', ['routeName' => 'purchaser.reports.item-summary'])

        <section class="grid grid-cols-3 border-b border-slate-200">
            @foreach ([['Products', $report['summary']['distinct_products']], ['Product units', $report['summary']['product_unit_rows']], ['Invoice lines', $report['summary']['invoice_lines']]] as [$label, $value])
                <div class="border-r border-slate-200 px-3 py-3 text-center"><p class="text-[10px] font-black uppercase text-slate-400">{{ $label }}</p><p class="mt-1 text-lg font-black text-slate-950">{{ $value }}</p></div>
            @endforeach
        </section>

        @if ($report['items'] === [])
            <div class="px-5 py-12 text-center text-sm font-semibold text-slate-500">No billable items found for this period.</div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500"><tr><th class="px-5 py-3">Product</th><th class="px-3 py-3">Category</th><th class="px-3 py-3">Unit</th><th class="px-3 py-3 text-right">Quantity</th><th class="px-3 py-3 text-right">Sales</th><th class="px-5 py-3 text-right">Shops</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report['items'] as $item)
                            <tr><td class="px-5 py-3"><p class="font-bold text-slate-950">{{ $item['product_name'] }}</p><p class="text-xs text-slate-400">{{ $item['product_sku'] ?? 'No SKU' }} · {{ $item['invoice_count'] }} invoices</p></td><td class="px-3 py-3 text-slate-600">{{ $item['category_name'] ?? 'Uncategorized' }}</td><td class="px-3 py-3 font-bold">{{ $item['unit'] }}</td><td class="px-3 py-3 text-right font-black">{{ rtrim(rtrim(number_format((float) $item['billed_quantity'], 4, '.', ''), '0'), '.') }}</td><td class="px-3 py-3 text-right font-semibold">₹{{ number_format((float) $item['line_sales_amount'], 2) }}</td><td class="px-5 py-3 text-right font-semibold">{{ $item['shop_count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-200 md:hidden">
                @foreach ($report['items'] as $item)
                    <article class="px-4 py-3"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate font-black text-slate-950">{{ $item['product_name'] }}</h2><p class="truncate text-xs font-semibold text-slate-400">{{ $item['product_sku'] ?? 'No SKU' }} · {{ $item['category_name'] ?? 'Uncategorized' }}</p></div><div class="shrink-0 text-right"><p class="font-black text-slate-950">{{ rtrim(rtrim(number_format((float) $item['billed_quantity'], 4, '.', ''), '0'), '.') }} {{ $item['unit'] }}</p><p class="text-xs font-bold text-emerald-700">₹{{ number_format((float) $item['line_sales_amount'], 2) }}</p></div></div><p class="mt-2 text-xs font-semibold text-slate-500">{{ $item['shop_count'] }} shops · {{ $item['invoice_count'] }} invoices</p></article>
                @endforeach
            </div>
        @endif
        @include('purchasing.purchaser.reports.partials.pagination', ['pagination' => $report['pagination']])
    </div>
</x-layouts.app>
