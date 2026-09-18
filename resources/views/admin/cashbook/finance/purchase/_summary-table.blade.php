<section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">{{ $title }}</h2>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 rounded-xl">
                <tr>
                    <th class="p-3">{{ ucfirst($kind) }}</th>
                    <th class="p-3 text-right">Invoices</th>
                    <th class="p-3 text-right">Cash</th>
                    <th class="p-3 text-right">Credit</th>
                    <th class="p-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    @php
                        $drilldown = $kind === 'vendor'
                            ? ['vendor_id' => $row->supplier_id]
                            : ($kind === 'purchaser' ? ['purchaser_id' => $row->purchaser_id] : ['category_ids' => [$row->category_id]]);
                        $tags = $kind === 'vendor' && $row->category_tags
                            ? array_map(fn ($tag) => explode('|', $tag, 2), array_filter(explode(',', $row->category_tags)))
                            : [];
                    @endphp
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="p-3 font-bold text-slate-900">
                            <a class="hover:text-emerald-700 hover:underline transition" href="{{ route('admin.cashbook.finance.purchase.report', array_merge(request()->query(), $drilldown)) }}">
                                {{ $row->{$kind.'_name'} ?? $row->category_name ?? 'Uncategorised' }}
                            </a>
                            @if($tags !== [])
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach(array_slice($tags, 0, 4) as [$categoryId, $categoryName])
                                        <a href="{{ route('admin.cashbook.finance.purchase.report', array_merge(request()->query(), ['vendor_id' => $row->supplier_id, 'category_ids' => [$categoryId]])) }}" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600 hover:bg-emerald-100 transition">
                                            {{ $categoryName }}
                                        </a>
                                    @endforeach
                                    @if(count($tags) > 4)
                                        <details class="text-[9px] text-slate-500">
                                            <summary class="cursor-pointer font-bold hover:text-slate-800">+{{ count($tags) - 4 }} more</summary>
                                            <div class="mt-1 pl-1 space-y-0.5">
                                                @foreach(array_slice($tags, 4) as [$categoryId, $categoryName])
                                                    <a href="{{ route('admin.cashbook.finance.purchase.report', array_merge(request()->query(), ['vendor_id' => $row->supplier_id, 'category_ids' => [$categoryId]])) }}" class="block hover:text-emerald-700">
                                                        {{ $categoryName }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="p-3 text-right font-mono font-bold text-slate-700 tabular-nums">{{ number_format((int) $row->invoice_count) }}</td>
                        <td class="p-3 text-right font-mono font-bold text-slate-700 tabular-nums">₹{{ number_format((float) ($row->cash_purchase ?? 0), 2) }}</td>
                        <td class="p-3 text-right font-mono font-bold text-slate-700 tabular-nums">₹{{ number_format((float) ($row->credit_purchase ?? 0), 2) }}</td>
                        <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">₹{{ number_format((float) $row->total_purchase, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-xs font-semibold text-slate-400">No matching purchase items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="border-t border-slate-100 pt-4">{{ $rows->links() }}</div>
    @endif
</section>
