<x-layouts.app title="Purchaser Dashboard">
    <div class="mx-auto max-w-3xl px-2 py-3 sm:px-4">
        <div class="space-y-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Purchaser Dashboard</p>
                        <h1 class="mt-1 text-xl font-black text-slate-950">Daily Order</h1>
                        <p class="mt-1 text-xs font-medium text-slate-500">Mobile-friendly buying flow for market purchase.</p>
                    </div>
                    <form action="{{ route('purchaser.dashboard') }}" method="GET">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-slate-50 px-2 py-2 text-xs font-bold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    </form>
                </div>

                <div class="mt-3 grid grid-cols-4 gap-2">
                    <div class="rounded-2xl bg-slate-50 p-2.5">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Need</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ number_format($dailyFulfillment['approved_qty'], 0) }}</p>
                    </div>
                    <div class="rounded-2xl bg-amber-50 p-2.5">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-600">Bought</p>
                        <p class="mt-1 text-sm font-black text-amber-700">{{ number_format($dailyFulfillment['bought_qty'], 0) }}</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 p-2.5">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-600">Left</p>
                        <p class="mt-1 text-sm font-black text-emerald-700">{{ number_format($dailyFulfillment['remaining_qty'], 0) }}</p>
                    </div>
                    <div class="rounded-2xl bg-cyan-50 p-2.5">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-600">Carts</p>
                        <p class="mt-1 text-sm font-black text-cyan-700">{{ $dailyFulfillment['draft_carts'] }}</p>
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <a href="{{ route('purchaser.dashboard', ['date' => $date, 'tab' => 'daily-summary']) }}" class="{{ $tab === 'daily-summary' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }} flex-1 rounded-2xl px-3 py-2.5 text-center text-xs font-black">Daily</a>
                    <a href="{{ route('purchaser.dashboard', ['date' => $date, 'tab' => 'vendor-cart-builder']) }}" class="{{ $tab === 'vendor-cart-builder' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }} flex-1 rounded-2xl px-3 py-2.5 text-center text-xs font-black">Cart</a>
                    <a href="{{ route('purchaser.dashboard', ['date' => $date, 'tab' => 'submitted-history']) }}" class="{{ $tab === 'submitted-history' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }} flex-1 rounded-2xl px-3 py-2.5 text-center text-xs font-black">History</a>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('purchaser.dashboard') }}" method="GET" class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search product..." class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:bg-white focus:outline-none">
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($quickFilters as $filter)
                        <button type="submit" name="chip" value="{{ $filter }}" class="{{ $selectedChip === $filter ? 'bg-cyan-600 text-white' : 'bg-slate-100 text-slate-600' }} shrink-0 rounded-full px-3 py-2 text-[11px] font-black">
                            {{ $filter }}
                        </button>
                    @endforeach
                </div>
            </form>

            @if ($tab === 'daily-summary')
                <div class="space-y-3">
                    <div class="flex items-center justify-between rounded-3xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div>
                            <p class="text-xs font-black text-slate-900">{{ $selectedChip }} Queue</p>
                            <p class="text-[11px] font-medium text-slate-500">{{ $dailySummary->count() }} products</p>
                        </div>
                        <a href="{{ $dailySummaryShareUrl }}" target="_blank" rel="noopener" class="rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-black text-white">WhatsApp</a>
                    </div>

                    @forelse ($dailySummary as $summary)
                        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black text-slate-950">{{ $summary['product_name'] }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-600">{{ $summary['category_name'] ?: 'Other' }}</span>
                                        <span class="text-[11px] font-bold text-slate-500">{{ number_format($summary['remaining_qty'], 2) }} {{ $summary['unit'] }} left</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($summary['quantity_buckets'] as $bucket)
                                            <span class="rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-black text-cyan-700">{{ $bucket['formatted'] }} x {{ $bucket['count'] }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <button type="button" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.remove('hidden')" class="h-10 w-10 shrink-0 rounded-xl border border-slate-200 bg-white text-xs font-black text-slate-700">i</button>
                                <button type="button" onclick="document.getElementById('add-product-{{ $summary['product_id'] }}').classList.remove('hidden')" class="h-10 w-10 shrink-0 rounded-xl bg-cyan-600 text-lg font-black leading-none text-white">+</button>
                            </div>
                        </div>

                        <!-- Info Modal -->
                        <div id="info-product-{{ $summary['product_id'] }}" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                            <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.add('hidden')"></div>
                            <div class="flex min-h-screen items-center justify-center p-4">
                                <div class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-4 shadow-2xl transition-all">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-950">{{ $summary['product_name'] }}</p>
                                            <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }} total</p>
                                        </div>
                                        <button type="button" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.add('hidden')" class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-700">Close</button>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($summary['quantity_buckets'] as $bucket)
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-700">{{ $bucket['formatted'] }} x {{ $bucket['count'] }}</span>
                                        @endforeach
                                    </div>

                                    <details class="mt-3 rounded-xl bg-slate-50 p-3">
                                        <summary class="cursor-pointer text-xs font-black text-slate-900">Shop order details</summary>
                                        <div class="mt-3 space-y-2">
                                            @foreach ($summary['shop_details'] as $detail)
                                                <div class="flex items-center justify-between gap-2 rounded-xl {{ $detail['is_direct_purchase'] ?? false ? 'bg-emerald-50' : 'bg-white' }} px-3 py-2">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-xs font-black {{ $detail['is_direct_purchase'] ?? false ? 'text-emerald-800' : 'text-slate-900' }}">{{ $detail['shop_name'] }}</p>
                                                        <p class="text-[10px] font-bold text-slate-400">{{ $detail['order_number'] }}</p>
                                                    </div>
                                                    <p class="text-xs font-black text-slate-900">{{ number_format($detail['approved_qty'], 2) }} {{ $detail['unit'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>

                        <!-- Add Product Modal -->
                        <div id="add-product-{{ $summary['product_id'] }}" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                            <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" onclick="document.getElementById('add-product-{{ $summary['product_id'] }}').classList.add('hidden')"></div>
                            <div class="flex min-h-screen items-center justify-center p-4">
                                <div class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-4 shadow-2xl transition-all">
                                    <form action="{{ route('purchaser.cart-items.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="business_date" value="{{ $date }}">
                                        <input type="hidden" name="product_id" value="{{ $summary['product_id'] }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-black text-slate-950">{{ $summary['product_name'] }}</p>
                                                <p class="mt-1 text-[11px] font-semibold text-slate-500">Add kg/box quantity and price</p>
                                            </div>
                                            <button type="button" onclick="document.getElementById('add-product-{{ $summary['product_id'] }}').classList.add('hidden')" class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-700">Close</button>
                                        </div>
                                        <div class="mt-3 space-y-2">
                                            <select name="cart_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                                <option value="">New cart</option>
                                                @foreach ($draftCarts as $cart)
                                                    <option value="{{ $cart['id'] }}">{{ $cart['cart_number'] }}</option>
                                                @endforeach
                                            </select>
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="number" step="0.01" min="0.01" name="quantity" value="{{ number_format(max($summary['remaining_qty'], 0.01), 2, '.', '') }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                                <input type="number" step="0.01" min="0" name="unit_price" value="0" placeholder="Price" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            </div>
                                            <button type="submit" class="w-full rounded-xl bg-cyan-600 px-3 py-3 text-xs font-black text-white">Add To Cart</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No products found.</div>
                    @endforelse
                </div>
            @endif

            @if ($tab === 'vendor-cart-builder')
                <div class="space-y-3">
                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-black text-slate-950">Vendor Cart</p>
                                <p class="text-[11px] font-medium text-slate-500">Add product, select vendor, share list, submit bill.</p>
                            </div>
                            <form action="{{ route('purchaser.carts.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="business_date" value="{{ $date }}">
                                <button type="submit" class="rounded-xl bg-slate-950 px-3 py-2 text-[11px] font-black text-white">New Cart</button>
                            </form>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-black text-slate-950">Buy Other</p>
                        <form action="{{ route('purchaser.cart-items.store') }}" method="POST" class="mt-3 space-y-2">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $date }}">
                            <select name="cart_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                <option value="">New cart</option>
                                @foreach ($draftCarts as $cart)
                                    <option value="{{ $cart['id'] }}">{{ $cart['cart_number'] }}</option>
                                @endforeach
                            </select>
                            <select name="product_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                @foreach ($allProducts as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}{{ $product->category?->name ? ' - '.$product->category->name : '' }}</option>
                                @endforeach
                            </select>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="0.01" min="0.01" name="quantity" value="1" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                <input type="number" step="0.01" min="0" name="unit_price" value="0" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-black text-white">Add Product</button>
                        </form>
                    </div>

                    @foreach ($draftCarts as $cart)
                        @php
                            $billFormId = 'cart-submit-'.$cart['id'];
                            $netTotal = max(0, $cart['subtotal'] - $cart['discount_amount']);
                        @endphp
                        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $cart['cart_number'] }}</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">{{ $cart['supplier_name'] ?: 'Vendor not selected' }}</p>
                                </div>
                                <details class="relative">
                                    <summary class="list-none rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-black text-slate-700">Share</summary>
                                    <div class="absolute right-0 top-11 z-10 w-44 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg">
                                        <a href="{{ $cart['share_url'] }}" target="_blank" rel="noopener" class="block rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">Without price</a>
                                        <a href="{{ $cart['share_with_price_url'] }}" target="_blank" rel="noopener" class="mt-1 block rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">With price</a>
                                    </div>
                                </details>
                            </div>

                            <div class="mt-3 space-y-2">
                                @foreach ($cart['items'] as $item)
                                    <div class="rounded-2xl bg-slate-50 p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-xs font-black text-slate-900">{{ $item['product_name'] }}</p>
                                                <p class="text-[10px] font-medium text-slate-500">{{ number_format($item['quantity'], 2) }} {{ $item['unit'] }}</p>
                                            </div>
                                            <form action="{{ route('purchaser.cart-items.destroy', $item['id']) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-2.5 py-1.5 text-[10px] font-black text-rose-700">X</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <details class="mt-3 rounded-2xl bg-slate-50">
                                <summary class="cursor-pointer list-none px-3 py-3 text-center text-xs font-black text-slate-950">Place Order</summary>
                                <form id="{{ $billFormId }}" action="{{ route('purchaser.carts.submit') }}" method="POST" class="space-y-2 border-t border-slate-200 p-3">
                                    @csrf
                                    <input type="hidden" name="business_date" value="{{ $date }}">
                                    <input type="hidden" name="cart_id" value="{{ $cart['id'] }}">

                                    <div class="space-y-1.5">
                                        @foreach ($cart['items'] as $item)
                                            <div class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                                                <span class="min-w-0 truncate">{{ $item['product_name'] }}</span>
                                                <span class="shrink-0">{{ number_format($item['quantity'], 2) }} {{ $item['unit'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="supplier_id" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <option value="">Select vendor</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-black text-slate-700" onclick="document.getElementById('vendor-modal-{{ $cart['id'] }}').classList.remove('hidden')">
                                            New Vendor
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" name="bill_number" value="{{ $cart['bill_number'] }}" placeholder="Bill no" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                        <select name="payment_method" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <option value="Cash" @selected($cart['payment_method'] === 'Cash')>Cash</option>
                                            <option value="GPay" @selected($cart['payment_method'] === 'GPay')>GPay</option>
                                            <option value="Credit" @selected($cart['payment_method'] === 'Credit')>Credit</option>
                                            <option value="Bank Transfer" @selected($cart['payment_method'] === 'Bank Transfer')>Bank Transfer</option>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="payment_status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <option value="paid">Paid</option>
                                            <option value="partial">Partial Paid</option>
                                            <option value="unpaid">Unpaid</option>
                                            <option value="credit_pending_approval">Credit Approval</option>
                                        </select>
                                        <input type="number" step="0.01" min="0" name="paid_amount" value="{{ number_format($cart['paid_amount'], 2, '.', '') }}" placeholder="Paid amount" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="number" step="0.01" min="0" name="discount_amount" value="{{ number_format($cart['discount_amount'], 2, '.', '') }}" placeholder="Discount" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                        <input type="text" name="payment_note" value="{{ $cart['payment_note'] }}" placeholder="Payment note" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                    </div>

                                    <textarea name="payment_details" rows="2" placeholder="Paid details / settlement details" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ $cart['payment_details'] }}</textarea>
                                    <textarea name="notes" rows="2" placeholder="Cart note" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ $cart['notes'] }}</textarea>

                                    <div class="rounded-xl bg-white p-3">
                                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                                            <span>Subtotal</span>
                                            <span>{{ number_format($cart['subtotal'], 2) }}</span>
                                        </div>
                                        <div class="mt-1 flex items-center justify-between text-[11px] font-bold text-slate-600">
                                            <span>Discount</span>
                                            <span>{{ number_format($cart['discount_amount'], 2) }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between text-sm font-black text-slate-950">
                                            <span>Total</span>
                                            <span>{{ number_format($netTotal, 2) }}</span>
                                        </div>
                                    </div>

                                    <button type="submit" class="w-full rounded-xl bg-cyan-600 px-3 py-3 text-xs font-black text-white">Submit Vendor Purchase</button>
                                </form>
                            </details>

                            <!-- Create Vendor Modal -->
                            <div id="vendor-modal-{{ $cart['id'] }}" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                                <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" onclick="document.getElementById('vendor-modal-{{ $cart['id'] }}').classList.add('hidden')"></div>
                                <div class="flex min-h-screen items-center justify-center p-4">
                                    <div class="relative w-full max-w-sm transform overflow-hidden rounded-3xl bg-white p-4 shadow-2xl transition-all">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-black text-slate-950">Create Vendor</p>
                                            <button type="button" class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-700" onclick="document.getElementById('vendor-modal-{{ $cart['id'] }}').classList.add('hidden')">Close</button>
                                        </div>
                                        <div class="mt-3 space-y-2">
                                            <input form="{{ $billFormId }}" type="text" name="vendor_name" placeholder="Vendor name" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <input form="{{ $billFormId }}" type="text" name="vendor_location" placeholder="Location" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <input form="{{ $billFormId }}" type="text" name="vendor_mobile_number" placeholder="Mobile number" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <input form="{{ $billFormId }}" type="text" name="vendor_type" value="Vendor" placeholder="Type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <input form="{{ $billFormId }}" type="text" name="payment_terms" value="COD" placeholder="Payment terms" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                                            <input form="{{ $billFormId }}" type="hidden" name="preferred_payment_method" value="Cash">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if ($draftCarts->isEmpty())
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No draft carts yet.</div>
                    @endif
                </div>
            @endif

            @if ($tab === 'submitted-history')
                <div class="space-y-3">
                    @forelse ($historyCarts as $cart)
                        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-950">{{ $cart->supplier?->name ?: 'Vendor removed' }}</p>
                                    <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $cart->cart_number }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-700">{{ $cart->status_label }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div class="rounded-2xl bg-slate-50 p-2.5">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Bill</p>
                                    <p class="mt-1 text-[11px] font-black text-slate-900">{{ $cart->bill_number ?: 'Pending' }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-2.5">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Payment</p>
                                    <p class="mt-1 text-[11px] font-black text-slate-900">{{ $cart->payment_method ?: 'Pending' }}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-2.5">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Status</p>
                                    <p class="mt-1 text-[11px] font-black text-slate-900">{{ $cart->purchaseInvoice?->payment_status ?: 'unpaid' }}</p>
                                </div>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach ($cart->items as $item)
                                    <div class="rounded-2xl bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-700">{{ $item->product->name }} - {{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }}</div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm font-bold text-slate-500">No submitted carts for this date.</div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
