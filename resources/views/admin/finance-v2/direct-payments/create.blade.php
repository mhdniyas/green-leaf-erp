<x-layouts.accounting title="Record Direct Payment">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp
    <div class="mx-auto max-w-3xl space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Direct Payment</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $invoice->invoice_number }}</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">{{ $invoice->supplier?->name ?? 'Supplier' }} · Outstanding Rs. {{ number_format((float) $outstanding, 2) }}</p>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.finance-v2.direct-payments.store', $invoice) }}" class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            @csrf
            <input type="hidden" name="date" value="{{ $dateParam }}">
            <label class="block">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Payment amount</span>
                <input type="number" step="0.01" min="0.01" max="{{ $outstanding }}" name="amount" value="{{ old('amount', $outstanding) }}" required class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
            </label>
            <label class="block">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Payment date</span>
                <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
            </label>
            <label class="block">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Payment method</span>
                <select name="payment_method" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                    <option value="upi">UPI</option>
                    <option value="cheque">Cheque</option>
                    <option value="online">Online</option>
                </select>
            </label>
            <label class="block">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Reference</span>
                <input type="text" name="reference" value="{{ old('reference') }}" class="mt-2 h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
            </label>
            <label class="block">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Notes</span>
                <textarea name="notes" class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900">{{ old('notes') }}</textarea>
            </label>
            <div class="flex gap-3">
                <button class="inline-flex h-11 items-center rounded-2xl bg-orange-500 px-5 text-sm font-black text-white">Record payment</button>
                <a href="{{ route('admin.finance-v2.direct-payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-5 text-sm font-black text-slate-700">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.accounting>
