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

        <form method="POST" action="{{ route('admin.company-settings.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <section class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
                <div class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Company settings sections">
                    <button type="button" data-settings-tab="company" role="tab" aria-selected="true" class="settings-tab shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white">Company</button>
                    <button type="button" data-settings-tab="operations" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Operations</button>
                    <button type="button" data-settings-tab="auto-load" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Auto Load All</button>
                    <button type="button" data-settings-tab="history" role="tab" aria-selected="false" class="settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white">Trigger History</button>
                </div>

                <div class="p-5 sm:p-6">
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

                    <section data-settings-panel="operations" role="tabpanel" class="settings-panel hidden max-w-3xl space-y-4">
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

                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <p class="text-xs font-semibold text-slate-500">Changes apply to all warehouse operations.</p>
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-teal-500">
                        Save Settings
                    </button>
                </div>
            </section>
        </form>
    </div>

    <script>
        document.querySelectorAll('.settings-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                const selectedTab = tab.dataset.settingsTab;

                document.querySelectorAll('.settings-tab').forEach((item) => {
                    const isSelected = item === tab;
                    item.setAttribute('aria-selected', String(isSelected));
                    item.className = isSelected
                        ? 'settings-tab shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white'
                        : 'settings-tab shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white';
                });

                document.querySelectorAll('.settings-panel').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.settingsPanel !== selectedTab);
                });
            });
        });
    </script>
</x-layouts.admin>
