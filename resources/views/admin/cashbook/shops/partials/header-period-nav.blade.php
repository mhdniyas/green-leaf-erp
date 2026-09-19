@php
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;
    $period = $financialReport['period'] ?? [
        'start' => $periodStart,
        'end' => $periodEnd,
        'month' => $month,
        'mode' => $periodMode,
        'formatted_range' => $periodStart === $periodEnd ? \Illuminate\Support\Carbon::parse($periodStart)->format('d M Y') : \Illuminate\Support\Carbon::parse($periodStart)->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($periodEnd)->format('d M Y'),
        'label' => $monthTitle ?? \Illuminate\Support\Carbon::parse($month.'-01')->format('F Y'),
    ];
@endphp

<header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-5" aria-label="Shop Cashbook Header and Period Navigation">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase">
                    {{ $currentShop->name ?: 'Shop #'.$currentShop->shop_id }}
                </h1>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                    {{ $currentShop->client->name ?? 'Action Center' }}
                </span>
            </div>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                Operational Action Center &bull; Financial Execution &amp; Reporting
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin') || auth()->user()->can('manage cashbook') || auth()->user()->hasAnyRole(['admin', 'super-admin'])))
                <!-- 1. RECEIVE PAYMENT (PRIMARY) -->
                <button type="button"
                        @click="openReceivePayment()"
                        class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>Receive Payment</span>
                </button>

                <!-- 2. FUND PETTY (SECONDARY) -->
                @if(!empty($pettyConfig['enabled']) && !empty($pettyConfig['allow_company_to_petty']))
                    <button type="button"
                            @click="showFundPettyModal = true"
                            class="inline-flex items-center gap-1.5 rounded-2xl bg-purple-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-purple-800 transition cursor-pointer">
                        <svg class="w-4 h-4 text-purple-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Fund Petty</span>
                    </button>
                @endif
            @endif

            @if($periodMode === 'month' && auth()->check())
                <!-- REFRESH & RECALCULATE MONTH (MONTH VIEW ONLY) -->
                <form method="POST" action="{{ route('admin.cashbook.shop.recalculate-month', $currentShopSlugOrId) }}" class="inline-flex items-center">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-2xl border border-indigo-300 bg-indigo-50 px-3.5 py-2 text-xs font-black uppercase tracking-wider text-indigo-900 shadow-xs hover:bg-indigo-100 transition cursor-pointer"
                            title="Rebuild all monthly Cashbook calculations according to its Category/Header/Relation configuration">
                        <svg class="w-4 h-4 text-indigo-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Refresh &amp; Recalculate Month</span>
                    </button>
                </form>
            @endif

            <!-- 3. SETTINGS -->
            <a href="{{ route('admin.cashbook.settings.shop', $currentShopSlugOrId) }}"
               class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-slate-800 shadow-xs hover:border-slate-400 hover:bg-slate-50 transition cursor-pointer">
                <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- Period Selector Form & Switcher -->
    <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 sm:p-4" x-data="{ activeMode: '{{ $periodMode }}' }">
        <form method="GET" action="{{ route('admin.cashbook.shop.show', $currentShopSlugOrId) }}" class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="period_mode" :value="activeMode">

            <!-- Mode Switcher Buttons -->
            <div class="flex items-center gap-1.5 bg-slate-200/70 p-1 rounded-xl w-fit flex-wrap">
                <button type="submit"
                        @click="activeMode = 'month'"
                        name="period_mode"
                        value="month"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $periodMode === 'month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Month
                </button>

                <button type="button"
                        @click="activeMode = 'day'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $periodMode === 'day' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Day
                </button>

                <button type="button"
                        @click="activeMode = 'custom'"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $periodMode === 'custom' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Custom
                </button>
            </div>

            <!-- Month Selection & Mode Specific Inputs -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Month Navigator -->
                <div class="flex items-center gap-1">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShopSlugOrId, 'month' => $prevMonth, 'period_mode' => 'month']) }}"
                       class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 transition" title="Previous Month">
                        &larr;
                    </a>
                    <span class="px-2.5 py-1 text-xs font-black text-slate-900 uppercase">
                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
                    </span>
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShopSlugOrId, 'month' => $nextMonth, 'period_mode' => 'month']) }}"
                       class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 transition" title="Next Month">
                        &rarr;
                    </a>
                </div>

                <!-- Day Mode Input -->
                <div x-show="activeMode === 'day'" class="flex flex-wrap items-center gap-2" style="display: none;">
                    <label for="day_picker" class="text-xs font-bold text-slate-600">Day:</label>
                    <input type="date"
                           id="day_picker"
                           name="date"
                           value="{{ $periodStart }}"
                           min="{{ $monthStart }}"
                           max="{{ $monthEnd }}"
                           class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:outline-hidden max-w-[150px]">
                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                        Apply Day
                    </button>
                </div>

                <!-- Custom Range Mode Inputs -->
                <div x-show="activeMode === 'custom'" class="flex flex-wrap items-center gap-2" style="display: none;">
                    <div class="flex items-center gap-1">
                        <label for="from_picker" class="text-xs font-bold text-slate-600">From:</label>
                        <input type="date"
                               id="from_picker"
                               name="from"
                               value="{{ $periodStart }}"
                               min="{{ $monthStart }}"
                               max="{{ $monthEnd }}"
                               class="rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:outline-hidden max-w-[130px]">
                    </div>
                    <div class="flex items-center gap-1">
                        <label for="to_picker" class="text-xs font-bold text-slate-600">To:</label>
                        <input type="date"
                               id="to_picker"
                               name="to"
                               value="{{ $periodEnd }}"
                               min="{{ $monthStart }}"
                               max="{{ $monthEnd }}"
                               class="rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:outline-hidden max-w-[130px]">
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                        Apply Range
                    </button>
                </div>
            </div>
        </form>

        <!-- Current Period Active Badge & Recalculation Info -->
        <div class="mt-3 pt-3 border-t border-slate-200/60 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs font-bold text-slate-600">
                    Period: <strong class="text-slate-950 font-black">{{ $period['formatted_range'] }}</strong>
                </span>
                @if($periodMode === 'month')
                    <span class="text-slate-300">&bull;</span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 font-medium">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Last recalculated: <strong class="text-slate-700 font-bold">{{ !empty($lastRecalculatedAt) ? \Illuminate\Support\Carbon::parse($lastRecalculatedAt)->format('d M Y h:i A') : 'Not recalculated yet' }}</strong></span>
                    </span>
                @endif
            </div>
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                {{ strtoupper($periodMode) }} VIEW
            </span>
        </div>
    </div>
</header>
