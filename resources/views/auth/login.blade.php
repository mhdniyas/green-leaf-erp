<x-layouts.auth title="Sign In">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(22,163,74,0.16),transparent_34%),linear-gradient(180deg,#f7faf7_0%,#eef6ef_52%,#e6f0e8_100%)]">
        <div class="mx-auto grid min-h-screen w-full max-w-7xl gap-8 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(340px,480px)] lg:items-center lg:px-8 lg:py-10">
            <section class="order-2 rounded-[2rem] border border-white/70 bg-white/70 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:p-8 lg:order-1 lg:p-10">
                <div class="inline-flex items-center gap-3 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    Green Leaf Traders
                </div>

                <div class="mt-6 max-w-2xl space-y-4">
                    <h1 class="text-4xl font-black tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                        Fresh distribution, one secure sign-in.
                    </h1>
                    <p class="max-w-xl text-sm leading-7 text-slate-600 sm:text-base">
                        Access purchasing, warehouse, accounts, and shop operations from one place. The login screen is optimized for fast use on both phone and desktop without exposing any testing credentials.
                    </p>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article class="rounded-[1.5rem] border border-emerald-100 bg-emerald-50/80 p-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Shop Workflows</p>
                        <p class="mt-3 text-sm leading-6 text-emerald-950">
                            Submit requisitions, track approvals, confirm deliveries, and review finance updates from any screen size.
                        </p>
                    </article>

                    <article class="rounded-[1.5rem] border border-slate-200 bg-white/90 p-5">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Operations</p>
                        <p class="mt-3 text-sm leading-6 text-slate-700">
                            Purchasing and warehouse teams can move from review to receiving with the same account flow used in production.
                        </p>
                    </article>

                    <article class="rounded-[1.5rem] border border-amber-200 bg-amber-50/80 p-5 sm:col-span-2 xl:col-span-1">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Mobile Ready</p>
                        <p class="mt-3 text-sm leading-6 text-amber-950">
                            Large touch targets, compact spacing, and a single-column form keep sign-in usable on smaller devices.
                        </p>
                    </article>
                </div>
            </section>

            <aside class="order-1 lg:order-2">
                <div class="rounded-[2rem] border border-slate-200/80 bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.10)] sm:p-7">
                    <div class="space-y-2">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Secure Access</p>
                        <h2 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Sign in to continue</h2>
                        <p class="text-sm leading-6 text-slate-500">
                            Enter your assigned email address and password to open your workspace.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mt-5 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3" role="alert">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            <div>
                                <p class="text-sm font-bold text-red-800">Sign in failed</p>
                                @foreach ($errors->all() as $error)
                                    <p class="mt-1 text-xs text-red-700">{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="mt-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3">
                            <p class="text-sm text-green-800">{{ session('status') }}</p>
                        </div>
                    @endif

                    <form id="login-form" method="POST" action="{{ route('login.submit') }}" class="mt-6 space-y-5" novalidate>
                        @csrf

                        <div class="space-y-1.5">
                            <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                </div>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    value="{{ old('email') }}"
                                    placeholder="you@greenleaf.com"
                                    class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pl-10 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('email') border-red-300 bg-red-50 focus:border-red-500 focus:ring-red-500/20 @enderror"
                                >
                            </div>
                            @error('email')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </div>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="••••••••"
                                    class="block w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pr-12 pl-10 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('password') border-red-300 bg-red-50 @enderror"
                                >
                                <button
                                    type="button"
                                    id="toggle-password"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 transition hover:text-slate-600"
                                    aria-label="Toggle password visibility"
                                >
                                    <svg id="eye-open" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg id="eye-closed" class="hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label class="flex items-center gap-2.5 text-sm text-slate-600">
                                <input
                                    id="remember"
                                    name="remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                                >
                                <span>Remember me</span>
                            </label>

                            <a
                                href="{{ route('password.request') }}"
                                class="text-sm font-semibold text-emerald-700 transition hover:text-emerald-800 hover:underline"
                            >
                                Forgot password?
                            </a>
                        </div>

                        <button
                            id="submit-btn"
                            type="submit"
                            class="relative flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-3.5 text-sm font-black text-white shadow-[0_12px_30px_rgba(15,23,42,0.18)] transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900/20 focus:ring-offset-2 active:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span id="btn-label">Sign In To Green Leaf</span>
                            <svg id="btn-spinner" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg id="btn-arrow" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </form>

                    @if (($demoUsers ?? collect())->isNotEmpty())
                        <div class="mt-5 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Demo Login</p>
                                    <p class="mt-2 text-sm leading-6 text-emerald-950">
                                        One-click access for local testing. These buttons are hidden in production.
                                    </p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">
                                    {{ $demoUsers->count() }} users
                                </span>
                            </div>

                            <div class="mt-4 grid gap-2">
                                @foreach ($demoUsers as $demoUser)
                                    <form method="POST" action="{{ route('login.demo', $demoUser) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="flex w-full items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-white px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-100/60 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
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
                        </div>
                    @else
                        <div class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Access Notice</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                Test credentials are not displayed on this page. Use the assigned shop-owner or staff account details provided separately.
                            </p>
                        </div>
                    @endif

                    <a
                        href="{{ route('shop-owner.register') }}"
                        class="mt-5 flex w-full items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-3 text-sm font-black text-emerald-800 transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:ring-offset-2"
                    >
                        New shop owner? Register here
                    </a>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password').addEventListener('click', function () {
            const input = document.getElementById('password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');

            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        });

        document.getElementById('login-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            const label = document.getElementById('btn-label');
            const spinner = document.getElementById('btn-spinner');
            const arrow = document.getElementById('btn-arrow');

            btn.disabled = true;
            label.textContent = 'Signing in…';
            spinner.classList.remove('hidden');
            arrow.classList.add('hidden');
        });
    </script>
</x-layouts.auth>
