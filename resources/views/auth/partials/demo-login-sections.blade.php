@forelse ($demoUserSections as $section)
    <section class="space-y-2">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{{ $section['title'] }}</h2>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">
                {{ $section['users']->count() }}
            </span>
        </div>

        <div class="grid gap-2">
            @foreach ($section['users'] as $demoUser)
                <form method="POST" action="{{ route('login.demo', $demoUser) }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-center justify-between gap-3 rounded-xl border border-emerald-100 bg-white px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-black text-slate-950">{{ $demoUser->name }}</span>
                            <span class="mt-0.5 block truncate text-xs font-semibold text-slate-500">{{ $demoUser->email }}</span>
                            @if ($demoUser->shop)
                                <span class="mt-0.5 block truncate text-[11px] font-bold text-emerald-700">{{ $demoUser->shop->name }}</span>
                            @endif
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            @foreach ($demoUser->roles->pluck('name')->take(2) as $roleName)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600">{{ str_replace('_', ' ', $roleName) }}</span>
                            @endforeach
                        </span>
                    </button>
                </form>
            @endforeach
        </div>
    </section>
@empty
    <div class="{{ $emptyClass ?? '' }}">
        <p class="text-sm font-bold text-slate-700">No demo users found.</p>
        <p class="mt-2 text-sm leading-6 text-slate-500">Run the role/user seeders to populate demo accounts.</p>
    </div>
@endforelse
