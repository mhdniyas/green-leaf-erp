<x-layouts.accounting title="Owned Shop Accounting">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Owned Shop Accounting</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Eligible shops</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Only owned and partnership shops with accounting enabled appear here.</p>
                </div>
                <a href="{{ route('admin.accounting.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    Back to Dashboard
                </a>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @forelse($shops as $shop)
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xl font-black text-slate-950">{{ $shop->name }}</p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $shop->code }} • {{ $shop->accounting_mode }}</p>
                        </div>
                        <a href="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="inline-flex h-10 items-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 text-xs font-black uppercase tracking-[0.18em] text-cyan-700 transition hover:bg-cyan-100">
                            Open
                        </a>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Owners</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ $shop->ownerships->count() }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Configured</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ $shop->accounting_enabled ? 'Yes' : 'No' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Mode</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ ucfirst($shop->accounting_mode) }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[1.75rem] border border-dashed border-slate-300 px-4 py-12 text-center text-sm font-bold text-slate-500 lg:col-span-2">
                    No accounting-enabled owned or partnership shops were found.
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.accounting>
