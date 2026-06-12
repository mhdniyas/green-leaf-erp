@extends('purchase-manager.layouts.app')

@section('title', 'Daily GRN Approval')
@section('page_title', 'Daily GRN Approval')
@section('page_description', 'Review all purchased items submitted by the purchaser. Approve to push stock into inventory with warehouse receive pending.')

@push('styles')
<style>
    /* Mobile-first product cards */
    .approval-card {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s;
    }
    .approval-card:last-child { border-bottom: none; }

    .product-details {
        display: none;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        padding: 16px;
    }
    .product-details.open { display: block; }

    .chevron-icon {
        transition: transform 0.2s ease;
        flex-shrink: 0;
    }
    .chevron-icon.rotated { transform: rotate(180deg); }

    @media (max-width: 640px) {
        .approval-action-bar {
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
        }
        .approval-action-bar > p { font-size: 11px; }
        .approve-all-btn { width: 100%; justify-content: center; }
    }
</style>
@endpush

@section('content')
    {{-- Date + Back bar --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form action="{{ route('purchasing.grns.daily-approval') }}" method="GET"
            class="flex items-center gap-3">
            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Date</label>
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-cyan-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
        </form>
        <div class="flex items-center gap-3">
            @if($totalPending > 0)
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-black text-amber-700">
                    {{ $totalPending }} GRN{{ $totalPending > 1 ? 's' : '' }} Pending
                </span>
            @endif
            <a href="{{ route('purchasing.grns.index') }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 transition">
                ← All Receipts
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-5 flex items-center gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-xs font-bold text-emerald-800">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-xs font-bold text-rose-800">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    @if($totalPending === 0)
        <div class="purchase-manager-panel p-10 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-emerald-100 bg-emerald-50">
                <svg class="h-7 w-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-sm font-black text-slate-900">All Clear</h3>
            <p class="mt-1 text-xs text-slate-500">No pending GRNs for {{ $date }}.</p>
        </div>
    @else
        {{-- Approve All Banner --}}
        <div class="purchase-manager-panel mb-5 p-4 sm:p-5">
            <div class="approval-action-bar flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-sm font-black text-slate-900">Ready to Approve?</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Approves {{ $totalPending }} GRN(s) → creates stock batches marked
                        <span class="font-bold text-amber-600">warehouse pending</span> until receiver confirms.
                    </p>
                </div>
                <form action="{{ route('purchasing.grns.daily-approval.approve') }}" method="POST" class="shrink-0"
                    onsubmit="return confirm('Approve all {{ $totalPending }} pending GRN(s) for {{ $date }}?')">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    <button type="submit"
                        class="approve-all-btn flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white shadow-sm hover:bg-emerald-700 transition cursor-pointer border-0">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Approve All
                    </button>
                </form>
            </div>
        </div>

        {{-- Daily Order Products --}}
        @if(count($dailyItems) > 0)
            <div class="mb-5">
                <div class="mb-2 flex items-center gap-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Daily Order</p>
                    <span class="rounded-full border border-cyan-100 bg-cyan-50 px-2 py-0.5 text-[9px] font-black text-cyan-700">{{ count($dailyItems) }}</span>
                </div>
                <div class="purchase-manager-panel overflow-hidden">
                    @foreach($dailyItems as $item)
                        @php $collapseId = 'daily-' . $item['product_id']; @endphp
                        <div class="approval-card">
                            <button type="button"
                                onclick="toggleCard('{{ $collapseId }}')"
                                class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left border-0 bg-transparent cursor-pointer hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="shrink-0 rounded-full bg-cyan-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-cyan-700">Daily</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $item['product_name'] }}</p>
                                        <p class="text-[10px] text-slate-400">{{ $item['sku'] }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <div class="text-right">
                                        <p class="text-sm font-black text-slate-900">{{ number_format($item['total_qty'], 1) }} {{ $item['unit'] }}</p>
                                        <p class="text-[10px] font-bold text-emerald-700">Avg INR {{ number_format($item['avg_price'], 2) }}</p>
                                    </div>
                                    <svg id="{{ $collapseId }}-chevron" class="chevron-icon h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </button>
                            <div id="{{ $collapseId }}" class="product-details">
                                @include('purchase-manager.grns.partials.approval-product-detail', ['item' => $item])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Extra Products --}}
        @if(count($extraItems) > 0)
            <div class="mb-5">
                <div class="mb-2 flex items-center gap-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Extras</p>
                    <span class="rounded-full border border-amber-100 bg-amber-50 px-2 py-0.5 text-[9px] font-black text-amber-700">{{ count($extraItems) }} not in daily order</span>
                </div>
                <div class="purchase-manager-panel overflow-hidden">
                    @foreach($extraItems as $item)
                        @php $collapseId = 'extra-' . $item['product_id']; @endphp
                        <div class="approval-card">
                            <button type="button"
                                onclick="toggleCard('{{ $collapseId }}')"
                                class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left border-0 bg-transparent cursor-pointer hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-700">Extra</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $item['product_name'] }}</p>
                                        <p class="text-[10px] text-slate-400">{{ $item['sku'] }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <div class="text-right">
                                        <p class="text-sm font-black text-slate-900">{{ number_format($item['total_qty'], 1) }} {{ $item['unit'] }}</p>
                                        <p class="text-[10px] font-bold text-emerald-700">Avg INR {{ number_format($item['avg_price'], 2) }}</p>
                                    </div>
                                    <svg id="{{ $collapseId }}-chevron" class="chevron-icon h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </button>
                            <div id="{{ $collapseId }}" class="product-details">
                                @include('purchase-manager.grns.partials.approval-product-detail', ['item' => $item])
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    @push('scripts')
    <script>
        function toggleCard(id) {
            const el = document.getElementById(id);
            const chevron = document.getElementById(id + '-chevron');
            el.classList.toggle('open');
            chevron.classList.toggle('rotated');
        }
    </script>
    @endpush
@endsection
