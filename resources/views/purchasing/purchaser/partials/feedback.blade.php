@if (session('success'))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm font-bold text-emerald-800 lg:px-4">
        <p>{{ session('success') }}</p>
        @if (session('cart_success_actions'))
            @php
                $cartSuccessDate = session('cart_success_date', request('date'));
            @endphp
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('purchaser.vendors', ['date' => $cartSuccessDate]) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-emerald-700 px-3 text-[11px] font-black uppercase text-white transition hover:bg-emerald-600">
                    View Cart
                </a>
                <a href="{{ route('purchaser.bulk-buy', ['date' => $cartSuccessDate]) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-emerald-200 bg-white px-3 text-[11px] font-black uppercase text-emerald-700 transition hover:bg-emerald-100">
                    Continue Ordering
                </a>
            </div>
        @endif
    </div>
@endif

@if (session('error'))
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm font-bold text-amber-800 lg:px-4">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-3 text-sm font-bold text-rose-800 lg:px-4">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
