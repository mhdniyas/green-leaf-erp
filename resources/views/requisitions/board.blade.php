@php
    $pendingDailyOrders = $orders->filter(fn($o) => !$o->is_late && ($o->state === 'submitted' || ($o->state === 'update_requested' && !$o->has_pending_revision)));
    $processedDailyOrders = $orders->filter(fn($o) => in_array($o->state, ['approved', 'rejected'], true) && !$o->is_late && !$o->has_pending_revision);
    $updateRequests = $orders->filter(fn($o) => $o->has_pending_revision);
@endphp

<x-layouts.app title="Approval Center (Consolidated Requisitions Board)">
    <div class="mx-auto px-4 py-8 max-w-5xl">
        {{-- Header Section --}}
        <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-700 dark:text-cyan-400">Purchasing Workflow</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-white">
                    Approval Center
                    <span class="sr-only">Consolidated Requisitions Board</span>
                </h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review, adjust, and approve daily shop requisitions, updates, and late submissions.</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                {{-- Date Selector --}}
                <form action="{{ route('requisitions.board') }}" method="GET" class="flex items-center gap-2 bg-white dark:bg-slate-900 px-4 py-2.5 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
                    <label for="date-select" class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">Delivery Date:</label>
                    <input type="date" id="date-select" name="date" value="{{ $date }}" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 dark:text-slate-300 bg-transparent border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                </form>

                {{-- Exports --}}
                <a href="{{ route('requisitions.board.export.csv', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold px-4 py-2.5 rounded-2xl transition shadow-sm border border-slate-200 dark:border-slate-700">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    CSV
                </a>

                <a href="{{ route('requisitions.board.export.pdf', ['date' => $date]) }}" target="_blank" class="inline-flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-950/30 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 text-xs font-bold px-4 py-2.5 rounded-2xl transition shadow-sm border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-8.25A3.375 3.375 0 004.5 11.625v2.625m15 0v3.375A2.625 2.625 0 0116.875 20.25H7.125A2.625 2.625 0 014.5 17.625V14.25m15 0h-15M15 6V3.75A1.125 1.125 0 0013.875 2.625h-3.75A1.125 1.125 0 009 3.75V6" /></svg>
                    PDF
                </a>

                <a href="{{ route('requisitions.approved_board', ['date' => $date]) }}" class="inline-flex items-center gap-1.5 bg-cyan-500 hover:bg-cyan-600 text-white text-xs font-black px-5 py-2.5 rounded-2xl transition shadow-md hover:shadow-lg">
                    Consolidation
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        @if(session('success'))
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/55 text-emerald-850 dark:text-emerald-450 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/55 text-red-850 dark:text-red-450 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                {{ session('error') }}
            </div>
        @endif

        @if($boardFullyApproved)
            <div class="mb-6 rounded-3xl border border-emerald-200 dark:border-emerald-900/55 bg-emerald-50 dark:bg-emerald-950/20 px-5 py-4 text-emerald-900 dark:text-emerald-450 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-sm font-black text-emerald-950 dark:text-white">All Requisitions Approved</h3>
                    <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-500">All daily requisitions for this date have been reviewed and approved.</p>
                </div>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('requisitions.board.save') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <button type="submit" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold px-4 py-2.5 rounded-xl cursor-pointer shadow-sm">
                            Save Board Changes
                        </button>
                    </form>
                    <a href="{{ route('requisitions.approved_board', ['date' => $date]) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black px-5 py-2.5 rounded-xl transition shadow-md">
                        Continue to Approved Board
                    </a>
                </div>
            </div>
        @endif

        @if($updateRequests->isNotEmpty())
            <div class="mb-6 rounded-3xl border border-indigo-200 dark:border-indigo-900/55 bg-indigo-50 dark:bg-indigo-950/20 px-5 py-4 text-indigo-900 dark:text-indigo-400 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-700 dark:text-indigo-300">Shop Owner Updates Waiting</p>
                <p class="mt-1 text-sm font-semibold text-indigo-900 dark:text-indigo-400">One or more shops changed their request after cutoff. Please review the note and update quantities before moving to the approved board.</p>
            </div>
        @endif

        {{-- Tabs Control --}}
        <div class="mb-6 border-b border-slate-200 dark:border-slate-800">
            <nav class="flex gap-2 -mb-px overflow-x-auto" aria-label="Tabs">
                <button
                    id="tab-btn-daily"
                    onclick="switchTab('daily')"
                    class="tab-btn shrink-0 border-b-2 py-4 px-4 text-sm font-bold border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2 transition"
                >
                    Daily Requisitions
                    <span class="rounded-full px-2 py-0.5 text-xs font-black {{ $pendingDailyOrders->isNotEmpty() ? 'bg-cyan-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                        {{ $pendingDailyOrders->count() }}
                    </span>
                </button>
                
                <button
                    id="tab-btn-updates"
                    onclick="switchTab('updates')"
                    class="tab-btn shrink-0 border-b-2 py-4 px-4 text-sm font-bold border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2 transition"
                >
                    Order Updates
                    <span class="rounded-full px-2 py-0.5 text-xs font-black {{ $updateRequests->isNotEmpty() ? 'bg-indigo-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                        {{ $updateRequests->count() }}
                    </span>
                </button>

                <button
                    id="tab-btn-late"
                    onclick="switchTab('late')"
                    class="tab-btn shrink-0 border-b-2 py-4 px-4 text-sm font-bold border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 flex items-center gap-2 transition"
                >
                    Late Requests
                    <span class="rounded-full px-2 py-0.5 text-xs font-black {{ $lateOrders->isNotEmpty() ? 'bg-amber-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                        {{ $lateOrders->count() }}
                    </span>
                </button>
            </nav>
        </div>

        {{-- Tab 1: Daily Requisitions --}}
        <div id="tab-panel-daily" class="tab-panel hidden space-y-4">
            @if($pendingDailyOrders->isEmpty() && $processedDailyOrders->isEmpty())
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 0A48.536 48.536 0 0112 3.75c0 .08-.004.16-.01.238m-2.886 0c.385.023.77.05 1.154.08m-3.456 0A48.108 48.108 0 002.25 6.11v10.39a2.25 2.25 0 002.25 2.25h3" /></svg>
                    <h3 class="mt-4 text-sm font-black text-slate-900 dark:text-white">No daily orders</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">All submissions for this date are already cleared or none have been submitted.</p>
                </div>
            @else
                {{-- Pending Section --}}
                @if($pendingDailyOrders->isNotEmpty())
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-2">Pending Daily Requisitions</h2>
                    <div class="space-y-4">
                        @foreach($pendingDailyOrders as $order)
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden transition hover:shadow-md">
                                {{-- Card Header --}}
                                <div onclick="toggleCard('{{ $order->order_number }}')" class="px-5 py-4 flex items-center justify-between cursor-pointer select-none">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-black text-slate-900 dark:text-white">{{ $order->shop?->name }}</h3>
                                            @if($order->state === 'update_requested')
                                                <span class="rounded-full bg-indigo-50 dark:bg-indigo-950/30 px-2.5 py-0.5 text-[10px] font-black text-indigo-700 dark:text-indigo-400 border border-indigo-150 dark:border-indigo-900/40">
                                                    Update Requested
                                                </span>
                                            @else
                                                <span class="rounded-full bg-amber-50 dark:bg-amber-950/30 px-2.5 py-0.5 text-[10px] font-black text-amber-700 dark:text-amber-450 border border-amber-100 dark:border-amber-900/50">
                                                    Pending Review
                                                </span>
                                            @endif
                                        </div>
                                        @if($order->state === 'update_requested' && $order->update_reason)
                                            <p class="mt-1 text-xs text-slate-605 dark:text-slate-355">
                                                <strong class="font-extrabold text-indigo-700 dark:text-indigo-400">Reason:</strong> {{ $order->update_reason }}
                                            </p>
                                        @endif
                                        <div class="mt-1 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                            <span>#{{ $order->order_number }}</span>
                                            <span>•</span>
                                            <span>Submitted {{ $order->submitted_at?->format('h:i A') ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-cyan-600 dark:text-cyan-400 font-bold">{{ $order->items->count() }} items</span>
                                        <svg id="card-icon-{{ $order->order_number }}" class="w-5 h-5 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                    </div>
                                </div>

                                {{-- Card Content --}}
                                <div id="card-content-{{ $order->order_number }}" class="hidden border-t border-slate-100 dark:border-slate-800 px-5 py-4 bg-slate-50/50 dark:bg-slate-900/40">
                                    <form method="POST" action="{{ route('requisitions.review', $order->order_number) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="approve" id="action-{{ $order->order_number }}">
                                        
                                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 mb-4">
                                            <table class="min-w-full text-left text-xs divide-y divide-slate-150 dark:divide-slate-800">
                                                <thead>
                                                    <tr class="bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                                        <th class="py-3 px-4">Product</th>
                                                        <th class="py-3 px-4 text-center">Requested Qty</th>
                                                        <th class="py-3 px-4 text-center">Approved Qty</th>
                                                        <th class="py-3 px-4 text-center">Fulfillment</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                    @foreach($order->items as $item)
                                                        <tr>
                                                            <td class="py-3 px-4">
                                                                <p class="font-bold text-slate-900 dark:text-white">{{ $item->product?->name }}</p>
                                                                <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase mt-0.5">{{ $item->product?->sku }}</p>
                                                            </td>
                                                            <td class="py-3 px-4 text-center font-bold text-slate-850 dark:text-slate-350">
                                                                {{ number_format($item->requested_qty, 2) }} {{ $item->unit }}
                                                            </td>
                                                            <td class="py-2 px-4">
                                                                <div class="flex items-center justify-center">
                                                                    <input type="number" step="0.01" min="0" name="approved_qty[{{ $item->id }}]" value="{{ number_format($item->requested_qty, 2, '.', '') }}" class="w-20 rounded-lg border border-slate-200 dark:border-slate-800 text-center py-1 text-xs font-black text-slate-800 dark:text-slate-200 dark:bg-slate-900 focus:outline-none focus:border-cyan-500">
                                                                </div>
                                                            </td>
                                                            <td class="py-2 px-4">
                                                                <div class="flex items-center justify-center">
                                                                    <select name="fulfillment_types[{{ $item->id }}]" class="rounded-lg border border-slate-200 dark:border-slate-800 py-1 text-xs font-bold text-slate-700 dark:text-slate-300 dark:bg-slate-900 focus:outline-none">
                                                                        <option value="warehouse" {{ ($item->fulfillment_type ?: 'warehouse') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                                                        <option value="selection" {{ $item->fulfillment_type === 'selection' ? 'selected' : '' }}>Selection</option>
                                                                    </select>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2.5">
                                            <button type="submit" onclick="document.getElementById('action-{{ $order->order_number }}').value='reject'" class="rounded-xl border border-red-200 dark:border-red-900/60 bg-white dark:bg-slate-900 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 px-4 py-2.5 text-xs font-black cursor-pointer shadow-sm">
                                                Reject Order
                                            </button>
                                            <button type="submit" onclick="document.getElementById('action-{{ $order->order_number }}').value='approve'" class="rounded-xl bg-cyan-500 hover:bg-cyan-600 text-white px-5 py-2.5 text-xs font-black cursor-pointer shadow-md">
                                                Approve Requisition
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Processed Section --}}
                @if($processedDailyOrders->isNotEmpty())
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mt-6 mb-2">Processed Daily Requisitions</h2>
                    <div class="space-y-4">
                        @foreach($processedDailyOrders as $order)
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden opacity-85 hover:opacity-100 transition">
                                <div onclick="toggleCard('{{ $order->order_number }}')" class="px-5 py-4 flex items-center justify-between cursor-pointer select-none">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-black text-slate-700 dark:text-slate-300">{{ $order->shop?->name }}</h3>
                                            @if($order->state === 'approved')
                                                <span class="rounded-full bg-emerald-50 dark:bg-emerald-950/30 px-2.5 py-0.5 text-[10px] font-black text-emerald-700 dark:text-emerald-450 border border-emerald-150 dark:border-emerald-900/40">
                                                    Approved
                                                </span>
                                            @else
                                                <span class="rounded-full bg-red-50 dark:bg-red-950/30 px-2.5 py-0.5 text-[10px] font-black text-red-700 dark:text-red-450 border border-red-150 dark:border-red-900/40">
                                                    Rejected
                                                </span>
                                            @endif
                                        </div>
                                        <div class="mt-1 flex items-center gap-3 text-xs text-slate-400 dark:text-slate-500">
                                            <span>#{{ $order->order_number }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-500 dark:text-slate-400 font-bold">{{ $order->items->count() }} items</span>
                                        <svg id="card-icon-{{ $order->order_number }}" class="w-5 h-5 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                    </div>
                                </div>

                                <div id="card-content-{{ $order->order_number }}" class="hidden border-t border-slate-100 dark:border-slate-800 px-5 py-4 bg-slate-50/20 dark:bg-slate-900/10">
                                    <div class="overflow-x-auto rounded-xl border border-slate-150 dark:border-slate-800 bg-white dark:bg-slate-900">
                                        <table class="min-w-full text-left text-xs divide-y divide-slate-150 dark:divide-slate-800">
                                            <thead>
                                                <tr class="bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                                    <th class="py-3 px-4">Product</th>
                                                    <th class="py-3 px-4 text-center">Requested Qty</th>
                                                    <th class="py-3 px-4 text-center">Approved Qty</th>
                                                    <th class="py-3 px-4 text-center">Fulfillment</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-400">
                                                @foreach($order->items as $item)
                                                    <tr>
                                                        <td class="py-3 px-4">
                                                            <p class="font-bold text-slate-800 dark:text-slate-200">{{ $item->product?->name }}</p>
                                                            <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase mt-0.5">{{ $item->product?->sku }}</p>
                                                        </td>
                                                        <td class="py-3 px-4 text-center">
                                                            {{ number_format($item->requested_qty, 2) }} {{ $item->unit }}
                                                        </td>
                                                        <td class="py-3 px-4 text-center font-bold text-slate-800 dark:text-slate-200">
                                                            {{ number_format($item->approved_qty ?? 0.0, 2) }} {{ $item->unit }}
                                                        </td>
                                                        <td class="py-3 px-4 text-center uppercase text-[10px] tracking-wider font-extrabold text-slate-400">
                                                            {{ $item->fulfillment_type ?: 'warehouse' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        {{-- Tab 2: Order Update Requests --}}
        <div id="tab-panel-updates" class="tab-panel hidden space-y-4">
            @if($updateRequests->isEmpty())
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    <h3 class="mt-4 text-sm font-black text-slate-900 dark:text-white">No update requests</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">All requested updates have been resolved or none are pending.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($updateRequests as $order)
                        @php
                            $revision = $order->latestPendingRevision;
                        @endphp
                        <div class="bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-950 rounded-2xl shadow-sm overflow-hidden transition hover:shadow-md">
                            {{-- Card Header --}}
                            <div onclick="toggleCard('update-{{ $order->order_number }}')" class="px-5 py-4 flex items-center justify-between cursor-pointer select-none">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-black text-slate-900 dark:text-white">{{ $order->shop?->name }}</h3>
                                        <span class="rounded-full bg-indigo-50 dark:bg-indigo-950/30 px-2.5 py-0.5 text-[10px] font-black text-indigo-700 dark:text-indigo-400 border border-indigo-150 dark:border-indigo-900/50">
                                            Update #{{ $revision->revision_no ?? 2 }} Pending
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-650 dark:text-slate-350">
                                        <strong class="font-extrabold text-indigo-700 dark:text-indigo-400">Reason:</strong> {{ $revision->reason ?? 'Requested changes.' }}
                                    </p>
                                    <div class="mt-1.5 flex items-center gap-3 text-xs text-slate-400 dark:text-slate-500">
                                        <span>#{{ $order->order_number }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-indigo-600 dark:text-indigo-400 font-bold">{{ $revision->items->count() }} changes</span>
                                    <svg id="card-icon-update-{{ $order->order_number }}" class="w-5 h-5 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </div>
                            </div>

                            {{-- Card Content --}}
                            <div id="card-content-update-{{ $order->order_number }}" class="hidden border-t border-slate-100 dark:border-slate-800 px-5 py-4 bg-indigo-50/20 dark:bg-indigo-950/10">
                                <form method="POST" action="{{ route('requisitions.approve-update', $order->order_number) }}" id="update-form-{{ $order->order_number }}">
                                    @csrf
                                    
                                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 mb-4">
                                        <table class="min-w-full text-left text-xs divide-y divide-slate-150 dark:divide-slate-800">
                                            <thead>
                                                <tr class="bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                                    <th class="py-3 px-4">Product</th>
                                                    <th class="py-3 px-4 text-center">Previous Qty</th>
                                                    <th class="py-3 px-4 text-center">Requested Qty</th>
                                                    <th class="py-3 px-4 text-center">Difference</th>
                                                    <th class="py-3 px-4 text-center">Final Approve Qty</th>
                                                    <th class="py-3 px-4 text-center">Fulfillment</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                @foreach($revision->items as $item)
                                                    @php
                                                        $diff = (float)$item->new_requested_qty - (float)$item->old_requested_qty;
                                                        $existingItem = $order->items->keyBy('product_id')->get($item->product_id);
                                                    @endphp
                                                    <tr>
                                                        <td class="py-3 px-4">
                                                            <p class="font-bold text-slate-900 dark:text-white">{{ $item->product?->name }}</p>
                                                            <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase mt-0.5">{{ $item->product?->sku }}</p>
                                                        </td>
                                                        <td class="py-3 px-4 text-center text-slate-500 dark:text-slate-400">
                                                            {{ number_format($item->old_requested_qty, 2) }} {{ $item->product?->unit }}
                                                        </td>
                                                        <td class="py-3 px-4 text-center font-bold text-slate-850 dark:text-slate-350">
                                                            {{ number_format($item->new_requested_qty, 2) }} {{ $item->product?->unit }}
                                                        </td>
                                                        <td class="py-3 px-4 text-center">
                                                            <span class="font-extrabold text-xs px-2 py-0.5 rounded-full {{ $diff > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-450' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-450' }}">
                                                                {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }}
                                                            </span>
                                                        </td>
                                                        <td class="py-2 px-4">
                                                            <div class="flex items-center justify-center">
                                                                <input type="number" step="0.01" min="0" name="approved_qty[{{ $item->product_id }}]" value="{{ number_format($item->new_requested_qty, 2, '.', '') }}" class="w-20 rounded-lg border border-slate-200 dark:border-slate-800 text-center py-1 text-xs font-black text-slate-800 dark:text-slate-200 dark:bg-slate-900 focus:outline-none focus:border-indigo-500">
                                                            </div>
                                                        </td>
                                                        <td class="py-2 px-4">
                                                            <div class="flex items-center justify-center">
                                                                <select name="fulfillment_types[{{ $item->product_id }}]" class="rounded-lg border border-slate-200 dark:border-slate-800 py-1 text-xs font-bold text-slate-700 dark:text-slate-300 dark:bg-slate-900 focus:outline-none">
                                                                    <option value="warehouse" {{ (($existingItem?->fulfillment_type) ?: 'warehouse') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                                                    <option value="selection" {{ ($existingItem?->fulfillment_type ?? '') === 'selection' ? 'selected' : '' }}>Selection</option>
                                                                </select>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2.5">
                                        <button type="button" onclick="document.getElementById('reject-update-form-{{ $order->order_number }}').submit()" class="rounded-xl border border-red-200 dark:border-red-900/60 bg-white dark:bg-slate-900 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 px-4 py-2.5 text-xs font-black cursor-pointer shadow-sm">
                                            Decline Update
                                        </button>
                                        <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-750 text-white px-5 py-2.5 text-xs font-black cursor-pointer shadow-md">
                                            Approve & Apply Update
                                        </button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('requisitions.reject-update', $order->order_number) }}" id="reject-update-form-{{ $order->order_number }}" class="hidden">
                                    @csrf
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Tab 3: Late Requests --}}
        <div id="tab-panel-late" class="tab-panel hidden space-y-4">
            @if($lateOrders->isEmpty())
                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <h3 class="mt-4 text-sm font-black text-slate-900 dark:text-white">No late requisitions</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No late requisitions are currently pending decision.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($lateOrders as $order)
                        <div class="bg-white dark:bg-slate-900 border border-amber-250 dark:border-amber-950 rounded-2xl shadow-sm overflow-hidden transition hover:shadow-md">
                            {{-- Card Header --}}
                            <div onclick="toggleCard('late-{{ $order->order_number }}')" class="px-5 py-4 flex items-center justify-between cursor-pointer select-none">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-black text-slate-900 dark:text-white">{{ $order->shop?->name }}</h3>
                                        <span class="rounded-full bg-amber-50 dark:bg-amber-950/30 px-2.5 py-0.5 text-[10px] font-black text-amber-700 dark:text-amber-450 border border-amber-150 dark:border-amber-900/40">
                                            Late Submission
                                        </span>
                                        @if($order->state === 'update_requested')
                                            <span class="rounded-full bg-indigo-50 dark:bg-indigo-950/30 px-2.5 py-0.5 text-[10px] font-black text-indigo-700 dark:text-indigo-400 border border-indigo-150 dark:border-indigo-900/40">
                                                Update Requested
                                            </span>
                                        @endif
                                    </div>
                                    @if($order->state === 'update_requested' && $order->update_reason)
                                        <p class="mt-1 text-xs text-slate-605 dark:text-slate-355">
                                            <strong class="font-extrabold text-indigo-700 dark:text-indigo-400">Reason:</strong> {{ $order->update_reason }}
                                        </p>
                                    @endif
                                    <div class="mt-1 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                        <span>#{{ $order->order_number }}</span>
                                        <span>•</span>
                                        <span>Submitted {{ $order->submitted_at?->format('h:i A') ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-amber-600 dark:text-amber-450 font-bold">{{ $order->items->count() }} items</span>
                                    <svg id="card-icon-late-{{ $order->order_number }}" class="w-5 h-5 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                </div>
                            </div>

                            {{-- Card Content --}}
                            <div id="card-content-late-{{ $order->order_number }}" class="hidden border-t border-slate-100 dark:border-slate-800 px-5 py-4 bg-amber-50/20 dark:bg-amber-950/10">
                                <form method="POST" action="{{ route('requisitions.review', $order->order_number) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approve" id="action-late-{{ $order->order_number }}">
                                    
                                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 mb-4">
                                        <table class="min-w-full text-left text-xs divide-y divide-slate-150 dark:divide-slate-800">
                                            <thead>
                                                <tr class="bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider">
                                                    <th class="py-3 px-4">Product</th>
                                                    <th class="py-3 px-4 text-center">Requested Qty</th>
                                                    <th class="py-3 px-4 text-center">Approved Qty</th>
                                                    <th class="py-3 px-4 text-center">Fulfillment</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                @foreach($order->items as $item)
                                                    <tr>
                                                        <td class="py-3 px-4">
                                                            <p class="font-bold text-slate-900 dark:text-white">{{ $item->product?->name }}</p>
                                                            <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase mt-0.5">{{ $item->product?->sku }}</p>
                                                        </td>
                                                        <td class="py-3 px-4 text-center font-bold text-slate-850 dark:text-slate-350">
                                                            {{ number_format($item->requested_qty, 2) }} {{ $item->unit }}
                                                        </td>
                                                        <td class="py-2 px-4">
                                                            <div class="flex items-center justify-center">
                                                                <input type="number" step="0.01" min="0" name="approved_qty[{{ $item->id }}]" value="{{ number_format($item->requested_qty, 2, '.', '') }}" class="w-20 rounded-lg border border-slate-200 dark:border-slate-800 text-center py-1 text-xs font-black text-slate-800 dark:text-slate-200 dark:bg-slate-900 focus:outline-none focus:border-amber-500">
                                                            </div>
                                                        </td>
                                                        <td class="py-2 px-4">
                                                            <div class="flex items-center justify-center">
                                                                <select name="fulfillment_types[{{ $item->id }}]" class="rounded-lg border border-slate-200 dark:border-slate-800 py-1 text-xs font-bold text-slate-700 dark:text-slate-300 dark:bg-slate-900 focus:outline-none">
                                                                    <option value="warehouse" {{ ($item->fulfillment_type ?: 'warehouse') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                                                    <option value="selection" {{ $item->fulfillment_type === 'selection' ? 'selected' : '' }}>Selection</option>
                                                                </select>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2.5">
                                        <button type="submit" onclick="document.getElementById('action-late-{{ $order->order_number }}').value='reject'" class="rounded-xl border border-red-200 dark:border-red-900/60 bg-white dark:bg-slate-900 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 px-4 py-2.5 text-xs font-black cursor-pointer shadow-sm">
                                            Reject Request
                                        </button>
                                        <button type="submit" onclick="document.getElementById('action-late-{{ $order->order_number }}').value='approve'" class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-5 py-2.5 text-xs font-black cursor-pointer shadow-md">
                                            Accept & Approve Late Requisition
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Tab switcher
        function switchTab(tabName) {
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.add('hidden');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-cyan-500', 'text-cyan-600', 'bg-cyan-50/50', 'dark:bg-slate-800/50', 'dark:text-cyan-400');
                btn.classList.add('border-transparent', 'text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
            });

            const activePanel = document.getElementById('tab-panel-' + tabName);
            if (activePanel) {
                activePanel.classList.remove('hidden');
            }
            const activeBtn = document.getElementById('tab-btn-' + tabName);
            if (activeBtn) {
                activeBtn.classList.add('border-cyan-500', 'text-cyan-600', 'bg-cyan-50/50', 'dark:bg-slate-800/50', 'dark:text-cyan-400');
                activeBtn.classList.remove('border-transparent', 'text-slate-500', 'hover:text-slate-700', 'dark:text-slate-400', 'dark:hover:text-slate-200');
            }
            localStorage.setItem('approval-center-tab', tabName);
        }

        // Collapsible cards toggle
        function toggleCard(id) {
            const content = document.getElementById('card-content-' + id);
            const icon = document.getElementById('card-icon-' + id);
            if (content && icon) {
                content.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Restore last active tab or default to 'daily'
            const lastTab = localStorage.getItem('approval-center-tab') || 'daily';
            switchTab(lastTab);
        });
    </script>
    @endpush
</x-layouts.app>
