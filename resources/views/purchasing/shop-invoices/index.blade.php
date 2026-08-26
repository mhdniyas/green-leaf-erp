@extends('purchase-manager.layouts.app')

@section('title', 'Shop Daily Invoices')
@section('page_title', 'Shop Daily Invoices')
@section('page_description', 'Approve shop delivery reviews, scan notes, and track invoice balances.')

@section('content')
    @php
        $switchDate = $selectedDate ?? $todayDate;
        $switchCarbonDate = \Illuminate\Support\Carbon::parse($switchDate);
        $prevDate = $switchCarbonDate->copy()->subDay()->toDateString();
        $nextDate = $switchCarbonDate->copy()->addDay()->toDateString();
        $tabs = [
            'needs-approval' => ['label' => 'Needs Approval', 'count' => $pendingApprovalCount, 'tone' => 'amber'],
            'shop-notes' => ['label' => 'Shop Notes', 'count' => $shopNotesCount, 'tone' => 'cyan'],
            'variance' => ['label' => 'Short / Excess', 'count' => $varianceCount, 'tone' => 'rose'],
            'payment-pending' => ['label' => 'Payment Pending', 'count' => $paymentPendingCount, 'tone' => 'indigo'],
            'all' => ['label' => 'All', 'count' => $allInvoicesCount, 'tone' => 'slate'],
        ];
        $statusLabel = function ($invoice): string {
            if ($invoice->finalized_at || in_array((string) $invoice->status, ['payment_pending', 'paid'], true)) {
                return ((float) $invoice->discount_total > 0 || (float) $invoice->shortage_total > 0 || (float) $invoice->excess_total > 0)
                    ? 'Adjusted by Admin'
                    : 'Finalized';
            }

            if ($invoice->order?->delivery_status === 'pending_approval') {
                return 'Needs Admin Review';
            }

            if ($invoice->delivery_status === 'awaiting_review') {
                return 'Shop Submitted';
            }

            return 'Awaiting Shop Check';
        };
        $deliveryLabel = function ($invoice): string {
            if ($invoice->order?->shop_checked_at) {
                return 'Shop Submitted';
            }

            if ($invoice->order?->delivery_status === 'in_transit') {
                return 'Awaiting Shop Check';
            }

            return $statusLabel($invoice);
        };
    @endphp

    <div class="space-y-5">
        <section class="rounded-3xl border border-amber-200 bg-amber-50 shadow-sm">
            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => 'needs-approval', 'date' => $selectedDate]) }}" class="rounded-2xl border border-amber-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Pending Approval</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $pendingApprovalCount }}</p>
                </a>
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => 'shop-notes', 'date' => $selectedDate]) }}" class="rounded-2xl border border-cyan-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">With Shop Notes</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $shopNotesCount }}</p>
                </a>
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => 'variance', 'date' => $selectedDate]) }}" class="rounded-2xl border border-rose-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Short / Excess</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $varianceCount }}</p>
                </a>
                <a href="{{ route('purchasing.shop-invoices.index', ['tab' => 'payment-pending', 'date' => $selectedDate]) }}" class="rounded-2xl border border-indigo-200 bg-white p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Balance Pending</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $paymentPendingCount }}</p>
                </a>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Purchasing</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">Shop Invoice Queue</h1>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ $switchCarbonDate->format('d M Y') }} · {{ $invoices->total() }} invoices in review workspace</p>
                </div>
                <form method="GET" action="{{ route('purchasing.shop-invoices.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <a href="{{ route('purchasing.shop-invoices.index', ['tab' => $tab, 'date' => $prevDate]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Prev</a>
                    <input type="date" name="date" value="{{ $switchDate }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-900">
                    <a href="{{ route('purchasing.shop-invoices.index', ['tab' => $tab, 'date' => $todayDate]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 hover:bg-emerald-50">Today</a>
                    <a href="{{ route('purchasing.shop-invoices.index', ['tab' => $tab, 'date' => $nextDate]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Next</a>
                </form>
            </div>

            <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                <div class="grid gap-2 rounded-2xl bg-slate-100 p-1.5 sm:grid-cols-5">
                    @foreach ($tabs as $tabKey => $tabMeta)
                        <a
                            href="{{ route('purchasing.shop-invoices.index', ['tab' => $tabKey, 'date' => $selectedDate]) }}"
                            class="{{ $tab === $tabKey ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800' }} rounded-xl px-3 py-3 text-center text-xs font-black uppercase tracking-[0.14em] transition"
                        >
                            {{ $tabMeta['label'] }}
                            <span class="mt-1 block text-[11px]">{{ $tabMeta['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($invoices->isEmpty())
                <div class="px-5 py-16 text-center text-sm font-semibold text-slate-500">
                    No invoices match this queue.
                </div>
            @else
                <div class="space-y-3 p-4 md:hidden">
                    @foreach ($invoices as $invoice)
                        @php
                            $needsApproval = (bool) $invoice->order?->hasPendingDeliveryReview();
                            $shopNotes = $invoice->items
                                ->map(fn ($item) => trim((string) $item->orderItem?->shop_verification_note))
                                ->filter()
                                ->unique()
                                ->values();
                            $reportedShort = (float) $invoice->items->sum(fn ($item) => (float) ($item->orderItem?->shop_reported_missing_qty ?? 0));
                            $reportedExcess = (float) $invoice->items->sum(fn ($item) => (float) ($item->orderItem?->shop_reported_excess_qty ?? 0));
                            $actionLabel = $needsApproval ? 'Review & Approve' : 'Open Invoice';
                        @endphp
                        <article class="{{ $needsApproval ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50' }} rounded-2xl border p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-black text-cyan-700">{{ $invoice->invoice_number }}</p>
                                    <h2 class="mt-1 text-base font-black text-slate-950">{{ $invoice->shop?->name }}</h2>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->business_date->format('d M Y') }}</p>
                                </div>
                                @if ($needsApproval)
                                    <span class="rounded-full bg-amber-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-amber-900">Needs Approval</span>
                                @endif
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Final</p>
                                    <p class="mt-1 font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                                    <p class="mt-1 font-black {{ (float) $invoice->balance_amount > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-white px-2.5 py-1 font-black text-slate-700">{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</span>
                                @if ($reportedShort > 0)
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 font-black text-amber-800">Short {{ number_format($reportedShort, 2) }}</span>
                                @endif
                                @if ($reportedExcess > 0)
                                    <span class="rounded-full bg-cyan-100 px-2.5 py-1 font-black text-cyan-800">Excess {{ number_format($reportedExcess, 2) }}</span>
                                @endif
                            </div>
                            @if ($shopNotes->isNotEmpty())
                                <p class="mt-3 rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs font-semibold text-amber-900">
                                    {{ $shopNotes->count() }} note{{ $shopNotes->count() === 1 ? '' : 's' }}: {{ $shopNotes->take(2)->implode(' · ') }}
                                </p>
                            @endif
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">PDF</a>
                                <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="{{ $needsApproval ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-slate-950 text-white hover:bg-slate-800' }} rounded-xl px-4 py-2 text-xs font-black uppercase tracking-[0.14em]">
                                    {{ $actionLabel }}
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Invoice</th>
                                <th class="px-5 py-4">Shop</th>
                                <th class="px-5 py-4">Business Date</th>
                                <th class="px-5 py-4 text-right">Original Amount</th>
                                <th class="px-5 py-4 text-right">Adjusted Amount</th>
                                <th class="px-5 py-4">Difference</th>
                                <th class="px-5 py-4">Delivery Status</th>
                                <th class="px-5 py-4">Invoice Status</th>
                                <th class="px-5 py-4">Last Action</th>
                                <th class="px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($invoices as $invoice)
                                @php
                                    $needsApproval = (bool) $invoice->order?->hasPendingDeliveryReview();
                                    $shopNotes = $invoice->items
                                        ->map(fn ($item) => trim((string) $item->orderItem?->shop_verification_note))
                                        ->filter()
                                        ->unique()
                                        ->values();
                                    $reportedShort = (float) $invoice->items->sum(fn ($item) => (float) ($item->orderItem?->shop_reported_missing_qty ?? 0));
                                    $reportedExcess = (float) $invoice->items->sum(fn ($item) => (float) ($item->orderItem?->shop_reported_excess_qty ?? 0));
                                    $actionLabel = $needsApproval ? 'Review & Approve' : 'Open';
                                    $originalAmount = round((float) $invoice->items->sum('line_subtotal'), 2);
                                    $adjustedAmount = round((float) $invoice->final_total, 2);
                                    $difference = round($adjustedAmount - $originalAmount, 2);
                                    $lastAction = $invoice->finalized_at
                                        ? 'Finalized by '.($invoice->finalizedBy?->name ?? 'Admin')
                                        : ($invoice->order?->admin_reviewed_at
                                            ? 'Reviewed by '.($invoice->order->adminReviewedBy?->name ?? 'Admin')
                                            : ($invoice->order?->shop_checked_at ? 'Submitted by shop' : 'Generated'));
                                @endphp
                                <tr class="{{ $needsApproval ? 'bg-amber-50/60' : 'hover:bg-slate-50/70' }}">
                                    <td class="px-5 py-4">
                                        <p class="font-mono font-black text-cyan-700">{{ $invoice->invoice_number }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->business_date->format('d M Y') }}</p>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-950">{{ $invoice->shop?->name }}</td>
                                    <td class="px-5 py-4 text-xs font-bold text-slate-600">{{ $invoice->business_date->format('d M Y') }}</td>
                                    <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format($originalAmount, 2) }}</td>
                                    <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format($adjustedAmount, 2) }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-1.5 text-xs font-black">
                                            <span class="{{ $difference < 0 ? 'text-rose-700' : ($difference > 0 ? 'text-cyan-700' : 'text-slate-500') }}">Rs. {{ number_format($difference, 2) }}</span>
                                            <span class="{{ ($reportedShort + $reportedExcess) > 0 ? 'text-amber-700' : 'text-slate-400' }}">Qty {{ number_format($reportedShort + $reportedExcess, 2) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-xs font-bold text-slate-600">{{ $deliveryLabel($invoice) }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">{{ $statusLabel($invoice) }}</span></td>
                                    <td class="px-5 py-4 text-xs font-semibold text-slate-600">{{ $lastAction }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="font-black uppercase tracking-[0.14em] text-slate-500 hover:text-slate-700">PDF</a>
                                            <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="{{ $needsApproval ? 'rounded-xl bg-amber-600 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-white hover:bg-amber-700' : 'font-bold text-cyan-700 hover:text-cyan-900' }}">
                                                {{ $actionLabel }}
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($invoices->hasPages())
                    <div class="border-t border-slate-100 px-5 py-4">
                        {{ $invoices->withQueryString()->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
