@if (session()->has('admin_impersonator_id'))
    <div class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 sm:mx-6" role="status">
        <div>
            <p class="text-sm font-semibold">Viewing as: {{ session('admin_impersonation_target_user_name', auth()->user()?->name) }}</p>
            <p class="mt-1 text-xs font-semibold text-amber-700">All actions are using this user&apos;s exact access until you return to admin.</p>
        </div>
        <form method="POST" action="{{ route('admin.user-access.stop') }}">
            @csrf
            <button type="submit" class="inline-flex h-9 items-center rounded-xl border border-amber-300 bg-white px-3 text-xs font-black uppercase tracking-[0.14em] text-amber-800 transition hover:bg-amber-100">
                Return to Admin
            </button>
        </form>
    </div>
@endif
