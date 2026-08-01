<x-layouts.app title="Shop Orders">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-3 py-4 sm:px-4 lg:px-6">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_58%,_#164e63_100%)] px-3 py-2.5 sm:px-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.16em] text-cyan-200">Purchaser Reference</p>
                            <h1 class="truncate text-base font-black tracking-tight sm:text-lg">Shop orders</h1>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5 text-center">
                            @foreach (['submitted' => 'Sub', 'update_requested' => 'Upd', 'approved' => 'Appr', 'rejected' => 'Rej'] as $stateKey => $label)
                                <a href="{{ route('purchaser.shop-orders.index', array_filter(['date' => $date, 'status' => $stateKey, 'source' => $source, 'search' => $search])) }}" class="rounded-lg bg-white/10 px-2 py-1 transition hover:bg-white/15">
                                    <p class="text-[8px] font-black uppercase text-slate-300">{{ $label }}</p>
                                    <p class="text-sm font-black">{{ (int) ($statusCounts[$stateKey] ?? 0) }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-2 lg:flex lg:items-center">
                        <form method="GET" action="{{ route('purchaser.shop-orders.index') }}" class="min-w-0">
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="source" value="{{ $source }}">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-9 w-full rounded-lg border border-white/10 bg-white/10 px-3 text-xs font-bold text-white outline-none lg:w-40">
                        </form>
                        <a href="{{ route('purchaser.add-ons.create', ['date' => $date]) }}" class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-500 px-3 text-xs font-black text-white transition hover:bg-emerald-400">
                            Add-on
                        </a>
                        <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/15 bg-white/10 px-3 text-xs font-black text-white transition hover:bg-white/15">
                            Daily
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('purchaser.shop-orders.index') }}" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm md:grid-cols-[140px_160px_160px_1fr_auto] lg:p-3">
            <input type="date" name="date" value="{{ $date }}" class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            <select name="status" class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                <option value="">All status</option>
                <option value="submitted" @selected($status === 'submitted')>Submitted</option>
                <option value="update_requested" @selected($status === 'update_requested')>Update requested</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
            <select name="source" class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                <option value="">All source</option>
                <option value="shop_owner" @selected($source === 'shop_owner')>Shop orders</option>
                <option value="admin_direct_purchase" @selected($source === 'admin_direct_purchase')>Direct purchase</option>
            </select>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search order, shop, product..." class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            <button type="submit" class="h-10 rounded-xl bg-slate-950 px-5 text-xs font-black text-white transition hover:bg-slate-800">
                Filter
            </button>
        </form>

        <section class="space-y-3 lg:hidden">
            @forelse ($orders as $order)
                <a href="{{ route('purchaser.shop-orders.show', $order->order_number) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-slate-950">{{ $order->demandSourceLabel() }}</p>
                            <p class="mt-1 font-mono text-xs font-bold text-teal-700">{{ $order->order_number }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">{{ $order->displayStateLabel() }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs font-bold">
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <p class="text-slate-400">Items</p>
                            <p class="mt-1 text-slate-950">{{ $order->items_count }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <p class="text-slate-400">Requested</p>
                            <p class="mt-1 text-slate-950">{{ number_format((float) $order->items->sum('requested_qty'), 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <p class="text-slate-400">Approved</p>
                            <p class="mt-1 text-slate-950">{{ number_format((float) $order->items->sum('approved_qty'), 2) }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">
                    No shop orders found for this filter.
                </div>
            @endforelse
        </section>

        <section class="hidden overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm lg:block">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Order</th>
                        <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Source</th>
                        <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Products</th>
                        <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Qty</th>
                        <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-mono text-xs font-black text-teal-700">{{ $order->order_number }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $order->business_date->format('d M Y') }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-black text-slate-950">{{ $order->demandSourceLabel() }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ str($order->order_source)->replace('_', ' ')->title() }}</p>
                            </td>
                            <td class="px-5 py-4 text-sm font-bold text-slate-700">{{ $order->items_count }} items</td>
                            <td class="px-5 py-4 text-sm font-bold text-slate-700">
                                {{ number_format((float) $order->items->sum('requested_qty'), 2) }} requested
                                <span class="block text-xs text-slate-400">{{ number_format((float) $order->items->sum('approved_qty'), 2) }} approved</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">{{ $order->displayStateLabel() }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('purchaser.shop-orders.show', $order->order_number) }}" class="text-sm font-black text-teal-700 hover:text-teal-900">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm font-bold text-slate-500">No shop orders found for this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{ $orders->links() }}
    </div>
</x-layouts.app>
