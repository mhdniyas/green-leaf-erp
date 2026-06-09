<x-layouts.auth title="Sign In">

<div class="min-h-screen flex">

    {{-- LEFT PANEL — Branding --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 relative overflow-hidden bg-brand-900">

        {{-- Background pattern --}}
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 20% 50%, oklch(0.47 0.20 145 / 0.4) 0%, transparent 60%), radial-gradient(circle at 80% 20%, oklch(0.56 0.22 145 / 0.3) 0%, transparent 50%), radial-gradient(circle at 60% 80%, oklch(0.39 0.17 145 / 0.5) 0%, transparent 40%);"></div>

        {{-- Decorative circles --}}
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-brand-700/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full border border-brand-700/20"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] rounded-full border border-brand-600/20"></div>

        {{-- Content --}}
        <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 w-full">

            {{-- Logo --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-500 flex items-center justify-center shadow-lg shadow-brand-900/50">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-lg leading-none">Green Leaf</p>
                    <p class="text-brand-400 text-xs font-medium tracking-wider uppercase">ERP System</p>
                </div>
            </div>

            {{-- Center content --}}
            <div class="space-y-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 bg-brand-800/60 backdrop-blur-sm border border-brand-700/40 rounded-full px-4 py-2">
                        <div class="w-2 h-2 rounded-full bg-accent-400 animate-pulse"></div>
                        <span class="text-brand-300 text-sm font-medium">Vegetable Trading Platform</span>
                    </div>

                    <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight">
                        Manage your<br>
                        <span class="text-brand-400">entire business</span><br>
                        from one place.
                    </h1>

                    <p class="text-brand-300 text-lg leading-relaxed max-w-sm">
                        From procurement to delivery — track every kilogram, every sale, every ringgit.
                    </p>
                </div>

                {{-- Feature pills --}}
                <div class="flex flex-wrap gap-2">
                    @foreach(['Inventory & Grading', 'Purchase Orders', 'Sales & Invoicing', 'Wastage Tracking', 'Finance & P&L'] as $feature)
                        <span class="inline-flex items-center gap-1.5 bg-brand-800/50 border border-brand-700/40 text-brand-300 text-xs font-medium px-3 py-1.5 rounded-full">
                            <svg class="w-3 h-3 text-brand-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                            {{ $feature }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-4">
                @foreach([
                    ['label' => 'Modules', 'value' => '8+'],
                    ['label' => 'Phase', 'value' => '1 / 8'],
                    ['label' => 'Status', 'value' => 'Beta'],
                ] as $stat)
                <div class="bg-brand-800/40 backdrop-blur-sm border border-brand-700/30 rounded-xl p-4">
                    <p class="text-2xl font-bold text-white">{{ $stat['value'] }}</p>
                    <p class="text-brand-400 text-xs mt-0.5">{{ $stat['label'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RIGHT PANEL — Login Form --}}
    <div class="flex-1 flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16 xl:px-20 bg-white">
        <div class="w-full max-w-md mx-auto space-y-8">

            {{-- Mobile logo --}}
            <div class="flex items-center gap-3 lg:hidden">
                <div class="w-9 h-9 rounded-xl bg-brand-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-gray-900 font-semibold text-base leading-none">Green Leaf ERP</p>
                    <p class="text-gray-500 text-xs">Vegetable Trading Platform</p>
                </div>
            </div>

            {{-- Header --}}
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Welcome back</h2>
                <p class="mt-1 text-gray-500 text-sm">Sign in to your Green Leaf account</p>
            </div>

            {{-- 📢 SHOP OWNER REQUISITION ALERT --}}
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 flex gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                    <h4 class="text-xs font-bold text-emerald-900 tracking-wide uppercase">Shop Requisition Rules</h4>
                    <p class="text-emerald-800 text-xs leading-relaxed">
                        Daily orders submitted before the **9:30 PM** deadline are automatically validated. Late submissions require Purchase Manager approval to be consolidated.
                    </p>
                </div>
            </div>

            {{-- ✅ DEMO CREDENTIALS BANNER (Testing Phase) --}}
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3" id="demo-credentials-banner">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <p class="text-amber-800 text-xs font-semibold tracking-wide uppercase">Testing Mode — Demo Accounts</p>
                </div>

                <div class="space-y-1.5">
                    @foreach([
                        ['role' => 'Administrator', 'email' => 'admin@greenleaf.com', 'color' => 'text-purple-700', 'initial' => 'AD'],
                        ['role' => 'Shop Owner · Casio', 'email' => 'shop@greenleaf.com', 'color' => 'text-emerald-700', 'initial' => 'SC'],
                        ['role' => 'Shop Owner · Budegere', 'email' => 'shop-budegere@greenleaf.com', 'color' => 'text-emerald-700', 'initial' => 'SB'],
                        ['role' => 'Shop Owner · Grancity', 'email' => 'shop-grancity@greenleaf.com', 'color' => 'text-emerald-700', 'initial' => 'SG'],
                        ['role' => 'Shop Owner · Ashirwad', 'email' => 'shop-ashirwad@greenleaf.com', 'color' => 'text-emerald-700', 'initial' => 'SA'],
                        ['role' => 'Purchase Manager', 'email' => 'purchase@greenleaf.com', 'color' => 'text-amber-700', 'initial' => 'PM'],
                        ['role' => 'Warehouse Manager', 'email' => 'warehouse@greenleaf.com', 'color' => 'text-pink-700', 'initial' => 'WM'],
                    ] as $demo)
                    <button
                        type="button"
                        onclick="fillCredentials('{{ $demo['email'] }}')"
                        class="w-full flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-amber-200/60 hover:border-amber-300 hover:bg-amber-50/50 transition-all group cursor-pointer"
                        title="Click to log in instantly"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 {{ $demo['color'] }} text-[9px] font-bold shrink-0">{{ $demo['initial'] }}</span>
                            <div class="text-left min-w-0">
                                <span class="text-amber-900 text-xs font-semibold block">{{ $demo['role'] }}</span>
                                <span class="text-amber-700 text-[11px] font-mono truncate block">{{ $demo['email'] }}</span>
                            </div>
                        </div>
                        <span class="text-amber-500 text-[10px] font-medium shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">one-click login →</span>
                    </button>
                    @endforeach
                </div>
                <p class="text-amber-700 text-[11px]">All accounts use password: <code class="font-mono font-bold bg-amber-100 px-1 rounded">password</code></p>
            </div>

            {{-- Error messages --}}
            @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 flex items-start gap-3" role="alert">
                <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="text-red-800 text-sm font-medium">Sign in failed</p>
                    @foreach($errors->all() as $error)
                        <p class="text-red-700 text-xs mt-0.5">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            @endif

            @if (session('status'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3">
                <p class="text-green-800 text-sm">{{ session('status') }}</p>
            </div>
            @endif

            {{-- Login Form --}}
            <form id="login-form" method="POST" action="{{ route('login.submit') }}" class="space-y-5" novalidate>
                @csrf

                {{-- Email --}}
                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email address
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
                            class="block w-full rounded-xl border border-gray-200 bg-gray-50 pl-10 pr-4 py-3 text-sm text-gray-900 placeholder-gray-400 transition-all focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('email') border-red-300 bg-red-50 focus:border-red-500 focus:ring-red-500/20 @enderror"
                        >
                    </div>
                    @error('email')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
                            class="block w-full rounded-xl border border-gray-200 bg-gray-50 pl-10 pr-12 py-3 text-sm text-gray-900 placeholder-gray-400 transition-all focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('password') border-red-300 bg-red-50 @enderror"
                        >
                        {{-- Toggle password visibility --}}
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 transition-colors"
                            aria-label="Toggle password visibility"
                        >
                            <svg id="eye-open" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg id="eye-closed" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Remember me + Forgot password --}}
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2.5 cursor-pointer group">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/30 cursor-pointer"
                        >
                        <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">Remember me</span>
                    </label>
                    <a
                        href="{{ route('password.request') }}"
                        class="text-sm text-brand-600 font-medium hover:text-brand-700 hover:underline transition-colors"
                    >
                        Forgot password?
                    </a>
                </div>

                {{-- Submit --}}
                <button
                    id="submit-btn"
                    type="submit"
                    class="relative w-full flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-900/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-all duration-150 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span id="btn-label">Sign in to Green Leaf</span>
                    <svg id="btn-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg id="btn-arrow" class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </form>

            {{-- Footer --}}
            <div class="border-t border-gray-100 pt-6 text-center space-y-1">
                <p class="text-xs text-gray-400">
                    Green Leaf ERP &copy; {{ date('Y') }} — Vegetable Trading & Distribution
                </p>
                <p class="text-xs text-gray-400">
                    Phase 1 — Testing Build
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    // Fill demo credentials on click and log in automatically
    function fillCredentials(email) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = 'password';
        
        // Auto-submit by programmatically clicking the sign in button
        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) {
            submitBtn.click();
        } else {
            document.getElementById('login-form').submit();
        }
    }

    // Password toggle
    document.getElementById('toggle-password').addEventListener('click', function() {
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

    // Loading state on submit
    document.getElementById('login-form').addEventListener('submit', function() {
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
