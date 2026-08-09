<x-layouts.inventory title="Delivery Operations Dashboard">
    @php
        $dashboardQuery = request()->only(['date', 'shop_id', 'category_id', 'status', 'invoice_lock']);
        $dateLabel = \Carbon\Carbon::parse($date)->format('d F Y');
        $buttonClass = 'inline-flex h-9 items-center justify-center rounded-xl px-3 text-[11px] font-black uppercase tracking-[0.12em] transition';
        $softButtonClass = $buttonClass.' border border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
    @endphp

    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inventory.deliveries.dashboard', array_merge($dashboardQuery, ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')])) }}" class="{{ $softButtonClass }}">Prev</a>
            <form id="date-form" method="GET" action="{{ route('inventory.deliveries.dashboard') }}" class="flex items-center gap-2">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">
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
                    <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}" class="{{ $softButtonClass }}">Shop Cards</a>
                    <a href="{{ route('warehouse.loadout.index', ['date' => $date]) }}" class="{{ $softButtonClass }}">Loadout Board</a>
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => $date]) }}" class="{{ $buttonClass }} bg-slate-950 text-white hover:bg-slate-800">Invoices</a>
                </div>
            </div>

            <form method="GET" action="{{ route('inventory.deliveries.dashboard') }}" class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-5">
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

    @push('scripts')
        <script>
            setInterval(() => {
                const refreshUrl = new URL(window.location.href);
                refreshUrl.searchParams.set('_refresh', Date.now().toString());
                window.location.replace(refreshUrl.toString());
            }, 30000);
        </script>
    @endpush
</x-layouts.inventory>
