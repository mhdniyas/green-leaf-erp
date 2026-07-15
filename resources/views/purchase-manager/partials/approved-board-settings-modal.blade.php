@php
    $cutoffService = app(\App\Services\Purchasing\PurchaserBusinessDayService::class);
    $cutoffLabel = $cutoffService->cutoffLabel();
    $cutoffInputValue = $cutoffService->cutoffInputValue();
    $autoApprovedOrders = collect($autoApprovedOrders ?? []);
    $autoApproveShopOrdersEnabled = (bool) ($autoApproveShopOrdersEnabled ?? false);
@endphp

<button
    type="button"
    onclick="openApprovedBoardSettingsModal()"
    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
>
    Settings
    @if($autoApproveShopOrdersEnabled)
        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Auto</span>
    @endif
</button>

<div id="approved-board-settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 px-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Purchase Manager Settings</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">Approved Board controls</h3>
                <p class="mt-2 text-sm text-slate-600">Manage cutoff timing and automatic approval for on-time shop orders.</p>
            </div>
            <button type="button" onclick="closeApprovedBoardSettingsModal()" class="rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('business-day-settings.cutoff.update') }}" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                @csrf
                <input type="hidden" name="redirect_date" value="{{ $date ?? request('date') }}">

                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Business Day</p>
                <h4 class="mt-1 text-sm font-black text-slate-950">Cutoff time</h4>
                <input
                    id="cutoff-time-input"
                    type="time"
                    name="cutoff_time"
                    value="{{ old('cutoff_time', $cutoffInputValue) }}"
                    required
                    class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-800 focus:border-sky-500 focus:outline-none"
                >
                <p class="mt-2 text-xs text-slate-500">Current live cutoff: {{ $cutoffLabel }}</p>
                <button type="submit" class="mt-4 rounded-2xl bg-sky-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-sky-700">
                    Save Cutoff
                </button>
            </form>

            <form method="POST" action="{{ route('business-day-settings.auto-approve.update') }}" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
                @csrf

                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Automatic Approval</p>
                <h4 class="mt-1 text-sm font-black text-emerald-950">On-time shop orders</h4>
                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3">
                    <input type="checkbox" name="auto_approve_shop_orders" value="1" @checked($autoApproveShopOrdersEnabled) class="mt-1 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                    <span>
                        <span class="block text-sm font-black text-slate-950">Approve automatically</span>
                        <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">New on-time shop orders go directly to Approved Board. Late orders and update requests still need review.</span>
                    </span>
                </label>
                <button type="submit" class="mt-4 rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-emerald-700">
                    Save Automatic Approval
                </button>
            </form>
        </div>

        <div class="mt-5 rounded-3xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Auto-approved for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                    <h4 class="mt-1 text-sm font-black text-slate-950">{{ $autoApprovedOrders->count() }} shop order(s)</h4>
                </div>
                <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider {{ $autoApproveShopOrdersEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $autoApproveShopOrdersEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                @forelse($autoApprovedOrders as $order)
                    <a href="{{ route('requisitions.show', $order->order_number) }}" class="flex items-start justify-between gap-4 px-4 py-3 transition hover:bg-slate-50">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-slate-950">{{ $order->shop?->name ?? 'Unknown Shop' }}</span>
                            <span class="mt-0.5 block text-xs font-semibold text-slate-500">{{ $order->order_number }} · {{ $order->items->count() }} item(s)</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block text-xs font-black text-emerald-700">{{ number_format((float) $order->items->sum('approved_qty'), 2) }}</span>
                            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">{{ $order->reviewed_at?->format('h:i A') ?? $order->submitted_at?->format('h:i A') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-5 text-sm font-semibold text-slate-400">No shop orders were automatically approved for this date.</p>
                @endforelse
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button type="button" onclick="closeApprovedBoardSettingsModal()" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                Close
            </button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function openApprovedBoardSettingsModal() {
                const modal = document.getElementById('approved-board-settings-modal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeApprovedBoardSettingsModal() {
                const modal = document.getElementById('approved-board-settings-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                if (@json(request()->boolean('settings'))) {
                    openApprovedBoardSettingsModal();
                }
            });
        </script>
    @endpush
@endonce
