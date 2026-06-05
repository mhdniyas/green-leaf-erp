@if (session('success'))
    <div class="mb-4 rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded-3xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-800">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-3xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
        <p class="font-black uppercase tracking-[0.18em] text-[11px]">Please review the form.</p>
        <p class="mt-1">{{ $errors->first() }}</p>
    </div>
@endif
