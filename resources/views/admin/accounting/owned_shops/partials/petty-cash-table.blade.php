<section id="owned-shop-petty-cash" class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Petty Cash</p>
            <h3 class="mt-2 text-lg font-black text-slate-950">Daily petty cash table</h3>
        </div>
        <p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black {{ $pettyCashBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
            Balance Rs. {{ number_format($pettyCashBalance, 2) }}
        </p>
    </div>

    <div class="mt-4 overflow-x-auto rounded-[1rem] border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Credit</th>
                    <th class="px-4 py-3 text-right">EXP</th>
                    <th class="px-4 py-3 text-right">BAL</th>
                    <th class="px-4 py-3 text-right">Last Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pettyCashRows as $pettyRow)
                    <tr>
                        <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($pettyRow['date'])->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-600">{{ $pettyRow['admin_cash_label'] ?: '-' }}</td>
                        <td class="px-4 py-3 text-right font-black text-rose-700">
                            Rs. {{ number_format((float) $pettyRow['expense'], 2) }}
                            @if ($pettyRow['expense_source'])
                                <span class="ml-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">{{ $pettyRow['expense_source'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-black {{ (float) $pettyRow['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format((float) $pettyRow['balance'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-500">
                            {{ $pettyRow['expense_updated_at'] ? $pettyRow['expense_updated_at']->format('d M Y h:i A') : '-' }}
                            @if ($pettyRow['amount_change_label'])
                                <span class="mt-1 block text-xs font-bold text-amber-700">{{ $pettyRow['amount_change_label'] }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No petty cash rows found for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
