@extends('purchase-manager.layouts.app')

@section('title', 'Selling Price Matrix')
@section('page_title', 'Selling Price Matrix')
@section('page_description', 'Dedicated selling-price editor with daily comparison against purchasing cost.')

@section('content')
    @php
        $canEditPrices = auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin');
        $currentCarbonDate = \Illuminate\Support\Carbon::parse($purchaseDate);
        $prevDayDate = $currentCarbonDate->copy()->subDay()->toDateString();
        $nextDayDate = $currentCarbonDate->copy()->addDay()->toDateString();
        $prevDayWeekStart = $currentCarbonDate->copy()->subDay()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString();
        $nextDayWeekStart = $currentCarbonDate->copy()->addDay()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString();
        $matrixExportParams = [
            'date' => $purchaseDate,
            'search' => $search,
            'category_id' => $categoryId,
            'matrix_category' => $matrixCategory,
            'week_start' => $weekStartDate,
        ];
    @endphp

    <div class="space-y-4">
        <!-- Top Search and Filter Controls Card (Ultra Compact 66% Width) -->
        <section class="rounded-2xl border border-slate-200/80 bg-white p-3 shadow-xs space-y-2.5 w-full lg:w-2/3">
            <form method="GET" action="{{ route('purchasing.prices.matrix.index') }}" class="space-y-2.5" id="matrix-filter-form">
                <input type="hidden" name="matrix_category" id="filter-matrix-category" value="{{ $matrixCategory }}">
                <input type="hidden" name="week_start" id="filter-week-start" value="{{ $weekStartDate }}">

                <!-- Primary Controls Grid -->
                <div class="grid gap-2.5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 items-end">
                    <!-- Product Search -->
                    <div>
                        <label for="search" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Product Search</label>
                        <div class="relative">
                            <input
                                id="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search product, SKU..."
                                autocomplete="off"
                                class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 pl-8 pr-3 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:bg-white focus:ring-2 focus:ring-cyan-500/20 focus:outline-none transition touch-manipulation"
                            >
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label for="category_id" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Product Category</label>
                        <select
                            id="category_id"
                            name="category_id"
                            onchange="this.form.submit()"
                            class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs font-bold text-slate-900 focus:border-cyan-500 focus:bg-white focus:ring-2 focus:ring-cyan-500/20 focus:outline-none transition touch-manipulation"
                        >
                            <option value="">All Categories</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) $categoryId === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Business Date Stepper -->
                    <div>
                        <label for="date" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Business Date</label>
                        <div class="relative flex items-center h-9 rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
                            <a
                                href="{{ route('purchasing.prices.matrix.index', ['date' => $prevDayDate, 'search' => $search, 'category_id' => $categoryId, 'matrix_category' => $matrixCategory, 'week_start' => $prevDayWeekStart]) }}"
                                title="Previous Day ({{ \Illuminate\Support\Carbon::parse($prevDayDate)->format('d M') }})"
                                aria-label="Previous Day"
                                class="w-8 h-9 inline-flex items-center justify-center bg-slate-100 text-slate-700 hover:bg-cyan-600 hover:text-white transition active:scale-95 shrink-0 touch-manipulation"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </a>
                            <input
                                id="date"
                                type="date"
                                name="date"
                                value="{{ $purchaseDate }}"
                                onchange="this.form.submit()"
                                class="w-full h-9 border-0 bg-white px-2 text-center text-xs font-black text-cyan-800 focus:ring-0 focus:outline-none transition touch-manipulation"
                            >
                            <a
                                href="{{ route('purchasing.prices.matrix.index', ['date' => $nextDayDate, 'search' => $search, 'category_id' => $categoryId, 'matrix_category' => $matrixCategory, 'week_start' => $nextDayWeekStart]) }}"
                                title="Next Day ({{ \Illuminate\Support\Carbon::parse($nextDayDate)->format('d M') }})"
                                aria-label="Next Day"
                                class="w-8 h-9 inline-flex items-center justify-center bg-slate-100 text-slate-700 hover:bg-cyan-600 hover:text-white transition active:scale-95 shrink-0 touch-manipulation"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Filter Action Buttons -->
                    <div class="flex items-center gap-1.5">
                        <button type="submit" class="flex-1 h-9 inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-black text-white hover:bg-slate-800 active:scale-95 transition shadow-2xs touch-manipulation">
                            Apply Filters
                        </button>
                        @if ($canEditPrices)
                            <button
                                type="submit"
                                form="matrix-fill-forward-form"
                                data-submit-label="Fill Missing"
                                class="h-9 inline-flex items-center justify-center whitespace-nowrap rounded-xl border border-amber-200 bg-amber-500 px-3 text-xs font-black text-white hover:bg-amber-400 active:scale-95 transition shadow-2xs touch-manipulation"
                            >
                                Fill Missing
                            </button>
                            <button
                                type="submit"
                                form="matrix-remove-future-form"
                                data-submit-label="Remove Future Prices"
                                onclick="return confirm('Remove all visible product prices after {{ $purchaseDate }}?')"
                                class="h-9 inline-flex items-center justify-center whitespace-nowrap rounded-xl border border-rose-200 bg-rose-50 px-2 text-[10px] font-bold text-rose-700 hover:bg-rose-100 active:scale-95 transition shadow-2xs touch-manipulation"
                            >
                                Clear Future
                            </button>
                        @endif

                        <form method="POST" action="{{ route('purchasing.prices.toggle-publish') }}" class="inline-flex items-center">
                            @csrf
                            <input type="hidden" name="date" value="{{ $purchaseDate }}">
                            <input type="hidden" name="is_published" value="{{ $isPublished ? '0' : '1' }}">
                            <button
                                type="submit"
                                class="h-9 inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl px-3 text-xs font-black uppercase tracking-wider transition shadow-2xs {{ $isPublished ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-amber-500 text-white hover:bg-amber-600' }}"
                                title="{{ $isPublished ? 'Click to unpublish prices' : 'Click to publish daily prices to shop owners' }}"
                            >
                                <span class="h-2 w-2 rounded-full {{ $isPublished ? 'bg-white animate-pulse' : 'bg-amber-200' }}"></span>
                                <span>{{ $isPublished ? 'Published' : 'Publish Prices' }}</span>
                            </button>
                        </form>
                        <a href="{{ route('purchasing.prices.index', ['date' => $purchaseDate]) }}" class="h-9 inline-flex items-center justify-center whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 hover:bg-slate-50 transition active:scale-95 shadow-2xs touch-manipulation">
                            Proposal Board
                        </a>
                    </div>
                </div>

                <!-- Secondary Bar: Price Category Switcher & Week Navigation -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-slate-100">
                    <!-- Price Category Switcher -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Selling Price Category:</span>
                        <div class="inline-flex rounded-xl border border-slate-200 bg-slate-100 p-0.5 shadow-inner">
                            <button
                                type="button"
                                onclick="switchMatrixCategory('a')"
                                id="btn-matrix-cat-a"
                                class="h-7 rounded-lg px-3 text-[11px] font-black transition active:scale-95 touch-manipulation {{ $matrixCategory === 'a' ? 'bg-cyan-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60' }}"
                            >
                                Price A (Default)
                            </button>
                            <button
                                type="button"
                                onclick="switchMatrixCategory('b')"
                                id="btn-matrix-cat-b"
                                class="h-7 rounded-lg px-3 text-[11px] font-black transition active:scale-95 touch-manipulation {{ $matrixCategory === 'b' ? 'bg-cyan-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60' }}"
                            >
                                Price B
                            </button>
                            <button
                                type="button"
                                onclick="switchMatrixCategory('c')"
                                id="btn-matrix-cat-c"
                                class="h-7 rounded-lg px-3 text-[11px] font-black transition active:scale-95 touch-manipulation {{ $matrixCategory === 'c' ? 'bg-cyan-700 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60' }}"
                            >
                                Price C
                            </button>
                        </div>
                    </div>

                    <!-- Week Stepper Navigation -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <a
                            href="{{ route('purchasing.prices.matrix.index', ['date' => $purchaseDate, 'search' => $search, 'category_id' => $categoryId, 'matrix_category' => $matrixCategory, 'week_start' => $previousWeekStartDate]) }}"
                            aria-label="Previous Week"
                            class="h-8 inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 text-[11px] font-black text-slate-700 hover:border-cyan-300 hover:bg-cyan-50 transition active:scale-95 shadow-2xs touch-manipulation"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                            <span>Prev Week</span>
                        </a>
                        <div class="h-8 inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-[11px] font-black text-slate-800 shadow-inner">
                            <svg class="w-3.5 h-3.5 mr-1.5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                            </svg>
                            <span>{{ \Illuminate\Support\Carbon::parse($weekStartDate)->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($weekEndDate)->format('d M Y') }}</span>
                        </div>
                        <a
                            href="{{ route('purchasing.prices.matrix.index', ['date' => $purchaseDate, 'search' => $search, 'category_id' => $categoryId, 'matrix_category' => $matrixCategory, 'week_start' => $nextWeekStartDate]) }}"
                            aria-label="Next Week"
                            class="h-8 inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 text-[11px] font-black text-slate-700 hover:border-cyan-300 hover:bg-cyan-50 transition active:scale-95 shadow-2xs touch-manipulation"
                        >
                            <span>Next Week</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 pt-0.5 text-[10px] font-medium text-slate-500">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-red-500"></span>
                    <span>Displaying matrix for selected week ({{ count($matrixProducts) }} products). Highlighted red values indicate modified prices.</span>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-3 shadow-xs space-y-2 w-full lg:w-2/3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-700">Share & Export</h2>
                    <p class="text-[10px] font-semibold text-slate-500">Today, full week, or today changed prices only.</p>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach ([
                        'today' => 'Today',
                        'week' => 'Week',
                        'today_changed' => 'Today Changed',
                    ] as $scope => $scopeLabel)
                        <a href="{{ route('purchasing.prices.matrix.export.whatsapp', array_merge($matrixExportParams, ['scope' => $scope])) }}" target="_blank" rel="noopener" class="h-8 inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 text-[10px] font-black text-emerald-700 hover:bg-emerald-100 transition">
                            WhatsApp {{ $scopeLabel }}
                        </a>
                        <a href="{{ route('purchasing.prices.matrix.export.excel', array_merge($matrixExportParams, ['scope' => $scope])) }}" class="h-8 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-2.5 text-[10px] font-black text-slate-700 hover:bg-slate-50 transition">
                            Excel {{ $scopeLabel }}
                        </a>
                        <a href="{{ route('purchasing.prices.matrix.export.pdf', array_merge($matrixExportParams, ['scope' => $scope])) }}" target="_blank" rel="noopener" class="h-8 inline-flex items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-2.5 text-[10px] font-black text-cyan-700 hover:bg-cyan-100 transition">
                            PDF {{ $scopeLabel }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-800 space-y-1">
                @foreach ($errors->all() as $error)
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
 on             </div>
        @endif

        @if ($canEditPrices)
            <form method="POST" action="{{ route('purchasing.prices.matrix.fill-forward') }}" id="matrix-fill-forward-form">
                @csrf
                <input type="hidden" name="date" value="{{ $purchaseDate }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="category_id" value="{{ $categoryId }}">
                <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
                <input type="hidden" name="matrix_category" value="{{ $matrixCategory }}">
                @foreach ($matrixProducts as $prod)
                    <input type="hidden" name="all_product_ids[]" value="{{ $prod['product_id'] }}">
                @endforeach
                @foreach ($matrixDates as $dateStr => $dateInfo)
                    <input type="hidden" name="all_dates[]" value="{{ $dateStr }}">
                @endforeach
            </form>

            <form method="POST" action="{{ route('purchasing.prices.matrix.remove-future') }}" id="matrix-remove-future-form">
                @csrf
                <input type="hidden" name="date" value="{{ $purchaseDate }}">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="category_id" value="{{ $categoryId }}">
                <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
                <input type="hidden" name="matrix_category" value="{{ $matrixCategory }}">
                @foreach ($matrixProducts as $prod)
                    <input type="hidden" name="all_product_ids[]" value="{{ $prod['product_id'] }}">
                @endforeach
                @foreach ($matrixDates as $dateStr => $dateInfo)
                    <input type="hidden" name="all_dates[]" value="{{ $dateStr }}">
                @endforeach
            </form>
        @endif

        <!-- Dedicated Matrix Table Card -->
        <form method="POST" action="{{ url('/purchasing/prices/matrix') }}" class="rounded-2xl border border-slate-200/80 bg-white p-3 shadow-xs space-y-3" id="matrix-edit-form">
            <h2 class="sr-only">Daily Price Matrix Table</h2>
            @csrf
            <input type="hidden" name="date" value="{{ $purchaseDate }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="category_id" value="{{ $categoryId }}">
            <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
            <input type="hidden" name="matrix_category" id="update-matrix-category" value="{{ $matrixCategory }}">
            <input type="hidden" name="action" id="matrix-form-action" value="update">
            
            {{-- Hidden fields to track all visible products for approve/publish --}}
            @foreach ($matrixProducts as $prod)
                <input type="hidden" name="all_product_ids[]" value="{{ $prod['product_id'] }}">
            @endforeach
            @foreach ($matrixDates as $dateStr => $dateInfo)
                <input type="hidden" name="all_dates[]" value="{{ $dateStr }}">
            @endforeach
            
            <!-- Compact Action Header Bar -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 rounded-xl border border-cyan-100 bg-cyan-50/70 px-3 py-2 shadow-2xs">
                <p class="text-xs font-semibold text-cyan-950 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-cyan-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <span>
                        @if ($canEditPrices)
                            <strong class="font-black">Save Final Price</strong> updates the live purchaser matrix immediately.
                        @else
                            Price matrix is view only for this role.
                        @endif
                    </span>
                </p>
                <div class="flex items-center gap-2">
                    @if ($canEditPrices)
                        <button
                            type="submit"
                            data-submit-label="Save Final Price"
                            class="flex-1 sm:flex-initial h-8 inline-flex items-center justify-center rounded-xl border border-cyan-200 bg-cyan-600 px-4 text-xs font-black text-white hover:bg-cyan-500 active:scale-95 transition shadow-2xs touch-manipulation"
                        >
                            Save Final Price
                        </button>
                    @endif
                </div>
            </div>

            <!-- Table Responsive Wrapper -->
            <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs">
                <!-- Top Horizontal Scrollbar for Table -->
                <div class="matrix-top-scrollbar overflow-x-auto overflow-y-hidden border-b border-slate-200 bg-slate-100/70 scrollbar-thin scrollbar-thumb-cyan-500/50 touch-pan-x" id="matrix-top-scrollbar">
                    <div class="matrix-top-scrollbar-dummy" id="matrix-top-scrollbar-dummy" style="height: 6px;"></div>
                </div>

                <!-- Swipe hint for touch screens -->
                <div class="block sm:hidden bg-cyan-50 px-3 py-1 text-[10px] font-bold text-cyan-800 text-center border-b border-cyan-100">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3 h-3 animate-pulse text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        Scroll horizontally to view all week dates
                    </span>
                </div>

                <div class="overflow-x-auto overflow-y-visible scrollbar-thin scrollbar-thumb-slate-300 touch-pan-x" id="matrix-table-container">
                    <table class="w-full text-left text-xs border-collapse min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px]">
                                <th scope="col" class="sticky left-0 z-30 bg-slate-100 px-1.5 py-2 text-center border-r border-slate-200 w-10 min-w-[40px] max-w-[40px]">
                                    SL
                                </th>
                                <th scope="col" class="sticky left-10 z-30 bg-slate-100 px-2 py-2 text-center border-r border-slate-200 w-24 min-w-[96px] max-w-[96px]">
                                    Code
                                </th>
                                <th scope="col" class="sticky left-[136px] z-30 bg-slate-100 px-2.5 py-2 border-r border-slate-200 w-40 min-w-[160px] max-w-[160px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)]">
                                    Item
                                </th>
                                <th scope="col" class="px-1.5 py-2 text-center border-r border-slate-200 min-w-[90px] bg-amber-100/90 text-amber-950 font-black">
                                    Prev Day<br>
                                    <span class="text-[9px] text-amber-800 font-bold">{{ \Illuminate\Support\Carbon::parse($previousDate)->format('d-M') }}</span>
                                </th>
                                @foreach ($matrixDates as $dateStr => $dateInfo)
                                    <th scope="col" class="px-1.5 py-2 text-center border-r border-slate-200 min-w-[100px] transition-colors {{ $dateInfo['is_selected'] ? 'bg-cyan-100 text-cyan-950 font-black ring-2 ring-inset ring-cyan-400' : '' }}">
                                        <div class="flex flex-col items-center">
                                            <span class="text-xs font-black">{{ $dateInfo['label'] }}</span>
                                            @if ($dateInfo['is_selected'])
                                                <span class="mt-0.5 rounded-full bg-cyan-700 px-1.5 py-0.2 text-[8px] font-bold text-white uppercase tracking-wider">Today</span>
                                            @endif
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white font-medium text-slate-800">
                            @forelse ($matrixProducts as $prod)
                                @php
                                    $latestApprovedDate = null;
                                    $latestApprovedPrice = null;

                                    foreach ($matrixDates as $dateStr => $dateInfo) {
                                        $cellData = $prod['prices'][$dateStr] ?? null;
                                        if (! $cellData || ($cellData['status'] ?? 'none') !== 'approved') {
                                            continue;
                                        }

                                        $candidatePrice = match($matrixCategory) {
                                            'a' => $cellData['price_a'] ?? null,
                                            'b' => $cellData['price_b'] ?? null,
                                            'c' => $cellData['price_c'] ?? null,
                                            default => null,
                                        };

                                        if ($candidatePrice === null) {
                                            continue;
                                        }

                                        if ($latestApprovedDate === null || $dateStr > $latestApprovedDate) {
                                            $latestApprovedDate = $dateStr;
                                            $latestApprovedPrice = (float) $candidatePrice;
                                        }
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="sticky left-0 z-20 bg-white px-1.5 py-2 text-center font-black text-slate-500 border-r border-slate-200 w-10 min-w-[40px] max-w-[40px]">
                                        {{ $prod['sl_no'] }}
                                    </td>
                                    <td class="sticky left-10 z-20 bg-white px-2 py-2 text-center font-black text-slate-700 border-r border-slate-200 w-24 min-w-[96px] max-w-[96px]">
                                        <span class="block truncate max-w-[90px] text-xs font-black text-slate-700" title="{{ $prod['sku'] }}">{{ $prod['sku'] ?: '—' }}</span>
                                    </td>
                                    <td class="sticky left-[136px] z-20 bg-white px-2.5 py-2 font-bold text-slate-900 border-r border-slate-200 w-40 min-w-[160px] max-w-[160px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)]">
                                        <span class="block truncate max-w-[150px] text-xs font-black text-slate-900" title="{{ $prod['name'] }}">{{ $prod['name'] }}</span>
                                        @if ($latestApprovedDate !== null && $latestApprovedPrice !== null)
                                            <span class="mt-0.5 inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-1.5 py-0.2 text-[9px] font-black text-emerald-700">
                                                <svg class="h-2.5 w-2.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                                <span>{{ number_format($latestApprovedPrice, 2) }}</span>
                                                <span class="text-emerald-600 font-bold">({{ \Illuminate\Support\Carbon::parse($latestApprovedDate)->format('d M') }})</span>
                                            </span>
                                        @endif
                                        @if ($prod['sku'])
                                            <span class="block text-[9px] font-semibold text-slate-400 mt-0.5">{{ $prod['sku'] }} ({{ strtoupper($prod['unit'] ?: 'KG') }})</span>
                                        @endif
                                    </td>
                                    <td class="p-1.5 border-r border-slate-200 text-center bg-amber-50/40">
                                        @php
                                            $prevDayPrice = match($matrixCategory) {
                                                'a' => $prod['previous_day']['price_a'] ?? null,
                                                'b' => $prod['previous_day']['price_b'] ?? null,
                                                'c' => $prod['previous_day']['price_c'] ?? null,
                                                default => null,
                                            };
                                        @endphp
                                        <div class="flex items-center justify-center gap-1">
                                            @if ($prevDayPrice !== null)
                                                <span class="text-xs font-black text-amber-900 bg-amber-100/60 px-1.5 py-0.5 rounded-md">{{ number_format($prevDayPrice, 2) }}</span>
                                            @else
                                                <span class="text-[10px] font-bold text-slate-400">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    @php
                                        $rowUnitOptions = collect($prod['unit_options'] ?? [
                                            ['unit' => strtolower((string) ($prod['unit'] ?? 'kg')), 'label' => strtoupper((string) ($prod['unit'] ?? 'kg'))],
                                        ]);

                                        $unitsUsedInWeek = collect($prod['prices'] ?? [])
                                            ->map(fn ($priceRow) => strtolower((string) ($priceRow['unit'] ?? '')))
                                            ->filter()
                                            ->map(fn ($unit) => [
                                                'unit' => $unit,
                                                'label' => strtoupper(str_replace('_', ' ', $unit)),
                                            ]);

                                        $rowUnitOptions = $rowUnitOptions
                                            ->merge($unitsUsedInWeek)
                                            ->unique(fn ($opt) => strtolower((string) ($opt['unit'] ?? '')))
                                            ->values();
                                    @endphp
                                    @foreach ($matrixDates as $dateStr => $dateInfo)
                                        @php
                                            $cellData = $prod['prices'][$dateStr] ?? null;
                                            $priceValA = $cellData['price_a'] ?? null;
                                            $priceValB = $cellData['price_b'] ?? null;
                                            $priceValC = $cellData['price_c'] ?? null;
                                            $cellUnit = strtolower((string) ($cellData['unit'] ?? 'kg'));
                                            $cellUnitDisplay = strtoupper(str_replace('_', ' ', $cellUnit));
                                            $cellUnitOptions = $rowUnitOptions
                                                ->when(! $rowUnitOptions->contains(fn ($opt) => strtolower((string) ($opt['unit'] ?? '')) === $cellUnit), fn ($opts) => $opts->prepend(['unit' => $cellUnit, 'label' => $cellUnitDisplay]))
                                                ->values();

                                            $hasChangedA = $cellData['changed_a'] ?? false;
                                            $hasChangedB = $cellData['changed_b'] ?? false;
                                            $hasChangedC = $cellData['changed_c'] ?? false;
                                        @endphp
                                        <td class="p-1.5 border-r border-slate-200 text-center transition-colors {{ $dateInfo['is_selected'] ? 'bg-cyan-50/40' : '' }} {{ $latestApprovedDate === $dateStr ? 'bg-emerald-50/50 ring-1 ring-inset ring-emerald-200' : '' }}">
                                            <div class="matrix-cell-container relative space-y-1" data-product-id="{{ $prod['product_id'] }}" data-date="{{ $dateStr }}">
                                                <!-- Input field and Save button -->
                                                <div class="flex items-center gap-1">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="matrix_prices[{{ $prod['product_id'] }}][{{ $dateStr }}]"
                                                        data-product-id="{{ $prod['product_id'] }}"
                                                        data-date="{{ $dateStr }}"
                                                        data-price-a="{{ $priceValA !== null ? number_format($priceValA, 2, '.', '') : '' }}"
                                                        data-price-b="{{ $priceValB !== null ? number_format($priceValB, 2, '.', '') : '' }}"
                                                        data-price-c="{{ $priceValC !== null ? number_format($priceValC, 2, '.', '') : '' }}"
                                                        data-changed-a="{{ $hasChangedA ? '1' : '0' }}"
                                                        data-changed-b="{{ $hasChangedB ? '1' : '0' }}"
                                                        data-changed-c="{{ $hasChangedC ? '1' : '0' }}"
                                                        value="{{ $matrixCategory === 'a' ? ($priceValA !== null ? number_format($priceValA, 2, '.', '') : '') : ($matrixCategory === 'b' ? ($priceValB !== null ? number_format($priceValB, 2, '.', '') : '') : ($priceValC !== null ? number_format($priceValC, 2, '.', '') : '')) }}"
                                                        onkeydown="if(event.key==='Enter'){ event.preventDefault(); saveMatrixCell(this.nextElementSibling); }"
                                                        @if (! $canEditPrices) readonly @endif
                                                        class="matrix-cell-input flex-1 min-w-[56px] h-8 rounded-lg border border-slate-200 bg-white py-0.5 px-1 text-center font-black text-xs focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/20 focus:outline-none transition touch-manipulation {{ ($matrixCategory === 'a' && $hasChangedA) || ($matrixCategory === 'b' && $hasChangedB) || ($matrixCategory === 'c' && $hasChangedC) ? 'text-red-600 font-black bg-red-50/40 border-red-200' : 'text-slate-900' }} {{ ! $canEditPrices ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : '' }}"
                                                    >
                                                    @if ($canEditPrices)
                                                        <button
                                                            type="button"
                                                            title="Save final price"
                                                            onclick="saveMatrixCell(this)"
                                                            class="matrix-cell-save-btn flex-shrink-0 h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-900 text-white p-1 text-xs font-black hover:bg-cyan-600 active:scale-95 transition touch-manipulation shadow-2xs"
                                                        >
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>
                                                    @if ($canEditPrices)
                                                        <button
                                                            type="button"
                                                            title="Click to change unit"
                                                            onclick="toggleUnitSelector(this)"
                                                            class="matrix-cell-unit-btn h-5 inline-flex items-center gap-0.5 px-1.5 rounded bg-slate-100 text-slate-700 text-[9px] font-black hover:bg-cyan-100 hover:text-cyan-800 transition active:scale-95 touch-manipulation"
                                                        >
                                                            <span class="unit-display">{{ $cellUnitDisplay }}</span>
                                                            <svg class="w-2.5 h-2.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                            </svg>
                                                        </button>
                                                        <div class="unit-selector hidden absolute left-1/2 -translate-x-1/2 top-full z-[100] mt-0.5 bg-white border border-slate-200 rounded-lg shadow-xl p-1 min-w-[100px] space-y-0.5">
                                                            @foreach($cellUnitOptions as $unitOption)
                                                                <button
                                                                    type="button"
                                                                    data-unit="{{ $unitOption['unit'] }}"
                                                                    onclick="selectUnit(this, '{{ $unitOption['unit'] }}')"
                                                                    class="w-full text-left px-2 py-1 rounded text-[9px] font-bold hover:bg-cyan-50 hover:text-cyan-800 transition"
                                                                >{{ $unitOption['label'] }}</button>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-[9px] font-black text-slate-500">{{ $cellUnitDisplay }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($matrixDates) + 4 }}" class="p-8 text-center text-slate-400 font-bold">
                                        <div class="max-w-xs mx-auto space-y-1.5">
                                            <svg class="w-7 h-7 mx-auto text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                            </svg>
                                            <p class="text-xs font-black text-slate-600">No Products Found</p>
                                            <p class="text-[11px] text-slate-400">No items match your current search query or category filter.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <!-- Accessible & Modern Loading Overlay Window -->
    <div
        id="matrix-loading-overlay"
        class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/60 backdrop-blur-md px-4 transition-all duration-300 overscroll-none touch-none"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-label="Updating matrix prices"
    >
        <div class="w-full max-w-xs rounded-2xl bg-white/95 p-5 text-center shadow-2xl border border-white/40 transform transition-all scale-100">
            <div class="relative mx-auto h-12 w-12 flex items-center justify-center">
                <div class="absolute inset-0 animate-ping rounded-full bg-cyan-400/20"></div>
                <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-cyan-600 border-r-cyan-600"></div>
            </div>
            <p class="mt-3 text-sm font-black text-slate-950 tracking-tight">Updating Matrix Prices</p>
            <p class="mt-1 text-xs font-semibold text-slate-600">Please wait while selling prices are saved and updated.</p>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                let currentMatrixCategory = "{{ $matrixCategory }}";
                let matrixSaving = false;
                const matrixLoadingOverlay = document.getElementById('matrix-loading-overlay');

                function showLoadingOverlay() {
                    if (!matrixLoadingOverlay) return;
                    matrixLoadingOverlay.classList.remove('hidden');
                    matrixLoadingOverlay.classList.add('flex');
                    matrixLoadingOverlay.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }

                function hideLoadingOverlay() {
                    if (!matrixLoadingOverlay) return;
                    matrixLoadingOverlay.classList.add('hidden');
                    matrixLoadingOverlay.classList.remove('flex');
                    matrixLoadingOverlay.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }

                function switchMatrixCategory(cat) {
                    currentMatrixCategory = cat;
                    document.getElementById('filter-matrix-category').value = cat;
                    const updateCategoryInput = document.getElementById('update-matrix-category');
                    if (updateCategoryInput) {
                        updateCategoryInput.value = cat;
                    }

                    ['a', 'b', 'c'].forEach(c => {
                        const btn = document.getElementById('btn-matrix-cat-' + c);
                        if (btn) {
                            if (c === cat) {
                                btn.className = 'h-7 rounded-lg px-3 text-[11px] font-black transition active:scale-95 touch-manipulation bg-cyan-700 text-white shadow-xs';
                            } else {
                                btn.className = 'h-7 rounded-lg px-3 text-[11px] font-black transition active:scale-95 touch-manipulation text-slate-600 hover:text-slate-900 hover:bg-slate-200/60';
                            }
                        }
                    });

                    document.querySelectorAll('.matrix-cell-input').forEach(input => {
                        const valA = input.dataset.priceA;
                        const valB = input.dataset.priceB;
                        const valC = input.dataset.priceC;
                        const changedA = input.dataset.changedA === '1';
                        const changedB = input.dataset.changedB === '1';
                        const changedC = input.dataset.changedC === '1';

                        let targetVal = '';
                        let isChanged = false;

                        if (cat === 'a') {
                            targetVal = valA || '';
                            isChanged = changedA;
                        } else if (cat === 'b') {
                            targetVal = valB || '';
                            isChanged = changedB;
                        } else if (cat === 'c') {
                            targetVal = valC || '';
                            isChanged = changedC;
                        }

                        input.value = targetVal;
                        if (isChanged) {
                            input.classList.remove('text-slate-900');
                            input.classList.add('text-red-600', 'font-black', 'bg-red-50/40', 'border-red-200');
                        } else {
                            input.classList.remove('text-red-600', 'font-black', 'bg-red-50/40', 'border-red-200');
                            input.classList.add('text-slate-900');
                        }
                    });
                }

                async function saveMatrixCell(btn) {
                    const container = btn.closest('.matrix-cell-container');
                    if (!container) return;
                    const input = container.querySelector('.matrix-cell-input');
                    const unitInput = container.querySelector('.matrix-cell-unit');
                    const unitDisplay = container.querySelector('.unit-display');
                    if (!input) return;

                    const productId = input.dataset.productId;
                    const dateStr = input.dataset.date;
                    const priceVal = input.value;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                                      document.querySelector('input[name="_token"]')?.value || '';

                    btn.disabled = true;
                    btn.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8" /></svg>';
                    btn.className = 'matrix-cell-save-btn flex-shrink-0 h-8 w-8 inline-flex items-center justify-center rounded-lg bg-amber-500 text-white p-1 text-xs font-black opacity-90 shadow-2xs';

                    try {
                        const response = await fetch("{{ route('purchasing.prices.matrix.cell.update') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                product_id: productId,
                                date: dateStr,
                                price_category: currentMatrixCategory,
                                price: priceVal,
                                price_unit: unitInput ? unitInput.value : null,
                            }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            input.dataset.priceA = data.price_a !== null ? Number(data.price_a).toFixed(2) : '';
                            input.dataset.priceB = data.price_b !== null ? Number(data.price_b).toFixed(2) : '';
                            input.dataset.priceC = data.price_c !== null ? Number(data.price_c).toFixed(2) : '';
                            if (unitInput && data.price_unit) {
                                const normalizedUnit = String(data.price_unit).toLowerCase();
                                unitInput.value = normalizedUnit;
                                if (unitDisplay) {
                                    unitDisplay.textContent = normalizedUnit.replace('_', ' ').toUpperCase();
                                }
                            }

                            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                            btn.className = 'matrix-cell-save-btn flex-shrink-0 h-8 w-8 inline-flex items-center justify-center rounded-lg bg-emerald-600 text-white p-1 text-xs font-black shadow-md';
                            input.classList.add('bg-emerald-50', 'border-emerald-300');

                            setTimeout(() => {
                                btn.disabled = false;
                                btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                                btn.className = 'matrix-cell-save-btn flex-shrink-0 h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-900 text-white p-1 text-xs font-black hover:bg-cyan-600 active:scale-95 transition shadow-2xs touch-manipulation';
                                input.classList.remove('bg-emerald-50', 'border-emerald-300');
                            }, 1200);
                        } else {
                            alert(data.message || 'Error saving price.');
                            btn.disabled = false;
                            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                            btn.className = 'matrix-cell-save-btn flex-shrink-0 h-8 w-8 inline-flex items-center justify-center rounded-lg bg-rose-600 text-white p-1 text-xs font-black shadow-2xs';
                        }
                    } catch (err) {
                        alert('Network error saving price.');
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                        btn.className = 'matrix-cell-save-btn flex-shrink-0 h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-900 text-white p-1 text-xs font-black shadow-2xs';
                    }
                }

                const matrixEditForm = document.getElementById('matrix-edit-form');
                if (matrixEditForm) {
                    matrixEditForm.addEventListener('submit', function() {
                        if (matrixSaving) return;
                        matrixSaving = true;
                        showLoadingOverlay();

                        matrixEditForm.querySelectorAll('button[type="submit"]').forEach(button => {
                            const label = button.dataset.submitLabel || button.textContent.trim();
                            button.disabled = true;
                            button.textContent = label + '...';
                            button.classList.add('opacity-70', 'cursor-not-allowed');
                        });
                    });
                }

                document.getElementById('matrix-filter-form')?.addEventListener('submit', function() {
                    showLoadingOverlay();
                });

                document.getElementById('matrix-fill-forward-form')?.addEventListener('submit', function(event) {
                    const confirmed = window.confirm('Fill all missing visible matrix prices from each product\\'s latest approved price? Existing positive prices will not be overwritten.');
                    if (!confirmed) {
                        event.preventDefault();
                        return;
                    }

                    showLoadingOverlay();
                });

                function toggleUnitSelector(btn) {
                    const selector = btn.nextElementSibling;
                    if (!selector) return;
                    
                    const isHidden = selector.style.display === 'none' || selector.classList.contains('hidden');
                    
                    // Hide any other open selectors first
                    document.querySelectorAll('.unit-selector').forEach(s => {
                        if (s !== selector) {
                            s.style.display = 'none';
                            s.classList.add('hidden');
                        }
                    });

                    selector.style.display = isHidden ? 'block' : 'none';
                    selector.classList.toggle('hidden', !isHidden);
                }

                function selectUnit(btn, unit, label) {
                    const selector = btn.parentElement;
                    const unitBtn = selector.previousElementSibling;
                    const hiddenInput = unitBtn.previousElementSibling;
                    
                    if (hiddenInput && hiddenInput.classList.contains('matrix-cell-unit')) {
                        hiddenInput.value = unit;
                    }
                    
                    if (unitBtn) {
                        const display = unitBtn.querySelector('.unit-display');
                        if (display) {
                            display.textContent = label || unit;
                        }
                    }
                    
                    selector.style.display = 'none';
                    selector.classList.add('hidden');
                }

                // Global click outside to hide unit selectors
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.matrix-cell-container')) {
                        document.querySelectorAll('.unit-selector').forEach(selector => {
                            selector.style.display = 'none';
                            selector.classList.add('hidden');
                        });
                    }
                });

                // Top & Bottom Scrollbar Synchronization
                const topScroll = document.getElementById('matrix-top-scrollbar');
                const bottomScroll = document.getElementById('matrix-table-container');
                const topDummy = document.getElementById('matrix-top-scrollbar-dummy');

                function syncMatrixScrollWidth() {
                    if (bottomScroll && topDummy) {
                        const table = bottomScroll.querySelector('table');
                        const width = table ? table.offsetWidth : bottomScroll.scrollWidth;
                        topDummy.style.width = width + 'px';
                    }
                }

                if (topScroll && bottomScroll) {
                    let isSyncingTop = false;
                    let isSyncingBottom = false;

                    topScroll.addEventListener('scroll', function() {
                        if (!isSyncingTop) {
                            isSyncingBottom = true;
                            bottomScroll.scrollLeft = topScroll.scrollLeft;
                        }
                        isSyncingTop = false;
                    });

                    bottomScroll.addEventListener('scroll', function() {
                        if (!isSyncingBottom) {
                            isSyncingTop = true;
                            topScroll.scrollLeft = bottomScroll.scrollLeft;
                        }
                        isSyncingBottom = false;
                    });

                    window.addEventListener('resize', syncMatrixScrollWidth);
                    setTimeout(syncMatrixScrollWidth, 100);
                }
            </script>
        @endpush
    @endonce
@endsection
