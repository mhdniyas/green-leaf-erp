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
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-600">{{ $row['date'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['party'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-600">{{ $row['reference'] ?? '-' }}</td>
                        <td class="min-w-[16rem] px-4 py-3 text-sm font-semibold text-slate-600">{{ $row['description'] ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-black text-emerald-700">Rs. {{ number_format((float) ($row['paid'] ?? 0), 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-black text-amber-700">Rs. {{ number_format((float) ($row['pending'] ?? 0), 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                                {{ $row['status'] ?? 'Open' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No records found for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
