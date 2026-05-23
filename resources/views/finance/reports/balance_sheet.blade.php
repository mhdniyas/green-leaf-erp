<x-layouts.app title="Balance Sheet">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">Balance Sheet</h1>
        <p class="text-sm text-gray-500 mt-0.5">Cumulative snapshot of assets, liabilities, and equity balances at a specific point in time.</p>
    </div>

    {{-- Date Filter --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('finance.reports.balance-sheet') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label for="end_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">As Of Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
                    Generate Balance Sheet
                </button>
            </div>
        </form>
    </div>

    {{-- Balance Sheet Report --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden max-w-3xl">
        <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
            <div>
                <h2 class="text-base font-bold text-gray-900">Balance Sheet</h2>
                <p class="text-xs text-gray-500 mt-1">As of: {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
            </div>
            <span class="inline-flex items-center text-[10px] font-bold px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 border border-brand-100">
                INR Reporting Currency
            </span>
        </div>

        <div class="p-8">
            {{-- Assets Section --}}
            <div class="mb-8">
                <div class="flex justify-between items-center border-b border-gray-950 pb-2 mb-3 bg-gray-50 px-2 py-1">
                    <span class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">ASSETS</span>
                    <span class="font-mono font-bold text-gray-900">INR {{ number_format((float) $report['total_assets'], 2) }}</span>
                </div>
                <div class="pl-4 space-y-2 text-sm text-gray-700">
                    @foreach($report['assets'] as $asset)
                    <div class="flex justify-between items-center">
                        <span>{{ $asset['name'] }} ({{ $asset['code'] }})</span>
                        <span class="font-mono">INR {{ number_format((float) $asset['balance'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Liabilities Section --}}
            <div class="mb-8">
                <div class="flex justify-between items-center border-b border-gray-950 pb-2 mb-3 bg-gray-50 px-2 py-1">
                    <span class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">LIABILITIES</span>
                    <span class="font-mono font-bold text-gray-900">INR {{ number_format((float) $report['total_liabilities'], 2) }}</span>
                </div>
                
                @if(empty($report['liabilities']))
                    <p class="text-xs text-gray-400 italic pl-4">No outstanding liabilities.</p>
                @else
                    <div class="pl-4 space-y-2 text-sm text-gray-700">
                        @foreach($report['liabilities'] as $liab)
                        <div class="flex justify-between items-center">
                            <span>{{ $liab['name'] }} ({{ $liab['code'] }})</span>
                            <span class="font-mono">INR {{ number_format((float) $liab['balance'], 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Equity Section --}}
            <div class="mb-8">
                <div class="flex justify-between items-center border-b border-gray-950 pb-2 mb-3 bg-gray-50 px-2 py-1">
                    <span class="text-sm font-extrabold text-gray-900 uppercase tracking-wider">EQUITY</span>
                    <span class="font-mono font-bold text-gray-900">INR {{ number_format((float) $report['total_equity'], 2) }}</span>
                </div>
                <div class="pl-4 space-y-2 text-sm text-gray-700">
                    @foreach($report['equity'] as $eq)
                    <div class="flex justify-between items-center">
                        <span>{{ $eq['name'] }} {{ $eq['code'] !== 'P&L' ? '('.$eq['code'].')' : '' }}</span>
                        <span class="font-mono">INR {{ number_format((float) $eq['balance'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Liabilities + Equity Total --}}
            <div class="bg-gray-100 rounded-xl p-4 flex justify-between items-center mb-6 border border-gray-200">
                <span class="text-sm font-bold text-gray-900 uppercase">Total Liabilities & Equity</span>
                @php $totalLiabilitiesAndEquity = (float) $report['total_liabilities'] + (float) $report['total_equity']; @endphp
                <span class="font-mono text-base font-bold text-gray-900">
                    INR {{ number_format($totalLiabilitiesAndEquity, 2) }}
                </span>
            </div>

            {{-- Accounting Balancing Check Indicator --}}
            @php $balanced = abs((float) $report['total_assets'] - $totalLiabilitiesAndEquity) < 0.05; @endphp
            <div class="flex items-center gap-2 p-3 rounded-xl {{ $balanced ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }} text-xs font-semibold">
                @if($balanced)
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>General Ledger is perfectly Balanced (Assets = Liabilities + Equity).</span>
                @else
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span>Warning: Balance Sheet mismatch by INR {{ number_format(abs((float) $report['total_assets'] - $totalLiabilitiesAndEquity), 2) }}.</span>
                @endif
            </div>

        </div>
    </div>

</x-layouts.app>
