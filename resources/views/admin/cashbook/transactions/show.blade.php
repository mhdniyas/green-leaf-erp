@extends('admin.cashbook.layouts.app')

@section('title', strtoupper($presented['payment_method']).' Collection — ₹'.number_format($presented['amount'], 2))

@section('content')
<div class="mx-auto max-w-4xl space-y-6 pb-16">

    <!-- Flash Messages / Toast Alerts -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
        </div>
    @endif

    @if(session('info'))
        <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 text-sky-900 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-sky-600"></i>
                <span>{{ session('info') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-sky-500 hover:text-sky-700 font-bold">&times;</button>
        </div>
    @endif

    <!-- Back Navigation Bar -->
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2 text-xs font-bold">
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $presented['raw_business_date']]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300 shadow-xs transition">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Money Flow</span>
            </a>
            <a href="{{ route('admin.cashbook.shop.show', ['shop' => $presented['shop_slug'], 'date' => $presented['raw_business_date']]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300 shadow-xs transition">
                <i data-lucide="store" class="w-3.5 h-3.5"></i>
                <span>{{ $presented['shop_name'] }} Shop Day</span>
            </a>
        </div>

        <span class="text-xs font-mono font-bold text-slate-400">
            TX-{{ $presented['id'] }}
        </span>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">

        <!-- Top Header & Amount Banner -->
        <div class="p-6 sm:p-8 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                        {{ $presented['payment_method'] }} Collection
                    </span>
                    <h1 class="text-3xl sm:text-4xl font-black font-mono text-slate-900 tracking-tight mt-1">
                        ₹{{ number_format($presented['amount'], 2) }}
                    </h1>
                    <p class="text-xs font-bold text-slate-500 mt-1">
                        <span class="text-slate-900 font-extrabold">{{ $presented['shop_name'] }}</span>
                        &bull; {{ $presented['business_date'] }}
                    </p>
                </div>

                <!-- Primary Status Badge -->
                <div>
                    @if($presented['display_status'] === 'RECEIVED')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-extrabold shadow-xs">
                            <i data-lucide="badge-check" class="w-4 h-4 text-emerald-600"></i>
                            RECEIVED
                        </span>
                    @elseif($presented['display_status'] === 'NEEDS VERIFICATION')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-extrabold shadow-xs">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                            NEEDS VERIFICATION
                        </span>
                    @elseif($presented['display_status'] === 'CASH WITH SHOP')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-50 text-sky-800 border border-sky-200 text-xs font-extrabold shadow-xs">
                            <i data-lucide="store" class="w-4 h-4 text-sky-600"></i>
                            CASH WITH SHOP
                        </span>
                    @elseif($presented['display_status'] === 'POSTED')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 text-xs font-extrabold shadow-xs">
                            <i data-lucide="clock" class="w-4 h-4 text-slate-500"></i>
                            POSTED
                        </span>
                    @elseif($presented['display_status'] === 'VOID')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 text-rose-800 border border-rose-200 text-xs font-extrabold shadow-xs">
                            <i data-lucide="x-circle" class="w-4 h-4 text-rose-600"></i>
                            VOID
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 text-xs font-extrabold shadow-xs">
                            {{ $presented['display_status'] }}
                        </span>
                    @endif
                </div>
            </div>

            @if(!empty($presented['attention_reason']))
                <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-bold flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 flex-shrink-0"></i>
                    <span>{{ $presented['attention_reason'] }}</span>
                </div>
            @endif
        </div>

        <!-- 2-Column: Money Flow Progression & Current Location -->
        <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- Left: MONEY FLOW PROGRESSION -->
            <div class="space-y-4">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">
                    MONEY FLOW
                </h2>

                <div class="space-y-3 pl-2">
                    @foreach($presented['flow_steps'] as $index => $step)
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-black {{ $step['state'] === 'completed' ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : ($step['state'] === 'current' ? 'bg-amber-100 text-amber-800 border border-amber-300 ring-2 ring-amber-200' : 'bg-slate-100 text-slate-400 border border-slate-200') }}">
                                @if($step['state'] === 'completed')
                                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                @elseif($step['state'] === 'current')
                                    &bull;
                                @else
                                    <span class="text-[9px]">{{ $index + 1 }}</span>
                                @endif
                            </div>
                            <span class="text-xs font-bold {{ $step['state'] === 'completed' ? 'text-slate-900 font-extrabold' : ($step['state'] === 'current' ? 'text-amber-900 font-black' : 'text-slate-400') }}">
                                {{ $step['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Right: CURRENT LOCATION -->
            <div class="space-y-4">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">
                    CURRENT LOCATION
                </h2>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Location / Destination</span>
                        <div class="text-sm font-extrabold text-slate-900 mt-0.5">
                            {{ $presented['destination_formatted'] }}
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-2 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500">Company verified receipt:</span>
                        <span class="text-xs font-extrabold {{ $presented['company_verified_receipt'] === 'Yes' ? 'text-emerald-700' : 'text-slate-600' }}">
                            {{ $presented['company_verified_receipt'] }}
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- EXACTLY ONE NEXT ACTION SECTION -->
        <div class="p-6 sm:p-8 bg-slate-50/70 space-y-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">
                NEXT ACTION
            </h2>

            @if($presented['stage'] === 'posted')
                <!-- ACTION: APPROVE -->
                <div class="space-y-3">
                    <p class="text-xs font-bold text-slate-600">
                        This retail collection has been recorded by shop staff and is awaiting admin approval.
                    </p>
                    <form method="POST" action="{{ $presented['next_action_url'] }}">
                        @csrf
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            <span>APPROVE</span>
                        </button>
                    </form>
                </div>

            @elseif($presented['stage'] === 'approved_online')
                <!-- ACTION: VERIFY RECEIVED (ONLINE) -->
                <div x-data="{ openConfirm: false }" class="space-y-3">
                    <p class="text-xs font-bold text-slate-600">
                        Confirm that this payment of <span class="text-slate-900 font-extrabold">₹{{ number_format($presented['amount'], 2) }}</span> was received in the configured company account (<span class="font-extrabold text-slate-900">{{ $presented['destination_account_name'] }}</span>).
                    </p>

                    <button type="button" @click="openConfirm = true"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                        <i data-lucide="badge-check" class="w-4 h-4"></i>
                        <span>VERIFY RECEIVED</span>
                    </button>

                    <!-- Simple Confirmation Modal -->
                    <div x-show="openConfirm" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
                        <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                             @click.away="openConfirm = false">
                            <h3 class="text-base font-black text-slate-900">Confirm Company Received?</h3>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-1 text-xs">
                                <div class="font-extrabold text-slate-900">{{ $presented['shop_name'] }} &bull; {{ $presented['payment_method'] }}</div>
                                <div class="text-lg font-black font-mono text-emerald-800">₹{{ number_format($presented['amount'], 2) }}</div>
                                <div class="text-slate-600 font-bold">{{ $presented['destination_formatted'] }}</div>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="button" @click="openConfirm = false"
                                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer">
                                    Cancel
                                </button>
                                <form method="POST" action="{{ $presented['next_action_url'] }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black shadow-xs transition cursor-pointer">
                                        Confirm Received
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($presented['stage'] === 'approved_cash')
                <!-- ACTION: VERIFY CASH RECEIVED -->
                <div x-data="{ openCashConfirm: false }" class="space-y-3">
                    <p class="text-xs font-bold text-slate-600">
                        Confirm physical cash handover of <span class="text-slate-900 font-extrabold">₹{{ number_format($presented['amount'], 2) }}</span> from <span class="font-extrabold text-slate-900">{{ $presented['shop_name'] }} Shop</span> into the company vault.
                    </p>

                    <button type="button" @click="openCashConfirm = true"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-sky-700 hover:bg-sky-800 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                        <i data-lucide="hand-coins" class="w-4 h-4"></i>
                        <span>VERIFY CASH RECEIVED</span>
                    </button>

                    <!-- Simple Confirmation Modal -->
                    <div x-show="openCashConfirm" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
                        <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                             @click.away="openCashConfirm = false">
                            <h3 class="text-base font-black text-slate-900">Confirm Physical Cash Received?</h3>
                            <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 space-y-1 text-xs">
                                <div class="font-extrabold text-slate-900">{{ $presented['shop_name'] }} Shop Cash</div>
                                <div class="text-lg font-black font-mono text-sky-900">₹{{ number_format($presented['amount'], 2) }}</div>
                                <div class="text-slate-600 font-bold mt-2">From: {{ $presented['shop_name'] }} Shop</div>
                                <div class="text-slate-600 font-bold">To: Company Cash Box</div>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="button" @click="openCashConfirm = false"
                                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer">
                                    Cancel
                                </button>
                                <form method="POST" action="{{ $presented['next_action_url'] }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-4 py-2 rounded-xl bg-sky-700 hover:bg-sky-800 text-white text-xs font-black shadow-xs transition cursor-pointer">
                                        Confirm Cash Received
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($presented['stage'] === 'exception')
                <!-- ACTION: RESOLVE ISSUE -->
                <div class="space-y-3">
                    <p class="text-xs font-bold text-amber-800">
                        This transaction encountered an exception or amount mismatch requiring resolution in the reconciliation workspace.
                    </p>
                    <a href="{{ $presented['next_action_url'] }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black shadow-sm transition-all cursor-pointer">
                        <i data-lucide="wrench" class="w-4 h-4"></i>
                        <span>RESOLVE ISSUE</span>
                    </a>
                </div>

            @elseif($presented['stage'] === 'verified')
                <!-- COMPLETED / NO ACTION -->
                <div class="flex items-center gap-3 text-xs font-bold text-emerald-800 bg-emerald-50 p-4 rounded-2xl border border-emerald-200">
                    <i data-lucide="badge-check" class="w-5 h-5 text-emerald-600 flex-shrink-0"></i>
                    <div>
                        <span class="font-extrabold text-sm text-emerald-950 block">✓ Completed</span>
                        <span>Verified and confirmed received into {{ $presented['destination_account_name'] }}. No further action needed.</span>
                    </div>
                </div>

            @else
                <!-- VOID OR SUPERSEDED -->
                <div class="text-xs font-bold text-slate-500 bg-slate-100 p-4 rounded-2xl border border-slate-200">
                    This transaction is inactive. No action required.
                </div>
            @endif
        </div>

        <!-- DETAILS SECTION -->
        <div class="p-6 sm:p-8 space-y-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">
                DETAILS
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-400 font-bold block">Payment Method</span>
                    <span class="font-black text-slate-900">{{ $presented['payment_method'] }}</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-400 font-bold block">Business Date</span>
                    <span class="font-black text-slate-900 font-mono">{{ $presented['business_date'] }}</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-400 font-bold block">Destination Account</span>
                    <span class="font-black text-slate-900">{{ $presented['destination_account_name'] }}</span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <span class="text-slate-400 font-bold block">Statement Reference</span>
                    <span class="font-black text-slate-900 font-mono">{{ $presented['audit']['statement_ref'] ?? 'Pending generation' }}</span>
                </div>
            </div>
        </div>

        <!-- AUDIT TRAIL -->
        <div class="p-6 sm:p-8 space-y-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">
                AUDIT
            </h2>

            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <div>
                        <span class="font-extrabold text-slate-900">Recorded</span>
                        <p class="text-slate-500 font-medium">{{ $presented['audit']['entered_at'] ?? '—' }}</p>
                    </div>
                    <span class="font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">
                        By {{ $presented['audit']['entered_by'] }}
                    </span>
                </div>

                @if(!empty($presented['audit']['approved_at']))
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <div>
                            <span class="font-extrabold text-slate-900">Approved</span>
                            <p class="text-slate-500 font-medium">{{ $presented['audit']['approved_at'] }}</p>
                        </div>
                        <span class="font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">
                            By {{ $presented['audit']['approved_by'] }}
                        </span>
                    </div>
                @endif

                @if(!empty($presented['audit']['verified_at']))
                    <div class="flex items-center justify-between py-2">
                        <div>
                            <span class="font-extrabold text-emerald-900">Verified</span>
                            <p class="text-emerald-700 font-medium">{{ $presented['audit']['verified_at'] }}</p>
                        </div>
                        <span class="font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">
                            By {{ $presented['audit']['verified_by'] }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

    </div>

</div>
@endsection
