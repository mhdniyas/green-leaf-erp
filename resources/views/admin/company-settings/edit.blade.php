<x-layouts.admin title="Company Settings">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Administration</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Company Settings</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">These details print on purchaser bill invoices.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.auto-load-all.create') }}"
                       class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 text-xs font-black text-white shadow-sm transition hover:bg-violet-500">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                        Load All Now
                    </a>
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
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

                    <div>
                        <label for="default_purchaser_user_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Default Purchaser Account</label>
                        <select id="default_purchaser_user_id" name="default_purchaser_user_id" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <option value="">Select default purchaser</option>
                            @foreach($purchaserUsers as $purchaserUser)
                                <option value="{{ $purchaserUser->id }}" @selected((int) old('default_purchaser_user_id', $companyDetails['default_purchaser_user_id'] ?? 0) === (int) $purchaserUser->id)>
                                    {{ $purchaserUser->name }} ({{ $purchaserUser->email }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Used as fallback account for purchaser ledger reports and company purchase ownership flows.</p>
                        @error('default_purchaser_user_id')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="default_direct_sale_shop_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Default Direct Sale Shop</label>
                        <select id="default_direct_sale_shop_id" name="default_direct_sale_shop_id" class="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <option value="">Auto-create Green Leaf Direct Sales</option>
                            @foreach($directSaleShops as $directSaleShop)
                                <option value="{{ $directSaleShop->id }}" @selected((int) old('default_direct_sale_shop_id', $companyDetails['default_direct_sale_shop_id'] ?? 0) === (int) $directSaleShop->id)>
                                    {{ $directSaleShop->name }} ({{ $directSaleShop->code }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Direct cash sale orders are created under this internal shop before warehouse loadout.</p>
                        @error('default_direct_sale_shop_id')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="hidden" name="allow_historical_invoice_repricing" value="0">
                            <input type="checkbox" name="allow_historical_invoice_repricing" value="1" {{ old('allow_historical_invoice_repricing', $companyDetails['allow_historical_invoice_repricing']) ? 'checked' : '' }} class="mt-1 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span>
                                <span class="block text-sm font-black text-slate-900">Allow historical invoice repricing</span>
                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">When enabled, unlocked invoices from past business dates can be repriced. Finalized invoices stay frozen.</span>
                            </span>
                        </label>
                    </div>

                    {{-- ── Auto Load All ──────────────────────────────────────── --}}
                    <div class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 space-y-4">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-violet-500">Auto Load All</p>
                            <p class="mt-1 text-xs font-semibold leading-5 text-slate-500">Global setting that controls the automatic warehouse loadout process. When enabled, Auto Load All runs automatically at the configured time each day across all warehouse operations.</p>
                        </div>

                        {{-- Enable toggle --}}
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="hidden" name="auto_load_all_enabled" value="0">
                            <input
                                id="auto_load_all_enabled"
                                type="checkbox"
                                name="auto_load_all_enabled"
                                value="1"
                                {{ old('auto_load_all_enabled', $companyDetails['auto_load_all_enabled']) ? 'checked' : '' }}
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            >
                            <span>
                                <span class="block text-sm font-black text-slate-900">Enable Auto Load All</span>
                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">Automatically runs "Auto Load All" at the configured trigger time each day. Applies globally to all warehouse loadout operations.</span>
                            </span>
                        </label>

                        {{-- Trigger time --}}
                        <div>
                            <label for="auto_load_all_time" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                                Daily Trigger Time
                            </label>
                            <input
                                id="auto_load_all_time"
                                type="time"
                                name="auto_load_all_time"
                                value="{{ old('auto_load_all_time', $companyDetails['auto_load_all_time']) }}"
                                class="mt-2 h-11 w-48 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 focus:border-violet-500 focus:outline-none"
                            >
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Auto Load All runs automatically at this time each day. Default: <strong>00:15</strong>.
                            </p>
                            @error('auto_load_all_time')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Delay between orders --}}
                        <div>
                            <label for="auto_load_all_delay_seconds" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                                Delay Between Orders (seconds)
                            </label>
                            <div class="mt-2 flex items-center gap-3">
                                <input
                                    id="auto_load_all_delay_seconds"
                                    type="number"
                                    name="auto_load_all_delay_seconds"
                                    value="{{ old('auto_load_all_delay_seconds', $companyDetails['auto_load_all_delay_seconds']) }}"
                                    min="1"
                                    max="60"
                                    class="h-11 w-28 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 focus:border-violet-500 focus:outline-none"
                                >
                                <span class="text-xs font-semibold text-slate-400">seconds</span>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Wait this many seconds between processing each shop order. Prevents database overload when many orders run sequentially. Default: <strong>3</strong>.
                            </p>
                            @error('auto_load_all_delay_seconds')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Next business day toggle --}}
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="hidden" name="auto_load_all_next_business_day" value="0">
                            <input
                                id="auto_load_all_next_business_day"
                                type="checkbox"
                                name="auto_load_all_next_business_day"
                                value="1"
                                {{ old('auto_load_all_next_business_day', $companyDetails['auto_load_all_next_business_day']) ? 'checked' : '' }}
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            >
                            <span>
                                <span class="block text-sm font-black text-slate-900">Target Next Business Day Only</span>
                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">When enabled, Auto Load All processes orders for the <em>next</em> business day instead of the current day. Useful when the trigger fires just after midnight to prepare tomorrow's orders.</span>
                            </span>
                        </label>
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
