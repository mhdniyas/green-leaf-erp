@extends('purchase-manager.layouts.app')

@section('title', 'Today Purchase Approval')
@section('page_title', 'Today Purchase Approval')
@section('page_description', 'Approve submitted purchaser receipts, review weighted average cost per product, then update proposed shop-category prices for admin approval.')

@section('content')
    <div class="space-y-6">
        <section class="purchase-manager-panel p-5">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-600">Daily Workflow</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Purchase Manager Approval Desk</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Step 1 approves purchased stock. Step 2 updates category price proposals.
                        Admin approval then publishes prices, generates shop invoices, and updates shop-owner finance.
                    </p>
                </div>
                <form method="GET" action="{{ route('purchasing.grns.index') }}" class="flex items-end gap-3">
                    <div>
                        <label for="purchase-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Purchase Date</label>
                        <input
                            id="purchase-date"
                            type="date"
                            name="date"
                            value="{{ $date }}"
                            onchange="this.form.submit()"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 focus:border-cyan-500 focus:outline-none"
                        >
                    </div>
                </form>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800">
                {{ session('success') }}
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
            <article class="purchase-manager-panel p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Submitted Receipts</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $submittedGrns->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Purchaser submissions waiting for your approval.</p>
            </article>
            <article class="purchase-manager-panel p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Approved Today</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $approvedGrns->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Receipts already approved for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}.</p>
            </article>
            <article class="purchase-manager-panel p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Next Business Date</p>
                <p class="mt-3 text-2xl font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($pricingBusinessDate)->format('d M Y') }}</p>
                <p class="mt-2 text-sm text-slate-500">These price proposals feed the admin invoice-publishing step.</p>
            </article>
        </section>

        <section class="purchase-manager-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Step 1</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Approve Submitted Purchases</h3>
                        <p class="mt-1 text-sm text-slate-500">Check who purchased each product, supplier split, and weighted average purchase cost.</p>
                    </div>
                    @if ($submittedGrns->isNotEmpty())
                        <form method="POST" action="{{ route('purchasing.grns.approve-submitted') }}">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date }}">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white transition hover:bg-emerald-700"
                            >
                                Approve All Submitted Purchases
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($submittedProductGroups->isEmpty())
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-black text-slate-900">No submitted purchases waiting.</p>
                    <p class="mt-2 text-sm text-slate-500">Once a purchaser submits the day&apos;s draft purchases, they will appear here for approval.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($submittedProductGroups as $group)
                        <article class="px-5 py-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $group['is_extra'] ? 'bg-amber-100 text-amber-700' : 'bg-cyan-100 text-cyan-700' }}">
                                            {{ $group['is_extra'] ? 'Add-on' : 'Regular' }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">
                                            {{ $group['receipt_count'] }} line{{ $group['receipt_count'] > 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                    <h4 class="mt-3 text-lg font-black text-slate-950">{{ $group['product_name'] }}</h4>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $group['sku'] }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($group['purchasers'] as $purchaser)
                                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-bold text-slate-700">{{ $purchaser }}</span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[420px]">
                                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Qty</p>
                                        <p class="mt-2 text-lg font-black text-slate-950">{{ number_format($group['total_qty'], 2) }} {{ $group['unit'] }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-cyan-50 px-4 py-3">
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Weighted Avg</p>
                                        <p class="mt-2 text-lg font-black text-cyan-900">INR {{ number_format($group['avg_price'], 2) }}</p>
                                    </div>
                                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Suppliers</p>
                                        <p class="mt-2 text-sm font-black text-slate-950">{{ implode(', ', $group['suppliers']) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                                <table class="min-w-[780px] text-left">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">Receipt</th>
                                            <th class="px-4 py-3">Supplier</th>
                                            <th class="px-4 py-3">Purchased By</th>
                                            <th class="px-4 py-3 text-right">Qty</th>
                                            <th class="px-4 py-3 text-right">Unit Price</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-sm">
                                        @foreach ($group['lines'] as $line)
                                            <tr>
                                                <td class="px-4 py-3 font-mono font-bold text-cyan-700">{{ $line['grn_number'] }}</td>
                                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $line['supplier'] }}</td>
                                                <td class="px-4 py-3 text-slate-600">{{ $line['purchaser'] }}</td>
                                                <td class="px-4 py-3 text-right font-bold text-slate-950">{{ number_format($line['received_qty'], 2) }} {{ $line['unit'] }}</td>
                                                <td class="px-4 py-3 text-right font-bold text-slate-950">INR {{ number_format($line['unit_price'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="purchase-manager-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Step 2</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Update Proposed Shop Category Prices</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Purchase manager edits proposed prices for business date {{ \Illuminate\Support\Carbon::parse($pricingBusinessDate)->format('d M Y') }}.
                            Admin approval later publishes these prices and generates one invoice per shop.
                        </p>
                    </div>
                    @if (auth()->user()?->hasRole('admin'))
                        <a href="{{ route('admin.price-approvals.index', ['date' => $pricingBusinessDate]) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">
                            Open Admin Approval
                        </a>
                    @endif
                </div>
            </div>

            @if ($pendingPriceApprovals->isEmpty())
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-black text-slate-900">No pending price proposals yet.</p>
                    <p class="mt-2 text-sm text-slate-500">Approve submitted purchases first. The system will create pending category prices for admin approval.</p>
                </div>
            @else
                <form method="POST" action="{{ route('purchasing.grns.proposed-prices.update') }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                        <table class="min-w-[760px] text-left">
                            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">Product</th>
                                    <th class="px-5 py-4 text-right">Avg Purchase</th>
                                    @foreach ($priceGroups as $group)
                                        <th class="px-5 py-4 text-right">Category {{ $group->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach ($pendingPriceApprovals as $approval)
                                    <tr>
                                        <td class="px-5 py-4">
                                            <p class="font-bold text-slate-950">{{ $approval->product?->name ?? 'Unknown Product' }}</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $approval->product?->sku }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-right font-black text-slate-950">
                                            INR {{ number_format((float) $approval->purchase_price, 2) }}
                                        </td>
                                        <td class="px-5 py-4">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="prices[{{ $approval->id }}][price_a]"
                                                value="{{ old("prices.{$approval->id}.price_a", number_format((float) $approval->price_a, 2, '.', '')) }}"
                                                class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                            >
                                        </td>
                                        <td class="px-5 py-4">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="prices[{{ $approval->id }}][price_b]"
                                                value="{{ old("prices.{$approval->id}.price_b", number_format((float) $approval->price_b, 2, '.', '')) }}"
                                                class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                            >
                                        </td>
                                        <td class="px-5 py-4">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="prices[{{ $approval->id }}][price_c]"
                                                value="{{ old("prices.{$approval->id}.price_c", number_format((float) $approval->price_c, 2, '.', '')) }}"
                                                class="ml-auto block w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-black text-slate-950 focus:border-cyan-500 focus:outline-none"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm font-semibold text-slate-500">Saving here updates only the proposed prices. Admin approval is still required before the prices go live and invoices are generated.</p>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
                            Save Proposed Prices
                        </button>
                    </div>
                </form>
            @endif
        </section>

        <section class="purchase-manager-panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-5">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Receipt History</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">All Receipts For {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</h3>
                <p class="mt-1 text-sm text-slate-500">Detailed GRN links stay available for recheck, invoice matching, and audit follow-up.</p>
            </div>

            @if ($recentReceipts->isEmpty())
                <div class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                    No receipts recorded for this date.
                </div>
            @else
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="min-w-[860px] text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">GRN</th>
                                <th class="px-5 py-4">Supplier</th>
                                <th class="px-5 py-4">Purchased By</th>
                                <th class="px-5 py-4">Approved By</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($recentReceipts as $grn)
                                @php
                                    $tone = $grn->status === 'approved' ? 'emerald' : ($grn->status === 'recheck_required' ? 'amber' : 'slate');
                                @endphp
                                <tr>
                                    <td class="px-5 py-4 font-mono font-bold text-cyan-700">{{ $grn->grn_number }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $grn->purchaseOrder->supplier?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $grn->receivedBy?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $grn->approvedBy?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-center">
                                        <x-purchase-manager.components.status-badge :label="str($grn->status)->replace('_', ' ')->title()" :tone="$tone" />
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <x-purchase-manager.components.action-button :href="route('purchasing.grns.show', $grn)" variant="secondary">Open</x-purchase-manager.components.action-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
