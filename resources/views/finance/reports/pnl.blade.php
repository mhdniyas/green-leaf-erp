<x-layouts.app title="Profit & Loss Statement">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Profit & Loss Statement</h1>
        <p class="text-sm text-gray-500 mt-0.5">Summary of revenue, direct cost of sales, wastage, and operational expenses for a selected period.</p>
    </div>

    {{-- Period Filter --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('finance.reports.pnl') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">From Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
            </div>
            <div>
                <label for="end_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">To Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
                    Generate Report
                </button>
            </div>
        </form>
    </div>

    {{-- Financial Report Statement --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden max-w-3xl">
        <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
            <div>
                <h2 class="text-base font-bold text-gray-900">Profit & Loss Statement</h2>
                <p class="text-xs text-gray-500 mt-1">Period: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
            </div>
            <span class="inline-flex items-center text-[10px] font-bold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 border border-brand-100">
                INR Reporting Currency
            </span>
        </div>

        <div class="p-8">
            {{-- Revenue Section --}}
            <div class="mb-6">
                <div class="flex justify-between items-center border-b border-gray-200 pb-2 mb-3">
                    <span class="text-sm font-bold text-gray-800 uppercase tracking-wider">Revenue</span>
                    <span class="font-mono font-bold text-gray-900">INR {{ number_format((float) $report['revenue'], 2) }}</span>
                </div>
                <div class="pl-4 flex justify-between items-center text-sm text-gray-600">
                    <span>Sales Revenue (4100)</span>
                    <span class="font-mono">INR {{ number_format((float) $report['revenue'], 2) }}</span>
                </div>
            </div>

            {{-- Cost of Sales --}}
            <div class="mb-6">
                <div class="flex justify-between items-center border-b border-gray-200 pb-2 mb-3">
                    <span class="text-sm font-bold text-gray-800 uppercase tracking-wider">Cost of Sales</span>
                    <span class="font-mono font-bold text-gray-900">
                        @php $totalDirectCosts = (float) $report['cogs'] + (float) $report['wastage']; @endphp
                        (INR {{ number_format($totalDirectCosts, 2) }})
                    </span>
                </div>
                <div class="pl-4 space-y-2 text-sm text-gray-600">
                    <div class="flex justify-between items-center">
                        <span>Cost of Goods Sold (Purchases - 5100)</span>
                        <span class="font-mono">INR {{ number_format((float) $report['cogs'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Wastage Expense (Spoiled/Damaged Stock - 5200)</span>
                        <span class="font-mono">INR {{ number_format((float) $report['wastage'], 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Gross Profit --}}
            <div class="bg-gray-50 rounded-xl p-4 flex justify-between items-center mb-8 border border-gray-100">
                <span class="text-sm font-bold text-gray-900 uppercase">Gross Profit</span>
                <span class="font-mono text-base font-bold {{ $report['gross_profit'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    INR {{ number_format((float) $report['gross_profit'], 2) }}
                </span>
            </div>

            {{-- Operating Expenses --}}
            <div class="mb-6">
                <div class="flex justify-between items-center border-b border-gray-200 pb-2 mb-3">
                    <span class="text-sm font-bold text-gray-800 uppercase tracking-wider">Operating Expenses</span>
                    <span class="font-mono font-bold text-gray-900">(INR {{ number_format((float) $report['total_expenses'], 2) }})</span>
                </div>
                
                @if(empty($report['expenses']))
                    <p class="text-xs text-gray-400 italic pl-4">No operating expenses recorded for this period.</p>
                @else
                    <div class="pl-4 space-y-2 text-sm text-gray-600">
                        @foreach($report['expenses'] as $exp)
                        <div class="flex justify-between items-center">
                            <span>{{ $exp['name'] }} ({{ $exp['code'] }})</span>
                            <span class="font-mono">INR {{ number_format((float) $exp['balance'], 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Net Profit --}}
            <div class="border-t-2 border-double border-gray-900 pt-4 mt-8 flex justify-between items-center">
                <span class="text-base font-bold text-gray-900 uppercase">Net Profit / Loss</span>
                <span class="font-mono text-lg font-extrabold {{ $report['net_profit'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    INR {{ number_format((float) $report['net_profit'], 2) }}
                </span>
            </div>
        </div>
    </div>

</x-layouts.app>
