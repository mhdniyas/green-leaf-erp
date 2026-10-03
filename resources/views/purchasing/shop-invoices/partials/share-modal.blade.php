@php
    $finalTotal = round((float) $invoice->final_total, 2);
    $money = fn (float|int|string|null $amount): string => 'Rs. '.number_format((float) $amount, 2);
    $shareUrl = $shareUrl ?? \Illuminate\Support\Facades\URL::signedRoute('shop-invoices.shared', ['invoice' => $invoice->invoice_number]);
    $pdfUrl = $pdfUrl ?? route('purchasing.shop-invoices.pdf', $invoice);
    $downloadPdfUrl = $pdfUrl . (str_contains($pdfUrl, '?') ? '&' : '?') . 'download=1';
    $shopName = $invoice->shop?->name ?? 'Shop';
    $invoiceDateStr = $invoice->business_date->format('d M Y');
    $summaryText = "Green Leaf — Shop Bill\nInvoice: {$invoice->invoice_number}\nShop: {$shopName}\nDate: {$invoiceDateStr}\nTotal: {$money($finalTotal)}\n\nView Bill: {$shareUrl}";
@endphp

<div
    class="fixed inset-0 z-50 hidden items-end bg-slate-950/60 p-0 backdrop-blur-xs sm:items-center sm:p-4 print:hidden"
    id="share-bill-modal"
    data-share-modal
    data-invoice-number="{{ $invoice->invoice_number }}"
    data-shop-name="{{ $shopName }}"
    data-invoice-total="{{ $money($finalTotal) }}"
    data-share-url="{{ $shareUrl }}"
    data-pdf-url="{{ $pdfUrl }}"
    data-download-pdf-url="{{ $downloadPdfUrl }}"
    data-share-text="{{ $summaryText }}"
>
    <div class="w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:mx-auto sm:max-w-md sm:rounded-2xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Share Invoice</p>
                <h3 class="mt-0.5 text-lg font-black text-slate-950">Share Bill</h3>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $shopName }} · {{ $invoice->invoice_number }}</p>
            </div>
            <button
                type="button"
                class="rounded-xl border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                data-close-share-modal
                aria-label="Close share menu"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="mt-4 grid gap-3">
            {{-- 1. Share (WhatsApp / Native Share) --}}
            <button
                type="button"
                data-share-action="share-whatsapp"
                class="flex w-full items-center gap-3.5 rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-4 text-left transition hover:bg-emerald-100/60 active:scale-[0.99]"
            >
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs">
                    {{-- Lucide MessageCircle / WhatsApp Icon --}}
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" /></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="text-base font-black text-slate-950">Share</span>
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full">WhatsApp</span>
                    </div>
                    <p class="text-xs font-semibold text-slate-500 truncate mt-0.5">Share bill via WhatsApp &amp; device share</p>
                </div>
            </button>

            {{-- 2. Download PDF --}}
            <a
                href="{{ $downloadPdfUrl }}"
                target="_blank"
                download="Shop-Invoice-{{ $invoice->invoice_number }}.pdf"
                data-share-action="download-pdf"
                class="flex w-full items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-slate-300 hover:bg-slate-50 active:scale-[0.99]"
            >
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-xs">
                    {{-- Lucide Download Icon --}}
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="text-base font-black text-slate-950">Download PDF</span>
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">Save File</span>
                    </div>
                    <p class="text-xs font-semibold text-slate-500 truncate mt-0.5">Save invoice PDF file to device</p>
                </div>
            </a>
        </div>

        {{-- Toast alert inside modal for dynamic feedback --}}
        <div class="mt-3 hidden rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800" data-share-toast></div>

        <div class="mt-4 pt-3 border-t border-slate-100 flex justify-end">
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50"
                data-close-share-modal
            >
                Close
            </button>
        </div>
    </div>
</div>
