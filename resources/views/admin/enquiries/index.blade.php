<x-layouts.admin title="Website Enquiries">
    <section class="space-y-8">
        <div class="rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-300">Administration</p>
            <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Website enquiries</h1>
                    <p class="mt-3 text-sm leading-7 text-slate-300">Review every enquiry submitted from the public website and product marketplace.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Total</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ number_format($stats['total']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Today</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ number_format($stats['today']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Homepage</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ number_format($stats['home']) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Marketplace</p>
                        <p class="mt-2 text-2xl font-black text-white">{{ number_format($stats['products']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('admin.enquiries.index') }}" class="grid gap-4 lg:grid-cols-[1fr_220px_140px]">
                <label class="grid gap-2">
                    <span class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Search</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Name, phone, or message" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white">
                </label>
                <label class="grid gap-2">
                    <span class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Source</span>
                    <select name="source" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white">
                        <option value="">All sources</option>
                        <option value="home" @selected($source === 'home')>Homepage</option>
                        <option value="products" @selected($source === 'products')>Marketplace</option>
                    </select>
                </label>
                <div class="flex items-end gap-3">
                    <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">Filter</button>
                    <a href="{{ route('admin.enquiries.index') }}" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 px-5 text-xs font-black uppercase tracking-[0.18em] text-slate-600 transition hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            @forelse ($enquiries as $enquiry)
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-black text-slate-950">{{ $enquiry->name }}</h2>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">{{ $enquiry->source_page === 'home' ? 'Homepage' : 'Marketplace' }}</span>
                                @if ($enquiry->customer_type)
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-600">{{ $enquiry->customer_type }}</span>
                                @endif
                            </div>
                            <div class="grid gap-3 text-sm font-semibold text-slate-600 sm:grid-cols-2 xl:grid-cols-4">
                                <p><span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Phone</span>{{ $enquiry->phone }}</p>
                                <p><span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Required date</span>{{ $enquiry->required_date?->format('d M Y') ?? 'Not specified' }}</p>
                                <p><span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Received</span>{{ $enquiry->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 rounded-2xl bg-slate-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Enquiry message</p>
                        <p class="mt-3 text-sm leading-7 text-slate-700">{{ $enquiry->message }}</p>
                    </div>
                </article>
            @empty
                <div class="rounded-[1.75rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <p class="text-sm font-semibold text-slate-500">No enquiries found for the current filters.</p>
                </div>
            @endforelse
        </section>

        <div>
            {{ $enquiries->links() }}
        </div>
    </section>
</x-layouts.admin>
