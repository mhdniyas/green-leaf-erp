<x-layouts.app title="{{ !empty($showGradeTabs) ? 'Daily Prices (Grades A / B / C)' : 'Daily Prices' }}">
    <style>
        .daily-price-list {
            width: 100%;
            overflow-x: hidden;
            padding: 0;
            box-sizing: border-box;
        }

        .daily-price-row {
            width: 100%;
            min-height: 56px;
            padding: 8px 10px;

            background: #fff;
            border: 1px solid #e5edf5;
            border-radius: 12px;
            box-sizing: border-box;
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .daily-price-row:hover {
            border-color: #cbd5e1;
        }

        .grade-panel.hidden {
            display: none !important;
        }

        .grade-header {
            display: none;
        }

        @media (min-width: 768px) {
            .grade-header:not(.hidden) {
                display: grid;
            }
        }

        .grade-header.hidden {
            display: none !important;
        }

        .grade-grid-a {
            display: grid;
            grid-template-columns: minmax(105px, 1.25fr) 56px 84px 82px;
            align-items: center;
            gap: 6px;
            width: 100%;
        }

        .grade-grid-bc {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) 40px 34px 44px 72px;
            align-items: center;
            gap: 4px;
            width: 100%;
            min-width: 0;
        }

        @media (min-width: 768px) {
            .grade-grid-bc {
                grid-template-columns: minmax(130px, 1.5fr) 68px 52px 68px 82px;
                gap: 8px;
            }
        }

        .product-cell {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .product-title-wrap {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
        }

        .product-name {
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;

            font-size: 13px;
            font-weight: 700;
            color: #172033;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            text-align: left;
        }

        .product-name:hover {
            text-decoration: underline;
            color: #0f766e;
        }

        .product-unit {
            flex-shrink: 0;
            font-size: 11px;
            font-weight: 600;
            color: #8da0b8;
            white-space: nowrap;
        }

        .product-cost-info {
            font-size: 10px;
            font-weight: 700;
            color: #0f766e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }

        .updater-info {
            font-size: 10px;
            font-weight: 600;
            color: #0f766e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 1px;
        }

        .previous-price {
            width: 56px;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: right;
        }

        .reference-price {
            width: 100%;
            min-width: 0;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: right;
        }

        .calc-price {
            width: 100%;
            min-width: 0;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 700;
            color: #0f766e;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: right;
        }

        .percent-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 4px;
            border-radius: 5px;
            font-size: 9px;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .price-status-col {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .price-status {
            white-space: nowrap;
            padding: 4px 6px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            max-width: 84px;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        .price-status.increase {
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }

        .price-status.decrease {
            color: #be123c;
            background: #fff1f2;
            border: 1px solid #fecdd3;
        }

        .price-status.not-set {
            color: #c25b00;
            background: #fff8e6;
            border: 1px solid #f8d98a;
        }

        .price-status.no-change {
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .price-input-wrap {
            width: 82px;
            height: 38px;
            display: flex;
            align-items: center;

            border: 1px solid #d9e2ec;
            border-radius: 9px;
            background: #f8fafc;
            overflow: hidden;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }

        .price-input-wrap:focus-within {
            border-color: #0f766e;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.1);
        }

        .price-input-wrap.locked {
            background: #f1f5f9;
            border-color: #cbd5e1;
            cursor: not-allowed;
        }

        .price-input-wrap.locked:focus-within {
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .price-col-a {
            width: 82px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .grade-cell {
            width: 100%;
            max-width: 72px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .grade-cell .price-input-wrap {
            width: 100%;
            max-width: 72px;
            height: 36px;
        }

        .grade-cell .price-currency {
            padding-left: 5px;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            user-select: none;
            flex-shrink: 0;
        }

        .grade-cell .price-input {
            width: calc(100% - 13px);
            min-width: 0;
            padding: 0 4px 0 2px;
            border: 0;
            outline: 0;
            background: transparent;
            text-align: right;
            font-size: 12px;
            font-weight: 700;
            color: #172033;
        }

        .grade-cell .updater-name-tag {
            max-width: 72px;
            font-size: 8px;
        }

        .updater-name-tag {
            display: block;
            width: 100%;
            max-width: 82px;
            min-height: 13px;
            font-size: 9px;
            font-weight: 600;
            color: #64748b;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .price-currency {
            padding-left: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            user-select: none;
            flex-shrink: 0;
        }

        .price-input {
            width: 58px;
            min-width: 0;
            padding: 0 7px 0 2px;

            border: 0;
            outline: 0;
            background: transparent;
            text-align: right;

            font-size: 13px;
            font-weight: 700;
            color: #172033;
        }

        .price-input:disabled {
            cursor: not-allowed;
            color: #64748b;
        }

        .reset-default-btn {
            font-size: 8px;
            font-weight: 700;
            color: #0f766e;
            text-decoration: underline;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            margin-top: 1px;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.15s ease;
        }

        .reset-default-btn:hover {
            color: #115e59;
        }

        @media (min-width: 768px) {
            .reference-price {
                font-size: 12px;
            }
            .calc-price {
                font-size: 12px;
            }
            .percent-badge {
                padding: 2px 6px;
                font-size: 10px;
                border-radius: 6px;
            }
            .grade-cell {
                max-width: 82px;
            }
            .grade-cell .price-input-wrap {
                max-width: 82px;
                height: 38px;
            }
            .grade-cell .price-currency {
                padding-left: 6px;
                font-size: 12px;
            }
            .grade-cell .price-input {
                width: 58px;
                padding: 0 7px 0 2px;
                font-size: 13px;
            }
            .grade-cell .updater-name-tag {
                max-width: 82px;
                font-size: 9px;
            }
            .reset-default-btn {
                font-size: 9px;
            }
        }
    </style>

    @php
        if (!function_exists('formatPriceCompactPHP')) {
            function formatPriceCompactPHP(?float $val): string {
                if ($val === null || $val <= 0) return '';
                return $val == (int)$val ? (string)(int)$val : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
            }
        }
        $isLocked = !($canUpdatePrices ?? true);
        $activeGrade = $activeGrade ?? 'A';
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-2 lg:max-w-5xl xl:max-w-6xl lg:gap-3 lg:px-4 lg:py-3">
        @include('purchasing.purchaser.partials.feedback')

        <div id="global-save-info" class="hidden rounded-xl border border-teal-200/90 bg-teal-50/90 px-4 py-2 text-xs font-bold text-teal-900 shadow-2xs"></div>

        {{-- Cutoff Status Notice Banner --}}
        @if ($isLocked)
            <div id="cutoff-lock-banner" class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/95 px-4 py-3 text-xs font-bold text-rose-900 shadow-sm">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <div>
                    <p class="font-black text-rose-950">Daily price editing closed at {{ $dailyPriceCutoffLabel ?? '09:00 AM' }}. Only Admin can update prices.</p>
                    <p class="text-[11px] font-medium text-rose-800">Purchasers can view all active prices in read-only mode. Input fields and autosave are locked.</p>
                </div>
            </div>
        @elseif (!empty($isAdmin) && !empty($isDailyPriceCutoffPassed))
            <div id="cutoff-admin-banner" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50/90 px-4 py-2 text-xs font-bold text-amber-900 shadow-2xs">
                <span class="inline-flex items-center rounded-lg bg-amber-200/90 px-2 py-0.5 text-[10px] font-black uppercase text-amber-900">Admin Override Active</span>
                <span>Daily price editing closed for purchasers at {{ $dailyPriceCutoffLabel ?? '09:00 AM' }}. You are authorized to update prices as an administrator.</span>
            </div>
        @endif

        {{-- Header section with Date Change Input --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-md">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#0f766e_100%)] px-4 py-3 sm:px-5">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-teal-300">Purchaser Pricing</p>
                        <h1 class="text-base sm:text-lg font-black tracking-tight text-white">{{ !empty($showGradeTabs) ? 'Daily Prices (Grades A / B / C)' : 'Daily Prices' }}</h1>
                        @if (!empty($showGradeTabs))
                            <p class="text-[10px] text-teal-200/80">Manage Grade A, B, and C Selling Prices. Single-grade focused view with keyboard isolation.</p>
                        @endif
                    </div>
                    
                    {{-- Interactive Date Picker --}}
                    <form id="daily-price-date-form" action="{{ route('purchaser.daily-prices') }}" method="GET" class="flex items-center gap-1.5">
                        <input type="hidden" name="grade" id="header-grade-input" value="{{ $activeGrade }}">
                        @if ($searchQuery)
                            <input type="hidden" name="search" value="{{ $searchQuery }}">
                        @endif
                        @if ($selectedCategory)
                            <input type="hidden" name="category_id" value="{{ $selectedCategory }}">
                        @endif
                        @if (!empty($doubleCheck))
                            <input type="hidden" name="double_check" value="1">
                        @endif
                        
                        <div class="relative flex items-center">
                            <input type="date"
                                   id="header-date-input"
                                   name="date"
                                   value="{{ $operationalDate->format('Y-m-d') }}"
                                   class="h-8 rounded-full border border-white/20 bg-white/10 px-3 text-[11px] font-bold text-teal-100 backdrop-blur-xs focus:bg-white/20 focus:outline-none focus:ring-2 focus:ring-teal-400/30 cursor-pointer">
                        </div>
                    </form>
                </div>
            </div>
        </section>

        {{-- Search & Category Filter Section --}}
        <form id="daily-price-search-form" action="{{ route('purchaser.daily-prices') }}" method="GET" class="flex flex-col gap-2 rounded-xl border border-slate-200/80 bg-white p-2 shadow-2xs">
            <input type="hidden" name="date" value="{{ $operationalDate->format('Y-m-d') }}">
            <input type="hidden" name="grade" id="filter-grade-input" value="{{ $activeGrade }}">
            
            <div class="flex min-w-0 gap-2">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input id="daily-price-search-input" type="search" name="search" value="{{ $searchQuery }}" placeholder="Search product name or SKU..." autocomplete="off" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50/80 pl-9 pr-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none focus:ring-3 focus:ring-teal-500/10 transition-all">
                </div>

                @if ($categories->isNotEmpty())
                    <select id="category-filter-select" name="category_id" class="h-9 rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 text-xs font-bold text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none shrink-0 cursor-pointer max-w-[130px] sm:max-w-[170px]">
                        <option value="all">All Categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ (string) $selectedCategory === (string) $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800 transition-colors shadow-2xs">Search</button>
            </div>

            <label class="inline-flex items-center gap-2 text-[11px] font-bold text-slate-700">
                <input
                    type="checkbox"
                    id="double-check-toggle"
                    name="double_check"
                    value="1"
                    {{ !empty($doubleCheck) ? 'checked' : '' }}
                    class="h-3.5 w-3.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                >
                Double Check Mode (show purchase value)
            </label>

            {{-- Category Quick Pills --}}
            @if ($categories->isNotEmpty())
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs scrollbar-none">
                    <a href="{{ route('purchaser.daily-prices', array_filter(['date' => $operationalDate->format('Y-m-d'), 'grade' => $activeGrade, 'search' => $searchQuery, 'category_id' => 'all', 'double_check' => !empty($doubleCheck) ? 1 : null])) }}"
                       class="category-pill-link shrink-0 px-2.5 py-1 rounded-lg font-bold text-[11px] transition-all {{ !$selectedCategory || $selectedCategory === 'all' ? 'bg-slate-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All
                    </a>
                    @foreach ($categories as $cat)
                        <a href="{{ route('purchaser.daily-prices', array_filter(['date' => $operationalDate->format('Y-m-d'), 'grade' => $activeGrade, 'search' => $searchQuery, 'category_id' => $cat->id, 'double_check' => !empty($doubleCheck) ? 1 : null])) }}"
                           class="category-pill-link shrink-0 px-2.5 py-1 rounded-lg font-bold text-[11px] transition-all {{ (string) $selectedCategory === (string) $cat->id ? 'bg-teal-700 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $cat->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </form>

        {{-- Grade Tab Navigation Bar / Cutoff Section --}}
        @if (!empty($showGradeTabs))
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-2xl border border-slate-200/90 bg-white p-2 sm:p-2.5 shadow-2xs">
                <div class="grid grid-cols-3 gap-1 sm:flex sm:items-center sm:gap-2 w-full sm:w-auto" role="tablist" aria-label="Grade Price Tabs">
                    <button type="button"
                            id="tab-btn-grade-a"
                            role="tab"
                            aria-selected="{{ $activeGrade === 'A' ? 'true' : 'false' }}"
                            onclick="switchGradeTab('A')"
                            class="grade-tab-btn flex items-center justify-center gap-1 px-1.5 py-2 sm:px-4 sm:py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $activeGrade === 'A' ? 'bg-slate-950 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <span>GRADE A</span>
                        <span class="tab-badge text-[9px] px-1 py-0.25 rounded-md font-bold {{ $activeGrade === 'A' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600' }}">Base</span>
                    </button>
                    <button type="button"
                            id="tab-btn-grade-b"
                            role="tab"
                            aria-selected="{{ $activeGrade === 'B' ? 'true' : 'false' }}"
                            onclick="switchGradeTab('B')"
                            class="grade-tab-btn flex items-center justify-center gap-1 px-1.5 py-2 sm:px-4 sm:py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $activeGrade === 'B' ? 'bg-teal-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <span>GRADE B</span>
                        <span class="tab-badge text-[9px] px-1 py-0.25 rounded-md font-bold {{ $activeGrade === 'B' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600' }}">{{ $globalPercentageB ?? 90 }}%</span>
                    </button>
                    <button type="button"
                            id="tab-btn-grade-c"
                            role="tab"
                            aria-selected="{{ $activeGrade === 'C' ? 'true' : 'false' }}"
                            onclick="switchGradeTab('C')"
                            class="grade-tab-btn flex items-center justify-center gap-1 px-1.5 py-2 sm:px-4 sm:py-2 rounded-xl text-xs font-black transition-all cursor-pointer {{ $activeGrade === 'C' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        <span>GRADE C</span>
                        <span class="tab-badge text-[9px] px-1 py-0.25 rounded-md font-bold {{ $activeGrade === 'C' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600' }}">{{ $globalPercentageC ?? 80 }}%</span>
                    </button>
                </div>

                {{-- Cutoff Badge Indicator --}}
                <div class="flex items-center justify-between sm:justify-start gap-2 px-1 text-xs font-semibold text-slate-600 shrink-0 whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Daily Cutoff: <strong class="text-slate-900">{{ $dailyPriceCutoffLabel ?? '09:00 AM' }}</strong></span>
                    </div>
                    <div>
                        @if ($isLocked)
                            <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-700">Locked</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">Open</span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- Single-Grade Clean Cutoff Bar --}}
            <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-white px-3.5 py-2 shadow-2xs text-xs font-semibold text-slate-600">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Daily Cutoff: <strong class="text-slate-900">{{ $dailyPriceCutoffLabel ?? '09:00 AM' }}</strong></span>
                </div>
                <div>
                    @if ($isLocked)
                        <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-700">Locked</span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-700">Open</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Product List with Single-Grade Tab Views --}}
        @if ($products->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-xs font-bold text-slate-500">
                No products found. Try adjusting your search, category or date filter.
            </div>
        @else
            <div class="daily-price-list flex flex-col gap-1.5">

                {{-- Column Header Bar - Grade A --}}
                <div id="grade-header-a" class="grade-header {{ $activeGrade !== 'A' ? 'hidden' : '' }} grid-cols-[minmax(105px,1.25fr)_56px_84px_82px] items-center gap-[6px] px-[10px] pb-1 pt-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400 select-none">
                    <div>PRODUCT</div>
                    <div class="text-right">{{ !empty($showGradeTabs) ? 'PREV (A)' : 'PREV' }}</div>
                    <div class="text-center">CHANGE</div>
                    <div class="text-right">{{ !empty($showGradeTabs) ? 'SELLING A' : strtoupper($operationalDate->format('d M')) }}</div>
                </div>

                @if (!empty($showGradeTabs))
                    {{-- Column Header Bar - Grade B --}}
                    <div id="grade-header-b" class="grade-header {{ $activeGrade !== 'B' ? 'hidden' : '' }} grid-cols-[minmax(130px,1.5fr)_68px_52px_68px_82px] items-center gap-2 px-[10px] pb-1 pt-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400 select-none">
                        <div>PRODUCT</div>
                        <div class="text-right">GRADE A REF</div>
                        <div class="text-center">PERCENT</div>
                        <div class="text-right">CALCULATED</div>
                        <div class="text-right">GRADE B</div>
                    </div>

                    {{-- Column Header Bar - Grade C --}}
                    <div id="grade-header-c" class="grade-header {{ $activeGrade !== 'C' ? 'hidden' : '' }} grid-cols-[minmax(130px,1.5fr)_68px_52px_68px_82px] items-center gap-2 px-[10px] pb-1 pt-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400 select-none">
                        <div>PRODUCT</div>
                        <div class="text-right">GRADE A REF</div>
                        <div class="text-center">PERCENT</div>
                        <div class="text-right">CALCULATED</div>
                        <div class="text-right">GRADE C</div>
                    </div>
                @endif

                @foreach ($products as $product)
                    <div id="product-row-{{ $product['id'] }}" class="daily-price-row">

                        {{-- Grade A Content Panel --}}
                        <div class="grade-panel grade-panel-a {{ $activeGrade !== 'A' ? 'hidden' : '' }} grade-grid-a" data-grade-panel="A">
                            {{-- Col 1: Product Cell --}}
                            <div class="product-cell">
                                <div class="product-title-wrap">
                                    <button type="button"
                                            class="product-name"
                                            onclick="openProductModal({{ json_encode($product) }})">
                                        {{ $product['name'] }}
                                    </button>
                                    <span class="product-unit">· {{ $product['unit'] }}</span>
                                    @if ($product['updated_by_name'])
                                        <span class="inline-flex items-center text-teal-600 shrink-0 cursor-pointer"
                                              onclick="event.stopPropagation(); openProductModal({{ json_encode($product) }})"
                                              title="Updated by {{ $product['updated_by_name'] }} at {{ $product['updated_time'] }}">
                                            <svg class="h-3 w-3 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-1 flex items-center gap-1.5">
                                    @if(auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('purchaser'))
                                        <form method="POST" action="{{ route('inventory.products.status.update', $product['public_uuid'] ?? $product['id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="show_in_purchaser_order" value="{{ $product['show_in_purchaser_order'] ? '0' : '1' }}">
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[9px] font-black transition-colors {{ $product['show_in_purchaser_order'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200' }}" title="Toggle Show/Hide status for Purchaser Order">
                                                <span class="relative inline-flex h-3 w-6 items-center rounded-full transition-colors {{ $product['show_in_purchaser_order'] ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-white shadow-xs transition-transform {{ $product['show_in_purchaser_order'] ? 'translate-x-3' : 'translate-x-0.5' }}"></span>
                                                </span>
                                                <span>{{ $product['show_in_purchaser_order'] ? 'Show in PO' : 'Hidden in PO' }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[9px] font-black {{ $product['show_in_purchaser_order'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500' }}">
                                            {{ $product['show_in_purchaser_order'] ? 'Show in PO' : 'Hidden in PO' }}
                                        </span>
                                    @endif
                                </div>
                                <div id="row-cost-avg-{{ $product['id'] }}" class="product-cost-info">
                                    @if (($product['purchaser_avg_price'] ?? null) !== null)
                                        Cost Avg: ₹{{ formatPriceCompactPHP($product['purchaser_avg_price']) }}
                                    @endif
                                </div>
                                <div id="row-selling-a-{{ $product['id'] }}" class="updater-info">
                                    @if (($product['selling_price_a'] ?? null) !== null)
                                        Selling A: ₹{{ formatPriceCompactPHP($product['selling_price_a']) }} <span class="text-[9px] font-semibold text-slate-400 bg-slate-100 border border-slate-200/50 rounded-sm px-1 py-0.25 ml-1">Updates Shop Order</span>
                                    @endif
                                </div>
                                @if (!empty($doubleCheck))
                                    <div id="row-purchase-check-{{ $product['id'] }}" class="updater-info">
                                        @if (($product['purchase_today'] ?? null) !== null)
                                            Purchase: ₹{{ formatPriceCompactPHP($product['purchase_today']) }}
                                        @endif
                                    </div>
                                @endif
                                <div id="row-updater-{{ $product['id'] }}" class="updater-info">
                                    @if ($product['updated_by_name'])
                                        {{ $product['updated_by_name'] }} &middot; {{ $product['updated_time'] }}
                                    @endif
                                </div>
                            </div>

                            {{-- Col 2: Previous Price --}}
                            <div class="previous-price" id="row-prev-{{ $product['id'] }}">
                                @if ($product['previous_price'])
                                    ₹{{ formatPriceCompactPHP($product['previous_price']) }}
                                @else
                                    —
                                @endif
                            </div>

                            {{-- Col 3: Change Badge --}}
                            <div class="price-status-col" id="row-badge-{{ $product['id'] }}">
                                @if ($product['price_state'] === 'not_set')
                                    <div class="price-status not-set">Not set</div>
                                @elseif ($product['price_state'] === 'no_previous')
                                    <div class="price-status increase">₹{{ formatPriceCompactPHP($product['price_today']) }}</div>
                                @elseif ($product['price_state'] === 'increased')
                                    <div class="price-status increase">▲ ₹{{ number_format($product['diff_amount'], 0) }} · {{ $product['diff_percentage'] }}%</div>
                                @elseif ($product['price_state'] === 'decreased')
                                    <div class="price-status decrease">▼ ₹{{ number_format(abs($product['diff_amount']), 0) }} · {{ $product['diff_percentage'] }}%</div>
                                @elseif ($product['price_state'] === 'no_change')
                                    <div class="price-status no-change">— 0%</div>
                                @endif
                            </div>

                            {{-- Col 4: Selling A Input --}}
                            <div class="price-col-a">
                                <div class="price-input-wrap {{ $isLocked ? 'locked' : '' }}" id="wrap-a-{{ $product['id'] }}">
                                    <span class="price-currency">₹</span>
                                    <input type="text"
                                           inputmode="decimal"
                                           id="price-{{ $product['id'] }}"
                                           value="{{ formatPriceCompactPHP($product['price_today']) }}"
                                           placeholder="—"
                                           tabindex="{{ $activeGrade === 'A' ? '0' : '-1' }}"
                                           {{ $isLocked ? 'disabled readonly' : '' }}
                                           oninput="handlePriceInput(this, {{ $product['id'] }})"
                                           class="price-input">
                                </div>
                            </div>
                        </div>

                        @if (!empty($showGradeTabs))
                            {{-- Grade B Content Panel --}}
                            <div class="grade-panel grade-panel-b {{ $activeGrade !== 'B' ? 'hidden' : '' }} grade-grid-bc" data-grade-panel="B">
                                {{-- Col 1: Product Cell --}}
                                <div class="product-cell">
                                    <div class="product-title-wrap">
                                        <button type="button"
                                                class="product-name"
                                                onclick="openProductModal({{ json_encode($product) }})">
                                            {{ $product['name'] }}
                                        </button>
                                        <span class="product-unit">· {{ $product['unit'] }}</span>
                                    </div>
                                    <div class="product-cost-info">
                                        Grade B Selling Tier
                                    </div>
                                </div>

                                {{-- Col 2: Grade A Reference Price --}}
                                <div class="reference-price" id="ref-a-b-{{ $product['id'] }}">
                                    ₹{{ formatPriceCompactPHP($product['price_today'] ?? $product['selling_price_a']) ?: '—' }}
                                </div>

                                {{-- Col 3: Configured Percentage --}}
                                <div class="flex justify-center items-center">
                                    <span class="percent-badge" id="percent-badge-b-{{ $product['id'] }}">{{ $product['percentage_b'] ?? $globalPercentageB ?? 90 }}%</span>
                                </div>

                                {{-- Col 4: Calculated Default Price --}}
                                <div class="calc-price" id="calc-display-b-{{ $product['id'] }}">
                                    ₹{{ formatPriceCompactPHP($product['calc_price_b']) ?: '—' }}
                                </div>

                                {{-- Col 5: Grade B Manual Input + Reset Button --}}
                                <div class="grade-cell grade-b-cell" id="grade-b-cell-{{ $product['id'] }}">
                                    <div class="price-input-wrap {{ $isLocked ? 'locked' : '' }}" id="wrap-b-{{ $product['id'] }}">
                                        <span class="price-currency">₹</span>
                                        <input type="text"
                                               inputmode="decimal"
                                               id="price-b-{{ $product['id'] }}"
                                               value="{{ formatPriceCompactPHP($product['selling_price_b'] ?? $product['calc_price_b']) }}"
                                               placeholder="—"
                                               tabindex="{{ $activeGrade === 'B' ? '0' : '-1' }}"
                                               {{ $isLocked ? 'disabled readonly' : '' }}
                                               oninput="handleGradeInput(this, {{ $product['id'] }}, 'B')"
                                               class="price-input">
                                    </div>
                                    <span id="updater-b-{{ $product['id'] }}" class="updater-name-tag text-[9px] font-semibold text-slate-400 truncate max-w-[82px] text-center" title="{{ $product['updated_by_b'] ?? '' }}">
                                        {{ $product['updated_by_b'] ?? '' }}
                                    </span>
                                    @if (!$isLocked)
                                        <button type="button"
                                                id="reset-b-{{ $product['id'] }}"
                                                onclick="resetGradeToDefault({{ $product['id'] }}, 'B')"
                                                class="reset-default-btn {{ !empty($product['is_manual_b']) ? '' : 'hidden' }}">
                                            Reset to Default
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Grade C Content Panel --}}
                            <div class="grade-panel grade-panel-c {{ $activeGrade !== 'C' ? 'hidden' : '' }} grade-grid-bc" data-grade-panel="C">
                                {{-- Col 1: Product Cell --}}
                                <div class="product-cell">
                                    <div class="product-title-wrap">
                                        <button type="button"
                                                class="product-name"
                                                onclick="openProductModal({{ json_encode($product) }})">
                                            {{ $product['name'] }}
                                        </button>
                                        <span class="product-unit">· {{ $product['unit'] }}</span>
                                    </div>
                                    <div class="product-cost-info">
                                        Grade C Selling Tier
                                    </div>
                                </div>

                                {{-- Col 2: Grade A Reference Price --}}
                                <div class="reference-price" id="ref-a-c-{{ $product['id'] }}">
                                    ₹{{ formatPriceCompactPHP($product['price_today'] ?? $product['selling_price_a']) ?: '—' }}
                                </div>

                                {{-- Col 3: Configured Percentage --}}
                                <div class="flex justify-center items-center">
                                    <span class="percent-badge" id="percent-badge-c-{{ $product['id'] }}">{{ $product['percentage_c'] ?? $globalPercentageC ?? 80 }}%</span>
                                </div>

                                {{-- Col 4: Calculated Default Price --}}
                                <div class="calc-price" id="calc-display-c-{{ $product['id'] }}">
                                    ₹{{ formatPriceCompactPHP($product['calc_price_c']) ?: '—' }}
                                </div>

                                {{-- Col 5: Grade C Manual Input + Reset Button --}}
                                <div class="grade-cell grade-c-cell" id="grade-c-cell-{{ $product['id'] }}">
                                    <div class="price-input-wrap {{ $isLocked ? 'locked' : '' }}" id="wrap-c-{{ $product['id'] }}">
                                        <span class="price-currency">₹</span>
                                        <input type="text"
                                               inputmode="decimal"
                                               id="price-c-{{ $product['id'] }}"
                                               value="{{ formatPriceCompactPHP($product['selling_price_c'] ?? $product['calc_price_c']) }}"
                                               placeholder="—"
                                               tabindex="{{ $activeGrade === 'C' ? '0' : '-1' }}"
                                               {{ $isLocked ? 'disabled readonly' : '' }}
                                               oninput="handleGradeInput(this, {{ $product['id'] }}, 'C')"
                                               class="price-input">
                                    </div>
                                    <span id="updater-c-{{ $product['id'] }}" class="updater-name-tag text-[9px] font-semibold text-slate-400 truncate max-w-[82px] text-center" title="{{ $product['updated_by_c'] ?? '' }}">
                                        {{ $product['updated_by_c'] ?? '' }}
                                    </span>
                                    @if (!$isLocked)
                                        <button type="button"
                                                id="reset-c-{{ $product['id'] }}"
                                                onclick="resetGradeToDefault({{ $product['id'] }}, 'C')"
                                                class="reset-default-btn {{ !empty($product['is_manual_c']) ? '' : 'hidden' }}">
                                            Reset to Default
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Details Popup Modal (White Card Design, Top Set Price, Top 3 History) --}}
    <div id="product-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs hidden transition-opacity">
        <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white p-5 shadow-2xl border border-slate-100 transition-all max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 id="modal-product-name" class="text-base font-black text-slate-900">Product Name</h2>
                    <p id="modal-product-unit" class="text-xs font-semibold text-slate-400 mt-0.5">Purchase Unit: 1 KG</p>
                </div>
                <button type="button" onclick="closeProductModal()" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                {{-- 1. SET TODAY'S PRICE (ON TOP) - Clean White/Light Card --}}
                <div class="rounded-2xl border border-teal-200/80 bg-teal-50/40 p-4 space-y-3 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <label for="modal-price-input" class="block text-[10px] font-black uppercase tracking-wider text-teal-900">SET GRADE A SELLING PRICE (GROUP A) FOR {{ $operationalDate->format('d M Y') }}</label>
                        <span id="modal-save-status" class="text-[10px] font-bold text-teal-700"></span>
                    </div>
                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-sm font-black text-slate-400 select-none">₹</span>
                        <input type="text"
                               inputmode="decimal"
                               id="modal-price-input"
                               placeholder="—"
                               {{ $isLocked ? 'disabled readonly' : '' }}
                               oninput="handleModalPriceInput(this)"
                               class="h-11 w-full rounded-xl border border-teal-300 bg-white pl-8 pr-3 text-right text-base font-black text-slate-900 shadow-xs focus:border-teal-600 focus:outline-none focus:ring-3 focus:ring-teal-500/20 transition-all {{ $isLocked ? 'cursor-not-allowed bg-slate-100 text-slate-500' : '' }}">
                    </div>
                    @if (!$isLocked)
                        <button type="button"
                                id="modal-save-btn"
                                onclick="triggerModalSave()"
                                class="w-full rounded-xl bg-teal-600 hover:bg-teal-700 active:scale-98 text-white font-black text-xs uppercase tracking-wider py-2.5 transition-all shadow-sm cursor-pointer">
                            SAVE GRADE A PRICE
                        </button>
                        <button type="button"
                                id="modal-refresh-invoices-btn"
                                onclick="triggerInvoiceRefresh()"
                                class="w-full rounded-xl border border-teal-600 bg-white text-teal-700 hover:bg-teal-50 font-black text-xs uppercase tracking-wider py-2.5 transition-all shadow-sm cursor-pointer">
                            REFRESH INVOICES
                        </button>
                    @else
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-center text-xs font-bold text-rose-800">
                            Price editing closed at {{ $dailyPriceCutoffLabel ?? '09:00 AM' }}
                        </div>
                    @endif
                    <p id="modal-invoice-update-count" class="text-[11px] font-bold text-teal-800 text-center"></p>
                </div>

                {{-- 2. Price Summary Grid --}}
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">GRADE A SELLING PRICE ({{ $operationalDate->format('d M') }})</p>
                            <p id="modal-today-price" class="text-base font-black text-slate-900 mt-0.5">₹0.00</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">PREVIOUS GRADE A PRICE</p>
                            <p id="modal-previous-price" class="text-base font-black text-slate-900 mt-0.5">₹0.00</p>
                        </div>
                    </div>

                    {{-- Change Badge --}}
                    <div class="border-t border-slate-200/60 pt-2.5">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400 mb-1">CHANGE</p>
                        <div id="modal-change-badge">
                            <!-- Populated dynamically by JS -->
                        </div>
                    </div>
                </div>

                {{-- 3. Recent Price History (Top 3 Recent) --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 space-y-2.5 shadow-2xs">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">RECENT GRADE A PRICE HISTORY (TOP 3)</p>
                    <div id="modal-history-list" class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        <!-- Populated dynamically by JS -->
                    </div>
                </div>

                {{-- 4. Audit Footer --}}
                <div id="modal-audit-info" class="rounded-2xl bg-emerald-50/80 border border-emerald-200/60 p-3 text-xs text-emerald-950 hidden space-y-0.5">
                    <p id="modal-audit-username" class="font-black text-emerald-950">Updated by User</p>
                    <p id="modal-audit-timestamp" class="text-[11px] font-medium text-emerald-800">Timestamp</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const debounceTimers = {};
        const pendingDebounceCallbacks = {};
        const inFlightRequests = {};
        const requestSequence = {};
        const csrfToken = '{{ csrf_token() }}';
        const updateUrl = '{{ route("purchaser.daily-prices.update") }}';
        const currentDate = '{{ $operationalDate->format("Y-m-d") }}';
        let currentActiveGrade = '{{ $activeGrade }}';
        let isPriceEditingLocked = {{ $isLocked ? 'true' : 'false' }};
        const cutoffLabelFormatted = '{{ $dailyPriceCutoffLabel ?? "09:00 AM" }}';
        let activeModalProduct = null;
        let lastScrollY = 0;
        let isNavigating = false;

        function hasPendingChanges() {
            return Object.keys(pendingDebounceCallbacks).length > 0 || Object.keys(inFlightRequests).length > 0;
        }

        function safelySubmitForm(form) {
            if (isNavigating) return;
            isNavigating = true;
            HTMLFormElement.prototype.submit.call(form);
        }

        function safelyNavigateTo(url) {
            if (isNavigating) return;
            isNavigating = true;
            window.location.href = url;
        }

        function awaitPendingSavesThen(action) {
            if (isNavigating) return;

            // 1. Immediately flush all debounced callbacks into fetch requests
            flushPendingDebounces();

            const pendingKeys = Object.keys(inFlightRequests);
            if (pendingKeys.length === 0) {
                if (typeof action === 'function') {
                    action();
                }
                return;
            }

            showGlobalSaveInfo('Saving pending price changes before navigating...');

            const promises = Object.values(inFlightRequests);
            Promise.allSettled(promises).then(results => {
                const hasFailures = results.some(r => {
                    if (r.status === 'rejected') return true;
                    if (r.value && r.value.success === false) return true;
                    return false;
                });

                if (hasFailures) {
                    showGlobalSaveInfo('Warning: One or more price updates could not be saved. Navigation paused.', true);
                    alert('A daily price update could not be saved. Please verify your prices before leaving this page.');
                    isNavigating = false;
                    return;
                }

                if (typeof action === 'function') {
                    action();
                }
            });
        }

        const productPriceMeta = {
            @foreach ($products as $p)
                {{ $p['id'] }}: {
                    percentage_b: {{ (float) ($p['percentage_b'] ?? $globalPercentageB ?? 90) }},
                    percentage_c: {{ (float) ($p['percentage_c'] ?? $globalPercentageC ?? 80) }},
                    is_manual_b: {{ !empty($p['is_manual_b']) ? 'true' : 'false' }},
                    is_manual_c: {{ !empty($p['is_manual_c']) ? 'true' : 'false' }},
                },
            @endforeach
        };

        function formatPriceCompactJS(val) {
            if (val === null || val === undefined || isNaN(val) || val <= 0) return '';
            const num = parseFloat(val);
            if (num % 1 === 0) return num.toString();
            return num.toFixed(2).replace(/\.?0+$/, '');
        }

        // Flush any pending debounce timers immediately (e.g. before switching tabs or filtering)
        function flushPendingDebounces() {
            Object.keys(pendingDebounceCallbacks).forEach(key => {
                if (debounceTimers[key]) {
                    clearTimeout(debounceTimers[key]);
                    delete debounceTimers[key];
                }
                if (typeof pendingDebounceCallbacks[key] === 'function') {
                    const cb = pendingDebounceCallbacks[key];
                    delete pendingDebounceCallbacks[key];
                    cb();
                }
            });
        }

        function switchGradeTab(grade) {
            if (grade === currentActiveGrade) return;

            // Immediately flush any unsaved debounce work before leaving the tab
            flushPendingDebounces();

            currentActiveGrade = grade;

            // 1. Update Tab buttons
            ['A', 'B', 'C'].forEach(g => {
                const btn = document.getElementById('tab-btn-grade-' + g.toLowerCase());
                if (!btn) return;
                const isSelected = g === grade;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                if (isSelected) {
                    btn.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
                    if (g === 'A') btn.classList.add('bg-slate-950', 'text-white', 'shadow-xs');
                    else if (g === 'B') btn.classList.add('bg-teal-700', 'text-white', 'shadow-xs');
                    else if (g === 'C') btn.classList.add('bg-emerald-700', 'text-white', 'shadow-xs');
                } else {
                    btn.classList.remove('bg-slate-950', 'bg-teal-700', 'bg-emerald-700', 'text-white', 'shadow-xs');
                    btn.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
                }

                const badge = btn.querySelector('.tab-badge');
                if (badge) {
                    if (isSelected) {
                        badge.classList.remove('bg-slate-200', 'text-slate-600');
                        badge.classList.add('bg-white/20', 'text-white');
                    } else {
                        badge.classList.remove('bg-white/20', 'text-white');
                        badge.classList.add('bg-slate-200', 'text-slate-600');
                    }
                }
            });

            // 2. Toggle column headers
            ['A', 'B', 'C'].forEach(g => {
                const hdr = document.getElementById('grade-header-' + g.toLowerCase());
                if (hdr) {
                    if (g === grade) {
                        hdr.classList.remove('hidden');
                    } else {
                        hdr.classList.add('hidden');
                    }
                }
            });

            // 3. Toggle product row panels & manage keyboard tabindex
            document.querySelectorAll('.grade-panel').forEach(panel => {
                const panelGrade = panel.getAttribute('data-grade-panel');
                const isPanelActive = panelGrade === grade;
                if (isPanelActive) {
                    panel.classList.remove('hidden');
                    panel.querySelectorAll('.price-input').forEach(input => {
                        input.setAttribute('tabindex', '0');
                    });
                } else {
                    panel.classList.add('hidden');
                    panel.querySelectorAll('.price-input').forEach(input => {
                        input.setAttribute('tabindex', '-1');
                    });
                }
            });

            // 4. Update hidden inputs for search forms and URL state
            const filterGradeInput = document.getElementById('filter-grade-input');
            if (filterGradeInput) filterGradeInput.value = grade;
            const headerGradeInput = document.getElementById('header-grade-input');
            if (headerGradeInput) headerGradeInput.value = grade;

            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('grade', grade);
                window.history.replaceState({}, '', url.toString());
            }
        }

        function handlePriceInput(inputEl, productId) {
            if (isPriceEditingLocked) return;

            const val = parseFloat(inputEl.value);
            const meta = productPriceMeta[productId];

            // In real-time, recalculate non-manual Grade B & C references
            if (meta) {
                const refB = document.getElementById('ref-a-b-' + productId);
                const refC = document.getElementById('ref-a-c-' + productId);
                if (refB) refB.textContent = (!isNaN(val) && val > 0) ? `₹${formatPriceCompactJS(val)}` : '—';
                if (refC) refC.textContent = (!isNaN(val) && val > 0) ? `₹${formatPriceCompactJS(val)}` : '—';

                if (!meta.is_manual_b) {
                    const inputB = document.getElementById('price-b-' + productId);
                    const calcBEl = document.getElementById('calc-display-b-' + productId);
                    const calcB = (!isNaN(val) && val > 0) ? Math.round(val * (meta.percentage_b / 100) * 100) / 100 : '';
                    if (inputB) inputB.value = formatPriceCompactJS(calcB);
                    if (calcBEl) calcBEl.textContent = calcB ? `₹${formatPriceCompactJS(calcB)}` : '—';
                }
                if (!meta.is_manual_c) {
                    const inputC = document.getElementById('price-c-' + productId);
                    const calcCEl = document.getElementById('calc-display-c-' + productId);
                    const calcC = (!isNaN(val) && val > 0) ? Math.round(val * (meta.percentage_c / 100) * 100) / 100 : '';
                    if (inputC) inputC.value = formatPriceCompactJS(calcC);
                    if (calcCEl) calcCEl.textContent = calcC ? `₹${formatPriceCompactJS(calcC)}` : '—';
                }
            }

            const timerKey = `product_${productId}_A`;
            if (debounceTimers[timerKey]) {
                clearTimeout(debounceTimers[timerKey]);
            }

            // Sync bi-directionally with modal input
            if (activeModalProduct && activeModalProduct.id === productId) {
                const modalInput = document.getElementById('modal-price-input');
                if (modalInput && modalInput !== document.activeElement) {
                    modalInput.value = inputEl.value;
                }
            }

            if (isNaN(val) || val <= 0) {
                delete pendingDebounceCallbacks[timerKey];
                return;
            }

            pendingDebounceCallbacks[timerKey] = () => {
                savePriceAuto(inputEl, productId, val);
            };

            debounceTimers[timerKey] = setTimeout(() => {
                delete debounceTimers[timerKey];
                if (typeof pendingDebounceCallbacks[timerKey] === 'function') {
                    const cb = pendingDebounceCallbacks[timerKey];
                    delete pendingDebounceCallbacks[timerKey];
                    cb();
                }
            }, 600);
        }

        function handleGradeInput(inputEl, productId, grade) {
            if (isPriceEditingLocked) return;

            const timerKey = `product_${productId}_${grade}`;
            if (debounceTimers[timerKey]) {
                clearTimeout(debounceTimers[timerKey]);
            }

            const val = parseFloat(inputEl.value);
            if (isNaN(val) || val <= 0) {
                delete pendingDebounceCallbacks[timerKey];
                return;
            }

            pendingDebounceCallbacks[timerKey] = () => {
                saveGradePrice(inputEl, productId, grade, val, true);
            };

            debounceTimers[timerKey] = setTimeout(() => {
                delete debounceTimers[timerKey];
                if (typeof pendingDebounceCallbacks[timerKey] === 'function') {
                    const cb = pendingDebounceCallbacks[timerKey];
                    delete pendingDebounceCallbacks[timerKey];
                    cb();
                }
            }, 600);
        }

        function saveGradePrice(inputEl, productId, grade, priceVal, isManual = true) {
            const gradeLower = grade.toLowerCase();
            const updaterCol = document.getElementById(`updater-${gradeLower}-${productId}`);
            const updaterRow = document.getElementById('row-updater-' + productId);
            const reqKey = `${productId}_${grade}`;

            requestSequence[reqKey] = (requestSequence[reqKey] || 0) + 1;
            const currentSeq = requestSequence[reqKey];

            if (updaterCol) updaterCol.textContent = 'Saving...';

            const reqPromise = fetch(updateUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    date: currentDate,
                    prices: [{
                        product_id: productId,
                        grade: grade,
                        price: priceVal,
                        is_manual: isManual
                    }]
                })
            })
            .then(res => {
                if (res.status === 403) {
                    return res.json().then(data => {
                        handleCutoffLockedNotice(data.message);
                        throw new Error(data.message || 'Cutoff reached. 403 Forbidden.');
                    });
                }
                if (!res.ok) {
                    throw new Error(`HTTP error ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (currentSeq !== requestSequence[reqKey]) return data; // Out of order response ignored

                if (data.success) {
                    const meta = productPriceMeta[productId];
                    if (meta) meta['is_manual_' + gradeLower] = isManual;

                    const resetBtn = document.getElementById(`reset-${gradeLower}-${productId}`);
                    if (resetBtn) {
                        if (isManual) resetBtn.classList.remove('hidden');
                        else resetBtn.classList.add('hidden');
                    }

                    if (inputEl) {
                        const wrap = inputEl.closest('.price-input-wrap');
                        if (wrap) {
                            wrap.style.borderColor = '#10b981';
                            wrap.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => {
                                wrap.style.borderColor = '#d9e2ec';
                                wrap.style.backgroundColor = '#f8fafc';
                            }, 1200);
                        }
                    }

                    const updaterName = data.updated_by_name || 'Saved';
                    if (updaterCol) {
                        updaterCol.textContent = updaterName;
                        updaterCol.title = updaterName;
                    }
                    if (updaterRow) {
                        updaterRow.textContent = `${updaterName} · ${data.updated_time || ''}`;
                    }
                }
                return data;
            })
            .catch(err => {
                console.error(`Save grade ${grade} price error:`, err);
                if (updaterCol) updaterCol.textContent = 'Failed';
                if (inputEl) {
                    const wrap = inputEl.closest('.price-input-wrap');
                    if (wrap) {
                        wrap.style.borderColor = '#ef4444';
                        wrap.style.backgroundColor = '#fef2f2';
                    }
                }
                throw err;
            })
            .finally(() => {
                delete inFlightRequests[reqKey];
            });

            inFlightRequests[reqKey] = reqPromise;
            return reqPromise;
        }

        function resetGradeToDefault(productId, grade) {
            if (isPriceEditingLocked) return Promise.resolve();

            const gradeLower = grade.toLowerCase();
            const inputEl = document.getElementById(`price-${gradeLower}-${productId}`);
            const updaterCol = document.getElementById(`updater-${gradeLower}-${productId}`);
            const resetBtn = document.getElementById(`reset-${gradeLower}-${productId}`);
            const reqKey = `reset_${productId}_${grade}`;

            if (resetBtn) resetBtn.textContent = 'Resetting...';

            const reqPromise = fetch(updateUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    date: currentDate,
                    prices: [{
                        product_id: productId,
                        grade: grade,
                        reset_to_default: true
                    }]
                })
            })
            .then(res => {
                if (res.status === 403) {
                    return res.json().then(data => {
                        handleCutoffLockedNotice(data.message);
                        throw new Error(data.message || 'Cutoff reached. 403 Forbidden.');
                    });
                }
                if (!res.ok) {
                    throw new Error(`HTTP error ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    const meta = productPriceMeta[productId];
                    if (meta) meta['is_manual_' + gradeLower] = false;

                    const defaultVal = grade === 'B'
                        ? (data.calc_price_b ?? data.selling_price_b)
                        : (data.calc_price_c ?? data.selling_price_c);

                    if (inputEl) {
                        inputEl.value = formatPriceCompactJS(defaultVal);
                        const wrap = inputEl.closest('.price-input-wrap');
                        if (wrap) {
                            wrap.style.borderColor = '#10b981';
                            wrap.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => {
                                wrap.style.borderColor = '#d9e2ec';
                                wrap.style.backgroundColor = '#f8fafc';
                            }, 1200);
                        }
                    }

                    if (resetBtn) {
                        resetBtn.textContent = 'Reset to Default';
                        resetBtn.classList.add('hidden');
                    }

                    const updaterName = data.updated_by_name || 'Reset to Default';
                    if (updaterCol) {
                        updaterCol.textContent = updaterName;
                        updaterCol.title = updaterName;
                    }

                    showGlobalSaveInfo(`Grade ${grade} price reset to default formula.`);
                }
                return data;
            })
            .catch(err => {
                console.error(`Reset grade ${grade} error:`, err);
                if (resetBtn) resetBtn.textContent = 'Reset to Default';
                throw err;
            })
            .finally(() => {
                delete inFlightRequests[reqKey];
            });

            inFlightRequests[reqKey] = reqPromise;
            return reqPromise;
        }

        function handleCutoffLockedNotice(customMessage) {
            isPriceEditingLocked = true;
            const message = customMessage || `Daily price editing closed at ${cutoffLabelFormatted}. Only Admin can update prices.`;

            // Disable all price inputs immediately
            document.querySelectorAll('.price-input').forEach(input => {
                input.disabled = true;
                input.readOnly = true;
                const wrap = input.closest('.price-input-wrap');
                if (wrap) wrap.classList.add('locked');
            });

            // Disable modal actions
            const modalSaveBtn = document.getElementById('modal-save-btn');
            const modalRefreshBtn = document.getElementById('modal-refresh-invoices-btn');
            if (modalSaveBtn) modalSaveBtn.disabled = true;
            if (modalRefreshBtn) modalRefreshBtn.disabled = true;

            showGlobalSaveInfo(message);
        }

        function handleModalPriceInput(modalInputEl) {
            if (!activeModalProduct) return;

            const mainInputEl = document.getElementById('price-' + activeModalProduct.id);
            if (mainInputEl) {
                mainInputEl.value = modalInputEl.value;
                handlePriceInput(mainInputEl, activeModalProduct.id);
            }
        }

        function triggerModalSave() {
            if (!activeModalProduct || isPriceEditingLocked) return;
            const modalInput = document.getElementById('modal-price-input');
            const val = parseFloat(modalInput.value);
            if (isNaN(val) || val <= 0) return;

            const mainInput = document.getElementById('price-' + activeModalProduct.id);
            savePriceAuto(mainInput, activeModalProduct.id, val, { refreshInvoicesOnly: false });
        }

        function triggerInvoiceRefresh() {
            if (!activeModalProduct || isPriceEditingLocked) return;

            const modalInput = document.getElementById('modal-price-input');
            const val = parseFloat(modalInput.value);
            if (isNaN(val) || val <= 0) return;

            const mainInput = document.getElementById('price-' + activeModalProduct.id);
            savePriceAuto(mainInput, activeModalProduct.id, val, { refreshInvoicesOnly: true });
        }

        function setModalActionLoading(isLoading, actionLabel = 'Saving...') {
            const saveBtn = document.getElementById('modal-save-btn');
            const refreshBtn = document.getElementById('modal-refresh-invoices-btn');

            if (saveBtn) {
                saveBtn.disabled = isLoading;
                saveBtn.classList.toggle('opacity-60', isLoading);
                saveBtn.classList.toggle('cursor-not-allowed', isLoading);
            }

            if (refreshBtn) {
                refreshBtn.disabled = isLoading;
                refreshBtn.classList.toggle('opacity-60', isLoading);
                refreshBtn.classList.toggle('cursor-not-allowed', isLoading);
            }

            const modalStatusEl = document.getElementById('modal-save-status');
            if (modalStatusEl && isLoading) {
                modalStatusEl.textContent = actionLabel;
            }
        }

        function savePriceAuto(inputEl, productId, priceValue, options = {}) {
            if (isPriceEditingLocked) return Promise.resolve();

            const refreshInvoicesOnly = options.refreshInvoicesOnly === true;
            const modalStatusEl = document.getElementById('modal-save-status');
            const updaterEl = document.getElementById('row-updater-' + productId);
            const reqKey = `${productId}_A`;

            requestSequence[reqKey] = (requestSequence[reqKey] || 0) + 1;
            const currentSeq = requestSequence[reqKey];

            if (updaterEl) {
                updaterEl.textContent = refreshInvoicesOnly ? 'Refreshing invoices...' : 'Saving...';
            }
            if (modalStatusEl && activeModalProduct && activeModalProduct.id === productId) {
                modalStatusEl.textContent = refreshInvoicesOnly ? 'Refreshing invoices...' : 'Saving...';
            }

            if (activeModalProduct && activeModalProduct.id === productId) {
                setModalActionLoading(true, refreshInvoicesOnly ? 'Refreshing invoices...' : 'Saving...');
            }

            const reqPromise = fetch(updateUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    date: currentDate,
                    refresh_invoices_only: refreshInvoicesOnly,
                    prices: [
                        {
                            product_id: productId,
                            purchase_price: priceValue
                        }
                    ]
                })
            })
            .then(res => {
                if (res.status === 403) {
                    return res.json().then(data => {
                        handleCutoffLockedNotice(data.message);
                        throw new Error(data.message || 'Cutoff reached. 403 Forbidden.');
                    });
                }
                if (!res.ok) {
                    throw new Error(`HTTP error ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (currentSeq !== requestSequence[reqKey]) return data; // Out of order response ignored

                if (data.success) {
                    const invoiceSyncOk = data.invoice_sync_ok === true;
                    updateModalInvoiceCount(data);

                    if (!invoiceSyncOk) {
                        showGlobalSaveInfo(data.global_update_message || 'Price saved, but related invoice update check failed.');

                        if (updaterEl) {
                            updaterEl.textContent = 'Invoice sync failed';
                        }

                        if (modalStatusEl && activeModalProduct && activeModalProduct.id === productId) {
                            modalStatusEl.textContent = data.global_update_message || 'Invoice sync failed';
                        }

                        return data;
                    }

                    // Update DOM elements on row
                    updateRowDOM(data);
                    if (data.global_update_message) {
                        showGlobalSaveInfo(data.global_update_message);
                    }

                    // If modal is open for this product, update modal DOM
                    if (activeModalProduct && activeModalProduct.id === productId) {
                        activeModalProduct.price_today = data.today_price;
                        activeModalProduct.previous_price = data.previous_price;
                        activeModalProduct.diff_amount = data.diff_amount;
                        activeModalProduct.diff_percentage = data.diff_percentage;
                        activeModalProduct.price_state = data.price_state;
                        activeModalProduct.selling_price_a = data.selling_price_a;
                        activeModalProduct.updated_by_name = data.updated_by_name;
                        activeModalProduct.updated_time = data.updated_time;
                        activeModalProduct.updated_at_formatted = data.updated_at_formatted;
                        activeModalProduct.history = data.history;

                        populateModalContent(activeModalProduct);

                        if (modalStatusEl) {
                            modalStatusEl.textContent = data.global_update_message || (refreshInvoicesOnly ? 'Invoice refresh completed' : '✓ Saved');
                            setTimeout(() => { modalStatusEl.textContent = ''; }, 3000);
                        }
                    }

                    if (inputEl) {
                        const wrap = inputEl.closest('.price-input-wrap');
                        if (wrap) {
                            wrap.style.borderColor = '#10b981';
                            wrap.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => {
                                wrap.style.borderColor = '#d9e2ec';
                                wrap.style.backgroundColor = '#f8fafc';
                            }, 1500);
                        }
                    }
                }
                return data;
            })
            .catch(err => {
                console.error('Auto-save error:', err);
                if (updaterEl) {
                    updaterEl.textContent = 'Failed';
                }
                if (modalStatusEl && activeModalProduct && activeModalProduct.id === productId) {
                    modalStatusEl.textContent = refreshInvoicesOnly ? 'Invoice refresh failed' : 'Failed';
                }
                if (inputEl) {
                    const wrap = inputEl.closest('.price-input-wrap');
                    if (wrap) {
                        wrap.style.borderColor = '#ef4444';
                        wrap.style.backgroundColor = '#fef2f2';
                    }
                }
                throw err;
            })
            .finally(() => {
                if (activeModalProduct && activeModalProduct.id === productId) {
                    setModalActionLoading(false);
                }
                delete inFlightRequests[reqKey];
            });

            inFlightRequests[reqKey] = reqPromise;
            return reqPromise;
        }

        function updateModalInvoiceCount(data) {
            const countEl = document.getElementById('modal-invoice-update-count');
            if (!countEl) return;

            const updated = Number(data?.invoice_updates_count ?? 0);
            const targeted = Number(data?.invoice_updates_targeted_count ?? 0);
            const skipped = Number(data?.invoice_updates_skipped_count ?? 0);
            const failed = Number(data?.invoice_updates_failed_count ?? 0);

            if (!Number.isFinite(updated) || !Number.isFinite(targeted) || !Number.isFinite(skipped) || !Number.isFinite(failed)) {
                return;
            }

            if (targeted <= 0) {
                countEl.textContent = 'Invoices Updated: 0';
                return;
            }

            if (failed > 0) {
                countEl.textContent = `Invoices Updated: ${updated}/${targeted} (Failed: ${failed})`;
                return;
            }

            if (skipped > 0) {
                countEl.textContent = `Invoices Updated: ${updated}/${targeted} (Skipped: ${skipped})`;
                return;
            }

            countEl.textContent = `Invoices Updated: ${updated}`;
        }

        function showGlobalSaveInfo(message, isError = false) {
            const infoEl = document.getElementById('global-save-info');
            if (!infoEl || !message) return;

            infoEl.textContent = message;
            if (isError) {
                infoEl.className = 'rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-900 shadow-2xs';
            } else {
                infoEl.className = 'rounded-xl border border-teal-200/90 bg-teal-50/90 px-4 py-2 text-xs font-bold text-teal-900 shadow-2xs';
            }

            infoEl.classList.remove('hidden');

            setTimeout(() => {
                infoEl.classList.add('hidden');
            }, isError ? 5000 : 3200);
        }

        function updateRowDOM(data) {
            const pId = data.product_id;
            const prevEl = document.getElementById('row-prev-' + pId);
            const badgeEl = document.getElementById('row-badge-' + pId);
            const updaterEl = document.getElementById('row-updater-' + pId);
            const costEl = document.getElementById('row-cost-avg-' + pId);
            const sellingAEl = document.getElementById('row-selling-a-' + pId);
            const purchaseCheckEl = document.getElementById('row-purchase-check-' + pId);
            const refB = document.getElementById('ref-a-b-' + pId);
            const refC = document.getElementById('ref-a-c-' + pId);

            if (prevEl) {
                if (data.previous_price) {
                    prevEl.textContent = `₹${formatPriceCompactJS(data.previous_price)}`;
                } else {
                    prevEl.textContent = `—`;
                }
            }

            if (refB) refB.textContent = data.today_price ? `₹${formatPriceCompactJS(data.today_price)}` : '—';
            if (refC) refC.textContent = data.today_price ? `₹${formatPriceCompactJS(data.today_price)}` : '—';

            if (badgeEl) {
                let bHtml = '';
                if (data.price_state === 'not_set') {
                    bHtml = `<div class="price-status not-set">Not set</div>`;
                } else if (data.price_state === 'no_previous') {
                    bHtml = `<div class="price-status increase">₹${formatPriceCompactJS(data.today_price)}</div>`;
                } else if (data.price_state === 'increased') {
                    bHtml = `<div class="price-status increase">▲ ₹${Math.round(data.diff_amount)} · ${data.diff_percentage}%</div>`;
                } else if (data.price_state === 'decreased') {
                    bHtml = `<div class="price-status decrease">▼ ₹${Math.round(Math.abs(data.diff_amount))} · ${data.diff_percentage}%</div>`;
                } else if (data.price_state === 'no_change') {
                    bHtml = `<div class="price-status no-change">— 0%</div>`;
                }
                badgeEl.innerHTML = bHtml;
            }

            if (updaterEl && data.updated_by_name) {
                updaterEl.textContent = `${data.updated_by_name} · ${data.updated_time}`;
            }

            const updaterA = document.getElementById('updater-a-' + pId);
            if (updaterA && data.updated_by_name) {
                updaterA.textContent = data.updated_by_name;
                updaterA.title = data.updated_by_name;
            }

            if (costEl) {
                if (data.purchaser_avg_price) {
                    costEl.textContent = `Cost Avg: ₹${formatPriceCompactJS(data.purchaser_avg_price)}`;
                } else {
                    costEl.textContent = '';
                }
            }

            if (sellingAEl) {
                if (data.selling_price_a) {
                    sellingAEl.textContent = `Selling A: ₹${formatPriceCompactJS(data.selling_price_a)}`;
                } else {
                    sellingAEl.textContent = '';
                }
            }

            if (purchaseCheckEl) {
                if (data.purchase_today) {
                    purchaseCheckEl.textContent = `Purchase: ₹${formatPriceCompactJS(data.purchase_today)}`;
                } else {
                    purchaseCheckEl.textContent = '';
                }
            }

            // Sync Grade B and Grade C values if non-manual
            const meta = productPriceMeta[pId];
            if (meta) {
                if (!meta.is_manual_b && data.calc_price_b !== undefined) {
                    const inputB = document.getElementById('price-b-' + pId);
                    const calcBEl = document.getElementById('calc-display-b-' + pId);
                    const valB = data.selling_price_b ?? data.calc_price_b;
                    if (inputB) inputB.value = formatPriceCompactJS(valB);
                    if (calcBEl) calcBEl.textContent = valB ? `₹${formatPriceCompactJS(valB)}` : '—';
                }
                if (!meta.is_manual_c && data.calc_price_c !== undefined) {
                    const inputC = document.getElementById('price-c-' + pId);
                    const calcCEl = document.getElementById('calc-display-c-' + pId);
                    const valC = data.selling_price_c ?? data.calc_price_c;
                    if (inputC) inputC.value = formatPriceCompactJS(valC);
                    if (calcCEl) calcCEl.textContent = valC ? `₹${formatPriceCompactJS(valC)}` : '—';
                }
            }
        }

        function populateModalContent(product) {
            document.getElementById('modal-product-name').textContent = product.name;
            document.getElementById('modal-product-unit').textContent = 'Purchase Unit: 1 ' + product.unit;

            document.getElementById('modal-today-price').textContent = product.price_today ? '₹' + formatPriceCompactJS(product.price_today) : 'Not set';
            document.getElementById('modal-previous-price').textContent = product.previous_price ? '₹' + formatPriceCompactJS(product.previous_price) : 'No previous price';

            // Change badge in modal
            const changeBadgeEl = document.getElementById('modal-change-badge');
            if (product.price_state === 'increased') {
                changeBadgeEl.innerHTML = `<span class="inline-flex items-center gap-1 font-extrabold text-emerald-800 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200 text-xs">▲ ₹${product.diff_amount.toFixed(2)} &middot; ${product.diff_percentage}%</span>`;
            } else if (product.price_state === 'decreased') {
                changeBadgeEl.innerHTML = `<span class="inline-flex items-center gap-1 font-extrabold text-rose-800 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200 text-xs">▼ ₹${Math.abs(product.diff_amount).toFixed(2)} &middot; ${product.diff_percentage}%</span>`;
            } else if (product.price_state === 'no_change') {
                changeBadgeEl.innerHTML = `<span class="font-bold text-slate-500 text-xs">&mdash; No change</span>`;
            } else if (product.price_state === 'no_previous') {
                changeBadgeEl.innerHTML = `<span class="font-semibold text-slate-400 text-xs">No previous price available</span>`;
            } else {
                changeBadgeEl.innerHTML = `<span class="font-bold text-amber-600 text-xs">Price not set</span>`;
            }

            // Sync main input value to modal input
            const mainInput = document.getElementById('price-' + product.id);
            const modalInput = document.getElementById('modal-price-input');
            if (mainInput && modalInput) {
                modalInput.value = mainInput.value;
            }

            // Populate top 3 recent price history
            renderModalHistory(product.history);

            // Audit Info Footer
            const auditInfo = document.getElementById('modal-audit-info');
            if (product.updated_by_name) {
                document.getElementById('modal-audit-username').textContent = 'Updated by ' + product.updated_by_name;
                document.getElementById('modal-audit-timestamp').textContent = product.updated_at_formatted || (product.updated_time || '');
                auditInfo.classList.remove('hidden');
            } else {
                auditInfo.classList.add('hidden');
            }
        }

        function renderModalHistory(history) {
            const historyListEl = document.getElementById('modal-history-list');
            if (!historyListEl) return;
            if (history && history.length > 0) {
                let hHtml = '';
                history.slice(0, 3).forEach(item => {
                    hHtml += `
                        <div class="flex items-center justify-between py-2">
                            <span class="font-bold text-slate-600">${item.date}</span>
                            <span class="font-black text-slate-900">₹${formatPriceCompactJS(item.price)}</span>
                            <span class="font-semibold text-slate-500">${item.updated_by}</span>
                        </div>
                    `;
                });
                historyListEl.innerHTML = hHtml;
            } else {
                historyListEl.innerHTML = `<div class="py-3 text-center text-slate-400 italic">No previous price history available</div>`;
            }
        }

        function openProductModal(product) {
            activeModalProduct = product;
            lastScrollY = window.scrollY;

            populateModalContent(product);

            const modal = document.getElementById('product-modal');
            modal.classList.remove('hidden');

            if (!product.history || product.history.length === 0) {
                const historyListEl = document.getElementById('modal-history-list');
                if (historyListEl) {
                    historyListEl.innerHTML = `<div class="py-3 text-center text-slate-400 text-xs animate-pulse">Loading history...</div>`;
                }

                fetch(`{{ url('/purchaser/daily-prices') }}/${product.id}/history?date=${currentDate}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && activeModalProduct && activeModalProduct.id === product.id) {
                        product.history = data.history || [];
                        renderModalHistory(product.history);
                    }
                })
                .catch(() => {
                    if (activeModalProduct && activeModalProduct.id === product.id) {
                        const historyListEl = document.getElementById('modal-history-list');
                        if (historyListEl) {
                            historyListEl.innerHTML = `<div class="py-3 text-center text-slate-400 italic">No previous price history available</div>`;
                        }
                    }
                });
            }
        }

        function closeProductModal() {
            activeModalProduct = null;
            const modal = document.getElementById('product-modal');
            modal.classList.add('hidden');
            window.scrollTo(0, lastScrollY);
        }

        function initLiveSearch() {
            const searchForm = document.getElementById('daily-price-search-form');
            const searchInput = document.getElementById('daily-price-search-input');

            if (!searchForm || !searchInput) {
                return;
            }

            let liveSearchTimer = null;
            let lastSubmittedValue = searchInput.value.trim();

            searchInput.addEventListener('input', function () {
                if (liveSearchTimer) {
                    clearTimeout(liveSearchTimer);
                }

                liveSearchTimer = setTimeout(() => {
                    const nextValue = searchInput.value.trim();

                    if (nextValue.length > 0 && nextValue.length < 3) {
                        return;
                    }

                    if (nextValue === lastSubmittedValue) {
                        return;
                    }

                    lastSubmittedValue = nextValue;
                    awaitPendingSavesThen(() => safelySubmitForm(searchForm));
                }, 300);
            });
        }

        // Intercept form submissions, filters, and link clicks to ensure pending edits are saved
        function initNavigationSafety() {
            const dateInput = document.getElementById('header-date-input');
            const dateForm = document.getElementById('daily-price-date-form');
            if (dateInput && dateForm) {
                dateInput.addEventListener('change', function () {
                    awaitPendingSavesThen(() => safelySubmitForm(dateForm));
                });
            }

            const categorySelect = document.getElementById('category-filter-select');
            const searchForm = document.getElementById('daily-price-search-form');
            if (categorySelect && searchForm) {
                categorySelect.addEventListener('change', function () {
                    awaitPendingSavesThen(() => safelySubmitForm(searchForm));
                });
            }

            const doubleCheckToggle = document.getElementById('double-check-toggle');
            if (doubleCheckToggle && searchForm) {
                doubleCheckToggle.addEventListener('change', function () {
                    awaitPendingSavesThen(() => safelySubmitForm(searchForm));
                });
            }

            if (searchForm) {
                searchForm.addEventListener('submit', function (e) {
                    if (isNavigating) return;
                    e.preventDefault();
                    awaitPendingSavesThen(() => safelySubmitForm(searchForm));
                });
            }

            document.querySelectorAll('.category-pill-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    if (isNavigating) return;
                    e.preventDefault();
                    const targetUrl = this.href;
                    awaitPendingSavesThen(() => safelyNavigateTo(targetUrl));
                });
            });

            document.querySelectorAll('form[action*="inventory.products.status.update"]').forEach(f => {
                f.addEventListener('submit', function (e) {
                    if (isNavigating) return;
                    e.preventDefault();
                    awaitPendingSavesThen(() => safelySubmitForm(f));
                });
            });
        }

        // Native beforeunload dialog if there are active unsaved debounces or in-flight requests
        window.addEventListener('beforeunload', function (e) {
            flushPendingDebounces();

            const hasPendingWork = Object.keys(pendingDebounceCallbacks).length > 0 || Object.keys(inFlightRequests).length > 0;
            if (hasPendingWork) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // Immediately flush on pagehide (mobile/tab switch)
        window.addEventListener('pagehide', function () {
            flushPendingDebounces();
        });

        // Immediately flush when tab is hidden
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                flushPendingDebounces();
            }
        });

        // Close modal on backdrop click
        document.getElementById('product-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });

        initLiveSearch();
        initNavigationSafety();
    </script>
    @endpush
</x-layouts.app>
