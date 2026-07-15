        @php
            $cashFlowSummary = $cashFlowReport['summary'];
            $selectedCashFlowDate = \Illuminate\Support\Carbon::parse($cashFlowReport['selected_date']);
        @endphp

        <section class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm" data-cash-flow-tabs data-cash-flow-active-tab="{{ $cashFlowTab }}">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Cash Flow Report</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Company cash and bank journal</h3>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Monthly view for {{ $cashFlowReport['month_label'] }} from posted journal entries, running balance, and purchaser-wise paid and received details.</p>
                </div>

                <div class="flex flex-col gap-3 xl:items-end">
                    <div class="flex flex-wrap gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Cash flow views">
                        @foreach([
                            'journal' => 'Journal',
                            'day-journal' => 'Day Journal',
                            'daily-balance' => 'Daily Balance',
                            'cash-paid' => 'Purchaser Paid',
                            'cash-received' => 'Purchaser Receive',
                        ] as $tabKey => $tabLabel)
                            <button
                                type="button"
                                role="tab"
                                aria-selected="{{ $cashFlowTab === $tabKey ? 'true' : 'false' }}"
                                data-cash-flow-tab-button="{{ $tabKey }}"
                                class="inline-flex h-11 items-center rounded-2xl px-4 text-xs font-black uppercase tracking-[0.16em] transition {{ $cashFlowTab === $tabKey ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-700 hover:bg-white' }}"
                            >
                                {{ $tabLabel }}
                            </button>
                        @endforeach
                    </div>

                    <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Report Period</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($cashFlowReport['start_date'])->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($cashFlowReport['end_date'])->format('d M Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Balance</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($cashFlowSummary['opening_balance'], 2) }}</p>
                </article>
                <article class="rounded-[1.35rem] border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Total In</p>
                    <p class="mt-2 text-2xl font-black text-emerald-800">Rs. {{ number_format($cashFlowSummary['total_in'], 2) }}</p>
                </article>
                <article class="rounded-[1.35rem] border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Total Out</p>
                    <p class="mt-2 text-2xl font-black text-amber-800">Rs. {{ number_format($cashFlowSummary['total_out'], 2) }}</p>
                </article>
                <article class="rounded-[1.35rem] border border-cyan-200 bg-cyan-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Closing Balance</p>
                    <p class="mt-2 text-2xl font-black text-cyan-900">Rs. {{ number_format($cashFlowSummary['closing_balance'], 2) }}</p>
                </article>
            </div>

            <div class="mt-5">
                <article class="overflow-hidden rounded-[1.5rem] border border-slate-200">
                <section data-cash-flow-tab-panel="journal" @class(['hidden' => $cashFlowTab !== 'journal'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Journal</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Posted cash and bank journal entries</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Amount</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Debit / Credit</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Journal</th>
                                    <th class="px-4 py-3 min-w-[15rem]">Remarks</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Category</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse($cashFlowReport['journal_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <a href="{{ route('admin.accounting.cash-flow', ['date' => $row['date'], 'cash_tab' => 'day-journal']) }}" class="font-black text-cyan-700 underline-offset-4 hover:underline">
                                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $row['direction'] === 'IN' ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-amber-200 bg-amber-50 text-amber-700' }}">
                                                {{ $row['direction'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <p class="font-black text-slate-950">{{ $row['journal'] }}</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ str_replace('_', ' ', $row['source']) }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-600 min-w-[15rem]">
                                            @if($row['remarks'])
                                                @if(strlen($row['remarks']) > 60)
                                                    <div data-remarks-container>
                                                        <span data-remarks-short class="block line-clamp-1 text-slate-600">{{ $row['remarks'] }}</span>
                                                        <span data-remarks-full class="hidden text-slate-600">{{ $row['remarks'] }}</span>
                                                        <button type="button" onclick="toggleRemarks(this)" class="mt-1 block text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700 hover:text-emerald-800 focus:outline-none">
                                                            Show More
                                                        </button>
                                                    </div>
                                                @else
                                                    {{ $row['remarks'] }}
                                                @endif
                                            @else
                                                <span class="text-slate-400 italic">No remarks</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-black text-slate-700 whitespace-nowrap">{{ $row['category'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                            No cash flow rows are available for {{ $cashFlowReport['month_label'] }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="day-journal" @class(['hidden' => $cashFlowTab !== 'day-journal'])>
                    <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Day Journal</p>
                            <p class="mt-1 text-sm font-black text-slate-950">Journal details for {{ $selectedCashFlowDate->format('d M Y') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.accounting.cash-flow.export.excel', ['date' => $selectedCashFlowDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700 transition hover:bg-emerald-100">
                                Export Excel
                            </a>
                            <a href="{{ route('admin.accounting.cash-flow.export.pdf', ['date' => $selectedCashFlowDate->toDateString()]) }}" target="_blank" class="inline-flex h-10 items-center rounded-xl border border-cyan-200 bg-cyan-50 px-3 text-[10px] font-black uppercase tracking-[0.14em] text-cyan-700 transition hover:bg-cyan-100">
                                Export PDF
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Amount</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Debit / Credit</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Journal</th>
                                    <th class="px-4 py-3 min-w-[15rem]">Remarks</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Category</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse($cashFlowReport['selected_day_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $row['direction'] === 'IN' ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-amber-200 bg-amber-50 text-amber-700' }}">
                                                {{ $row['direction'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <p class="font-black text-slate-950">{{ $row['journal'] }}</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ str_replace('_', ' ', $row['source']) }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-600 min-w-[15rem]">
                                            {{ $row['remarks'] ?: 'No remarks' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-black text-slate-700 whitespace-nowrap">{{ $row['category'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                            No journal rows are available for {{ $selectedCashFlowDate->format('d M Y') }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="daily-balance" @class(['hidden' => $cashFlowTab !== 'daily-balance'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Daily Balance</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Running cash position for the month</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">In</th>
                                    <th class="px-4 py-3 text-right">Out</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($cashFlowReport['daily_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.accounting.cash-flow', ['date' => $row['date'], 'cash_tab' => 'day-journal']) }}" class="font-black text-cyan-700 underline-offset-4 hover:underline">
                                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $row['in_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-amber-700">Rs. {{ number_format((float) $row['out_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ (float) $row['balance'] >= 0 ? 'text-cyan-900' : 'text-rose-700' }}">Rs. {{ number_format((float) $row['balance'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="cash-paid" @class(['hidden' => $cashFlowTab !== 'cash-paid'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Purchaser Paid</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Purchaser Paid</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                    @foreach($cashFlowReport['purchaser_columns'] as $column)
                                        <th class="px-4 py-3 text-right whitespace-nowrap">{{ $column['label'] }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($cashFlowReport['paid_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}</td>
                                        @foreach($cashFlowReport['purchaser_columns'] as $column)
                                            <td class="px-4 py-3 text-right font-black text-slate-700 whitespace-nowrap">Rs. {{ number_format((float) ($row['amounts'][$column['id']] ?? 0), 2) }}</td>
                                        @endforeach
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="cash-received" @class(['hidden' => $cashFlowTab !== 'cash-received'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Purchaser Receive</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Purchaser Receive</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                    @foreach($cashFlowReport['purchaser_columns'] as $column)
                                        <th class="px-4 py-3 text-right whitespace-nowrap">{{ $column['label'] }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($cashFlowReport['received_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}</td>
                                        @foreach($cashFlowReport['purchaser_columns'] as $column)
                                            <td class="px-4 py-3 text-right font-black text-slate-700 whitespace-nowrap">Rs. {{ number_format((float) ($row['amounts'][$column['id']] ?? 0), 2) }}</td>
                                        @endforeach
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </article>
            </div>
        </section>

        <script>
            function toggleRemarks(button) {
                const container = button.closest('[data-remarks-container]');
                const shortSpan = container.querySelector('[data-remarks-short]');
                const fullSpan = container.querySelector('[data-remarks-full]');

                if (fullSpan.classList.contains('hidden')) {
                    fullSpan.classList.remove('hidden');
                    shortSpan.classList.add('hidden');
                    button.textContent = 'Show Less';
                } else {
                    fullSpan.classList.add('hidden');
                    shortSpan.classList.remove('hidden');
                    button.textContent = 'Show More';
                }
            }
        </script>
        <script src="{{ asset('js/accounting-cash-flow-tabs.js') }}" defer></script>
