@props([
    'excelUrl' => null,
    'pdfUrl' => null,
    'tableId' => null,
    'title' => null,
    'compact' => false,
    'align' => 'right',
])

<div x-data="{ open: false, includeDetails: '1' }" class="relative inline-block text-left print:hidden">
    <!-- Compact Share / Export Trigger Button -->
    <button @click="open = !open" type="button"
        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-800 shadow-2xs hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all cursor-pointer"
        title="Share & Export options">
        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
        </svg>
        <span>Export</span>
        <svg class="w-3 h-3 text-slate-400 transition-transform duration-200 shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Dropdown Popup Menu -->
    <div x-show="open" @click.away="open = false" x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 mt-1.5 w-64 max-w-[calc(100vw-1.5rem)] rounded-2xl bg-white p-2 shadow-xl ring-1 ring-black/5 z-50 space-y-1" style="display: none;">
        
        <!-- Radio Selection for Export Scope -->
        <div class="px-2.5 py-2 border-b border-slate-100 mb-1 bg-slate-50/80 rounded-xl space-y-1">
            <p class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Export Scope</p>
            <div class="space-y-1 text-xs font-bold text-slate-700">
                <label class="flex items-center gap-2 cursor-pointer hover:text-slate-900">
                    <input type="radio" value="1" x-model="includeDetails" class="text-emerald-600 focus:ring-emerald-500 rounded-full h-3.5 w-3.5 cursor-pointer">
                    <span>Summary + Sales/Expense Details</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer hover:text-slate-900">
                    <input type="radio" value="0" x-model="includeDetails" class="text-emerald-600 focus:ring-emerald-500 rounded-full h-3.5 w-3.5 cursor-pointer">
                    <span>Summary Table Only</span>
                </label>
            </div>
        </div>

        <!-- Copy for Sheets (Unstyled TSV) -->
        <button type="button"
            @click="open = false; window.copyTableToGoogleSheets('{{ $tableId ?: 'table' }}', '{{ $title ?: 'Report' }}', includeDetails)"
            class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-950 transition flex items-center gap-2.5 cursor-pointer">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                </svg>
            </span>
            <div>
                <p class="font-black text-slate-900 leading-tight">Copy for Sheets</p>
                <p class="text-[10px] font-medium text-slate-400">Plain text TSV (No style)</p>
            </div>
        </button>

        <!-- Excel / CSV Export -->
        @if ($excelUrl)
            <a href="{{ $excelUrl }}" @click.prevent="open = false; window.location.href = window.buildExportUrl('{{ $excelUrl }}', includeDetails)"
                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition flex items-center gap-2.5 cursor-pointer">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-emerald-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </span>
                <div>
                    <p class="font-black text-slate-900 leading-tight">Export Excel / CSV</p>
                    <p class="text-[10px] font-medium text-slate-400">Spreadsheet download</p>
                </div>
            </a>
        @else
            <button type="button"
                @click="open = false; window.copyTableToGoogleSheets('{{ $tableId ?: 'table' }}', '{{ $title ?: 'Report' }}', includeDetails)"
                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition flex items-center gap-2.5 cursor-pointer">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-emerald-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </span>
                <div>
                    <p class="font-black text-slate-900 leading-tight">Export Excel / CSV</p>
                    <p class="text-[10px] font-medium text-slate-400">Spreadsheet data</p>
                </div>
            </button>
        @endif

        <!-- PDF Share / Print -->
        @if ($pdfUrl)
            <a href="{{ $pdfUrl }}" target="_blank" @click.prevent="open = false; window.open(window.buildExportUrl('{{ $pdfUrl }}', includeDetails), '_blank')"
                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition flex items-center gap-2.5 cursor-pointer">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                </span>
                <div>
                    <p class="font-black text-slate-900 leading-tight">PDF Share / Print</p>
                    <p class="text-[10px] font-medium text-slate-400">Printable PDF document</p>
                </div>
            </a>
        @else
            <button type="button"
                @click="open = false; window.print()"
                class="w-full text-left rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition flex items-center gap-2.5 cursor-pointer">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                </span>
                <div>
                    <p class="font-black text-slate-900 leading-tight">PDF Share / Print</p>
                    <p class="text-[10px] font-medium text-slate-400">Print or save as PDF</p>
                </div>
            </button>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.buildExportUrl = function(baseUrl, includeDetails) {
                if (!baseUrl || baseUrl === '#') return '#';
                try {
                    let url = new URL(baseUrl, window.location.origin);
                    let pageParams = new URLSearchParams(window.location.search);
                    if (pageParams.has('timeframe')) url.searchParams.set('timeframe', pageParams.get('timeframe'));
                    if (pageParams.has('start_date')) url.searchParams.set('start_date', pageParams.get('start_date'));
                    if (pageParams.has('end_date')) url.searchParams.set('end_date', pageParams.get('end_date'));

                    url.searchParams.set('include_details', includeDetails || '1');
                    return url.toString();
                } catch (e) {
                    let sep = baseUrl.includes('?') ? '&' : '?';
                    return baseUrl + sep + 'include_details=' + (includeDetails || '1');
                }
            };
            window.copyTableToGoogleSheets = function(tableTarget, customTitle, includeDetails) {
                let tables = [];
                if (typeof tableTarget === 'string') {
                    tables = Array.from(document.querySelectorAll(tableTarget));
                } else if (tableTarget instanceof HTMLElement) {
                    tables = [tableTarget];
                }

                if (tables.length === 0) {
                    tables = Array.from(document.querySelectorAll('table'));
                }

                if (tables.length === 0) {
                    let csvUrl = '{{ route('admin.cashbook.reports.export.csv') }}' + '?include_details=' + (includeDetails || '1');
                    fetch(csvUrl)
                        .then(res => res.text())
                        .then(csvText => {
                            let tsvText = csvText.split('\n').map(line => {
                                return line.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/).map(c => c.replace(/^"|"$/g, '')).join('\t');
                            }).join('\n');

                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(tsvText).then(() => {
                                    window.showExportToast('Copied for Google Sheets! Paste directly in your sheet.');
                                });
                            } else {
                                fallbackCopy(tsvText);
                            }
                        })
                        .catch(() => {
                            window.showExportToast ? window.showExportToast('No table found to copy.') : alert('No table found to copy.');
                        });
                    return;
                }

                let lines = [];
                tables.forEach((table, tIdx) => {
                    if (tables.length > 1 && tIdx > 0) {
                        lines.push('');
                    }

                    let trs = table.querySelectorAll('tr');
                    trs.forEach(tr => {
                        if (tr.classList.contains('no-export')) return;
                        let cells = tr.querySelectorAll('th, td');
                        if (cells.length === 0) return;
                        let rowCells = [];
                        cells.forEach(cell => {
                            let text = cell.innerText || cell.textContent || '';
                            text = text.replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
                            if (text === 'Action' || text === 'Detail' || text === 'Daily View' || text === 'Open') {
                                return;
                            }
                            rowCells.push(text);
                        });
                        if (rowCells.length > 0) {
                            lines.push(rowCells.join('\t'));
                        }
                    });
                });

                let tsvContent = lines.join('\n');

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(tsvContent).then(() => {
                        window.showExportToast('Copied for Google Sheets! Paste directly in your sheet.');
                    }).catch(err => {
                        console.error('Failed to copy to clipboard:', err);
                        fallbackCopy(tsvContent);
                    });
                } else {
                    fallbackCopy(tsvContent);
                }
            };

            function fallbackCopy(text) {
                let textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    window.showExportToast('Copied for Google Sheets! Paste directly in your sheet.');
                } catch (e) {
                    alert('Could not copy automatically.');
                }
                document.body.removeChild(textArea);
            }

            window.showExportToast = function(message) {
                let toast = document.getElementById('export-toast-notification');
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = 'export-toast-notification';
                    toast.className = 'fixed bottom-5 right-5 z-50 flex items-center gap-2.5 rounded-2xl bg-slate-950 px-4 py-3 text-xs font-black text-white shadow-2xl transition-all duration-300 transform translate-y-10 opacity-0 pointer-events-none';
                    toast.innerHTML = `<span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white shrink-0"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg></span><span id="export-toast-message"></span>`;
                    document.body.appendChild(toast);
                }
                document.getElementById('export-toast-message').textContent = message;
                requestAnimationFrame(() => {
                    toast.classList.remove('translate-y-10', 'opacity-0');
                    toast.classList.add('translate-y-0', 'opacity-100');
                });
                setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('translate-y-10', 'opacity-0');
                }, 3500);
            };
        </script>
    @endpush
@endonce
