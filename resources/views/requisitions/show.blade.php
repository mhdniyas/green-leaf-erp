<x-layouts.app title="Requisition Details — {{ $order->order_number }}">
    @php
        $canApprove = auth()->user()->hasRole('purchase') || auth()->user()->can('purchasing.order.approve');
        $isPendingApproval = in_array($order->state, ['submitted', 'update_requested']);
        $showApprovalForm = $canApprove && $isPendingApproval;
    @endphp

    @if($showApprovalForm)
        <form id="review-form" method="POST" action="{{ route('requisitions.review', $order->order_number) }}">
            @csrf
        </form>
    @endif

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Breadcrumbs & Status Alert -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Back to Control Center
                </a>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    Requisition ID: <span class="text-emerald-600">{{ $order->order_number }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">Target Delivery: <span class="font-bold text-slate-700">{{ \Carbon\Carbon::parse($order->business_date)->format('d F Y') }}</span></p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- CSV Export -->
                <a href="{{ route('requisitions.export.csv', $order->order_number) }}" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-2.5 rounded-xl transition-all cursor-pointer focus:outline-none border border-slate-200">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Export CSV
                </a>

                <!-- PDF Share -->
                <a href="{{ route('requisitions.export.pdf', $order->order_number) }}" target="_blank" class="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold px-4 py-2.5 rounded-xl transition-all cursor-pointer focus:outline-none border border-emerald-100">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4" /></svg>
                    Share PDF
                </a>

                <!-- Edit (Only before cutoff) -->
                @if($order->canEditDirectly())
                    <a href="{{ route('requisitions.edit', $order->order_number) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all cursor-pointer focus:outline-none shadow-sm hover:shadow">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-2.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        Edit Requisition
                    </a>
                @endif
            </div>
        </div>

        <!-- Success/Error Banners -->
        @if(session('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Columns: Order Details Table -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Item Details</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                    <th class="py-3 px-6">Product</th>
                                    @if($order->is_delivered)
                                        <th class="py-3 px-6 text-right">Approved Qty</th>
                                        <th class="py-3 px-6 text-right">Delivered Qty</th>
                                        <th class="py-3 px-6 text-right">Shortage Qty</th>
                                        <th class="py-3 px-6 text-right">Unit Cost</th>
                                        <th class="py-3 px-6 text-right">Shortage Value</th>
                                    @else
                                        <th class="py-3 px-6 text-center">Fulfillment</th>
                                        <th class="py-3 px-6 text-right">Requested Qty</th>
                                        <th class="py-3 px-6 text-right">Approved Qty</th>
                                        <th class="py-3 px-6 text-center">Status</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse($order->items as $item)
                                    <tr class="hover:bg-slate-50/20">
                                        <td class="py-4 px-6 font-semibold text-slate-900">
                                            {{ $item->product->name }}
                                            <span class="block text-[10px] text-slate-400 font-normal mt-0.5">{{ $item->product->sku }}</span>
                                        </td>
                                        @if($order->is_delivered)
                                            <td class="py-4 px-6 text-right font-semibold text-slate-700">
                                                {{ number_format((float) ($item->approved_qty ?? 0.00), 2) }} {{ $item->unit }}
                                            </td>
                                            <td class="py-4 px-6 text-right font-black text-slate-900">
                                                {{ number_format((float) ($item->delivered_qty ?? 0.00), 2) }} {{ $item->unit }}
                                            </td>
                                            <td class="py-4 px-6 text-right font-semibold {{ (float) $item->shortage_qty > 0.01 ? 'text-red-600 font-bold' : 'text-slate-400' }}">
                                                {{ number_format((float) ($item->shortage_qty ?? 0.00), 2) }} {{ $item->unit }}
                                            </td>
                                            <td class="py-4 px-6 text-right text-slate-500 font-medium">
                                                Rs. {{ number_format((float) ($item->unit_cost ?? 0.00), 4) }}/{{ $item->unit }}
                                            </td>
                                            <td class="py-4 px-6 text-right font-black {{ (float) $item->shortage_qty > 0.01 ? 'text-red-600' : 'text-slate-400' }}">
                                                Rs. {{ number_format((float) ($item->shortage_value ?? 0.00), 2) }}
                                            </td>
                                        @else
                                            <td class="py-4 px-6 text-center">
                                                @if($showApprovalForm)
                                                    <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-200">
                                                        <label class="cursor-pointer">
                                                            <input type="radio" name="fulfillment_types[{{ $item->id }}]" value="warehouse" @checked(($item->fulfillment_type ?? 'warehouse') === 'warehouse') form="review-form" class="sr-only peer">
                                                            <span class="inline-block px-3 py-1 rounded-md text-[10px] font-bold text-slate-500 peer-checked:bg-white peer-checked:text-slate-800 peer-checked:shadow-sm transition-all select-none">
                                                                Warehouse
                                                            </span>
                                                        </label>
                                                        <label class="cursor-pointer">
                                                            <input type="radio" name="fulfillment_types[{{ $item->id }}]" value="selection" @checked(($item->fulfillment_type ?? 'warehouse') === 'selection') form="review-form" class="sr-only peer">
                                                            <span class="inline-block px-3 py-1 rounded-md text-[10px] font-bold text-slate-500 peer-checked:bg-white peer-checked:text-slate-800 peer-checked:shadow-sm transition-all select-none">
                                                                Selection
                                                            </span>
                                                        </label>
                                                    </div>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border {{ ($item->fulfillment_type ?? 'warehouse') === 'selection' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-slate-50 text-slate-700 border-slate-200' }}">
                                                        {{ ($item->fulfillment_type ?? 'warehouse') === 'selection' ? 'Selection (Packet)' : 'Warehouse (Bulk)' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-4 px-6 text-right font-semibold text-slate-800">
                                                {{ $item->requested_qty }} {{ $item->unit }}
                                            </td>
                                            <td class="py-4 px-6 text-right font-black text-slate-900">
                                                @if($showApprovalForm)
                                                    <div class="flex items-center justify-end gap-2">
                                                        <input type="number" 
                                                               step="0.01" 
                                                               min="0" 
                                                               name="approved_qty[{{ $item->id }}]" 
                                                               value="{{ $item->approved_qty !== null ? $item->approved_qty : $item->requested_qty }}" 
                                                               form="review-form"
                                                               class="w-24 rounded-lg border border-slate-200 px-2.5 py-1 text-slate-900 text-center font-black focus:border-emerald-500 focus:outline-none transition-all">
                                                        <span class="text-slate-500 font-semibold">{{ $item->unit }}</span>
                                                    </div>
                                                @elseif($item->approved_qty !== null)
                                                    {{ $item->approved_qty }} {{ $item->unit }}
                                                @else
                                                    <span class="text-slate-400 font-normal italic">Pending</span>
                                                @endif
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                @if($order->state === 'approved')
                                                    @if($item->approved_qty === $item->requested_qty)
                                                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-emerald-100">
                                                            <svg class="w-3.5 h-3.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            Approved
                                                        </span>
                                                    @elseif($item->approved_qty > 0)
                                                        <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100">
                                                            <svg class="w-3.5 h-3.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                                            Partial
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-red-100">
                                                            <svg class="w-3.5 h-3.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            Rejected
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-slate-400 italic">Pending Review</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $order->is_delivered ? 6 : 5 }}" class="py-8 text-center text-slate-400 font-medium italic bg-slate-50/10">No items found in this requisition.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Status Summary & Update Requests -->
            <div class="space-y-6">
                @if($showApprovalForm)
                    <!-- Review Actions Card -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100">Review Actions</h2>
                        
                        @if($order->state === 'update_requested')
                            <div class="bg-indigo-50 border border-indigo-100 text-indigo-800 text-xs rounded-2xl p-4 leading-normal">
                                <span class="font-bold block mb-1">Update Request Justification:</span>
                                <p class="font-medium italic text-indigo-900 bg-white/50 p-2 rounded-xl">"{{ $order->update_reason }}"</p>
                            </div>
                        @endif

                        <div class="text-xs text-slate-500 leading-normal">
                            Adjust the approved quantities in the table on the left if necessary, then choose an action below.
                        </div>

                        <div class="flex flex-col gap-2.5 pt-2">
                            <button type="submit" form="review-form" name="action" value="approve" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-3 rounded-xl shadow-sm hover:shadow transition-all cursor-pointer focus:outline-none flex items-center justify-center gap-1.5 border-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                Approve Requisition
                            </button>
                            <button type="submit" form="review-form" name="action" value="reject" class="w-full bg-red-50 hover:bg-red-100 text-red-700 text-xs font-bold py-3 rounded-xl border border-red-200 transition-all cursor-pointer focus:outline-none flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                Reject Requisition
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Status & General Info Card -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100">Requisition Status</h2>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-bold">Status Badge</span>
                        <div>
                            @if($order->is_delivered)
                                <span class="inline-flex items-center gap-1.5 bg-teal-50 text-teal-700 px-3 py-1 rounded-full text-xs font-black border border-teal-100">
                                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296a3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0110 21.036 3.745 3.745 0 016.704 19.4a3.745 3.745 0 01-1.043-3.296a3.745 3.745 0 01-3.296-1.043A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296a3.745 3.745 0 013.296-1.043A3.745 3.745 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043a3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                                    Delivered
                                </span>
                            @elseif($order->is_allocation_completed)
                                <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-black border border-indigo-100">
                                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.024a1.794 1.794 0 00-3.587 0v.024m-4.5 0h9.75M8.25 7.5H21m-6 3H9" /></svg>
                                    Loaded & Shipped
                                </span>
                            @elseif($order->state === 'submitted')
                                <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-black border border-amber-100">
                                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Submitted
                                </span>
                            @elseif($order->state === 'approved')
                                <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full text-xs font-black border border-emerald-100">
                                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Approved
                                </span>
                            @elseif($order->state === 'update_requested')
                                <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full text-xs font-black border border-indigo-100">
                                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Update Requested
                                </span>
                            @elseif($order->state === 'rejected')
                                <span class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 px-3 py-1 rounded-full text-xs font-black border border-red-100">
                                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Rejected
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 px-3 py-1 rounded-full text-xs font-black border border-slate-200">
                                    Draft
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-1">
                        <span class="text-slate-400 font-bold">Requesting Shop</span>
                        <span class="font-semibold text-slate-800">{{ $order->shop ? $order->shop->name : 'CASIO HYPERMARKET' }}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-bold">Submitted At</span>
                        <span class="font-semibold text-slate-800">{{ $order->submitted_at ? $order->submitted_at->format('d M Y, h:i A') : 'N/A' }}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-bold">Created By</span>
                        <span class="font-semibold text-slate-800">{{ $order->creator ? $order->creator->name : 'Shop Owner' }}</span>
                    </div>

                    @if($order->is_delivered)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-bold">Delivery Status</span>
                            <span class="font-semibold text-slate-800">{{ str($order->delivery_status ?? 'delivered')->replace('_', ' ')->title() }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-bold">Payment Status</span>
                            <span class="font-semibold text-slate-800">{{ str($order->payment_status ?? 'unpaid')->replace('_', ' ')->title() }}</span>
                        </div>
                    @endif
                </div>

                <!-- Delivery & Check-in Details / Actions -->
                @if($order->is_delivered)
                    <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6 shadow-sm space-y-4">
                        <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-200">Delivery Receipt</h2>
                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-bold">Checked-in By</span>
                                <span class="font-semibold text-slate-800">{{ $order->deliveredBy ? $order->deliveredBy->name : 'Shop Owner' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-bold">Received At</span>
                                <span class="font-semibold text-slate-800">{{ $order->delivered_at ? $order->delivered_at->format('d M Y, h:i A') : 'N/A' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-red-600 border-t border-dashed border-slate-200 pt-3">
                                <span class="font-bold">Total Shortage Value</span>
                                <span class="font-black">Rs. {{ number_format((float) $order->total_shortage_value, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-bold">Amount Paid</span>
                                <span class="font-semibold text-slate-800">Rs. {{ number_format((float) $order->cash_collected, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-bold">Balance Amount</span>
                                <span class="font-semibold text-slate-800">Rs. {{ number_format((float) $order->balance_amount, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-bold">Payment Status</span>
                                <span class="font-semibold text-slate-800">{{ str($order->payment_status ?? 'unpaid')->replace('_', ' ')->title() }}</span>
                            </div>
                            
                            @php
                                $disc = (float) $order->cash_discrepancy;
                                if (abs($disc) < 0.01) {
                                    $discClasses = 'bg-emerald-50 text-emerald-800 border-emerald-100';
                                } elseif ($disc > 0) {
                                    $discClasses = 'bg-red-50 text-red-800 border-red-100';
                                } else {
                                    $discClasses = 'bg-blue-50 text-blue-800 border-blue-100';
                                }
                            @endphp
                            <div class="flex items-center justify-between p-2.5 rounded-xl border {{ $discClasses }}">
                                <span class="font-bold">Cash Discrepancy</span>
                                <span class="font-black">
                                    @if(abs($disc) < 0.01)
                                        Rs. 0.00 (Balanced)
                                    @elseif($disc > 0)
                                        Rs. {{ number_format($disc, 2) }} (Shortage)
                                    @else
                                        Rs. {{ number_format(abs($disc), 2) }} (Surplus)
                                    @endif
                                </span>
                            </div>
                            
                            @if($order->delivery_notes)
                                <div class="border-t border-dashed border-slate-200 pt-3">
                                    <span class="text-slate-400 font-bold block mb-1">Notes:</span>
                                    <p class="italic text-slate-600 bg-white p-2 rounded-lg border border-slate-100">{{ $order->delivery_notes }}</p>
                                </div>
                            @endif

                            @if($order->finance_note)
                                <div class="border-t border-dashed border-slate-200 pt-3">
                                    <span class="text-slate-400 font-bold block mb-1">Finance Note:</span>
                                    <p class="italic text-slate-600 bg-white p-2 rounded-lg border border-slate-100">{{ $order->finance_note }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($order->is_allocation_completed)
                    <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-6 shadow-sm space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.024a1.794 1.794 0 00-3.587 0v.024m-4.5 0h9.75M8.25 7.5H21m-6 3H9" /></svg>
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-emerald-800 uppercase tracking-wider mb-1">Verify Delivery</h3>
                                <p class="text-xs text-emerald-700 leading-normal">
                                    Warehouse allocation has completed. Please verify physical receipt & check-in.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('requisitions.delivery.show', $order->order_number) }}" class="w-full inline-flex bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-3 rounded-xl shadow-sm hover:shadow transition-all cursor-pointer focus:outline-none items-center justify-center gap-1.5">
                            Verify & Check-in Delivery
                        </a>
                    </div>
                @else
                    <!-- Update Request Form (After Cutoff Lock) -->
                    @if(!$order->canEditDirectly())
                        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 mb-4">Request Requisition Update</h2>
                            
                            @if($order->state === 'update_requested')
                                <div class="bg-indigo-50 border border-indigo-100 text-indigo-800 text-xs rounded-2xl p-4 leading-normal">
                                    <span class="font-bold block mb-1">Status: Pending Review</span>
                                    You requested an update with the following message:
                                    <p class="mt-2 font-medium italic text-indigo-900 bg-white/50 p-2 rounded-xl">"{{ $order->update_reason }}"</p>
                                    <p class="mt-2 text-[10px] text-indigo-500">The Purchase Manager has been notified and will review your request.</p>
                                </div>
                            @else
                                <div class="mb-4 text-xs text-slate-500 leading-normal">
                                    The 9:30 PM cutoff deadline has passed. To make changes, you must submit an update request with a justification.
                                </div>

                                <form action="{{ route('requisitions.update-request', $order->order_number) }}" method="POST" class="space-y-4">
                                    @csrf
                                    <div>
                                        <label for="reason" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Reason for Update</label>
                                        <textarea id="reason" name="reason" rows="3" required class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-slate-300 resize-none" placeholder="Explain what needs to be changed (e.g. Add 5 kg Tomato H)..."></textarea>
                                    </div>

                                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold py-2.5 rounded-xl shadow-sm transition-all cursor-pointer focus:outline-none">
                                        Submit Request
                                    </button>
                                </form>
                            @endif
                        </div>
                    @else
                        <!-- Cutoff Countdown Alert Card (Before Cutoff) -->
                        <div class="bg-emerald-50 border border-emerald-100 rounded-3xl p-6 flex items-start gap-4">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-100/50 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-emerald-800 uppercase tracking-wider mb-1">Requisition Window Open</h3>
                                <p class="text-xs text-emerald-700 leading-normal">
                                    You can edit this requisition directly until the **9:30 PM** cutoff time tonight.
                                </p>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
