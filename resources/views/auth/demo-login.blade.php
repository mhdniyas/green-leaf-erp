<x-layouts.auth title="Demo Login">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(15,118,110,0.14),transparent_32%),linear-gradient(180deg,#f6faf8_0%,#edf5ef_48%,#e4eee8_100%)]">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <section class="rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:p-7 lg:p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl space-y-3">
                        <div class="inline-flex items-center gap-3 rounded-full border border-teal-200 bg-teal-50 px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] text-teal-700">
                            <span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>
                            Demo Login
                        </div>
                        <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">
                            One-click access for all demo accounts.
                        </h1>
                        <p class="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                            This page is intentionally hidden from the standard login screen. Use any card below to sign in directly for demo testing.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Accounts listed: {{ count($staffAccounts) + count($shopAccounts) }}
                    </div>
                </div>

                <div class="mt-8 space-y-8">
                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Staff Accounts</p>
                                <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Operations and management logins</h2>
                            </div>
                            <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-500">
                                {{ count($staffAccounts) }} accounts
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($staffAccounts as $account)
                                <article class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-black text-slate-950">{{ $account['name'] }}</h3>
                                            <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-teal-700">{{ $account['role'] }}</p>
                                        </div>
                                    </div>

                                    <dl class="mt-4 space-y-2 text-sm text-slate-600">
                                        <div>
                                            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Email</dt>
                                            <dd class="mt-1 break-all font-semibold text-slate-800">{{ $account['email'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Password</dt>
                                            <dd class="mt-1 font-semibold text-slate-800">{{ $account['password'] }}</dd>
                                        </div>
                                    </dl>

                                    <form method="POST" action="{{ route('login.submit') }}" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="email" value="{{ $account['email'] }}">
                                        <input type="hidden" name="password" value="{{ $account['password'] }}">
                                        <button
                                            type="submit"
                                            class="flex w-full items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900/20 focus:ring-offset-2"
                                        >
                                            Login as {{ $account['name'] }}
                                        </button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Accounts</p>
                                <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">All shop-owner demo logins</h2>
                            </div>
                            <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-500">
                                {{ count($shopAccounts) }} accounts
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($shopAccounts as $account)
                                <article class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50/50 p-4 shadow-[0_12px_30px_rgba(16,185,129,0.08)]">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-black text-slate-950">{{ $account['name'] }}</h3>
                                            <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">{{ $account['code'] }}</p>
                                        </div>
                                    </div>

                                    <dl class="mt-4 space-y-2 text-sm text-slate-600">
                                        <div>
                                            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Email</dt>
                                            <dd class="mt-1 break-all font-semibold text-slate-800">{{ $account['email'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Password</dt>
                                            <dd class="mt-1 font-semibold text-slate-800">{{ $account['password'] }}</dd>
                                        </div>
                                    </dl>

                                    <form method="POST" action="{{ route('login.submit') }}" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="email" value="{{ $account['email'] }}">
                                        <input type="hidden" name="password" value="{{ $account['password'] }}">
                                        <button
                                            type="submit"
                                            class="flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 focus:ring-offset-2"
                                        >
                                            Login as {{ $account['name'] }}
                                        </button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </div>
</x-layouts.auth>
