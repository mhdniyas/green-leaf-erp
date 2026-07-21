<x-layouts.auth title="Demo Login">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(22,163,74,0.16),transparent_34%),linear-gradient(180deg,#f7faf7_0%,#eef6ef_52%,#e6f0e8_100%)]">
        <div class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-[2rem] border border-slate-200/80 bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.10)] sm:p-8">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Green Leaf Demo</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Choose a demo account</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            One-click access for local testing. This page is disabled in production.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50"
                    >
                        Normal Login
                    </a>
                </div>

                @if ($errors->any())
                    <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3" role="alert">
                        <p class="text-sm font-bold text-red-800">Demo sign in failed</p>
                        @foreach ($errors->all() as $error)
                            <p class="mt-1 text-xs text-red-700">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 grid gap-3 md:grid-cols-2">
                    @forelse ($demoUsers as $demoUser)
                        <form method="POST" action="{{ route('login.demo', $demoUser) }}">
                            @csrf
                            <button
                                type="submit"
                                class="flex h-full w-full items-center justify-between gap-4 rounded-[1.5rem] border border-emerald-100 bg-emerald-50/70 p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-base font-black text-slate-950">{{ $demoUser->name }}</span>
                                    <span class="mt-1 block truncate text-sm font-semibold text-slate-500">{{ $demoUser->email }}</span>
                                    @if ($demoUser->shop)
                                        <span class="mt-1 block truncate text-xs font-bold text-emerald-700">{{ $demoUser->shop->name }}</span>
                                    @endif
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    @foreach ($demoUser->roles->pluck('name')->take(2) as $roleName)
                                        <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-600">{{ str_replace('_', ' ', $roleName) }}</span>
                                    @endforeach
                                </span>
                            </button>
                        </form>
                    @empty
                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                            <p class="text-sm font-bold text-slate-700">No demo users found.</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Run the role/user seeders to populate demo accounts.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts.auth>
