<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Leaf — Shop Bill {{ $invoice->invoice_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            body {
                background: #ffffff !important;
                color: #0f172a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print, [data-no-print] {
                display: none !important;
            }
            section {
                border-color: #cbd5e1 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-950 min-h-screen py-4 sm:py-8 px-2 sm:px-4">
    <div class="mx-auto max-w-[46rem] space-y-4">
        {{-- Public Header & Action Bar --}}
        <div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-600 text-white font-black text-sm">GL</span>
                <div>
                    <h1 class="text-sm font-black text-slate-950">Green Leaf Fresh</h1>
                    <p class="text-[10px] font-bold text-slate-400 font-mono">{{ $invoice->invoice_number }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Share Bill --}}
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 text-xs font-black text-emerald-800 hover:bg-emerald-100 transition"
                    data-open-share-modal
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" /></svg>
                    Share Bill
                </button>

                {{-- Download PDF --}}
                <a
                    href="{{ $pdfUrl }}&download=1"
                    download="Shop-Invoice-{{ $invoice->invoice_number }}.pdf"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 hover:bg-slate-50 transition"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                    Download PDF
                </a>

                {{-- Print / Save PDF --}}
                <button
                    type="button"
                    onclick="window.print()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800 transition"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                    Print Bill
                </button>
            </div>
        </div>

        {{-- The Exact Invoice Card --}}
        @include('purchasing.shop-invoices.partials.invoice-card', [
            'invoice' => $invoice,
            'isPdf' => true,
            'canEditBill' => false,
            'canEditDiscount' => false,
        ])

        {{-- Credit note if applicable --}}
        @if ($invoice->creditNoteSummary()['has_changes'] ?? false)
            @include('purchasing.shop-invoices.partials.credit-note', ['invoice' => $invoice])
        @endif
    </div>

    {{-- Share Modal --}}
    @include('purchasing.shop-invoices.partials.share-modal', [
        'invoice' => $invoice,
        'shareUrl' => $shareUrl,
        'pdfUrl' => $pdfUrl,
    ])

    @include('purchasing.shop-invoices.partials.share-scripts')
</body>
</html>
