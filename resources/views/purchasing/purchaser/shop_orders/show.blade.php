<x-layouts.app title="Shop Order {{ $order->order_number }}">
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-3 py-4 sm:px-4 lg:px-6">
        @include('purchasing.purchaser.partials.feedback')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-teal-700">Read Only Shop Order</p>
                    <h1 class="mt-1 break-words font-mono text-xl font-black text-slate-950 sm:text-2xl">{{ $order->order_number }}</h1>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <form method="GET" action="{{ route('purchaser.shop-orders.index') }}" class="flex">
                        <input
                            type="date"
                            name="date"
                            value="{{ $date }}"
                            onchange="this.form.submit()"
                            class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-700 outline-none hover:bg-white focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                        >
                    </form>
                    <a href="{{ route('purchaser.shop-orders.index', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm font-black text-slate-700 hover:bg-white">
                        Back
                    </a>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Products</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">{{ $order->items->count() }} items</h2>
                </div>
                <p class="rounded-full bg-teal-50 px-3 py-1 text-xs font-black text-teal-700">
                    {{ number_format((float) $order->items->sum('approved_qty'), 2) }} approved
                </p>
            </div>

            @php
                $sortedItems = $order->items->sortBy(
                    fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
                );
            @endphp

            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-[760px] w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Requested</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Rejected</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Delivered</th>
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($sortedItems as $item)
                            @php
                                $approvedQty = $item->approved_qty === null ? null : (float) $item->approved_qty;
                                $rejectedQty = max(0, (float) $item->requested_qty - (float) ($approvedQty ?? 0));
                            @endphp
                            <tr class="bg-white">
                                <td class="max-w-[300px] px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="shrink-0 rounded-lg bg-slate-100 px-2 py-1 font-mono text-[11px] font-black text-slate-500">{{ $item->product?->sku ?? 'N/A' }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-black text-slate-950">{{ $item->product?->name ?? 'Product' }}</p>
                                            <p class="mt-0.5 truncate text-[10px] font-black uppercase tracking-[0.12em] text-emerald-700">{{ $item->requestedMeasureBreakdownLabel() }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-slate-700">{{ number_format((float) $item->requested_qty, 2) }} {{ $item->unit }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-slate-700">{{ $approvedQty === null ? 'Pending' : number_format($approvedQty, 2).' '.$item->unit }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-rose-700">{{ number_format($rejectedQty, 2) }} {{ $item->unit }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold text-slate-700">{{ number_format((float) ($item->delivered_qty ?? 0), 2) }} {{ $item->unit }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-500">
                                    <p class="max-w-[220px] truncate">{{ $item->notes ?: 'N/A' }}</p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
