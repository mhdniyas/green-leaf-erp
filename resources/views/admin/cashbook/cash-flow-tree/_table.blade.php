{{-- Tabular Matrix View for Monthly Cash Flow Tree --}}
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h3 class="text-sm font-bold text-slate-900 font-sans">Entity Balance & Movement Matrix</h3>
            <p class="text-xs text-slate-500 font-sans">Comprehensive tabular view of opening balances, monthly inflows, outflows, and current holdings.</p>
        </div>
        <div class="text-xs font-mono text-slate-500">
            Total Movements: <span class="font-bold text-slate-800">{{ $tree->movementsCount() }}</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-xs font-sans">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="py-3 px-4 text-left font-semibold text-slate-600">Entity / Holder</th>
                    <th scope="col" class="py-3 px-3 text-left font-semibold text-slate-600">Type</th>
                    <th scope="col" class="py-3 px-4 text-right font-semibold text-slate-600">Opening</th>
                    <th scope="col" class="py-3 px-4 text-right font-semibold text-emerald-600">In (+)</th>
                    <th scope="col" class="py-3 px-4 text-right font-semibold text-rose-600">Out (-)</th>
                    <th scope="col" class="py-3 px-4 text-right font-semibold text-slate-900">Closing / Holding</th>
                    <th scope="col" class="py-3 px-4 text-right font-semibold text-slate-600">Net Change</th>
                    <th scope="col" class="py-3 px-3 text-center font-semibold text-slate-600">Audit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @foreach($tree->children as $branch)
                    {{-- Branch Header Row --}}
                    <tr class="bg-slate-50/80 font-semibold text-slate-900">
                        <td colspan="2" class="py-2.5 px-4 font-bold tracking-tight">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                                {{ $branch->title }}
                            </span>
                        </td>
                        <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-700">
                            {{ abs($branch->openingBalance) > 0.001 ? '₹'.number_format($branch->openingBalance, 2) : '—' }}
                        </td>
                        <td class="py-2.5 px-4 text-right font-mono font-bold text-emerald-600">
                            {{ $branch->totalIn > 0.001 ? '₹'.number_format($branch->totalIn, 2) : '—' }}
                        </td>
                        <td class="py-2.5 px-4 text-right font-mono font-bold text-rose-600">
                            {{ $branch->totalOut > 0.001 ? '₹'.number_format($branch->totalOut, 2) : '—' }}
                        </td>
                        <td class="py-2.5 px-4 text-right font-mono font-extrabold text-slate-900">
                            {{ abs($branch->closingBalance) > 0.001 ? '₹'.number_format($branch->closingBalance, 2) : '—' }}
                        </td>
                        <td class="py-2.5 px-4 text-right font-mono font-bold {{ $branch->netChange >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ abs($branch->netChange) > 0.001 ? ($branch->netChange > 0 ? '+' : '').'₹'.number_format($branch->netChange, 2) : '—' }}
                        </td>
                        <td class="py-2.5 px-3 text-center font-mono text-[11px] text-slate-500">
                            {{ $branch->movementsCount() > 0 ? $branch->movementsCount().' moves' : '—' }}
                        </td>
                    </tr>

                    {{-- Member Rows --}}
                    @foreach($branch->children as $member)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-2.5 px-4 pl-8">
                                <div class="font-medium text-slate-800">{{ $member->title }}</div>
                                @if($member->subtitle)
                                    <div class="text-[11px] text-slate-400 font-normal">{{ $member->subtitle }}</div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $member->badge ?: ucfirst($member->entityType) }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono text-slate-600">
                                {{ abs($member->openingBalance) > 0.001 ? '₹'.number_format($member->openingBalance, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono font-medium text-emerald-600">
                                {{ $member->totalIn > 0.001 ? '₹'.number_format($member->totalIn, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono font-medium text-rose-600">
                                {{ $member->totalOut > 0.001 ? '₹'.number_format($member->totalOut, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono font-bold {{ $member->closingBalance < 0 ? 'text-rose-700' : 'text-slate-900' }}">
                                {{ abs($member->closingBalance) > 0.001 ? '₹'.number_format($member->closingBalance, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono {{ $member->netChange >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ abs($member->netChange) > 0.001 ? ($member->netChange > 0 ? '+' : '').'₹'.number_format($member->netChange, 2) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                @if($member->movementsCount() > 0)
                                    <button 
                                        type="button"
                                        @click="fetchDrilldown('{{ $member->id }}', '{{ addslashes($member->title) }}')"
                                        class="inline-flex items-center gap-1 text-[11px] text-brand-600 hover:text-brand-800 font-medium underline hover:bg-brand-50 px-1.5 py-0.5 rounded transition"
                                    >
                                        {{ $member->movementsCount() }} moves
                                    </button>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
