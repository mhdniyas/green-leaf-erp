<x-layouts.app title="Admin Control Center">
    @php
        $prevDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = now()->toDateString();
        $overviewCards = [
            ['label' => 'Today Orders', 'value' => number_format($overview['today_orders']), 'hint' => $overview['submitted_orders'].' waiting approval', 'tone' => 'slate'],
            ['label' => 'Delivered Orders', 'value' => number_format($overview['delivered_orders']), 'hint' => $overview['approved_orders'].' approved today', 'tone' => 'emerald'],
            ['label' => 'Inventory Received', 'value' => number_format($overview['received_kg_today'], 2).' kg', 'hint' => $overview['pending_batches'].' batch(es) pending', 'tone' => 'cyan'],
            ['label' => 'Purchase Flow', 'value' => number_format($overview['open_purchase_orders']), 'hint' => $overview['pending_grn_approval'].' GRNs + '.$overview['pending_invoices'].' invoices pending', 'tone' => 'violet'],
            ['label' => 'Wastage Today', 'value' => number_format($overview['wastage_kg_today'], 2).' kg', 'hint' => 'stock loss recorded today', 'tone' => 'amber'],
            ['label' => 'Users Online', 'value' => number_format($overview['online_users']), 'hint' => 'live activity in last 5 minutes', 'tone' => 'rose'],
        ];

        $toneClasses = [
            'slate' => 'border-slate-200 bg-slate-50 text-slate-700',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'cyan' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
            'violet' => 'border-violet-200 bg-violet-50 text-violet-700',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
            'rose' => 'border-rose-200 bg-rose-50 text-rose-700',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
            'danger' => 'border-red-200 bg-red-50 text-red-700',
            'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(6,182,212,0.18),_transparent_35%),linear-gradient(135deg,_#020617,_#0f172a_60%,_#111827)] p-6 text-white shadow-[0_24px_80px_rgba(15,23,42,0.18)] sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.34em] text-cyan-200">Admin Overview</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl">Control operations, cash flow, and user access from one place.</h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-300">
                        Monitor today&apos;s order flow, live inventory movement, warehouse execution, purchase approvals, and user-level access overrides without leaving the admin workspace.
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.overview') }}" class="flex flex-wrap items-center gap-2 rounded-[1.75rem] border border-white/10 bg-white/10 p-2 backdrop-blur">
                    <a href="{{ route('admin.overview', ['date' => $prevDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-slate-900/70 text-white transition hover:bg-slate-800" title="Previous day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[12rem] rounded-2xl border border-white/10 bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.overview', ['date' => $todayDate]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-cyan-300/40 bg-cyan-400/20 px-4 text-xs font-black uppercase tracking-[0.24em] text-cyan-100 transition hover:bg-cyan-400/30">
                            Today
                        </a>
                    @endif
                    <a href="{{ route('admin.overview', ['date' => $nextDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-slate-900/70 text-white transition hover:bg-slate-800" title="Next day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($overviewCards as $card)
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">{{ $card['label'] }}</span>
                    <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ $card['value'] }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-500">{{ $card['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Role Progress</p>
                        <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Who is online and what is blocked</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-slate-500">{{ $onlineUsers->count() }} live now</span>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @foreach($roleProgress as $role)
                        <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ $role['name'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $role['label'] }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] {{ $toneClasses[$role['tone']] ?? $toneClasses['slate'] }}">
                                    {{ $role['online'] }} online
                                </span>
                            </div>
                            <div class="mt-5 flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Assigned Users</p>
                                    <p class="mt-2 text-3xl font-black text-slate-950">{{ $role['count'] }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Needs Action</p>
                                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $role['pending'] }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Quick Access</p>
                <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Jump into active workflows</h2>
                <div class="mt-6 space-y-3">
                    @foreach($quickLinks as $link)
                        <a href="{{ $link['href'] }}" class="flex items-center justify-between rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-black text-slate-900 transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-800">
                            <span>{{ $link['label'] }}</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Finance Snapshot</p>
                        <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">How cash moved today</h2>
                    </div>
                    <a href="{{ route('finance.reports.cash-flow') }}" class="rounded-full border border-slate-200 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 transition hover:border-cyan-200 hover:text-cyan-700">Cash Flow</a>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <article class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">Cash Collected</p>
                        <p class="mt-3 text-3xl font-black text-emerald-950">Rs. {{ number_format($finance['cash_collected_today'], 2) }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-red-200 bg-red-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-red-700">Cash Discrepancy</p>
                        <p class="mt-3 text-3xl font-black text-red-950">Rs. {{ number_format($finance['cash_discrepancy_today'], 2) }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-amber-700">Expense Outflow</p>
                        <p class="mt-3 text-3xl font-black text-amber-950">Rs. {{ number_format($finance['expense_outflow_today'], 2) }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border border-violet-200 bg-violet-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-violet-700">Supplier Payments</p>
                        <p class="mt-3 text-3xl font-black text-violet-950">Rs. {{ number_format($finance['supplier_payments_today'], 2) }}</p>
                    </article>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-500">Pending Supplier Dues</p>
                        <p class="mt-3 text-2xl font-black text-slate-950">Rs. {{ number_format($finance['pending_supplier_dues'], 2) }}</p>
                    </article>
                    <article class="rounded-[1.5rem] border {{ $finance['net_cash_position_today'] >= 0 ? 'border-cyan-200 bg-cyan-50' : 'border-rose-200 bg-rose-50' }} p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] {{ $finance['net_cash_position_today'] >= 0 ? 'text-cyan-700' : 'text-rose-700' }}">Net Cash Position</p>
                        <p class="mt-3 text-2xl font-black {{ $finance['net_cash_position_today'] >= 0 ? 'text-cyan-950' : 'text-rose-950' }}">Rs. {{ number_format($finance['net_cash_position_today'], 2) }}</p>
                    </article>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Suspicious Activity</p>
                        <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Flagged operational signals</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">{{ $suspiciousActivities->count() }} flag(s)</span>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse($suspiciousActivities as $activity)
                        <article class="rounded-[1.35rem] border p-4 {{ $toneClasses[$activity['severity']] ?? $toneClasses['slate'] }}">
                            <p class="text-sm font-black">{{ $activity['title'] }}</p>
                            <p class="mt-1 text-xs font-semibold leading-5 opacity-90">{{ $activity['detail'] }}</p>
                        </article>
                    @empty
                        <div class="rounded-[1.35rem] border border-emerald-200 bg-emerald-50 p-5 text-emerald-800">
                            <p class="text-sm font-black">No suspicious activity detected for this date.</p>
                            <p class="mt-1 text-xs font-semibold">Cash, warehouse, GRN, and permission signals look normal.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Live Users</p>
                        <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Who is online right now</h2>
                    </div>
                    <a href="{{ route('admin.users.index') }}" class="rounded-full border border-slate-200 px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 transition hover:border-cyan-200 hover:text-cyan-700">Manage Users</a>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse($onlineUsers as $user)
                        <article class="flex items-center justify-between gap-3 rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                    <p class="truncate text-sm font-black text-slate-950">{{ $user->name }}</p>
                                </div>
                                <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $user->email }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                                    @foreach($user->roles as $role)
                                        <span class="rounded-full border border-slate-200 bg-white px-2 py-1">{{ $role->name }}</span>
                                    @endforeach
                                    @if($user->shop)
                                        <span class="rounded-full border border-cyan-200 bg-cyan-50 px-2 py-1 text-cyan-700">{{ $user->shop->name }}</span>
                                    @endif
                                </div>
                            </div>
                            <p class="shrink-0 text-[11px] font-bold text-slate-400">{{ optional($user->last_seen_at)->diffForHumans() }}</p>
                        </article>
                    @empty
                        <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                            No users are active in the last five minutes.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Permission Overrides</p>
                    <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Users with direct control changes</h2>
                    <div class="mt-6 space-y-3">
                        @forelse($usersWithDirectPermissions->take(8) as $user)
                            <article class="rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-950">{{ $user->name }}</p>
                                        <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $user->email }}</p>
                                    </div>
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">
                                        {{ $user->permissions->count() }} override(s)
                                    </span>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-[1.35rem] border border-emerald-200 bg-emerald-50 p-5 text-sm font-semibold text-emerald-800">
                                No users have direct permission overrides.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Recent Activity</p>
                    <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">What happened across the app</h2>
                    <div class="mt-6 space-y-3">
                        @forelse($recentActivities as $activity)
                            <article class="rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-slate-950">{{ $activity->description }}</p>
                                    <span class="text-[11px] font-bold text-slate-400">{{ $activity->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ $activity->causer?->name ?? 'System' }}
                                    @if($activity->event)
                                        • {{ $activity->event }}
                                    @endif
                                </p>
                            </article>
                        @empty
                            <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                                No activity records available yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
