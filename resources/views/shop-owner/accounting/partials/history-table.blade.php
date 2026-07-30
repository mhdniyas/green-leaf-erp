@if ($entries->count() > 0)
    <div class="overflow-x-auto rounded-[1.5rem] border border-slate-200">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Opening Cash</th>
                    <th class="px-4 py-3 text-right">Income</th>
                    <th class="px-4 py-3 text-right">Expense</th>
                    <th class="px-4 py-3 text-right">Net</th>
                    <th class="px-4 py-3 text-right">Closing Cash</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($entries as $entry)
                    @php
                        $income = (float) $entry->lines->where('type', 'income')->sum('amount');
                        $expense = (float) $entry->lines->where('type', 'expense')->sum('amount');
                        $net = $income - $expense;
                    @endphp
                    <tr class="hover:bg-slate-50/40 transition-colors">
                        <td class="px-4 py-3.5 font-semibold text-slate-600">{{ $entry->business_date->format('d M Y') }}</td>
                        <td class="px-4 py-3.5">
                            @include('shop-owner.components.status-badge', ['label' => $entry->statusLabel(), 'tone' => $entry->statusTone()])
                        </td>
                        <td class="px-4 py-3.5 text-right font-black text-slate-700">Rs. {{ number_format((float) $entry->opening_cash, 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-black text-emerald-700">Rs. {{ number_format($income, 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-black text-rose-700">Rs. {{ number_format($expense, 2) }}</td>
                        <td @class([
                            'px-4 py-3.5 text-right font-black',
                            'text-emerald-700' => $net >= 0,
                            'text-rose-700' => $net < 0,
                        ])>
                            {{ $net >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($net), 2) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-black text-slate-950">Rs. {{ number_format((float) $entry->closing_cash, 2) }}</td>
                        <td class="px-4 py-3.5 text-right">
                            <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => $entry->business_date->format('Y-m-d')]) }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900">
                                Open & Update
                            </a>
                        </td>
                    </tr>
                    @if ($entry->admin_note || $entry->shop_reply_note)
                        <tr class="bg-slate-50/50">
                            <td colspan="8" class="px-4 py-2 text-xs border-b border-slate-100">
                                <div class="flex flex-col gap-2 md:flex-row md:gap-6">
                                    @if ($entry->admin_note)
                                        <div>
                                            <span class="font-black text-rose-700 uppercase tracking-wider text-[9px]">Admin Note:</span>
                                            <span class="font-semibold text-slate-600 ml-1">{{ $entry->admin_note }}</span>
                                        </div>
                                    @endif
                                    @if ($entry->shop_reply_note)
                                        <div>
                                            <span class="font-black text-emerald-700 uppercase tracking-wider text-[9px]">Your Reply:</span>
                                            <span class="font-semibold text-slate-600 ml-1">{{ $entry->shop_reply_note }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $entries->links() }}</div>
@else
    @include('shop-owner.components.empty-state', ['title' => 'No accounting history', 'description' => 'Submitted daily accounting entries will appear here with approval status and notes.'])
@endif
