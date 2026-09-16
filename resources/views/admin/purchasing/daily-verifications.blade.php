<x-layouts.admin title="Shop Purchaser Daily Verification">
    <div class="space-y-6">
        {{-- Header & Filters --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-black text-slate-950">Shop Purchaser Daily Verification</h1>
                <p class="text-xs font-semibold text-slate-500">Audit and manage purchaser daily reconciliation, verification states, and reopening.</p>
            </div>

            <form method="GET" action="{{ route('admin.purchasing.daily-verifications.index') }}" class="flex flex-wrap items-center gap-3">
                <div>
                    <select
                        name="shop_id"
                        onchange="this.form.submit()"
                        class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs focus:border-emerald-500 focus:outline-none"
                    >
                        @foreach($shops as $s)
                            <option value="{{ $s->id }}" @selected($s->id === $selectedShopId)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <input
                        type="date"
                        name="date"
                        value="{{ $date }}"
                        onchange="this.form.submit()"
                        class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs focus:border-emerald-500 focus:outline-none"
                    >
                </div>
            </form>
        </div>

        {{-- Verification Status Table --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 bg-slate-50/50 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black text-slate-900">Purchasers for {{ $selectedShop?->name ?? 'Shop' }}</h2>
                    <p class="text-[11px] font-semibold text-slate-500">Business Date: {{ $date }}</p>
                </div>
                <span class="rounded-full bg-slate-200/80 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                    {{ $purchaserRows->count() }} Purchaser(s)
                </span>
            </div>

            @if($purchaserRows->isEmpty())
                <div class="py-12 text-center text-xs font-semibold text-slate-400">
                    No purchasing activity or verification records found for this shop on {{ $date }}.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500 bg-slate-50/30">
                                <th class="py-3 px-4">Purchaser</th>
                                <th class="py-3 px-4 text-center">Bills</th>
                                <th class="py-3 px-4 text-right">Cash</th>
                                <th class="py-3 px-4 text-right">Credit</th>
                                <th class="py-3 px-4 text-center">Pending</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Audit Trail</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @foreach($purchaserRows as $row)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-3.5 px-4 font-black text-slate-950">
                                        {{ $row['purchaser']->name }}
                                        <span class="block text-[11px] font-normal text-slate-400">{{ $row['purchaser']->email }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-900">
                                        {{ $row['total_bills'] }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-emerald-800">
                                        ₹{{ number_format((float) $row['cash_total'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-amber-800">
                                        ₹{{ number_format((float) $row['credit_total'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if($row['pending_count'] > 0)
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-black text-rose-700 border border-rose-200">
                                                {{ $row['pending_count'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-400 font-normal">0</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider {{ $row['status']->badgeClasses() }}">
                                            {{ $row['status']->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-[11px] space-y-0.5">
                                        @if($row['verified_at'])
                                            <div class="text-slate-600">
                                                <strong class="text-slate-800">User Verified:</strong> {{ $row['verified_by_name'] ?? 'User' }} ({{ $row['verified_at']->format('h:i A') }})
                                            </div>
                                        @endif
                                        @if($row['second_verified_at'])
                                            <div class="text-slate-600">
                                                <strong class="text-slate-800">2nd Verified:</strong> {{ $row['second_verified_by_name'] ?? 'Verifier' }} ({{ $row['second_verified_at']->format('h:i A') }})
                                            </div>
                                        @endif
                                        @if($row['finalized_at'])
                                            <div class="text-emerald-700 font-bold">
                                                <strong>Finalized:</strong> {{ $row['finalized_by_name'] ?? 'Admin' }} ({{ $row['finalized_at']->format('d M, h:i A') }})
                                            </div>
                                        @endif
                                        @if($row['reopened_at'])
                                            <div class="text-rose-700">
                                                <strong>Reopened:</strong> {{ $row['reopened_by_name'] ?? 'Admin' }} ({{ $row['reopened_at']->format('d M, h:i A') }})
                                                <span class="block italic text-slate-500">"{{ $row['reopen_reason'] }}"</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        @if($row['status'] === \App\Enums\Purchasing\ShopPurchaserDailyVerificationStatus::Finalized && $row['verification'])
                                            <button
                                                type="button"
                                                onclick="openReopenModal({{ $row['verification']->id }}, '{{ addslashes($row['purchaser']->name) }}', '{{ $date }}')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-black text-rose-700 hover:bg-rose-100 transition"
                                            >
                                                Reopen
                                            </button>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Reopen Modal --}}
    <div id="reopen-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-xs flex items-center justify-center">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h3 class="text-base font-black text-slate-950">Reopen Finalized Purchasing Day</h3>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                Reopening finalized purchasing for <strong id="modal-purchaser-name" class="text-slate-900"></strong> on <strong id="modal-date" class="text-slate-900"></strong>.
            </p>

            <form id="reopen-form" method="POST" action="" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Reopen Reason <span class="text-rose-600">*</span></label>
                    <textarea
                        name="reason"
                        required
                        rows="3"
                        placeholder="e.g. Rate correction required on Vendor Bill #1042..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-800 focus:bg-white focus:border-rose-500 focus:outline-none"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        onclick="closeReopenModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-black text-white hover:bg-rose-700 transition shadow-xs"
                    >
                        Confirm Reopen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReopenModal(verificationId, purchaserName, date) {
            document.getElementById('modal-purchaser-name').textContent = purchaserName;
            document.getElementById('modal-date').textContent = date;
            document.getElementById('reopen-form').action = '/admin/purchasing/daily-verifications/' + verificationId + '/reopen';
            document.getElementById('reopen-modal').classList.remove('hidden');
        }

        function closeReopenModal() {
            document.getElementById('reopen-modal').classList.add('hidden');
        }
    </script>
</x-layouts.admin>
