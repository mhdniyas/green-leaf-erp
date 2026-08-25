<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 p-4"><h2 class="font-black text-slate-950">{{ $title }}</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">{{ ucfirst($kind) }}</th><th class="p-3 text-right">Invoices</th><th class="p-3 text-right">Cash</th><th class="p-3 text-right">Credit</th><th class="p-3 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    @php
                        $drilldown = $kind === 'vendor' ? ['vendor_id' => $row->supplier_id] : ($kind === 'purchaser' ? ['purchaser_id' => $row->purchaser_id] : ['category_ids' => [$row->category_id]]);
                        $tags = $kind === 'vendor' && $row->category_tags ? array_map(fn ($tag) => explode('|', $tag, 2), array_filter(explode(',', $row->category_tags))) : [];
                    @endphp
                    <tr>
                        <td class="p-3 font-bold text-slate-900">
                            <a class="hover:text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.purchase.report', array_merge(request()->query(), $drilldown)) }}">{{ $row->{$kind.'_name'} ?? $row->category_name ?? 'Uncategorised' }}</a>
                            @if($tags !== [])
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach(array_slice($tags, 0, 4) as [$categoryId, $categoryName])
                                        <a href="{{ route('admin.cashbook.finance.purchase.report', array_merge(request()->query(), ['vendor_id' => $row->supplier_id, 'category_ids' => [$categoryId]])) }}" class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-600 hover:bg-emerald-100">{{ $categoryName }}</a>
                                    @endforeach
                                    @if(count($tags) > 4)
                                        <details class="text-[9px] text-slate-500">
                                            <summary class="cursor-pointer">+{{ count($tags) - 4 }} more</summary>
                                            @foreach(array_slice($tags, 4) as [$categoryId, $categoryName])
                                                <a href="{{ route('admin.cashbook.finance.purchase.report', array_merge(request()->query(), ['vendor_id' => $row->supplier_id, 'category_ids' => [$categoryId]])) }}" class="block hover:text-emerald-700">{{ $categoryName }}</a>
                                            @endforeach
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="p-3 text-right font-mono">{{ number_format((int) $row->invoice_count) }}</td>
                        <td class="p-3 text-right font-mono">₹{{ number_format((float) ($row->cash_purchase ?? 0), 2) }}</td>
                        <td class="p-3 text-right font-mono">₹{{ number_format((float) ($row->credit_purchase ?? 0), 2) }}</td>
                        <td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $row->total_purchase, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-5 text-center text-slate-400">No matching purchase items.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rows instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="p-4">{{ $rows->links() }}</div>@endif
</section>
