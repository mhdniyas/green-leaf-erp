<x-layouts.accounting title="Finance V2 Clients">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Finance V2</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">Clients</h1>
                <p class="mt-2 text-sm font-semibold text-slate-300">All active clients and finance-enabled shops.</p>
                @if(!empty($company_position['net_client_position_message']))
                    <p class="mt-3 text-sm font-black text-emerald-300">{{ $company_position['net_client_position_message'] }}</p>
                @endif
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($client_summaries as $row)
                @php
                    $client = $row['client'];
                    $summary = $row['summary'];
                @endphp
                <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Client</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $client->name }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ number_format((int) $summary['shop_count']) }} shops</p>
                        </div>
                        <a href="{{ route('admin.finance-v2.clients.show', ['client' => $client, 'date' => $dateParam]) }}" class="inline-flex h-10 items-center rounded-[1rem] border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 hover:bg-slate-50">Open</a>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Receivable</p>
                            <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format((float) $summary['balance'], 2) }}</p>
                        </div>
                        <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Received</p>
                            <p class="mt-1 text-lg font-black text-emerald-700">Rs. {{ number_format((float) $summary['received'], 2) }}</p>
                        </div>
                        <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Bills</p>
                            <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format((float) $summary['bills'], 2) }}</p>
                        </div>
                        <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Credit</p>
                            <p class="mt-1 text-lg font-black text-cyan-700">Rs. {{ number_format((float) $summary['credit'], 2) }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[1.6rem] border border-dashed border-slate-300 bg-white p-10 text-center text-sm font-bold text-slate-500 md:col-span-2 xl:col-span-3">
                    No active clients found.
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.accounting>
