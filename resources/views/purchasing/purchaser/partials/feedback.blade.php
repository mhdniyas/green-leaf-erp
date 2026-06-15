@if (session('success'))
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm font-bold text-emerald-800 lg:px-4">
        {{ session('success') }}
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
