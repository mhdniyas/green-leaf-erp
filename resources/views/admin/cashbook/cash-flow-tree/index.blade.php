@extends('admin.cashbook.layouts.app')

@section('title', 'Monthly Cash Flow Tree — Green Leaf')

@section('content')
<div x-data="cashFlowTreeApp()" class="space-y-6 pb-12">
    
    <!-- Top Header & Breadcrumb -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-1">
                <a href="{{ route('admin.cashbook.index') }}" class="hover:text-brand-600 transition">Cashbook</a>
                <span>/</span>
                <span class="text-slate-700 font-semibold">Monthly Cash Flow Tree</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 font-sans tracking-tight">Monthly Cash Flow Tree</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Normalized read-only money-movement tracing layer across all banks, shops, purchasers, vendor credits, and payroll.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ route('admin.cashbook.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Cashbook
            </a>
            <button 
                type="button" 
                @click="window.print()" 
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition shadow-sm"
            >
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print / Export
            </button>
        </div>
    </div>

    <!-- Filters Bar -->
    <form method="GET" action="{{ route('admin.cashbook.cash-flow-tree.index') }}" class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <!-- Month Picker -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Target Month</label>
                <input 
                    type="month" 
                    name="month" 
                    value="{{ $month }}" 
                    class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 font-sans"
                >
            </div>

            <!-- Shop Filter -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Shop</label>
                <select name="shop_id" class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 font-sans">
                    <option value="">All Shops</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" {{ ($filters['shop_id'] ?? null) == $shop->id ? 'selected' : '' }}>
                            {{ $shop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Purchaser Filter -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Purchaser</label>
                <select name="purchaser_id" class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 font-sans">
                    <option value="">All Purchasers</option>
                    @foreach($purchasers as $p)
                        <option value="{{ $p->id }}" {{ ($filters['purchaser_id'] ?? null) == $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Vendor Filter -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Filter Vendor</label>
                <select name="vendor_id" class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 font-sans">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" {{ ($filters['vendor_id'] ?? null) == $v->id ? 'selected' : '' }}>
                            {{ $v->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button 
                    type="submit" 
                    class="flex-1 px-4 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition shadow-sm"
                >
                    Apply
                </button>
                <a 
                    href="{{ route('admin.cashbook.cash-flow-tree.index') }}" 
                    class="px-3 py-2 text-xs font-semibold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition"
                    title="Reset to current month"
                >
                    Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Phase 9: Monthly Reconciliation Summary Card -->
    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 rounded-2xl p-6 text-white shadow-lg border border-slate-800">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-700/60">
            <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-brand-500/20 text-brand-300 border border-brand-400/30 mb-2">
                    <svg class="w-3.5 h-3.5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Phase 9 Control Framework
                </span>
                <h2 class="text-xl font-bold tracking-tight">Monthly Money Reconciliation Summary — {{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Systemic audit of all company funds: Opening + Inflow - Outflow must equal Located Money across all holders.
                </p>
            </div>

            <!-- Status Indicator -->
            <div class="shrink-0 flex items-center gap-3">
                @if($summary->is_balanced)
                    <div class="flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/30 px-3.5 py-2 rounded-xl text-emerald-400">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-bold uppercase tracking-wider font-sans">Reconciliation Balanced</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 bg-rose-500/10 border border-rose-500/30 px-3.5 py-2 rounded-xl text-rose-400">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-400 animate-pulse"></span>
                        <span class="text-xs font-bold uppercase tracking-wider font-sans">
                            Diff: ₹{{ number_format(abs($summary->unexplainedDifference), 2) }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <!-- 4 Key Figures -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 py-6 border-b border-slate-700/60">
            <div class="bg-white/5 p-4 rounded-xl border border-white/10 backdrop-blur-sm">
                <span class="block text-xs uppercase tracking-wider text-slate-400 font-sans font-medium">Opening Company Money</span>
                <span class="text-xl md:text-2xl font-bold font-mono text-white mt-1 block">
                    ₹{{ number_format($summary->openingCompanyMoney, 2) }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 block">As of {{ \Carbon\Carbon::parse($month.'-01')->startOfMonth()->format('M 01') }}</span>
            </div>

            <div class="bg-white/5 p-4 rounded-xl border border-white/10 backdrop-blur-sm">
                <span class="block text-xs uppercase tracking-wider text-emerald-400 font-sans font-medium">External Money In (+)</span>
                <span class="text-xl md:text-2xl font-bold font-mono text-emerald-300 mt-1 block">
                    ₹{{ number_format($summary->externalMoneyIn, 2) }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 block">Retail sales & external receipts</span>
            </div>

            <div class="bg-white/5 p-4 rounded-xl border border-white/10 backdrop-blur-sm">
                <span class="block text-xs uppercase tracking-wider text-rose-400 font-sans font-medium">External Money Out (-)</span>
                <span class="text-xl md:text-2xl font-bold font-mono text-rose-300 mt-1 block">
                    ₹{{ number_format($summary->externalMoneyOut, 2) }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 block">Purchases, expenses, vendor pay</span>
            </div>

            <div class="bg-white/5 p-4 rounded-xl border border-white/10 backdrop-blur-sm">
                <span class="block text-xs uppercase tracking-wider text-brand-300 font-sans font-medium">Expected Closing</span>
                <span class="text-xl md:text-2xl font-bold font-mono text-white mt-1 block">
                    ₹{{ number_format($summary->expectedClosing, 2) }}
                </span>
                <span class="text-[11px] text-slate-400 mt-1 block">Mathematical theoretical pool</span>
            </div>
        </div>

        <!-- Located Money Breakdown vs Difference -->
        <div class="pt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <span class="text-xs uppercase font-sans tracking-wider font-semibold text-slate-400 block mb-3">
                    Actual Money Location (Closing Balance with Each Holder)
                </span>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    @php
                        $activeLocations = collect($summary->locations)->filter(fn ($amt) => abs((float) $amt) > 0.001);
                    @endphp
                    @forelse($activeLocations as $holder => $amt)
                        <div class="bg-white/5 p-3 rounded-lg border border-white/5">
                            <span class="text-[11px] text-slate-400 block truncate font-sans">{{ $holder }}</span>
                            <span class="text-sm font-bold font-mono text-white mt-0.5 block">
                                ₹{{ number_format($amt, 2) }}
                            </span>
                        </div>
                    @empty
                        <div class="col-span-full text-xs text-slate-400 py-1 font-sans">
                            No holder accounts with active balance.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white/5 p-4 rounded-xl border border-white/10 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-400 font-sans mb-1">
                        <span>Total Located Money:</span>
                        <span class="font-mono text-white font-semibold">₹{{ number_format($summary->locatedMoney, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-400 font-sans">
                        <span>Expected Closing:</span>
                        <span class="font-mono text-white font-semibold">₹{{ number_format($summary->expectedClosing, 2) }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold font-sans uppercase {{ $summary->is_balanced ? 'text-emerald-400' : 'text-rose-400' }}">
                            Unexplained Diff:
                        </span>
                        <span class="text-base font-bold font-mono {{ $summary->is_balanced ? 'text-emerald-400' : 'text-rose-400' }}">
                            ₹{{ number_format($summary->unexplainedDifference, 2) }}
                        </span>
                    </div>
                    @if(! $summary->is_balanced)
                        <p class="text-[11px] text-rose-300/80 mt-1">
                            Investigation recommended. Check "Other / Needs Review" or unlinked transactions below.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Tree Action Controls -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <h3 class="text-base font-bold text-slate-800 font-sans">Hierarchical Cash Flow Tree</h3>
            <span class="text-xs text-slate-500 font-sans">({{ $tree->movementsCount() }} total movements traced)</span>
        </div>
        <div class="text-xs text-slate-500 font-sans">
            Tip: Click on <span class="text-brand-600 font-medium underline">"moves"</span> next to any node to inspect individual transactions.
        </div>
    </div>

    <!-- Main Tree Container -->
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-4 sm:p-6 overflow-x-auto">
        @include('admin.cashbook.cash-flow-tree._node', ['node' => $tree, 'level' => 0])
    </div>

    <!-- Phase 11: Transaction Drill-down Slide-over / Modal -->
    <div 
        x-show="modalOpen" 
        x-cloak
        class="fixed inset-0 z-50 overflow-hidden" 
        aria-labelledby="slide-over-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="modalOpen = false"></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <div class="pointer-events-auto w-screen max-w-3xl transform transition-transform ease-in-out duration-300 bg-white shadow-2xl flex flex-col">
                
                <!-- Modal Header -->
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

                <!-- Financial Snapshot in Modal (only show with non-zero values) -->
                <div x-show="Math.abs(modalOpening) > 0.001 || modalIn > 0.001 || modalOut > 0.001 || Math.abs(modalClosing) > 0.001" class="flex items-center gap-2 p-4 bg-slate-50 border-b border-slate-200 text-xs font-mono flex-wrap">
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

                <!-- Search inside modal -->
                <div class="p-4 border-b border-slate-200 flex items-center gap-2">
                    <input 
                        type="text" 
                        x-model="searchQuery" 
                        placeholder="Filter transactions by entity, reference, notes..." 
                        class="w-full text-xs rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 font-sans"
                    >
                </div>

                <!-- Transactions Table Container -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div x-show="loading" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-t-brand-600"></div>
                        <p class="text-xs text-slate-500 mt-2 font-sans">Loading transaction drill-down...</p>
                    </div>

                    <div x-show="!loading && filteredMovements.length === 0" class="text-center py-12 text-slate-400 text-xs font-sans">
                        No transactions found for this node.
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
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700" x-text="tx.movement_type.replaceAll('_', ' ')"></span>
                                            <span x-show="tx.reference_number" class="block font-mono text-[10px] text-slate-400 mt-0.5" x-text="tx.reference_number"></span>
                                        </td>
                                        <td class="px-3 py-2.5 text-slate-500 max-w-xs truncate" :title="tx.notes" x-text="tx.notes || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500 font-sans">
                    <div>
                        Showing <span class="font-semibold text-slate-800" x-text="filteredMovements.length"></span> of <span class="font-semibold text-slate-800" x-text="movements.length"></span> transactions
                    </div>
                    <button 
                        type="button" 
                        @click="modalOpen = false" 
                        class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition shadow-sm"
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
