<x-layouts.app title="Finance Control">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Module Header -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Finance Dashboard</h1>
                    <p class="text-xs text-slate-500 mt-1">Monitor cash flow, track payments, review running ledgers, and download account statements.</p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex overflow-x-auto border border-slate-200 bg-slate-50/50 p-1.5 rounded-2xl gap-2 w-full shadow-sm select-none scrollbar-none">
            <button type="button" onclick="switchTab('overview')" id="tab-btn-overview" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none bg-white text-slate-900 border border-slate-200 shadow-sm whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>
                Overview
            </button>
            <button type="button" onclick="switchTab('payments')" id="tab-btn-payments" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Payment History
            </button>
            <button type="button" onclick="switchTab('ledger')" id="tab-btn-ledger" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Ledger Statement
            </button>
            <button type="button" onclick="switchTab('statements')" id="tab-btn-statements" 
                    class="tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Download Statements
            </button>
        </div>

        <!-- 1. TAB: Overview -->
        <div id="tab-panel-overview" class="tab-panel space-y-6">
            <!-- Metrics Card Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Available Balance -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Available Balance</span>
                        <div class="text-3xl font-black text-emerald-600 mt-1">₹{{ number_format($availableBalance, 2) }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Combined Cash & Bank liquidity</div>
                </div>

                <!-- Outstanding Amount -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Outstanding Amount</span>
                        <div class="text-3xl font-black text-rose-600 mt-1">₹{{ number_format($outstandingAmount, 2) }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Total Accounts Payable (2100)</div>
                </div>

                <!-- This Month Purchases -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">This Month Purchases</span>
                        <div class="text-3xl font-black text-indigo-600 mt-1">₹{{ number_format($thisMonthPurchases, 2) }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Procured items value this month</div>
                </div>

                <!-- Expected Credit -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Expected Credit</span>
                        <div class="text-3xl font-black text-teal-600 mt-1">₹{{ number_format($expectedCredit, 2) }}</div>
                    </div>
                    <div class="text-[10px] text-slate-500 font-semibold mt-4 pt-2 border-t border-slate-50">Total Accounts Receivable (1100)</div>
                </div>
            </div>

            <!-- Recent Ledger Quickview -->
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Recent Ledger Log</h2>
                    <button type="button" onclick="switchTab('ledger')" class="text-xs text-emerald-600 hover:text-emerald-800 font-bold transition-all">
                        View Full Statement &rarr;
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-6">Date</th>
                                <th class="py-3 px-6">Description</th>
                                <th class="py-3 px-6 text-right">Debit (Outflow)</th>
                                <th class="py-3 px-6 text-right">Credit (Inflow)</th>
                                <th class="py-3 px-6 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach(array_slice($ledgerData['lines'], -5) as $line)
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 text-slate-500">
                                    {{ $line->date ? $line->date->format('d M Y') : '—' }}
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $line->description }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-rose-600">
                                    {{ $line->debit !== null ? '₹' . number_format($line->debit, 2) : '—' }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-emerald-600">
                                    {{ $line->credit !== null ? '₹' . number_format($line->credit, 2) : '—' }}
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-slate-900">
                                    ₹{{ number_format($line->balance, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. TAB: Payment History -->
        <div id="tab-panel-payments" class="tab-panel hidden space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white rounded-3xl border border-slate-200 p-5">
                <form method="GET" action="{{ route('finance.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="tab" value="payments">
                    
                    <div>
                        <label for="payments_date_filter" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Period</label>
                        <select name="date_filter" id="payments_date_filter" onchange="toggleCustomDatesPayments(this.value)"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="this_month" {{ $dateFilter === 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="last_month" {{ $dateFilter === 'last_month' ? 'selected' : '' }}>Last Month</option>
                            <option value="custom" {{ $dateFilter === 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>

                    <div id="payments-custom-date-inputs" class="{{ $dateFilter === 'custom' ? 'grid' : 'hidden' }} grid-cols-2 gap-2 col-span-2">
                        <div>
                            <label for="payments_start_date" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                            <input type="date" name="start_date" id="payments_start_date" value="{{ $startDate }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        </div>
                        <div>
                            <label for="payments_end_date" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                            <input type="date" name="end_date" id="payments_end_date" value="{{ $endDate }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        </div>
                    </div>

                    <div class="flex gap-2 {{ $dateFilter === 'custom' ? 'col-span-4 justify-end' : '' }}">
                        <button type="submit" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white transition-all shadow-md flex items-center justify-center min-w-[120px]">
                            Apply Filters
                        </button>
                        @if($dateFilter !== 'this_month')
                        <a href="{{ route('finance.index', ['tab' => 'payments']) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all flex items-center justify-center">
                            Reset
                        </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Payments Table -->
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                @if(empty($payments))
                <div class="py-16 text-center text-slate-400 font-medium italic bg-slate-50/10">
                    No payment transactions found in this period.
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-6">Date</th>
                                <th class="py-3 px-6">Type</th>
                                <th class="py-3 px-6">Reference</th>
                                <th class="py-3 px-6">Description</th>
                                <th class="py-3 px-6 text-right">Amount</th>
                                <th class="py-3 px-6 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($payments as $pmt)
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 text-slate-500">
                                    {{ $pmt->date->format('d M Y') }}
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $pmt->type }}
                                </td>
                                <td class="py-4 px-6 font-mono text-emerald-600 font-bold">
                                    {{ $pmt->reference }}
                                </td>
                                <td class="py-4 px-6 text-slate-600">
                                    {{ $pmt->description }}
                                </td>
                                <td class="py-4 px-6 text-right font-black {{ $pmt->flow === 'in' ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $pmt->flow === 'in' ? '+' : '-' }}₹{{ number_format($pmt->amount, 2) }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="inline-flex items-center text-[9px] font-black uppercase text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                        {{ $pmt->status }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <!-- 3. TAB: Ledger Statement -->
        <div id="tab-panel-ledger" class="tab-panel hidden space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white rounded-3xl border border-slate-200 p-5">
                <form method="GET" action="{{ route('finance.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="tab" value="ledger">
                    
                    <div>
                        <label for="ledger_date_filter" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Period</label>
                        <select name="date_filter" id="ledger_date_filter" onchange="toggleCustomDatesLedger(this.value)"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="this_month" {{ $dateFilter === 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="last_month" {{ $dateFilter === 'last_month' ? 'selected' : '' }}>Last Month</option>
                            <option value="custom" {{ $dateFilter === 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>

                    <div id="ledger-custom-date-inputs" class="{{ $dateFilter === 'custom' ? 'grid' : 'hidden' }} grid-cols-2 gap-2 col-span-2">
                        <div>
                            <label for="ledger_start_date" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                            <input type="date" name="start_date" id="ledger_start_date" value="{{ $startDate }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        </div>
                        <div>
                            <label for="ledger_end_date" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                            <input type="date" name="end_date" id="ledger_end_date" value="{{ $endDate }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        </div>
                    </div>

                    <div class="flex gap-2 {{ $dateFilter === 'custom' ? 'col-span-4 justify-end' : '' }}">
                        <button type="submit" class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white transition-all shadow-md flex items-center justify-center min-w-[120px]">
                            Apply Filters
                        </button>
                        @if($dateFilter !== 'this_month')
                        <a href="{{ route('finance.index', ['tab' => 'ledger']) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all flex items-center justify-center">
                            Reset
                        </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Ledger Table -->
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Ledger Running Statement</h2>
                    <div class="flex gap-4 text-xs font-bold text-slate-500">
                        <div>Opening: <span class="text-slate-900">₹{{ number_format($ledgerData['opening_balance'], 2) }}</span></div>
                        <div>Closing: <span class="text-slate-900">₹{{ number_format($ledgerData['closing_balance'], 2) }}</span></div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-6">Date</th>
                                <th class="py-3 px-6">Description</th>
                                <th class="py-3 px-6 text-right">Debit (Outflow)</th>
                                <th class="py-3 px-6 text-right">Credit (Inflow)</th>
                                <th class="py-3 px-6 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($ledgerData['lines'] as $line)
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-4 px-6 text-slate-500">
                                    {{ $line->date ? $line->date->format('d M Y') : '—' }}
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $line->description }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-rose-600">
                                    {{ $line->debit !== null ? '₹' . number_format($line->debit, 2) : '—' }}
                                </td>
                                <td class="py-4 px-6 text-right font-semibold text-emerald-600">
                                    {{ $line->credit !== null ? '₹' . number_format($line->credit, 2) : '—' }}
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-slate-900">
                                    ₹{{ number_format($line->balance, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 4. TAB: Download Statements -->
        <div id="tab-panel-statements" class="tab-panel hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h2 class="text-sm font-bold text-slate-800 tracking-tight">Export Ledger Statement</h2>
                    <p class="text-xs text-slate-500 mt-1">Generate print-friendly PDF files or stream raw ledger CSV transactions for auditing.</p>
                </div>
                <div class="p-6">
                    <form method="GET" class="space-y-6 max-w-lg">
                        <div>
                            <label for="statements_period" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Statement Period</label>
                            <select name="date_filter" id="statements_period" onchange="toggleCustomDatesStatements(this.value)"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                                <option value="this_month" {{ $dateFilter === 'this_month' ? 'selected' : '' }}>This Month</option>
                                <option value="last_month" {{ $dateFilter === 'last_month' ? 'selected' : '' }}>Last Month</option>
                                <option value="custom" {{ $dateFilter === 'custom' ? 'selected' : '' }}>Custom Range</option>
                            </select>
                        </div>

                        <div id="statements-custom-date-inputs" class="{{ $dateFilter === 'custom' ? 'grid' : 'hidden' }} grid-cols-2 gap-4">
                            <div>
                                <label for="statements_start_date" class="block text-xs font-bold text-slate-400 uppercase mb-2">Start Date</label>
                                <input type="date" name="start_date" id="statements_start_date" value="{{ $startDate }}"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            </div>
                            <div>
                                <label for="statements_end_date" class="block text-xs font-bold text-slate-400 uppercase mb-2">End Date</label>
                                <input type="date" name="end_date" id="statements_end_date" value="{{ $endDate }}"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            </div>
                        </div>

                        <div class="flex gap-4 pt-4">
                            <button type="submit" onclick="this.form.action='{{ route('finance.statement.export.pdf') }}'; this.form.target='_blank';"
                                    class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-xs font-bold text-white hover:bg-emerald-700 transition-all shadow-md hover:shadow-lg cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                Print Statement (PDF)
                            </button>
                            <button type="submit" onclick="this.form.action='{{ route('finance.statement.export.csv') }}'; this.form.target='_self';"
                                    class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                Export CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function switchTab(tabId) {
            // Hide all tab panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.add('hidden');
            });
            // Show target panel
            document.getElementById('tab-panel-' + tabId).classList.remove('hidden');

            // Reset all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.className = "tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0";
            });
            // Select active button
            const activeBtn = document.getElementById('tab-btn-' + tabId);
            if (activeBtn) {
                activeBtn.className = "tab-btn inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none bg-white text-slate-900 border border-slate-200 shadow-sm whitespace-nowrap shrink-0";
            }

            // Sync with URL query string
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        function toggleCustomDatesPayments(value) {
            const customDatesDiv = document.getElementById('payments-custom-date-inputs');
            if (value === 'custom') {
                customDatesDiv.classList.remove('hidden');
                customDatesDiv.classList.add('grid');
            } else {
                customDatesDiv.classList.add('hidden');
                customDatesDiv.classList.remove('grid');
            }
        }

        function toggleCustomDatesLedger(value) {
            const customDatesDiv = document.getElementById('ledger-custom-date-inputs');
            if (value === 'custom') {
                customDatesDiv.classList.remove('hidden');
                customDatesDiv.classList.add('grid');
            } else {
                customDatesDiv.classList.add('hidden');
                customDatesDiv.classList.remove('grid');
            }
        }

        function toggleCustomDatesStatements(value) {
            const customDatesDiv = document.getElementById('statements-custom-date-inputs');
            if (value === 'custom') {
                customDatesDiv.classList.remove('hidden');
                customDatesDiv.classList.add('grid');
            } else {
                customDatesDiv.classList.add('hidden');
                customDatesDiv.classList.remove('grid');
            }
        }

        // On page load, switch to active tab from query param
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || '{{ $activeTab }}';
            switchTab(activeTab);
        });
    </script>
    @endpush
</x-layouts.app>
