@if ($deliveries->isNotEmpty())
    {{-- Mobile View: Strict Single-Row Cards --}}
    <div class="space-y-1.5 md:hidden">
        @foreach ($deliveries as $order)
            @php
                $invoiceTotal = $order->invoice ? (float) $order->invoice->final_total : null;
                $tone = $order->warehouseWorkflowTone();
                $badgeClass = match($tone) {
                    'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'danger' => 'bg-red-50 text-red-700 border-red-200',
                    'info' => 'bg-blue-50 text-blue-700 border-blue-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                };
            @endphp
            <article class="flex items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-white p-2 shadow-xs transition hover:border-slate-300">
                {{-- Date & Order Number --}}
                <div class="min-w-0 flex-1 flex items-center gap-1">
                    <span class="text-[11px] font-black text-slate-900 whitespace-nowrap">{{ $order->business_date->format('d M') }}</span>
                    <span class="truncate font-mono text-[9px] font-semibold text-slate-400 max-w-[65px] sm:max-w-none">{{ $order->order_number }}</span>
                </div>

                {{-- Status Badge --}}
                <div class="shrink-0">
                    <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider whitespace-nowrap {{ $badgeClass }}">
                        {{ $order->warehouseWorkflowLabel() }}
                    </span>
                </div>

                {{-- Invoice Total & Action --}}
                <div class="shrink-0 flex items-center gap-1.5 text-right">
                    @if ($invoiceTotal !== null)
                        <span class="text-xs font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format($invoiceTotal, 2) }}</span>
                    @else
                        <span class="text-[10px] font-semibold text-slate-400 italic">Pending</span>
                    @endif
                    <a href="{{ route('shop-owner.deliveries.show', $order->order_number) }}"
                       class="inline-flex items-center justify-center rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 transition-colors">
                        Open
                    </a>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                    <th class="py-2.5 pr-3">Date</th>
                    <th class="py-2.5 pr-3">Order Ref</th>
                    <th class="py-2.5 pr-3">Status</th>
                    <th class="py-2.5 pr-3">Warehouse</th>
                    <th class="py-2.5 pr-3 text-right">Invoice Total</th>
                    <th class="py-2.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                @foreach ($deliveries as $order)
                    @php
                        $invoiceTotal = $order->invoice ? (float) $order->invoice->final_total : null;
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-2.5 pr-3 font-bold text-slate-900">{{ $order->business_date->format('d M Y') }}</td>
                        <td class="py-2.5 pr-3 font-mono text-xs font-bold text-slate-600">{{ $order->order_number }}</td>
                        <td class="py-2.5 pr-3">@include('shop-owner.deliveries.partials.delivery-status-badge', ['order' => $order])</td>
                        <td class="py-2.5 pr-3 font-bold text-slate-700">{{ $order->warehouseWorkflowLabel() }}</td>
                        <td class="py-2.5 pr-3 text-right font-black text-slate-950">
                            @if ($invoiceTotal !== null)
                                Rs. {{ number_format($invoiceTotal, 2) }}
                            @else
                                <span class="text-xs font-normal text-slate-400 italic">Pending Invoice</span>
                            @endif
                        </td>
                        <td class="py-2.5 text-right">
                            <a href="{{ route('shop-owner.deliveries.show', $order->order_number) }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-900">
                                Open &rarr;
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination Links --}}
    @if ($deliveries instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-3 border-t border-slate-100 pt-2">
            {{ $deliveries->links() }}
        </div>
    @endif
@else
    @include('shop-owner.components.empty-state', ['title' => 'No deliveries yet', 'description' => 'Priced invoices, allocated orders, and delivered orders will appear here.'])
@endif
