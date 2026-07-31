<x-layouts.accounting title="Client Payments">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Finance Payments</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Client Payments</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">Shop payments and company payables by client.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.finance-v2.payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">Shop Payments</a>
                        <a href="{{ route('admin.finance-v2.company-payables.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600">Company Payables ({{ $pending_count }})</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($clients as $client)
                <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Client</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $client->name }}</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $client->shops_count }} shops</p>
                    <a href="{{ route('admin.finance-v2.clients.show', ['client' => $client, 'date' => $dateParam]) }}" class="mt-5 inline-flex h-10 items-center rounded-[1rem] border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 hover:bg-slate-50">Open client</a>
                </article>
            @empty
                <div class="rounded-[1.6rem] border border-dashed border-slate-300 bg-white p-10 text-center text-sm font-bold text-slate-500 md:col-span-2 xl:col-span-3">No clients found.</div>
            @endforelse
        </section>
    </div>
</x-layouts.accounting>
