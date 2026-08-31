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
        {{-- Flash notifications --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-xs">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 shadow-xs">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 shadow-xs">
                {{ session('error') }}
            </div>
        @endif

        @if (session('bulk_finalize_result'))
            @php
                $bulkRes = session('bulk_finalize_result');
            @endphp
            <section class="overflow-hidden rounded-3xl border {{ $bulkRes['failed'] > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-emerald-200 bg-emerald-50/50' }} p-4 sm:p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-black {{ $bulkRes['failed'] > 0 ? 'text-amber-950' : 'text-emerald-950' }}">
                        Finalization Results for {{ \Illuminate\Support\Carbon::parse($bulkRes['date'])->format('d M Y') }}
                    </h2>
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full {{ $bulkRes['failed'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                        {{ $bulkRes['finalized'] }} of {{ $bulkRes['total'] }} Processed
                    </span>
                </div>

                <div class="flex flex-wrap gap-2 text-xs font-black">
                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-white px-3 py-1 text-emerald-700 shadow-2xs">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                        {{ $bulkRes['finalized'] }} Finalized
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1 text-slate-700 shadow-2xs">
                        <span class="inline-block h-2 w-2 rounded-full bg-slate-400"></span>
                        {{ $bulkRes['skipped'] }} Skipped (Already Finalized)
                    </span>
                    @if ($bulkRes['failed'] > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-1 text-rose-700 shadow-2xs">
                            <span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span>
                            {{ $bulkRes['failed'] }} Could Not Finalize
                        </span>
                    @endif
                </div>

                @if (!empty($bulkRes['failures']))
                    <div class="rounded-2xl border border-rose-100 bg-white p-3.5 space-y-2 text-xs">
                        <p class="font-black text-rose-700 uppercase tracking-wider text-[10px]">Unfinalized Bill Issues</p>
                        <ul class="divide-y divide-slate-100 space-y-1">
                            @foreach ($bulkRes['failures'] as $failure)
                                <li class="pt-1.5 first:pt-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 text-slate-700">
                                    <span class="font-mono font-black text-slate-900">{{ $failure['invoice_number'] }} <span class="font-sans font-semibold text-slate-500">({{ $failure['shop_name'] }})</span></span>
                                    <span class="font-semibold text-rose-600">{{ $failure['reason'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        {{-- Page Header and Date Selector --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Shop Bills</h1>
                <p class="mt-0.5 text-xs font-bold text-slate-500">{{ $selectedCarbonDate->format('d M Y') }} · {{ $invoices->total() }} invoices</p>
            </div>

            <form method="GET" action="{{ route('purchasing.shop-invoices.index') }}" class="flex flex-wrap items-center gap-2">
                <a href="{{ route('purchasing.shop-invoices.index', ['date' => $todayDate]) }}" class="h-9 rounded-xl px-3 text-xs font-black leading-9 {{ $selectedDate === $todayDate ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">Today</a>
                <a href="{{ route('purchasing.shop-invoices.index', ['date' => $yesterdayDate]) }}" class="h-9 rounded-xl px-3 text-xs font-black leading-9 {{ $selectedDate === $yesterdayDate ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">Yesterday</a>
                <label class="relative h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black leading-9 text-slate-700 hover:bg-slate-50 cursor-pointer">
                    Date
                    <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
            </form>
        </div>

        {{-- Top Bulk Finalization Action Banner --}}
        <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)]">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-2.5 w-2.5 rounded-full {{ ($eligibleInvoicesCount ?? 0) > 0 ? 'bg-cyan-500 ring-4 ring-cyan-100 animate-pulse' : ($allInvoicesCount > 0 && $finalizedBillsCount === $allInvoicesCount ? 'bg-emerald-500' : 'bg-slate-300') }}"></span>
                        <h2 class="text-base font-black text-slate-900 tracking-tight">
                            Finalize All for {{ $selectedCarbonDate->format('d M Y') }}
                        </h2>
                        @if ($allInvoicesCount > 0 && $finalizedBillsCount === $allInvoicesCount)
                            <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                                Completed
                            </span>
                        @elseif (($eligibleInvoicesCount ?? 0) > 0)
                            <span class="rounded-full bg-cyan-50 border border-cyan-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-cyan-700">
                                {{ $eligibleInvoicesCount }} Ready
                            </span>
                        @endif
                    </div>
                    <p class="text-xs font-semibold text-slate-500">
                        @if ($allInvoicesCount === 0)
                            No shop bills for this date.
                        @elseif ($allInvoicesCount > 0 && $finalizedBillsCount === $allInvoicesCount)
                            All {{ $allInvoicesCount }} bills for {{ $selectedCarbonDate->format('d M Y') }} are finalized and locked.
                        @elseif (($eligibleInvoicesCount ?? 0) > 0)
                            {{ $eligibleInvoicesCount }} of {{ $allInvoicesCount }} shop bill{{ $allInvoicesCount === 1 ? '' : 's' }} eligible to finalize in one action.
                        @else
                            {{ $allInvoicesCount - $finalizedBillsCount }} unfinalized bill{{ ($allInvoicesCount - $finalizedBillsCount) === 1 ? '' : 's' }} pending shop submission or price confirmation.
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if (($eligibleInvoicesCount ?? 0) > 0)
                        <button
                            type="button"
                            id="open-bulk-finalize-btn"
                            data-open-bulk-finalize
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800 transition-colors shadow-sm cursor-pointer"
                        >
                            <svg class="h-4 w-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            Finalize All ({{ $eligibleInvoicesCount }})
                        </button>
                    @elseif ($allInvoicesCount > 0 && $finalizedBillsCount === $allInvoicesCount)
                        <button
                            type="button"
                            disabled
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black text-emerald-700 cursor-not-allowed opacity-90"
                        >
                            <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            All Bills Finalized
                        </button>
                    @else
                        <button
                            type="button"
                            disabled
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-100 px-4 text-xs font-black text-slate-400 cursor-not-allowed"
                        >
                            Finalize All
                        </button>
                    @endif
                </div>
            </div>
        </section>

        {{-- Metrics Summary Grid --}}
        <div class="grid gap-2 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Bills</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $allInvoicesCount }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-rose-500">Pending / Review</p>
                <p class="mt-1 text-lg font-black text-rose-700">{{ $pendingReviewBillsCount }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Finalized Bills</p>
                <p class="mt-1 text-lg font-black text-emerald-700">{{ $finalizedBillsCount }}</p>
            </div>
            <div class="rounded-2xl border border-cyan-100 bg-white p-3 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-cyan-500">Total Finalized Amount</p>
                <p class="mt-1 text-lg font-black text-cyan-700">Rs. {{ number_format((float) $totalFinalizedAmount, 2) }}</p>
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
                                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $statusTone($label) }}">{{ strtoupper($label) }}</span>
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
                                        <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-black text-white hover:bg-slate-800">{{ $label === 'Finalized' ? 'View Bill' : 'Review Bill' }}</a>
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

    {{-- Bulk Finalization Confirmation Modal --}}
    <div id="bulk-finalize-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs" data-bulk-finalize-modal>
        <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-cyan-700 border border-cyan-200">
                        Confirm Bulk Action
                    </span>
                    <h3 class="text-xl font-black text-slate-950">Finalize All for {{ $selectedCarbonDate->format('d M Y') }}</h3>
                </div>
                <button type="button" data-close-bulk-finalize class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 cursor-pointer">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Selected Date</p>
                        <p class="mt-0.5 font-black text-slate-950">{{ $selectedCarbonDate->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Eligible Bills</p>
                        <p class="mt-0.5 font-black text-cyan-700">{{ $eligibleInvoicesCount ?? 0 }} invoice{{ ($eligibleInvoicesCount ?? 0) === 1 ? '' : 's' }}</p>
                    </div>
                </div>
                @if ($finalizedBillsCount > 0)
                    <p class="text-xs font-semibold text-slate-500 border-t border-slate-200/60 pt-2">
                        {{ $finalizedBillsCount }} already finalized bill{{ $finalizedBillsCount === 1 ? '' : 's' }} will be safely skipped.
                    </p>
                @endif
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs font-semibold text-amber-900 flex items-start gap-2.5">
                <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p>
                    Finalized invoices follow standard invoice finalization rules. Inventory movements and accounting entries will be locked and synchronized.
                </p>
            </div>

            <form id="bulk-finalize-form" method="POST" action="{{ route('purchasing.shop-invoices.finalize-all') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <label class="block space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Review Note (Optional)</span>
                    <input
                        type="text"
                        name="review_note"
                        placeholder="e.g. Day finalized by purchaser"
                        maxlength="500"
                        class="w-full rounded-2xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:ring-cyan-500"
                    >
                </label>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 pt-2">
                    <button
                        type="button"
                        data-close-bulk-finalize
                        class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        id="confirm-bulk-finalize-btn"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 text-xs font-black text-white hover:bg-slate-800 transition-colors cursor-pointer"
                    >
                        <span id="bulk-finalize-spinner" class="hidden">
                            <svg class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span id="bulk-finalize-btn-text">Confirm & Finalize All</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.querySelector('[data-bulk-finalize-modal]');
                const openBtn = document.querySelector('[data-open-bulk-finalize]');
                const closeBtns = document.querySelectorAll('[data-close-bulk-finalize]');
                const form = document.getElementById('bulk-finalize-form');
                const submitBtn = document.getElementById('confirm-bulk-finalize-btn');
                const spinner = document.getElementById('bulk-finalize-spinner');
                const btnText = document.getElementById('bulk-finalize-btn-text');

                const openModal = () => {
                    modal?.classList.remove('hidden');
                    modal?.classList.add('flex');
                };

                const closeModal = () => {
                    modal?.classList.add('hidden');
                    modal?.classList.remove('flex');
                };

                openBtn?.addEventListener('click', openModal);
                closeBtns.forEach((btn) => btn.addEventListener('click', closeModal));

                // Close on click outside modal panel
                modal?.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal();
                    }
                });

                // Prevent double submissions
                form?.addEventListener('submit', (e) => {
                    if (submitBtn?.hasAttribute('disabled')) {
                        e.preventDefault();
                        return;
                    }
                    submitBtn?.setAttribute('disabled', 'disabled');
                    submitBtn?.classList.add('opacity-75', 'cursor-not-allowed');
                    spinner?.classList.remove('hidden');
                    if (btnText) {
                        btnText.textContent = 'Finalizing...';
                    }
                });
            });
        </script>
    @endpush
@endsection
