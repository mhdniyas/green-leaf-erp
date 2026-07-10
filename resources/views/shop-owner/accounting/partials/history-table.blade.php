@if ($entries->count() > 0)
    <div class="space-y-4">
        @foreach ($entries as $entry)
            @php
                $income = (float) $entry->lines->where('type', 'income')->sum('amount');
                $expense = (float) $entry->lines->where('type', 'expense')->sum('amount');
            @endphp
            <article class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-black text-slate-950">{{ $entry->business_date->format('d M Y') }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            @include('shop-owner.components.status-badge', ['label' => $entry->statusLabel(), 'tone' => $entry->statusTone()])
                            <span class="text-sm font-semibold text-slate-600">Income Rs. {{ number_format($income, 2) }}</span>
                            <span class="text-sm font-semibold text-slate-600">Expense Rs. {{ number_format($expense, 2) }}</span>
                            <span class="text-sm font-semibold text-slate-600">Net Rs. {{ number_format($income - $expense, 2) }}</span>
                        </div>
                    </div>
                    <a href="{{ route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => $entry->business_date->format('Y-m-d')]) }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">
                        Open & Update
                    </a>
                </div>

                @if ($entry->admin_note || $entry->shop_reply_note)
                    <div class="mt-4 grid gap-3 border-t border-slate-200 pt-4 md:grid-cols-2">
                        @if ($entry->admin_note)
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-red-700">Admin Note</p>
                                <p class="mt-2 text-sm font-semibold leading-6 text-slate-700">{{ $entry->admin_note }}</p>
                            </div>
                        @endif
                        @if ($entry->shop_reply_note)
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Your Reply</p>
                                <p class="mt-2 text-sm font-semibold leading-6 text-slate-700">{{ $entry->shop_reply_note }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    <div class="mt-5">{{ $entries->links() }}</div>
@else
    @include('shop-owner.components.empty-state', ['title' => 'No accounting history', 'description' => 'Submitted daily accounting entries will appear here with approval status and notes.'])
@endif
