<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daily Ledger Engine — Multi-Shop Operations Core</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .white-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
        }
        .white-card-hover:hover {
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.08), 0 8px 10px -6px rgba(79, 70, 229, 0.05);
            border-color: rgba(199, 210, 254, 0.8);
        }
        .gradient-text {
            background: linear-gradient(135deg, #4f46e5 0%, #0284c7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #94a3b8;
            transition: all 0.15s ease-in-out;
        }
        .sidebar-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.06);
        }
        .sidebar-link.active-sidebar {
            color: #ffffff;
            background-color: #4f46e5;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>
<body class="h-full font-sans text-slate-800 antialiased selection:bg-brand-500 selection:text-white bg-slate-50 custom-scrollbar">

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-md w-full pointer-events-none"></div>

    <!-- Edit Amount Modal -->
    <div id="edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm hidden">
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-5 shadow-2xl mx-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="edit-3" class="w-4 h-4 text-brand-600"></i> Edit Entry Amount
                </h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>
            <form onsubmit="submitEditEntry(event)" class="space-y-4">
                <input type="hidden" id="edit-transaction-id">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">New Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" id="edit-amount-input" required class="w-full bg-white text-sm font-mono font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                </div>
                <p class="text-[11px] text-slate-500 bg-amber-50 p-2.5 rounded-xl border border-amber-200">
                    <strong class="text-amber-800">Note:</strong> Changing parent amounts automatically recalculates daily snapshots and auto-adjusts paired secondary entries.
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs transition-all shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Void Entry Modal -->
    <div id="void-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm hidden">
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-5 shadow-2xl mx-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-bold text-rose-700 flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4 text-rose-600"></i> Void Transaction Line
                </h3>
                <button onclick="closeVoidModal()" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>
            <form onsubmit="submitVoidEntry(event)" class="space-y-4">
                <input type="hidden" id="void-transaction-id">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Reason for Voiding</label>
                    <input type="text" id="void-reason-input" placeholder="e.g. Duplicate entry, incorrect code..." required class="w-full bg-white text-xs text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                </div>
                <p class="text-[11px] text-slate-500 bg-rose-50 p-2.5 rounded-xl border border-rose-200">
                    <strong class="text-rose-800">Warning:</strong> Voiding marks the transaction as void, cascading to paired entries, without destroying audit trails.
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeVoidModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs transition-all shadow-sm">Confirm Void</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Shop Modal (Template Copy Flow) -->
    <div id="add-shop-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm hidden">
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-5 shadow-2xl mx-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="store" class="w-4 h-4 text-brand-600"></i> Add New Shop (Template Copy Flow)
                </h3>
                <button onclick="closeAddShopModal()" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>
            <form onsubmit="submitAddShop(event)" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Shop ID</label>
                        <input type="number" id="add-shop-id-input" placeholder="e.g. 13" required class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Shop Code</label>
                        <input type="text" id="add-shop-code-input" placeholder="e.g. AV_MYSORE" required class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Shop Name</label>
                    <input type="text" id="add-shop-name-input" placeholder="e.g. Mysore Supermarket" required class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Ownership Category</label>
                    <select id="add-shop-ownership-input" class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        <option value="client">Aiswarya Veg (Client-Owned Shop)</option>
                        <option value="direct">Direct Shop (Independent Bill Payer)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Profile Template</label>
                    <select id="add-shop-template-input" class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        <option value="owned_standard">owned_standard (Standard Company Shop)</option>
                        <option value="direct_buyer">direct_buyer (Direct Bill Payer)</option>
                        <option value="managed_outlet">managed_outlet (Managed Franchise)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Copy Rules From Shop</label>
                    <select id="add-shop-copy-from-input" class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        @foreach($shops as $shop)
                            <option value="{{ $shop->shop_id }}">{{ $shop->name }} ({{ $shop->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeAddShopModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs shadow-sm">Create & Copy Rules</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Rule Config Modal -->
    <div id="create-config-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm hidden">
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-5 shadow-2xl mx-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="sliders" class="w-4 h-4 text-indigo-600"></i> Create Rule Configuration
                </h3>
                <button onclick="closeCreateConfigModal()" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>
            <form onsubmit="submitCreateConfig(event)" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Select Shop <span class="text-rose-500">*</span></label>
                    <select id="config-shop-id-input" required class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        @foreach($shops as $shop)
                            <option value="{{ $shop->shop_id }}">{{ $shop->name }} ({{ $shop->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Select Entry Type <span class="text-rose-500">*</span></label>
                    <select id="config-entry-type-id-input" required class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        @foreach($entryTypes as $et)
                            <option value="{{ $et->id }}">{{ $et->name }} [{{ $et->code }}] ({{ ucfirst($et->category) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Default Funding Source</label>
                    <select id="config-funding-source-input" class="w-full bg-white text-xs font-semibold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        <option value="sales_cash">Sales Cash (Counter Cash)</option>
                        <option value="petty_cash">Petty Cash Box</option>
                        <option value="bank">Bank Deposit</option>
                        <option value="company_pending">Company Pending Debt</option>
                    </select>
                </div>

                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                    <span class="text-[10px] font-extrabold uppercase text-slate-500 block mb-1">Rule Flags & Behavior</span>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" id="config-in-sales-input" class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                            <span>In Sales</span>
                        </label>
                        <label class="flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" id="config-in-expense-input" class="rounded text-rose-600 focus:ring-rose-500 h-4 w-4">
                            <span>In Expense</span>
                        </label>
                        <label class="flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" id="config-in-pl-input" checked class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                            <span>In P&L</span>
                        </label>
                        <label class="flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                            <input type="checkbox" id="config-secondary-input" class="rounded text-purple-600 focus:ring-purple-500 h-4 w-4">
                            <span>Secondary Entry</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeCreateConfigModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- MAIN DASHBOARD LAYOUT WITH PROPER FINTECH SIDEBAR -->
    <!-- ========================================================================= -->
    <div class="min-h-screen flex">

        <!-- WHITE SIDEBAR PARTIAL INCLUDE -->
        @include('admin.cashbook.layouts.partials.sidebar')

        <!-- MAIN CONTENT CONTAINER -->
        <div class="flex-1 md:pl-64 flex flex-col min-h-screen">

            <!-- TOP HEADER BAR -->
            <header class="sticky top-0 z-30 w-full bg-white/90 border-b border-slate-200/80 backdrop-blur-md shadow-sm">
                <div class="px-4 sm:px-6 py-3 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2.5">
                        <!-- Mobile Sidebar Hamburger Toggle -->
                        <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <div>
                            <h1 id="top-header-title" class="text-base sm:text-lg font-extrabold text-slate-900 flex items-center gap-2">
                                <i data-lucide="layout-grid" class="w-5 h-5 text-brand-600"></i> All Shops Daily Overview
                            </h1>
                            <p id="top-header-subtitle" class="text-[11px] sm:text-xs text-slate-500">Real-time daily accounting metrics across all owned shops.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 ml-auto">
                        <button id="global-toggle-day-btn" onclick="handleToggleDay()" class="px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="lock" class="w-3.5 h-3.5"></i> Close Day
                        </button>
                    </div>
                </div>

                {{-- DATE FILTER BAR --}}
                <div class="px-4 sm:px-6 pb-3 flex items-center gap-1.5 sm:gap-2 flex-wrap">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mr-0.5 hidden sm:inline">Period:</span>
                    <button onclick="setDateFilter('today')" id="dfbtn-today"
                        class="date-filter-btn px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold border transition-all border-slate-200 text-slate-600 hover:bg-slate-100">
                        Today
                    </button>
                    <button onclick="setDateFilter('yesterday')" id="dfbtn-yesterday"
                        class="date-filter-btn px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold border transition-all border-brand-300 bg-brand-50 text-brand-700">
                        Yesterday
                    </button>
                    <button onclick="setDateFilter('week')" id="dfbtn-week"
                        class="date-filter-btn px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold border transition-all border-slate-200 text-slate-600 hover:bg-slate-100">
                        This Week
                    </button>
                    <button onclick="setDateFilter('month')" id="dfbtn-month"
                        class="date-filter-btn px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold border transition-all border-slate-200 text-slate-600 hover:bg-slate-100">
                        This Month
                    </button>
                    <button onclick="setDateFilter('custom')" id="dfbtn-custom"
                        class="date-filter-btn px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-lg text-[11px] sm:text-xs font-bold border transition-all border-slate-200 text-slate-600 hover:bg-slate-100 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Custom
                    </button>
                    {{-- Hidden date picker (shown on Custom) --}}
                    <input type="date" id="all-shops-date-input" value="{{ $selectedDate }}"
                        onchange="onCustomDateChange(this.value)"
                        class="hidden text-xs font-mono font-bold text-slate-800 px-2.5 py-1 rounded-lg border border-brand-300 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20" />

                    {{-- Active date label --}}
                    <span id="active-date-label" class="sm:ml-auto text-[10px] sm:text-[11px] font-mono font-bold text-slate-500 bg-slate-100 px-2 sm:px-2.5 py-1 rounded-lg border border-slate-200">
                        {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('d M Y') }}
                    </span>
                </div>
            </header>

            <!-- MAIN DYNAMIC SECTION BODY -->
            <main class="flex-1 p-3 sm:p-6 md:p-8 space-y-6 sm:space-y-8">
                
                <!-- ========================================================================= -->
                <!-- TAB 1: ALL SHOPS OVERVIEW -->
                <!-- ========================================================================= -->
                <section id="tab-all-shops" class="tab-content space-y-6">

                    {{-- Top Summary Cards Bar --}}
                    <div id="all-shops-summary-cards" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4">
                        <div class="white-card rounded-2xl p-3 sm:p-4 border border-slate-200 shadow-sm flex items-center gap-2.5 sm:gap-3">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="store" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <div id="summary-total-shops" class="text-base sm:text-2xl font-extrabold text-slate-900 font-mono truncate">—</div>
                                <div class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider truncate">Total Shops</div>
                            </div>
                        </div>
                        <div class="white-card rounded-2xl p-3 sm:p-4 border border-slate-200 shadow-sm flex items-center gap-2.5 sm:gap-3">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="receipt" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <div id="summary-total-gl-bills" class="text-base sm:text-2xl font-extrabold text-amber-700 font-mono truncate">—</div>
                                <div class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider truncate">GL Bills</div>
                            </div>
                        </div>
                        <div class="white-card rounded-2xl p-3 sm:p-4 border border-slate-200 shadow-sm flex items-center gap-2.5 sm:gap-3">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="circle-dollar-sign" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <div id="summary-total-received" class="text-base sm:text-2xl font-extrabold text-emerald-700 font-mono truncate">—</div>
                                <div class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider truncate">Received</div>
                            </div>
                        </div>
                        <div class="white-card rounded-2xl p-3 sm:p-4 border border-slate-200 shadow-sm flex items-center gap-2.5 sm:gap-3">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="arrow-down-left" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <div id="summary-total-payable" class="text-base sm:text-2xl font-extrabold text-rose-700 font-mono truncate">—</div>
                                <div class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider truncate">Total Payable</div>
                            </div>
                        </div>
                    </div>

                    {{-- DATE + REFRESH CONTROLS --}}
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                            <i data-lucide="layout-list" class="w-4 h-4 text-indigo-600"></i>
                            Shops Financial Matrix — Grouped by Ownership
                        </h2>
                        <button onclick="loadAllShopsOverview()" class="px-3 py-1.5 rounded-xl bg-brand-50 text-brand-700 border border-brand-200 font-semibold text-xs flex items-center gap-1">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
                        </button>
                    </div>

                    {{-- CLIENT-OWNED SHOPS GROUP (e.g. Aiswarya Veg) --}}
                    <div id="client-shops-section" class="white-card rounded-3xl overflow-hidden border border-slate-200 shadow-sm">
                        {{-- Section Header --}}
                        <div class="flex items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4 bg-amber-50/60 border-b border-amber-200/60 flex-wrap gap-2">
                            <div class="flex items-center gap-2.5 sm:gap-3">
                                <div class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl bg-amber-100 border border-amber-200 text-amber-700 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div id="client-group-title" class="text-xs sm:text-sm font-extrabold text-amber-900">Client-Owned Shops</div>
                                    <div class="text-[9px] sm:text-[10px] text-amber-700 font-semibold">Income via Green Leaf purchase bills — track bills issued vs payments received</div>
                                </div>
                            </div>
                            <div id="client-group-totals" class="flex items-center gap-2 sm:gap-3 text-xs font-mono text-amber-900 flex-wrap"></div>
                        </div>

                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs min-w-[700px]">
                                <thead>
                                    <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                        <th class="py-3 px-4">Shop</th>
                                        <th class="py-3 px-4 text-right">GL Bills Issued</th>
                                        <th class="py-3 px-4 text-right">Received Today</th>
                                        <th class="py-3 px-4 text-right">Net Receivable</th>
                                        <th class="py-3 px-4 text-right">Shop Position</th>
                                        <th class="py-3 px-4 text-right">Comp. Pending</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="client-shops-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400 font-sans">Loading client-owned shops...</td>
                                    </tr>
                                </tbody>
                                <tfoot id="client-shops-tfoot" class="bg-amber-50/80 font-mono font-bold text-amber-900 border-t-2 border-amber-200 text-xs"></tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- DIRECT SHOPS GROUP (independent shops, pay GL bills directly) --}}
                    <div id="direct-shops-section" class="white-card rounded-3xl overflow-hidden border border-slate-200 shadow-sm">
                        {{-- Section Header --}}
                        <div class="flex items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4 bg-indigo-50/60 border-b border-indigo-200/60 flex-wrap gap-2">
                            <div class="flex items-center gap-2.5 sm:gap-3">
                                <div class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl bg-indigo-100 border border-indigo-200 text-indigo-700 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div id="direct-group-title" class="text-xs sm:text-sm font-extrabold text-indigo-900">Direct Shops</div>
                                    <div class="text-[9px] sm:text-[10px] text-indigo-700 font-semibold">Independent shops that buy veg/fruits directly from Green Leaf — track GL bills issued vs payments received</div>
                                </div>
                            </div>
                            <div id="direct-group-totals" class="flex items-center gap-2 sm:gap-3 text-xs font-mono text-indigo-900 flex-wrap"></div>
                        </div>

                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs min-w-[600px]">
                                <thead>
                                    <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                        <th class="py-3 px-4">Shop</th>
                                        <th class="py-3 px-4 text-right">GL Bills Issued</th>
                                        <th class="py-3 px-4 text-right">Received Today</th>
                                        <th class="py-3 px-4 text-right">Net Receivable</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="direct-shops-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400 font-sans">Loading direct shops...</td>
                                    </tr>
                                </tbody>
                                <tfoot id="direct-shops-tfoot" class="bg-indigo-50/80 font-mono font-bold text-indigo-900 border-t-2 border-indigo-200 text-xs"></tfoot>
                            </table>
                        </div>
                    </div>


                </section>



                <!-- ========================================================================= -->
                <!-- TAB 2: COMPANY PAYABLE & PENDING LISTS -->
                <!-- ========================================================================= -->
                <section id="tab-payables" class="tab-content space-y-6 hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- Company Payable List -->
                        <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-9 w-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-700">
                                        <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">Company Payable List</h3>
                                        <p class="text-xs text-slate-500">Shops owing money to company (sorted highest payable first).</p>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto custom-scrollbar">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                            <th class="py-2.5 px-3">Shop</th>
                                            <th class="py-2.5 px-3 text-right">Payable Amount</th>
                                            <th class="py-2.5 px-3 text-right">1-Click Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payables-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                        <tr>
                                            <td colspan="3" class="py-6 text-center text-slate-400 font-sans">Loading payables...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Company Pending List -->
                        <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-9 w-9 rounded-xl bg-purple-50 border border-purple-200 flex items-center justify-center text-purple-700">
                                        <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">Company Pending List</h3>
                                        <p class="text-xs text-slate-500">Shops the company owes reimbursement to (sorted highest pending first).</p>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto custom-scrollbar">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                            <th class="py-2.5 px-3">Shop</th>
                                            <th class="py-2.5 px-3 text-right">Pending Amount</th>
                                            <th class="py-2.5 px-3 text-right">1-Click Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendings-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                        <tr>
                                            <td colspan="3" class="py-6 text-center text-slate-400 font-sans">Loading pendings...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </section>


                <!-- ========================================================================= -->
                <!-- TAB 3: ACCEPT PAYMENT & PETTY FLOWS -->
                <!-- ========================================================================= -->
                <section id="tab-payments" class="tab-content space-y-6 hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Accept Payment Form -->
                        <div class="lg:col-span-2 white-card p-8 rounded-3xl space-y-6 shadow-xl">
                            <div class="border-b border-slate-200 pb-4">
                                <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                                    <i data-lucide="wallet" class="w-5 h-5 text-brand-600"></i> Accept Payment (Coherent Money-In Flow)
                                </h2>
                                <p class="text-xs text-slate-500 mt-1">Admin accepts money received from shop and decides allocation: settle company payable, fund petty cash, or both.</p>
                            </div>

                            <form id="accept-payment-form" onsubmit="handleAcceptPayment(event)" class="space-y-5">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Shop</label>
                                        <select id="payment-shop-id" onchange="handlePaymentShopChange(this.value)" class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                            @foreach($shops as $shop)
                                                <option value="{{ $shop->shop_id }}">{{ $shop->name ? $shop->name . ' (' . $shop->code . ')' : 'Shop ID: #' . $shop->shop_id }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Business Date</label>
                                        <input type="date" id="payment-date" value="{{ today()->toDateString() }}" onchange="handlePaymentShopChange(document.getElementById('payment-shop-id').value)" required class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                    </div>
                                </div>

                                <!-- Live Shop Payable & Position Summary Banner -->
                                <div id="payment-shop-summary-banner" class="bg-indigo-50/80 border border-indigo-200 p-4 rounded-2xl space-y-2">
                                    <div class="text-xs font-extrabold text-indigo-900 flex items-center justify-between">
                                        <span class="flex items-center gap-1.5"><i data-lucide="info" class="w-4 h-4 text-indigo-600"></i> Selected Shop Financial Summary</span>
                                        <span class="text-[10px] uppercase px-2 py-0.5 rounded font-bold bg-white text-indigo-700 border border-indigo-200">Live Snapshot</span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3 pt-1 text-xs">
                                        <div class="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-xs">
                                            <span class="text-[10px] font-bold text-slate-500 block uppercase">1. Payable to Company</span>
                                            <strong id="payment-banner-position" class="text-sm font-mono font-extrabold text-amber-600">₹0.00</strong>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-xs">
                                            <span class="text-[10px] font-bold text-slate-500 block uppercase">2. Company Pending</span>
                                            <strong id="payment-banner-pending" class="text-sm font-mono font-extrabold text-purple-600">₹0.00</strong>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-xs">
                                            <span class="text-[10px] font-bold text-slate-500 block uppercase">3. Petty Cash Float</span>
                                            <strong id="payment-banner-petty" class="text-sm font-mono font-extrabold text-sky-600">₹0.00</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                    <div>
                                        <label class="block text-xs font-bold text-amber-800 mb-1">1. Settle Company Payable (₹)</label>
                                        <input type="number" step="0.01" min="0" id="payment-settle-amount" placeholder="0.00" class="w-full bg-white text-sm font-mono font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-amber-500">
                                        <span class="text-[10px] text-slate-500 mt-1 block">Reduces Payable to Company</span>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-sky-800 mb-1">2. Fund Petty Cash Float (₹)</label>
                                        <input type="number" step="0.01" min="0" id="payment-petty-amount" placeholder="0.00" class="w-full bg-white text-sm font-mono font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-sky-500">
                                        <span class="text-[10px] text-slate-500 mt-1 block">Top-up shop's Petty Float</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Credited Company Account (Bank / Cash)</label>
                                    <select id="payment-company-account" class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                        @foreach($companyAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->is_default ? 'selected' : '' }}>
                                                {{ $acc->name }} ({{ strtoupper($acc->account_type) }}) {{ $acc->is_default ? '— Default' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Payment Memo / Notes</label>
                                    <input type="text" id="payment-notes" placeholder="e.g. Weekly settlement & petty top-up..." class="w-full bg-white text-xs text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                </div>

                                <button type="submit" class="w-full py-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition-all shadow-lg shadow-brand-600/25 flex items-center justify-center gap-2">
                                    <i data-lucide="check-circle" class="w-4 h-4"></i> Accept Payment & Credit Company Account
                                </button>
                            </form>
                        </div>

                        <!-- Pay a Shop Form -->
                        <div class="white-card p-6 rounded-3xl space-y-5 shadow-xl flex flex-col justify-between">
                            <div class="space-y-4">
                                <div class="border-b border-slate-200 pb-3">
                                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                        <i data-lucide="send" class="w-4 h-4 text-purple-600"></i> Pay a Shop (Reimbursement)
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-1">Clear what company owes a shop (`company_pending`).</p>
                                </div>

                                <form id="pay-shop-form" onsubmit="handlePayShop(event)" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Shop</label>
                                        <select id="pay-shop-id" onchange="handlePaymentShopChange(this.value)" class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                                            @foreach($shops as $shop)
                                                <option value="{{ $shop->shop_id }}">{{ $shop->name }} ({{ $shop->code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Date</label>
                                        <input type="date" id="pay-shop-date" value="{{ today()->toDateString() }}" required class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Reimbursement Amount (₹)</label>
                                        <input type="number" step="0.01" min="0.01" id="pay-shop-amount" placeholder="0.00" required class="w-full bg-white text-xs font-mono font-bold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Notes</label>
                                        <input type="text" id="pay-shop-notes" placeholder="Vehicle expense reimbursement..." class="w-full bg-white text-xs text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                                    </div>
                                    <button type="submit" class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs shadow-md">
                                        Pay Reimbursement to Shop
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </section>


                <!-- ========================================================================= -->
                <!-- TAB 4: INCOME & EXPENSES CRUD SECTION -->
                <!-- ========================================================================= -->
                <section id="tab-income-expense" class="tab-content space-y-6 hidden">
                    <div class="flex items-center justify-between bg-slate-100 p-1.5 rounded-2xl border border-slate-200/80 w-fit">
                        <button onclick="filterCrudCategory('all')" id="crud-filter-all" class="crud-filter-btn px-4 py-1.5 text-xs font-bold rounded-xl transition-all bg-white text-slate-900 shadow-sm">
                            All Lines
                        </button>
                        <button onclick="filterCrudCategory('income')" id="crud-filter-income" class="crud-filter-btn px-4 py-1.5 text-xs font-bold rounded-xl transition-all text-slate-600 hover:text-slate-900">
                            Income Only
                        </button>
                        <button onclick="filterCrudCategory('expense')" id="crud-filter-expense" class="crud-filter-btn px-4 py-1.5 text-xs font-bold rounded-xl transition-all text-slate-600 hover:text-slate-900">
                            Expense Only
                        </button>
                        <button onclick="filterCrudCategory('transfer')" id="crud-filter-transfer" class="crud-filter-btn px-4 py-1.5 text-xs font-bold rounded-xl transition-all text-slate-600 hover:text-slate-900">
                            Transfers Only
                        </button>
                    </div>

                    <div class="white-card rounded-3xl p-6 space-y-4 shadow-xl">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                        <th class="py-3 px-3">Entry Type</th>
                                        <th class="py-3 px-3">Reference</th>
                                        <th class="py-3 px-3">Category</th>
                                        <th class="py-3 px-3">Direction</th>
                                        <th class="py-3 px-3 text-right">Amount</th>
                                        <th class="py-3 px-3">Funding Source</th>
                                        <th class="py-3 px-3 text-right">P/L Delta</th>
                                        <th class="py-3 px-3">Status</th>
                                        <th class="py-3 px-3 text-right">Actions (CRUD)</th>
                                    </tr>
                                </thead>
                                <tbody id="crud-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                    <tr>
                                        <td colspan="9" class="py-8 text-center text-slate-400 font-sans">Loading transaction entries...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>


                <!-- ========================================================================= -->
                <!-- TAB 5: SINGLE SHOP LEDGER DASHBOARD -->
                <!-- ========================================================================= -->
                <section id="tab-dashboard" class="tab-content space-y-6 hidden">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 white-card p-5 rounded-2xl">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-xl bg-brand-50 border border-brand-200 flex items-center justify-center text-brand-600">
                                <i data-lucide="store" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-3">
                                    <h2 id="dashboard-shop-title" class="text-xl font-extrabold text-slate-900">Shop B</h2>
                                    <span id="dashboard-day-status" class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Open</span>
                                </div>
                                <p id="dashboard-shop-subtitle" class="text-xs text-slate-500">Viewing business date <span id="dashboard-date-display" class="font-mono text-slate-800 font-semibold">2026-08-12</span></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button id="toggle-day-btn" onclick="handleToggleDay()" class="px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="lock" class="w-3.5 h-3.5"></i> Close Day
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
                        <div class="white-card p-4 rounded-2xl space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Sales</span>
                            <div id="stat-sales" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
                            <span class="text-[10px] text-emerald-600 font-semibold block">Gross Inflow</span>
                        </div>

                        <div class="white-card p-4 rounded-2xl space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Expense</span>
                            <div id="stat-expense" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
                            <span class="text-[10px] text-rose-600 font-semibold block">P/L Chargeable</span>
                        </div>

                        <div class="white-card p-4 rounded-2xl space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Net P/L</span>
                            <div id="stat-net-pl" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
                            <span id="stat-net-pl-sub" class="text-[10px] text-slate-500 font-medium block">Income - Expense</span>
                        </div>

                        <div class="white-card p-4 rounded-2xl space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Closing Petty</span>
                            <div id="stat-petty" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
                            <span class="text-[10px] text-sky-600 font-semibold block">Petty Float</span>
                        </div>

                        <div class="white-card p-4 rounded-2xl space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Shop Position</span>
                            <div id="stat-settlement" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
                            <span id="stat-settlement-sub" class="text-[10px] text-amber-600 font-semibold block">Payable to Company</span>
                        </div>

                        <div class="white-card p-4 rounded-2xl space-y-1">
                            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Company Pending</span>
                            <div id="stat-company-pending" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
                            <span class="text-[10px] text-purple-600 font-semibold block">Pending Reimbursements</span>
                        </div>
                    </div>

                    <div class="white-card rounded-2xl p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="list-ordered" class="w-4 h-4 text-brand-600"></i> Posted Transactions Log
                            </h3>
                            <span id="transaction-count" class="text-xs text-slate-500 font-mono font-medium">0 entries</span>
                        </div>

                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-600 bg-slate-100/70 border-b border-slate-200 uppercase tracking-wider font-bold">
                                        <th class="py-3 px-3">Entry Type</th>
                                        <th class="py-3 px-3">Direction</th>
                                        <th class="py-3 px-3 text-right">Amount</th>
                                        <th class="py-3 px-3">Funding Source</th>
                                        <th class="py-3 px-3 text-right">P/L Delta</th>
                                        <th class="py-3 px-3 text-right">Settlement</th>
                                        <th class="py-3 px-3 text-right">Petty Cash</th>
                                        <th class="py-3 px-3">Type</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400 font-sans">Loading transaction data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>


                <!-- ========================================================================= -->
                <!-- TAB 6: TRANSACTION SIMULATOR (POST ENTRY) -->
                <!-- ========================================================================= -->
                <section id="tab-simulator" class="tab-content space-y-6 hidden">
                    
                    <!-- Live Just-Posted Highlight Card -->
                    <div id="just-posted-card" class="hidden max-w-2xl mx-auto p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 space-y-2 shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-xs flex items-center gap-1.5 text-emerald-800">
                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i> Entry Successfully Posted!
                            </span>
                            <span id="just-posted-id" class="text-xs font-mono font-bold bg-white px-2 py-0.5 rounded border border-emerald-200">Manual Entry</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 font-mono text-xs pt-1">
                            <div><span class="text-[10px] text-slate-500 block uppercase font-sans">Entry Type</span><strong id="just-posted-type" class="text-slate-900 font-sans">-</strong></div>
                            <div><span class="text-[10px] text-slate-500 block uppercase font-sans">Amount</span><strong id="just-posted-amount" class="text-slate-900">₹0.00</strong></div>
                            <div><span class="text-[10px] text-slate-500 block uppercase font-sans">Funding Source</span><strong id="just-posted-source" class="text-slate-700 font-sans">-</strong></div>
                            <div><span class="text-[10px] text-slate-500 block uppercase font-sans">P/L Impact</span><strong id="just-posted-pl" class="text-emerald-700">₹0.00</strong></div>
                        </div>
                    </div>

                    <!-- Post Entry Form -->
                    <div class="max-w-2xl mx-auto white-card p-8 rounded-3xl space-y-6 shadow-xl">
                        <div class="border-b border-slate-200 pb-4">
                            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="plus-circle" class="w-5 h-5 text-brand-600"></i> Record Ledger Entry
                            </h2>
                            <p class="text-xs text-slate-500 mt-1">Post a new Income or Expense transaction into the active shop's engine service.</p>
                        </div>

                        <form id="record-entry-form" onsubmit="handleRecordEntry(event)" class="space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-2">1. Select Entry Category</label>
                                <div class="grid grid-cols-3 gap-2 bg-slate-100 p-1.5 rounded-2xl border border-slate-200/80">
                                    <button type="button" onclick="selectEntryCategory('income')" id="cat-toggle-income" class="cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all bg-emerald-600 text-white shadow-sm flex items-center justify-center gap-1.5">
                                        <i data-lucide="trending-up" class="w-4 h-4"></i> Income
                                    </button>
                                    <button type="button" onclick="selectEntryCategory('expense')" id="cat-toggle-expense" class="cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                                        <i data-lucide="trending-down" class="w-4 h-4"></i> Expense
                                    </button>
                                    <button type="button" onclick="selectEntryCategory('transfer')" id="cat-toggle-transfer" class="cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                                        <i data-lucide="arrow-left-right" class="w-4 h-4"></i> Transfer
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Target Shop</label>
                                    <select id="form-shop-id" class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                        @foreach($shops as $shop)
                                            <option value="{{ $shop->shop_id }}" {{ (isset($initialShopId) && (int)$initialShopId === (int)$shop->shop_id) ? 'selected' : '' }}>{{ $shop->name ? $shop->name . ' (' . $shop->code . ')' : 'Shop ID: #' . $shop->shop_id }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Business Date</label>
                                    <input type="date" id="form-business-date" value="2026-08-12" required class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">2. Related Entry Type</label>
                                <select id="form-entry-type" onchange="updateAllowedFundingSources()" required class="w-full bg-white text-xs font-bold text-slate-900 px-3.5 py-3 rounded-xl border border-slate-300 shadow-sm">
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Amount (₹)</label>
                                    <input type="number" step="0.01" min="0.01" id="form-amount" placeholder="0.00" required class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Funding Source</label>
                                    <select id="form-funding-source" class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                                        <option value="">Default (From Shop Config)</option>
                                        <option value="sales">sales</option>
                                        <option value="petty">petty</option>
                                        <option value="company">company</option>
                                        <option value="none">none</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Notes / Memo</label>
                                <input type="text" id="form-notes" placeholder="Optional reference or transaction note..." class="w-full bg-white text-xs text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
                            </div>

                            <button type="submit" id="form-submit-btn" class="w-full py-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm transition-all shadow-lg shadow-brand-600/25 flex items-center justify-center gap-2">
                                <i data-lucide="send" class="w-4 h-4"></i> Submit Entry to Ledger Engine
                            </button>
                        </form>
                    </div>

                    <!-- Entries Recorded Below Form -->
                    <div class="max-w-2xl mx-auto white-card rounded-3xl p-6 space-y-3 shadow-xl border border-slate-200">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="history" class="w-4 h-4 text-brand-600"></i> Entries Recorded Below (Session Feed)
                            </h3>
                            <span id="session-entry-count" class="text-xs text-slate-500 font-mono font-medium">0 entries</span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                        <th class="py-2.5 px-3">Reference</th>
                                        <th class="py-2.5 px-3">Entry Type</th>
                                        <th class="py-2.5 px-3 text-right">Amount</th>
                                        <th class="py-2.5 px-3">Source</th>
                                        <th class="py-2.5 px-3 text-right">P/L Delta</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="session-entries-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-slate-400 font-sans">No entries submitted yet in this session. Post an entry above to see it live here.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </section>


                <!-- ========================================================================= -->
                <!-- TAB 7: SHOP CONFIGURATION & RULE EDITOR -->
                <!-- ========================================================================= -->
                <section id="tab-rules" class="tab-content space-y-6 hidden">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 white-card p-6 rounded-3xl shadow-sm">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <i data-lucide="sliders" class="w-5 h-5 text-brand-600"></i> Shop Configuration & Rule Editor
                            </h3>
                            <p class="text-xs text-slate-500">Per-shop accounting rules (`shop_ledger_entry_settings`). Edit rules or onboard new shops via template copy.</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <button onclick="openCreateConfigModal()" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm flex items-center gap-1.5">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Create Config
                            </button>
                            <button onclick="loadRulesData()" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 border border-slate-300 flex items-center gap-1.5">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh Rules
                            </button>
                        </div>
                    </div>

                    <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-600 bg-slate-100/70 border-b border-slate-200 uppercase tracking-wider font-bold">
                                        <th class="py-3 px-3">Shop</th>
                                        <th class="py-3 px-3">Entry Type</th>
                                        <th class="py-3 px-3">Default Funding Source</th>
                                        <th class="py-3 px-3">Allowed Sources</th>
                                        <th class="py-3 px-3 text-center">Sales?</th>
                                        <th class="py-3 px-3 text-center">Expense?</th>
                                        <th class="py-3 px-3 text-center">P/L?</th>
                                        <th class="py-3 px-3 text-center">Secondary Entry?</th>
                                        <th class="py-3 px-3 text-right">Quick Edit</th>
                                    </tr>
                                </thead>
                                <tbody id="rules-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                                    <tr>
                                        <td colspan="9" class="py-8 text-center text-slate-400 font-sans">Loading rule configurations...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>


                <!-- ========================================================================= -->
                <!-- TAB 8: PRD SPEC VERIFICATION -->
                <!-- ========================================================================= -->
                <section id="tab-prd-spec" class="tab-content space-y-6 hidden">
                    <div class="white-card p-8 rounded-3xl space-y-6 shadow-xl">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                                    <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i> PRD Section 5 Acceptance Criteria Runner
                                </h2>
                                <p class="text-xs text-slate-500 mt-1">Execute worked examples live to verify exact balance matches for Shop A and Shop B.</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <button onclick="runSpecVerification(2)" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs transition-all shadow-sm flex items-center gap-2">
                                    <i data-lucide="play" class="w-3.5 h-3.5"></i> Run Shop B Test
                                </button>
                                <button onclick="runSpecVerification(1)" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-all shadow-sm flex items-center gap-2">
                                    <i data-lucide="play" class="w-3.5 h-3.5"></i> Run Shop A Test
                                </button>
                            </div>
                        </div>

                        <!-- Verification Output Box -->
                        <div id="spec-output" class="hidden space-y-6">
                            <div id="spec-badge" class="p-4 rounded-2xl flex items-center justify-between border">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="spec-results-grid">
                            </div>
                        </div>

                        <div id="spec-placeholder" class="text-center py-12 text-slate-400 text-sm">
                            Click one of the buttons above to execute a live simulation and verify balance correctness.
                        </div>
                    </div>
                </section>

            </main>
        </div>
    </div>

    <!-- JAVASCRIPT APP LOGIC -->
    <script>
        lucide.createIcons();

        const allEntryTypes = @json($entryTypes);

        const initialTab = @json($initialTab ?? 'all-shops');
        const initialShopIdVal = @json($initialShopId ?? 1);

        let currentTab = initialTab;
        let currentShopId = parseInt(initialShopIdVal);
        let currentDate = @json($selectedDate);
        let globalStartDate = @json($selectedDate);
        let globalEndDate = @json($selectedDate);
        let globalTimeframe = 'daily';
        let loadedTransactions = [];
        let sessionTransactions = [];
        let activeCrudFilter = 'all';
        let activeCategoryToggle = 'income';

        const tabHeaderInfo = {
            'all-shops': { title: 'All Shops Daily Overview', subtitle: 'Real-time daily accounting metrics across all 12 owned shops.', icon: 'layout-grid' },
            'payables': { title: 'Company Payables & Pending List', subtitle: 'Ranked financial position of shop payables and company reimbursements.', icon: 'arrow-down-left' },
            'payments': { title: 'Accept Payment & Petty Allocation', subtitle: 'Process incoming money, settle company payables, and fund petty floats.', icon: 'wallet' },
            'income-expense': { title: 'Income & Expenses (CRUD)', subtitle: 'Create, update amounts, and void transaction line items.', icon: 'receipt' },
            'dashboard': { title: 'Single Shop Daily Ledger', subtitle: 'Detailed daily snapshot and posted transaction log per shop.', icon: 'activity' },
            'simulator': { title: 'Record Ledger Entry Simulator', subtitle: 'Post new transactions directly into the ledger engine.', icon: 'plus-circle' },
            'rules': { title: 'Shop Configuration & Rule Editor', subtitle: 'Database-driven rule settings per shop without touching code.', icon: 'sliders' },
            'prd-spec': { title: 'PRD Acceptance Criteria Runner', subtitle: 'Verify exact Section 5 mathematical worked examples.', icon: 'check-circle-2' }
        };

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function transactionReferenceLabel(transaction) {
            const notes = String(transaction?.notes || '').trim();

            if (notes) {
                return notes.replace(/^Auto from invoice\s+/i, '');
            }

            if (transaction?.reference_type) {
                return 'Linked source';
            }

            return 'Manual entry';
        }

        const tabUrlMap = {
            'all-shops': '/admin/cashbook/all-shops',
            'payables': '/admin/cashbook/payables',
            'payments': '/admin/cashbook/accept-payment',
            'income-expense': '/admin/cashbook/income-expenses',
            'dashboard': `/admin/cashbook/shops/${currentShopId}`,
            'simulator': '/admin/cashbook/post-entry',
            'rules': '/admin/cashbook/rules-config',
            'prd-spec': '/admin/cashbook/specs'
        };

        document.addEventListener('DOMContentLoaded', () => {
            const selector = document.getElementById('active-shop-selector');
            if (selector) selector.value = currentShopId;
            const formShop = document.getElementById('form-shop-id');
            if (formShop) formShop.value = currentShopId;
            const payShop = document.getElementById('payment-shop-id');
            if (payShop) payShop.value = currentShopId;
            const payShop2 = document.getElementById('pay-shop-id');
            if (payShop2) payShop2.value = currentShopId;
            setActiveDateFilterButton(activeDateFilter);
            selectEntryCategory('income');
            switchTab(initialTab, false);
        });

        window.onpopstate = (event) => {
            if (event.state && event.state.tabId) {
                if (event.state.shopId) {
                    currentShopId = event.state.shopId;
                    const selector = document.getElementById('active-shop-selector');
                    if (selector) selector.value = currentShopId;
                }
                switchTab(event.state.tabId, false);
            } else {
                const path = window.location.pathname;
                if (path.startsWith('/admin/cashbook/shops/')) {
                    const parts = path.split('/');
                    const shopParam = parts[2];
                    if (shopParam) {
                        currentShopId = parseInt(shopParam) || 1;
                        const selector = document.getElementById('active-shop-selector');
                        if (selector) selector.value = currentShopId;
                    }
                    switchTab('dashboard', false);
                } else if (path === '/admin/cashbook/payables') {
                    switchTab('payables', false);
                } else if (path === '/admin/cashbook/accept-payment') {
                    switchTab('payments', false);
                } else if (path === '/admin/cashbook/income-expenses') {
                    switchTab('income-expense', false);
                } else if (path === '/admin/cashbook/post-entry' || path.startsWith('/post-entry/')) {
                    switchTab('simulator', false);
                } else if (path === '/admin/cashbook/rules-config') {
                    switchTab('rules', false);
                } else if (path === '/admin/cashbook/specs') {
                    switchTab('prd-spec', false);
                } else {
                    switchTab('all-shops', false);
                }
            }
        };

        function getTabUrl(tabId) {
            if (tabId === 'dashboard') {
                return `/admin/cashbook/shops/${currentShopId}`;
            }
            return tabUrlMap[tabId] || '/admin/cashbook/all-shops';
        }

        function syncGlobalDate(endDate, startDate = null, timeframe = 'daily') {
            currentDate = endDate;
            globalStartDate = startDate || endDate;
            globalEndDate   = endDate;
            globalTimeframe = timeframe;
            document.getElementById('dashboard-date-input') && (document.getElementById('dashboard-date-input').value = endDate);
            if (currentTab === 'all-shops') loadAllShopsOverview();
            if (currentTab === 'payables') loadPayablesAndPendings();
            if (currentTab === 'dashboard' || currentTab === 'income-expense') loadDashboardData();
        }

        // ── Date Filter Bar ─────────────────────────────────────────────────
        const todayForFilter = new Date();
        const yesterdayForFilter = new Date(todayForFilter);
        yesterdayForFilter.setDate(todayForFilter.getDate() - 1);
        const dateFilterFmt = d => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        let activeDateFilter = currentDate === dateFilterFmt(yesterdayForFilter) ? 'yesterday' : (currentDate === dateFilterFmt(todayForFilter) ? 'today' : 'custom');

        function setActiveDateFilterButton(filter) {
            document.querySelectorAll('.date-filter-btn').forEach(btn => {
                btn.className = 'date-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold border transition-all border-slate-200 text-slate-600 hover:bg-slate-100 flex items-center gap-1';
            });
            const activeBtn = document.getElementById('dfbtn-' + filter);
            if (activeBtn) {
                activeBtn.className = 'date-filter-btn px-3 py-1.5 rounded-lg text-xs font-bold border transition-all border-brand-300 bg-brand-50 text-brand-700 flex items-center gap-1';
            }
        }

        function setDateFilter(filter) {
            activeDateFilter = filter;
            setActiveDateFilterButton(filter);

            const pickerEl = document.getElementById('all-shops-date-input');
            const labelEl  = document.getElementById('active-date-label');

            const today = new Date();
            const fmt = d => {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            const fmtLabel = d => d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

            if (filter === 'custom') {
                pickerEl.classList.remove('hidden');
                pickerEl.focus();
                return;
            } else {
                pickerEl.classList.add('hidden');
            }

            let sDate, eDate, labelText;
            let timeframe = 'daily';
            if (filter === 'today') {
                sDate = today;
                eDate = today;
                labelText = fmtLabel(today);
            } else if (filter === 'yesterday') {
                const yest = new Date(today); yest.setDate(today.getDate() - 1);
                sDate = yest;
                eDate = yest;
                labelText = fmtLabel(yest);
            } else if (filter === 'week') {
                const day = today.getDay();
                const diff = (day === 0) ? 6 : day - 1; // Monday
                sDate = new Date(today); sDate.setDate(today.getDate() - diff);
                eDate = today;
                timeframe = 'weekly';
                labelText = `${fmtLabel(sDate)} – ${fmtLabel(eDate)}`;
            } else if (filter === 'month') {
                sDate = new Date(today.getFullYear(), today.getMonth(), 1); // 1st of month
                eDate = today;
                timeframe = 'monthly';
                labelText = `${fmtLabel(sDate)} – ${fmtLabel(eDate)}`;
            }

            const sStr = fmt(sDate);
            const eStr = fmt(eDate);
            pickerEl.value = eStr;
            if (labelEl) labelEl.innerText = labelText;
            syncGlobalDate(eStr, sStr, timeframe);
        }

        function onCustomDateChange(val) {
            const labelEl = document.getElementById('active-date-label');
            if (labelEl && val) {
                const d = new Date(val + 'T00:00:00');
                labelEl.innerText = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
            }
            syncGlobalDate(val, val, 'custom');
        }
        // ─────────────────────────────────────────────────────────────────────

        // Simplified Category Toggle (Income vs Expense vs Transfer)
        function selectEntryCategory(cat) {
            activeCategoryToggle = cat;

            document.querySelectorAll('.cat-toggle-btn').forEach(btn => {
                btn.className = 'cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5';
            });

            const activeBtn = document.getElementById(`cat-toggle-${cat}`);
            if (activeBtn) {
                if (cat === 'income') {
                    activeBtn.className = 'cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all bg-emerald-600 text-white shadow-sm flex items-center justify-center gap-1.5';
                } else if (cat === 'expense') {
                    activeBtn.className = 'cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all bg-rose-600 text-white shadow-sm flex items-center justify-center gap-1.5';
                } else {
                    activeBtn.className = 'cat-toggle-btn py-2.5 text-xs font-extrabold rounded-xl transition-all bg-brand-600 text-white shadow-sm flex items-center justify-center gap-1.5';
                }
            }

            const selectEl = document.getElementById('form-entry-type');
            const filtered = allEntryTypes.filter(t => t.category === cat);

            if (filtered.length > 0) {
                selectEl.innerHTML = filtered.map(t => 
                    `<option value="${t.code}" data-category="${t.category}">${t.name} (${t.code})</option>`
                ).join('');
            } else {
                selectEl.innerHTML = `<option value="">No entry types for ${cat}</option>`;
            }
        }

        // Sidebar Navigation Tab Switching
        function switchTab(tabId, pushState = true) {
            currentTab = tabId;
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active-sidebar'));

            const targetTab = document.getElementById(`tab-${tabId}`);
            if (targetTab) targetTab.classList.remove('hidden');

            const activeBtn = document.getElementById(`nav-${tabId}`);
            if (activeBtn) activeBtn.classList.add('active-sidebar');

            const targetUrl = getTabUrl(tabId);
            if (pushState && window.location.pathname !== targetUrl) {
                history.pushState({ tabId: tabId, shopId: currentShopId }, '', targetUrl);
            }

            const info = tabHeaderInfo[tabId] || tabHeaderInfo['all-shops'];
            const headerTitle = document.getElementById('top-header-title');
            const headerSub = document.getElementById('top-header-subtitle');
            if (headerTitle) headerTitle.innerHTML = `<i data-lucide="${info.icon}" class="w-5 h-5 text-brand-600"></i> ${info.title}`;
            if (headerSub) headerSub.innerText = info.subtitle;
            lucide.createIcons();

            const formShop = document.getElementById('form-shop-id');
            if (formShop && currentShopId) formShop.value = currentShopId;

            if (tabId === 'all-shops') loadAllShopsOverview();
            if (tabId === 'payables') loadPayablesAndPendings();
            if (tabId === 'dashboard' || tabId === 'income-expense') loadDashboardData();
            if (tabId === 'rules') loadRulesData();
            if (tabId === 'payments') {
                const payShopEl = document.getElementById('payment-shop-id');
                const selectedShopId = payShopEl ? payShopEl.value : currentShopId;
                handlePaymentShopChange(selectedShopId);
            }
        }

        // Handle Shop Change
        function handleShopChange(shopId) {
            currentShopId = parseInt(shopId);
            if (document.getElementById('form-shop-id')) document.getElementById('form-shop-id').value = currentShopId;
            if (document.getElementById('payment-shop-id')) document.getElementById('payment-shop-id').value = currentShopId;
            if (document.getElementById('pay-shop-id')) document.getElementById('pay-shop-id').value = currentShopId;

            if (currentTab === 'dashboard') {
                const targetUrl = `/admin/cashbook/shops/${currentShopId}`;
                history.pushState({ tabId: 'dashboard', shopId: currentShopId }, '', targetUrl);
            }

            if (currentTab === 'dashboard' || currentTab === 'income-expense') loadDashboardData();
            if (currentTab === 'payments') handlePaymentShopChange(currentShopId);
        }

        // Handle Payment Shop Selection & Live Data Fetch
        async function handlePaymentShopChange(shopId) {
            if (!shopId) return;
            try {
                const dateInput = document.getElementById('payment-date');
                const date = dateInput ? dateInput.value : currentDate;
                
                const res = await fetch(`/admin/cashbook/api/shop-data?shop_id=${shopId}&business_date=${date}`);
                const data = await res.json();
                
                if (data.success && data.snapshot) {
                    const snap = data.snapshot;
                    const shopPos = parseFloat(snap.closing_shop_position || 0);
                    const compPend = parseFloat(snap.closing_company_pending || 0);
                    const petty = parseFloat(snap.closing_petty || 0);
                    
                    const posEl = document.getElementById('payment-banner-position');
                    if (posEl) posEl.innerText = `₹${shopPos.toFixed(2)}`;
                    
                    const pendEl = document.getElementById('payment-banner-pending');
                    if (pendEl) pendEl.innerText = `₹${compPend.toFixed(2)}`;
                    
                    const pettyEl = document.getElementById('payment-banner-petty');
                    if (pettyEl) pettyEl.innerText = `₹${petty.toFixed(2)}`;
                    
                    // Prefill settle amount input with full shop position payable if greater than 0
                    const settleInput = document.getElementById('payment-settle-amount');
                    if (settleInput) {
                        settleInput.value = shopPos > 0 ? shopPos.toFixed(2) : '';
                    }

                    // Sync pay-shop form for reimbursement
                    const payShopSelect = document.getElementById('pay-shop-id');
                    if (payShopSelect && payShopSelect.value != shopId) payShopSelect.value = shopId;
                    const payAmountInput = document.getElementById('pay-shop-amount');
                    if (payAmountInput) payAmountInput.value = compPend > 0 ? compPend.toFixed(2) : '';
                }
            } catch (err) {
                console.error('Failed to load payment shop data:', err);
            }
        }

        // Load All Shops Overview — Grouped by Client vs Direct
        async function loadAllShopsOverview() {
            try {
                const params = new URLSearchParams({
                    start_date: globalStartDate,
                    end_date: globalEndDate,
                    business_date: currentDate,
                    timeframe: globalTimeframe,
                });
                const res = await fetch(`/admin/cashbook/api/all-shops-overview?${params.toString()}`);
                const data = await res.json();

                if (!data.success) return;

                const overview = data.overview || [];
                const totals   = data.totals || {};

                // Update top summary cards
                const summaryTotalShops    = document.getElementById('summary-total-shops');
                const summaryGlBills       = document.getElementById('summary-total-gl-bills');
                const summaryReceived      = document.getElementById('summary-total-received');
                const summaryPayable       = document.getElementById('summary-total-payable');

                if (summaryTotalShops)  summaryTotalShops.innerText  = overview.length;
                if (summaryGlBills)     summaryGlBills.innerText     = `₹${(totals.total_green_leaf_bills || 0).toFixed(2)}`;
                if (summaryReceived)    summaryReceived.innerText     = `₹${(totals.total_received_today || 0).toFixed(2)}`;
                if (summaryPayable)     summaryPayable.innerText      = `₹${(totals.closing_shop_position || 0).toFixed(2)}`;

                const clientGroups = data.client_groups || [];
                const clientShops = clientGroups.flatMap(group => group.shops || []);
                const directShops = data.direct_owned_shops || [];

                // ──────────────────────────────────────────────────────────────
                // CLIENT-OWNED SHOPS TABLE (GL Bills-centric view)
                // ──────────────────────────────────────────────────────────────
                const clientTbody = document.getElementById('client-shops-tbody');
                const clientTfoot = document.getElementById('client-shops-tfoot');
                const clientTitle = document.getElementById('client-group-title');
                const clientGroupTotals = document.getElementById('client-group-totals');

                if (clientShops.length > 0) {
                    if (clientTitle) clientTitle.innerText = clientGroups.length === 1
                        ? `${clientGroups[0].client.name} (${clientShops.length} Shops)`
                        : `Client Shops — ${clientGroups.length} Clients (${clientShops.length} Shops)`;

                    let cGlBills = 0, cReceived = 0, cNetRec = 0, cShopPos = 0, cCompPend = 0;

                    clientTbody.innerHTML = clientShops.map(item => {
                        const s    = item.snapshot;
                        const shop = item.shop;
                        const glBill      = parseFloat(item.green_leaf_bill || 0);
                        const received    = parseFloat(item.received_today || 0);
                        const netRec      = parseFloat(item.net_receivable || 0);
                        const shopPos     = parseFloat(s.closing_shop_position || 0);
                        const compPend    = parseFloat(s.closing_company_pending || 0);
                        const isClosed    = s.closed_at !== null;
                        const slug        = shop.slug || shop.shop_id;

                        cGlBills  += glBill;
                        cReceived += received;
                        cNetRec   += netRec;
                        cShopPos  += shopPos;
                        cCompPend += compPend;

                        return `
                            <tr class="hover:bg-amber-50/30 transition-all cursor-pointer" onclick="window.location.href='/admin/cashbook/shops/${slug}'">
                                <td class="py-3.5 px-4 font-sans">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-8 w-8 rounded-lg bg-amber-100 border border-amber-200 text-amber-700 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-slate-900 text-xs">${shop.name || 'Shop #' + shop.shop_id}</div>
                                            <div class="font-mono text-[10px] text-slate-500">${shop.client ? shop.client.name + ' · ' : ''}${shop.code || ''}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-amber-700">₹${glBill.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-right font-bold text-emerald-700">₹${received.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-right font-extrabold ${netRec > 0 ? 'text-rose-700' : 'text-emerald-700'}">₹${netRec.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-700">₹${shopPos.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-right font-bold text-purple-700">₹${compPend.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full ${isClosed ? 'bg-slate-100 text-slate-700 border border-slate-300' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                                        ${isClosed ? 'Closed' : 'Open'}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center" onclick="event.stopPropagation()">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="/admin/cashbook/shops/${slug}" class="px-2 py-1 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 rounded-lg">View</a>
                                         <button onclick="quickRecordPayment(${shop.shop_id})" class="px-2 py-1 text-[10px] font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-sm transition flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg> Record Payment
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    // Client group footer totals
                    clientTfoot.innerHTML = `
                        <tr class="text-[11px]">
                            <td class="py-3 px-4 font-sans font-extrabold uppercase tracking-wide">Totals (${clientShops.length} shops)</td>
                            <td class="py-3 px-4 text-right text-amber-800">₹${cGlBills.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right text-emerald-800">₹${cReceived.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right ${cNetRec > 0 ? 'text-rose-800' : 'text-emerald-800'} font-extrabold">₹${cNetRec.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right">₹${cShopPos.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right text-purple-800">₹${cCompPend.toFixed(2)}</td>
                            <td colspan="2"></td>
                        </tr>
                    `;

                    // Header totals badge
                    if (clientGroupTotals) {
                        clientGroupTotals.innerHTML = `
                            <span class="bg-white px-2.5 py-1 rounded-lg border border-amber-200 text-amber-800 font-extrabold">
                                GL Bills: ₹${cGlBills.toFixed(2)}
                            </span>
                            <span class="bg-white px-2.5 py-1 rounded-lg border border-emerald-200 text-emerald-800 font-extrabold">
                                Received: ₹${cReceived.toFixed(2)}
                            </span>
                            <span class="bg-${cNetRec > 0 ? 'rose' : 'emerald'}-50 px-2.5 py-1 rounded-lg border border-${cNetRec > 0 ? 'rose' : 'emerald'}-200 text-${cNetRec > 0 ? 'rose' : 'emerald'}-800 font-extrabold">
                                Net Due: ₹${cNetRec.toFixed(2)}
                            </span>
                        `;
                    }
                } else {
                    if (clientTbody) clientTbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-slate-400 font-sans">No client-owned shops registered.</td></tr>`;
                }

                // ──────────────────────────────────────────────────────────────
                // DIRECT SHOPS TABLE — Bills Only (GL Bills · Received · Net Due)
                // ──────────────────────────────────────────────────────────────
                const directTbody = document.getElementById('direct-shops-tbody');
                const directTfoot = document.getElementById('direct-shops-tfoot');
                const directGroupTotals = document.getElementById('direct-group-totals');
                const directTitle = document.getElementById('direct-group-title');

                if (directShops.length > 0) {
                    if (directTitle) directTitle.innerText = `Direct Shops (${directShops.length})`;

                    let dGlBills = 0, dReceived = 0, dNetRec = 0;

                    directTbody.innerHTML = directShops.map(item => {
                        const s    = item.snapshot;
                        const shop = item.shop;
                        const glBill   = parseFloat(item.green_leaf_bill || 0);
                        const received = parseFloat(item.received_today || 0);
                        const netRec   = parseFloat(item.net_receivable || 0);
                        const isClosed = s.closed_at !== null;
                        const slug     = shop.slug || shop.shop_id;

                        dGlBills  += glBill;
                        dReceived += received;
                        dNetRec   += netRec;

                        return `
                            <tr class="hover:bg-indigo-50/30 transition-all cursor-pointer" onclick="window.location.href='/admin/cashbook/shops/${slug}'">
                                <td class="py-3.5 px-4 font-sans">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-8 w-8 rounded-lg bg-indigo-100 border border-indigo-200 text-indigo-700 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-slate-900 text-xs">${shop.name || 'Shop #' + shop.shop_id}</div>
                                            <div class="font-mono text-[10px] text-slate-500">${shop.code || ''}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-amber-700">₹${glBill.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-right font-bold text-emerald-700">₹${received.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-right font-extrabold ${netRec > 0 ? 'text-rose-700' : 'text-emerald-700'}">₹${netRec.toFixed(2)}</td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full ${isClosed ? 'bg-slate-100 text-slate-700 border border-slate-300' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                                        ${isClosed ? 'Closed' : 'Open'}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center" onclick="event.stopPropagation()">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="/admin/cashbook/shops/${slug}" class="px-2 py-1 text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 rounded-lg">View</a>
                                        <button onclick="quickRecordPayment(${shop.shop_id})" class="px-2 py-1 text-[10px] font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition flex items-center gap-1">
                                            <svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><path d='M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8'/><path d='M12 18V6'/></svg>
                                            Record Payment
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    directTfoot.innerHTML = `
                        <tr class="text-[11px]">
                            <td class="py-3 px-4 font-sans font-extrabold uppercase tracking-wide">Totals (${directShops.length} shops)</td>
                            <td class="py-3 px-4 text-right text-amber-800">₹${dGlBills.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right text-emerald-800">₹${dReceived.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right ${dNetRec > 0 ? 'text-rose-800' : 'text-emerald-800'} font-extrabold">₹${dNetRec.toFixed(2)}</td>
                            <td colspan="2"></td>
                        </tr>
                    `;

                    if (directGroupTotals) {
                        directGroupTotals.innerHTML = `
                            <span class="bg-white px-2.5 py-1 rounded-lg border border-amber-200 text-amber-800 font-extrabold">
                                GL Bills: ₹${dGlBills.toFixed(2)}
                            </span>
                            <span class="bg-white px-2.5 py-1 rounded-lg border border-emerald-200 text-emerald-800 font-extrabold">
                                Received: ₹${dReceived.toFixed(2)}
                            </span>
                            <span class="bg-${dNetRec > 0 ? 'rose' : 'emerald'}-50 px-2.5 py-1 rounded-lg border border-${dNetRec > 0 ? 'rose' : 'emerald'}-200 text-${dNetRec > 0 ? 'rose' : 'emerald'}-800 font-extrabold">
                                Net Due: ₹${dNetRec.toFixed(2)}
                            </span>
                        `;
                    }
                } else {
                    if (directTbody) directTbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-slate-400 font-sans italic">No direct shops registered yet.</td></tr>`;
                }

                lucide.createIcons();
            } catch (err) {
                console.error('Failed to load all shops overview:', err);
                showToast('Failed to load all shops overview', 'error');
            }
        }

        function selectShopAndOpenDashboard(shopId) {
            document.getElementById('active-shop-selector').value = shopId;
            handleShopChange(shopId);
            switchTab('dashboard');
        }

        async function loadPayablesAndPendings() {
            try {
                const res = await fetch(`/admin/cashbook/api/payables-pendings?business_date=${currentDate}`);
                const data = await res.json();

                if (data.success) {
                    const payablesTbody = document.getElementById('payables-tbody');
                    const pendingsTbody = document.getElementById('pendings-tbody');

                    if (data.payables.length === 0) {
                        payablesTbody.innerHTML = `<tr><td colspan="3" class="py-6 text-center text-slate-400 font-sans">No shops owe the company.</td></tr>`;
                    } else {
                        payablesTbody.innerHTML = data.payables.map(p => `
                            <tr class="hover:bg-amber-50/40 transition-all">
                                <td class="py-3 px-3 font-sans font-bold text-slate-900">${p.shop.name} (${p.shop.code})</td>
                                <td class="py-3 px-3 text-right font-bold text-amber-600">₹${p.amount.toFixed(2)}</td>
                                <td class="py-3 px-3 text-right">
                                    <button onclick="window.location.href='/admin/cashbook/shops/' + ('${p.shop.slug}' || ${p.shop.shop_id}) + '/settlement'" class="px-3 py-1 text-[11px] font-extrabold bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-sm">
                                        Accept Payment
                                    </button>
                                </td>
                            </tr>
                        `).join('');
                    }

                    if (data.pendings.length === 0) {
                        pendingsTbody.innerHTML = `<tr><td colspan="3" class="py-6 text-center text-slate-400 font-sans">No pending reimbursements owed by company.</td></tr>`;
                    } else {
                        pendingsTbody.innerHTML = data.pendings.map(p => `
                            <tr class="hover:bg-purple-50/40 transition-all">
                                <td class="py-3 px-3 font-sans font-bold text-slate-900">${p.shop.name} (${p.shop.code})</td>
                                <td class="py-3 px-3 text-right font-bold text-purple-600">₹${p.amount.toFixed(2)}</td>
                                <td class="py-3 px-3 text-right">
                                    <button onclick="prefillPayShop(${p.shop.shop_id}, ${p.amount})" class="px-3 py-1 text-[11px] font-extrabold bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow-sm">
                                        Pay Shop
                                    </button>
                                </td>
                            </tr>
                        `).join('');
                    }
                }
            } catch (err) {
                showToast('Failed to load payables/pendings', 'error');
            }
        }

        function prefillAcceptPayment(shopId, amount) {
            document.getElementById('payment-shop-id').value = shopId;
            document.getElementById('payment-settle-amount').value = amount;
            document.getElementById('payment-petty-amount').value = '';
            switchTab('payments');
        }

        // Quick Record Payment from All-Shops view — selects shop and loads live data
        function quickRecordPayment(shopId) {
            const shopSelect = document.getElementById('payment-shop-id');
            if (shopSelect) shopSelect.value = shopId;
            const payShopSelect = document.getElementById('pay-shop-id');
            if (payShopSelect) payShopSelect.value = shopId;
            switchTab('payments');
            handlePaymentShopChange(shopId);
        }

        function prefillPayShop(shopId, amount) {
            document.getElementById('pay-shop-id').value = shopId;
            document.getElementById('pay-shop-amount').value = amount;
            switchTab('payments');
        }

        async function handleAcceptPayment(e) {
            e.preventDefault();
            const shopId = document.getElementById('payment-shop-id').value;
            const date = document.getElementById('payment-date').value;
            const companyAccountId = document.getElementById('payment-company-account').value;
            const settleAmount = document.getElementById('payment-settle-amount').value;
            const pettyAmount = document.getElementById('payment-petty-amount').value;
            const notes = document.getElementById('payment-notes').value;

            try {
                const res = await fetch('/admin/cashbook/api/accept-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        shop_id: shopId,
                        business_date: date,
                        company_account_id: companyAccountId,
                        settle_amount: settleAmount,
                        petty_amount: pettyAmount,
                        notes: notes
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('accept-payment-form').reset();
                    switchTab('payables');
                } else {
                    showToast(data.message || 'Failed to accept payment', 'error');
                }
            } catch (err) {
                showToast('Server error while accepting payment', 'error');
            }
        }

        async function handlePayShop(e) {
            e.preventDefault();
            const shopId = document.getElementById('pay-shop-id').value;
            const date = document.getElementById('pay-shop-date').value;
            const amount = document.getElementById('pay-shop-amount').value;
            const notes = document.getElementById('pay-shop-notes').value;

            try {
                const res = await fetch('/admin/cashbook/api/pay-shop', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        shop_id: shopId,
                        business_date: date,
                        amount: amount,
                        notes: notes
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('pay-shop-form').reset();
                    switchTab('payables');
                } else {
                    showToast(data.message || 'Failed to pay shop', 'error');
                }
            } catch (err) {
                showToast('Server error while paying shop', 'error');
            }
        }

        function openAddShopModal() {
            document.getElementById('add-shop-modal').classList.remove('hidden');
        }
        function closeAddShopModal() {
            document.getElementById('add-shop-modal').classList.add('hidden');
        }

        function openCreateConfigModal() {
            document.getElementById('create-config-modal').classList.remove('hidden');
        }
        function closeCreateConfigModal() {
            document.getElementById('create-config-modal').classList.add('hidden');
        }

        async function submitCreateConfig(e) {
            e.preventDefault();
            const shopId = document.getElementById('config-shop-id-input').value;
            const entryTypeId = document.getElementById('config-entry-type-id-input').value;
            const fundingSource = document.getElementById('config-funding-source-input').value;
            const inSales = document.getElementById('config-in-sales-input').checked;
            const inExpense = document.getElementById('config-in-expense-input').checked;
            const inPl = document.getElementById('config-in-pl-input').checked;
            const secondary = document.getElementById('config-secondary-input').checked;

            try {
                const res = await fetch('/admin/cashbook/api/create-rule-config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        shop_id: shopId,
                        entry_type_id: entryTypeId,
                        default_funding_source: fundingSource,
                        include_in_sales: inSales,
                        include_in_expense: inExpense,
                        include_in_pl: inPl,
                        generates_secondary: secondary
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeCreateConfigModal();
                    loadRulesData();
                } else {
                    showToast(data.message || 'Failed to create config', 'error');
                }
            } catch (err) {
                showToast('Server error while creating config', 'error');
            }
        }

        async function submitAddShop(e) {
            e.preventDefault();
            const shopId = document.getElementById('add-shop-id-input').value;
            const code = document.getElementById('add-shop-code-input').value;
            const name = document.getElementById('add-shop-name-input').value;
            const ownershipType = document.getElementById('add-shop-ownership-input').value;
            const template = document.getElementById('add-shop-template-input').value;
            const copyFrom = document.getElementById('add-shop-copy-from-input').value;

            try {
                const res = await fetch('/admin/cashbook/api/add-shop', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        shop_id: shopId,
                        code: code,
                        name: name,
                        ownership_type: ownershipType,
                        client_id: (ownershipType === 'client' ? 1 : null),
                        profile_template: template,
                        copy_from_shop_id: copyFrom
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeAddShopModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to add shop', 'error');
                }
            } catch (err) {
                showToast('Server error while adding shop', 'error');
            }
        }

        async function updateShopRule(settingId) {
            const defaultSource = document.getElementById(`rule-source-${settingId}`).value;
            const sales = document.getElementById(`rule-sales-${settingId}`).checked;
            const expense = document.getElementById(`rule-expense-${settingId}`).checked;
            const pl = document.getElementById(`rule-pl-${settingId}`).checked;
            const secondary = document.getElementById(`rule-sec-${settingId}`).checked;

            try {
                const res = await fetch('/admin/cashbook/api/update-rule', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        setting_id: settingId,
                        default_funding_source: defaultSource,
                        include_in_sales: sales,
                        include_in_expense: expense,
                        include_in_pl: pl,
                        generates_secondary: secondary
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Shop rule updated successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to update rule', 'error');
                }
            } catch (err) {
                showToast('Server error updating shop rule', 'error');
            }
        }

        async function loadDashboardData() {
            try {
                const params = new URLSearchParams({
                    shop_id: currentShopId,
                    business_date: currentDate,
                    timeframe: globalTimeframe,
                    start_date: globalStartDate,
                    end_date: globalEndDate,
                });
                const res = await fetch(`/admin/cashbook/api/shop-data?${params.toString()}`);
                const data = await res.json();

                if (data.success) {
                    loadedTransactions = data.transactions || [];
                    renderSnapshot(data.snapshot);
                    renderTransactions(data.transactions);
                    renderCrudTable(loadedTransactions);
                    document.getElementById('dashboard-shop-title').innerText = `Shop ID: #${currentShopId}`;
                }
            } catch (err) {
                showToast('Failed to load shop data', 'error');
            }
        }

        function renderSnapshot(snapshot) {
            if (!snapshot) return;

            document.getElementById('stat-sales').innerText = `₹${parseFloat(snapshot.total_sales).toFixed(2)}`;
            document.getElementById('stat-expense').innerText = `₹${parseFloat(snapshot.total_expense).toFixed(2)}`;

            const netPl = parseFloat(snapshot.net_pl);
            const netPlEl = document.getElementById('stat-net-pl');
            netPlEl.innerText = `₹${netPl.toFixed(2)}`;
            netPlEl.className = `text-xl font-bold font-mono ${netPl < 0 ? 'text-rose-600' : 'text-emerald-600'}`;

            document.getElementById('stat-petty').innerText = `₹${parseFloat(snapshot.closing_petty).toFixed(2)}`;
            document.getElementById('stat-settlement').innerText = `₹${parseFloat(snapshot.closing_shop_position).toFixed(2)}`;
            document.getElementById('stat-company-pending').innerText = `₹${parseFloat(snapshot.closing_company_pending).toFixed(2)}`;

            const isClosed = snapshot.closed_at !== null;
            const statusBadge = document.getElementById('dashboard-day-status');
            const toggleBtn = document.getElementById('toggle-day-btn');
            const globalBtn = document.getElementById('global-toggle-day-btn');

            if (isClosed) {
                if (statusBadge) {
                    statusBadge.innerText = 'Closed';
                    statusBadge.className = 'px-2.5 py-0.5 text-xs font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-300';
                }
                const btnHtml = '<i data-lucide="unlock" class="w-3.5 h-3.5"></i> Reopen Day';
                const btnClass = 'px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm';
                if (toggleBtn) { toggleBtn.innerHTML = btnHtml; toggleBtn.className = btnClass; }
                if (globalBtn) { globalBtn.innerHTML = btnHtml; globalBtn.className = btnClass; }
            } else {
                if (statusBadge) {
                    statusBadge.innerText = 'Open';
                    statusBadge.className = 'px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200';
                }
                const btnHtml = '<i data-lucide="lock" class="w-3.5 h-3.5"></i> Close Day';
                const btnClass = 'px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm';
                if (toggleBtn) { toggleBtn.innerHTML = btnHtml; toggleBtn.className = btnClass; }
                if (globalBtn) { globalBtn.innerHTML = btnHtml; globalBtn.className = btnClass; }
            }
            lucide.createIcons();
        }

        function renderTransactions(transactions) {
            const tbody = document.getElementById('transactions-tbody');
            document.getElementById('transaction-count').innerText = `${transactions.length} entries`;

            if (transactions.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-slate-400 font-sans">No transactions recorded for this day.</td></tr>`;
                return;
            }

            tbody.innerHTML = transactions.map(t => {
                const entryName = t.entry_type ? t.entry_type.name : t.entry_type_id;
                const isGenerated = t.generated_by_rule;
                return `
                    <tr class="hover:bg-indigo-50/40 transition-all">
                        <td class="py-3 px-3 font-sans font-semibold text-slate-900">
                            ${entryName}
                            ${isGenerated ? '<span class="ml-1 px-1.5 py-0.5 text-[9px] bg-purple-100 text-purple-700 border border-purple-200 rounded font-semibold">Auto-Paired</span>' : ''}
                        </td>
                        <td class="py-3 px-3 capitalize text-slate-700 font-sans">${t.direction}</td>
                        <td class="py-3 px-3 text-right font-bold text-slate-900">₹${parseFloat(t.amount).toFixed(2)}</td>
                        <td class="py-3 px-3 capitalize text-slate-600 font-sans">${t.funding_source || '-'}</td>
                        <td class="py-3 px-3 text-right font-bold ${parseFloat(t.pl_delta) < 0 ? 'text-rose-600' : (parseFloat(t.pl_delta) > 0 ? 'text-emerald-600' : 'text-slate-400')}">
                            ${parseFloat(t.pl_delta) > 0 ? '+' : ''}${parseFloat(t.pl_delta).toFixed(2)}
                        </td>
                        <td class="py-3 px-3 text-right font-bold text-amber-600">${parseFloat(t.settlement_delta) !== 0 ? '₹' + parseFloat(t.settlement_delta).toFixed(2) : '-'}</td>
                        <td class="py-3 px-3 text-right font-bold text-sky-600">${parseFloat(t.petty_delta) !== 0 ? '₹' + parseFloat(t.petty_delta).toFixed(2) : '-'}</td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded ${t.status === 'void' ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-700 border border-slate-200'}">${t.status}</span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderCrudTable(transactions) {
            const tbody = document.getElementById('crud-tbody');
            let filtered = transactions;

            if (activeCrudFilter === 'income') {
                filtered = transactions.filter(t => t.direction === 'income');
            } else if (activeCrudFilter === 'expense') {
                filtered = transactions.filter(t => t.direction === 'expense');
            } else if (activeCrudFilter === 'transfer') {
                filtered = transactions.filter(t => t.direction === 'transfer');
            }

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-slate-400 font-sans">No matching ${activeCrudFilter} entries found.</td></tr>`;
                return;
            }

            tbody.innerHTML = filtered.map(t => {
                const entryName = t.entry_type ? t.entry_type.name : t.entry_type_id;
                const referenceLabel = escapeHtml(transactionReferenceLabel(t));
                const isIncome = t.direction === 'income';
                const isExpense = t.direction === 'expense';
                const isVoid = t.status === 'void';

                return `
                    <tr class="hover:bg-indigo-50/40 transition-all ${isVoid ? 'opacity-50 line-through bg-slate-50' : ''}">
                        <td class="py-3 px-3 font-sans font-semibold text-slate-900">
                            ${entryName}
                            ${t.generated_by_rule ? '<span class="ml-1 px-1.5 py-0.5 text-[9px] bg-purple-100 text-purple-700 border border-purple-200 rounded font-semibold">Auto-Paired</span>' : ''}
                        </td>
                        <td class="py-3 px-3 font-sans text-slate-600 max-w-[220px] truncate" title="${referenceLabel}">${referenceLabel}</td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded-full ${
                                isIncome ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' :
                                (isExpense ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-slate-100 text-slate-700 border border-slate-200')
                            }">${t.direction}</span>
                        </td>
                        <td class="py-3 px-3 capitalize text-slate-700 font-sans">${t.direction}</td>
                        <td class="py-3 px-3 text-right font-bold text-slate-900">₹${parseFloat(t.amount).toFixed(2)}</td>
                        <td class="py-3 px-3 capitalize text-slate-600 font-sans">${t.funding_source || '-'}</td>
                        <td class="py-3 px-3 text-right font-bold ${parseFloat(t.pl_delta) < 0 ? 'text-rose-600' : (parseFloat(t.pl_delta) > 0 ? 'text-emerald-600' : 'text-slate-400')}">
                            ${parseFloat(t.pl_delta) > 0 ? '+' : ''}${parseFloat(t.pl_delta).toFixed(2)}
                        </td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded ${isVoid ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-700 border border-slate-200'}">${t.status}</span>
                        </td>
                        <td class="py-3 px-3 text-right">
                            ${!isVoid && !t.generated_by_rule ? `
                                <button onclick="openEditModal(${t.id}, ${t.amount})" class="px-2.5 py-1 text-[11px] font-semibold bg-brand-50 hover:bg-brand-100 text-brand-700 border border-brand-200 rounded-lg transition-all mr-1">Edit</button>
                                <button onclick="openVoidModal(${t.id})" class="px-2.5 py-1 text-[11px] font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg transition-all">Void</button>
                            ` : (t.generated_by_rule ? '<span class="text-[10px] text-slate-400 font-sans italic">Auto-Derived</span>' : '-')}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filterCrudCategory(cat) {
            activeCrudFilter = cat;
            document.querySelectorAll('.crud-filter-btn').forEach(btn => {
                btn.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
                btn.classList.add('text-slate-600');
            });
            const active = document.getElementById(`crud-filter-${cat}`);
            if (active) {
                active.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
                active.classList.remove('text-slate-600');
            }
            renderCrudTable(loadedTransactions);
        }

        function openEditModal(id, currentAmount) {
            document.getElementById('edit-transaction-id').value = id;
            document.getElementById('edit-amount-input').value = currentAmount;
            document.getElementById('edit-modal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
        }

        async function submitEditEntry(e) {
            e.preventDefault();
            const id = document.getElementById('edit-transaction-id').value;
            const newAmount = document.getElementById('edit-amount-input').value;

            try {
                const res = await fetch('/admin/cashbook/api/update-entry', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ transaction_id: id, amount: newAmount })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Entry amount updated successfully!', 'success');
                    closeEditModal();
                    loadDashboardData();
                } else {
                    showToast(data.message || 'Failed to update entry', 'error');
                }
            } catch (err) {
                showToast('Server error while updating entry', 'error');
            }
        }

        function openVoidModal(id) {
            document.getElementById('void-transaction-id').value = id;
            document.getElementById('void-reason-input').value = '';
            document.getElementById('void-modal').classList.remove('hidden');
        }
        function closeVoidModal() {
            document.getElementById('void-modal').classList.add('hidden');
        }

        async function submitVoidEntry(e) {
            e.preventDefault();
            const id = document.getElementById('void-transaction-id').value;
            const reason = document.getElementById('void-reason-input').value;

            try {
                const res = await fetch('/admin/cashbook/api/void-entry', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ transaction_id: id, reason: reason })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Transaction line voided successfully!', 'success');
                    closeVoidModal();
                    loadDashboardData();
                } else {
                    showToast(data.message || 'Failed to void entry', 'error');
                }
            } catch (err) {
                showToast('Server error while voiding entry', 'error');
            }
        }

        // Record New Entry Form Handler — STAYS ON PAGE & SHOWS RECENTLY ENTERED BELOW
        async function handleRecordEntry(e) {
            e.preventDefault();
            const shopId = document.getElementById('form-shop-id').value;
            const businessDate = document.getElementById('form-business-date').value;
            const entryTypeCode = document.getElementById('form-entry-type').value;
            const amount = document.getElementById('form-amount').value;
            const fundingSource = document.getElementById('form-funding-source').value;
            const notes = document.getElementById('form-notes').value;

            try {
                const res = await fetch('/admin/cashbook/api/record-entry', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        shop_id: shopId,
                        business_date: businessDate,
                        entry_type_code: entryTypeCode,
                        amount: amount,
                        funding_source: fundingSource,
                        notes: notes
                    })
                });

                const data = await res.json();
                if (data.success) {
                    showToast('Transaction posted successfully!', 'success');
                    
                    const t = data.transaction;
                    sessionTransactions.unshift(t);

                    // 1. Show Just Posted Highlight Card
                    const card = document.getElementById('just-posted-card');
                    card.classList.remove('hidden');
                    document.getElementById('just-posted-id').innerText = transactionReferenceLabel(t);
                    document.getElementById('just-posted-type').innerText = t.entry_type ? t.entry_type.name : t.entry_type_id;
                    document.getElementById('just-posted-amount').innerText = `₹${parseFloat(t.amount).toFixed(2)}`;
                    document.getElementById('just-posted-source').innerText = t.funding_source || 'default';
                    document.getElementById('just-posted-pl').innerText = `${parseFloat(t.pl_delta) > 0 ? '+' : ''}₹${parseFloat(t.pl_delta).toFixed(2)}`;

                    // 2. Render Session Feed Below Form
                    renderSessionEntriesFeed();

                    // 3. Reset inputs so operator can enter next entry immediately
                    document.getElementById('form-amount').value = '';
                    document.getElementById('form-notes').value = '';

                    // Reload dashboard data in background
                    loadDashboardData();
                } else {
                    showToast(data.message || 'Failed to post entry', 'error');
                }
            } catch (err) {
                showToast('Server error while posting transaction', 'error');
            }
        }

        // Render Session Feed Below Form
        function renderSessionEntriesFeed() {
            const tbody = document.getElementById('session-entries-tbody');
            document.getElementById('session-entry-count').innerText = `${sessionTransactions.length} entries`;

            if (sessionTransactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400 font-sans">No entries submitted yet in this session. Post an entry above to see it live here.</td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = sessionTransactions.map((t, idx) => {
                const entryName = t.entry_type ? t.entry_type.name : t.entry_type_id;
                const referenceLabel = escapeHtml(transactionReferenceLabel(t));
                const isFirst = idx === 0;

                return `
                    <tr class="transition-all ${isFirst ? 'bg-emerald-50/70 font-bold border-l-4 border-l-emerald-500' : 'hover:bg-slate-50'}">
                        <td class="py-2.5 px-3 font-sans text-slate-600 max-w-[180px] truncate" title="${referenceLabel}">${referenceLabel}</td>
                        <td class="py-2.5 px-3 font-sans font-semibold text-slate-900">
                            ${entryName}
                            ${isFirst ? '<span class="ml-1.5 px-1.5 py-0.5 text-[9px] bg-emerald-600 text-white rounded font-bold uppercase tracking-wider">Just Entered</span>' : ''}
                        </td>
                        <td class="py-2.5 px-3 text-right font-bold text-slate-900">₹${parseFloat(t.amount).toFixed(2)}</td>
                        <td class="py-2.5 px-3 capitalize text-slate-600 font-sans">${t.funding_source || '-'}</td>
                        <td class="py-2.5 px-3 text-right font-bold ${parseFloat(t.pl_delta) < 0 ? 'text-rose-600' : (parseFloat(t.pl_delta) > 0 ? 'text-emerald-600' : 'text-slate-400')}">
                            ${parseFloat(t.pl_delta) > 0 ? '+' : ''}${parseFloat(t.pl_delta).toFixed(2)}
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800 border border-emerald-200">${t.status}</span>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <button type="button" onclick="removeSessionEntry(${t.id})" class="px-2 py-1 text-[10px] font-semibold rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 transition-all">
                                Remove
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function removeSessionEntry(transactionId) {
            const idx = sessionTransactions.findIndex(t => Number(t.id) === Number(transactionId));
            if (idx === -1) return;

            sessionTransactions.splice(idx, 1);
            renderSessionEntriesFeed();
            showToast('Removed entry from session feed', 'success');
        }

        async function handleToggleDay() {
            const statusBadge = document.getElementById('dashboard-day-status');
            const isClosed = statusBadge ? statusBadge.innerText.trim() === 'Closed' : false;
            const action = isClosed ? 'reopen' : 'close';

            try {
                const res = await fetch('/admin/cashbook/api/toggle-day', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        shop_id: currentShopId,
                        business_date: currentDate,
                        action: action
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    loadDashboardData();
                    if (currentTab === 'all-shops') loadAllShopsOverview();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Error toggling day status', 'error');
            }
        }

        async function loadRulesData() {
            try {
                const res = await fetch('/admin/cashbook/api/rules');
                const data = await res.json();
                if (data.success) {
                    const tbody = document.getElementById('rules-tbody');
                    let html = '';

                    Object.keys(data.rules).forEach(shopId => {
                        const settings = data.rules[shopId];
                        settings.forEach(s => {
                            const entryName = s.entry_type ? s.entry_type.name : s.entry_type_id;
                            html += `
                                <tr id="rule-row-${s.id}" class="hover:bg-indigo-50/40 transition-all">
                                    <td class="py-3 px-3 font-bold text-brand-700">Shop #${s.shop_id}</td>
                                    <td class="py-3 px-3 font-sans font-semibold text-slate-900">${entryName}</td>
                                    <td class="py-3 px-3">
                                        <select id="rule-source-${s.id}" class="bg-white text-xs font-semibold px-2 py-1 rounded border border-slate-300">
                                            <option value="none" ${s.default_funding_source === 'none' ? 'selected' : ''}>none</option>
                                            <option value="sales" ${s.default_funding_source === 'sales' ? 'selected' : ''}>sales</option>
                                            <option value="petty" ${s.default_funding_source === 'petty' ? 'selected' : ''}>petty</option>
                                            <option value="company" ${s.default_funding_source === 'company' ? 'selected' : ''}>company</option>
                                        </select>
                                    </td>
                                    <td class="py-3 px-3 text-slate-600 text-[11px] font-sans">${(s.allowed_funding_sources || []).join(', ')}</td>
                                    <td class="py-3 px-3 text-center"><input type="checkbox" id="rule-sales-${s.id}" ${s.include_in_sales ? 'checked' : ''}></td>
                                    <td class="py-3 px-3 text-center"><input type="checkbox" id="rule-expense-${s.id}" ${s.include_in_expense ? 'checked' : ''}></td>
                                    <td class="py-3 px-3 text-center"><input type="checkbox" id="rule-pl-${s.id}" ${s.include_in_pl ? 'checked' : ''}></td>
                                    <td class="py-3 px-3 text-center"><input type="checkbox" id="rule-sec-${s.id}" ${s.generates_secondary_entry ? 'checked' : ''}></td>
                                    <td class="py-3 px-3 text-right">
                                        <button onclick="updateShopRule(${s.id})" class="px-2.5 py-1 text-[11px] font-bold bg-brand-600 text-white rounded-lg shadow-sm">Save</button>
                                    </td>
                                </tr>
                            `;
                        });
                    });

                    tbody.innerHTML = html;
                }
            } catch (err) {
                showToast('Failed to load rules', 'error');
            }
        }

        async function runSpecVerification(shopId) {
            document.getElementById('spec-placeholder').classList.add('hidden');
            const outputBox = document.getElementById('spec-output');
            outputBox.classList.remove('hidden');

            const badge = document.getElementById('spec-badge');
            badge.className = 'p-4 rounded-2xl bg-slate-100 text-slate-600 text-sm font-semibold flex items-center gap-2 border border-slate-200';
            badge.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Running PRD Spec Verification simulation for Shop #' + shopId + '...';
            lucide.createIcons();

            try {
                const res = await fetch('/admin/cashbook/api/verify-spec', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ shop_id: shopId })
                });
                const data = await res.json();

                if (data.success) {
                    const allPassed = data.all_passed;
                    badge.className = allPassed
                        ? 'p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold flex items-center justify-between shadow-sm'
                        : 'p-4 rounded-2xl bg-rose-50 text-rose-800 border border-rose-200 font-bold flex items-center justify-between shadow-sm';

                    badge.innerHTML = `
                        <div class="flex items-center gap-2">
                            <i data-lucide="${allPassed ? 'check-circle' : 'x-circle'}" class="w-5 h-5 text-${allPassed ? 'emerald-600' : 'rose-600'}"></i>
                            PRD Section 5 Acceptance Benchmarks for Shop #${shopId} — ${allPassed ? 'ALL ASSERTIONS PASSED' : 'ASSERTIONS FAILED'}
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-lg bg-white/80 border border-slate-200 shadow-sm text-slate-700">Business Date: 2026-08-12</span>
                    `;

                    const grid = document.getElementById('spec-results-grid');
                    grid.innerHTML = Object.keys(data.results).map(key => {
                        const item = data.results[key];
                        return `
                            <div class="white-card p-4 rounded-2xl space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-600 uppercase">${key.replace(/_/g, ' ')}</span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold ${item.passed ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'}">
                                        ${item.passed ? 'MATCH' : 'MISMATCH'}
                                    </span>
                                </div>
                                <div class="flex items-baseline justify-between font-mono pt-1">
                                    <span class="text-xs font-semibold text-slate-500">Target: ₹${parseFloat(item.expected).toFixed(2)}</span>
                                    <span class="text-lg font-bold text-slate-900">Actual: ₹${parseFloat(item.actual).toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    }).join('');
                    lucide.createIcons();
                }
            } catch (err) {
                showToast('Failed to run verification test', 'error');
            }
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-2xl shadow-xl text-xs font-bold flex items-center justify-between pointer-events-auto transition-all transform translate-y-2 border ${
                type === 'error' ? 'bg-rose-50 text-rose-900 border-rose-200 shadow-rose-500/10' :
                type === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-200 shadow-emerald-500/10' :
                'bg-white text-slate-900 border-slate-200 shadow-slate-500/10'
            }`;
            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <i data-lucide="${type === 'error' ? 'alert-circle' : 'check-circle'}" class="w-4 h-4 text-${type === 'error' ? 'rose-600' : 'emerald-600'}"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 ml-4 font-bold">✕</button>
            `;
            container.appendChild(toast);
            lucide.createIcons();
            setTimeout(() => {
                toast.classList.add('opacity-0', '-translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    </script>
</body>
</html>
