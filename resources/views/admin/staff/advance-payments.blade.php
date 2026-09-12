<x-layouts.staff title="Advance Payments">
    <div class="mx-auto max-w-7xl space-y-6 pb-12">
        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        {{-- TOP HEADER & FILTER BAR                                                   --}}
        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                    <a href="{{ route('admin.staff.index') }}" class="hover:text-slate-900 transition">Staff Management</a>
                    <span>/</span>
                    <span class="text-slate-800">Advance Payments</span>
                </div>
                <h1 class="text-2xl font-black text-slate-950 font-sans tracking-tight mt-0.5">Advance Payments</h1>
                <p class="mt-1 text-xs font-semibold text-slate-500">Review, approve, edit amount/date, and track employee advance payments shop-wise.</p>
            </div>

            <form method="GET" action="{{ route('admin.staff.advance-payments.index') }}" class="grid gap-2.5 rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Payroll Month</label>
                    <input type="month" name="payroll_month" value="{{ $selectedPayrollMonth->format('Y-m') }}" onchange="this.form.submit()" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-800 focus:border-slate-950 focus:ring-0">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Status</label>
                    <select name="status" onchange="this.form.submit()" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-800 focus:border-slate-950 focus:ring-0">
                        <option value="all" @selected($status === 'all')>All Status</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Shop</label>
                    <select name="shop_id" onchange="this.form.submit()" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-800 focus:border-slate-950 focus:ring-0">
                        <option value="0">All Shops</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Employee</label>
                    <select name="employee_id" onchange="this.form.submit()" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-800 focus:border-slate-950 focus:ring-0">
                        <option value="0">All Employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected($selectedEmployeeId === $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="h-10 w-full rounded-xl bg-slate-950 px-4 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800 transition shadow-sm">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        {{-- FLASH MESSAGES & VALIDATION ALERTS                                        --}}
        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs font-bold text-amber-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
                <p class="font-bold mb-1">Please correct the following errors:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        {{-- SUMMARY STATS                                                             --}}
        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        <section class="grid gap-3 grid-cols-2 lg:grid-cols-4">
            <article class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Pending Requests</p>
                    <span class="w-2 h-2 rounded-full {{ $summary['pending_count'] > 0 ? 'bg-amber-500 animate-pulse' : 'bg-slate-300' }}"></span>
                </div>
                <p class="mt-2 text-2xl font-black text-amber-950 font-mono">{{ number_format($summary['pending_count']) }}</p>
                <p class="text-[11px] font-semibold text-amber-700/80 mt-0.5">Awaiting manager review</p>
            </article>
            <article class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Requested</p>
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <p class="mt-2 text-2xl font-black text-slate-950 font-mono">₹{{ number_format($summary['requested_amount'], 2) }}</p>
                <p class="text-[11px] font-semibold text-slate-500 mt-0.5">{{ $advanceRequests->count() }} total requests</p>
            </article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Total Approved</p>
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <p class="mt-2 text-2xl font-black text-emerald-950 font-mono">₹{{ number_format($summary['approved_amount'], 2) }}</p>
                <p class="text-[11px] font-semibold text-emerald-700/80 mt-0.5">{{ $advanceRequests->where('status', 'approved')->count() }} approved</p>
            </article>
            <article class="rounded-2xl border border-cyan-200 bg-cyan-50/70 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-wider text-cyan-700">Posted To Cashbook</p>
                    <svg class="w-4 h-4 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                </div>
                <p class="mt-2 text-2xl font-black text-cyan-950 font-mono">₹{{ number_format($summary['paid_amount'], 2) }}</p>
                <p class="text-[11px] font-semibold text-cyan-700/80 mt-0.5">Synced to cashbook ledger</p>
            </article>
        </section>

        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        {{-- SHOP-WISE ADVANCE PAYMENTS CONTAINER                                      --}}
        {{-- ───────────────────────────────────────────────────────────────────────── --}}
        @php
            $groupedByShop = $advanceRequests->groupBy(fn ($req) => $req->shop_id ?: 0);
        @endphp

        <div class="space-y-6">
            @forelse($groupedByShop as $shopIdKey => $shopAdvanceRequests)
                @php
                    $currentShop = $shopAdvanceRequests->first()?->shop;
                    $shopName = $currentShop?->name ?? 'General / Head Office';
                    $shopCode = $currentShop?->code ?? '';
                    $shopTotalRequested = (float) $shopAdvanceRequests->sum('requested_amount');
                    $shopTotalApproved = (float) $shopAdvanceRequests->where('status', 'approved')->sum('approved_amount');
                    $shopTotalPaid = (float) $shopAdvanceRequests->where('status', 'approved')->sum(fn ($req) => (float) ($req->shopStaffPayment?->amount ?? 0));
                    $shopEmployeeGroups = $shopAdvanceRequests->groupBy('employee_id');
                @endphp

                <section class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden" x-data="{ shopExpanded: true }">
                    {{-- Shop Header Bar --}}
                    <div class="border-b border-slate-200/80 bg-slate-900 px-5 py-3.5 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-black text-sm text-cyan-400">
                                {{ strtoupper(substr($shopName, 0, 2)) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-black tracking-tight text-white">{{ $shopName }}</h2>
                                    @if($shopCode)
                                        <span class="px-2 py-0.5 rounded-md bg-slate-800 text-[10px] font-mono text-slate-300 border border-slate-700">{{ $shopCode }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400 font-medium">
                                    {{ $shopEmployeeGroups->count() }} {{ \Illuminate\Support\Str::plural('employee', $shopEmployeeGroups->count()) }} · {{ $shopAdvanceRequests->count() }} {{ \Illuminate\Support\Str::plural('advance request', $shopAdvanceRequests->count()) }}
                                </p>
                            </div>
                        </div>

                        {{-- Shop Total Badges & Toggle --}}
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 text-xs font-mono">
                                <div class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700">
                                    <span class="text-[10px] text-slate-400 uppercase font-sans mr-1">Req:</span>
                                    <span class="font-bold text-slate-200">₹{{ number_format($shopTotalRequested, 2) }}</span>
                                </div>
                                <div class="px-2.5 py-1 rounded-lg bg-emerald-950/80 border border-emerald-700/60">
                                    <span class="text-[10px] text-emerald-400 uppercase font-sans mr-1">Appr:</span>
                                    <span class="font-bold text-emerald-300">₹{{ number_format($shopTotalApproved, 2) }}</span>
                                </div>
                                @if($shopTotalPaid > 0)
                                    <div class="px-2.5 py-1 rounded-lg bg-cyan-950/80 border border-cyan-700/60 hidden md:block">
                                        <span class="text-[10px] text-cyan-400 uppercase font-sans mr-1">Paid:</span>
                                        <span class="font-bold text-cyan-300">₹{{ number_format($shopTotalPaid, 2) }}</span>
                                    </div>
                                @endif
                            </div>

                            <button 
                                type="button" 
                                @click="shopExpanded = !shopExpanded" 
                                class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition cursor-pointer"
                                title="Toggle Shop Details"
                            >
                                <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': !shopExpanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Shop Employee Advances List --}}
                    <div x-show="shopExpanded" class="p-4 sm:p-5 space-y-4 bg-slate-50/50">
                        @foreach($shopEmployeeGroups as $employeeIdKey => $empAdvanceRequests)
                            @php
                                $firstReq = $empAdvanceRequests->first();
                                $employee = $firstReq?->employee;
                                $employeeName = $employee?->name ?? 'Unknown Employee';
                                $employeeCode = $employee?->staff_id ?? ('#'.$employeeIdKey);
                                $empDesignation = $employee?->designation ?? 'Staff Member';
                                $empTotalRequested = (float) $empAdvanceRequests->sum('requested_amount');
                                $empTotalApproved = (float) $empAdvanceRequests->where('status', 'approved')->sum('approved_amount');
                                $empTotalPaid = (float) $empAdvanceRequests->where('status', 'approved')->sum(fn ($req) => (float) ($req->shopStaffPayment?->amount ?? 0));
                                $pendingCountForEmp = $empAdvanceRequests->where('status', 'pending')->count();
                            @endphp

                            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                {{-- Employee Subheader --}}
                                <div class="bg-slate-100/80 px-4 py-3 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center border border-slate-300">
                                            {{ strtoupper(substr($employeeName, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-sm font-black text-slate-900">{{ $employeeName }}</h3>
                                                <span class="text-[11px] font-mono text-slate-500 font-bold">({{ $employeeCode }})</span>
                                                @if($pendingCountForEmp > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                        {{ $pendingCountForEmp }} Pending Review
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-slate-500 font-medium">{{ $empDesignation }} · {{ $empAdvanceRequests->count() }} records this month</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 font-mono text-xs">
                                        <div>
                                            <span class="text-[10px] uppercase font-sans text-slate-400 block sm:inline">Total Requested:</span>
                                            <span class="font-bold text-slate-900">₹{{ number_format($empTotalRequested, 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-[10px] uppercase font-sans text-emerald-600 block sm:inline">Approved:</span>
                                            <span class="font-bold text-emerald-700">₹{{ number_format($empTotalApproved, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Date-Only Breakdown Table --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-200/60 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                                <th class="py-2.5 px-4">Date</th>
                                                <th class="py-2.5 px-3">Req ID</th>
                                                <th class="py-2.5 px-4 text-right">Requested</th>
                                                <th class="py-2.5 px-4 text-right">Approved</th>
                                                <th class="py-2.5 px-3 text-center">Status</th>
                                                <th class="py-2.5 px-4">Cashbook / Notes</th>
                                                <th class="py-2.5 px-4 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                            @foreach($empAdvanceRequests->sortByDesc(fn ($r) => $r->requested_on?->toDateString() ?? $r->created_at->toDateString()) as $advanceRequest)
                                                @php
                                                    $reqDate = $advanceRequest->requested_on ?? $advanceRequest->created_at;
                                                    $formattedDate = $reqDate->format('d M Y');
                                                    $dateInputVal = $reqDate->format('Y-m-d');
                                                    $dayOfWeek = $reqDate->format('D');
                                                    $reqAmt = (float) $advanceRequest->requested_amount;
                                                    $apprAmt = $advanceRequest->approved_amount !== null ? (float) $advanceRequest->approved_amount : null;
                                                    $currentEffectiveAmount = $apprAmt !== null ? $apprAmt : $reqAmt;
                                                    $currentNote = $advanceRequest->request_note ?: ($advanceRequest->review_note ?: '');
                                                    $statusBadgeClass = match ($advanceRequest->status) {
                                                        'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                                        'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                                                        default => 'border-amber-200 bg-amber-50 text-amber-700',
                                                    };
                                                    $defaultFundSource = $advanceRequest->fund_source ?: 'sales_income';
                                                    
                                                    $editPayload = [
                                                        'id' => $advanceRequest->id,
                                                        'employeeName' => $employeeName,
                                                        'shopName' => $shopName,
                                                        'formattedDate' => $formattedDate,
                                                        'date' => $dateInputVal,
                                                        'amount' => (float) $currentEffectiveAmount,
                                                        'note' => (string) ($currentNote ?? ''),
                                                        'actionUrl' => route('admin.staff.advance-requests.update', $advanceRequest),
                                                    ];

                                                    $reviewPayload = [
                                                        'id' => $advanceRequest->id,
                                                        'employeeName' => $employeeName,
                                                        'shopName' => $shopName,
                                                        'date' => $formattedDate,
                                                        'requestedAmount' => (float) $reqAmt,
                                                        'fundSource' => (string) $defaultFundSource,
                                                        'companyAccountId' => (string) ($advanceRequest->company_account_id ?? ''),
                                                        'requestNote' => (string) ($advanceRequest->request_note ?? ''),
                                                        'actionUrl' => route('admin.staff.advance-requests.review', $advanceRequest),
                                                    ];
                                                @endphp

                                                <tr class="hover:bg-slate-50/70 transition">
                                                    {{-- 1. Date Only --}}
                                                    <td class="py-3 px-4 whitespace-nowrap">
                                                        <div class="flex items-center gap-2">
                                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-900 font-mono font-bold text-xs">
                                                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                                {{ $formattedDate }}
                                                            </span>
                                                            <span class="text-[10px] text-slate-400 font-mono">({{ $dayOfWeek }})</span>
                                                        </div>
                                                    </td>

                                                    {{-- 2. Request ID & Creator --}}
                                                    <td class="py-3 px-3 whitespace-nowrap">
                                                        <span class="font-mono font-bold text-slate-800">#{{ $advanceRequest->id }}</span>
                                                        <span class="block text-[10px] text-slate-400">By {{ $advanceRequest->requestedBy?->name ?? 'Shop incharge' }}</span>
                                                    </td>

                                                    {{-- 3. Requested Amount --}}
                                                    <td class="py-3 px-4 text-right font-mono font-semibold text-slate-800 whitespace-nowrap">
                                                        ₹{{ number_format($reqAmt, 2) }}
                                                    </td>

                                                    {{-- 4. Approved Amount --}}
                                                    <td class="py-3 px-4 text-right font-mono font-bold whitespace-nowrap {{ $advanceRequest->status === 'approved' ? 'text-emerald-600' : 'text-slate-400' }}">
                                                        {{ $apprAmt !== null ? '₹'.number_format($apprAmt, 2) : '—' }}
                                                    </td>

                                                    {{-- 5. Status Badge --}}
                                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $statusBadgeClass }}">
                                                            {{ $advanceRequest->status }}
                                                        </span>
                                                    </td>

                                                    {{-- 6. Cashbook & Notes --}}
                                                    <td class="py-3 px-4">
                                                        <div class="space-y-0.5 max-w-xs">
                                                            @if($advanceRequest->shopStaffPayment)
                                                                <div class="flex items-center gap-1 text-[11px] font-semibold text-cyan-700">
                                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                                    <span>{{ $advanceRequest->shopStaffPayment->cashbookLine ? 'Posted to shop cashbook' : 'Cashbook synced' }}</span>
                                                                </div>
                                                            @endif
                                                            @if($advanceRequest->request_note)
                                                                <p class="text-xs text-slate-600 truncate" title="{{ $advanceRequest->request_note }}">
                                                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Note:</span> {{ $advanceRequest->request_note }}
                                                                </p>
                                                            @endif
                                                            @if($advanceRequest->review_note && $advanceRequest->review_note !== $advanceRequest->request_note)
                                                                <p class="text-[11px] text-slate-500 italic truncate" title="{{ $advanceRequest->review_note }}">
                                                                    <span class="font-semibold text-slate-400">Review:</span> {{ $advanceRequest->review_note }}
                                                                </p>
                                                            @endif
                                                            @if(! $advanceRequest->shopStaffPayment && ! $advanceRequest->request_note && ! $advanceRequest->review_note)
                                                                <span class="text-slate-300">—</span>
                                                            @endif
                                                        </div>
                                                    </td>

                                                    {{-- 7. Actions --}}
                                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                                            {{-- Pending Quick Review Button --}}
                                                            @if($advanceRequest->status === 'pending')
                                                                <button 
                                                                    type="button" 
                                                                    onclick="openAdvanceReviewModal({{ json_encode($reviewPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }})" 
                                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-amber-300 bg-amber-50 text-[11px] font-bold text-amber-800 hover:bg-amber-100 transition shadow-sm cursor-pointer"
                                                                >
                                                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                    Review
                                                                </button>
                                                            @endif

                                                            {{-- Edit Modal Trigger --}}
                                                            <button 
                                                                type="button" 
                                                                onclick="openAdvanceEditModal({{ json_encode($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }})" 
                                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition shadow-sm cursor-pointer"
                                                                title="Edit date or amount"
                                                            >
                                                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                                Edit
                                                            </button>

                                                            {{-- Delete Form --}}
                                                            <form method="POST" action="{{ route('admin.staff.advance-requests.destroy', $advanceRequest) }}" onsubmit="return confirm('Are you sure you want to delete advance request #{{ $advanceRequest->id }} for {{ addslashes($employeeName) }} on {{ $formattedDate }}?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-[11px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-900 transition shadow-sm cursor-pointer" title="Delete request">
                                                                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="mt-3 text-base font-black text-slate-900">No advance requests found.</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Try changing the filter parameters or select a different payroll month.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    {{-- GLOBAL EDIT ADVANCE MODAL                                                 --}}
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    <div id="advance-edit-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm hidden" onclick="if(event.target === this) closeAdvanceEditModal()">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl space-y-4 text-left animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 id="edit-modal-title" class="text-base font-black text-slate-950">Edit Advance Payment</h3>
                    <p id="edit-modal-subtitle" class="text-xs font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeAdvanceEditModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form id="edit-modal-form" method="POST" action="" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="space-y-1">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-600">Advance Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" id="edit-modal-amount" name="amount" required class="h-11 w-full rounded-xl border border-slate-300 px-3 text-base font-black text-slate-950 focus:border-slate-950 focus:ring-0">
                    <p class="text-[11px] text-slate-500 font-medium">Updating amount recalculates linked payment and cashbook balances.</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-600">Payment / Request Date</label>
                    <input type="date" id="edit-modal-date" name="requested_on" required class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm font-bold text-slate-950 focus:border-slate-950 focus:ring-0">
                    <p class="text-[11px] text-slate-500 font-medium">Changing the date automatically updates daily ledger and accounting entries.</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-600">Notes / Reason (Optional)</label>
                    <textarea id="edit-modal-note" name="note" rows="2" placeholder="Update notes or reason for this advance..." class="w-full rounded-xl border border-slate-300 p-3 text-sm text-slate-950 focus:border-slate-950 focus:ring-0"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeAdvanceEditModal()" class="h-10 rounded-xl border border-slate-200 bg-slate-100 px-4 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                    <button type="submit" class="h-10 rounded-xl bg-slate-950 px-5 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800 transition shadow cursor-pointer">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    {{-- GLOBAL REVIEW ADVANCE MODAL                                               --}}
    {{-- ───────────────────────────────────────────────────────────────────────── --}}
    <div id="advance-review-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm hidden" onclick="if(event.target === this) closeAdvanceReviewModal()">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl space-y-4 text-left animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200 mb-1">
                        Pending Review
                    </span>
                    <h3 id="review-modal-title" class="text-base font-black text-slate-950">Review Advance Request</h3>
                    <p id="review-modal-subtitle" class="text-xs font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeAdvanceReviewModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div id="review-modal-note-box" class="rounded-xl bg-slate-50 p-3 text-xs border border-slate-200/80 hidden">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Staff Request Note</span>
                <p id="review-modal-request-note" class="font-medium text-slate-800"></p>
            </div>

            <form id="review-modal-form" method="POST" action="" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-black uppercase tracking-wider text-slate-600">Approved Amount (₹)</label>
                        <span id="review-modal-req-badge" class="text-[11px] font-mono text-slate-400"></span>
                    </div>
                    <input type="number" step="0.01" min="0.01" id="review-modal-approved-amount" name="approved_amount" required class="h-11 w-full rounded-xl border border-slate-300 px-3 text-base font-black text-slate-950 focus:border-slate-950 focus:ring-0">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-600">Fund Source</label>
                    <select id="review-modal-fund-source" name="fund_source" onchange="toggleCompanyAccountField(this.value)" class="h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-bold text-slate-800 focus:border-slate-950 focus:ring-0">
                        <option value="sales_income">Shop Sales Income (Till / Cash Drawer)</option>
                        <option value="petty_cash">Shop Petty Cash</option>
                        <option value="company_cash">Company Cash Account</option>
                        <option value="company_bank">Company Bank Account</option>
                    </select>
                </div>

                <div id="review-company-account-container" class="space-y-1 hidden">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-600">Company Account</label>
                    <select id="review-modal-company-account" name="company_account_id" class="h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-bold text-slate-800 focus:border-slate-950 focus:ring-0">
                        <option value="">Select Company Account...</option>
                        @foreach($companyAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} ({{ strtoupper($account->account_type) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-600">Review Note (Required if Rejecting)</label>
                    <textarea id="review-modal-note" name="review_note" rows="2" placeholder="Add review notes or reason for decision..." class="w-full rounded-xl border border-slate-300 p-3 text-sm text-slate-950 focus:border-slate-950 focus:ring-0"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeAdvanceReviewModal()" class="h-10 rounded-xl border border-slate-200 bg-slate-100 px-4 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                    <button type="submit" name="decision" value="reject" class="h-10 rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-black uppercase tracking-wider text-rose-700 hover:bg-rose-100 transition cursor-pointer">Reject</button>
                    <button type="submit" name="decision" value="approve" class="h-10 rounded-xl bg-emerald-600 px-5 text-xs font-black uppercase tracking-wider text-white hover:bg-emerald-700 transition shadow cursor-pointer">Approve &amp; Pay</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    function openAdvanceEditModal(data) {
        document.getElementById('edit-modal-title').innerText = 'Edit Advance Payment #' + (data.id || '');
        document.getElementById('edit-modal-subtitle').innerText = (data.employeeName || '') + ' · ' + (data.shopName || '') + ' · Date: ' + (data.formattedDate || '');
        document.getElementById('edit-modal-form').action = data.actionUrl || '';
        document.getElementById('edit-modal-amount').value = data.amount || '';
        document.getElementById('edit-modal-date').value = data.date || '';
        document.getElementById('edit-modal-note').value = data.note || '';

        const modal = document.getElementById('advance-edit-modal');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeAdvanceEditModal() {
        const modal = document.getElementById('advance-edit-modal');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function openAdvanceReviewModal(data) {
        document.getElementById('review-modal-title').innerText = 'Review Advance Request #' + (data.id || '');
        document.getElementById('review-modal-subtitle').innerText = (data.employeeName || '') + ' · ' + (data.shopName || '') + ' · Date: ' + (data.date || '');
        document.getElementById('review-modal-form').action = data.actionUrl || '';
        
        const reqAmt = Number(data.requestedAmount || 0);
        document.getElementById('review-modal-req-badge').innerText = 'Requested: ₹' + reqAmt.toFixed(2);
        document.getElementById('review-modal-approved-amount').value = reqAmt ? reqAmt.toFixed(2) : '';
        
        const fundSource = data.fundSource || 'sales_income';
        document.getElementById('review-modal-fund-source').value = fundSource;
        toggleCompanyAccountField(fundSource);
        
        document.getElementById('review-modal-company-account').value = data.companyAccountId || '';
        document.getElementById('review-modal-note').value = '';

        const noteBox = document.getElementById('review-modal-note-box');
        const reqNote = document.getElementById('review-modal-request-note');
        if (data.requestNote && data.requestNote.trim().length > 0) {
            reqNote.innerText = data.requestNote;
            noteBox.classList.remove('hidden');
        } else {
            noteBox.classList.add('hidden');
        }

        const modal = document.getElementById('advance-review-modal');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeAdvanceReviewModal() {
        const modal = document.getElementById('advance-review-modal');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function toggleCompanyAccountField(fundSource) {
        const container = document.getElementById('review-company-account-container');
        if (fundSource === 'company_cash' || fundSource === 'company_bank') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }

    // Keyboard ESC listener
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAdvanceEditModal();
            closeAdvanceReviewModal();
        }
    });
    </script>
    @endpush
</x-layouts.staff>
