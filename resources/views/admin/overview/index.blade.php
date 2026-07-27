<x-layouts.admin title="Admin Control Center">
    @php
        $prevDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = now()->toDateString();
        $mainSummary = $salesChannelContext['summary'];
        $salesChannels = $salesChannelContext['channels'];
        $mainStats = [
            ['label' => 'Total Sales', 'value' => 'Rs. '.number_format($mainSummary['sales_total'], 2), 'hint' => $mainSummary['invoice_count'].' invoice(s)'],
            ['label' => 'Collections', 'value' => 'Rs. '.number_format($mainSummary['collections_total'], 2), 'hint' => 'cash received today'],
            ['label' => 'Outstanding', 'value' => 'Rs. '.number_format($mainSummary['outstanding_total'], 2), 'hint' => 'pending invoice balance'],
            ['label' => 'Orders', 'value' => number_format($mainSummary['orders_today']), 'hint' => $mainSummary['delivered_orders'].' delivered'],
            ['label' => 'Inventory In', 'value' => number_format($mainSummary['received_kg_today'], 2).' kg', 'hint' => 'warehouse received'],
            ['label' => 'Staff Present', 'value' => number_format($mainSummary['present_staff']), 'hint' => $mainSummary['online_users'].' user(s) online'],
        ];
        $overviewCards = [
            ['label' => 'Today Orders', 'value' => number_format($overview['today_orders']), 'hint' => $overview['submitted_orders'].' waiting approval', 'tone' => 'slate'],
            ['label' => 'Delivered Orders', 'value' => number_format($overview['delivered_orders']), 'hint' => $overview['approved_orders'].' approved today', 'tone' => 'emerald'],
            ['label' => 'Inventory Received', 'value' => number_format($overview['received_kg_today'], 2).' kg', 'hint' => $overview['pending_batches'].' batch(es) pending', 'tone' => 'cyan'],
            ['label' => 'Purchase Flow', 'value' => number_format($overview['open_purchase_orders']), 'hint' => $overview['pending_grn_approval'].' GRNs + '.$overview['pending_invoices'].' invoices pending', 'tone' => 'violet'],
            ['label' => 'Wastage Today', 'value' => number_format($overview['wastage_kg_today'], 2).' kg', 'hint' => 'stock loss recorded today', 'tone' => 'amber'],
            ['label' => 'Users Online', 'value' => number_format($overview['online_users']), 'hint' => 'live activity in last 5 minutes', 'tone' => 'rose'],
            ['label' => 'Staff Present', 'value' => number_format($overview['present_staff']), 'hint' => $overview['staff_on_leave'].' on leave • '.$overview['pending_leave_requests'].' pending request(s)', 'tone' => 'emerald'],
            ['label' => 'Employees', 'value' => number_format($overview['total_employees']), 'hint' => 'staff covered under payroll', 'tone' => 'slate'],
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
        $channelToneClasses = [
            'emerald' => [
                'panel' => 'border-emerald-200 bg-emerald-50 hover:border-emerald-300',
                'eyebrow' => 'text-emerald-700',
                'badge' => 'border-emerald-200 bg-white text-emerald-700',
                'link' => 'text-emerald-800 group-hover:text-emerald-950',
            ],
            'cyan' => [
                'panel' => 'border-cyan-200 bg-cyan-50 hover:border-cyan-300',
                'eyebrow' => 'text-cyan-700',
                'badge' => 'border-cyan-200 bg-white text-cyan-700',
                'link' => 'text-cyan-800 group-hover:text-cyan-950',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700">Green Leaf Traders</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Green Leaf Main Company</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Today&apos;s sales, collections, outstanding invoices, delivery flow, inventory, and staff visibility.</p>
                </div>

                <form method="GET" action="{{ route('admin.overview') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2">
                    <a href="{{ route('admin.overview', ['date' => $prevDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700" title="Previous day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[12rem] rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-900">
                        <span class="block text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.overview', ['date' => $todayDate]) }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.22em] text-emerald-700 transition hover:bg-emerald-100">
                            Today
                        </a>
                    @endif
                    <a href="{{ route('admin.overview', ['date' => $nextDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700" title="Next day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($mainStats as $stat)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $stat['hint'] }}</p>
                    </div>
                @endforeach
            </div>

            <details open class="mt-5 group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-900 transition hover:border-emerald-200">
                    <span>Sales channels</span>
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition group-open:rotate-90">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                        </svg>
                    </span>
                </summary>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach($salesChannels as $channel)
                        @php($channelTone = $channelToneClasses[$channel['tone']] ?? $channelToneClasses['emerald'])
                        <article class="group rounded-[1.35rem] border p-5 transition {{ $channelTone['panel'] }}">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-[0.24em] {{ $channelTone['eyebrow'] }}">{{ $channel['label'] }}</p>
                                    <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Rs. {{ number_format($channel['summary']['total_amount'], 2) }}</h2>
                                    <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">{{ $channel['description'] }}</p>
                                </div>
                                <span class="inline-flex w-fit shrink-0 rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.2em] {{ $channelTone['badge'] }}">
                                    {{ $channel['shop_count'] }} shop(s)
                                </span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Invoices</p>
                                    <p class="mt-1 text-lg font-black text-slate-950">{{ number_format($channel['summary']['invoice_count']) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Paid</p>
                                    <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($channel['summary']['paid_amount'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Pending</p>
                                    <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format($channel['summary']['outstanding_amount'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Settled</p>
                                    <p class="mt-1 text-lg font-black text-slate-950">{{ number_format($channel['summary']['settlement_rate'], 1) }}%</p>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center gap-3 text-xs font-black uppercase tracking-[0.18em]">
                                <a href="{{ $channel['href'] }}" class="{{ $channelTone['link'] }}">Open details</a>
                                <span class="text-slate-300">/</span>
                                <a href="{{ $channel['secondary_href'] }}" class="{{ $channelTone['link'] }}">{{ $channel['secondary_label'] }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </details>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Action Required</p>
                    <h2 class="mt-2 text-xl font-black tracking-tight text-slate-950">Pending approvals and review queues</h2>
                </div>
                <span class="inline-flex w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.22em] text-slate-500">
                    {{ collect($actionItems)->sum('count') }} open
                </span>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($actionItems as $item)
                    <a href="{{ $item['href'] }}" class="group rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4 transition hover:border-cyan-200 hover:bg-cyan-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-950 group-hover:text-cyan-900">{{ $item['label'] }}</p>
                                <p class="mt-1 text-xs font-semibold leading-5 text-slate-500">{{ $item['hint'] }}</p>
                            </div>
                            <span class="inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-full border px-2 text-sm font-black {{ $toneClasses[$item['tone']] ?? $toneClasses['slate'] }}">
                                {{ $item['count'] }}
                            </span>
                        </div>
                    </a>
                @endforeach
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

        <section>
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
        </section>
    </div>
</x-layouts.admin>
