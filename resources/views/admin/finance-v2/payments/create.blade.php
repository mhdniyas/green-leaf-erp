<x-layouts.accounting title="Create Shop Payment">
    <div class="mx-auto max-w-4xl space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Admin Entry</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Create shop payment</h2>
            <p class="mt-2 text-sm font-semibold text-slate-600">This creates a pending payment. Approval happens after checking pending bills and expenses.</p>

            <form method="POST" action="{{ route('admin.finance-v2.payments.store') }}" class="mt-6 grid gap-4">
                @csrf
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Shop</span>
                    <select name="shop_id" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                        <option value="">Select shop</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }} @if($shop->client) - {{ $shop->client->name }} @endif</option>
                        @endforeach
                    </select>
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                        <input type="number" step="0.01" min="0.01" name="requested_amount" value="{{ old('requested_amount') }}" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Payment Date</span>
                        <input type="date" name="payment_date" value="{{ old('payment_date', $date->format('Y-m-d')) }}" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Method</span>
                        <select name="payment_method" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                            <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                            <option value="online_upi" @selected(old('payment_method') === 'online_upi')>Bank / UPI</option>
                            <option value="cheque" @selected(old('payment_method') === 'cheque')>Cheque</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Reference / Cheque No</span>
                        <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Cheque Bank</span>
                        <input type="text" name="cheque_bank_name" value="{{ old('cheque_bank_name') }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Cheque Date</span>
                        <input type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 px-3 text-sm font-bold">
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Notes</span>
                    <textarea name="shop_note" rows="3" class="mt-2 w-full rounded-[1rem] border border-slate-200 px-3 py-3 text-sm font-bold">{{ old('shop_note') }}</textarea>
                </label>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.finance-v2.payments.index') }}" class="inline-flex h-11 items-center rounded-[1rem] border border-slate-200 px-5 text-xs font-black uppercase tracking-[0.16em] text-slate-700">Cancel</a>
                    <button class="inline-flex h-11 items-center rounded-[1rem] bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.16em] text-white">Create</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.accounting>
