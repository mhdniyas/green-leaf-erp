<x-layouts.app title="Delivery Reviews">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Admin</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Delivery Reviews</h1>
                <p class="mt-1 text-sm text-slate-600">Every shop-reported delivery waits here until the final received quantities are approved.</p>
            </div>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-amber-700">
                {{ $reviews->total() }} pending
            </span>
        </div>

        @if ($reviews->isEmpty())
            <div class="px-5 py-16 text-center text-sm text-slate-500">
                No delivery reviews are waiting for approval.
            </div>
        @else
            <div class="space-y-3 p-4 md:hidden">
                @foreach ($reviews as $review)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-xs font-black text-cyan-700">{{ $review->invoice_number }}</p>
                                <h2 class="mt-1 text-base font-black text-slate-950">{{ $review->shop?->name }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ $review->business_date->format('d M Y') }}</p>
                            </div>
                            <a href="{{ route('purchasing.shop-invoices.show', $review) }}" class="text-sm font-bold text-slate-900">Open</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Invoice</th>
                            <th class="px-5 py-4">Shop</th>
                            <th class="px-5 py-4">Business Date</th>
                            <th class="px-5 py-4">Submitted By Shop</th>
                            <th class="px-5 py-4">Submitted At</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($reviews as $review)
                            <tr>
                                <td class="px-5 py-4 font-mono font-black text-cyan-700">{{ $review->invoice_number }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950">{{ $review->shop?->name }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $review->business_date->format('d M Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $review->order?->shopCheckedBy?->name ?? 'Shop Incharge' }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $review->order?->shop_checked_at?->format('d M Y h:i A') ?? '-' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('purchasing.shop-invoices.show', $review) }}" class="font-bold text-cyan-700 hover:text-cyan-900">Open Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($reviews->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $reviews->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
