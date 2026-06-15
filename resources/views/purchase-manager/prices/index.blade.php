@extends('purchase-manager.layouts.app')

@section('title', 'Price Proposal Board')
@section('page_title', 'Price Proposal Board')
@section('page_description', 'Purchase manager proposes category prices from purchased-product cost. Admin approval publishes prices and generates shop invoices.')

@section('content')
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('purchasing.prices.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto] lg:items-end">
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
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
                    Search
                </button>
            </form>
            <p class="mt-3 text-sm text-slate-500">
                Purchase date {{ \Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y') }} feeds selling proposals for
                {{ \Illuminate\Support\Carbon::parse($targetBusinessDate)->format('d M Y') }}.
            </p>
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
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Pending Admin Approval</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $pendingApprovals->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Rows waiting for admin publish.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Already Approved</p>
                <p class="mt-3 text-3xl font-black text-slate-950">{{ $approvedApprovals->count() }}</p>
                <p class="mt-2 text-sm text-slate-500">Editing any approved row will send it back for approval.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Trigger</p>
                <p class="mt-3 text-lg font-black text-slate-950">Admin Publish</p>
                <p class="mt-2 text-sm text-slate-500">When admin approves, selling prices go live and shop-owner invoices are generated or repriced.</p>
            </article>
        </section>

        <form method="POST" action="{{ route('purchasing.prices.update') }}" class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="date" value="{{ $purchaseDate }}">

            <div class="border-b border-slate-200 px-5 py-5">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Proposal Update</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Proposed Shop Category Prices</h2>
                        <p class="mt-1 text-sm text-slate-500">This page no longer updates live selling prices directly. It only edits admin approval proposals.</p>
                    </div>
                    <div>
                        <label for="reason" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reason</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Purchase cost changed / resubmit for admin" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    </div>
                </div>
            </div>

            @php
                $allApprovals = $pendingApprovals->concat($approvedApprovals);
            @endphp

            @if ($allApprovals->isEmpty())
                <div class="px-5 py-12 text-center">
                    <p class="text-base font-black text-slate-900">No purchased products found.</p>
                    <p class="mt-2 text-sm text-slate-500">Once products are purchased for the selected date, proposals will appear here automatically.</p>
                </div>
            @else
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="min-w-[980px] text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Product</th>
                                <th class="px-5 py-4 text-center">Status</th>
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
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-slate-950">{{ $product?->name ?? 'Unknown Product' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-400">{{ $product?->sku }} · {{ strtoupper($product?->unit ?? 'NA') }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <x-purchase-manager.components.status-badge :label="str($approval->status)->replace('_', ' ')->title()" :tone="$tone" />
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
                                    <td class="px-5 py-4 text-xs font-semibold text-slate-500">
                                        @if ($approval->approved_at)
                                            Approved {{ $approval->approved_at->format('d M, h:i A') }}
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
                    <p class="text-sm font-semibold text-slate-500">Saving any row submits the proposal back to admin approval. Admin publish updates live prices and shop-owner finance invoices.</p>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
                        Save And Send To Admin
                    </button>
                </div>
            @endif
        </form>
    </div>
@endsection
