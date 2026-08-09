<div class="grid grid-cols-3 gap-2">
    {{-- Row 1, Card 1: Pending Delivery --}}
    <div class="rounded-xl bg-slate-950 p-2.5 text-white shadow-xs">
        <p class="truncate text-[8px] font-black uppercase tracking-wider text-emerald-400 sm:text-[10px]">Pending Delivery</p>
        <p class="mt-1 truncate text-xs font-black sm:text-lg">{{ $stats['pending_delivery_count'] }} Orders</p>
    </div>

    {{-- Row 1, Card 2: Today Bill --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Today Bill</p>
        <p class="mt-1 truncate text-xs font-black text-slate-900 sm:text-lg">Rs. {{ number_format((float) $stats['today_bill_total'], 2) }}</p>
    </div>

    {{-- Row 1, Card 3: Unpaid Bills --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">{{ $isOwnedAccountingShop ? 'Pending' : 'Unpaid' }}</p>
        <p class="mt-1 truncate text-xs font-black text-amber-700 sm:text-lg">Rs. {{ number_format((float) $stats['pending_bill_approval_amount'], 2) }}</p>
    </div>

    {{-- Row 2, Card 4: Delivered Count --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Delivered</p>
        <p class="mt-1 truncate text-xs font-black text-emerald-700 sm:text-lg">{{ $stats['delivered_orders_count'] }} Orders</p>
    </div>

    {{-- Row 2, Card 5: Pending Review --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Pending Review</p>
        <p class="mt-1 truncate text-xs font-black text-amber-600 sm:text-lg">{{ $stats['pending_approval_count'] }} Orders</p>
    </div>

    {{-- Row 2, Card 6: Outstanding / Closing --}}
    <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
        <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">{{ $isOwnedAccountingShop ? 'Closing' : 'Outstanding' }}</p>
        <p class="mt-1 truncate text-xs font-black {{ $isOwnedAccountingShop ? 'text-emerald-700' : 'text-red-600' }} sm:text-lg">
            Rs. {{ number_format((float) ($isOwnedAccountingShop ? $stats['today_closing_balance'] : $stats['outstanding_balance']), 2) }}
        </p>
    </div>
</div>
