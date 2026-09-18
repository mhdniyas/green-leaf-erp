@php
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $context = [
        'month' => $filters['month'] ?? today('Asia/Kolkata')->format('Y-m'),
        'period_mode' => $filters['period_mode'] ?? $filters['period'] ?? 'month',
    ];
    if ($selectedProductFilter) {
        $context['product_filter'] = $selectedProductFilter;
    }
    if (($filters['period_mode'] ?? $filters['period'] ?? '') === 'day') {
        $context['date'] = $filters['start_date'] ?? null;
    } elseif (($filters['period_mode'] ?? $filters['period'] ?? '') === 'custom') {
        $context += ['from' => $filters['start_date'] ?? null, 'to' => $filters['end_date'] ?? null];
    }
    $detailUrl = match($section) {
        'purchasers' => route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $row->purchaser_public_uuid] + $context),
        'vendors' => route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $row->supplier_public_uuid] + $context),
        'categories' => route('admin.cashbook.finance.purchase.categories.show', ['category' => $row->category_id] + $context),
        default => route('purchasing.invoices.show', $row->invoice_public_uuid),
    };
    $title = $section === 'purchasers'
        ? ($row->purchaser_name ?: 'Unassigned')
        : ($section === 'vendors'
            ? ($row->supplier_name ?: 'Unknown')
            : ($section === 'categories'
                ? ($row->category_name ?: 'Uncategorized')
                : ($row->invoice_number ?: 'Invoice')));
@endphp
@if($mobile)
    <article class="p-4 space-y-2.5 text-xs hover:bg-slate-50/60 transition">
        <div class="flex items-start justify-between gap-3">
            <a href="{{ $detailUrl }}" class="font-bold text-slate-900 hover:text-emerald-700 transition flex items-center gap-1">
                <span>{{ $title }}</span>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
            </a>
            <strong class="font-mono text-sm font-black text-slate-950 tabular-nums">
                ₹{{ number_format((float) $row->total_purchase, 2) }}
            </strong>
        </div>

        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-600 font-medium">
            @if($section === 'invoices')
                <span class="font-mono">{{ \Illuminate\Support\Carbon::parse($row->business_date)->format('d M Y') }}</span>
                <span class="text-right">
                    @if(strtolower($row->payment_class) === 'credit')
                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[9px] font-extrabold uppercase text-amber-800 border border-amber-200">Credit</span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[9px] font-extrabold uppercase text-emerald-800 border border-emerald-200">Cash</span>
                    @endif
                </span>
                <span>Vendor: <strong class="text-slate-800">{{ $row->supplier_name }}</strong></span>
                <span class="text-right">Purchaser: <strong class="text-slate-800">{{ $row->purchaser_name }}</strong></span>
                <span class="col-span-2 text-slate-500">{{ $row->categories }}</span>
            @else
                <span>Cash: <strong class="font-mono text-slate-800">₹{{ number_format((float) $row->cash_purchase, 2) }}</strong></span>
                <span class="text-right">Credit: <strong class="font-mono text-slate-800">₹{{ number_format((float) $row->credit_purchase, 2) }}</strong></span>
                <span>Invoices: <strong class="font-mono text-slate-800">{{ number_format((int) $row->invoice_count) }}</strong></span>
            @endif
        </div>

        @if($section === 'purchasers')
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60 text-[11px] space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500">Period Funding:</span>
                    <strong class="font-mono text-slate-800">₹{{ number_format((float) $row->funding, 2) }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Current Advance:</span>
                    <strong class="font-mono text-slate-800">₹{{ number_format((float) $row->balance, 2) }}</strong>
                </div>
            </div>
        @endif

        <div class="pt-1">
            <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-black text-slate-800 hover:bg-slate-50 hover:border-slate-400 transition">
                <span>View Details</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-400"></i>
            </a>
        </div>
    </article>
@elseif($section === 'purchasers')
    <tr class="hover:bg-slate-50/80 transition">
        <td class="p-3 font-bold">
            <a class="text-slate-900 hover:text-emerald-700 hover:underline transition" href="{{ $detailUrl }}">
                {{ $title }}
            </a>
        </td>
        <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">₹{{ number_format((float) $row->total_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->cash_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->credit_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono font-bold text-sky-700 tabular-nums">₹{{ number_format((float) $row->funding, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->funding_used, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-600">{{ $row->transaction_count }}</td>
        <td class="p-3 text-right font-mono font-bold text-purple-700 tabular-nums">₹{{ number_format((float) $row->balance, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-600">{{ $row->invoice_count }}</td>
        <td class="p-3 text-center">
            <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-black text-emerald-700 hover:bg-emerald-50 hover:border-emerald-300 transition">
                <span>View</span>
            </a>
        </td>
    </tr>
@elseif($section === 'vendors')
    @php
        $allTags = collect(explode(',', (string) $row->category_tags))->filter();
        $tags = $allTags->take(3);
    @endphp
    <tr class="hover:bg-slate-50/80 transition">
        <td class="p-3 font-bold">
            <a class="text-slate-900 hover:text-emerald-700 hover:underline transition" href="{{ $detailUrl }}">
                {{ $title }}
            </a>
        </td>
        <td class="p-3">
            <div class="flex flex-wrap gap-1">
                @foreach($tags as $tag)
                    <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                        {{ explode('|', $tag, 2)[1] ?? $tag }}
                    </span>
                @endforeach
                @if($allTags->count() > 3)
                    <span class="rounded-md bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold text-white">
                        +{{ $allTags->count() - 3 }}
                    </span>
                @endif
            </div>
        </td>
        <td class="p-3 text-right font-mono text-slate-700">{{ $row->invoice_count }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->cash_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->credit_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono font-bold text-rose-700 tabular-nums">₹{{ number_format((float) $row->outstanding, 2) }}</td>
        <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">₹{{ number_format((float) $row->total_purchase, 2) }}</td>
        <td class="p-3 text-center">
            <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-black text-emerald-700 hover:bg-emerald-50 hover:border-emerald-300 transition">
                <span>View</span>
            </a>
        </td>
    </tr>
@elseif($section === 'categories')
    <tr class="hover:bg-slate-50/80 transition">
        <td class="p-3 font-bold">
            <a class="text-slate-900 hover:text-emerald-700 hover:underline transition" href="{{ $detailUrl }}">
                {{ $title }}
            </a>
        </td>
        <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">₹{{ number_format((float) $row->total_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->cash_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->credit_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700">{{ $row->vendor_count }}</td>
        <td class="p-3 text-right font-mono text-slate-700">{{ $row->purchaser_count }}</td>
        <td class="p-3 text-right font-mono text-slate-700">{{ $row->invoice_count }}</td>
    </tr>
@else
    <tr class="hover:bg-slate-50/80 transition">
        <td class="p-3 font-mono font-bold text-slate-600">{{ \Illuminate\Support\Carbon::parse($row->business_date)->format('d M Y') }}</td>
        <td class="p-3 font-bold font-mono">
            <a class="text-emerald-700 hover:underline" href="{{ $detailUrl }}">{{ $title }}</a>
        </td>
        <td class="p-3 font-medium text-slate-800">{{ $row->supplier_name }}</td>
        <td class="p-3 font-medium text-slate-800">{{ $row->purchaser_name }}</td>
        <td class="p-3 text-slate-600">{{ $row->categories }}</td>
        <td class="p-3 text-center">
            @if(strtolower($row->payment_class) === 'credit')
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-amber-800 border border-amber-200">
                    Credit
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-emerald-800 border border-emerald-200">
                    Cash
                </span>
            @endif
        </td>
        <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">₹{{ number_format((float) $row->total_purchase, 2) }}</td>
        <td class="p-3 text-right font-mono text-slate-700 tabular-nums">₹{{ number_format((float) $row->paid_amount, 2) }}</td>
        <td class="p-3 text-right font-mono font-bold text-rose-700 tabular-nums">₹{{ number_format((float) $row->outstanding, 2) }}</td>
        <td class="p-3 text-center">
            <a href="{{ $detailUrl }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-black text-emerald-700 hover:bg-emerald-50 hover:border-emerald-300 transition">
                <span>View</span>
            </a>
        </td>
    </tr>
@endif
