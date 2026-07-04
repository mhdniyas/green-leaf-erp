<x-layouts.inventory title="Shop Dispatch Cards">
    @php
        $existingWarehouseTags = $orders
            ->map(fn ($order) => $order->shop?->warehouse_tag)
            ->filter()
            ->unique()
            ->values();
    @endphp

    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.sorting.shop-orders', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Previous Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </a>
            <form id="date-form" method="GET" action="{{ route('inventory.sorting.shop-orders') }}" class="flex items-center gap-2">
                <input id="date-select" type="date" name="date" value="{{ $date }}" onchange="document.getElementById('date-form').submit();"
                       class="border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white shadow-xs">
            </form>
            <a href="{{ route('inventory.sorting.shop-orders', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Next Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
            <a href="{{ route('inventory.sorting.shop-orders', ['date' => \Carbon\Carbon::today()->format('Y-m-d')]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 border border-brand-200 rounded-xl text-xs font-bold hover:bg-brand-100 transition-colors shadow-xs">
                Today
            </a>
        </div>
    </x-slot:actions>

    {{-- Main Container --}}
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-black text-slate-800 tracking-tight">Shop Dispatch Cards</h2>
                <p class="text-xs text-slate-400 mt-1">Open one card per shop, pack approved products, move items to transit, and finalize each loading sheet.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 border border-slate-200">
                    Target Date: {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @if($orders->isEmpty())
            <div class="col-span-full bg-white rounded-3xl border border-gray-200 shadow-sm p-16 text-center">
                <div class="w-16 h-16 rounded-3xl bg-slate-50 flex items-center justify-center mx-auto mb-4 text-slate-400">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 0A48.536 48.536 0 0112 3.75c0 .08-.004.16-.01.238m-2.886 0c.385.023.77.05 1.154.08m-3.456 0A48.108 48.108 0 002.25 6.11v10.39a2.25 2.25 0 002.25 2.25h3" /></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">No approved orders found</h3>
                <p class="text-sm text-slate-500 mt-1">There are no approved shop orders to process for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</p>
            </div>
        @else
            @foreach($orders as $order)
                @php
                    $orderTotal = $order->items->count();
                    $orderSorted = $order->items->where('is_sorted', true)->count();
                    $orderPercentage = $orderTotal > 0 ? (int) round(($orderSorted / $orderTotal) * 100) : 0;
                @endphp
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden flex flex-col justify-between transition-all duration-200 hover:shadow-md {{ $order->is_allocation_completed ? 'ring-2 ring-emerald-500/20' : '' }}" id="shop-card-{{ $order->id }}">
                    
                    {{-- Card Header --}}
                    <div class="p-5 border-b border-gray-100 bg-slate-50/50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-black text-slate-800 tracking-tight">{{ $order->shop ? $order->shop->name : 'N/A' }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <p class="text-[10px] font-mono font-bold text-slate-400">{{ $order->order_number }}</p>
                                    <button type="button"
                                        onclick="openTagModal('{{ $order->shop?->code }}', '{{ $order->shop?->name }}', '{{ $order->shop?->warehouse_tag }}')"
                                        class="inline-flex items-center gap-1 rounded-full border border-cyan-100 bg-cyan-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700 transition hover:border-cyan-200 hover:bg-cyan-100">
                                        <span>Tag {{ $order->shop?->warehouse_tag ?: '--' }}</span>
                                        <span class="text-[9px] normal-case tracking-normal text-cyan-600">change</span>
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <span id="shop-badge-{{ $order->id }}" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $order->is_allocation_completed ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-brand-50 text-brand-700 border border-brand-200' }}">
                                    <span id="shop-percentage-text-{{ $order->id }}">{{ $order->is_allocation_completed ? 'Finalized' : $orderPercentage . '%' }}</span>
                                </span>
                            </div>
                        </div>

                        {{-- Shop Local Progress Bar --}}
                        <div class="w-full bg-slate-200 h-1.5 rounded-full mt-4 overflow-hidden">
                            <div id="shop-progress-bar-{{ $order->id }}" class="h-full bg-brand-500 transition-all duration-300 rounded-full" style="width: {{ $orderPercentage }}%;"></div>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <p id="shop-ratio-text-{{ $order->id }}" class="text-[10px] font-bold text-slate-400">
                                {{ $orderSorted }} of {{ $orderTotal }} products packed / in transit
                            </p>
                            @if($order->is_allocation_completed)
                                <span class="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">Completed</span>
                            @else
                                <span class="text-[9px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">In Progress</span>
                            @endif
                        </div>
                    </div>

                    {{-- Card Body --}}
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                <span>Requested Date:</span>
                                <span>{{ $order->business_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                                <span>Total Products:</span>
                                <span class="font-bold text-slate-700">{{ $orderTotal }}</span>
                            </div>
                            @if($order->sorting_notes)
                                <div class="bg-slate-50 border border-slate-100 rounded-xl p-2 text-[10px] text-slate-500 mt-2">
                                    <span class="font-extrabold text-slate-700 block mb-0.5">Notes:</span>
                                    {{ $order->sorting_notes }}
                                </div>
                            @endif

                        </div>

                        <button onclick="openAllocationModal(this)"
                                data-id="{{ $order->id }}"
                                data-order-number="{{ $order->order_number }}"
                                data-shop-name="{{ $order->shop ? $order->shop->name : 'N/A' }}"
                                data-is-completed="{{ $order->is_allocation_completed ? 'true' : 'false' }}"
                                data-notes="{{ $order->sorting_notes ?? '' }}"
                                data-items="{{ $order->items->map(function($item) {
                                    return [
                                        'id' => $item->id,
                                        'product_name' => $item->product->name,
                                        'product_sku' => $item->product->sku,
                                        'requested_qty' => (float) $item->requested_qty,
                                        'approved_qty' => (float) $item->approved_qty,
                                        'unit' => $item->unit,
                                        'fulfillment_type' => $item->fulfillment_type,
                                        'is_sorted' => $item->is_sorted,
                                        'sorting_status' => $item->sorting_status,
                                        'sorted_by_name' => $item->is_sorted && $item->sortedBy ? $item->sortedBy->name : null,
                                        'sorted_at_formatted' => $item->sorted_at ? $item->sorted_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
                                    ];
                                })->toJson() }}"
                                class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-black shadow-xs hover:shadow-md transition-all cursor-pointer text-center flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75c.621 0 1.125.504 1.125 1.125v1.875c0 .621-.504 1.125-1.125 1.125H5.625a1.125 1.125 0 01-1.125-1.125V5.625c0-.621.504-1.125 1.125-1.125z" /></svg>
                            {{ $order->is_allocation_completed ? 'View Loading Details' : 'Pack & Load Products' }}
                        </button>
                        <a href="{{ route('inventory.sorting.shop-sorting.show', ['order' => $order->order_number, 'date' => $date]) }}"
                           class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 py-2.5 text-center text-xs font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" /></svg>
                            Open Worker Sorting Page
                        </a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- ── Shop Dispatch Card Modal ── --}}
    <div id="allocation-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 transition-opacity" aria-hidden="true" onclick="closeAllocationModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="relative z-10 inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full border border-slate-100">
                <form id="allocation-form" onsubmit="submitAllocationFinalization(event)">
                    @csrf
                    <input type="hidden" name="shop_order_id" id="modal-order-id">
                    
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-base font-black text-slate-800" id="modal-title-text">Shop Dispatch Card</h3>
                                <p class="text-xs text-slate-400 mt-1" id="modal-subtitle-text"></p>
                            </div>
                            <button type="button" onclick="closeAllocationModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        {{-- Modal Progress tracker --}}
                        <div class="mt-4 flex items-center justify-between gap-4 bg-white border border-slate-100 rounded-2xl p-3 shadow-xs">
                            <div class="flex-1">
                                <div class="flex justify-between text-[10px] font-extrabold uppercase text-slate-500 mb-1">
                                    <span>Packing Progress</span>
                                    <span id="modal-percentage-label">0%</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div id="modal-progress-bar" class="h-full bg-brand-500 rounded-full transition-all duration-300" style="width: 0%;"></div>
                                </div>
                            </div>
                            <span id="modal-ratio-label" class="text-xs font-mono font-black text-slate-700 bg-slate-100 border border-slate-200/60 rounded-xl px-3 py-1.5">
                                0 / 0
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4 max-h-[50vh] overflow-y-auto" id="modal-items-container">
                        <!-- Loaded dynamically via javascript -->
                    </div>
                    
                    <div class="p-6 border-t border-slate-100 bg-slate-50/50 space-y-4" id="modal-finalize-section">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="modal-success-100" checked onchange="toggleModalNotesField()"
                                   class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/20 cursor-pointer">
                            <label for="modal-success-100" class="text-[11px] font-bold text-slate-700 cursor-pointer select-none">
                                Order packed exactly as approved (no changes)
                            </label>
                        </div>

                        <div id="modal-notes-container" class="hidden transition-all duration-300">
                            <label for="modal-sorting-notes" class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Specify Changes / Discrepancy Notes</label>
                            <textarea id="modal-sorting-notes" rows="2" placeholder="Detail any discrepancy or why the order wasn't fulfilled 100%..."
                                      class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white"></textarea>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" onclick="closeAllocationModal()" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition-colors cursor-pointer">Cancel</button>
                            <button type="submit" id="btn-modal-finalize" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors cursor-pointer">Complete Loading Sheet</button>
                        </div>
                    </div>

                    <div class="p-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3 hidden" id="modal-view-only-section">
                        <div class="w-full bg-emerald-50 border border-emerald-100 rounded-2xl p-4 text-emerald-800 flex flex-col gap-2">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="text-xs font-black">Loading Sheet Finalized</span>
                            </div>
                            <div id="modal-notes-display" class="text-[10px] text-emerald-600 bg-white/60 p-2 rounded-xl font-semibold hidden"></div>
                            <button type="button" onclick="closeAllocationModal()" class="self-end px-3 py-1.5 bg-emerald-600 text-white hover:bg-emerald-700 text-[10px] font-bold rounded-lg transition-colors cursor-pointer">Close</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Dynamic Toast Notification Container --}}
    <div id="toast-container" class="fixed bottom-5 right-5 z-55 flex flex-col gap-2"></div>
    <div id="tag-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/60" onclick="closeTagModal()"></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-sm rounded-3xl border border-slate-200 bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Update Shop Tag</h3>
                        <p id="tag-modal-shop-name" class="mt-1 text-xs font-semibold text-slate-500"></p>
                    </div>
                    <button type="button" onclick="closeTagModal()" class="text-slate-400 transition hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form id="tag-modal-form" method="POST" class="mt-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="date" value="{{ $date }}">
                    <label for="tag-modal-input" class="block text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Tag</label>
                    <input id="tag-modal-input" type="text" name="warehouse_tag" maxlength="12" placeholder="A"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-black uppercase tracking-[0.18em] text-slate-700 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-200">
                    <p id="tag-modal-error" class="mt-2 hidden text-xs font-bold text-red-600"></p>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="closeTagModal()" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 transition hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-black text-white transition hover:bg-slate-800">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            let currentOrderItems = [];
            let currentOrderId = null;
            const warehouseTags = @json($existingWarehouseTags);

            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `flex items-center gap-3 rounded-2xl border px-4 py-3 shadow-lg transform transition-all duration-300 translate-y-2 opacity-0 bg-white ${
                    type === 'success' ? 'border-green-200 text-green-800' : 'border-red-200 text-red-800'
                }`;
                
                const icon = type === 'success' 
                    ? `<svg class="w-5 h-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
                    : `<svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`;
                
                toast.innerHTML = `
                    ${icon}
                    <p class="text-xs font-bold leading-none">${message}</p>
                `;
                
                const container = document.getElementById('toast-container');
                if (container) {
                    container.appendChild(toast);
                    setTimeout(() => {
                        toast.classList.remove('translate-y-2', 'opacity-0');
                    }, 10);
                    setTimeout(() => {
                        toast.classList.add('translate-y-2', 'opacity-0');
                        setTimeout(() => toast.remove(), 300);
                    }, 4000);
                }
            }

            function openTagModal(shopCode, shopName, currentTag) {
                const modal = document.getElementById('tag-modal');
                const form = document.getElementById('tag-modal-form');
                const input = document.getElementById('tag-modal-input');
                const title = document.getElementById('tag-modal-shop-name');
                const error = document.getElementById('tag-modal-error');

                form.action = `/inventory/sorting-checklist/shops/${shopCode}/tag`;
                form.dataset.currentTag = (currentTag || '').toUpperCase();
                input.value = currentTag || '';
                title.textContent = shopName;
                error.classList.add('hidden');
                error.textContent = '';
                modal.classList.remove('hidden');
                setTimeout(() => input.focus(), 10);
            }

            function closeTagModal() {
                document.getElementById('tag-modal').classList.add('hidden');
            }

            document.getElementById('tag-modal-form')?.addEventListener('submit', function (event) {
                const input = document.getElementById('tag-modal-input');
                const error = document.getElementById('tag-modal-error');
                const nextTag = input.value.trim().toUpperCase();
                const currentTag = (event.currentTarget.dataset.currentTag || '').toUpperCase();

                input.value = nextTag;
                error.classList.add('hidden');
                error.textContent = '';

                if (nextTag && nextTag !== currentTag && warehouseTags.includes(nextTag)) {
                    event.preventDefault();
                    error.textContent = `Tag ${nextTag} is already used by another shop.`;
                    error.classList.remove('hidden');
                }
            });

            function openAllocationModal(btn) {
                const orderId = btn.getAttribute('data-id');
                const orderNumber = btn.getAttribute('data-order-number');
                const shopName = btn.getAttribute('data-shop-name');
                const isCompleted = btn.getAttribute('data-is-completed') === 'true';
                const notes = btn.getAttribute('data-notes');
                
                currentOrderId = orderId;
                currentOrderItems = JSON.parse(btn.getAttribute('data-items'));

                document.getElementById('modal-order-id').value = orderId;
                document.getElementById('modal-title-text').textContent = shopName;
                document.getElementById('modal-subtitle-text').textContent = `Order: ${orderNumber}`;

                // Setup the finalization forms
                if (isCompleted) {
                    document.getElementById('modal-finalize-section').classList.add('hidden');
                    const notesDisplay = document.getElementById('modal-notes-display');
                    if (notes && notes.trim() !== '') {
                        notesDisplay.innerHTML = `<span class="font-extrabold text-emerald-800 block mb-0.5">Discrepancy Notes:</span> ${notes}`;
                        notesDisplay.classList.remove('hidden');
                    } else {
                        notesDisplay.innerHTML = `Order packed exactly as approved.`;
                        notesDisplay.classList.remove('hidden');
                    }
                    document.getElementById('modal-view-only-section').classList.remove('hidden');
                } else {
                    document.getElementById('modal-finalize-section').classList.remove('hidden');
                    document.getElementById('modal-view-only-section').classList.add('hidden');
                    document.getElementById('modal-success-100').checked = true;
                    document.getElementById('modal-sorting-notes').value = '';
                    document.getElementById('modal-notes-container').classList.add('hidden');
                }

                renderModalItems(isCompleted);
                updateModalProgress();

                // Open Modal
                document.getElementById('allocation-modal').classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeAllocationModal() {
                document.getElementById('allocation-modal').classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            function toggleModalNotesField() {
                const checked = document.getElementById('modal-success-100').checked;
                const container = document.getElementById('modal-notes-container');
                if (checked) {
                    container.classList.add('hidden');
                } else {
                    container.classList.remove('hidden');
                }
            }

            function renderModalItems(isCompleted = false) {
                const container = document.getElementById('modal-items-container');
                container.innerHTML = '';

                currentOrderItems.forEach(item => {
                    const row = document.createElement('div');
                    row.className = `p-4 border border-slate-100 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all duration-300 ${
                        item.is_sorted ? 'bg-slate-50/80 opacity-90' : 'bg-white'
                    }`;
                    row.id = `modal-item-row-${item.id}`;

                    const textClass = item.sorting_status === 'loaded' ? 'text-indigo-900' : (item.is_sorted ? 'text-emerald-900' : 'text-slate-800');

                    // Left Side: Product details
                    const leftCol = document.createElement('div');
                    leftCol.className = "flex-1 min-w-0";
                    leftCol.innerHTML = `
                        <h4 class="text-xs font-black truncate ${textClass}" id="modal-label-${item.id}">${item.product_name}</h4>
                        <div class="flex items-center gap-1.5 mt-1 text-[9px] text-slate-400 font-bold">
                            <span class="font-mono text-slate-500">${item.product_sku}</span>
                            <span>·</span>
                            <span class="bg-slate-100 px-1 py-0.5 rounded uppercase">${item.fulfillment_type || 'warehouse'}</span>
                            <span>·</span>
                            <span>Ordered: ${item.requested_qty.toFixed(2)} ${item.unit}</span>
                        </div>
                    `;

                    // Middle: Quantities (Approved by Purchase Manager is critical)
                    const middleCol = document.createElement('div');
                    middleCol.className = "text-left md:text-right shrink-0 min-w-[120px]";
                    middleCol.innerHTML = `
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Approved Qty</p>
                        <p class="text-xs font-black text-slate-800">${item.approved_qty.toFixed(2)} <span class="text-[9px] font-bold text-slate-500">${item.unit}</span></p>
                    `;

                    // Right Side: Action pill buttons (3-State Status Selector)
                    const rightCol = document.createElement('div');
                    rightCol.className = "flex flex-col items-start md:items-end gap-2 shrink-0";
                    
                    const btnGroup = document.createElement('div');
                    btnGroup.className = "inline-flex rounded-xl p-0.5 bg-slate-100 border border-slate-200 select-none";
                    
                    const btnPending = document.createElement('button');
                    btnPending.type = "button";
                    btnPending.id = `modal-btn-pending-${item.id}`;
                    btnPending.disabled = isCompleted;
                    btnPending.className = `px-2.5 py-1 rounded-lg text-[9px] font-black transition-all ${!isCompleted ? 'cursor-pointer' : ''} ${
                        item.sorting_status === 'pending' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-400 hover:text-slate-700'
                    }`;
                    btnPending.textContent = "Ready";
                    btnPending.onclick = () => updateModalItemStatus(item.id, 'pending');

                    const btnAllocated = document.createElement('button');
                    btnAllocated.type = "button";
                    btnAllocated.id = `modal-btn-allocated-${item.id}`;
                    btnAllocated.disabled = isCompleted;
                    btnAllocated.className = `px-2.5 py-1 rounded-lg text-[9px] font-black transition-all ${!isCompleted ? 'cursor-pointer' : ''} ${
                        item.sorting_status === 'allocated' ? 'bg-emerald-500 text-white shadow-xs' : 'text-slate-400 hover:text-slate-700'
                    }`;
                    btnAllocated.textContent = "Packing";
                    btnAllocated.onclick = () => updateModalItemStatus(item.id, 'allocated');

                    const btnLoaded = document.createElement('button');
                    btnLoaded.type = "button";
                    btnLoaded.id = `modal-btn-loaded-${item.id}`;
                    btnLoaded.disabled = isCompleted;
                    btnLoaded.className = `px-2.5 py-1 rounded-lg text-[9px] font-black transition-all ${!isCompleted ? 'cursor-pointer' : ''} ${
                        item.sorting_status === 'loaded' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-700'
                    }`;
                    btnLoaded.textContent = "In Transit";
                    btnLoaded.onclick = () => updateModalItemStatus(item.id, 'loaded');

                    btnGroup.appendChild(btnPending);
                    btnGroup.appendChild(btnAllocated);
                    btnGroup.appendChild(btnLoaded);

                    const metaDisplay = document.createElement('div');
                    metaDisplay.id = `modal-meta-container-${item.id}`;
                    metaDisplay.className = item.is_sorted ? "" : "hidden";
                    
                    let prefixIcon = item.sorting_status === 'loaded' ? '🚚' : '📦';
                    metaDisplay.innerHTML = `
                        <span class="text-[8px] font-bold text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-lg inline-flex items-center gap-1">
                            ${prefixIcon} ${item.is_sorted && item.sorted_by_name ? item.sorted_by_name : ''} ${item.sorted_at_formatted ? 'at ' + item.sorted_at_formatted : ''}
                        </span>
                    `;

                    rightCol.appendChild(btnGroup);
                    rightCol.appendChild(metaDisplay);

                    row.appendChild(leftCol);
                    row.appendChild(middleCol);
                    row.appendChild(rightCol);

                    container.appendChild(row);
                });
            }

            function updateModalProgress() {
                const total = currentOrderItems.length;
                const sorted = currentOrderItems.filter(i => i.is_sorted).length;
                const percentage = total > 0 ? Math.round((sorted / total) * 100) : 0;

                document.getElementById('modal-percentage-label').textContent = `${percentage}%`;
                document.getElementById('modal-progress-bar').style.width = `${percentage}%`;
                document.getElementById('modal-ratio-label').textContent = `${sorted} / ${total}`;
            }

            function updateModalItemStatus(itemId, status) {
                // Instantly update UI for snappy feedback
                const btnPending = document.getElementById(`modal-btn-pending-${itemId}`);
                const btnAllocated = document.getElementById(`modal-btn-allocated-${itemId}`);
                const btnLoaded = document.getElementById(`modal-btn-loaded-${itemId}`);
                const label = document.getElementById(`modal-label-${itemId}`);
                const row = document.getElementById(`modal-item-row-${itemId}`);
                const meta = document.getElementById(`modal-meta-container-${itemId}`);

                fetch(`/inventory/sorting-checklist/toggle/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: status })
                })
                .then(response => {
                    if (!response.ok) throw new Error('Unauthorized or DB error.');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Update our local state array
                        const index = currentOrderItems.findIndex(i => i.id === itemId);
                        if (index !== -1) {
                            currentOrderItems[index].is_sorted = data.item.is_sorted;
                            currentOrderItems[index].sorting_status = data.item.sorting_status;
                            currentOrderItems[index].sorted_by_name = data.item.sorted_by_name;
                            currentOrderItems[index].sorted_at_formatted = data.item.sorted_at_formatted;
                        }

                        // Style active buttons
                        btnPending.className = "px-2.5 py-1 rounded-lg text-[9px] font-black transition-all cursor-pointer text-slate-400 hover:text-slate-700";
                        btnAllocated.className = "px-2.5 py-1 rounded-lg text-[9px] font-black transition-all cursor-pointer text-slate-400 hover:text-slate-700";
                        btnLoaded.className = "px-2.5 py-1 rounded-lg text-[9px] font-black transition-all cursor-pointer text-slate-400 hover:text-slate-700";

                        if (data.item.sorting_status === 'pending') {
                            btnPending.className = "px-2.5 py-1 rounded-lg text-[9px] font-black transition-all cursor-pointer bg-white text-slate-800 shadow-xs";
                            label.className = "text-xs font-black truncate text-slate-800";
                            row.className = "p-4 border border-slate-100 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all duration-300 bg-white";
                            meta.classList.add('hidden');
                        } else if (data.item.sorting_status === 'allocated') {
                            btnAllocated.className = "px-2.5 py-1 rounded-lg text-[9px] font-black transition-all cursor-pointer bg-emerald-500 text-white shadow-xs";
                            label.className = "text-xs font-black truncate text-emerald-900";
                            row.className = "p-4 border border-slate-100 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all duration-300 bg-slate-50/80 opacity-90";
                            meta.classList.remove('hidden');
                            meta.querySelector('span').innerHTML = `📦 ${data.item.sorted_by_name} at ${data.item.sorted_at_formatted}`;
                        } else if (data.item.sorting_status === 'loaded') {
                            btnLoaded.className = "px-2.5 py-1 rounded-lg text-[9px] font-black transition-all cursor-pointer bg-indigo-600 text-white shadow-xs";
                            label.className = "text-xs font-black truncate text-indigo-900";
                            row.className = "p-4 border border-slate-100 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all duration-300 bg-slate-50/80 opacity-90";
                            meta.classList.remove('hidden');
                            meta.querySelector('span').innerHTML = `🚚 ${data.item.sorted_by_name} at ${data.item.sorted_at_formatted}`;
                        }

                        // Update modal stats
                        updateModalProgress();

                        // Update parent card in the background dynamically!
                        const orderId = currentOrderId;
                        const percentage = data.shop_progress.percentage;
                        
                        const parentPercentageText = document.getElementById(`shop-percentage-text-${orderId}`);
                        const parentProgressBar = document.getElementById(`shop-progress-bar-${orderId}`);
                        const parentRatioText = document.getElementById(`shop-ratio-text-${orderId}`);

                        if (parentPercentageText) parentPercentageText.textContent = `${percentage}%`;
                        if (parentProgressBar) parentProgressBar.style.width = `${percentage}%`;
                        if (parentRatioText) parentRatioText.textContent = `${data.shop_progress.sorted} of ${data.shop_progress.total} products packed / in transit`;

                        // Re-serialize modified items back into the trigger button's data attribute so it stays in sync
                        const btn = document.querySelector(`button[data-id="${orderId}"]`);
                        if (btn) {
                            btn.setAttribute('data-items', JSON.stringify(currentOrderItems));
                        }

                        showToast(`Product warehouse status updated.`);
                    }
                })
                .catch(error => {
                    console.error(error);
                    showToast('Failed to update status. Action unauthorized or database error.', 'error');
                });
            }

            function submitAllocationFinalization(e) {
                e.preventDefault();

                const isSuccess100 = document.getElementById('modal-success-100').checked;
                const notes = isSuccess100 ? '' : document.getElementById('modal-sorting-notes').value;

                if (!isSuccess100 && notes.trim() === '') {
                    showToast('Please enter discrepancy notes if order was not 100% successful.', 'error');
                    return;
                }

                const orderId = currentOrderId;

                fetch(`/inventory/sorting-checklist/complete-order/${orderId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ sorting_notes: notes })
                })
                .then(response => {
                    if (!response.ok) throw new Error('Finalization failed.');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Loading sheet finalized successfully!');
                        
                        // Close the modal
                        closeAllocationModal();

                        // Reload window to reflect finalized status in the list
                        setTimeout(() => {
                            window.location.reload();
                        }, 800);
                    }
                })
                .catch(error => {
                    console.error(error);
                    showToast('Failed to finalize loading sheet.', 'error');
                });
            }
        </script>
    @endpush

</x-layouts.inventory>
