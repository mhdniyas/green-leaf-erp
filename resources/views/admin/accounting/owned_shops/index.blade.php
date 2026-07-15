<x-layouts.accounting title="Owned Shop Accounting">
    @php
        $openAddModal = $errors->any() || old('shop_id');
        $pendingBalanceTotal = (float) $shops->sum(fn ($shop): float => (float) ($shop->pending_balance_amount ?? 0));
        $pettyBalanceTotal = (float) $shops->sum(fn ($shop): float => (float) ($shop->petty_cash_balance_amount ?? 0));
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-6">
        <section class="rounded-[1.9rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Owned Shop Accounting</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Eligible shops</h1>
                    <p class="mt-3 max-w-3xl text-sm font-semibold leading-6 text-slate-600">Only owned and partnership shops with accounting enabled appear here. Keep this page as a clean control table for petty cash, ownership mode, and settlement access.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        id="owned-shop-open-modal"
                        @disabled($availableShops->isEmpty())
                        class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                        Add Owned Shop
                    </button>
                    <a href="{{ route('admin.accounting.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[1.9rem] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Shop Register</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Owned and partnership shops table</h2>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-500">
                    {{ number_format($shops->count()) }} shop(s)
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="owned-shops-table" class="min-w-full table-auto text-left">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Shop</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Update Alert</th>
                            <th class="px-4 py-3 text-right">Pending Balance</th>
                            <th class="px-4 py-3 text-right">Petty Balance</th>
                            <th class="px-4 py-3">Configured</th>
                            <th class="px-4 py-3 text-right">Open</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($shops as $shop)
                            @php
                                $latestEntry = $shop->latestAccountingEntry;
                                $hasRecheckUpdates = (int) ($shop->recheck_updates_count ?? 0) > 0;
                                $hasPendingUpdates = (int) ($shop->pending_updates_count ?? 0) > 0;
                            @endphp
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-4 py-4">
                                    <p class="font-black text-slate-950">{{ $shop->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $shop->users->count() }} linked user(s)</p>
                                </td>
                                <td class="px-4 py-4 font-black text-slate-700">{{ $shop->code }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">
                                        {{ ucfirst($shop->accounting_mode) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($hasRecheckUpdates)
                                        <div class="space-y-2">
                                            <span class="inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-red-700">
                                                Recheck Update
                                            </span>
                                            <p class="text-xs font-semibold text-slate-600">{{ number_format((int) $shop->recheck_updates_count) }} item(s) need attention</p>
                                            @if ($latestEntry)
                                                <p class="text-xs font-semibold text-slate-500">Updated {{ $latestEntry->updated_at?->format('d M h:i A') }}</p>
                                            @endif
                                        </div>
                                    @elseif ($hasPendingUpdates)
                                        <div class="space-y-2">
                                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">
                                                New Update
                                            </span>
                                            <p class="text-xs font-semibold text-slate-600">{{ number_format((int) $shop->pending_updates_count) }} submitted update(s)</p>
                                            @if ($latestEntry)
                                                <p class="text-xs font-semibold text-slate-500">{{ $latestEntry->submittedBy?->name ?? 'Shop owner' }} · {{ $latestEntry->updated_at?->format('d M h:i A') }}</p>
                                            @endif
                                        </div>
                                    @elseif ($latestEntry)
                                        <div class="space-y-2">
                                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">
                                                No New Update
                                            </span>
                                            <p class="text-xs font-semibold text-slate-500">Last {{ str($latestEntry->status)->replace('_', ' ')->title() }} · {{ $latestEntry->updated_at?->format('d M h:i A') }}</p>
                                        </div>
                                    @else
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">
                                            No Ledger Yet
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right font-black {{ (float) ($shop->pending_balance_amount ?? 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                    Rs. {{ number_format((float) ($shop->pending_balance_amount ?? 0), 2) }}
                                </td>
                                @php
                                    $pettyBalance = (float) ($shop->petty_cash_balance_amount ?? 0);
                                @endphp
                                <td class="px-4 py-4 text-right font-black {{ $pettyBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $pettyBalance >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($pettyBalance), 2) }}
                                </td>
                                <td class="px-4 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em]',
                                        'border border-emerald-200 bg-emerald-50 text-emerald-700' => $shop->accounting_enabled,
                                        'border border-slate-200 bg-slate-100 text-slate-600' => ! $shop->accounting_enabled,
                                    ])>
                                        {{ $shop->accounting_enabled ? 'Enabled' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="inline-flex h-9 items-center rounded-xl border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-100">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-sm font-bold text-slate-500">
                                    No accounting-enabled owned or partnership shops were found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($shops->isNotEmpty())
                        <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-right font-black uppercase tracking-[0.14em] text-slate-500">Total</td>
                                <td class="px-4 py-4 text-right font-black {{ $pendingBalanceTotal > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                    Rs. {{ number_format($pendingBalanceTotal, 2) }}
                                </td>
                                <td class="px-4 py-4 text-right font-black {{ $pettyBalanceTotal >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $pettyBalanceTotal >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($pettyBalanceTotal), 2) }}
                                </td>
                                <td colspan="2" class="px-4 py-4"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </div>

    <div id="owned-shop-modal" class="@if(! $openAddModal) hidden @endif fixed inset-0 z-[70]">
        <div id="owned-shop-modal-overlay" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-4xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Add Owned Shop</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Enable accounting in a popup</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Use this compact form instead of taking space on the table page.</p>
                    </div>
                    <button type="button" id="owned-shop-close-modal" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-6">
                    @if($availableShops->isNotEmpty())
                        <form method="POST" action="{{ route('admin.accounting.owned-shops.store') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_220px_220px]">
                            @csrf
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shop</span>
                                <select name="shop_id" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                                    <option value="">Select shop</option>
                                    @foreach($availableShops as $shop)
                                        <option value="{{ $shop->id }}" @selected((string) old('shop_id') === (string) $shop->id)>
                                            {{ $shop->name }} ({{ $shop->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Mode</span>
                                <select name="accounting_mode" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                                    <option value="owned" @selected(old('accounting_mode', 'owned') === 'owned')>Owned</option>
                                    <option value="partnership" @selected(old('accounting_mode') === 'partnership')>Partnership</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Default Petty Cash</span>
                                <input type="number" step="0.01" min="0" name="default_petty_cash_amount" value="{{ old('default_petty_cash_amount', '0.00') }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                            </label>

                            <div class="lg:col-span-3 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                                <button type="button" id="owned-shop-cancel-modal" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                    Add Shop
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-bold text-emerald-800">
                            All shops are already configured for owned-shop accounting.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('owned-shop-modal');
            const openButton = document.getElementById('owned-shop-open-modal');
            const closeButton = document.getElementById('owned-shop-close-modal');
            const cancelButton = document.getElementById('owned-shop-cancel-modal');
            const overlay = document.getElementById('owned-shop-modal-overlay');

            if (!modal || !openButton) {
                return;
            }

            const showModal = () => {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            const hideModal = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            openButton.addEventListener('click', showModal);
            closeButton?.addEventListener('click', hideModal);
            cancelButton?.addEventListener('click', hideModal);
            overlay?.addEventListener('click', hideModal);

            if (!modal.classList.contains('hidden')) {
                document.body.classList.add('overflow-hidden');
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    hideModal();
                }
            });
        })();
    </script>
</x-layouts.accounting>
