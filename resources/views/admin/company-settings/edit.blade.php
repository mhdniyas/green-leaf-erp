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

        <div class="space-y-5">
            <section class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
                <div class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Company settings sections">
                    <button type="button" data-settings-tab="company" role="tab" aria-selected="true" class="settings-tab shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white">Company</button>
                    <button type="button" data-settings-tab="operations" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Operations</button>
                    <button type="button" data-settings-tab="purchaser" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Purchaser</button>
                    <button type="button" data-settings-tab="warehouse-sales" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Warehouse Sales</button>
                    <button type="button" data-settings-tab="auto-load" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Auto Load All</button>
                    <button type="button" data-settings-tab="history" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Trigger History</button>
                </div>

                <div class="p-5 sm:p-6">
                    <form method="POST" action="{{ route('admin.company-settings.update') }}" id="company-settings-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="active_tab" id="settings-active-tab" value="{{ $activeTab ?? request('tab', 'company') }}">
                        <input type="hidden" name="active_subtab" id="settings-active-subtab" value="{{ $activeSubtab ?? request('subtab', 'daily-prices') }}">
                    <section data-settings-panel="company" role="tabpanel" class="settings-panel grid gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
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

                        <aside class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Bill Preview</p>
                            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-4 text-center">
                                <p class="text-sm font-black uppercase text-slate-950">{{ old('company_name', $companyDetails['company_name']) }}</p>
                                <p class="mt-2 text-xs font-semibold leading-5 text-slate-600">{{ old('company_address', $companyDetails['company_address']) ?: 'Address not set' }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ old('company_phone', $companyDetails['company_phone']) ? 'Phone: '.old('company_phone', $companyDetails['company_phone']) : 'Phone not set' }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ old('company_email', $companyDetails['company_email']) ? 'Email: '.old('company_email', $companyDetails['company_email']) : 'Email not set' }}</p>
                            </div>
                        </aside>
                    </section>

                    {{-- Dedicated Purchaser Section --}}
                    <section data-settings-panel="purchaser" role="tabpanel" class="settings-panel hidden max-w-4xl space-y-6">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-teal-600">Purchaser Settings &amp; Operations</p>
                            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                                Configure daily price cutoff rules, grade selling price defaults, default purchaser account, daily submission verification, and operating expenses.
                            </p>
                        </div>

                        {{-- Purchaser Internal Subtabs --}}
                        <div class="flex gap-1.5 overflow-x-auto rounded-2xl border border-slate-200 bg-slate-100 p-1.5" role="tablist" aria-label="Purchaser settings sub-sections">
                            <button type="button" data-purchaser-subtab="daily-prices" role="tab" aria-selected="true" class="purchaser-subtab shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white shadow-xs">Daily Price Settings</button>
                            <button type="button" data-purchaser-subtab="operations" role="tab" aria-selected="false" class="purchaser-subtab shrink-0 rounded-xl px-4 py-2 text-xs font-black text-slate-600 hover:text-slate-900 transition hover:bg-white/60">Purchaser Operations</button>
                            <button type="button" data-purchaser-subtab="operating-expenses" role="tab" aria-selected="false" class="purchaser-subtab shrink-0 rounded-xl px-4 py-2 text-xs font-black text-slate-600 hover:text-slate-900 transition hover:bg-white/60">Operating Expense Settings</button>
                        </div>

                        {{-- Subpanel 1: Daily Price Settings --}}
                        <div data-purchaser-subpanel="daily-prices" class="space-y-5">
                            {{-- Daily Price Cutoff Time Setting --}}
                            <div class="rounded-2xl border border-teal-200 bg-teal-50/60 p-4 sm:p-5 space-y-3">
                                <div>
                                    <label for="daily_price_cutoff_time" class="text-[11px] font-black uppercase tracking-[0.16em] text-teal-900">
                                        Daily Price Update Cutoff Time
                                    </label>
                                    <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
                                        Purchasers can update daily prices before this time. After the cutoff, only administrators can update prices.
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <input
                                        id="daily_price_cutoff_time"
                                        type="time"
                                        name="daily_price_cutoff_time"
                                        value="{{ old('daily_price_cutoff_time', $companyDetails['daily_price_cutoff_time']) }}"
                                        class="h-11 w-48 rounded-xl border border-teal-300 bg-white px-3 text-sm font-bold text-slate-900 focus:border-teal-600 focus:outline-none cursor-pointer"
                                    >
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-teal-100/80 px-2.5 py-1 text-xs font-bold text-teal-800">
                                        <svg class="h-3.5 w-3.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        Asia/Kolkata (Default: 09:00 AM) · Current: {{ $companyDetails['daily_price_cutoff_time_label'] ?? '09:00 AM' }}
                                    </span>
                                </div>
                                @error('daily_price_cutoff_time')
                                    <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Grade Selling Price Default Percentages --}}
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5 space-y-3">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-800">
                                        Grade Selling Price Default Percentages (Customer Group A)
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">
                                        Auto-calculated selling price percentage applied to Grade A prices when purchasers manage daily prices.
                                    </p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="grade_b_default_percentage" class="text-xs font-bold text-slate-700">
                                            Grade B Default Percentage (%)
                                        </label>
                                        <div class="relative mt-1">
                                            <input
                                                id="grade_b_default_percentage"
                                                type="number"
                                                step="0.1"
                                                min="1"
                                                max="200"
                                                name="grade_b_default_percentage"
                                                value="{{ old('grade_b_default_percentage', $companyDetails['grade_b_default_percentage']) }}"
                                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 pr-8 text-sm font-bold text-slate-900 focus:border-teal-500 focus:outline-none"
                                            >
                                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-slate-400">%</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-400 font-semibold">Standard default: 90% of Grade A</p>
                                        @error('grade_b_default_percentage')
                                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="grade_c_default_percentage" class="text-xs font-bold text-slate-700">
                                            Grade C Default Percentage (%)
                                        </label>
                                        <div class="relative mt-1">
                                            <input
                                                id="grade_c_default_percentage"
                                                type="number"
                                                step="0.1"
                                                min="1"
                                                max="200"
                                                name="grade_c_default_percentage"
                                                value="{{ old('grade_c_default_percentage', $companyDetails['grade_c_default_percentage']) }}"
                                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 pr-8 text-sm font-bold text-slate-900 focus:border-teal-500 focus:outline-none"
                                            >
                                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-slate-400">%</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-400 font-semibold">Standard default: 80% of Grade A</p>
                                        @error('grade_c_default_percentage')
                                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Allow Historical Invoice Repricing --}}
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
                        </div>

                        {{-- Subpanel 2: Purchaser Operations --}}
                        <div data-purchaser-subpanel="operations" class="space-y-5 hidden">
                            {{-- Default Purchaser Account --}}
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 space-y-2">
                                <label for="default_purchaser_user_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-700">Default Purchaser Account</label>
                                <select id="default_purchaser_user_id" name="default_purchaser_user_id" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                    <option value="">Select default purchaser</option>
                                    @foreach($purchaserUsers as $purchaserUser)
                                        <option value="{{ $purchaserUser->id }}" @selected((int) old('default_purchaser_user_id', $companyDetails['default_purchaser_user_id'] ?? 0) === (int) $purchaserUser->id)>
                                            {{ $purchaserUser->name }} ({{ $purchaserUser->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs font-semibold text-slate-500">Used as fallback account for purchaser ledger reports and company purchase ownership flows.</p>
                                @error('default_purchaser_user_id')
                                    <p class="text-xs font-bold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Purchaser Business Day Verification --}}
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 space-y-4 shadow-xs">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-teal-600">Purchaser Business Day Verification</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Configure global purchaser business day verification and daily submission settings across purchasing operations.</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                                        <label class="flex cursor-pointer items-start gap-3">
                                            <input type="hidden" name="purchaser_business_day_verification_enabled" value="0">
                                            <input
                                                type="checkbox"
                                                name="purchaser_business_day_verification_enabled"
                                                value="1"
                                                {{ old('purchaser_business_day_verification_enabled', $purchaserBusinessDayGlobalSettings['verification_enabled']) ? 'checked' : '' }}
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                            >
                                            <span>
                                                <span class="block text-sm font-black text-slate-900">Verification Enabled</span>
                                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">Enable purchaser daily business day verification and coverage tracking across all warehouses.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                                        <label class="flex cursor-pointer items-start gap-3">
                                            <input type="hidden" name="purchaser_business_day_require_daily_submission" value="0">
                                            <input
                                                type="checkbox"
                                                name="purchaser_business_day_require_daily_submission"
                                                value="1"
                                                {{ old('purchaser_business_day_require_daily_submission', $purchaserBusinessDayGlobalSettings['require_daily_submission']) ? 'checked' : '' }}
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                            >
                                            <span>
                                                <span class="block text-sm font-black text-slate-900">Require Daily Submission</span>
                                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">Require purchasers to verify and freeze daily receipts &amp; bills before cutoff.</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Purchaser User Preferences Link Card --}}
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-900">Individual Purchaser User Preferences</p>
                                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Category assignments and vendor visibility rules are configured per purchaser user account.</p>
                                </div>
                                <a href="{{ route('purchaser.settings') }}" class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-black text-slate-700 transition hover:bg-slate-50 shadow-2xs">
                                    Open Purchaser Preferences &rarr;
                                </a>
                            </div>
                        </div>

                        {{-- Subpanel 3: Operating Expenses --}}
                        <div data-purchaser-subpanel="operating-expenses" class="hidden">
                            @include('admin.company-settings.partials.operating_expenses')
                        </div>
                    </section>

                    {{-- General Operations Panel --}}
                    <section data-settings-panel="operations" role="tabpanel" class="settings-panel hidden max-w-3xl space-y-4">
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

                        <div class="rounded-2xl border border-teal-200 bg-teal-50/60 p-4 space-y-2">
                            <label for="shop_attendance_cutoff_time" class="text-[11px] font-black uppercase tracking-[0.16em] text-teal-900">
                                Shop Attendance Cutoff Time
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    id="shop_attendance_cutoff_time"
                                    type="time"
                                    name="shop_attendance_cutoff_time"
                                    value="{{ old('shop_attendance_cutoff_time', $companyDetails['shop_attendance_cutoff_time']) }}"
                                    class="h-11 w-48 rounded-xl border border-teal-300 bg-white px-3 text-sm font-bold text-slate-900 focus:border-teal-600 focus:outline-none cursor-pointer"
                                >
                                <span class="text-xs font-bold text-teal-800">Asia/Kolkata (Default: 10:00 AM)</span>
                            </div>
                            <p class="text-xs font-semibold leading-5 text-slate-600">
                                Shop owners can mark same-day staff attendance until this time. HR/Admin can correct attendance afterward.
                            </p>
                            @error('shop_attendance_cutoff_time')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-2xl border border-purple-200 bg-purple-50/70 p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <label class="flex cursor-pointer items-start gap-3 flex-1">
                                    <input type="hidden" name="enable_multi_grade_ordering" value="0">
                                    <input
                                        id="enable_multi_grade_ordering"
                                        type="checkbox"
                                        name="enable_multi_grade_ordering"
                                        value="1"
                                        {{ old('enable_multi_grade_ordering', $companyDetails['enable_multi_grade_ordering']) ? 'checked' : '' }}
                                        class="mt-1 h-5 w-5 rounded border-purple-300 text-purple-600 focus:ring-purple-500"
                                    >
                                    <div>
                                        <span class="block text-sm font-black text-slate-900">Enable Multi-Grade Shop Ordering</span>
                                        <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">
                                            Allow selected shops to order products separately by Quality Grade A, B, and C. Only shops with the corresponding grade permissions can order additional grades.
                                        </span>
                                    </div>
                                </label>
                                <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-black {{ $companyDetails['enable_multi_grade_ordering'] ? 'bg-purple-100 text-purple-800 border border-purple-300' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                    {{ $companyDetails['enable_multi_grade_ordering'] ? 'Active (ON)' : 'Disabled (OFF)' }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section data-settings-panel="warehouse-sales" role="tabpanel" class="settings-panel hidden max-w-4xl space-y-6">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-600">Warehouse Sales Access</p>
                            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                                Configure direct sales permissions. When enabled, only selected users can access <code>/warehouse/sales</code> to perform direct warehouse sales, strictly scoped to their assigned warehouses.
                            </p>
                        </div>

                        <!-- 1. Enable Warehouse Sales Toggle -->
                        <div class="rounded-2xl border p-4 transition-all {{ old('warehouse_sales_enabled', $warehouseSalesConfig['enabled']) ? 'border-emerald-300 bg-emerald-50/70 shadow-sm' : 'border-slate-200 bg-slate-50' }}">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="hidden" name="warehouse_sales_enabled" value="0">
                                <input
                                    id="warehouse_sales_enabled"
                                    type="checkbox"
                                    name="warehouse_sales_enabled"
                                    value="1"
                                    {{ old('warehouse_sales_enabled', $warehouseSalesConfig['enabled']) ? 'checked' : '' }}
                                    class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                >
                                <span>
                                    <span class="block text-sm font-black text-slate-900">Enable Warehouse Sales</span>
                                    <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">
                                        When OFF, the operational sales screen is inaccessible. Existing sales and Cashbook historical reporting remain safe and readable.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <!-- 2. Users & Warehouse Permission Matrix -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-600">
                                    Users Allowed to Make Warehouse Sales &amp; Warehouse Access
                                </label>
                                <span class="text-xs font-semibold text-slate-400">Explicit Access Control</span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium">
                                Select which users can create sales, and check the specific warehouses each user is permitted to sell from.
                            </p>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($allActiveUsers as $user)
                                    @php
                                        $isAllowed = in_array((int) $user->id, (array) old('warehouse_sales_allowed_user_ids', $warehouseSalesConfig['allowed_user_ids'] ?? []), true);
                                        $userAllowedWarehouses = (array) (old("warehouse_sales_user_warehouses.{$user->id}", $warehouseSalesConfig['user_warehouses'][$user->id] ?? []));
                                    @endphp
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition space-y-3">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="warehouse_sales_allowed_user_ids[]"
                                                value="{{ $user->id }}"
                                                @checked($isAllowed)
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 user-sale-access-chk"
                                                data-user-id="{{ $user->id }}"
                                            >
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-bold text-slate-900 truncate">{{ $user->name }}</div>
                                                <div class="text-xs text-slate-500 font-mono truncate">{{ $user->email }}</div>
                                            </div>
                                        </label>

                                        <!-- Warehouse checklist for this user -->
                                        <div class="pt-2 border-t border-slate-100 space-y-1.5 pl-7">
                                            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                                                Allowed Warehouses:
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($allActiveWarehouses as $wh)
                                                    <label class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            name="warehouse_sales_user_warehouses[{{ $user->id }}][]"
                                                            value="{{ $wh->id }}"
                                                            @checked(in_array((int) $wh->id, array_map('intval', $userAllowedWarehouses), true))
                                                            class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                        >
                                                        <span>{{ $wh->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section data-settings-panel="auto-load" role="tabpanel" class="settings-panel hidden space-y-5">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-violet-500">Auto Load All</p>
                            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">Scheduled loadout runs server-side. Save settings, then keep Laravel scheduler running with <code>php artisan schedule:work</code> locally or cron in production.</p>
                        </div>

                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4">
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
                                <span class="block text-sm font-black text-slate-900">Enable scheduled Auto Load All</span>
                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">Runs eligible warehouse orders at saved trigger time. History records every completed, failed, and empty run.</span>
                            </span>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4">
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
                                Asia/Kolkata timezone. Default: <strong>00:15</strong>.
                            </p>
                            @error('auto_load_all_time')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                            </div>

                            <div class="rounded-2xl border border-slate-200 p-4">
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
                                Waits between orders in manual and scheduled runs. Default: <strong>3</strong>.
                            </p>
                            @error('auto_load_all_delay_seconds')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4">
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

                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4">
                            <input type="hidden" name="auto_load_all_allow_manual" value="0">
                            <input
                                id="auto_load_all_allow_manual"
                                type="checkbox"
                                name="auto_load_all_allow_manual"
                                value="1"
                                {{ old('auto_load_all_allow_manual', $companyDetails['auto_load_all_allow_manual']) ? 'checked' : '' }}
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            >
                            <span>
                                <span class="block text-sm font-black text-slate-900">Allow Manual Load All Execution</span>
                                <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">Enables manual trigger mode allowing admins to run Load All on demand for selected dates and shops.</span>
                            </span>
                        </label>
                        </div>

                        <div class="rounded-xl border border-violet-300 bg-white p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
                            <div>
                                <span class="flex items-center gap-1.5 text-xs font-black text-violet-950">
                                    <svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    Manual Load All Trigger
                                </span>
                                <span class="block text-xs font-semibold text-slate-500 mt-0.5">Select specific date and shops to process orders manually one by one.</span>
                            </div>
                            @if(old('auto_load_all_allow_manual', $companyDetails['auto_load_all_allow_manual']))
                                <a href="{{ route('admin.auto-load-all.create') }}"
                                   class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-violet-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    Launch Manual Load All
                                </a>
                            @else
                                <span class="rounded-xl bg-slate-100 px-4 py-3 text-xs font-black text-slate-500">Manual trigger disabled</span>
                            @endif
                        </div>
                    </section>

                    <div id="company-settings-save-bar" class="flex items-center justify-between border-t border-slate-200 bg-slate-50 p-4 rounded-xl mt-6">
                        <p class="text-xs font-semibold text-slate-500">Changes apply to all warehouse operations.</p>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-teal-500">
                            Save Settings
                        </button>
                    </div>
                </form>

                <section data-settings-panel="history" role="tabpanel" class="settings-panel hidden">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-violet-500">Auto Load All History</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Manual and scheduled trigger summaries. Per-order details stay on manual run screen.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $autoLoadAllRuns->count() }} recent</span>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                        @forelse($autoLoadAllRuns as $run)
                            @php($properties = $run->properties)
                            <div class="grid gap-3 border-b border-slate-100 p-4 last:border-b-0 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ data_get($properties, 'status') === 'completed' ? 'bg-emerald-100 text-emerald-700' : (data_get($properties, 'status') === 'stopped' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">{{ data_get($properties, 'status', 'unknown') }}</span>
                                        <span class="text-xs font-black text-slate-900">{{ ucfirst((string) data_get($properties, 'trigger_mode', 'manual')) }} trigger</span>
                                        <span class="text-xs font-semibold text-slate-400">{{ data_get($properties, 'business_date', 'No date') }}</span>
                                    </div>
                                    <p class="mt-2 text-xs font-semibold text-slate-500">{{ data_get($properties, 'loaded_orders', 0) }} loaded · {{ data_get($properties, 'skipped_orders', 0) }} skipped · {{ data_get($properties, 'failed_orders', 0) }} failed · {{ $run->causer?->name ?? 'System' }}</p>
                                    @if(data_get($properties, 'notes'))
                                        <p class="mt-1 text-xs font-semibold text-slate-400">{{ data_get($properties, 'notes') }}</p>
                                    @endif
                                </div>
                                <time class="text-xs font-bold text-slate-400">{{ $run->created_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</time>
                            </div>
                        @empty
                            <div class="p-8 text-center text-sm font-semibold text-slate-500">No manual or automatic trigger recorded yet.</div>
                        @endforelse
                    </div>
                </section>

            </div>
        </section>
    </div>

    <script>
        const activeTabInput = document.getElementById('settings-active-tab');
        const activeSubtabInput = document.getElementById('settings-active-subtab');
        const urlParams = new URLSearchParams(window.location.search);
        let initialTab = urlParams.get('tab') || window.location.hash.replace('#', '') || (activeTabInput ? activeTabInput.value : 'company');
        let initialSubtab = urlParams.get('subtab') || (activeSubtabInput ? activeSubtabInput.value : 'daily-prices');

        function activateTab(selectedTab, selectedSubtab) {
            // Backward compatibility aliases
            if (selectedTab === 'operating-expenses') {
                selectedTab = 'purchaser';
                selectedSubtab = 'operating-expenses';
            } else if (selectedTab === 'purchaser-business-day') {
                selectedTab = 'purchaser';
                selectedSubtab = 'operations';
            }

            document.querySelectorAll('.settings-tab').forEach((item) => {
                const isSelected = item.dataset.settingsTab === selectedTab;
                item.setAttribute('aria-selected', String(isSelected));
                item.className = isSelected
                    ? 'settings-tab shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white'
                    : 'settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white';
            });

            document.querySelectorAll('.settings-panel').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.settingsPanel !== selectedTab);
            });

            if (selectedTab === 'purchaser') {
                const subtabToActivate = selectedSubtab || (activeSubtabInput && activeSubtabInput.value ? activeSubtabInput.value : 'daily-prices');
                activatePurchaserSubtab(subtabToActivate);
            } else {
                updateSaveBarVisibility(selectedTab, null);
            }

            if (activeTabInput) {
                activeTabInput.value = selectedTab;
            }
        }

        function activatePurchaserSubtab(selectedSubtab) {
            document.querySelectorAll('.purchaser-subtab').forEach((item) => {
                const isSelected = item.dataset.purchaserSubtab === selectedSubtab;
                item.setAttribute('aria-selected', String(isSelected));
                item.className = isSelected
                    ? 'purchaser-subtab shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white shadow-xs'
                    : 'purchaser-subtab shrink-0 rounded-xl px-4 py-2 text-xs font-black text-slate-600 hover:text-slate-900 transition hover:bg-white/60';
            });

            document.querySelectorAll('[data-purchaser-subpanel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.purchaserSubpanel !== selectedSubtab);
            });

            if (activeSubtabInput) {
                activeSubtabInput.value = selectedSubtab;
            }

            updateSaveBarVisibility('purchaser', selectedSubtab);
        }

        function updateSaveBarVisibility(tab, subtab) {
            const saveBar = document.getElementById('company-settings-save-bar');
            if (saveBar) {
                const isOperatingExpenses = (tab === 'purchaser' && subtab === 'operating-expenses') || tab === 'operating-expenses';
                saveBar.classList.toggle('hidden', isOperatingExpenses || tab === 'history');
            }
        }

        document.querySelectorAll('.settings-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                activateTab(tab.dataset.settingsTab, null);
            });
        });

        document.querySelectorAll('.purchaser-subtab').forEach((subtab) => {
            subtab.addEventListener('click', () => {
                activatePurchaserSubtab(subtab.dataset.purchaserSubtab);
            });
        });

        activateTab(initialTab, initialSubtab);
    </script>
</x-layouts.admin>
