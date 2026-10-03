<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Bill - {{ $invoice->invoice_number }}</title>
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
    @php
        $downloadPdfUrl = route('purchasing.shop-invoices.pdf', array_merge(['invoice' => $invoice, 'download' => 1], request()->query()));
    @endphp

    <div class="mx-auto max-w-[46rem] space-y-4">
        {{-- Top Action Bar (hidden in print) --}}
        <div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <div class="flex items-center gap-2">
                @if (auth()->check() && (auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin')))
                    <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 hover:bg-slate-50 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Back to Invoice
                    </a>
                @endif
                <span class="text-xs font-mono font-bold text-slate-500">{{ $invoice->invoice_number }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Download PDF --}}
                <a
                    href="{{ $downloadPdfUrl }}"
                    download="Shop-Invoice-{{ $invoice->invoice_number }}.pdf"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 text-xs font-black text-emerald-800 hover:bg-emerald-100 transition"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
                    Download PDF
                </a>
            </div>
        </div>

        {{-- The Exact Invoice Card --}}
        @include('purchasing.shop-invoices.partials.invoice-card', [
            'invoice' => $invoice,
            'isPdf' => true,
            'canEditBill' => false,
            'canEditDiscount' => false,
        ])
    </div>
</body>
</html>
