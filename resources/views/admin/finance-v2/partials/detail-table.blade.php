<div class="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-left">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Date</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Party</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Reference</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Description</th>
                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Amount</th>
                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Paid</th>
                    <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Pending</th>
                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Status</th>
                    <th class="px-4 py-3 text-center text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600">{{ $row['date'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-black text-slate-950">
                            @if (!empty($row['supplier_url']))
                                <a href="{{ $row['supplier_url'] }}" class="text-slate-950 hover:text-teal-700 hover:underline">{{ $row['party'] ?? '-' }}</a>
                            @else
                                {{ $row['party'] ?? '-' }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-mono font-bold text-slate-700">
                            @if (!empty($row['view_url']))
                                <a href="{{ $row['view_url'] }}" class="text-cyan-700 hover:underline">{{ $row['reference'] ?? '-' }}</a>
                            @else
                                {{ $row['reference'] ?? '-' }}
                            @endif
                        </td>
                        <td class="min-w-[14rem] px-4 py-3 text-sm font-semibold text-slate-600">{{ $row['description'] ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-black text-emerald-700">Rs. {{ number_format((float) ($row['paid'] ?? 0), 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-black text-amber-700">Rs. {{ number_format((float) ($row['pending'] ?? 0), 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ ($row['status'] ?? '') === 'Paid' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'text-slate-600' }}">
                                {{ $row['status'] ?? 'Open' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-center">
                            @if (!empty($row['view_url']))
                                <a href="{{ $row['view_url'] }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-slate-950 px-3 text-[10px] font-black uppercase tracking-wider text-white shadow-xs transition-all hover:bg-slate-800 active:scale-95">
                                    <span>View Details</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            @elseif (!empty($row['supplier_url']))
                                <a href="{{ $row['supplier_url'] }}" class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-slate-950 px-3 text-[10px] font-black uppercase tracking-wider text-white shadow-xs transition-all hover:bg-slate-800 active:scale-95">
                                    <span>Vendor</span>
                                </a>
                            @else
                                <span class="text-[10px] font-bold text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No records found for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
