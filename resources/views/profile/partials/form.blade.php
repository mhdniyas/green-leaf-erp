<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Account Settings</p>
            <h2 class="mt-2 text-xl font-black tracking-tight text-slate-900">Profile Update</h2>
            <p class="mt-1 text-sm text-slate-500">Update your basic account details and password from mobile or desktop.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Current Role</p>
            <p class="mt-1 font-bold text-slate-900">{{ $user->getRoleNames()->join(', ') ?: 'User' }}</p>
            @if ($user->shop)
                <p class="mt-1 text-xs text-slate-500">{{ $user->shop->name }}</p>
            @endif
        </div>
    </div>

    <form action="{{ route('profile.update') }}" method="POST" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="profile-name" class="block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Full Name</label>
                <input
                    id="profile-name"
                    type="text"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none"
                    required
                >
            </div>

            <div>
                <label for="profile-email" class="block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Email</label>
                <input
                    id="profile-email"
                    type="email"
                    name="email"
                    value="{{ old('email', $user->email) }}"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none"
                    required
                >
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <label for="profile-password" class="block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">New Password</label>
                <input
                    id="profile-password"
                    type="password"
                    name="password"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none"
                    placeholder="Leave blank to keep current password"
                >
            </div>

            <div>
                <label for="profile-password-confirmation" class="block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Confirm Password</label>
                <input
                    id="profile-password-confirmation"
                    type="password"
                    name="password_confirmation"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none"
                    placeholder="Repeat the new password"
                >
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">Password changes are optional. Leave the password fields empty if you only need to update name or email.</p>
            <button type="submit" class="rounded-xl bg-cyan-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-cyan-700">
                Save Profile
            </button>
        </div>
    </form>
</section>
