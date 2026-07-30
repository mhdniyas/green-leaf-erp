<x-layouts.admin title="Company Settings">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Administration</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Company Settings</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">These details print on purchaser bill invoices.</p>
                </div>
                <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                    Back to Admin
                </a>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.company-settings.update') }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            @csrf
            @method('PATCH')

            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4">
                    <div>
                        <label for="company_name" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Company Name</label>
                        <input id="company_name" type="text" name="company_name" value="{{ old('company_name', $companyDetails['company_name']) }}" required class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        @error('company_name')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company_address" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Address</label>
                        <textarea id="company_address" name="company_address" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">{{ old('company_address', $companyDetails['company_address']) }}</textarea>
                        @error('company_address')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="company_phone" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Phone</label>
                            <input id="company_phone" type="text" name="company_phone" value="{{ old('company_phone', $companyDetails['company_phone']) }}" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            @error('company_phone')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="company_email" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Email</label>
                            <input id="company_email" type="email" name="company_email" value="{{ old('company_email', $companyDetails['company_email']) }}" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            @error('company_email')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-teal-500">
                        Save Company Details
                    </button>
                </div>
            </section>

            <aside class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Bill Preview</p>
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                    <p class="text-sm font-black uppercase text-slate-950">{{ old('company_name', $companyDetails['company_name']) }}</p>
                    <p class="mt-2 text-xs font-semibold leading-5 text-slate-600">{{ old('company_address', $companyDetails['company_address']) ?: 'Address not set' }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ old('company_phone', $companyDetails['company_phone']) ? 'Phone: '.old('company_phone', $companyDetails['company_phone']) : 'Phone not set' }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ old('company_email', $companyDetails['company_email']) ? 'Email: '.old('company_email', $companyDetails['company_email']) : 'Email not set' }}</p>
                </div>
            </aside>
        </form>
    </div>
</x-layouts.admin>
