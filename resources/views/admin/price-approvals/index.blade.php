<x-layouts.app title="Daily Price Approvals">

    <div class="space-y-6">
        {{-- Header & Date Filter --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Admin Controls</p>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 mt-1">Daily Price Approvals</h1>
                    <p class="text-sm text-slate-500 mt-1">Review purchase-manager proposals, publish category selling prices, and generate or reprice shop-owner invoices for the selected business date.</p>
                </div>
                <form method="GET" action="{{ route('admin.price-approvals.index') }}" class="flex items-center gap-3 shrink-0">
                    <div>
                        <label for="date-select" class="block text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 mb-1">Target Business Date</label>
                        <input
                            type="date"
                            id="date-select"
                            name="date"
                            value="{{ $date }}"
                            onchange="this.form.submit()"
                            class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-bold text-indigo-600 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer"
                        >
                    </div>
                </form>
            </div>
        </section>

        {{-- Toast / Session Notifications --}}
        @if(session('success'))
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm font-bold text-emerald-800 shadow-sm flex items-center gap-2">
                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-sm font-bold text-rose-800 shadow-sm space-y-1">
                @foreach($errors->all() as $e)
                    <p>• {{ $e }}</p>
                @endforeach
            </div>
        @endif

        {{-- Approvals Form --}}
        @if($items->isEmpty())
            <div class="rounded-[2rem] border border-slate-200 bg-white p-16 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 border border-emerald-100">
                    <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-black text-slate-900">All Clear!</h3>
                <p class="mt-2 text-sm text-slate-500">No pending price approvals for {{ \Carbon\Carbon::parse($date)->format('d F Y') }}.</p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.price-approvals.approve') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">

                <div class="rounded-[2rem] border border-slate-200 bg-white overflow-hidden shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 px-6 py-5 bg-slate-50/50">
                        <div>
                            <h2 class="text-base font-black text-slate-900">Pending Approvals</h2>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $items->count() }} product prices waiting for review.</p>
                        </div>
                        <button type="submit" name="action" value="approve_all"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-xs font-black text-white hover:bg-indigo-700 transition-colors shadow-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                            </svg>
                            Approve All Proposed Prices
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                    <th class="px-6 py-4">Product Details</th>
                                    <th class="px-6 py-4 text-right">Proposed Cost (Today)</th>
                                    <th class="px-6 py-4 text-center">Previous 2 Days' Cost</th>
                                    <th class="px-6 py-4 text-right">Cost Change (Variance)</th>
                                    <th class="px-6 py-4 text-center">Category A</th>
                                    <th class="px-6 py-4 text-center">Category B</th>
                                    <th class="px-6 py-4 text-center">Category C</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach ($items as $item)
                                    @php
                                        $app = $item['approval'];
                                        $product = $item['product'];
                                        $variance = $item['variance'];
                                        $isVarianceLarge = $variance >= 3.00;
                                    @endphp
                                    <tr class="hover:bg-slate-50/40 transition-colors">
                                        {{-- Product details --}}
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-slate-900">{{ $product->name }}</p>
                                            <p class="text-[10px] font-semibold text-slate-400 mt-0.5">Category: {{ $product->category?->name ?? 'Uncategorized' }} · SKU: {{ $product->sku }}</p>
                                        </td>
                                        
                                        {{-- Proposed Cost --}}
                                        <td class="px-6 py-4 text-right font-mono font-bold text-slate-900">
                                            Rs. {{ number_format((float) $app->purchase_price, 2) }}
                                        </td>

                                        {{-- History --}}
                                        <td class="px-6 py-4 text-center">
                                            @if(empty($item['history']))
                                                <span class="text-xs text-slate-400 font-semibold">—</span>
                                            @else
                                                <div class="flex items-center justify-center gap-2">
                                                    @foreach ($item['history'] as $hist)
                                                        <span class="inline-flex flex-col items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 min-w-[70px]">
                                                            <span class="text-[8px] font-black uppercase text-slate-400 leading-none">{{ \Carbon\Carbon::parse($hist['date'])->format('d M') }}</span>
                                                            <span class="text-xs font-mono font-bold text-slate-700 mt-1 leading-none">Rs. {{ number_format($hist['purchase_price'], 1) }}</span>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Price Difference --}}
                                        <td class="px-6 py-4 text-right">
                                            @if($variance > 0.0001)
                                                @php
                                                    $isIncrease = (float) $app->purchase_price > $item['last_purchase_price'];
                                                @endphp
                                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-black uppercase tracking-wider {{ $isVarianceLarge ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ $isIncrease ? '↑' : '↓' }} Rs. {{ number_format($variance, 2) }}
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-400 font-semibold">No Change</span>
                                            @endif
                                        </td>

                                        {{-- Category Prices (Editable Inputs) --}}
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="price_a[{{ $app->id }}]"
                                                    value="{{ old('price_a.' . $app->id, number_format((float) $app->price_a, 2, '.', '')) }}"
                                                    class="w-20 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-center text-xs font-black text-slate-900 focus:border-indigo-500 focus:outline-none"
                                                >
                                                <span class="text-[9px] font-bold text-slate-400 mt-1">Proposed A</span>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="price_b[{{ $app->id }}]"
                                                    value="{{ old('price_b.' . $app->id, number_format((float) $app->price_b, 2, '.', '')) }}"
                                                    class="w-20 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-center text-xs font-black text-slate-900 focus:border-indigo-500 focus:outline-none"
                                                >
                                                <span class="text-[9px] font-bold text-slate-400 mt-1">Proposed B</span>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4">
                                            <div class="flex flex-col items-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="price_c[{{ $app->id }}]"
                                                    value="{{ old('price_c.' . $app->id, number_format((float) $app->price_c, 2, '.', '')) }}"
                                                    class="w-20 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-center text-xs font-black text-slate-900 focus:border-indigo-500 focus:outline-none"
                                                >
                                                <span class="text-[9px] font-bold text-slate-400 mt-1">Proposed C</span>
                                            </div>
                                        </td>

                                        {{-- Row Action --}}
                                        <td class="px-6 py-4 text-right">
                                            <button type="submit" name="approvals[]" value="{{ $app->id }}"
                                                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-black text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                                Approve
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        @endif

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-900">Approved Rows</h2>
                    <p class="mt-1 text-xs text-slate-500">Current approvals for {{ \Carbon\Carbon::parse($date)->format('d F Y') }}. If purchase manager edits a row again, it returns to pending review.</p>
                </div>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-black text-emerald-700">{{ $approvedApprovals->count() }} approved</span>
            </div>

            @if($approvedApprovals->isEmpty())
                <p class="mt-5 text-sm font-semibold text-slate-500">No approvals published yet for this date.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3 text-right">Purchase</th>
                                <th class="px-4 py-3 text-right">A</th>
                                <th class="px-4 py-3 text-right">B</th>
                                <th class="px-4 py-3 text-right">C</th>
                                <th class="px-4 py-3">Approved By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($approvedApprovals as $approval)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-bold text-slate-900">{{ $approval->product?->name ?? 'Unknown Product' }}</p>
                                        <p class="mt-1 text-[10px] font-semibold text-slate-400">{{ $approval->product?->sku }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">Rs. {{ number_format((float) $approval->purchase_price, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900">Rs. {{ number_format((float) $approval->price_a, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900">Rs. {{ number_format((float) $approval->price_b, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-900">Rs. {{ number_format((float) $approval->price_c, 2) }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-slate-500">
                                        {{ $approval->approvedBy?->name ?? '—' }}
                                        @if($approval->approved_at)
                                            <span class="block text-[10px] text-slate-400">{{ $approval->approved_at->format('d M, h:i A') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="text-base font-black text-slate-900">Publish History</h2>
                <p class="mt-1 text-xs text-slate-500">Recent publish history by product and shop category. This helps admin verify previous approvals before re-approving updated proposals.</p>
            </div>

            @if($revisionHistory->isEmpty())
                <p class="mt-5 text-sm font-semibold text-slate-500">No publish history available yet.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3 text-right">Old</th>
                                <th class="px-4 py-3 text-right">New</th>
                                <th class="px-4 py-3">Changed By</th>
                                <th class="px-4 py-3">Changed At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($revisionHistory as $revision)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-bold text-slate-900">{{ $revision->product?->name ?? 'Unknown Product' }}</p>
                                        <p class="mt-1 text-[10px] font-semibold text-slate-400">{{ $revision->product?->sku }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $revision->shopPriceGroup?->display_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-500">{{ $revision->old_price !== null ? 'Rs. '.number_format((float) $revision->old_price, 2) : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">Rs. {{ number_format((float) $revision->new_price, 2) }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-slate-500">{{ $revision->changedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-slate-500">{{ $revision->changed_at?->format('d M, h:i A') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

</x-layouts.app>
