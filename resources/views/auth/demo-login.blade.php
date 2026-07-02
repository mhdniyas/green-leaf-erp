<x-layouts.auth title="Demo Login">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(15,118,110,0.14),transparent_32%),linear-gradient(180deg,#f6faf8_0%,#edf5ef_48%,#e4eee8_100%)]">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <section class="rounded-[2rem] border border-white/70 bg-white/80 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:p-7 lg:p-8">
                @if (! $hasDemoAccess)
                    <div class="mx-auto max-w-xl space-y-6">
                        <div class="space-y-3 text-center">
                            <div class="inline-flex items-center gap-3 rounded-full border border-teal-200 bg-teal-50 px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] text-teal-700">
                                <span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>
                                Demo Access
                            </div>
                            <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                                Protected testing logins
                            </h1>
                            <p class="text-sm leading-7 text-slate-600 sm:text-base">
                                Enter the page password to open the one-click testing accounts. Credentials are not displayed after access is granted.
                            </p>
                        </div>

                        @if ($errors->has('page_password'))
                            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {{ $errors->first('page_password') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.demo.unlock') }}" class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-slate-50/80 p-5 sm:p-6">
                            @csrf
                            <div class="space-y-1.5">
                                <label for="page_password" class="block text-sm font-medium text-slate-700">Page password</label>
                                <input
                                    id="page_password"
                                    name="page_password"
                                    type="password"
                                    required
                                    autofocus
                                    class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                                >
                            </div>

                            <button
                                type="submit"
                                class="flex w-full items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-black text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900/20 focus:ring-offset-2"
                            >
                                Open demo page
                            </button>
                        </form>
                    </div>
                @else
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl space-y-3">
                            <div class="inline-flex items-center gap-3 rounded-full border border-teal-200 bg-teal-50 px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] text-teal-700">
                                <span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>
                                Demo Login
                            </div>
                            <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">
                                One-click staff access for testing.
                            </h1>
                            <p class="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                                Staff accounts and shop-owner demo sign-ins are available here for fast testing. Shop demo users are created only when you use them.
                            </p>
                        </div>

                        <a
                            href="{{ route('shop-owner.register') }}"
                            class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-800 transition hover:border-emerald-300 hover:bg-emerald-100"
                        >
                            Open shop-owner registration
                        </a>
                    </div>

                    <div class="mt-8 space-y-4">
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
                                    <div>
                                        <h3 class="text-base font-black text-slate-950">{{ $account['name'] }}</h3>
                                        <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-teal-700">{{ $account['role'] }}</p>
                                    </div>

                                    <p class="mt-4 break-all text-sm font-semibold text-slate-700">{{ $account['email'] }}</p>

                                    <form method="POST" action="{{ route('login.demo.account') }}" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="account" value="{{ $account['key'] }}">
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
                    </div>

                    <div class="mt-8 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Accounts</p>
                                <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Shop-owner delivery check logins</h2>
                            </div>
                            <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-500">
                                {{ count($shopAccounts) }} shops
                            </div>
                        </div>

                        @if (empty($shopAccounts))
                            <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 px-5 py-6 text-sm font-semibold text-slate-500">
                                No active shops are available for demo sign-in yet.
                            </div>
                        @else
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($shopAccounts as $account)
                                    <article class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50/40 p-4 shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
                                        <div>
                                            <h3 class="text-base font-black text-slate-950">{{ $account['name'] }}</h3>
                                            <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">{{ $account['role'] }} · {{ $account['shop_code'] }}</p>
                                        </div>

                                        <p class="mt-4 break-all text-sm font-semibold text-slate-700">{{ $account['email'] }}</p>

                                        <form method="POST" action="{{ route('login.demo.account') }}" class="mt-4">
                                            @csrf
                                            <input type="hidden" name="account" value="{{ $account['key'] }}">
                                            <button
                                                type="submit"
                                                class="flex w-full items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:ring-offset-2"
                                            >
                                                Login as {{ $account['name'] }}
                                            </button>
                                        </form>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.auth>
