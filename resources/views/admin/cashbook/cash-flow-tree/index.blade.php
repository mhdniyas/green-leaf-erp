@extends('admin.cashbook.layouts.app')

@section('title', 'Monthly Cash Flow Tree — Green Leaf')

@section('content')
<div x-data="cashFlowTreeApp()" class="space-y-4 pb-12">
    
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    {{-- COMPACT TOP TOOLBAR & VIEW SWITCHER                                       --}}
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        {{-- Left: Title & Month Picker Form --}}
        <div class="flex items-center gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                    <a href="{{ route('admin.cashbook.index') }}" class="hover:text-brand-600 transition">Cashbook</a>
                    <span>/</span>
                    <span class="text-slate-800">Monthly Cash Flow</span>
                </div>
                <h1 class="text-xl font-black text-slate-900 font-sans tracking-tight">Cash Flow Hierarchy &amp; Matrix</h1>
            </div>

            <form method="GET" action="{{ route('admin.cashbook.cash-flow-tree.index') }}" class="flex items-center gap-2">
                <input 
                    type="month" 
                    name="month" 
                    value="{{ $month }}" 
                    onchange="this.form.submit()"
                    class="text-xs rounded-xl border-slate-300 font-mono font-bold text-slate-800 shadow-sm focus:border-brand-500 focus:ring-brand-500 py-1.5 px-2.5"
                    title="Change target month"
                >
                @if(!empty($filters['shop_id']))
                    <input type="hidden" name="shop_id" value="{{ $filters['shop_id'] }}">
                @endif
                @if(!empty($filters['purchaser_id']))
                    <input type="hidden" name="purchaser_id" value="{{ $filters['purchaser_id'] }}">
                @endif
                @if(!empty($filters['vendor_id']))
                    <input type="hidden" name="vendor_id" value="{{ $filters['vendor_id'] }}">
                @endif
            </form>
        </div>

        {{-- Center: View Mode Switcher [ 🌳 Tree View | 📋 Table Matrix ] --}}
        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 self-start lg:self-auto">
            <button 
                type="button" 
                @click="viewMode = 'tree'" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition font-sans cursor-pointer"
                :class="viewMode === 'tree' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            >
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                Tree View
            </button>
            <button 
                type="button" 
                @click="viewMode = 'table'" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition font-sans cursor-pointer"
                :class="viewMode === 'table' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Table Matrix
            </button>
        </div>

        {{-- Right: Actions & Entity Search --}}
        <div class="flex items-center gap-2.5">
            <div class="relative">
                <input 
                    type="text" 
                    x-model="entitySearch" 
                    placeholder="Search entity..." 
                    class="text-xs rounded-xl border-slate-300 font-sans pl-8 pr-3 py-1.5 w-36 sm:w-44 focus:border-brand-500 focus:ring-brand-500"
                >
                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <button 
                type="button" 
                @click="window.print()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-sm"
                title="Print View"
            >
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </button>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    {{-- COMPACT FLOATING FINANCIAL SUMMARY BAR (No Giant KPI Cards)               --}}
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-3 sm:p-4 rounded-2xl text-white shadow-md border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs font-mono">
        <div class="flex items-center gap-3 sm:gap-6 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="text-slate-400 font-sans uppercase text-[10px] font-semibold">Opening:</span>
                <span class="font-bold text-white">₹{{ number_format($summary->openingCompanyMoney, 2) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-emerald-400 font-sans uppercase text-[10px] font-semibold">Money In (+):</span>
                <span class="font-bold text-emerald-300">₹{{ number_format($summary->externalMoneyIn, 2) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-rose-400 font-sans uppercase text-[10px] font-semibold">Money Out (-):</span>
                <span class="font-bold text-rose-300">₹{{ number_format($summary->externalMoneyOut, 2) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-brand-300 font-sans uppercase text-[10px] font-semibold">Closing:</span>
                <span class="font-bold text-white">₹{{ number_format($summary->expectedClosing, 2) }}</span>
            </div>
        </div>

        {{-- Reconciliation Audit Status --}}
        <div class="flex items-center gap-2">
            @if($summary->is_balanced)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Reconciliation Balanced
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/20 text-rose-300 border border-rose-400/30">
                    <span class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></span>
                    Diff: ₹{{ number_format(abs($summary->unexplainedDifference), 2) }}
                </span>
            @endif
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    {{-- MAIN VIEW CONTAINER (Tree View | Table Matrix)                             --}}
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    <div>
        {{-- 1. Hierarchical Tree View --}}
        <div x-show="viewMode === 'tree'" x-cloak class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-6 overflow-x-auto">
            <div class="mb-4 pb-3 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 font-sans">Hierarchical Tree Structure</h3>
                    <p class="text-xs text-slate-500 font-sans">Tree view of accounts, shops, purchasers, vendors, and staff.</p>
                </div>
                <span class="text-xs font-mono text-slate-500">{{ $tree->movementsCount() }} total movements</span>
            </div>
            @include('admin.cashbook.cash-flow-tree._node', ['node' => $tree, 'level' => 0])
        </div>

        {{-- 2. Table Matrix View --}}
        <div x-show="viewMode === 'table'" x-cloak>
            @include('admin.cashbook.cash-flow-tree._table')
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    {{-- TRANSACTION DRILL-DOWN SLIDE-OVER DRAWER (For Nodes & Matrix)             --}}
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    <div 
        x-show="modalOpen" 
        x-cloak
        class="fixed inset-0 z-50 overflow-hidden" 
        aria-labelledby="slide-over-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity" @click="modalOpen = false"></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-3xl transform transition-transform ease-in-out duration-300 bg-white shadow-2xl flex flex-col">
                
                {{-- Drawer Header --}}
                <div class="p-6 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span x-text="modalBadge" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-brand-500/30 text-brand-200 border border-brand-400/30"></span>
                            <span class="text-xs text-slate-400 font-mono">Month: {{ $month }}</span>
                        </div>
                        <h2 class="text-lg font-bold text-white font-sans" x-text="modalTitle"></h2>
                    </div>

                    <button 
                        type="button" 
                        @click="modalOpen = false" 
                        class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-white/10 transition"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Financial Snapshot in Drawer --}}
                <div 
                    x-show="Math.abs(modalOpening) > 0.001 || modalIn > 0.001 || modalOut > 0.001 || Math.abs(modalClosing) > 0.001" 
                    class="flex items-center gap-2 p-4 bg-slate-50 border-b border-slate-200 text-xs font-mono flex-wrap"
                >
                    <div x-show="Math.abs(modalOpening) > 0.001" class="text-center p-2 px-3 rounded bg-white border border-slate-200">
                        <span class="block text-[10px] uppercase font-sans text-slate-500">Opening</span>
                        <span class="font-semibold text-slate-800" x-text="'₹' + Number(modalOpening).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                    </div>
                    <div x-show="modalIn > 0.001" class="text-center p-2 px-3 rounded bg-white border border-slate-200">
                        <span class="block text-[10px] uppercase font-sans text-emerald-600">In (+)</span>
                        <span class="font-semibold text-emerald-600" x-text="'₹' + Number(modalIn).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                    </div>
                    <div x-show="modalOut > 0.001" class="text-center p-2 px-3 rounded bg-white border border-slate-200">
                        <span class="block text-[10px] uppercase font-sans text-rose-600">Out (-)</span>
                        <span class="font-semibold text-rose-600" x-text="'₹' + Number(modalOut).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                    </div>
                    <div x-show="Math.abs(modalClosing) > 0.001" class="text-center p-2 px-3 rounded bg-white border border-slate-200">
                        <span class="block text-[10px] uppercase font-sans text-slate-500">Closing</span>
                        <span class="font-bold text-slate-900" x-text="'₹' + Number(modalClosing).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                    </div>
                </div>

                {{-- Search inside Drawer --}}
                <div class="p-4 border-b border-slate-200 flex items-center gap-2">
                    <input 
                        type="text" 
                        x-model="searchQuery" 
                        placeholder="Filter transactions by entity, reference, notes..." 
                        class="w-full text-xs rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 font-sans"
                    >
                </div>

                {{-- Transactions Table Container --}}
                <div class="flex-1 overflow-y-auto p-4">
                    <div x-show="loading" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-brand-600"></div>
                        <p class="text-xs text-slate-500 mt-2 font-sans">Loading transaction drill-down...</p>
                    </div>

                    <div x-show="!loading && filteredMovements.length === 0" class="text-center py-12 text-slate-400 text-xs font-sans">
                        No individual transactions found for this selection.
                    </div>

                    <div x-show="!loading && filteredMovements.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-xs font-sans">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">Date</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">From Entity</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">To Entity</th>
                                    <th class="px-3 py-2 text-right font-semibold text-slate-600">Amount</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">Type / Reference</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-600">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="(tx, idx) in filteredMovements" :key="idx">
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="px-3 py-2.5 font-mono text-slate-600 whitespace-nowrap" x-text="tx.date"></td>
                                        <td class="px-3 py-2.5 font-medium text-slate-800" x-text="tx.from_entity_name"></td>
                                        <td class="px-3 py-2.5 font-medium text-slate-800" x-text="tx.to_entity_name"></td>
                                        <td class="px-3 py-2.5 font-mono font-bold text-right whitespace-nowrap text-slate-900" x-text="'₹' + Number(tx.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></td>
                                        <td class="px-3 py-2.5">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700" x-text="tx.movement_type ? tx.movement_type.replaceAll('_', ' ') : 'Move'"></span>
                                            <span x-show="tx.reference_number" class="block font-mono text-[10px] text-slate-400 mt-0.5" x-text="tx.reference_number"></span>
                                        </td>
                                        <td class="px-3 py-2.5 text-slate-500 max-w-xs truncate" :title="tx.notes" x-text="tx.notes || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Drawer Footer --}}
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500 font-sans">
                    <div>
                        Showing <span class="font-semibold text-slate-800" x-text="filteredMovements.length"></span> of <span class="font-semibold text-slate-800" x-text="movements.length"></span> transactions
                    </div>
                    <button 
                        type="button" 
                        @click="modalOpen = false" 
                        class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-sm"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function cashFlowTreeApp() {
    return {
        viewMode: 'tree',
        entitySearch: '',
        modalOpen: false,
        loading: false,
        modalTitle: '',
        modalBadge: '',
        modalOpening: 0,
        modalIn: 0,
        modalOut: 0,
        modalClosing: 0,
        movements: [],
        searchQuery: '',

        get filteredMovements() {
            if (!this.searchQuery.trim()) {
                return this.movements;
            }
            const q = this.searchQuery.toLowerCase();
            return this.movements.filter(m => 
                (m.from_entity_name && m.from_entity_name.toLowerCase().includes(q)) ||
                (m.to_entity_name && m.to_entity_name.toLowerCase().includes(q)) ||
                (m.notes && m.notes.toLowerCase().includes(q)) ||
                (m.reference_number && m.reference_number.toLowerCase().includes(q)) ||
                (m.movement_type && m.movement_type.toLowerCase().includes(q))
            );
        },

        fetchDrilldown(nodeId, title) {
            this.modalOpen = true;
            this.loading = true;
            this.modalTitle = title;
            this.searchQuery = '';
            this.movements = [];

            const month = '{{ $month }}';
            const url = `{{ route('admin.cashbook.cash-flow-tree.drilldown') }}?month=${month}&node_id=${encodeURIComponent(nodeId)}`;

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.loading = false;
                if (data.status === 'success') {
                    this.modalBadge = data.badge || 'Transactions';
                    this.modalOpening = data.opening_balance || 0;
                    this.modalIn = data.total_in || 0;
                    this.modalOut = data.total_out || 0;
                    this.modalClosing = data.closing_balance || 0;
                    this.movements = data.movements || [];
                } else {
                    this.movements = [];
                }
            })
            .catch(err => {
                this.loading = false;
                console.error('Failed to load drilldown:', err);
            });
        }
    }
}
</script>
@endsection
