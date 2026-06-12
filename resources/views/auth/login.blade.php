<x-layouts.auth title="Sign In">
    @php
        $roleGroups = [
            [
                'title' => 'Administration',
                'description' => 'Platform configuration, user control, reporting oversight, and operational review.',
                'accent' => 'from-slate-900 via-slate-800 to-slate-700',
                'badge' => 'Control',
                'accounts' => [
                    ['role' => 'Administrator', 'email' => 'admin@greenleaf.com', 'initial' => 'AD', 'accent' => 'bg-slate-900 text-white'],
                ],
            ],
            [
                'title' => 'Shop Operations',
                'description' => 'Submit requisitions, review approvals, check deliveries, and verify each shop workflow.',
                'accent' => 'from-emerald-700 via-emerald-600 to-lime-500',
                'badge' => '4 Shops',
                'accounts' => [
                    ['role' => 'Casio Hypermarket', 'email' => 'shop@greenleaf.com', 'initial' => 'CA', 'accent' => 'bg-emerald-600 text-white'],
                    ['role' => 'Budegere', 'email' => 'shop-budegere@greenleaf.com', 'initial' => 'BU', 'accent' => 'bg-emerald-600 text-white'],
                    ['role' => 'Grancity', 'email' => 'shop-grancity@greenleaf.com', 'initial' => 'GR', 'accent' => 'bg-emerald-600 text-white'],
                    ['role' => 'Ashirwad', 'email' => 'shop-ashirwad@greenleaf.com', 'initial' => 'AS', 'accent' => 'bg-emerald-600 text-white'],
                ],
            ],
            [
                'title' => 'Procurement & Warehouse',
                'description' => 'Approve demand, generate purchase orders, receive goods, and confirm physical receipt into inventory.',
                'accent' => 'from-amber-500 via-orange-500 to-rose-500',
                'badge' => 'Execution',
                'accounts' => [
                    ['role' => 'Purchase Manager', 'email' => 'purchase@greenleaf.com', 'initial' => 'PM', 'accent' => 'bg-amber-500 text-slate-950'],
                    ['role' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'initial' => 'PU', 'accent' => 'bg-cyan-500 text-white'],
                    ['role' => 'Warehouse Manager', 'email' => 'warehouse@greenleaf.com', 'initial' => 'WH', 'accent' => 'bg-rose-500 text-white'],
                    ['role' => 'Warehouse Receiver', 'email' => 'receiver@greenleaf.com', 'initial' => 'WR', 'accent' => 'bg-indigo-500 text-white'],
                ],
            ],
        ];
    @endphp

    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(38,84,124,0.16),transparent_38%),linear-gradient(180deg,#f5f8fb_0%,#edf3f7_50%,#e8eef4_100%)]">
        <div class="mx-auto flex min-h-screen w-full max-w-[1700px] flex-col px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
            <div class="rounded-[2rem] border border-white/70 bg-white/72 shadow-[0_24px_120px_rgba(15,23,42,0.10)] backdrop-blur-xl">
                <div class="border-b border-slate-200/80 px-5 py-5 sm:px-8 lg:px-10">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl space-y-4">
                            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.22em] text-cyan-700">
                                <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                Testing Environment
                            </div>

                            <div class="space-y-2">
                                <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">
                                    Green Leaf ERP Demo Access
                                </h1>
                                <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                                    Full-screen QA login for role switching, shop validation, procurement review, and warehouse flow testing.
                                    Use the grouped demo accounts below for one-click access during testing.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ([
                                ['label' => 'Demo Roles', 'value' => '8'],
                                ['label' => 'Shop Logins', 'value' => '4'],
                                ['label' => 'Password', 'value' => 'Shared'],
                                ['label' => 'Build', 'value' => 'Testing'],
                            ] as $stat)
                                <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $stat['label'] }}</p>
                                    <p class="mt-2 text-lg font-black text-slate-950">{{ $stat['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 px-5 py-5 sm:px-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(360px,0.75fr)] lg:px-10 lg:py-8">
                    <section class="space-y-5">
                        <div class="grid gap-5 xl:grid-cols-3">
                            @foreach ($roleGroups as $group)
                                <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                                    <div class="bg-gradient-to-br {{ $group['accent'] }} px-5 py-5 text-white">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-white/70">{{ $group['badge'] }}</p>
                                                <h2 class="mt-2 text-xl font-black">{{ $group['title'] }}</h2>
                                            </div>
                                            <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.18em]">
                                                {{ count($group['accounts']) }} Access
                                            </span>
                                        </div>
                                        <p class="mt-4 text-sm leading-6 text-white/80">{{ $group['description'] }}</p>
                                    </div>

                                    <div class="space-y-3 p-4">
                                        @foreach ($group['accounts'] as $demo)
                                            <button
                                                type="button"
                                                onclick="fillCredentials('{{ $demo['email'] }}')"
                                                class="group flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left transition hover:border-slate-300 hover:bg-white hover:shadow-sm"
                                                title="Sign in as {{ $demo['role'] }}"
                                            >
                                                <div class="flex min-w-0 items-center gap-3">
                                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-xs font-black {{ $demo['accent'] }}">
                                                        {{ $demo['initial'] }}
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-black text-slate-900">{{ $demo['role'] }}</p>
                                                        <p class="mt-1 truncate text-[11px] font-semibold text-slate-500">{{ $demo['email'] }}</p>
                                                    </div>
                                                </div>
                                                <span class="shrink-0 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 transition group-hover:text-slate-700">
                                                    Use
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                            <div class="rounded-[1.75rem] border border-emerald-200 bg-emerald-50/80 p-5">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 rounded-2xl bg-emerald-600 p-2 text-white">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Shop Requisition Rule</p>
                                        <p class="mt-2 text-sm leading-6 text-emerald-950">
                                            Orders submitted before <span class="font-black">9:30 PM</span> move through the normal approval flow.
                                            Late adjustments require purchase manager review before consolidation.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-[1.75rem] border border-slate-200 bg-slate-950 p-5 text-white">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-300">Demo Password</p>
                                <p class="mt-3 text-2xl font-black">password</p>
                                <p class="mt-3 text-sm leading-6 text-slate-300">
                                    Every demo login uses the same password. Click any access card to auto-fill and sign in instantly.
                                </p>
                            </div>
                        </div>
                    </section>

                    <aside class="space-y-4">
                        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="space-y-2">
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Manual Sign In</p>
                                <h2 class="text-2xl font-black tracking-tight text-slate-950">Access Portal</h2>
                                <p class="text-sm leading-6 text-slate-500">
                                    Use direct credentials below or click any role card to populate the form automatically.
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

                            <form id="login-form" method="POST" action="{{ route('login.submit') }}" class="mt-5 space-y-5" novalidate>
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
                                            class="block w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500/20 @error('email') border-red-300 bg-red-50 focus:border-red-500 focus:ring-red-500/20 @enderror"
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
                                            class="block w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-12 py-3 text-sm text-slate-900 placeholder-slate-400 transition focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500/20 @error('password') border-red-300 bg-red-50 @enderror"
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
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-2.5 text-sm text-slate-600">
                                        <input
                                            id="remember"
                                            name="remember"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500/30"
                                        >
                                        <span>Remember me</span>
                                    </label>

                                    <a
                                        href="{{ route('password.request') }}"
                                        class="text-sm font-semibold text-cyan-700 transition hover:text-cyan-800 hover:underline"
                                    >
                                        Forgot password?
                                    </a>
                                </div>

                                <button
                                    id="submit-btn"
                                    type="submit"
                                    class="relative flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-3 text-sm font-black text-white shadow-[0_12px_30px_rgba(15,23,42,0.18)] transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900/20 focus:ring-offset-2 active:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
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
                        </div>

                        <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Testing Notes</p>
                            <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                                <p>Use shop accounts to verify requisition, delivery, and finance visibility for each location.</p>
                                <p>Use purchase and warehouse accounts to test handoff between approval, PO generation, GRN, and allocation.</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillCredentials(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password';

            const submitBtn = document.getElementById('submit-btn');
            if (submitBtn) {
                submitBtn.click();
            } else {
                document.getElementById('login-form').submit();
            }
        }

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
