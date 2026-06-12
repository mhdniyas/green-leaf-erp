@extends('purchase-manager.layouts.app')

@section('title', 'Purchase Manager Daily Desk')
@section('page_title', 'Purchase Manager Daily Desk')
@section('page_description', 'Approve shop orders, control purchaser submissions, finalize daily prices, and clear invoice exceptions from one mobile-first desk.')

@section('page_actions')
    <a href="{{ route('purchasing.grns.index', ['date' => $selectedDate]) }}"
        class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-slate-800">
        Open Receipts
    </a>
    <a href="{{ route('purchasing.shop-invoices.index') }}"
        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
        Open Invoices
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('purchasing.orders.index') }}"
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Business Date</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Daily workflow focus</h2>
                    <p class="mt-1 text-sm text-slate-600">Use one date to review approvals, purchases, prices, and invoice exceptions.</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div>
                        <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Date</label>
                        <input id="date" type="date" name="date" value="{{ $selectedDate }}"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none sm:w-44">
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-black text-white transition hover:bg-cyan-600">
                        Load Desk
                    </button>
                </div>
            </form>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-rose-600">Needs Action</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Clear today&apos;s queue</h2>
                    </div>
                    <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-rose-700">
                        {{ $pendingShopOrders->count() + $pendingRegularPurchases->count() + $pendingAddonPurchases->count() + $grnCorrections->count() + $pendingPriceApprovalsCount + $invoiceExceptions->count() }} open
                    </span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('requisitions.board', ['date' => $selectedDate]) }}"
                        class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Shop Orders</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $pendingShopOrders->count() }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Pending owner approvals and revision checks.</p>
                    </a>

                    <a href="{{ route('purchasing.grns.index', ['date' => $selectedDate]) }}"
                        class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Received Batches</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $pendingRegularPurchases->count() + $pendingAddonPurchases->count() }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Regular and add-on receipts already approved by the receiver.</p>
                    </a>

                    <a href="{{ route('purchasing.prices.index') }}"
                        class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Daily Prices</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $pendingPriceApprovalsCount }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Product prices prepared and waiting for admin approval.</p>
                    </a>

                    <a href="{{ route('purchasing.shop-invoices.index') }}"
                        class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Exceptions</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ $invoiceExceptions->count() }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-600">Shortages, discounts, repricing, and payment follow-up.</p>
                    </a>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Today Summary</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Progress snapshot</h2>

                <div class="mt-5 grid gap-3">
                    <div class="rounded-[1.5rem] bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Orders Approved</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $todaySummary['orders_approved'] }}</p>
                    </div>
                    <div class="rounded-[1.5rem] bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Purchase Batches Approved</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $todaySummary['purchase_batches_approved'] }}</p>
                    </div>
                    <div class="rounded-[1.5rem] bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">GRN Rechecks Open</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $todaySummary['grn_corrections'] }}</p>
                    </div>
                    <div class="rounded-[1.5rem] bg-slate-50 px-4 py-3">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoices Finalized</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $todaySummary['invoices_finalized'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Order Approval</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Pending review</h2>
                    </div>
                    <a href="{{ route('requisitions.board', ['date' => $selectedDate]) }}" class="text-sm font-black text-cyan-700">Open</a>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($pendingShopOrders->take(4) as $order)
                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black text-slate-950">{{ $order->shop?->name ?? 'Unknown Shop' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $order->order_number }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-slate-700">
                                    {{ str($order->state)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-600">{{ $order->items->count() }} items awaiting decision.</p>
                        </div>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                            No shop owner approvals are pending for this date.
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Purchaser Control</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Regular and add-on buys</h2>
                    </div>
                    <a href="{{ route('purchasing.grns.index', ['date' => $selectedDate]) }}" class="text-sm font-black text-cyan-700">Open</a>
                </div>

                <div class="mt-4 grid gap-3">
                    <div class="rounded-[1.5rem] bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Regular Receipts</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $pendingRegularPurchases->count() }}</p>
                    </div>
                    <div class="rounded-[1.5rem] bg-slate-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Add-on Receipts</p>
                        <p class="mt-1 text-2xl font-black text-slate-950">{{ $pendingAddonPurchases->count() }}</p>
                    </div>
                    @if($grnCorrections->isNotEmpty())
                        <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-amber-700">Correction Loop</p>
                            <p class="mt-1 text-2xl font-black text-amber-900">{{ $grnCorrections->count() }}</p>
                            <p class="mt-2 text-sm font-semibold text-amber-800">Admin flagged these GRNs for warehouse recheck and resubmission.</p>
                        </div>
                    @endif
                </div>
            </article>

            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Invoice Exception Queue</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Finance follow-up</h2>
                    </div>
                    <a href="{{ route('purchasing.shop-invoices.index') }}" class="text-sm font-black text-cyan-700">Open</a>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($invoiceExceptions->take(4) as $invoice)
                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black text-slate-950">{{ $invoice->shop?->name ?? 'Unknown Shop' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->invoice_number }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-slate-700">
                                    {{ str($invoice->status)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Shortage</p>
                                    <p class="mt-1 font-black text-amber-700">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Balance</p>
                                    <p class="mt-1 font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                            No invoice exceptions are pending for this date.
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">PO Output</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Generated purchase orders</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-slate-700">
                        {{ $generatedPurchaseOrdersCount }} created
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($recentPurchaseOrders as $order)
                        <a href="{{ route('purchasing.orders.show', $order) }}"
                            class="flex items-start justify-between gap-3 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                            <div>
                                <p class="font-mono text-xs font-black text-cyan-700">{{ $order->po_number }}</p>
                                <p class="mt-1 font-black text-slate-950">{{ $order->supplier?->name ?? 'No supplier' }}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-600">Rs. {{ number_format((float) $order->total_amount, 2) }}</p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $order->status->color() }}">
                                {{ $order->status->label() }}
                            </span>
                        </a>
                    @empty
                        <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                            No purchase orders were generated for this date yet.
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Next Steps</p>
                <h2 class="mt-1 text-lg font-black text-slate-950">Use the right screen for each job</h2>

                <div class="mt-4 space-y-3">
                    <a href="{{ route('requisitions.board', ['date' => $selectedDate]) }}"
                        class="block rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="font-black text-slate-950">1. Approve shop owner orders</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Review submitted orders and revision requests before buying starts.</p>
                    </a>
                    <a href="{{ route('requisitions.approved_board', ['date' => $selectedDate]) }}"
                        class="block rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="font-black text-slate-950">2. Build purchase orders</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Generate or update supplier purchase orders from approved demand.</p>
                    </a>
                    <a href="{{ route('purchasing.prices.index') }}"
                        class="block rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <p class="font-black text-slate-950">3. Finalize prices and release invoices</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Update daily category pricing, then move into invoice review and payment approval.</p>
                    </a>
                </div>
            </article>
        </section>
    </div>
@endsection
