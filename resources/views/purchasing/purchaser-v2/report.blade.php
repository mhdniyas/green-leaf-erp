<x-layouts.purchaser-v2 :date="$date" :grade="$grade" title="Purchaser V2 &bull; Daily Report">
    <div class="space-y-4 sm:space-y-6">

        <!-- Top Header & Actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'purchase_grade' => $grade]) }}"
                   class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Daily Purchase Report</h1>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700 border border-emerald-200">
                            Grade {{ $grade }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Overview of today's completed purchases, fulfilled demand & vendor bills.</p>
                </div>
            </div>

            <!-- Date Indicator -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}
                </span>
                <a href="{{ route('purchaser-v2.report', ['date' => $date, 'grade' => $grade]) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                   title="Refresh Report">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- KPI Hero Summary Grid (4 Cards) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- 1. Total Spend -->
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Purchase Spend</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 font-mono">₹{{ number_format($metrics['total_spend'], 2) }}</p>
                    <p class="text-[10px] text-slate-500 font-medium">
                        Paid: <strong class="text-emerald-700">₹{{ number_format($metrics['total_paid'], 2) }}</strong>
                        @if ($metrics['total_balance'] > 0)
                            &bull; Bal: <strong class="text-amber-600">₹{{ number_format($metrics['total_balance'], 2) }}</strong>
                        @endif
                    </p>
                </div>
            </div>

            <!-- 2. Quantity Bought -->
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Volume Bought</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_quantity_bought'], 1) }}</p>
                    <p class="text-[10px] text-slate-500 font-medium">Across <strong class="text-slate-800">{{ $metrics['distinct_products_count'] }}</strong> distinct products</p>
                </div>
            </div>

            <!-- 3. Bills & Invoices -->
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Vendor Bills</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 font-mono">{{ $metrics['total_bills_count'] }}</p>
                    <p class="text-[10px] text-slate-500 font-medium">
                        <span class="text-emerald-700 font-bold">{{ $metrics['paid_bills_count'] }} Paid</span> &bull; 
                        <span class="text-amber-600 font-bold">{{ $metrics['credit_bills_count'] }} Credit</span>
                    </p>
                </div>
            </div>

            <!-- 4. Demand Fulfillment -->
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Demand Fulfilled</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 font-mono">{{ $metrics['demand_fulfillment_pct'] }}%</p>
                    <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1 overflow-hidden">
                        <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" style="width: {{ min(100, $metrics['demand_fulfillment_pct']) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar & Tab Switcher -->
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                
                <!-- Tab Selector: Purchased Items vs Vendor Bills -->
                <div class="flex rounded-2xl bg-slate-100 p-1">
                    <button type="button" id="tabBtnItems"
                            class="flex-1 sm:flex-none rounded-xl px-4 py-2 text-xs font-black transition shadow-sm bg-white text-slate-900 flex items-center justify-center gap-1.5">
                        <span>📦 Purchased Items</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-mono text-slate-600">{{ count($items) }}</span>
                    </button>
                    <button type="button" id="tabBtnBills"
                            class="flex-1 sm:flex-none rounded-xl px-4 py-2 text-xs font-black transition text-slate-500 hover:text-slate-900 flex items-center justify-center gap-1.5">
                        <span>🧾 Vendor Bills</span>
                        <span class="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-mono text-slate-600">{{ count($bills) }}</span>
                    </button>
                </div>

                <!-- Instant Search Box -->
                <div class="relative flex-1 max-w-md">
                    <input type="text" id="reportSearchInput"
                           placeholder="Search products, SKU, or vendors..."
                           value="{{ $search }}"
                           class="h-10 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── TAB 1: PURCHASED ITEMS BREAKDOWN ────────────────────────────── -->
        <div id="panelItems" class="space-y-3">
            @if (empty($items))
                <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-400 shadow-sm space-y-2">
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <p class="text-sm font-bold text-slate-700">No items purchased yet for today.</p>
                    <p class="text-xs text-slate-400">Submitted cart items for Grade {{ $grade }} will appear here automatically.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="itemsGrid">
                    @foreach ($items as $item)
                        <div class="report-item-card rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 transition hover:border-slate-300"
                             data-search="{{ strtolower($item['name'] . ' ' . $item['sku'] . ' ' . implode(' ', $item['suppliers'])) }}">
                            
                            <!-- Header: Name, SKU, Category, Grade -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-black text-slate-900 truncate">{{ $item['name'] }}</h3>
                                        <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[9px] font-black text-emerald-700 border border-emerald-200 font-mono">
                                            Gr. {{ $item['grade'] }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-mono">{{ $item['category_name'] }} &bull; SKU: {{ $item['sku'] }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-sm font-black text-slate-900 font-mono block">₹{{ number_format($item['total_amount'], 2) }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">@ ₹{{ number_format($item['avg_price'], 2) }}/{{ $item['unit'] }}</span>
                                </div>
                            </div>

                            <!-- Fulfillment & Quantity Bar -->
                            <div class="rounded-2xl bg-slate-50 p-3 space-y-2 border border-slate-100">
                                <div class="flex items-center justify-between text-xs font-mono">
                                    <span class="text-slate-500">
                                        Bought: <strong class="text-slate-900 font-bold">{{ number_format($item['quantity'], 1) }} {{ $item['unit'] }}</strong>
                                    </span>
                                    <span class="text-slate-500">
                                        Demand: <strong class="text-slate-900 font-bold">{{ number_format($item['approved_demand_qty'], 1) }} {{ $item['unit'] }}</strong>
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black {{ $item['fulfillment_pct'] >= 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $item['fulfillment_pct'] }}% Fulfilled
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full {{ $item['fulfillment_pct'] >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }}"
                                         style="width: {{ min(100, $item['fulfillment_pct']) }}%"></div>
                                </div>
                            </div>

                            <!-- Suppliers Tag List -->
                            @if (!empty($item['suppliers']))
                                <div class="flex items-center gap-1.5 flex-wrap pt-1">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Vendors:</span>
                                    @foreach ($item['suppliers'] as $sup)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                            <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            {{ $sup }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- ── TAB 2: VENDOR BILLS BREAKDOWN ──────────────────────────────── -->
        <div id="panelBills" class="space-y-3 hidden">
            @if (empty($bills))
                <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-400 shadow-sm space-y-2">
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm font-bold text-slate-700">No vendor bills submitted yet today.</p>
                    <p class="text-xs text-slate-400">Bills completed via Purchaser Cart flow will appear here.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="billsGrid">
                    @foreach ($bills as $b)
                        <div class="report-bill-card rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 transition hover:border-slate-300"
                             data-search="{{ strtolower($b['supplier_name'] . ' ' . $b['invoice_number'] . ' ' . $b['cart_number'] . ' ' . ($b['supplier_location'] ?? '')) }}">
                            
                            <!-- Bill Header: Supplier, Location, Status Badge -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-black text-slate-900 truncate">{{ $b['supplier_name'] }}</h3>
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase font-mono {{ strcasecmp($b['payment_status'], 'paid') === 0 || $b['balance_amount'] <= 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                                            {{ $b['payment_status'] }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">
                                        {{ $b['supplier_location'] ?: 'Local' }}
                                        @if ($b['supplier_mobile']) &bull; {{ $b['supplier_mobile'] }} @endif
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-sm font-black text-slate-900 font-mono block">₹{{ number_format($b['net_amount'], 2) }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $b['items_count'] }} {{ $b['items_count'] === 1 ? 'item' : 'items' }}</span>
                                </div>
                            </div>

                            <!-- Bill Metadata Strip: Invoice Number & Payment Terms -->
                            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-2.5 text-[11px] font-mono border border-slate-100 text-slate-600">
                                <div>
                                    <span class="text-slate-400">Bill:</span>
                                    <strong class="text-slate-900">{{ $b['invoice_number'] }}</strong>
                                </div>
                                <div>
                                    <span class="text-slate-400">Paid:</span>
                                    <strong class="text-emerald-700">₹{{ number_format($b['paid_amount'], 2) }}</strong>
                                    @if ($b['balance_amount'] > 0)
                                        <span class="text-slate-400 ml-1">Bal:</span>
                                        <strong class="text-rose-600">₹{{ number_format($b['balance_amount'], 2) }}</strong>
                                    @endif
                                </div>
                            </div>

                            <!-- Actions Row: Payment Method, Time & Link to Bill Details -->
                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-medium">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 font-bold text-slate-600">
                                        {{ $b['payment_method'] }}
                                    </span>
                                    <span>{{ $b['created_at'] }}</span>
                                </div>

                                <a href="{{ $b['bill_url'] }}"
                                   class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 active:scale-95">
                                    <span>View Bill</span>
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>

                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <!-- Client-Side Vanilla JS Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabBtnItems = document.getElementById('tabBtnItems');
            const tabBtnBills = document.getElementById('tabBtnBills');
            const panelItems = document.getElementById('panelItems');
            const panelBills = document.getElementById('panelBills');
            const reportSearchInput = document.getElementById('reportSearchInput');

            // Tab Switching
            tabBtnItems.addEventListener('click', () => {
                tabBtnItems.className = 'flex-1 sm:flex-none rounded-xl px-4 py-2 text-xs font-black transition shadow-sm bg-white text-slate-900 flex items-center justify-center gap-1.5';
                tabBtnBills.className = 'flex-1 sm:flex-none rounded-xl px-4 py-2 text-xs font-black transition text-slate-500 hover:text-slate-900 flex items-center justify-center gap-1.5';
                panelItems.classList.remove('hidden');
                panelBills.classList.add('hidden');
            });

            tabBtnBills.addEventListener('click', () => {
                tabBtnBills.className = 'flex-1 sm:flex-none rounded-xl px-4 py-2 text-xs font-black transition shadow-sm bg-white text-slate-900 flex items-center justify-center gap-1.5';
                tabBtnItems.className = 'flex-1 sm:flex-none rounded-xl px-4 py-2 text-xs font-black transition text-slate-500 hover:text-slate-900 flex items-center justify-center gap-1.5';
                panelBills.classList.remove('hidden');
                panelItems.classList.add('hidden');
            });

            // Real-time Instant Search Filtering
            reportSearchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();

                document.querySelectorAll('.report-item-card').forEach(card => {
                    const searchData = card.dataset.search || '';
                    card.style.display = searchData.includes(query) ? '' : 'none';
                });

                document.querySelectorAll('.report-bill-card').forEach(card => {
                    const searchData = card.dataset.search || '';
                    card.style.display = searchData.includes(query) ? '' : 'none';
                });
            });
        });
    </script>
</x-layouts.purchaser-v2>
