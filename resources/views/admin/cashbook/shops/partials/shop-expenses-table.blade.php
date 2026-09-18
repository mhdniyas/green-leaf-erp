@php
    $expenses = $financialReport['expenses'] ?? [];
    $totalExpenses = $financialReport['summary']['expenses'] ?? 0.0;
    $sales = $financialReport['summary']['sales'] ?? 0.0;
@endphp

<section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" aria-label="Shop Expenses and Funding Source Breakdown">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                Shop Expenses
            </h2>
            <p class="text-xs text-slate-500 font-medium">Configured categories with percentage of sales &amp; funding source split</p>
        </div>
        <span class="font-mono text-xs font-black text-rose-950 tabular-nums">
            Total: ₹{{ number_format((float) $totalExpenses, 2) }}
        </span>
    </div>

    @if(!empty($expenses))
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-100 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="py-3 px-4">Expense Category</th>
                        <th scope="col" class="py-3 px-4 text-right">Amount</th>
                        <th scope="col" class="py-3 px-4 text-right">% of Sales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($expenses as $expenseItem)
                        <tr x-data="{ expanded: false }" class="hover:bg-slate-50/50 transition">
                            <td class="py-3 px-4">
                                <div class="space-y-2">
                                    <button type="button"
                                            @click="expanded = !expanded"
                                            class="flex items-center gap-2 font-bold text-slate-900 hover:text-indigo-600 transition text-left cursor-pointer">
                                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                                             :class="{ 'rotate-90': expanded }"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <span>{{ $expenseItem['category'] }}</span>
                                    </button>

                                    <!-- Funding Source Breakdown on Expand -->
                                    <div x-show="expanded" class="pl-6 space-y-1.5 pt-1" style="display: none;">
                                        @if(!empty($expenseItem['funding_split']))
                                            <div class="rounded-xl border border-slate-200/60 bg-slate-50/90 p-3 space-y-1 text-[11px]">
                                                <span class="font-black uppercase tracking-wider text-slate-500 block text-[10px]">Funding Source Split:</span>
                                                @foreach($expenseItem['funding_split'] as $split)
                                                    <div class="flex items-center justify-between text-slate-700">
                                                        <span>&bull; {{ $split['label'] }}</span>
                                                        <span class="font-mono font-bold text-slate-900 tabular-nums">₹{{ number_format((float) $split['amount'], 2) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-[11px] text-slate-400 italic">No specific funding source breakdown.</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-950 align-top tabular-nums whitespace-nowrap">
                                ₹{{ number_format((float) $expenseItem['amount'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-slate-600 align-top tabular-nums whitespace-nowrap">
                                {{ $expenseItem['percentage_formatted'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
            No expenses recorded or configured for this period.
        </div>
    @endif
</section>
