<table class="w-full min-w-[42rem] text-left text-xs">
    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200/80">
        <tr>
            <th scope="col" class="py-3 px-4 text-center w-12">SL No</th>
            <th scope="col" class="py-3 px-4 text-left">Name</th>
            <th scope="col" class="py-3 px-4 text-right">Amount</th>
            <th scope="col" class="py-3 px-4 text-right">Paid</th>
            <th scope="col" class="py-3 px-4 text-right">Balance</th>
            <th scope="col" class="py-3 px-4 text-center">Action</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 font-medium" x-ref="vendorTableBody">
        @foreach($vendors as $index => $vendor)
            @php
                $vTotalPurchase = (float) ($vendor->total_purchase ?? 0);
                $vCash = (float) ($vendor->cash_purchase ?? 0);
                $vCredit = (float) ($vendor->credit_purchase ?? 0);
                if ($vCredit <= 0.009) {
                    $vPaidAmount = (float) ($vendor->paid_amount ?? $vendor->cash_purchase);
                } elseif ($vCash <= 0.009) {
                    $vPaidAmount = (float) ($vendor->paid_amount ?? $vendor->credit_paid);
                } else {
                    $vPaidAmount = (float) ($vendor->paid_amount ?? ($vCash + (float) $vendor->credit_paid));
                }
                $vCreditBalance = (float) ($vendor->outstanding ?? 0);
                $vName = strtolower($vendor->supplier_name ?? '');
                $vTags = strtolower($vendor->category_tags ?? '');

                $supplierUuid = $vendor->supplier_public_uuid ?? null;
                $vendorUrl = $supplierUuid
                    ? route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplierUuid] + $purchaseContext)
                    : '#';
                $vendorPdfUrl = $supplierUuid
                    ? route('admin.cashbook.finance.purchase.vendors.single.export.pdf', ['supplier' => $supplierUuid] + $purchaseContext)
                    : '#';
                $vendorExcelUrl = $supplierUuid
                    ? route('admin.cashbook.finance.purchase.vendors.single.export.excel', ['supplier' => $supplierUuid] + $purchaseContext)
                    : '#';
                $vendorCreditUrl = $supplierUuid
                    ? route('admin.cashbook.finance.vendor-credit.show', $supplierUuid)
                    : '#';
            @endphp
            <tr class="vendor-table-row hover:bg-slate-50/80 transition-colors duration-150 {{ $index >= 20 ? 'hidden' : '' }}"
                data-cash="{{ $vCash }}"
                data-credit="{{ $vCredit }}"
                data-name="{{ $vName }}"
                data-tags="{{ $vTags }}"
                style="{{ $index >= 20 ? 'display: none;' : '' }}">
                <td class="py-3 px-4 text-center font-mono font-bold text-slate-500 whitespace-nowrap">
                    <span class="table-sl-no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                </td>
                <td class="py-3 px-4 font-bold text-slate-900 whitespace-nowrap">
                    <a href="{{ $vendorUrl }}" class="hover:text-emerald-700 hover:underline transition">
                        {{ $vendor->supplier_name ?? 'Unassigned Vendor' }}
                    </a>
                </td>
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 tabular-nums whitespace-nowrap">
                    ₹{{ number_format($vTotalPurchase, 2) }}
                </td>
                <td class="py-3 px-4 text-right font-mono font-semibold text-slate-700 tabular-nums whitespace-nowrap">
                    ₹{{ number_format($vPaidAmount, 2) }}
                </td>
                <td class="py-3 px-4 text-right font-mono tabular-nums whitespace-nowrap">
                    @if($vCreditBalance > 0.009)
                        @if($supplierUuid)
                            <div class="inline-flex items-center gap-1.5 justify-end">
                                <a href="{{ $vendorCreditUrl }}"
                                   @click.prevent="openVendorPayment('{{ $supplierUuid }}', '{{ $vendorCreditUrl }}')"
                                   class="inline-block font-bold text-rose-700 bg-rose-50/90 hover:bg-rose-100 hover:border-rose-300 border border-rose-200/80 px-2 py-0.5 rounded-md transition shadow-2xs cursor-pointer group"
                                   title="Click to update payment for {{ $vendor->supplier_name }} (Balance: ₹{{ number_format($vCreditBalance, 2) }})">
                                    <span class="group-hover:underline">₹{{ number_format($vCreditBalance, 2) }}</span>
                                </a>
                                <a href="{{ $vendorCreditUrl }}"
                                   @click.prevent="openVendorPayment('{{ $supplierUuid }}', '{{ $vendorCreditUrl }}')"
                                   class="inline-flex items-center justify-center p-1 rounded-md bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-2xs cursor-pointer"
                                   title="Update Payment for {{ $vendor->supplier_name }}"
                                   aria-label="Update Payment for {{ $vendor->supplier_name }}">
                                    <svg x-show="loadingVendorPayment === '{{ $supplierUuid }}'" x-cloak class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <i x-show="loadingVendorPayment !== '{{ $supplierUuid }}'" data-lucide="credit-card" class="w-3 h-3"></i>
                                </a>
                            </div>
                        @else
                            <span class="inline-block font-bold text-rose-700 bg-rose-50/90 border border-rose-200/70 px-2 py-0.5 rounded-md">
                                ₹{{ number_format($vCreditBalance, 2) }}
                            </span>
                        @endif
                    @else
                        <span class="text-slate-600 font-medium">
                            ₹{{ number_format($vCreditBalance, 2) }}
                        </span>
                    @endif
                </td>
                <td class="py-3 px-4 text-center whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5 justify-center">
                        <a href="{{ $vendorUrl }}"
                           class="inline-flex items-center justify-center p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-emerald-50 hover:border-emerald-300 text-slate-700 hover:text-emerald-700 transition shadow-2xs cursor-pointer"
                           title="View Vendor Details for {{ $vendor->supplier_name }}"
                           aria-label="View Vendor Details for {{ $vendor->supplier_name }}">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </a>
                        <a href="{{ $vendorPdfUrl }}"
                           class="inline-flex items-center justify-center p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-rose-50 hover:border-rose-300 text-rose-600 transition shadow-2xs cursor-pointer"
                           title="Export {{ $vendor->supplier_name }} Bill Report to PDF"
                           aria-label="Export {{ $vendor->supplier_name }} Bill Report to PDF">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        </a>
                        <a href="{{ $vendorExcelUrl }}"
                           class="inline-flex items-center justify-center p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-emerald-50 hover:border-emerald-300 text-emerald-600 transition shadow-2xs cursor-pointer"
                           title="Export {{ $vendor->supplier_name }} Report to Excel (.xlsx)"
                           aria-label="Export {{ $vendor->supplier_name }} Report to Excel (.xlsx)">
                            <i data-lucide="sheet" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
