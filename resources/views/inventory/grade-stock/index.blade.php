<x-layouts.inventory title="Grade Stock Management">
    @php
        $buttonClass = 'inline-flex h-9 items-center justify-center rounded-xl px-3 text-[11px] font-black uppercase tracking-[0.12em] transition';
        $softButtonClass = $buttonClass.' border border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    @endphp

    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.deliveries.dashboard', ['warehouse_id' => $selectedWarehouseId]) }}" class="{{ $softButtonClass }}">
                <svg class="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Deliveries Dashboard
            </a>
            <a href="{{ route('inventory.grade-stock.index', ['warehouse_id' => $selectedWarehouseId]) }}" class="{{ $buttonClass }} border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                <svg class="mr-1.5 h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </a>
        </div>
    </x-slot:actions>

    <div class="mx-auto max-w-7xl space-y-6" x-data="gradeStockApp({
        currentTab: '{{ $tab }}',
        selectedWarehouseId: {{ $selectedWarehouseId }},
        selectedProductId: {{ $selectedProductId ? $selectedProductId : 'null' }},
        initialProductDetails: {{ $activeProductDetails ? json_encode($activeProductDetails) : 'null' }}
    })">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800 shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        <!-- Header with Warehouse Selector & Global Totals -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-lg bg-indigo-100 px-2.5 py-0.5 text-xs font-black text-indigo-800">12-Way Transfer</span>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Warehouse Operations</p>
                    </div>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Grade Stock Management</h1>
                    <p class="mt-1 text-sm font-semibold text-slate-600">Reclassify and transfer physical stock between Mixed (U), Grade A, Grade B, and Grade C with full auditability and stock conservation.</p>
                </div>

                <!-- Warehouse Selection Form -->
                <form method="GET" action="{{ route('inventory.grade-stock.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" :value="currentTab">
                    @if($search !== '') <input type="hidden" name="search" value="{{ $search }}"> @endif
                    <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-1.5">
                        <span class="pl-2 text-[10px] font-black uppercase tracking-wider text-slate-500">Warehouse:</span>
                        <select name="warehouse_id" onchange="this.form.submit()" class="h-8 rounded-lg border-none bg-white px-3 text-xs font-black text-slate-900 shadow-xs focus:ring-2 focus:ring-indigo-500">
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected($wh->id == $selectedWarehouseId)>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <!-- Global Warehouse Summary Cards -->
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Physical</p>
                    <p class="mt-1 text-xl font-black text-slate-950">{{ number_format($summaryTotals['total_all'], 1) }} <span class="text-xs font-bold text-slate-500">kg</span></p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">Mixed (U)</p>
                    <p class="mt-1 text-xl font-black text-amber-950">{{ number_format($summaryTotals['total_mixed'], 1) }} <span class="text-xs font-bold text-amber-700">kg</span></p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Grade A</p>
                    <p class="mt-1 text-xl font-black text-emerald-950">{{ number_format($summaryTotals['total_grade_a'], 1) }} <span class="text-xs font-bold text-emerald-700">kg</span></p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-blue-800">Grade B</p>
                    <p class="mt-1 text-xl font-black text-blue-950">{{ number_format($summaryTotals['total_grade_b'], 1) }} <span class="text-xs font-bold text-blue-700">kg</span></p>
                </div>
                <div class="rounded-xl border border-purple-200 bg-purple-50/50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-purple-800">Grade C</p>
                    <p class="mt-1 text-xl font-black text-purple-950">{{ number_format($summaryTotals['total_grade_c'], 1) }} <span class="text-xs font-bold text-purple-700">kg</span></p>
                </div>
            </div>

            <!-- Tab Navigation Strip -->
            <div class="mt-6 flex border-b border-slate-200">
                <button type="button" @click="setTab('inventory')" :class="currentTab === 'inventory' ? 'border-indigo-600 text-indigo-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-bold'" class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Tab 1: Current Inventory</span>
                </button>
                <button type="button" @click="setTab('transfer')" :class="currentTab === 'transfer' ? 'border-indigo-600 text-indigo-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-bold'" class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>Tab 2: Grade Transfer</span>
                    <span x-show="selectedProduct" class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-black text-indigo-800" x-text="selectedProduct?.name"></span>
                </button>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- TAB 1: CURRENT INVENTORY                   -->
        <!-- ========================================== -->
        <div x-show="currentTab === 'inventory'" class="space-y-4">
            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('inventory.grade-stock.index') }}" class="flex flex-1 items-center gap-2">
                    <input type="hidden" name="tab" value="inventory">
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                    <div class="relative flex-1">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search products by name or SKU..." class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-4 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                        <svg class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <button type="submit" class="{{ $buttonClass }} bg-slate-950 text-white hover:bg-slate-800">Search</button>
                    @if($search !== '')
                        <a href="{{ route('inventory.grade-stock.index', ['warehouse_id' => $selectedWarehouseId, 'tab' => 'inventory']) }}" class="{{ $softButtonClass }}">Clear</a>
                    @endif
                </form>
            </div>

            <!-- Inventory Table -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3 text-right">Mixed (U)</th>
                                <th class="px-4 py-3 text-right">Grade A</th>
                                <th class="px-4 py-3 text-right">Grade B</th>
                                <th class="px-4 py-3 text-right">Grade C</th>
                                <th class="px-4 py-3 text-right">Total Physical</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold text-slate-800">
                            @forelse($products as $prod)
                                @php
                                    $pId = $prod->id;
                                    $mixed = $stockMap[$pId]['U'] ?? 0.0;
                                    $gradeA = $stockMap[$pId]['A'] ?? 0.0;
                                    $gradeB = $stockMap[$pId]['B'] ?? 0.0;
                                    $gradeC = $stockMap[$pId]['C'] ?? 0.0;
                                    $totalStock = round($mixed + $gradeA + $gradeB + $gradeC, 3);
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors {{ $totalStock > 0 ? '' : 'opacity-60' }}">
                                    <td class="px-4 py-3.5">
                                        <div class="font-black text-slate-950">{{ $prod->name }}</div>
                                        <div class="text-[10px] text-slate-400 font-medium">Unit: {{ $prod->unit ?? 'kg' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-[11px] text-slate-600">{{ $prod->sku }}</td>
                                    <td class="px-4 py-3.5 text-slate-600">{{ $prod->category?->name ?? 'Produce' }}</td>
                                    <td class="px-4 py-3.5 text-right font-mono">
                                        <span class="inline-block rounded-lg px-2 py-0.5 {{ $mixed > 0 ? 'bg-amber-100 text-amber-900 font-black' : 'text-slate-400' }}">
                                            {{ number_format($mixed, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono">
                                        <span class="inline-block rounded-lg px-2 py-0.5 {{ $gradeA > 0 ? 'bg-emerald-100 text-emerald-900 font-black' : 'text-slate-400' }}">
                                            {{ number_format($gradeA, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono">
                                        <span class="inline-block rounded-lg px-2 py-0.5 {{ $gradeB > 0 ? 'bg-blue-100 text-blue-900 font-black' : 'text-slate-400' }}">
                                            {{ number_format($gradeB, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono">
                                        <span class="inline-block rounded-lg px-2 py-0.5 {{ $gradeC > 0 ? 'bg-purple-100 text-purple-900 font-black' : 'text-slate-400' }}">
                                            {{ number_format($gradeC, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-mono text-slate-950 font-black">
                                        {{ number_format($totalStock, 2) }} {{ $prod->unit ?? 'kg' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <button type="button" @click="startTransfer({{ $prod->id }})" class="inline-flex h-8 items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 text-[11px] font-black text-indigo-700 hover:bg-indigo-100 transition shadow-2xs">
                                            Transfer
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                                        No products found matching the criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($products->hasPages())
                    <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: GRADE TRANSFER                      -->
        <!-- ========================================== -->
        <div x-show="currentTab === 'transfer'" class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left: Form Controls (2 Columns) -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <h2 class="text-lg font-black text-slate-950">Execute Physical Grade Transfer</h2>
                                <p class="text-xs text-slate-500 font-semibold">Select produce, source and destination grades, and specify quantity.</p>
                            </div>
                            <span class="rounded-lg bg-slate-100 px-3 py-1 font-mono text-xs font-black text-slate-700">All 12 Combinations Supported</span>
                        </div>

                        <!-- Step 1: Product Selection -->
                        <div class="space-y-2">
                            <label class="text-[11px] font-black uppercase tracking-wider text-slate-500">1. Product Selection</label>
                            <div class="flex gap-2">
                                <select x-model="selectedProductId" @change="onProductSelect($event.target.value)" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    <option value="">-- Choose a Product --</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" @selected($selectedProductId == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div x-show="isLoadingDetails" class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 font-bold">
                            <svg class="mx-auto h-6 w-6 animate-spin text-indigo-600 mb-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Fetching real-time grade stock and eligible batches...
                        </div>

                        <!-- Main Form When Product Selected -->
                        <form method="POST" action="{{ route('inventory.grade-stock.transfer') }}" @submit="onFormSubmit($event)" x-show="selectedProduct && !isLoadingDetails" class="space-y-6">
                            @csrf
                            <input type="hidden" name="product_id" :value="selectedProduct?.id">
                            <input type="hidden" name="warehouse_id" :value="selectedWarehouseId">
                            <input type="hidden" name="client_submission_id" :value="clientSubmissionId">

                            <!-- Step 2: Source Grade Selection -->
                            <div class="space-y-2">
                                <label class="text-[11px] font-black uppercase tracking-wider text-slate-500">2. Source Grade (Transfer FROM)</label>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <template x-for="g in grades" :key="'src_' + g.code">
                                        <label :class="sourceGrade === g.code ? 'border-indigo-600 bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 bg-white hover:border-slate-300'" class="flex cursor-pointer flex-col rounded-xl border p-3 transition">
                                            <div class="flex items-center justify-between">
                                                <span class="font-black text-slate-900" x-text="g.label"></span>
                                                <input type="radio" name="source_grade" :value="g.code" x-model="sourceGrade" @change="onSourceGradeChange()" class="text-indigo-600 focus:ring-indigo-500">
                                            </div>
                                            <div class="mt-2 text-xs font-bold text-slate-500">
                                                Avail: <span class="font-mono text-slate-900 font-black" x-text="getStockForGrade(g.code).toFixed(2)"></span> <span x-text="selectedProduct?.unit || 'kg'"></span>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <!-- Step 3: Destination Grade Selection -->
                            <div class="space-y-2">
                                <label class="text-[11px] font-black uppercase tracking-wider text-slate-500">3. Destination Grade (Transfer TO)</label>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <template x-for="g in grades" :key="'dest_' + g.code">
                                        <label x-show="g.code !== sourceGrade" :class="targetGrade === g.code ? 'border-indigo-600 bg-indigo-50/50 ring-2 ring-indigo-500/20' : 'border-slate-200 bg-white hover:border-slate-300'" class="flex cursor-pointer flex-col rounded-xl border p-3 transition">
                                            <div class="flex items-center justify-between">
                                                <span class="font-black text-slate-900" x-text="g.label"></span>
                                                <input type="radio" name="target_grade" :value="g.code" x-model="targetGrade" @change="updatePreview()" class="text-indigo-600 focus:ring-indigo-500">
                                            </div>
                                            <div class="mt-2 text-xs font-bold text-slate-500">
                                                Current: <span class="font-mono text-slate-900 font-black" x-text="getStockForGrade(g.code).toFixed(2)"></span> <span x-text="selectedProduct?.unit || 'kg'"></span>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <!-- Step 4: Source Batch Selection -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-[11px] font-black uppercase tracking-wider text-slate-500">4. Eligible Physical Source Batch</label>
                                    <span class="text-[11px] font-bold text-slate-400">Deterministic allocation or choose specific batch</span>
                                </div>
                                <select name="source_batch_id" x-model="selectedBatchId" @change="onBatchSelect()" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    <option value="">Automatic Allocation (FIFO across available source stock)</option>
                                    <template x-for="b in currentEligibleBatches" :key="b.id">
                                        <option :value="b.id" x-text="b.reference + ' (Avail: ' + b.available_qty.toFixed(2) + ' kg | Cost: ₹' + b.cost_per_kg + ')'"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Step 5: Quantity Input -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-[11px] font-black uppercase tracking-wider text-slate-500">5. Transfer Quantity (<span x-text="selectedProduct?.unit || 'kg'"></span>)</label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-500">Max Available: <span class="font-black text-slate-900" x-text="maxAvailableSourceStock.toFixed(2)"></span></span>
                                        <button type="button" @click="quantity = maxAvailableSourceStock; updatePreview()" class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-700 hover:bg-slate-200">MAX</button>
                                    </div>
                                </div>
                                <input type="number" step="0.001" min="0.001" :max="maxAvailableSourceStock" name="quantity" x-model.number="quantity" @input="updatePreview()" placeholder="0.000" required class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-black text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>

                            <!-- Step 6: Remarks -->
                            <div class="space-y-2">
                                <label class="text-[11px] font-black uppercase tracking-wider text-slate-500">6. Reason / Remarks (Optional)</label>
                                <input type="text" name="notes" x-model="notes" placeholder="e.g., Sorted higher quality, reclassification..." class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>

                            <!-- Confirm Button -->
                            <div class="pt-4 border-t border-slate-100">
                                <button type="submit" :disabled="!isTransferValid || isSubmitting" class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-sm flex items-center justify-center gap-2">
                                    <svg x-show="isSubmitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span x-text="isSubmitting ? 'Executing Transfer...' : 'Confirm Grade Transfer (' + (sourceGrade ? sourceGrade : '?') + ' → ' + (targetGrade ? targetGrade : '?') + ')'"></span>
                                </button>
                            </div>
                        </form>

                        <div x-show="!selectedProduct && !isLoadingDetails" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-xs font-bold text-slate-400">
                            Please select a product above to start a grade transfer.
                        </div>
                    </div>
                </div>

                <!-- Right: Live Balance Preview & Invariance Verification -->
                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-black text-slate-950">Live Balance Preview</h3>
                            <p class="text-[11px] text-slate-500 font-semibold">Immediate before / after balances for this product.</p>
                        </div>

                        <template x-if="selectedProduct">
                            <div class="space-y-3">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Product</p>
                                    <p class="text-sm font-black text-slate-950" x-text="selectedProduct.name"></p>
                                    <p class="text-xs text-slate-500 font-mono" x-text="selectedProduct.sku"></p>
                                </div>

                                <div class="space-y-2">
                                    <!-- Mixed (U) -->
                                    <div class="flex items-center justify-between rounded-lg border border-slate-100 p-2 text-xs">
                                        <span class="font-bold text-amber-800">Mixed (U)</span>
                                        <div class="font-mono text-right">
                                            <span class="text-slate-500" x-text="currentStock.mixed.toFixed(2)"></span>
                                            <span class="text-slate-300 mx-1">→</span>
                                            <span class="font-black" :class="previewStock.mixed < currentStock.mixed ? 'text-rose-600' : (previewStock.mixed > currentStock.mixed ? 'text-emerald-600' : 'text-slate-900')" x-text="previewStock.mixed.toFixed(2)"></span>
                                        </div>
                                    </div>
                                    <!-- Grade A -->
                                    <div class="flex items-center justify-between rounded-lg border border-slate-100 p-2 text-xs">
                                        <span class="font-bold text-emerald-800">Grade A</span>
                                        <div class="font-mono text-right">
                                            <span class="text-slate-500" x-text="currentStock.grade_a.toFixed(2)"></span>
                                            <span class="text-slate-300 mx-1">→</span>
                                            <span class="font-black" :class="previewStock.grade_a < currentStock.grade_a ? 'text-rose-600' : (previewStock.grade_a > currentStock.grade_a ? 'text-emerald-600' : 'text-slate-900')" x-text="previewStock.grade_a.toFixed(2)"></span>
                                        </div>
                                    </div>
                                    <!-- Grade B -->
                                    <div class="flex items-center justify-between rounded-lg border border-slate-100 p-2 text-xs">
                                        <span class="font-bold text-blue-800">Grade B</span>
                                        <div class="font-mono text-right">
                                            <span class="text-slate-500" x-text="currentStock.grade_b.toFixed(2)"></span>
                                            <span class="text-slate-300 mx-1">→</span>
                                            <span class="font-black" :class="previewStock.grade_b < currentStock.grade_b ? 'text-rose-600' : (previewStock.grade_b > currentStock.grade_b ? 'text-emerald-600' : 'text-slate-900')" x-text="previewStock.grade_b.toFixed(2)"></span>
                                        </div>
                                    </div>
                                    <!-- Grade C -->
                                    <div class="flex items-center justify-between rounded-lg border border-slate-100 p-2 text-xs">
                                        <span class="font-bold text-purple-800">Grade C</span>
                                        <div class="font-mono text-right">
                                            <span class="text-slate-500" x-text="currentStock.grade_c.toFixed(2)"></span>
                                            <span class="text-slate-300 mx-1">→</span>
                                            <span class="font-black" :class="previewStock.grade_c < currentStock.grade_c ? 'text-rose-600' : (previewStock.grade_c > currentStock.grade_c ? 'text-emerald-600' : 'text-slate-900')" x-text="previewStock.grade_c.toFixed(2)"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Invariance Check Badge -->
                                <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-3 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-indigo-900">Total Physical Invariant:</span>
                                        <span class="font-mono font-black text-indigo-950" x-text="previewStock.total.toFixed(2) + ' ' + (selectedProduct?.unit || 'kg')"></span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1.5 text-[10px] font-black text-emerald-700">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Conserves exact physical stock quantity</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="!selectedProduct" class="py-6 text-center text-xs font-bold text-slate-400">
                            Select a product to view live stock preview.
                        </div>
                    </div>

                    <!-- Reversible Transfers Card -->
                    <template x-if="selectedProduct && reversibleBatches.length > 0">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                            <h3 class="text-sm font-black text-slate-950">Recent Transferred Batches</h3>
                            <p class="text-[11px] text-slate-500 font-semibold">Batches eligible for reversal back to source grade.</p>
                            <div class="space-y-2">
                                <template x-for="rb in reversibleBatches" :key="rb.id">
                                    <div class="flex items-center justify-between rounded-lg border border-slate-100 p-2 text-xs">
                                        <div>
                                            <span class="font-black font-mono text-slate-900" x-text="rb.reference"></span>
                                            <span class="ml-1 text-[10px] text-slate-400 font-bold" x-text="'Grade ' + rb.grade"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-slate-600" x-text="rb.available_qty.toFixed(2) + ' kg'"></span>
                                            <form method="POST" :action="'{{ url('inventory/grade-stock/reverse') }}/' + rb.id" onsubmit="return confirm('Reverse this grade transfer back to its source batch?')">
                                                @csrf
                                                <input type="hidden" name="quantity" :value="rb.available_qty">
                                                <button type="submit" class="rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-black text-rose-700 hover:bg-rose-100">Reverse</button>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function gradeStockApp(config) {
            return {
                currentTab: config.currentTab || 'inventory',
                selectedWarehouseId: config.selectedWarehouseId,
                selectedProductId: config.selectedProductId,
                selectedProduct: config.initialProductDetails ? config.initialProductDetails.product : null,
                isLoadingDetails: false,
                isSubmitting: false,
                clientSubmissionId: 'gs_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                sourceGrade: 'U',
                targetGrade: 'A',
                selectedBatchId: '',
                quantity: '',
                notes: '',
                grades: [
                    { code: 'U', label: 'Mixed (U)' },
                    { code: 'A', label: 'Grade A' },
                    { code: 'B', label: 'Grade B' },
                    { code: 'C', label: 'Grade C' }
                ],
                currentStock: {
                    mixed: config.initialProductDetails ? config.initialProductDetails.stock.mixed : 0,
                    grade_a: config.initialProductDetails ? config.initialProductDetails.stock.grade_a : 0,
                    grade_b: config.initialProductDetails ? config.initialProductDetails.stock.grade_b : 0,
                    grade_c: config.initialProductDetails ? config.initialProductDetails.stock.grade_c : 0,
                    total: config.initialProductDetails ? config.initialProductDetails.stock.total : 0
                },
                previewStock: {
                    mixed: 0,
                    grade_a: 0,
                    grade_b: 0,
                    grade_c: 0,
                    total: 0
                },
                batchesByGrade: config.initialProductDetails ? config.initialProductDetails.batches_by_grade : { U: [], A: [], B: [], C: [] },
                reversibleBatches: config.initialProductDetails ? (config.initialProductDetails.reversible_batches || []) : [],

                init() {
                    this.updatePreview();
                },

                setTab(tab) {
                    this.currentTab = tab;
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    if (this.selectedProductId) {
                        url.searchParams.set('product_id', this.selectedProductId);
                    }
                    window.history.replaceState({}, '', url);
                },

                startTransfer(productId) {
                    this.selectedProductId = productId;
                    this.setTab('transfer');
                    this.onProductSelect(productId);
                },

                async onProductSelect(productId) {
                    if (!productId) {
                        this.selectedProduct = null;
                        return;
                    }
                    this.isLoadingDetails = true;
                    try {
                        const res = await fetch(`{{ route('inventory.grade-stock.index') }}/product-details/${productId}?warehouse_id=${this.selectedWarehouseId}`);
                        const json = await res.json();
                        if (json.success && json.data) {
                            const data = json.data;
                            this.selectedProduct = data.product;
                            this.currentStock = data.stock;
                            this.batchesByGrade = data.batches_by_grade;
                            this.reversibleBatches = data.reversible_batches || [];
                            this.onSourceGradeChange();
                        }
                    } catch (e) {
                        console.error('Failed to load transfer details', e);
                    } finally {
                        this.isLoadingDetails = false;
                        this.updatePreview();
                    }
                },

                getStockForGrade(gradeCode) {
                    if (gradeCode === 'U') return this.currentStock.mixed;
                    if (gradeCode === 'A') return this.currentStock.grade_a;
                    if (gradeCode === 'B') return this.currentStock.grade_b;
                    if (gradeCode === 'C') return this.currentStock.grade_c;
                    return 0;
                },

                get currentEligibleBatches() {
                    return this.batchesByGrade[this.sourceGrade] || [];
                },

                get maxAvailableSourceStock() {
                    if (this.selectedBatchId) {
                        const b = this.currentEligibleBatches.find(x => x.id == this.selectedBatchId);
                        return b ? b.available_qty : 0;
                    }
                    return this.getStockForGrade(this.sourceGrade);
                },

                get isTransferValid() {
                    const q = parseFloat(this.quantity);
                    return this.selectedProduct &&
                           this.sourceGrade &&
                           this.targetGrade &&
                           this.sourceGrade !== this.targetGrade &&
                           !isNaN(q) &&
                           q > 0.0001 &&
                           q <= (this.maxAvailableSourceStock + 0.001);
                },

                onSourceGradeChange() {
                    if (this.targetGrade === this.sourceGrade) {
                        const remaining = this.grades.filter(g => g.code !== this.sourceGrade);
                        this.targetGrade = remaining[0]?.code || 'A';
                    }
                    this.selectedBatchId = '';
                    this.updatePreview();
                },

                onBatchSelect() {
                    this.updatePreview();
                },

                updatePreview() {
                    const q = parseFloat(this.quantity) || 0;
                    this.previewStock.mixed = this.currentStock.mixed;
                    this.previewStock.grade_a = this.currentStock.grade_a;
                    this.previewStock.grade_b = this.currentStock.grade_b;
                    this.previewStock.grade_c = this.currentStock.grade_c;

                    if (this.sourceGrade && this.targetGrade && this.sourceGrade !== this.targetGrade && q > 0) {
                        if (this.sourceGrade === 'U') this.previewStock.mixed = Math.max(0, this.currentStock.mixed - q);
                        if (this.sourceGrade === 'A') this.previewStock.grade_a = Math.max(0, this.currentStock.grade_a - q);
                        if (this.sourceGrade === 'B') this.previewStock.grade_b = Math.max(0, this.currentStock.grade_b - q);
                        if (this.sourceGrade === 'C') this.previewStock.grade_c = Math.max(0, this.currentStock.grade_c - q);

                        if (this.targetGrade === 'U') this.previewStock.mixed += q;
                        if (this.targetGrade === 'A') this.previewStock.grade_a += q;
                        if (this.targetGrade === 'B') this.previewStock.grade_b += q;
                        if (this.targetGrade === 'C') this.previewStock.grade_c += q;
                    }

                    this.previewStock.total = this.previewStock.mixed + this.previewStock.grade_a + this.previewStock.grade_b + this.previewStock.grade_c;
                },

                onFormSubmit(event) {
                    if (!this.isTransferValid || this.isSubmitting) {
                        event.preventDefault();
                        return;
                    }
                    this.isSubmitting = true;
                }
            };
        }
    </script>
</x-layouts.inventory>
