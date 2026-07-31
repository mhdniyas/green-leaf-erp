@extends('purchase-manager.layouts.app')

@section('title', 'Edit Shop Orders')
@section('page_title', 'Edit Shop Orders')
@section('page_description', 'Pick a business date, open any shop marketplace, and add or update daily order products.')

@section('page_actions')
    <form action="{{ route('purchasing.shop-orders.index') }}" method="GET" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
        <label for="shop-orders-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Date</label>
        <input type="date" id="shop-orders-date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="border-0 bg-transparent p-0 text-xs font-bold text-slate-700 focus:outline-none focus:ring-0">
        @if ($status !== '')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        @if ($search !== '')
            <input type="hidden" name="search" value="{{ $search }}">
        @endif
    </form>
@endsection

@section('content')
    <div class="space-y-6">
        <section class="overflow-hidden rounded-[2.5rem] bg-slate-950 text-white shadow-[0_24px_60px_rgba(15,23,42,0.28)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.22),_transparent_38%),linear-gradient(135deg,_#020617_0%,_#0f172a_55%,_#082f49_100%)] px-5 py-6 sm:px-7 sm:py-7">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-cyan-300">Purchase Manager</p>
                <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Daily shop marketplace</h2>
                <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">Same product window shop owners use. Admin can create or update quantities for any shop on {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}.</p>

                <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-5">
                    @php
                        $totalShops = array_sum($statusCounts);
                    @endphp
                    @foreach ([
                        '' => ['label' => 'All shops', 'count' => $totalShops],
                        'none' => ['label' => 'No order', 'count' => $statusCounts['none']],
                        'submitted' => ['label' => 'Submitted', 'count' => $statusCounts['submitted']],
                        'update_requested' => ['label' => 'Updates', 'count' => $statusCounts['update_requested']],
                        'approved' => ['label' => 'Approved', 'count' => $statusCounts['approved']],
                    ] as $statusKey => $meta)
                        <a href="{{ route('purchasing.shop-orders.index', array_filter(['date' => $date, 'status' => $statusKey, 'search' => $search])) }}"
                            class="rounded-xl px-3 py-2 transition {{ $status === $statusKey ? 'bg-cyan-400 text-slate-950' : 'bg-white/10 hover:bg-white/15' }}">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] {{ $status === $statusKey ? 'text-slate-700' : 'text-slate-200' }}">{{ $meta['label'] }}</p>
                            <p class="mt-1 text-lg font-black">{{ (int) $meta['count'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('purchasing.shop-orders.index') }}" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-[180px_180px_1fr_auto] lg:rounded-[2rem] lg:p-4">
            <input type="date" name="date" value="{{ $date }}" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            <select name="status" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                <option value="">All status</option>
                <option value="none" @selected($status === 'none')>No order</option>
                <option value="submitted" @selected($status === 'submitted')>Submitted</option>
                <option value="update_requested" @selected($status === 'update_requested')>Update requested</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search shop, code, order..." class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            <button type="submit" class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                Filter
            </button>
        </form>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-xs font-semibold text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-700">
                        <tr>
                            <th class="px-4 py-3 sm:px-6">Shop</th>
                            <th class="px-4 py-3 sm:px-6">Order</th>
                            <th class="px-4 py-3 sm:px-6">Status</th>
                            <th class="px-4 py-3 sm:px-6 text-right">Items</th>
                            <th class="px-4 py-3 sm:px-6 text-right">Requested</th>
                            <th class="px-4 py-3 sm:px-6 text-right">Approved</th>
                            <th class="px-4 py-3 sm:px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white font-medium">
                        @forelse ($shops as $row)
                            @php
                                /** @var \App\Models\Shop $shop */
                                $shop = $row['shop'];
                                /** @var \App\Models\ShopOrder|null $order */
                                $order = $row['order'];
                            @endphp
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-4 sm:px-6">
                                    <p class="font-black text-slate-950">{{ $shop->name }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-400">{{ $shop->code }}</p>
                                </td>
                                <td class="px-4 py-4 sm:px-6 font-mono text-xs font-bold text-teal-700">
                                    {{ $order?->order_number ?? '—' }}
                                </td>
                                <td class="px-4 py-4 sm:px-6">
                                    @if ($order)
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">
                                            {{ $order->displayStateLabel() }}
                                        </span>
                                        @if ($row['is_locked'])
                                            <span class="ml-1 inline-flex rounded-full bg-rose-50 px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-rose-700">Locked</span>
                                        @endif
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-amber-700">No order</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 sm:px-6 text-right font-bold text-slate-900">{{ $row['items_count'] }}</td>
                                <td class="px-4 py-4 sm:px-6 text-right font-bold text-slate-900">{{ number_format($row['requested_qty'], 2) }}</td>
                                <td class="px-4 py-4 sm:px-6 text-right font-bold text-slate-900">{{ number_format($row['approved_qty'], 2) }}</td>
                                <td class="px-4 py-4 sm:px-6 text-right">
                                    <a href="{{ route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => $date]) }}"
                                        class="inline-flex items-center justify-center rounded-xl {{ $row['can_edit'] ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }} px-4 py-2 text-xs font-black transition">
                                        {{ $order ? ($row['can_edit'] ? 'Edit' : 'View') : 'New Order' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm font-semibold text-slate-500 sm:px-6">
                                    No shops matched this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
