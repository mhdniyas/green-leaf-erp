<x-layouts.app title="Shop Orders">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-3 py-4 sm:px-4 lg:px-6">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.24),_transparent_34%),linear-gradient(135deg,_#0f172a_0%,_#111827_58%,_#164e63_100%)] px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-200">Purchaser Reference</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:text-2xl">Shop orders</h1>
                        <p class="mt-1.5 max-w-2xl text-sm font-semibold leading-6 text-slate-200">Read-only view of shop demand and direct-purchase add-ons for the selected business date.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex">
                        <a href="{{ route('purchaser.add-ons.create', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-500 px-4 text-sm font-black text-white transition hover:bg-emerald-400">
                            Add-ons
                        </a>
                        <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-black text-white transition hover:bg-white/15">
                            Daily
                        </a>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (['submitted' => 'Submitted', 'update_requested' => 'Updates', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $stateKey => $label)
                        <a href="{{ route('purchaser.shop-orders.index', array_filter(['date' => $date, 'status' => $stateKey, 'source' => $source, 'search' => $search])) }}" class="rounded-xl bg-white/10 px-3 py-2 transition hover:bg-white/15">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">{{ $label }}</p>
                            <p class="mt-1 text-lg font-black">{{ (int) ($statusCounts[$stateKey] ?? 0) }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('purchaser.shop-orders.index') }}" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-[160px_180px_180px_1fr_auto] lg:rounded-[2rem] lg:p-4">
            <input type="date" name="date" value="{{ $date }}" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            <select name="status" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                <option value="">All status</option>
                <option value="submitted" @selected($status === 'submitted')>Submitted</option>
                <option value="update_requested" @selected($status === 'update_requested')>Update requested</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
            <select name="source" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                <option value="">All source</option>
                <option value="shop_owner" @selected($source === 'shop_owner')>Shop orders</option>
                <option value="admin_direct_purchase" @selected($source === 'admin_direct_purchase')>Direct purchase</option>
            </select>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search order, shop, product..." class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            <button type="submit" class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
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
