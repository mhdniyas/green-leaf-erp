<x-layouts.accounting title="Client Accounting">
    @php
        $openAddModal = $errors->any() || old('shop_id');
        $pendingBalanceTotal = (float) $shops->sum(fn ($shop): float => (float) ($shop->pending_balance_amount ?? 0));
        $closingBalanceTotal = (float) $shops->sum(fn ($shop): float => (float) ($shop->latestClosingAccountingEntry?->closing_cash ?? 0));
        $clientRows = $clients->map(function ($client) use ($shops) {
            $clientShops = $shops->where('client_id', $client->id);

            return [
                'client' => $client,
                'shop_count' => $clientShops->count(),
                'pending_balance' => (float) $clientShops->sum(fn ($shop): float => (float) ($shop->pending_balance_amount ?? 0)),
            ];
        })->filter(fn ($row): bool => $row['shop_count'] > 0)->values();

        $attentionShops = $shops
            ->map(function ($shop) {
                $recheckCount = (int) ($shop->recheck_updates_count ?? 0);
                $pendingCount = (int) ($shop->pending_updates_count ?? 0);

                if ($recheckCount > 0) {
                    return [
                        'shop' => $shop,
                        'priority' => 1,
                        'label' => 'Needs recheck',
                        'message' => $shop->name.' needs recheck — shop sent updates after your review request.',
                        'count' => $recheckCount,
                    ];
                }

                if ($pendingCount > 0) {
                    return [
                        'shop' => $shop,
                        'priority' => 2,
                        'label' => 'Ready for review',
                        'message' => $shop->name.' is ready for review — cashbook submitted and waiting for admin.',
                        'count' => $pendingCount,
                    ];
                }

                return null;
            })
            ->filter()
            ->sortBy('priority')
            ->values();
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Client Accounting</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Client shops</h1>
                        <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-300">Review cashbooks, pending balances, and daily closing for client-assigned shops.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.accounting.index') }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">
                            Back to Dashboard
                        </a>
                        <button
                            type="button"
                            id="owned-shop-open-modal"
                            @disabled($availableShops->isEmpty())
                            class="inline-flex h-11 items-center justify-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600 disabled:cursor-not-allowed disabled:bg-slate-500"
                        >
                            Add Client Shop
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border {{ $attentionShops->isNotEmpty() ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} shadow-sm">
            <div class="border-b {{ $attentionShops->isNotEmpty() ? 'border-amber-200' : 'border-emerald-200' }} px-5 py-4 sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] {{ $attentionShops->isNotEmpty() ? 'text-amber-700' : 'text-emerald-700' }}">Needs attention</p>
                <h2 class="mt-1 text-xl font-black {{ $attentionShops->isNotEmpty() ? 'text-amber-950' : 'text-emerald-950' }}">
                    @if ($attentionShops->isNotEmpty())
                        {{ $attentionShops->count() }} shop{{ $attentionShops->count() === 1 ? '' : 's' }} need action
                    @else
                        All shops are up to date
                    @endif
                </h2>
            </div>
            @if ($attentionShops->isNotEmpty())
                <div class="divide-y divide-amber-200/70">
                    @foreach ($attentionShops as $item)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span @class([
                                        'inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em]',
                                        'border border-red-200 bg-red-50 text-red-700' => $item['priority'] === 1,
                                        'border border-amber-300 bg-white text-amber-800' => $item['priority'] === 2,
                                    ])>
                                        {{ $item['label'] }}
                                        @if ($item['count'] > 1)
                                            · {{ $item['count'] }}
                                        @endif
                                    </span>
                                    <p class="text-sm font-black text-slate-950">{{ $item['shop']->name }}</p>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-slate-600">{{ $item['message'] }}</p>
                            </div>
                            <a href="{{ route('admin.accounting.owned-shops.show', $item['shop']) }}" class="inline-flex h-10 shrink-0 items-center rounded-[1rem] bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-slate-800">
                                Open
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-5 py-4 text-sm font-semibold text-emerald-800 sm:px-6">No cashbooks waiting for review or recheck.</p>
            @endif
        </section>

        @if ($clientRows->isNotEmpty())
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($clientRows as $row)
                    <a href="{{ route('admin.accounting.clients.show', $row['client']) }}" class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Client</p>
                                <h2 class="mt-1 text-xl font-black text-slate-950">{{ $row['client']->name }}</h2>
                            </div>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                                Open
                            </span>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Shops</p>
                                <p class="mt-1 text-xl font-black text-slate-950">{{ number_format($row['shop_count']) }}</p>
                            </div>
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Pending</p>
                                <p class="mt-1 text-lg font-black {{ $row['pending_balance'] > 0 ? 'text-rose-700' : 'text-slate-950' }}">Rs. {{ number_format($row['pending_balance'], 2) }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </section>
        @endif

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Register</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Client shops</h2>
                </div>
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ number_format($shops->count()) }} shop(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table id="owned-shops-table" class="min-w-full table-auto text-left">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Shop</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Pending</th>
                            <th class="px-4 py-3 text-right">Closing Balance</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($shops as $shop)
                            @php
                                $recheckCount = (int) ($shop->recheck_updates_count ?? 0);
                                $pendingCount = (int) ($shop->pending_updates_count ?? 0);
                                $closingBalance = (float) ($shop->latestClosingAccountingEntry?->closing_cash ?? 0);
                                $hasLedger = $shop->latestAccountingEntry !== null;

                                if ($recheckCount > 0) {
                                    $statusLabel = 'Needs recheck'.($recheckCount > 1 ? ' · '.$recheckCount : '');
                                    $statusClass = 'border-red-200 bg-red-50 text-red-700';
                                } elseif ($pendingCount > 0) {
                                    $statusLabel = 'Ready for review'.($pendingCount > 1 ? ' · '.$pendingCount : '');
                                    $statusClass = 'border-amber-200 bg-amber-50 text-amber-800';
                                } elseif ($hasLedger) {
                                    $statusLabel = 'Up to date';
                                    $statusClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
                                } else {
                                    $statusLabel = 'No cashbook yet';
                                    $statusClass = 'border-slate-200 bg-slate-100 text-slate-600';
                                }
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-4">
                                    <p class="font-black text-slate-950">{{ $shop->name }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($shop->client)
                                        <a href="{{ route('admin.accounting.clients.show', $shop->client) }}" class="font-black text-emerald-700 transition hover:text-emerald-900">{{ $shop->client->name }}</a>
                                    @else
                                        <span class="font-black text-slate-700">Aishwarya Veg</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right font-black {{ (float) ($shop->pending_balance_amount ?? 0) > 0 ? 'text-rose-700' : 'text-slate-950' }}">
                                    Rs. {{ number_format((float) ($shop->pending_balance_amount ?? 0), 2) }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <p class="font-black {{ $closingBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                        {{ $closingBalance >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($closingBalance), 2) }}
                                    </p>
                                    @if ($shop->latestClosingAccountingEntry?->business_date)
                                        <p class="mt-1 text-[11px] font-bold text-slate-400">{{ $shop->latestClosingAccountingEntry->business_date->format('d M Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="inline-flex h-9 items-center rounded-[0.9rem] bg-slate-950 px-3 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-slate-800">
                                            Open
                                        </a>
                                        <div class="relative" data-owned-shop-menu>
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-[0.9rem] border border-slate-200 text-slate-600 hover:bg-slate-50"
                                                data-owned-shop-menu-toggle
                                                aria-label="More actions"
                                            >
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </button>
                                            <div class="absolute right-0 z-20 mt-2 hidden w-40 overflow-hidden rounded-[1rem] border border-slate-200 bg-white shadow-lg" data-owned-shop-menu-panel>
                                                <button type="button" data-owned-shop-edit="{{ $shop->id }}" class="block w-full px-4 py-2.5 text-left text-xs font-black uppercase tracking-[0.14em] text-slate-700 hover:bg-slate-50">
                                                    Edit
                                                </button>
                                                <form method="POST" action="{{ route('admin.accounting.owned-shops.destroy', $shop) }}" onsubmit="return confirm('Remove {{ $shop->name }} from client accounting? Existing shop records will be kept.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="block w-full px-4 py-2.5 text-left text-xs font-black uppercase tracking-[0.14em] text-rose-700 hover:bg-rose-50">
                                                        Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-slate-500">
                                    No accounting-enabled client shops were found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($shops->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-sm">
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-right text-xs font-black uppercase tracking-[0.14em] text-slate-500">Total</td>
                                <td class="px-4 py-4 text-right font-black {{ $pendingBalanceTotal > 0 ? 'text-rose-700' : 'text-slate-950' }}">
                                    Rs. {{ number_format($pendingBalanceTotal, 2) }}
                                </td>
                                <td class="px-4 py-4 text-right font-black {{ $closingBalanceTotal >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $closingBalanceTotal >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($closingBalanceTotal), 2) }}
                                </td>
                                <td class="px-4 py-4"></td>
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
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Add Client Shop</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Enable client accounting</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Assign a shop to a client and turn on cashbook review.</p>
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
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Client</span>
                                <select name="client_id" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                                    <option value="">Aishwarya Veg</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">New Client</span>
                                <input type="text" name="client_name" value="{{ old('client_name') }}" placeholder="Optional client name" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Mode</span>
                                <select name="accounting_mode" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                                    <option value="owned" @selected(old('accounting_mode', 'owned') === 'owned')>Client</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Default Opening Balance</span>
                                <input type="number" step="0.01" name="default_petty_cash_amount" value="{{ old('default_petty_cash_amount', '0.00') }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                            </label>

                            <div class="lg:col-span-3 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                                <button type="button" id="owned-shop-cancel-modal" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                    Add Client Shop
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-bold text-emerald-800">
                            All shops are already configured for client accounting.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @foreach($shops as $shop)
        <div id="owned-shop-edit-modal-{{ $shop->id }}" class="hidden fixed inset-0 z-[70]" data-owned-shop-edit-modal>
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-owned-shop-edit-close></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-4xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Edit Client Shop</p>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $shop->name }}</h2>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Update client assignment and cash defaults without deleting shop history.</p>
                        </div>
                        <button type="button" data-owned-shop-edit-close class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.accounting.owned-shops.update', $shop) }}" class="grid gap-4 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_220px]">
                        @csrf
                        @method('PATCH')

                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Client</span>
                            <select name="client_id" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                                <option value="">Aishwarya Veg</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((int) $shop->client_id === (int) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">New Client</span>
                            <input type="text" name="client_name" placeholder="Optional client name" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Business Date</span>
                            <input type="date" name="business_date" value="{{ today()->toDateString() }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Reserve Amount</span>
                            <input type="number" step="0.01" min="0" name="reserve_amount" value="{{ number_format((float) $shop->reserve_amount, 2, '.', '') }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                        </label>

                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Default Opening Balance</span>
                            <input type="number" step="0.01" name="default_petty_cash_amount" value="{{ number_format((float) $shop->default_petty_cash_amount, 2, '.', '') }}" class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900">
                        </label>

                        <div class="flex items-end justify-end gap-3 lg:col-span-3">
                            <button type="button" data-owned-shop-edit-close class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

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

        (() => {
            const editButtons = document.querySelectorAll('[data-owned-shop-edit]');
            const editModals = document.querySelectorAll('[data-owned-shop-edit-modal]');

            const hideAll = () => {
                editModals.forEach((modal) => modal.classList.add('hidden'));
                document.body.classList.remove('overflow-hidden');
            };

            editButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    hideAll();
                    document.querySelectorAll('[data-owned-shop-menu-panel]').forEach((panel) => panel.classList.add('hidden'));
                    const modal = document.getElementById(`owned-shop-edit-modal-${button.dataset.ownedShopEdit}`);

                    if (!modal) {
                        return;
                    }

                    modal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                });
            });

            document.querySelectorAll('[data-owned-shop-edit-close]').forEach((button) => {
                button.addEventListener('click', hideAll);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideAll();
                }
            });
        })();

        (() => {
            const menus = document.querySelectorAll('[data-owned-shop-menu]');

            const closeAllMenus = () => {
                menus.forEach((menu) => {
                    menu.querySelector('[data-owned-shop-menu-panel]')?.classList.add('hidden');
                });
            };

            menus.forEach((menu) => {
                const toggle = menu.querySelector('[data-owned-shop-menu-toggle]');
                const panel = menu.querySelector('[data-owned-shop-menu-panel]');

                toggle?.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const wasHidden = panel?.classList.contains('hidden');
                    closeAllMenus();
                    if (wasHidden) {
                        panel?.classList.remove('hidden');
                    }
                });
            });

            document.addEventListener('click', closeAllMenus);
        })();
    </script>
</x-layouts.accounting>
