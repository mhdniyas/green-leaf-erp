<x-layouts.app title="Loadout">
    <div class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 lg:px-4 lg:py-4">

        <div class="flex items-center justify-between gap-3 px-1">
            <div>
                <h1 class="text-base font-black text-slate-900">Warehouse Loadout</h1>
                <p class="mt-1 text-[11px] font-semibold text-slate-500">Track waiting, partial, ready, and in-transit orders.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-slate-500">
                {{ now()->format('d M Y') }}
            </span>
        </div>

        <form method="GET" action="{{ route('warehouse.loadout.index') }}" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex items-center gap-2">
                <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                    </svg>
                    <input type="search"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search by shop or order number"
                           class="w-full border-none bg-transparent p-0 text-sm font-semibold text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                </div>
                <button type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-[11px] font-black uppercase tracking-[0.12em] text-white transition-colors hover:bg-slate-800 border-none cursor-pointer">
                    Search
                </button>
            </div>
        </form>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @php
            $activeTab = request()->query('tab', 'waiting');
            $waiting = $orders->filter(fn ($shopOrder) => ! in_array($shopOrder->delivery_status, ['in_transit', 'delivered'], true) && $shopOrder->loaded_count === 0)->values();
            $partial = $orders->filter(fn ($shopOrder) => ! in_array($shopOrder->delivery_status, ['in_transit', 'delivered'], true) && $shopOrder->loaded_count > 0 && $shopOrder->loaded_count < $shopOrder->total_count)->values();
            $ready = $orders->filter(fn ($shopOrder) => ! in_array($shopOrder->delivery_status, ['in_transit', 'delivered'], true) && $shopOrder->total_count > 0 && $shopOrder->loaded_count === $shopOrder->total_count)->values();
            $inTransit = $orders->where('delivery_status', 'in_transit')->values();
            $delivered = $orders->filter(fn ($shopOrder) => in_array($shopOrder->delivery_status, ['delivered', 'pending_approval', 'partially_delivered', 'delivery_issue'], true))->values();

            $tabs = [
                'waiting' => ['label' => 'Waiting', 'count' => $waiting->count()],
                'partial' => ['label' => 'Partial', 'count' => $partial->count()],
                'ready' => ['label' => 'Ready', 'count' => $ready->count()],
                'transit' => ['label' => 'In Transit', 'count' => $inTransit->count()],
                'delivered' => ['label' => 'Delivered', 'count' => $delivered->count()],
            ];

            $emptyStates = [
                'waiting' => 'No waiting orders found.',
                'partial' => 'No partially loaded orders found.',
                'ready' => 'No fully loaded orders ready for delivery.',
                'transit' => 'No orders out for delivery.',
                'delivered' => 'No delivered orders found.',
            ];
        @endphp

        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-200/60 p-1 sm:grid-cols-5">
            @foreach($tabs as $key => $tab)
                <button type="button"
                        onclick="switchLoadoutTab('{{ $key }}')"
                        id="tab-btn-{{ $key }}"
                        class="{{ $activeTab === $key ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900' }} rounded-xl px-2 py-2 text-center text-[11px] font-black uppercase tracking-[0.08em] transition-all border-none cursor-pointer">
                    {{ $tab['label'] }} ({{ $tab['count'] }})
                </button>
            @endforeach
        </div>

        @foreach(['waiting' => $waiting, 'partial' => $partial, 'ready' => $ready, 'transit' => $inTransit, 'delivered' => $delivered] as $tabKey => $tabOrders)
            <div id="loadout-tab-{{ $tabKey }}" class="{{ $activeTab === $tabKey ? '' : 'hidden' }} space-y-2.5">
                @if($tabOrders->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center">
                        <p class="text-sm font-bold text-slate-400">{{ $emptyStates[$tabKey] }}</p>
                    </div>
                @else
                    @foreach($tabOrders as $shopOrder)
                        @php
                            $progress = $shopOrder->total_count > 0 ? round(($shopOrder->loaded_count / $shopOrder->total_count) * 100) : 0;
                            $statusLabel = match (true) {
                                $shopOrder->delivery_status === 'pending_approval' => 'Pending Approval',
                                $shopOrder->delivery_status === 'partially_delivered' => 'Partially Delivered',
                                $shopOrder->delivery_status === 'delivery_issue' => 'Delivery Issue',
                                $shopOrder->delivery_status === 'delivered' => 'Delivered',
                                $shopOrder->delivery_status === 'in_transit' => 'Out for Delivery',
                                $shopOrder->loaded_count === 0 => 'Waiting for Loadout',
                                $shopOrder->loaded_count === $shopOrder->total_count => 'Ready for Delivery',
                                default => 'Partially Loaded',
                            };
                            $statusColor = match (true) {
                                $shopOrder->delivery_status === 'pending_approval' => 'bg-amber-100 text-amber-700',
                                $shopOrder->delivery_status === 'partially_delivered' => 'bg-cyan-100 text-cyan-700',
                                $shopOrder->delivery_status === 'delivery_issue' => 'bg-rose-100 text-rose-700',
                                $shopOrder->delivery_status === 'delivered' => 'bg-emerald-100 text-emerald-700',
                                $shopOrder->delivery_status === 'in_transit' => 'bg-indigo-100 text-indigo-700',
                                $shopOrder->loaded_count === 0 => 'bg-amber-100 text-amber-700',
                                $shopOrder->loaded_count === $shopOrder->total_count => 'bg-emerald-100 text-emerald-700',
                                default => 'bg-cyan-100 text-cyan-700',
                            };
                            $buttonLabel = match (true) {
                                $shopOrder->delivery_status === 'in_transit' => 'Update',
                                in_array($shopOrder->delivery_status, ['delivered', 'pending_approval', 'partially_delivered', 'delivery_issue'], true) => 'View',
                                default => 'Open',
                            };
                            $progressColor = match (true) {
                                $shopOrder->delivery_status === 'pending_approval' => 'bg-amber-500',
                                $shopOrder->delivery_status === 'partially_delivered' => 'bg-cyan-500',
                                $shopOrder->delivery_status === 'delivery_issue' => 'bg-rose-500',
                                $shopOrder->delivery_status === 'delivered' => 'bg-emerald-500',
                                $shopOrder->delivery_status === 'in_transit' => 'bg-indigo-500',
                                $shopOrder->loaded_count === $shopOrder->total_count => 'bg-emerald-500',
                                $shopOrder->loaded_count > 0 => 'bg-cyan-500',
                                default => 'bg-slate-300',
                            };
                        @endphp
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center gap-4 p-4">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black text-slate-900">{{ $shopOrder->loadoutDisplayName() }}</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-slate-400">{{ $shopOrder->order_number }}</p>

                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full transition-all {{ $progressColor }}"
                                                 style="width: {{ $progress }}%"></div>
                                        </div>
                                        <span class="shrink-0 text-[10px] font-black text-slate-500">
                                            {{ $shopOrder->loaded_count }}/{{ $shopOrder->total_count }} loaded
                                        </span>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                    <a href="{{ route('warehouse.loadout.show', $shopOrder) }}"
                                       class="inline-flex h-8 items-center justify-center rounded-xl {{ $shopOrder->delivery_status === 'in_transit' ? 'bg-slate-800 hover:bg-slate-900' : (in_array($shopOrder->delivery_status, ['delivered', 'pending_approval', 'partially_delivered', 'delivery_issue'], true) ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700') }} px-3 text-[11px] font-black text-white transition-colors text-decoration-none">
                                        {{ $buttonLabel }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
        function switchLoadoutTab(tabName) {
            ['waiting', 'partial', 'ready', 'transit', 'delivered'].forEach(function (name) {
                document.getElementById('loadout-tab-' + name)?.classList.add('hidden');
                document.getElementById('tab-btn-' + name)?.classList.remove('bg-slate-950', 'text-white', 'shadow-sm');
                document.getElementById('tab-btn-' + name)?.classList.add('text-slate-500', 'hover:text-slate-900');
            });

            document.getElementById('loadout-tab-' + tabName)?.classList.remove('hidden');
            const activeBtn = document.getElementById('tab-btn-' + tabName);
            if (activeBtn) {
                activeBtn.classList.remove('text-slate-500', 'hover:text-slate-900');
                activeBtn.classList.add('bg-slate-950', 'text-white', 'shadow-sm');
            }

            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }
    </script>
    @endpush
</x-layouts.app>
