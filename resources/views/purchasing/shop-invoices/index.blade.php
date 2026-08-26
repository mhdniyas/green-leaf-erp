@extends('purchase-manager.layouts.app')

@section('title', 'Shop Invoice Review')
@section('page_title', 'Shop Invoice Review')
@section('page_description', 'Day-wise shop bills ready for review and finalization.')

@section('content')
    @php
        $selectedCarbonDate = \Illuminate\Support\Carbon::parse($selectedDate);
        $yesterdayDate = today()->subDay()->toDateString();
        $statusLabel = function ($invoice): string {
            if ($invoice->finalized_at || in_array((string) $invoice->status, ['payment_pending', 'paid'], true)) {
                return ((float) $invoice->discount_total > 0 || (float) $invoice->shortage_total > 0 || (float) $invoice->excess_total > 0)
                    ? 'Adjusted'
                    : 'Finalized';
            }

            if ($invoice->order?->delivery_status === 'pending_approval' || $invoice->delivery_status === 'awaiting_review') {
                return 'Pending Review';
            }

            return 'Ready';
        };
        $statusTone = fn (string $status): string => match ($status) {
            'Finalized' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'Adjusted' => 'bg-amber-50 text-amber-700 border-amber-200',
            'Pending Review' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    @endphp

    <div class="mx-auto max-w-4xl space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Shop Bills</h1>
                <p class="mt-0.5 text-xs font-bold text-slate-500">{{ $selectedCarbonDate->format('d M Y') }} · {{ $invoices->total() }} invoices</p>
            </div>

            <form method="GET" action="{{ route('purchasing.shop-invoices.index') }}" class="flex flex-wrap items-center gap-2">
                <a href="{{ route('purchasing.shop-invoices.index', ['date' => $todayDate]) }}" class="h-9 rounded-xl px-3 text-xs font-black leading-9 {{ $selectedDate === $todayDate ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">Today</a>
                <a href="{{ route('purchasing.shop-invoices.index', ['date' => $yesterdayDate]) }}" class="h-9 rounded-xl px-3 text-xs font-black leading-9 {{ $selectedDate === $yesterdayDate ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">Yesterday</a>
                <label class="relative h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black leading-9 text-slate-700 hover:bg-slate-50">
                    Date
                    <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
            </form>
        </div>

        <div class="grid grid-cols-4 gap-2">
            <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">All</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $allInvoicesCount }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-rose-500">Review</p>
                <p class="mt-1 text-lg font-black text-rose-700">{{ $pendingApprovalCount }}</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-amber-500">Adjusted</p>
                <p class="mt-1 text-lg font-black text-amber-700">{{ $varianceCount }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-cyan-500">Notes</p>
                <p class="mt-1 text-lg font-black text-cyan-700">{{ $shopNotesCount }}</p>
            </div>
        </div>

        @if ($invoices->isEmpty())
            <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                <h2 class="font-black text-slate-900">No shop bills for this date</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Pick another date to review older invoices.</p>
            </section>
        @else
            <div class="space-y-3">
                @foreach ($invoicesByShop as $shopName => $shopInvoices)
                    <section class="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-black text-slate-950">{{ $shopName }}</h2>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $shopInvoices->count() }} bill{{ $shopInvoices->count() === 1 ? '' : 's' }}</p>
                            </div>
                            <p class="font-mono text-sm font-black text-slate-900">Rs. {{ number_format((float) $shopInvoices->sum('final_total'), 2) }}</p>
                        </div>

                        <div class="divide-y divide-slate-100">
                            @foreach ($shopInvoices as $invoice)
                                @php
                                    $label = $statusLabel($invoice);
                                    $originalTotal = round((float) $invoice->items->sum('line_subtotal'), 2);
                                    $difference = round((float) $invoice->final_total - $originalTotal, 2);
                                @endphp
                                <article class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-mono text-sm font-black text-cyan-700">{{ $invoice->invoice_number }}</p>
                                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $statusTone($label) }}">{{ $label }}</span>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">
                                            Original Rs. {{ number_format($originalTotal, 2) }}
                                            @if (abs($difference) > 0.001)
                                                · Change Rs. {{ number_format($difference, 2) }}
                                            @endif
                                            @if ((float) $invoice->discount_total > 0)
                                                · Discount Rs. {{ number_format((float) $invoice->discount_total, 2) }}
                                            @endif
                                        </p>
                                    </div>

                                    <p class="font-mono text-lg font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>

                                    <div class="flex items-center gap-2 sm:justify-end">
                                        <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 hover:bg-slate-50">PDF</a>
                                        <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-black text-white hover:bg-slate-800">Review Bill</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            @if ($invoices->hasPages())
                <div class="rounded-2xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
                    {{ $invoices->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
