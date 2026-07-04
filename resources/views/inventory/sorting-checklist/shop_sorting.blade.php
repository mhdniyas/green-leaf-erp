<x-layouts.inventory title="Worker Shop Sorting">
    @php
        $existingWarehouseTags = $orders
            ->map(fn ($order) => $order->shop?->warehouse_tag)
            ->filter()
            ->unique()
            ->values();
    @endphp

    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inventory.sorting.shop-sorting', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="rounded-xl border border-gray-200 bg-white p-2 text-gray-600 shadow-xs transition-colors hover:bg-gray-50 hover:text-gray-900" title="Previous Day">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </a>
            <form id="worker-sorting-date-form" method="GET" action="{{ route('inventory.sorting.shop-sorting') }}" class="flex items-center gap-2">
                <input id="worker-sorting-date" type="date" name="date" value="{{ $date }}" onchange="document.getElementById('worker-sorting-date-form').submit();"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </form>
            <a href="{{ route('inventory.sorting.shop-sorting', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="rounded-xl border border-gray-200 bg-white p-2 text-gray-600 shadow-xs transition-colors hover:bg-gray-50 hover:text-gray-900" title="Next Day">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
            <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                Shop Cards
            </a>
        </div>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black tracking-tight text-slate-800">Tagged Shops</h2>
                    <p class="mt-1 text-xs text-slate-400">Pick a shop, check its approved list, then mark each product as pending, allocated, or loaded.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">
                    {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                </span>
            </div>

            @if (session('status'))
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-5 space-y-3">
                @forelse ($orders as $order)
                    @php
                        $itemCount = $order->items->count();
                        $packedCount = $order->items->where('is_sorted', true)->count();
                        $isSelected = $selectedOrder?->id === $order->id;
                    @endphp
                    <div class="rounded-3xl border p-4 transition {{ $isSelected ? 'border-cyan-300 bg-cyan-50/60 shadow-sm' : 'border-slate-200 bg-slate-50/60 hover:border-slate-300' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                        onclick="openTagModal('{{ $order->shop?->code }}', '{{ $order->shop?->name }}', '{{ $order->shop?->warehouse_tag }}')"
                                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-2xl bg-slate-900 px-3 text-sm font-black text-white transition hover:bg-slate-800">
                                        {{ $order->shop?->warehouse_tag ?: '--' }}
                                    </button>
                                    <div>
                                        <a href="{{ route('inventory.sorting.shop-sorting.show', ['order' => $order->order_number, 'date' => $date]) }}#product-update-section" class="text-sm font-black text-slate-800 hover:text-brand-700">
                                            {{ $order->shop?->name ?? 'Unknown Shop' }}
                                        </a>
                                        <p class="mt-0.5 text-[10px] font-mono font-bold text-slate-400">{{ $order->order_number }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 text-[11px] font-bold text-slate-500">{{ $packedCount }} / {{ $itemCount }} products moved from approved to allocated or loaded.</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $order->is_allocation_completed ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-amber-200 bg-amber-50 text-amber-700' }}">
                                {{ $order->is_allocation_completed ? 'Finalized' : $order->warehouseWorkflowLabel() }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <button type="button"
                                onclick="openTagModal('{{ $order->shop?->code }}', '{{ $order->shop?->name }}', '{{ $order->shop?->warehouse_tag }}')"
                                class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                                Change Tag
                            </button>
                            <a href="{{ route('inventory.sorting.shop-sorting.show', ['order' => $order->order_number, 'date' => $date]) }}#product-update-section" class="ml-auto rounded-2xl bg-slate-900 px-3 py-2 text-[11px] font-black text-white transition hover:bg-slate-800">
                                Open
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                        <h3 class="text-sm font-black text-slate-800">No approved shop orders</h3>
                        <p class="mt-1 text-xs text-slate-500">Approved orders for this date will appear here for warehouse workers.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
            @if (! $selectedOrder)
                <div class="flex min-h-[420px] items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                    <div>
                        <h2 class="text-lg font-black text-slate-800">Pick a shop to start sorting</h2>
                        <p class="mt-2 text-sm text-slate-500">The worker page will show all approved products for the selected shop.</p>
                    </div>
                </div>
            @else
                @php
                    $selectedTotal = $selectedOrder->items->count();
                    $selectedPacked = $selectedOrder->items->where('is_sorted', true)->count();
                    $selectedPercentage = $selectedTotal > 0 ? (int) round(($selectedPacked / $selectedTotal) * 100) : 0;
                @endphp
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex h-11 min-w-11 items-center justify-center rounded-2xl bg-cyan-400 px-4 text-base font-black text-slate-950">
                                {{ $selectedOrder->shop?->warehouse_tag ?: '--' }}
                            </span>
                            <div>
                                <h2 class="text-xl font-black tracking-tight text-slate-900">{{ $selectedOrder->shop?->name ?? 'Unknown Shop' }}</h2>
                                <p class="mt-1 text-xs font-mono font-bold text-slate-400">{{ $selectedOrder->order_number }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-slate-500">Use this page to mark each product as `Pending`, `Allocated`, or `Loaded`, then finalize the shop sheet.</p>
                        <button type="button"
                            onclick="openTagModal('{{ $selectedOrder->shop?->code }}', '{{ $selectedOrder->shop?->name }}', '{{ $selectedOrder->shop?->warehouse_tag }}')"
                            class="mt-4 inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                            <span>Tag {{ $selectedOrder->shop?->warehouse_tag ?: '--' }}</span>
                            <span class="text-slate-400">change</span>
                        </button>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex items-center justify-between gap-8 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                            <span>Progress</span>
                            <span id="workspace-progress-text">{{ $selectedPacked }}/{{ $selectedTotal }}</span>
                        </div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div id="workspace-progress-bar" class="h-full rounded-full bg-cyan-500 transition-all duration-300" style="width: {{ $selectedPercentage }}%"></div>
                        </div>
                        <p id="workspace-progress-label" class="mt-2 text-right text-xs font-black text-slate-700">{{ $selectedPercentage }}% complete</p>
                    </div>
                </div>

                <div id="product-update-section" class="mt-6 overflow-hidden rounded-3xl border border-slate-200 scroll-mt-36">
                    <div class="hidden grid-cols-[minmax(0,2.2fr)_120px_110px_1fr] gap-4 bg-slate-50 px-5 py-3 text-[10px] font-black uppercase tracking-[0.22em] text-slate-400 md:grid">
                        <div>Product</div>
                        <div>Approved Qty</div>
                        <div>Unit</div>
                        <div>Status</div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($selectedOrder->items as $item)
                            <article class="grid gap-4 px-5 py-5 md:grid-cols-[minmax(0,2.2fr)_120px_110px_1fr] md:items-center"
                                data-item-row
                                data-item-id="{{ $item->id }}"
                                data-shop-order-id="{{ $selectedOrder->id }}"
                                data-status="{{ $item->sorting_status }}"
                                data-sorted="{{ $item->is_sorted ? '1' : '0' }}">
                                <div>
                                    <h3 class="text-base font-black text-slate-900">{{ $item->product->name }}</h3>
                                    <p class="mt-1 text-xs font-mono font-bold text-slate-400">{{ $item->product->sku }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-bold text-slate-500">
                                        <span>Requested: {{ number_format((float) $item->requested_qty, 2) }}</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $item->warehouseWorkflowLabel() }}</span>
                                        @if ($item->sorted_by)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">{{ $item->sortedBy?->name }} {{ $item->sorted_at?->setTimezone('Asia/Kolkata')->format('h:i A') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-lg font-black text-slate-800">{{ number_format((float) ($item->approved_qty ?? $item->requested_qty), 2) }}</div>
                                <div class="text-sm font-bold uppercase text-slate-500">{{ $item->unit }}</div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="updateSortingStatus({{ $item->id }}, 'pending')" class="rounded-2xl px-3 py-2 text-[11px] font-black transition {{ $item->sorting_status === 'pending' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100' }}">
                                        Pending
                                    </button>
                                    <button type="button" onclick="updateSortingStatus({{ $item->id }}, 'allocated')" class="rounded-2xl px-3 py-2 text-[11px] font-black transition {{ $item->sorting_status === 'allocated' ? 'bg-amber-500 text-white' : 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                                        Allocated
                                    </button>
                                    <button type="button" onclick="updateSortingStatus({{ $item->id }}, 'loaded')" class="rounded-2xl px-3 py-2 text-[11px] font-black transition {{ $item->sorting_status === 'loaded' ? 'bg-emerald-600 text-white' : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                        Loaded
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                    <label for="workspace-sorting-notes" class="block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Final Notes</label>
                    <textarea id="workspace-sorting-notes" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-200" placeholder="Add packing notes, shortage notes, or loading comments...">{{ $selectedOrder->sorting_notes }}</textarea>
                    <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                        <a href="{{ route('inventory.sorting.shop-orders', ['date' => $date]) }}#shop-card-{{ $selectedOrder->id }}" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                            Open Card View
                        </a>
                        <button type="button" onclick="completeSortingSheet({{ $selectedOrder->id }})" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-black text-white transition hover:bg-slate-800">
                            {{ $selectedOrder->is_allocation_completed ? 'Update Final Notes' : 'Finalize Shop Sheet' }}
                        </button>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <div id="worker-toast" class="pointer-events-none fixed bottom-5 right-5 z-50 flex flex-col gap-2"></div>
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
            const warehouseTags = @json($existingWarehouseTags);

            function showWorkerToast(message, type = 'success') {
                @if (! $selectedOrder)
                    return;
                @endif

                    const element = document.createElement('div');
                    element.className = `rounded-2xl border px-4 py-3 text-xs font-black shadow-lg transition ${
                        type === 'success'
                            ? 'border-emerald-200 bg-white text-emerald-700'
                            : 'border-red-200 bg-white text-red-700'
                    }`;
                    element.textContent = message;

                    const container = document.getElementById('worker-toast');
                    container.appendChild(element);

                    setTimeout(() => {
                        element.remove();
                    }, 2600);
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

            function scrollToProductUpdateSection() {
                const detailsSection = document.getElementById('product-update-section');
                if (!detailsSection || window.innerWidth >= 1280) {
                    return;
                }

                detailsSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
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

            function updateWorkspaceProgress(shopProgress) {
                @if (! $selectedOrder)
                    return;
                @endif

                    const progressText = document.getElementById('workspace-progress-text');
                    const progressBar = document.getElementById('workspace-progress-bar');
                    const progressLabel = document.getElementById('workspace-progress-label');

                    if (progressText) {
                        progressText.textContent = `${shopProgress.sorted}/${shopProgress.total}`;
                    }

                    if (progressBar) {
                        progressBar.style.width = `${shopProgress.percentage}%`;
                    }

                    if (progressLabel) {
                        progressLabel.textContent = `${shopProgress.percentage}% complete`;
                    }
                }

            function applyRowState(itemId, nextStatus, payload) {
                @if (! $selectedOrder)
                    return;
                @endif

                    const row = document.querySelector(`[data-item-row][data-item-id="${itemId}"]`);
                    if (!row) {
                        return;
                    }

                    row.dataset.status = nextStatus;
                    row.dataset.sorted = payload.item.is_sorted ? '1' : '0';

                    const buttons = row.querySelectorAll('button');
                    buttons.forEach((button) => {
                        const label = button.textContent.trim().toLowerCase();
                        const isActive = (
                            (label === 'pending' && nextStatus === 'pending') ||
                            (label === 'allocated' && nextStatus === 'allocated') ||
                            (label === 'loaded' && nextStatus === 'loaded')
                        );

                        button.classList.remove('bg-slate-900', 'text-white', 'bg-amber-500', 'bg-emerald-600');
                        button.classList.add('border');

                        if (label === 'pending') {
                            button.className = `rounded-2xl px-3 py-2 text-[11px] font-black transition ${isActive ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100'}`;
                        }

                        if (label === 'allocated') {
                            button.className = `rounded-2xl px-3 py-2 text-[11px] font-black transition ${isActive ? 'bg-amber-500 text-white' : 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'}`;
                        }

                        if (label === 'loaded') {
                            button.className = `rounded-2xl px-3 py-2 text-[11px] font-black transition ${isActive ? 'bg-emerald-600 text-white' : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}`;
                        }
                    });

                    updateWorkspaceProgress(payload.shop_progress);
                }

            async function updateSortingStatus(itemId, status) {
                @if (! $selectedOrder)
                    return;
                @endif

                    try {
                        const response = await fetch(`/inventory/sorting-checklist/toggle/${itemId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ status }),
                        });

                        const payload = await response.json();

                        if (!response.ok || !payload.success) {
                            showWorkerToast(payload.message ?? 'Unable to update item status.', 'error');
                            return;
                        }

                        applyRowState(itemId, status, payload);
                        showWorkerToast('Product status updated.');
                    } catch (error) {
                        showWorkerToast('Unable to update item status.', 'error');
                    }
                }

            async function completeSortingSheet(orderId) {
                @if (! $selectedOrder)
                    return;
                @endif

                    try {
                        const response = await fetch(`/inventory/sorting-checklist/complete-order/${orderId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                sorting_notes: document.getElementById('workspace-sorting-notes').value,
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok || !payload.success) {
                            showWorkerToast(payload.message ?? 'Unable to finalize shop sheet.', 'error');
                            return;
                        }

                        showWorkerToast(payload.message ?? 'Shop sheet finalized.');
                    } catch (error) {
                        showWorkerToast('Unable to finalize shop sheet.', 'error');
                    }
            }

            if (window.location.hash === '#product-update-section') {
                window.addEventListener('load', () => {
                    setTimeout(scrollToProductUpdateSection, 120);
                });
            }
        </script>
    @endpush
</x-layouts.inventory>
