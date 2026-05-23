<x-layouts.app title="Cash Flow Statement">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Cash Flow Statement</h1>
        <p class="text-sm text-gray-500 mt-0.5">Summary of cash receipts and cash payments (inflows/outflows) through the cash and bank accounts.</p>
    </div>

    {{-- Period Filter --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('finance.reports.cash-flow') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
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
                <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
                    Generate Statement
                </button>
            </div>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0 border border-green-100">
                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0l-6.75-6.75M12 19.5l6.75-6.75" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Inflows</p>
                <p class="text-2xl font-bold text-green-700 mt-0.5">INR {{ number_format((float) $report['inflows'], 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0 border border-red-100">
                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0l-6.75 6.75M12 4.5l6.75 6.75" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Total Outflows</p>
                <p class="text-2xl font-bold text-red-600 mt-0.5">INR {{ number_format((float) $report['outflows'], 2) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 border border-blue-100">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Net Cash Flow</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5 {{ $report['net_cash_flow'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    INR {{ number_format((float) $report['net_cash_flow'], 2) }}
                </p>
            </div>
        </div>
    </div>

    {{-- Statement Details --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Cash Flow Transactions</h2>
            <span class="text-xs text-gray-500">{{ count($report['movements']) }} cash movements</span>
        </div>

        @if(empty($report['movements']))
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a6 6 0 01-2.25-4.5V12a6 6 0 014.5-5.75M2.25 18.75h19.5M2.25 18.75v-10.5M21.75 8.25V18a6 6 0 01-6 6M21.75 8.25A6 6 0 0015 2.25m6.75 6H9M15 2.25h-3m3 0V9" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No cash movements for this period</p>
            <p class="text-xs text-gray-500 mt-1">Cash and bank transactions (Sales Payments, Purchase Payments, Expenses) will list here.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Ref #</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Category/Contra</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($report['movements'] as $mv)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($mv['date'])->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 font-mono text-xs font-semibold text-brand-600 whitespace-nowrap">
                            {{ $mv['reference'] ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-medium text-gray-900">{{ $mv['category'] }}</span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 max-w-xs truncate" title="{{ $mv['description'] }}">
                            {{ $mv['description'] ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-semibold whitespace-nowrap {{ $mv['type'] === 'inflow' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $mv['type'] === 'inflow' ? '+' : '' }}INR {{ number_format((float) $mv['amount'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</x-layouts.app>
