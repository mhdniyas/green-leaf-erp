<x-layouts.inventory title="Delivery Operations Dashboard">
    @php
        $dashboardQuery = request()->only(['date', 'shop_id', 'category_id', 'status', 'invoice_lock', 'warehouse_id']);
        $dateLabel = \Carbon\Carbon::parse($date)->format('d F Y');
        $buttonClass = 'inline-flex h-9 items-center justify-center rounded-xl px-3 text-[11px] font-black uppercase tracking-[0.12em] transition';
        $softButtonClass = $buttonClass.' border border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
        $canSegregate = auth()->user()?->can('inventory.sorting.process')
            || auth()->user()?->can('warehouse.receive.sort')
            || auth()->user()?->hasRole(['admin', 'warehouse_receiver']);
    @endphp

    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inventory.deliveries.dashboard', array_merge($dashboardQuery, ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')])) }}" class="{{ $softButtonClass }}">Prev</a>
            <form id="date-form" method="GET" action="{{ route('inventory.deliveries.dashboard') }}" class="flex items-center gap-2">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
                <input type="hidden" name="invoice_lock" value="{{ $selectedInvoiceLock }}">
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </form>
            <a href="{{ route('inventory.deliveries.dashboard', array_merge($dashboardQuery, ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')])) }}" class="{{ $softButtonClass }}">Next</a>
            <a href="{{ route('inventory.deliveries.dashboard', array_merge($dashboardQuery, ['date' => today()->toDateString()])) }}" class="{{ $buttonClass }} border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100">Today</a>
        </div>
    </x-slot:actions>

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Admin Delivery Desk</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Delivery Operations - {{ $dateLabel }}</h1>
                    <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-600">Check each shop bill by category, move loadout to delivery, reopen loadout corrections, approve delivery review, and lock the invoice when final.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('inventory.grade-stock.index', ['warehouse_id' => $selectedWarehouseId]) }}" class="{{ $buttonClass }} border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100">Grade Stock</a>
                    <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}" class="{{ $softButtonClass }}">Shop Cards</a>
                    <a href="{{ route('warehouse.loadout.index', ['date' => $date]) }}" class="{{ $softButtonClass }}">Loadout Board</a>
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => $date]) }}" class="{{ $buttonClass }} bg-slate-950 text-white hover:bg-slate-800">Invoices</a>
                </div>
            </div>

            <form method="GET" action="{{ route('inventory.deliveries.dashboard') }}" class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-6">
                <input type="hidden" name="date" value="{{ $date }}">
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Shop</span>
                    <select name="shop_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All shops</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" @selected((int) $selectedShopId === (int) $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Category</span>
                    <select name="category_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) $selectedCategoryId === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Warehouse</span>
                    <select name="warehouse_id" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All warehouses</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected((int) $selectedWarehouseId === (int) $wh->id)>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Status</span>
                    <select name="status" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="all" @selected($selectedStatus === 'all')>All status</option>
                        <option value="loadout" @selected($selectedStatus === 'loadout')>Loadout</option>
                        <option value="in_transit" @selected($selectedStatus === 'in_transit')>In transit</option>
                        <option value="review" @selected($selectedStatus === 'review')>Admin review</option>
                        <option value="delivered" @selected($selectedStatus === 'delivered')>Delivered</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Invoice</span>
                    <select name="invoice_lock" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="all" @selected($selectedInvoiceLock === 'all')>All invoices</option>
                        <option value="open" @selected($selectedInvoiceLock === 'open')>Open invoices</option>
                        <option value="locked" @selected($selectedInvoiceLock === 'locked')>Locked invoices</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="{{ $buttonClass }} w-full bg-emerald-600 text-white hover:bg-emerald-700">Filter</button>
                    <a href="{{ route('inventory.deliveries.dashboard', ['date' => $date]) }}" class="{{ $softButtonClass }}">Clear</a>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Orders</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ $totalOrdersCount }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-700">Packing</p>
                <p class="mt-2 text-2xl font-black text-cyan-950">{{ $packingCount }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Transit</p>
                <p class="mt-2 text-2xl font-black text-indigo-950">{{ $inTransitCount }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Delivered</p>
                <p class="mt-2 text-2xl font-black text-emerald-950">{{ $deliveredCount }}</p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Shortage</p>
                <p class="mt-2 text-xl font-black text-rose-950">Rs. {{ number_format($totalShortageValue, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Cash Variance</p>
                <p class="mt-2 text-xl font-black text-amber-950">Rs. {{ number_format(abs($totalCashDiscrepancy), 2) }}</p>
            </div>
        </section>

        <!-- Warehouse Produce & Grade Segregation Section -->
        <section id="warehouse-segregation-section" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Warehouse Inventory & Grading</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Produce Grade Segregation</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Segregate available Mixed stock into Grade A, B, or C before shop order loadout.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-800">
                        {{ $segregationProducts->count() }} Eligible Produce {{ Str::plural('Item', $segregationProducts->count()) }}
                    </span>
                </div>
            </div>

            <div id="segregation-alert" class="mt-4 hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800 transition"></div>

            @if($segregationProducts->isNotEmpty())
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Warehouse</th>
                                <th class="px-4 py-3 text-right">Mixed Stock</th>
                                <th class="px-4 py-3 text-right">Grade A</th>
                                <th class="px-4 py-3 text-right">Grade B</th>
                                <th class="px-4 py-3 text-right">Grade C</th>
                                <th class="px-4 py-3 text-right">Total Stock</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold text-slate-800">
                            @foreach($segregationProducts as $p)
                                <tr id="seg-product-row-{{ $p['product_id'] }}" class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $p['product_name'] }}</p>
                                        <div class="mt-0.5 flex items-center gap-1.5">
                                            <span class="font-mono text-[10px] text-slate-400">#{{ $p['product_sku'] }}</span>
                                            <span class="text-[10px] text-slate-400">•</span>
                                            <span class="text-[10px] text-slate-500">{{ $p['category_name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.1em] text-slate-700">
                                            {{ $p['warehouse_name'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900">
                                        <span id="seg-mixed-{{ $p['product_id'] }}">{{ number_format($p['available_mixed'], 2) }} {{ $p['product_unit'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-700">
                                        <span id="seg-grade-a-{{ $p['product_id'] }}">{{ number_format($p['grade_a'], 2) }} {{ $p['product_unit'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-blue-700">
                                        <span id="seg-grade-b-{{ $p['product_id'] }}">{{ number_format($p['grade_b'], 2) }} {{ $p['product_unit'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-amber-700">
                                        <span id="seg-grade-c-{{ $p['product_id'] }}">{{ number_format($p['grade_c'], 2) }} {{ $p['product_unit'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">
                                        <span id="seg-total-{{ $p['product_id'] }}">{{ number_format($p['total_stock'], 2) }} {{ $p['product_unit'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($canSegregate && $p['available_mixed'] > 0.001)
                                            <button type="button"
                                                onclick="openSegregationModal({{ $p['product_id'] }})"
                                                id="btn-segregate-{{ $p['product_id'] }}"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-[10px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Segregate
                                            </button>
                                        @elseif(! $canSegregate)
                                            <span class="text-[10px] font-bold text-slate-400">View only</span>
                                        @else
                                            <span class="text-[10px] font-bold text-slate-400">No Mixed</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">
                    <p class="text-xs font-bold text-slate-500">No products with available Mixed stock found for this date and warehouse filter.</p>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Cards</p>
                    <h2 class="mt-1 text-lg font-black text-slate-950">Admin loadout and invoice actions</h2>
                </div>
                <span class="w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                    Last update {{ $lastUpdatedAt ? $lastUpdatedAt->setTimezone(config('app.timezone'))->format('h:i A') : 'none' }}
                </span>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @forelse($shopCards as $card)
                    @php
                        $toneClasses = match ($card['status_tone']) {
                            'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'info' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'danger' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200',
                        };
                        $lockClasses = $card['invoice_locked']
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-amber-200 bg-amber-50 text-amber-800';
                    @endphp
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-base font-black text-slate-950">{{ $card['shop_name'] }}</h3>
                                <p class="mt-1 font-mono text-[11px] font-bold text-slate-500">{{ $card['order_number'] }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-600">{{ $card['invoice_number'] ?? 'Invoice not generated' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $toneClasses }}">{{ $card['status_label'] }}</span>
                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $lockClasses }}">{{ $card['invoice_locked'] ? 'Invoice Locked' : 'Invoice Open' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Loaded</p>
                                <p class="mt-1 text-lg font-black text-slate-950">{{ $card['loaded_items'] }}/{{ $card['total_items'] }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Bill</p>
                                <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($card['invoice_total'], 2) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Paid</p>
                                <p class="mt-1 text-lg font-black text-emerald-700">Rs. {{ number_format($card['invoice_paid'], 2) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Balance</p>
                                <p class="mt-1 text-lg font-black text-rose-700">Rs. {{ number_format($card['invoice_balance'], 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-500">
                                <span>Warehouse progress</span>
                                <span>{{ $card['progress_percentage'] }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-cyan-500" style="width: {{ $card['progress_percentage'] }}%;"></div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ $card['loadout_url'] }}" class="{{ $softButtonClass }}">Edit Loadout</a>
                            <a href="{{ $card['slip_url'] }}" target="_blank" class="{{ $softButtonClass }}">Slip</a>
                            @if($card['check_in_url'])
                                <a href="{{ $card['check_in_url'] }}" class="{{ $buttonClass }} bg-emerald-600 text-white hover:bg-emerald-700">Check-in</a>
                            @endif
                            @if($card['invoice_url'])
                                <a href="{{ $card['invoice_url'] }}" class="{{ $softButtonClass }}">Invoice</a>
                            @endif
                            @if($card['invoice_pdf_url'])
                                <a href="{{ $card['invoice_pdf_url'] }}" target="_blank" class="{{ $softButtonClass }}">PDF</a>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3">
                            @if($card['can_merge_duplicates'])
                                <form method="POST" action="{{ route('warehouse.loadout.merge-duplicates.all', $card['route_key']) }}">
                                    @csrf
                                    <input type="hidden" name="return_to_dashboard" value="1">
                                    <button class="{{ $softButtonClass }}" type="submit">Merge Duplicates</button>
                                </form>
                            @endif
                            @if($card['can_remove_unpriced'])
                                <form method="POST" action="{{ route('warehouse.loadout.remove-unpriced-items', $card['route_key']) }}">
                                    @csrf
                                    <input type="hidden" name="return_to_dashboard" value="1">
                                    <button class="{{ $softButtonClass }}" type="submit">Remove Unpriced</button>
                                </form>
                            @endif
                            @if($card['can_move_to_delivery'])
                                <form method="POST" action="{{ route('warehouse.loadout.move-to-delivery', $card['route_key']) }}">
                                    @csrf
                                    <input type="hidden" name="return_to_dashboard" value="1">
                                    <button class="{{ $buttonClass }} bg-indigo-600 text-white hover:bg-indigo-700" type="submit">Move Delivery</button>
                                </form>
                            @endif
                            @if($card['can_move_to_partial_delivery'])
                                <form method="POST" action="{{ route('warehouse.loadout.move-to-partial-delivery', $card['route_key']) }}">
                                    @csrf
                                    <input type="hidden" name="return_to_dashboard" value="1">
                                    <button class="{{ $buttonClass }} bg-indigo-600 text-white hover:bg-indigo-700" type="submit">Partial Delivery</button>
                                </form>
                            @endif
                            @if($card['can_reopen_loadout'])
                                <form method="POST" action="{{ route('warehouse.loadout.move-to-loadout', $card['route_key']) }}">
                                    @csrf
                                    <input type="hidden" name="return_to_dashboard" value="1">
                                    <button class="{{ $buttonClass }} border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100" type="submit">Reopen Loadout</button>
                                </form>
                            @endif
                            @if($card['can_lock_invoice'])
                                <form method="POST" action="{{ route('inventory.deliveries.dashboard.lock-invoice', $card['route_key']) }}" onsubmit="return confirm('Lock this invoice as final? Further loadout and invoice edits will be blocked.');">
                                    @csrf
                                    <button class="{{ $buttonClass }} bg-slate-950 text-white hover:bg-slate-800" type="submit">Lock Invoice</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center lg:col-span-2">
                        <p class="text-base font-black text-slate-700">No shop orders found for these filters.</p>
                    </div>
                @endforelse
            </div>

            @if($shopCards->hasPages())
                <div class="mt-5">
                    {{ $shopCards->links() }}
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-slate-950 p-5 text-white shadow-sm">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-300">Step 1</p>
                    <p class="mt-1 text-sm font-black">Filter the day, shop, category, and invoice lock state.</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-300">Step 2</p>
                    <p class="mt-1 text-sm font-black">Use each shop card to reopen loadout, move delivery, check in, or lock invoice.</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-300">Step 3</p>
                    <p class="mt-1 text-sm font-black">Open invoice or PDF from the shop card before final lock.</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-300">Final</p>
                    <p class="mt-1 text-sm font-black">Locked invoices block further loadout and bill edits.</p>
                </div>
            </div>
        </section>
    </div>

    <!-- Segregation Modal -->
    <div id="segregation-modal" class="fixed inset-0 z-50 flex hidden items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-xs">
        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-600">Warehouse Segregation</p>
                    <h3 id="modal-product-title" class="text-xl font-black text-slate-950">Product Name</h3>
                    <div class="mt-0.5 flex items-center gap-2">
                        <span id="modal-warehouse-badge" class="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700"></span>
                        <span id="modal-sku-badge" class="font-mono text-[10px] text-slate-400"></span>
                    </div>
                </div>
                <button type="button" onclick="closeSegregationModal()" class="rounded-xl border border-slate-200 bg-white p-2 text-slate-400 transition hover:bg-slate-50 hover:text-slate-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div id="modal-error-banner" class="mt-3 hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-800"></div>

            <div class="mt-4 grid grid-cols-4 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-center">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Available Mixed</p>
                    <p id="modal-stat-mixed" class="mt-0.5 text-sm font-black text-slate-900">0.00 kg</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-emerald-600">Grade A</p>
                    <p id="modal-stat-grade-a" class="mt-0.5 text-sm font-black text-emerald-700">0.00 kg</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-blue-600">Grade B</p>
                    <p id="modal-stat-grade-b" class="mt-0.5 text-sm font-black text-blue-700">0.00 kg</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-amber-600">Grade C</p>
                    <p id="modal-stat-grade-c" class="mt-0.5 text-sm font-black text-amber-700">0.00 kg</p>
                </div>
            </div>

            <form id="segregation-form" method="POST" action="{{ route('inventory.deliveries.dashboard.segregate') }}" onsubmit="submitSegregation(event)" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
                <input type="hidden" name="invoice_lock" value="{{ $selectedInvoiceLock }}">
                <input type="hidden" id="modal-client-submission-id" name="client_submission_id" value="">

                <label class="block space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">Source Mixed Batch</span>
                        <span id="modal-batch-count-badge" class="text-[10px] font-bold text-slate-400"></span>
                    </div>
                    <select id="modal-batch-id" name="batch_id" required onchange="onBatchSelected()" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                    </select>
                </label>

                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">Target Grade</span>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 p-2 text-center text-xs font-black transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-800">
                            <input type="radio" name="target_grade" value="A" checked onchange="updateLivePreview()" class="sr-only"> Grade A
                        </label>
                        <label class="flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 p-2 text-center text-xs font-black transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-800">
                            <input type="radio" name="target_grade" value="B" onchange="updateLivePreview()" class="sr-only"> Grade B
                        </label>
                        <label class="flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 p-2 text-center text-xs font-black transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50 has-[:checked]:text-amber-800">
                            <input type="radio" name="target_grade" value="C" onchange="updateLivePreview()" class="sr-only"> Grade C
                        </label>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">Quantity to Segregate</span>
                        <span class="text-[10px] font-bold text-slate-400">Available: <span id="modal-batch-avail-num">0</span> <span class="modal-unit">kg</span></span>
                    </div>
                    <div class="relative">
                        <input type="number" step="0.01" min="0.01" id="modal-quantity" name="quantity" required placeholder="0.00" oninput="updateLivePreview()" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 pr-12 text-base font-black text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                        <span class="pointer-events-none absolute right-3 top-2.5 text-xs font-bold text-slate-400 modal-unit">kg</span>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="button" onclick="setQuantityFraction(0.25)" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-600 hover:bg-slate-50 transition">25%</button>
                        <button type="button" onclick="setQuantityFraction(0.50)" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-600 hover:bg-slate-50 transition">50%</button>
                        <button type="button" onclick="setQuantityFraction(1.00)" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-600 hover:bg-slate-50 transition">All Mixed</button>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-1.5 text-xs font-bold">
                    <div class="flex justify-between text-slate-600">
                        <span>Remaining Mixed on Batch:</span>
                        <span id="preview-remaining-mixed" class="font-black text-slate-900">0.00 kg</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Selected Target Grade Stock Result:</span>
                        <span id="preview-target-grade-stock" class="font-black text-emerald-700">0.00 kg</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-1 text-[11px] text-slate-500">
                        <span>Total Product Stock Conserved:</span>
                        <span id="preview-total-stock">0.00 kg</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" onclick="closeSegregationModal()" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                    <button type="submit" id="btn-confirm-segregate" class="inline-flex h-10 items-center justify-center rounded-xl bg-emerald-600 px-5 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-700 disabled:opacity-50">
                        Confirm Segregation
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            window.segregationProductsMap = @json($segregationProducts->keyBy('product_id'));
            let currentProduct = null;
            let currentBatchAvailable = 0;

            function openSegregationModal(productId) {
                currentProduct = window.segregationProductsMap[productId];
                if (!currentProduct) return;

                document.getElementById('modal-product-title').textContent = currentProduct.product_name;
                document.getElementById('modal-warehouse-badge').textContent = currentProduct.warehouse_name;
                document.getElementById('modal-sku-badge').textContent = '#' + currentProduct.product_sku;

                document.querySelectorAll('.modal-unit').forEach(el => el.textContent = currentProduct.product_unit);

                document.getElementById('modal-stat-mixed').textContent = currentProduct.available_mixed.toFixed(2) + ' ' + currentProduct.product_unit;
                document.getElementById('modal-stat-grade-a').textContent = currentProduct.grade_a.toFixed(2) + ' ' + currentProduct.product_unit;
                document.getElementById('modal-stat-grade-b').textContent = currentProduct.grade_b.toFixed(2) + ' ' + currentProduct.product_unit;
                document.getElementById('modal-stat-grade-c').textContent = currentProduct.grade_c.toFixed(2) + ' ' + currentProduct.product_unit;

                // Populate batch options
                const batchSelect = document.getElementById('modal-batch-id');
                batchSelect.innerHTML = '';

                if (currentProduct.batches && currentProduct.batches.length > 0) {
                    document.getElementById('modal-batch-count-badge').textContent = currentProduct.batches.length + (currentProduct.batches.length === 1 ? ' batch' : ' batches');
                    currentProduct.batches.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.dataset.available = b.available_mixed_qty;
                        opt.textContent = `${b.reference} — ${b.available_mixed_qty.toFixed(2)} ${currentProduct.product_unit} available (Rec'd ${b.received_at})`;
                        batchSelect.appendChild(opt);
                    });
                } else {
                    document.getElementById('modal-batch-count-badge').textContent = '0 batches';
                }

                const stableSubmissionId = 'seg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                document.getElementById('modal-client-submission-id').value = stableSubmissionId;

                document.getElementById('modal-error-banner').classList.add('hidden');
                document.getElementById('modal-error-banner').textContent = '';
                document.getElementById('modal-quantity').value = '';

                onBatchSelected();

                document.getElementById('segregation-modal').classList.remove('hidden');

                // Optional live fetch of latest batches
                fetch(`{{ route('inventory.deliveries.dashboard') }}/segregation-data/${currentProduct.product_id}?warehouse_id=${currentProduct.warehouse_id}&date={{ $date }}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.batches && data.batches.length > 0) {
                            currentProduct.batches = data.batches;
                            currentProduct.available_mixed = data.stock.mixed;
                            currentProduct.grade_a = data.stock.grade_a;
                            currentProduct.grade_b = data.stock.grade_b;
                            currentProduct.grade_c = data.stock.grade_c;
                            currentProduct.total_stock = data.stock.total;

                            if (!document.getElementById('segregation-modal').classList.contains('hidden')) {
                                document.getElementById('modal-stat-mixed').textContent = data.stock.mixed.toFixed(2) + ' ' + currentProduct.product_unit;
                                document.getElementById('modal-stat-grade-a').textContent = data.stock.grade_a.toFixed(2) + ' ' + currentProduct.product_unit;
                                document.getElementById('modal-stat-grade-b').textContent = data.stock.grade_b.toFixed(2) + ' ' + currentProduct.product_unit;
                                document.getElementById('modal-stat-grade-c').textContent = data.stock.grade_c.toFixed(2) + ' ' + currentProduct.product_unit;

                                const currentVal = batchSelect.value;
                                batchSelect.innerHTML = '';
                                data.batches.forEach(b => {
                                    const opt = document.createElement('option');
                                    opt.value = b.id;
                                    opt.dataset.available = b.available_mixed_qty;
                                    opt.textContent = `${b.reference} — ${b.available_mixed_qty.toFixed(2)} ${currentProduct.product_unit} available (Rec'd ${b.received_at})`;
                                    if (String(b.id) === String(currentVal)) opt.selected = true;
                                    batchSelect.appendChild(opt);
                                });
                                onBatchSelected();
                            }
                        }
                    }).catch(() => {});
            }

            function closeSegregationModal() {
                document.getElementById('segregation-modal').classList.add('hidden');
                currentProduct = null;
            }

            function onBatchSelected() {
                const select = document.getElementById('modal-batch-id');
                const selectedOpt = select.selectedOptions[0];
                currentBatchAvailable = selectedOpt ? parseFloat(selectedOpt.dataset.available || '0') : 0;

                document.getElementById('modal-batch-avail-num').textContent = currentBatchAvailable.toFixed(2);
                const qtyInput = document.getElementById('modal-quantity');
                qtyInput.max = currentBatchAvailable;

                updateLivePreview();
            }

            function setQuantityFraction(fraction) {
                if (!currentBatchAvailable) return;
                const qty = Math.min(currentBatchAvailable, Math.round((currentBatchAvailable * fraction) * 100) / 100);
                document.getElementById('modal-quantity').value = qty > 0 ? qty : '';
                updateLivePreview();
            }

            function getSelectedGrade() {
                const checked = document.querySelector('input[name="target_grade"]:checked');
                return checked ? checked.value : 'A';
            }

            function updateLivePreview() {
                if (!currentProduct) return;
                const qty = parseFloat(document.getElementById('modal-quantity').value || '0');
                const grade = getSelectedGrade();

                const remainingMixed = Math.max(0, currentBatchAvailable - qty);
                document.getElementById('preview-remaining-mixed').textContent = remainingMixed.toFixed(2) + ' ' + currentProduct.product_unit;

                let currentGradeStock = 0;
                if (grade === 'A') currentGradeStock = currentProduct.grade_a;
                else if (grade === 'B') currentGradeStock = currentProduct.grade_b;
                else if (grade === 'C') currentGradeStock = currentProduct.grade_c;

                const targetAfter = currentGradeStock + qty;
                document.getElementById('preview-target-grade-stock').textContent = targetAfter.toFixed(2) + ' ' + currentProduct.product_unit + ` (Grade ${grade})`;

                document.getElementById('preview-total-stock').textContent = currentProduct.total_stock.toFixed(2) + ' ' + currentProduct.product_unit;

                const btn = document.getElementById('btn-confirm-segregate');
                const errBanner = document.getElementById('modal-error-banner');

                if (qty > currentBatchAvailable + 0.001) {
                    errBanner.textContent = `Quantity (${qty} ${currentProduct.product_unit}) exceeds available batch mixed stock (${currentBatchAvailable} ${currentProduct.product_unit}).`;
                    errBanner.classList.remove('hidden');
                    btn.disabled = true;
                } else {
                    if (errBanner.textContent.includes('exceeds available')) {
                        errBanner.classList.add('hidden');
                        errBanner.textContent = '';
                    }
                    btn.disabled = qty <= 0.0001;
                }
            }

            async function submitSegregation(e) {
                e.preventDefault();
                const form = e.target;
                const btn = document.getElementById('btn-confirm-segregate');
                const errBanner = document.getElementById('modal-error-banner');

                const batchId = form.batch_id.value;
                const targetGrade = getSelectedGrade();
                const quantity = parseFloat(form.quantity.value || '0');
                const clientSubmissionId = form.client_submission_id.value;
                const csrfToken = document.querySelector('input[name="_token"]').value;

                if (quantity <= 0.001) {
                    errBanner.textContent = 'Please enter a valid quantity greater than zero.';
                    errBanner.classList.remove('hidden');
                    return;
                }

                if (quantity > currentBatchAvailable + 0.001) {
                    errBanner.textContent = `Quantity exceeds available batch stock of ${currentBatchAvailable} ${currentProduct.product_unit}.`;
                    errBanner.classList.remove('hidden');
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Segregating...';
                errBanner.classList.add('hidden');

                try {
                    const response = await fetch('{{ route('inventory.deliveries.dashboard.segregate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            batch_id: batchId,
                            target_grade: targetGrade,
                            quantity: quantity,
                            client_submission_id: clientSubmissionId,
                            _token: csrfToken
                        })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        const pId = data.product_id;
                        const unit = currentProduct.product_unit;

                        // Update in-memory map
                        if (window.segregationProductsMap[pId]) {
                            window.segregationProductsMap[pId].available_mixed = data.stock.mixed;
                            window.segregationProductsMap[pId].grade_a = data.stock.grade_a;
                            window.segregationProductsMap[pId].grade_b = data.stock.grade_b;
                            window.segregationProductsMap[pId].grade_c = data.stock.grade_c;
                            window.segregationProductsMap[pId].total_stock = data.stock.total;
                        }

                        // Update DOM row values
                        const mixedEl = document.getElementById(`seg-mixed-${pId}`);
                        if (mixedEl) mixedEl.textContent = `${data.stock.mixed.toFixed(2)} ${unit}`;

                        const gradeAEl = document.getElementById(`seg-grade-a-${pId}`);
                        if (gradeAEl) gradeAEl.textContent = `${data.stock.grade_a.toFixed(2)} ${unit}`;

                        const gradeBEl = document.getElementById(`seg-grade-b-${pId}`);
                        if (gradeBEl) gradeBEl.textContent = `${data.stock.grade_b.toFixed(2)} ${unit}`;

                        const gradeCEl = document.getElementById(`seg-grade-c-${pId}`);
                        if (gradeCEl) gradeCEl.textContent = `${data.stock.grade_c.toFixed(2)} ${unit}`;

                        const totalEl = document.getElementById(`seg-total-${pId}`);
                        if (totalEl) totalEl.textContent = `${data.stock.total.toFixed(2)} ${unit}`;

                        // Highlight updated row
                        const rowEl = document.getElementById(`seg-product-row-${pId}`);
                        if (rowEl) {
                            rowEl.classList.add('bg-emerald-50');
                            setTimeout(() => rowEl.classList.remove('bg-emerald-50'), 2500);
                        }

                        // Close modal
                        closeSegregationModal();

                        // Display success banner
                        const alertEl = document.getElementById('segregation-alert');
                        alertEl.textContent = '✓ ' + data.message;
                        alertEl.classList.remove('hidden');
                        setTimeout(() => alertEl.classList.add('hidden'), 7000);

                        // If mixed stock is zero, disable button
                        if (data.stock.mixed <= 0.001) {
                            const segBtn = document.getElementById(`btn-segregate-${pId}`);
                            if (segBtn) {
                                segBtn.outerHTML = '<span class="text-[10px] font-bold text-slate-400">No Mixed</span>';
                            }
                        }
                    } else {
                        const errMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Segregation failed.');
                        errBanner.textContent = errMsg;
                        errBanner.classList.remove('hidden');
                    }
                } catch (err) {
                    errBanner.textContent = 'A network error occurred. Please try again.';
                    errBanner.classList.remove('hidden');
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Confirm Segregation';
                }
            }

            // Keyboard Escape key closes modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSegregationModal();
                }
            });

            // Auto-refresh interval (skips if modal is open)
            setInterval(() => {
                const modal = document.getElementById('segregation-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    return;
                }
                const refreshUrl = new URL(window.location.href);
                refreshUrl.searchParams.set('_refresh', Date.now().toString());
                window.location.replace(refreshUrl.toString());
            }, 30000);
        </script>
    @endpush
</x-layouts.inventory>
