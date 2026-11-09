<x-layouts.app title="Warehouse Credit Note Returns — Green Leaf ERP">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-4 py-3 lg:max-w-7xl lg:gap-5 lg:px-6 lg:py-5"
        x-data="warehouseCreditNoteManager({
            warehouses: @js($warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name, 'code' => $w->code])),
            defaultWarehouseId: {{ $warehouses->first()?->id ?? 'null' }}
        })">

        {{-- Hero Header Box --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)] lg:rounded-[2rem] lg:shadow-[0_20px_48px_rgba(15,23,42,0.22)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4 sm:px-5 lg:px-6 lg:py-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-indigo-500/20 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-indigo-300 border border-indigo-500/30">
                                Physical Inventory Returns
                            </span>
                            <span class="text-xs text-slate-400">&bull;</span>
                            <span class="text-xs text-slate-300 font-semibold">Shop Credit Notes</span>
                        </div>
                        <h1 class="mt-1 text-lg font-black tracking-tight sm:text-[1.75rem]">Return Goods Receiving</h1>
                        <p class="mt-1.5 max-w-2xl text-xs font-medium leading-5 text-slate-200 sm:text-sm">
                            Physically inspect returned goods from finalized shop credit notes, confirm actual received grades (A/B/C), log transit damage into wastage, or document disputes.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('warehouse.receiver.checklist') }}"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 bg-white/10 px-3.5 py-2 text-xs font-bold text-white hover:bg-white/20 transition shadow-xs">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            <span>Checklist</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 shadow-xs flex items-center gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <span class="text-xs font-bold text-emerald-900">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs flex items-center gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <span class="text-xs font-bold text-rose-800">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Statistics Quick Counters --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-600">Pending Receipt</span>
                <span class="mt-1 block font-mono text-xl sm:text-2xl font-black text-slate-900">{{ $counts['pending'] }}</span>
                <span class="mt-0.5 block text-[10px] text-slate-400">Awaiting arrival</span>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-blue-600">Partially Received</span>
                <span class="mt-1 block font-mono text-xl sm:text-2xl font-black text-slate-900">{{ $counts['partially_received'] }}</span>
                <span class="mt-0.5 block text-[10px] text-slate-400">Partial batches arrived</span>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-emerald-600">Verified & Stocked</span>
                <span class="mt-1 block font-mono text-xl sm:text-2xl font-black text-slate-900">{{ $counts['verified'] }}</span>
                <span class="mt-0.5 block text-[10px] text-slate-400">Fully reconciled</span>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-rose-600">Disputed / Missing</span>
                <span class="mt-1 block font-mono text-xl sm:text-2xl font-black text-slate-900">{{ $counts['disputed'] }}</span>
                <span class="mt-0.5 block text-[10px] text-slate-400">Not received or rejected</span>
            </div>
        </div>

        {{-- Filters & Search Section --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs space-y-4">
            {{-- Status Tabs --}}
            <div class="flex gap-2 overflow-x-auto pb-1 border-b border-slate-100">
                <a href="{{ route('warehouse.receiver.credit-notes.index', array_merge(request()->except('page'), ['status' => 'pending'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition whitespace-nowrap {{ $statusFilter === 'pending' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>Pending</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black {{ $statusFilter === 'pending' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $counts['pending'] }}</span>
                </a>
                <a href="{{ route('warehouse.receiver.credit-notes.index', array_merge(request()->except('page'), ['status' => 'partially_received'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition whitespace-nowrap {{ $statusFilter === 'partially_received' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>Partially Received</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black {{ $statusFilter === 'partially_received' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $counts['partially_received'] }}</span>
                </a>
                <a href="{{ route('warehouse.receiver.credit-notes.index', array_merge(request()->except('page'), ['status' => 'verified'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition whitespace-nowrap {{ $statusFilter === 'verified' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>Verified</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black {{ $statusFilter === 'verified' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $counts['verified'] }}</span>
                </a>
                <a href="{{ route('warehouse.receiver.credit-notes.index', array_merge(request()->except('page'), ['status' => 'disputed'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition whitespace-nowrap {{ $statusFilter === 'disputed' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>Disputed</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black {{ $statusFilter === 'disputed' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $counts['disputed'] }}</span>
                </a>
                <a href="{{ route('warehouse.receiver.credit-notes.index', array_merge(request()->except('page'), ['status' => 'all'])) }}"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-bold transition whitespace-nowrap {{ $statusFilter === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span>All Returns</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black {{ $statusFilter === 'all' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $counts['all'] }}</span>
                </a>
            </div>

            {{-- Filter Form --}}
            <form action="{{ route('warehouse.receiver.credit-notes.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                <input type="hidden" name="status" value="{{ $statusFilter }}">

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $searchQuery }}" placeholder="Credit Note, Shop, Product, Invoice..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Shop</label>
                    <select name="shop_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-500">
                        <option value="">All Shops</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected((string) $shopId === (string) $shop->id)>{{ $shop->name }} ({{ $shop->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Product</label>
                    <select name="product_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:border-indigo-500">
                        <option value="">All Products</option>
                        @foreach ($products as $prod)
                            <option value="{{ $prod->id }}" @selected((string) $productId === (string) $prod->id)>{{ $prod->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Date Range</label>
                    <div class="flex items-center gap-1.5">
                        <input type="date" name="start_date" value="{{ $startDate }}" class="w-1/2 rounded-xl border border-slate-200 bg-slate-50 px-2 py-1.5 text-[11px] font-bold text-slate-800 focus:bg-white">
                        <span class="text-slate-400">&ndash;</span>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="w-1/2 rounded-xl border border-slate-200 bg-slate-50 px-2 py-1.5 text-[11px] font-bold text-slate-800 focus:bg-white">
                    </div>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition">
                        Filter
                    </button>
                    <a href="{{ route('warehouse.receiver.credit-notes.index', ['status' => $statusFilter]) }}"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        {{-- Main Physical Return Queue Table --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Physical Return Items</h2>
                    <p class="text-xs text-slate-500">Items tagged for warehouse restock awaiting physical receipt, grade assessment, or damage write-off.</p>
                </div>
                <span class="text-xs font-bold text-slate-600 font-mono">{{ $items->total() }} return item(s)</span>
            </div>

            @if ($items->isEmpty())
                <div class="py-12 text-center text-xs text-slate-400 space-y-2 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                    <i data-lucide="package-check" class="w-10 h-10 mx-auto text-slate-300"></i>
                    <p class="font-bold text-slate-600 text-sm">No return items matching filter criteria</p>
                    <p class="text-[11px] text-slate-400">All physical return goods have either been processed or no credit notes require warehouse receipt under this filter.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="py-2.5 px-3">Credit Note / Shop</th>
                                <th class="py-2.5 px-3">Product</th>
                                <th class="py-2.5 px-3">Original Invoice</th>
                                <th class="py-2.5 px-3 text-center">Orig Grade</th>
                                <th class="py-2.5 px-3 text-right">Expected Qty</th>
                                <th class="py-2.5 px-3 text-right">Received Qty</th>
                                <th class="py-2.5 px-3 text-right">Remaining</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($items as $item)
                                @php
                                    $alreadyReceived = round((float) $item->receipts->whereIn('status', ['verified', 'damaged'])->whereNull('reversed_at')->sum('received_quantity'), 4);
                                    $expectedQty = (float) $item->adjusted_quantity;
                                    $remainingQty = max(0.00, round($expectedQty - $alreadyReceived, 4));
                                    $isVoided = $item->creditNote?->isVoided();
                                    $statusValue = $item->receipt_status?->value ?? (string) $item->receipt_status;
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition {{ $isVoided ? 'bg-slate-50/40 opacity-70' : '' }}">
                                    {{-- Credit Note & Shop --}}
                                    <td class="py-3 px-3 min-w-[150px]">
                                        <div class="font-mono font-bold text-slate-900 flex items-center gap-1.5">
                                            <span>{{ $item->creditNote?->credit_note_number }}</span>
                                            @if ($isVoided)
                                                <span class="rounded bg-rose-100 px-1.5 py-0.2 text-[9px] font-extrabold text-rose-800 uppercase">Voided</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] font-bold text-slate-700 mt-0.5">{{ $item->creditNote?->shop?->name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ optional($item->creditNote?->credit_note_date)->format('d M Y') }}</div>
                                    </td>

                                    {{-- Product --}}
                                    <td class="py-3 px-3 min-w-[160px]">
                                        <div class="font-bold text-slate-900">{{ $item->product?->name ?? $item->product_name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">SKU: {{ $item->product?->sku ?? '—' }} &bull; Unit: {{ $item->unit }}</div>
                                    </td>

                                    {{-- Original Invoice --}}
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="font-mono font-bold text-slate-800 block">{{ $item->sourceInvoice?->invoice_number ?? "INV-{$item->source_shop_invoice_id}" }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ optional($item->sourceInvoice?->business_date)->format('d M Y') }}</span>
                                    </td>

                                    {{-- Original Dispatched Grade --}}
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span class="rounded-lg bg-slate-100 border border-slate-200 px-2 py-0.5 text-xs font-mono font-bold text-slate-800">
                                            Grade {{ $item->product_grade }}
                                        </span>
                                    </td>

                                    {{-- Expected Return Qty --}}
                                    <td class="py-3 px-3 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                        {{ number_format($expectedQty, 2) }} {{ $item->unit }}
                                    </td>

                                    {{-- Received Qty --}}
                                    <td class="py-3 px-3 text-right font-mono font-bold text-indigo-700 whitespace-nowrap">
                                        {{ number_format($alreadyReceived, 2) }} {{ $item->unit }}
                                    </td>

                                    {{-- Remaining Qty --}}
                                    <td class="py-3 px-3 text-right font-mono font-black whitespace-nowrap {{ $remainingQty > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                        {{ number_format($remainingQty, 2) }} {{ $item->unit }}
                                    </td>

                                    {{-- Receipt Status --}}
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        @if ($statusValue === 'verified')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800 uppercase">
                                                <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i>
                                                <span>Verified</span>
                                            </span>
                                        @elseif ($statusValue === 'partially_received')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-[10px] font-bold text-blue-800 uppercase">
                                                <i data-lucide="clock" class="w-3 h-3 text-blue-600"></i>
                                                <span>Partial</span>
                                            </span>
                                        @elseif ($statusValue === 'disputed')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[10px] font-bold text-rose-800 uppercase">
                                                <i data-lucide="alert-triangle" class="w-3 h-3 text-rose-600"></i>
                                                <span>Disputed</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-[10px] font-bold text-amber-800 uppercase">
                                                <i data-lucide="package" class="w-3 h-3 text-amber-600"></i>
                                                <span>Pending</span>
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Action Buttons --}}
                                    <td class="py-3 px-3 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if (! $isVoided && $remainingQty > 0.0001)
                                                {{-- Receive Goods --}}
                                                <button type="button"
                                                    @click="openReceiveModal({{ $item->id }}, '{{ $item->creditNote?->credit_note_number }}', '{{ addslashes($item->creditNote?->shop?->name ?? '') }}', '{{ addslashes($item->product?->name ?? $item->product_name) }}', '{{ $item->unit }}', '{{ $item->product_grade }}', {{ $expectedQty }}, {{ $alreadyReceived }}, {{ $remainingQty }})"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition shadow-2xs"
                                                    title="Receive Goods into Warehouse">
                                                    <i data-lucide="package-plus" class="w-3.5 h-3.5 text-emerald-600"></i>
                                                    <span>Receive</span>
                                                </button>

                                                {{-- Receive Damaged --}}
                                                <button type="button"
                                                    @click="openDamageModal({{ $item->id }}, '{{ $item->creditNote?->credit_note_number }}', '{{ addslashes($item->creditNote?->shop?->name ?? '') }}', '{{ addslashes($item->product?->name ?? $item->product_name) }}', '{{ $item->unit }}', {{ $remainingQty }})"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 transition shadow-2xs"
                                                    title="Record Damaged Return into Wastage">
                                                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-600"></i>
                                                    <span>Damaged</span>
                                                </button>

                                                {{-- Dispute --}}
                                                <button type="button"
                                                    @click="openDisputeModal({{ $item->id }}, '{{ $item->creditNote?->credit_note_number }}', '{{ addslashes($item->product?->name ?? $item->product_name) }}', {{ $remainingQty }})"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition shadow-2xs"
                                                    title="Mark as Disputed / Missing">
                                                    <i data-lucide="flag" class="w-3.5 h-3.5 text-rose-600"></i>
                                                    <span>Dispute</span>
                                                </button>
                                            @endif

                                            {{-- History & Reversals --}}
                                            <button type="button"
                                                @click="openHistoryModal({{ $item->id }}, '{{ $item->creditNote?->credit_note_number }}', '{{ addslashes($item->product?->name ?? $item->product_name) }}')"
                                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-700 bg-slate-100 border border-slate-200 hover:bg-slate-200 transition shadow-2xs"
                                                title="View Receipt History & Reversals">
                                                <i data-lucide="history" class="w-3.5 h-3.5 text-slate-500"></i>
                                                <span>History ({{ $item->receipts->count() }})</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                <div class="pt-2">
                    {{ $items->links() }}
                </div>
            @endif
        </div>

        {{-- 1. RECEIVE PHYSICAL GOODS MODAL (ALPINE.JS) --}}
        <div x-show="showReceiveModal" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="closeReceiveModal()"
                class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 font-bold">
                            <i data-lucide="package-plus" class="w-4 h-4 text-emerald-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Receive Returned Goods</h3>
                            <p class="text-xs text-slate-500">Verify quantity and assess actual received grade</p>
                        </div>
                    </div>
                    <button type="button" @click="closeReceiveModal()"
                        class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
                </div>

                {{-- Item Summary --}}
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Credit Note:</span>
                        <span class="font-mono font-bold text-slate-900" x-text="activeCreditNoteNumber"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Shop:</span>
                        <span class="font-bold text-slate-800" x-text="activeShopName"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Product:</span>
                        <span class="font-bold text-slate-900" x-text="activeProductName"></span>
                    </div>
                    <div class="flex justify-between border-t border-slate-200/60 pt-2">
                        <span class="text-slate-500">Original Dispatched Grade:</span>
                        <span class="font-mono font-bold text-slate-800" x-text="'Grade ' + activeProductGrade"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Remaining Expected Return:</span>
                        <span class="font-mono font-black text-amber-700 text-sm" x-text="Number(activeRemainingQty).toFixed(2) + ' ' + activeUnit"></span>
                    </div>
                </div>

                {{-- Form Controls --}}
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Receiving Warehouse <span class="text-rose-600">*</span></label>
                        <select x-model.number="receiveWarehouseId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500">
                            <template x-for="w in warehouses" :key="w.id">
                                <option :value="w.id" x-text="w.name + ' (' + w.code + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Actual Received Qty <span class="text-rose-600">*</span></label>
                            <div class="relative">
                                <input type="number" step="0.001" min="0.001" :max="activeRemainingQty"
                                    x-model.number="receiveQuantity"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-mono font-bold text-slate-800 focus:border-emerald-500"
                                    placeholder="0.00">
                                <span class="absolute right-3 top-2 text-slate-400 font-mono text-xs" x-text="activeUnit"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Actual Assessed Grade <span class="text-rose-600">*</span></label>
                            <select x-model="receiveGrade"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                <option value="A">Grade A (Premium)</option>
                                <option value="B">Grade B (Standard)</option>
                                <option value="C">Grade C (Commercial)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Warehouse Remarks (Optional)</label>
                        <input type="text" x-model="receiveRemarks" placeholder="Condition upon receipt, vehicle/driver info, inspection notes..."
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500">
                    </div>
                </div>

                {{-- Explanatory Note --}}
                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-xs text-blue-900 flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-bold">Independent Grade Restocking</p>
                        <p class="text-[11px] text-blue-800">
                            Available inventory will increase for the selected warehouse and assessed grade (<span class="font-bold" x-text="'Grade ' + receiveGrade"></span>). Original customer credit amount remains unaffected.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" @click="closeReceiveModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" @click="executeReceive()" :disabled="!canSubmitReceive || isSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-xs disabled:opacity-50">
                        <template x-if="isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                <span>Verifying...</span>
                            </span>
                        </template>
                        <template x-if="!isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                <span>Confirm Receipt</span>
                            </span>
                        </template>
                    </button>
                </div>
            </div>
        </div>

        {{-- 2. RECEIVE DAMAGED RETURN MODAL (ALPINE.JS) --}}
        <div x-show="showDamageModal" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="closeDamageModal()"
                class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600 font-bold">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Record Damaged Return</h3>
                            <p class="text-xs text-slate-500">Log spoiled/damaged goods into WastageEntry</p>
                        </div>
                    </div>
                    <button type="button" @click="closeDamageModal()"
                        class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
                </div>

                {{-- Summary --}}
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Credit Note:</span>
                        <span class="font-mono font-bold text-slate-900" x-text="activeCreditNoteNumber"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Product:</span>
                        <span class="font-bold text-slate-900" x-text="activeProductName"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Remaining Expected Qty:</span>
                        <span class="font-mono font-black text-amber-700 text-sm" x-text="Number(activeRemainingQty).toFixed(2) + ' ' + activeUnit"></span>
                    </div>
                </div>

                {{-- Form Controls --}}
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Receiving Warehouse <span class="text-rose-600">*</span></label>
                        <select x-model.number="damageWarehouseId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-amber-500">
                            <template x-for="w in warehouses" :key="w.id">
                                <option :value="w.id" x-text="w.name + ' (' + w.code + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Damaged Quantity <span class="text-rose-600">*</span></label>
                            <div class="relative">
                                <input type="number" step="0.001" min="0.001" :max="activeRemainingQty"
                                    x-model.number="damageQuantity"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-mono font-bold text-slate-800 focus:border-amber-500"
                                    placeholder="0.00">
                                <span class="absolute right-3 top-2 text-slate-400 font-mono text-xs" x-text="activeUnit"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Damage Reason <span class="text-rose-600">*</span></label>
                            <select x-model="damageReason"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-amber-500">
                                @foreach ($wastageReasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1">Damage Details / Inspection Notes (Optional)</label>
                        <input type="text" x-model="damageRemarks" placeholder="Crushed boxes, rotten smell, temperature break during transit..."
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-amber-500">
                    </div>
                </div>

                {{-- Notice --}}
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-950 flex items-start gap-2">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-bold">Recorded as Wastage / Loss</p>
                        <p class="text-[11px] text-amber-800">
                            Goods will be written off immediately into Damage & Loss reporting. No saleable stock will be added to warehouse inventory.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" @click="closeDamageModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" @click="executeDamage()" :disabled="!canSubmitDamage || isSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-5 py-2 text-xs font-bold text-white hover:bg-amber-700 transition shadow-xs disabled:opacity-50">
                        <template x-if="isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                <span>Recording...</span>
                            </span>
                        </template>
                        <template x-if="!isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                <span>Confirm Damaged</span>
                            </span>
                        </template>
                    </button>
                </div>
            </div>
        </div>

        {{-- 3. DISPUTE RETURN MODAL (ALPINE.JS) --}}
        <div x-show="showDisputeModal" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="closeDisputeModal()"
                class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 font-bold">
                            <i data-lucide="flag" class="w-4 h-4 text-rose-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Dispute Return Goods</h3>
                            <p class="text-xs text-slate-500">Record missing or disputed goods return</p>
                        </div>
                    </div>
                    <button type="button" @click="closeDisputeModal()"
                        class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
                </div>

                {{-- Summary --}}
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Credit Note:</span>
                        <span class="font-mono font-bold text-slate-900" x-text="activeCreditNoteNumber"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Product:</span>
                        <span class="font-bold text-slate-900" x-text="activeProductName"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Expected Remaining:</span>
                        <span class="font-mono font-black text-rose-700 text-sm" x-text="Number(activeRemainingQty).toFixed(2) + ' kg'"></span>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Dispute Reason <span class="text-rose-600">*</span></label>
                    <textarea x-model="disputeReason" rows="3" placeholder="e.g. Driver arrived without return crates, shop refused hand-over, wrong product delivered..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-rose-500"></textarea>
                </div>

                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 flex items-start gap-2">
                    <i data-lucide="info" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-bold">No Inventory Movement</p>
                        <p class="text-[11px] text-rose-800">
                            Disputing flags this return line as disputed for management audit. Zero stock movements are created.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" @click="closeDisputeModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" @click="executeDispute()" :disabled="!disputeReason.trim() || isSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2 text-xs font-bold text-white hover:bg-rose-700 transition shadow-xs disabled:opacity-50">
                        <template x-if="isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                <span>Submitting...</span>
                            </span>
                        </template>
                        <template x-if="!isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="flag" class="w-3.5 h-3.5"></i>
                                <span>Submit Dispute</span>
                            </span>
                        </template>
                    </button>
                </div>
            </div>
        </div>

        {{-- 4. RECEIPT HISTORY & REVERSAL MODAL (ALPINE.JS) --}}
        <div x-show="showHistoryModal" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="closeHistoryModal()"
                class="relative w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700 font-bold">
                            <i data-lucide="history" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Receipt History & Reversals</h3>
                            <p class="text-xs text-slate-500">Audit trail of all physical receipt events for this credit note item</p>
                        </div>
                    </div>
                    <button type="button" @click="closeHistoryModal()"
                        class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
                </div>

                {{-- Header info --}}
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-mono font-bold text-slate-900" x-text="activeCreditNoteNumber"></span>
                        <span class="text-slate-400 font-semibold mx-1.5">&bull;</span>
                        <span class="font-bold text-slate-800" x-text="activeProductName"></span>
                    </div>
                    <div>
                        <span class="text-slate-400">Total Expected:</span>
                        <span class="font-mono font-bold text-slate-900 ml-1" x-text="Number(activeExpectedQty).toFixed(2) + ' kg'"></span>
                    </div>
                </div>

                {{-- Loading state --}}
                <template x-if="isLoadingHistory">
                    <div class="py-10 text-center text-xs text-slate-400 space-y-2">
                        <i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto text-indigo-600"></i>
                        <p>Loading receipt events...</p>
                    </div>
                </template>

                {{-- Receipts List --}}
                <template x-if="!isLoadingHistory && historyReceipts.length === 0">
                    <div class="py-8 text-center text-xs text-slate-400 italic">
                        No physical receipts or disputes recorded yet for this item.
                    </div>
                </template>

                <template x-if="!isLoadingHistory && historyReceipts.length > 0">
                    <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-80 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500 sticky top-0">
                                <tr>
                                    <th class="py-2 px-3">Date / Time</th>
                                    <th class="py-2 px-3">Type</th>
                                    <th class="py-2 px-3 text-right">Received Qty</th>
                                    <th class="py-2 px-3 text-center">Grade</th>
                                    <th class="py-2 px-3">Warehouse</th>
                                    <th class="py-2 px-3">Receiver</th>
                                    <th class="py-2 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="r in historyReceipts" :key="r.id">
                                    <tr :class="r.is_reversed ? 'bg-slate-50/60 opacity-60' : ''">
                                        <td class="py-2 px-3 font-mono text-[11px] text-slate-600 whitespace-nowrap" x-text="r.received_at"></td>
                                        <td class="py-2 px-3 whitespace-nowrap">
                                            <template x-if="r.status === 'verified'">
                                                <span class="rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800 uppercase">Saleable</span>
                                            </template>
                                            <template x-if="r.status === 'damaged'">
                                                <span class="rounded bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 uppercase">Damaged</span>
                                            </template>
                                            <template x-if="r.status === 'disputed'">
                                                <span class="rounded bg-rose-50 border border-rose-200 px-1.5 py-0.5 text-[9px] font-bold text-rose-800 uppercase">Disputed</span>
                                            </template>
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono font-bold text-slate-900" x-text="Number(r.received_quantity).toFixed(2) + ' kg'"></td>
                                        <td class="py-2 px-3 text-center">
                                            <span class="font-mono font-bold text-slate-700" x-text="r.received_grade ? 'Grade ' + r.received_grade : '—'"></span>
                                        </td>
                                        <td class="py-2 px-3 text-slate-700" x-text="r.warehouse_name"></td>
                                        <td class="py-2 px-3 text-slate-600" x-text="r.receiver_name"></td>
                                        <td class="py-2 px-3 text-right whitespace-nowrap">
                                            <template x-if="r.is_reversed">
                                                <div class="text-[10px] text-rose-700 italic">
                                                    <span>Reversed</span>
                                                    <span x-text="'(' + r.reversed_at + ')'"></span>
                                                </div>
                                            </template>
                                            <template x-if="!r.is_reversed && r.can_reverse">
                                                <button type="button" @click="openReverseReceiptModal(r)"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition shadow-2xs">
                                                    <i data-lucide="rotate-ccw" class="w-3 h-3 text-rose-600"></i>
                                                    <span>Reverse</span>
                                                </button>
                                            </template>
                                            <template x-if="!r.is_reversed && !r.can_reverse">
                                                <span class="text-slate-400 text-[10px]">—</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div class="flex justify-end pt-2 border-t border-slate-100">
                    <button type="button" @click="closeHistoryModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Close
                    </button>
                </div>
            </div>
        </div>

        {{-- 5. REVERSE PHYSICAL RECEIPT CONFIRMATION MODAL (ALPINE.JS) --}}
        <div x-show="showReverseReceiptModal" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="closeReverseReceiptModal()"
                class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 font-bold">
                            <i data-lucide="rotate-ccw" class="w-4 h-4 text-rose-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Reverse Physical Return Receipt</h3>
                            <p class="text-xs text-slate-500">Safely deduce inventory and restore pending return balance</p>
                        </div>
                    </div>
                    <button type="button" @click="closeReverseReceiptModal()"
                        class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
                </div>

                {{-- Summary --}}
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Receipt ID:</span>
                        <span class="font-mono font-bold text-slate-900" x-text="'#' + targetReceiptId"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Quantity to Deduce:</span>
                        <span class="font-mono font-black text-rose-700 text-sm" x-text="targetReceiptQty + ' kg (Grade ' + targetReceiptGrade + ')'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Warehouse:</span>
                        <span class="font-bold text-slate-800" x-text="targetReceiptWarehouseName"></span>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-950 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600 shrink-0"></i>
                        <span>Stock Reduction Warning</span>
                    </div>
                    <p>Reversing will deduce <span class="font-bold" x-text="targetReceiptQty + ' kg of Grade ' + targetReceiptGrade"></span> from <span class="font-bold" x-text="targetReceiptWarehouseName"></span>. If current warehouse inventory has already been dispatched and is insufficient, the system will prevent negative stock.</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Mandatory Reversal Reason <span class="text-rose-600">*</span></label>
                    <textarea x-model="reverseReceiptReason" rows="2" placeholder="e.g. Incorrect quantity confirmed by receiver, entered wrong grade, duplicate entry..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-rose-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" @click="closeReverseReceiptModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" @click="executeReverseReceipt()" :disabled="!reverseReceiptReason.trim() || isSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2 text-xs font-bold text-white hover:bg-rose-700 transition shadow-xs disabled:opacity-50">
                        <template x-if="isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                <span>Reversing...</span>
                            </span>
                        </template>
                        <template x-if="!isSubmitting">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                <span>Confirm Reversal</span>
                            </span>
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Alpine.js Manager Script --}}
    <script>
        function warehouseCreditNoteManager(config) {
            return {
                warehouses: config.warehouses || [],
                defaultWarehouseId: config.defaultWarehouseId,

                // Active item context for modals
                activeItemId: null,
                activeCreditNoteNumber: '',
                activeShopName: '',
                activeProductName: '',
                activeUnit: 'kg',
                activeProductGrade: 'A',
                activeExpectedQty: 0.0,
                activeAlreadyReceivedQty: 0.0,
                activeRemainingQty: 0.0,

                // Modal states
                showReceiveModal: false,
                showDamageModal: false,
                showDisputeModal: false,
                showHistoryModal: false,
                showReverseReceiptModal: false,

                isSubmitting: false,
                isLoadingHistory: false,

                // Receive form state
                receiveWarehouseId: config.defaultWarehouseId,
                receiveQuantity: 0.0,
                receiveGrade: 'A',
                receiveRemarks: '',

                // Damage form state
                damageWarehouseId: config.defaultWarehouseId,
                damageQuantity: 0.0,
                damageReason: 'transit_damage',
                damageRemarks: '',

                // Dispute form state
                disputeReason: '',

                // History state
                historyReceipts: [],

                // Reversal form state
                targetReceiptId: null,
                targetReceiptQty: 0.0,
                targetReceiptGrade: 'A',
                targetReceiptWarehouseName: '',
                reverseReceiptReason: '',

                generateIdempotencyKey(action, itemId) {
                    return 'wh-' + action + '-' + itemId + '-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7);
                },

                // 1. Receive Goods
                openReceiveModal(id, cnNum, shop, prod, unit, grade, exp, rec, rem) {
                    this.activeItemId = id;
                    this.activeCreditNoteNumber = cnNum;
                    this.activeShopName = shop;
                    this.activeProductName = prod;
                    this.activeUnit = unit;
                    this.activeProductGrade = grade;
                    this.activeExpectedQty = exp;
                    this.activeAlreadyReceivedQty = rec;
                    this.activeRemainingQty = rem;

                    this.receiveQuantity = rem;
                    this.receiveGrade = grade;
                    this.receiveWarehouseId = this.defaultWarehouseId;
                    this.receiveRemarks = '';
                    this.showReceiveModal = true;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                },

                closeReceiveModal() {
                    this.showReceiveModal = false;
                },

                get canSubmitReceive() {
                    return this.receiveQuantity > 0.0001 && this.receiveQuantity <= (this.activeRemainingQty + 0.0001) && !this.isSubmitting;
                },

                executeReceive() {
                    if (!this.canSubmitReceive) return;
                    this.isSubmitting = true;

                    const url = `/warehouse-receiver/credit-notes/items/${this.activeItemId}/receive`;
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            quantity: this.receiveQuantity,
                            received_grade: this.receiveGrade,
                            warehouse_id: this.receiveWarehouseId,
                            remarks: this.receiveRemarks,
                            idempotency_key: this.generateIdempotencyKey('rcv', this.activeItemId)
                        })
                    })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            alert(data.message || 'Validation error while receiving return goods.');
                            this.isSubmitting = false;
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error('Receive error:', err);
                        alert('An unexpected network error occurred.');
                        this.isSubmitting = false;
                    });
                },

                // 2. Receive Damaged
                openDamageModal(id, cnNum, shop, prod, unit, rem) {
                    this.activeItemId = id;
                    this.activeCreditNoteNumber = cnNum;
                    this.activeShopName = shop;
                    this.activeProductName = prod;
                    this.activeUnit = unit;
                    this.activeRemainingQty = rem;

                    this.damageQuantity = rem;
                    this.damageWarehouseId = this.defaultWarehouseId;
                    this.damageReason = 'transit_damage';
                    this.damageRemarks = '';
                    this.showDamageModal = true;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                },

                closeDamageModal() {
                    this.showDamageModal = false;
                },

                get canSubmitDamage() {
                    return this.damageQuantity > 0.0001 && this.damageQuantity <= (this.activeRemainingQty + 0.0001) && !this.isSubmitting;
                },

                executeDamage() {
                    if (!this.canSubmitDamage) return;
                    this.isSubmitting = true;

                    const url = `/warehouse-receiver/credit-notes/items/${this.activeItemId}/damage`;
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            quantity: this.damageQuantity,
                            damage_reason: this.damageReason,
                            warehouse_id: this.damageWarehouseId,
                            remarks: this.damageRemarks,
                            idempotency_key: this.generateIdempotencyKey('dmg', this.activeItemId)
                        })
                    })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            alert(data.message || 'Validation error while recording damaged return.');
                            this.isSubmitting = false;
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error('Damage error:', err);
                        alert('An unexpected network error occurred.');
                        this.isSubmitting = false;
                    });
                },

                // 3. Dispute Return
                openDisputeModal(id, cnNum, prod, rem) {
                    this.activeItemId = id;
                    this.activeCreditNoteNumber = cnNum;
                    this.activeProductName = prod;
                    this.activeRemainingQty = rem;
                    this.disputeReason = '';
                    this.showDisputeModal = true;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                },

                closeDisputeModal() {
                    this.showDisputeModal = false;
                },

                executeDispute() {
                    if (!this.disputeReason.trim() || this.isSubmitting) return;
                    this.isSubmitting = true;

                    const url = `/warehouse-receiver/credit-notes/items/${this.activeItemId}/dispute`;
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            reason: this.disputeReason.trim()
                        })
                    })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            alert(data.message || 'Validation error while submitting dispute.');
                            this.isSubmitting = false;
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error('Dispute error:', err);
                        alert('An unexpected network error occurred.');
                        this.isSubmitting = false;
                    });
                },

                // 4. History Modal
                openHistoryModal(id, cnNum, prod) {
                    this.activeItemId = id;
                    this.activeCreditNoteNumber = cnNum;
                    this.activeProductName = prod;
                    this.historyReceipts = [];
                    this.isLoadingHistory = true;
                    this.showHistoryModal = true;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });

                    fetch(`/warehouse-receiver/credit-notes/items/${id}/history`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.historyReceipts = data.receipts || [];
                        this.activeExpectedQty = data.expected_quantity;
                        this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                    })
                    .catch(err => {
                        console.error('History error:', err);
                    })
                    .finally(() => {
                        this.isLoadingHistory = false;
                    });
                },

                closeHistoryModal() {
                    this.showHistoryModal = false;
                },

                // 5. Reverse Receipt Modal
                openReverseReceiptModal(receipt) {
                    this.targetReceiptId = receipt.id;
                    this.targetReceiptQty = receipt.received_quantity;
                    this.targetReceiptGrade = receipt.received_grade;
                    this.targetReceiptWarehouseName = receipt.warehouse_name;
                    this.reverseReceiptReason = '';
                    this.showReverseReceiptModal = true;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                },

                closeReverseReceiptModal() {
                    this.showReverseReceiptModal = false;
                },

                executeReverseReceipt() {
                    if (!this.reverseReceiptReason.trim() || !this.targetReceiptId || this.isSubmitting) return;
                    this.isSubmitting = true;

                    const url = `/warehouse-receiver/credit-notes/receipts/${this.targetReceiptId}/reverse`;
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            reason: this.reverseReceiptReason.trim()
                        })
                    })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            alert(data.message || 'Validation error while reversing warehouse receipt.');
                            this.isSubmitting = false;
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error('Reversal error:', err);
                        alert('An unexpected network error occurred.');
                        this.isSubmitting = false;
                    });
                }
            };
        }

        if (window.Alpine) {
            window.Alpine.data('warehouseCreditNoteManager', warehouseCreditNoteManager);
        } else {
            document.addEventListener('alpine:init', function() {
                window.Alpine.data('warehouseCreditNoteManager', warehouseCreditNoteManager);
            });
        }
    </script>
</x-layouts.app>

