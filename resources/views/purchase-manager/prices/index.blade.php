@extends('purchase-manager.layouts.app')

@section('title', 'Price Proposal Board')
@section('page_title', 'Price Proposal Board')
@section('page_description', 'Purchase manager proposes category prices from purchased-product cost. Admin approval publishes prices and generates shop invoices.')

@section('content')
    @php
        $isAdminViewer = auth()->user()?->hasRole('admin');
        $allApprovals = $pendingApprovals->concat($approvedApprovals);
        $movementOptions = [
            'changed' => 'Changed',
            'up' => 'Up',
            'down' => 'Down',
            'all' => 'All',
        ];
        $sortOptions = [
            'code' => 'Code',
            'name' => 'Name',
            'status' => 'Status',
            'movement' => 'Movement',
        ];
    @endphp
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('purchasing.prices.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_190px_170px_320px_auto_auto] xl:items-end">
                <div>
                    <label for="search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Product Search</label>
                    <input
                        id="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search by product, SKU, or category"
                        autocomplete="off"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:outline-none"
                    >
                </div>
                <div>
                    <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Purchase Date</label>
                    <input
                        id="date"
                        type="date"
                        name="date"
                        value="{{ $purchaseDate }}"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 focus:border-cyan-500 focus:outline-none"
                    >
                </div>
                <div>
                    <label for="sort" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Sort By</label>
                    <select
                        id="sort"
                        name="sort"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none"
                    >
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Price Movement</label>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($movementOptions as $value => $label)
                            <button
                                type="submit"
                                name="movement"
                                value="{{ $value }}"
                                class="inline-flex min-h-11 items-center justify-center rounded-2xl border px-3 text-center text-xs font-black transition {{ $movement === $value ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <button type="submit" name="movement" value="{{ $movement }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
                    Search
                </button>
                <button type="button" onclick="openPriceBoardSettingsModal()" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700">
                    Settings
                    @if ($autoApproveSamePurchasePrice)
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Auto</span>
                    @endif
                </button>
            </form>
            <p class="mt-3 text-sm text-slate-500">
                Purchase date {{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y') }} publishes selling proposals for
                {{ \Illuminate\Support\Carbon::parse($targetBusinessDate)->format('d M Y') }}.
            </p>
            <form method="POST" action="{{ route('purchasing.prices.products.store') }}" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                @csrf
                <input type="hidden" name="date" value="{{ $purchaseDate }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="movement" value="{{ $movement }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <div>
                    <label for="product_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Add Inventory Product</label>
                    <select id="product_id" name="product_id" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        <option value="">Select product</option>
                        @foreach ($inventoryProducts as $inventoryProduct)
                            @php($inventoryUnit = strtolower((string) $inventoryProduct->unit) === 'piece' ? 'PCE' : strtoupper((string) $inventoryProduct->unit))
                            <option value="{{ $inventoryProduct->id }}">
                                {{ $inventoryProduct->sku ?: 'NA' }} - {{ $inventoryProduct->name }} ({{ $inventoryUnit }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-cyan-600 px-5 py-3 text-sm font-black text-white hover:bg-cyan-500">
                    Add Product
                </button>
            </form>
        </section>

        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Pending Admin Approval</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $pendingApprovals->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Rows waiting for admin publish, including products not purchased today.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Already Approved</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $approvedApprovals->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $isAdminViewer ? 'Editing any approved row will publish the update immediately.' : 'Editing any approved row will send it back for approval.' }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Trigger</p>
                <p class="mt-3 text-lg font-black text-slate-950">{{ $isAdminViewer ? 'Instant Publish' : 'Admin Publish' }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $isAdminViewer ? 'Saving as admin publishes live prices immediately and reprices shop invoices.' : 'When admin approves, selling prices go live and shop-owner invoices are generated or repriced.' }}</p>
            </article>
        </section>

        <form method="POST" action="{{ route('purchasing.prices.update') }}" class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="movement" value="{{ $movement }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="date" value="{{ $purchaseDate }}">

            <div class="border-b border-slate-200 px-5 py-5">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Proposal Update</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Proposed Shop Category Prices</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $isAdminViewer ? 'As admin, saving here publishes the live selling prices directly.' : 'This page no longer updates live selling prices directly. It only edits admin approval proposals.' }}</p>
                    </div>
                    <div>
                        <label for="reason" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reason</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Purchase cost changed / resubmit for admin" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>
            @if ($allApprovals->isEmpty())
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-black text-slate-900">No products found.</p>
                    <p class="mt-2 text-sm text-slate-500">Inventory products appear here so prices can be updated even when there is no purchaser activity for the selected date.</p>
                </div>
            @else
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="min-w-[980px] text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Product</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-center">Movement</th>
                                <th class="px-5 py-4 text-right">Avg Purchase</th>
                                <th class="px-5 py-4 text-right">Category A</th>
                                <th class="px-5 py-4 text-right">Category B</th>
                                <th class="px-5 py-4 text-right">Category C</th>
                                <th class="px-5 py-4">Admin Check</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($allApprovals as $approval)
                                @php
                                    $product = $approval->product;
                                    $tone = $approval->status === 'approved' ? 'emerald' : 'amber';
                                    $movementClass = match ($approval->movement_status) {
                                        'same' => 'border-sky-200 bg-sky-50 text-sky-700',
                                        'up' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                        'down' => 'border-rose-200 bg-rose-50 text-rose-700',
                                        default => 'border-cyan-200 bg-cyan-50 text-cyan-700',
                                    };
                                    $unitLabel = strtolower((string) ($product?->unit ?? '')) === 'piece' ? 'PCE' : strtoupper($product?->unit ?? 'NA');
                                    $movementLabel = match ($approval->movement_status) {
                                        'same' => 'Same',
                                        'up' => '+ INR '.number_format(abs((float) $approval->purchase_price - (float) $approval->comparison_purchase_price), 2),
                                        'down' => '- INR '.number_format(abs((float) $approval->purchase_price - (float) $approval->comparison_purchase_price), 2),
                                        default => 'Changed',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex min-w-12 justify-center rounded-xl border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-black text-slate-700">
                                                {{ $product?->sku ?: 'NA' }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-950">{{ $product?->name ?? 'Unknown Product' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-400">{{ $unitLabel }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <x-purchase-manager.components.status-badge :label="str($approval->status)->replace('_', ' ')->title()" :tone="$tone" />
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $movementClass }}">
                                            {{ $movementLabel }}
                                        </span>
                                        @if ($approval->comparison_purchase_price !== null)
                                            <p class="mt-1 text-[10px] font-semibold text-slate-400">Prev INR {{ number_format($approval->comparison_purchase_price, 2) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right font-black text-slate-950">
                                        INR {{ number_format((float) $approval->purchase_price, 2) }}
                                        @if (! (bool) $approval->getAttribute('purchased_today'))
                                            <p class="mt-1 text-[10px] font-semibold text-amber-600">No purchase today</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="prices[{{ $approval->id }}][price_a]"
                                            value="{{ old("prices.{$approval->id}.price_a", number_format((float) $approval->price_a, 2, '.', '')) }}"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="prices[{{ $approval->id }}][price_b]"
                                            value="{{ old("prices.{$approval->id}.price_b", number_format((float) $approval->price_b, 2, '.', '')) }}"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                    <td class="px-5 py-4">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="prices[{{ $approval->id }}][price_c]"
                                            value="{{ old("prices.{$approval->id}.price_c", number_format((float) $approval->price_c, 2, '.', '')) }}"
                                            class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                        >
                                    </td>
                                    <td class="px-5 py-4 text-xs font-semibold text-slate-500">
                                        @if ($approval->approved_at)
                                            Approved {{ $approval->approved_at->format('d M, h:i A') }}
                                        @elseif ($isAdminViewer)
                                            Ready for admin publish
                                        @else
                                            Waiting for admin approval
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold text-slate-500">{{ $isAdminViewer ? 'Saving any row publishes live prices immediately and reprices shop-owner finance invoices.' : 'Saving any row submits the proposal back to admin approval. Admin publish updates live prices and shop-owner finance invoices.' }}</p>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
                        {{ $isAdminViewer ? 'Save And Publish' : 'Save And Send To Admin' }}
                    </button>
                </div>
            @endif
        </form>

        <div id="price-board-settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 px-4">
            <div class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Price Board Settings</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Approval controls</h3>
                        <p class="mt-2 text-sm text-slate-600">Same-price products can skip admin review while changed prices keep the publish workflow.</p>
                    </div>
                    <button type="button" onclick="closePriceBoardSettingsModal()" class="rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('purchasing.prices.settings.update') }}" class="mt-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
                    @csrf
                    <input type="hidden" name="date" value="{{ $purchaseDate }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="movement" value="{{ $movement }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">

                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Automatic Approval</p>
                    <h4 class="mt-1 text-sm font-black text-emerald-950">Same purchase price</h4>
                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3">
                        <input type="checkbox" name="auto_approve_same_purchase_price" value="1" @checked($autoApproveSamePurchasePrice) class="mt-1 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                        <span>
                            <span class="block text-sm font-black text-slate-950">Approve automatically</span>
                            <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">Products with the same average purchase price as the previous approved day will be marked approved and keep the previous selling prices.</span>
                        </span>
                    </label>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" onclick="closePriceBoardSettingsModal()" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                            Close
                        </button>
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-emerald-700">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                function openPriceBoardSettingsModal() {
                    const modal = document.getElementById('price-board-settings-modal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    }
                }

                function closePriceBoardSettingsModal() {
                    const modal = document.getElementById('price-board-settings-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }
                }

                document.addEventListener('DOMContentLoaded', () => {
                    if (@json(request()->boolean('settings'))) {
                        openPriceBoardSettingsModal();
                    }
                });
            </script>
        @endpush
    @endonce
@endsection
