<x-layouts.auth title="Shop Incharge Registration">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(16,185,129,0.16),transparent_30%),linear-gradient(180deg,#f8fbf8_0%,#eef7f1_55%,#e5f0e8_100%)] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-xl items-center">
            <div class="w-full rounded-[2rem] border border-white/70 bg-white/88 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:p-7">
                <div class="space-y-3 text-center sm:text-left">
                    <div class="inline-flex items-center gap-3 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        Registration
                    </div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                        Shop-incharge registration
                    </h1>
                    <p class="text-sm leading-6 text-slate-600 sm:text-base">
                        Fill this form. Admin will approve the account, then you can log in.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                        <p class="text-sm font-bold text-red-800">Please fix the form errors.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('shop-owner.register.store') }}" class="mt-6 space-y-4" novalidate>
                    @csrf

                    <div class="space-y-1.5">
                        <label for="shop_name" class="block text-sm font-medium text-slate-700">Shop name</label>
                        <input id="shop_name" name="shop_name" type="text" value="{{ old('shop_name') }}" required class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('shop_name') border-red-300 bg-red-50 @enderror">
                        @error('shop_name')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="owner_name" class="block text-sm font-medium text-slate-700">Incharge name</label>
                        <input id="owner_name" name="owner_name" type="text" value="{{ old('owner_name') }}" required class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('owner_name') border-red-300 bg-red-50 @enderror">
                        @error('owner_name')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('email') border-red-300 bg-red-50 @enderror">
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="phone" class="block text-sm font-medium text-slate-700">Phone</label>
                        <input id="phone" name="phone" type="text" inputmode="numeric" value="{{ old('phone') }}" placeholder="9876543210" required class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('phone') border-red-300 bg-red-50 @enderror">
                        <p class="text-[11px] font-medium text-slate-500">Enter 10 digits only. +91 is added automatically.</p>
                        @error('phone')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="address" class="block text-sm font-medium text-slate-700">Address</label>
                        <textarea id="address" name="address" rows="3" class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('address') border-red-300 bg-red-50 @enderror">{{ old('address') }}</textarea>
                        @error('address')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 @error('password') border-red-300 bg-red-50 @enderror">
                        @error('password')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    </div>

                    <button type="submit" class="flex w-full items-center justify-center rounded-2xl bg-slate-950 px-6 py-3.5 text-sm font-black text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900/20 focus:ring-offset-2">
                        Submit registration
                    </button>

                    <p class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-6 text-slate-600">
                        After admin approval, you can sign in using this same email and password.
                    </p>
                </form>
            </div>
        </div>
    </div>
</x-layouts.auth>
