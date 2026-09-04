{{-- DEDICATED HEADER ENTRY DRAWER / MODAL --}}
<div id="header-entry-sheet" onclick="handleModalBackdropClick(event, 'header-entry-sheet')" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 backdrop-blur-xs hidden transition-all duration-200">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-100 bg-white p-3 sm:p-4 shadow-2xl space-y-2.5 max-h-[85vh] flex flex-col">
        {{-- Header Top Bar --}}
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 gap-2 shrink-0">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <button type="button" aria-label="Back" onclick="closeHeaderEntrySheet()"
                        class="h-8 w-8 min-h-[32px] min-w-[32px] inline-flex items-center justify-center rounded-full bg-slate-100 border border-slate-200/80 active:scale-95 transition cursor-pointer shrink-0 sm:hidden">
                    <img src="{{ asset('images/greenleaf-logo.png') }}" alt="GL" class="h-4 w-4 object-contain">
                </button>
                <div class="w-7 h-7 rounded-full bg-white border border-slate-200/80 shadow-2xs flex items-center justify-center shrink-0 hidden sm:flex">
                    <img src="{{ asset('images/greenleaf-logo.png') }}" alt="GL" class="h-4 w-4 object-contain">
                </div>
                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wide text-slate-900 truncate" id="entry-sheet-title">
                    HEADER TITLE
                </h3>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <span class="font-mono text-xs font-black text-slate-950 px-2.5 py-1 bg-slate-100 rounded-full border border-slate-200/60" id="entry-sheet-subtotal">
                    ₹0.00
                </span>
                <button type="button" aria-label="Close" onclick="closeHeaderEntrySheet()"
                        class="h-8 w-8 min-h-[32px] min-w-[32px] inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
        </div>

        {{-- Entry Form Scrollable Body --}}
        <div class="space-y-2 overflow-y-auto flex-1 pr-0.5 py-0.5" id="entry-sheet-body">
            @foreach($ownerHeaderSections as $hSec)
                <div id="header-form-section-{{ $hSec['id'] }}" class="space-y-1.5 hidden">
                    @foreach($hSec['settings'] as $s)
                        @php
                            $cat = strtolower((string) ($s->entryType?->category ?? ''));
                            $isIncome = $cat === 'income' || $s->include_in_sales || $s->include_in_income;
                            $resolver = app(\App\Services\Cashbook\CashFlowResolutionService::class);
                            $destLabel = $resolver->resolveDestinationLabel($s);
                            $requiresNote = (bool) ($s->requires_note ?? false);
                            $noteEnabled = $resolver->resolveNoteEnabled($s);
                            $showNote = $requiresNote || $noteEnabled;
                            $rawName = $s->displayName();
                            $displayName = $rawName;
                            $displaySub = (strtolower($displayName) === 'cash' || strtolower($displayName) === 'cash sales') ? 'Remaining cash in shop' : $destLabel;
                            $firstLetter = strtoupper(substr($displayName, 0, 1));
                        @endphp

                        <div class="p-2 sm:p-2.5 rounded-xl bg-slate-50/70 border border-slate-100 hover:border-slate-200/80 transition space-y-1" data-entry-row="{{ $s->id }}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1 flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-white border border-slate-200/60 shadow-2xs flex items-center justify-center text-slate-800 font-black text-[11px] uppercase shrink-0">
                                        {{ $firstLetter }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs font-bold text-slate-900 block truncate leading-none">{{ $displayName }}</span>
                                        <span class="text-[10px] font-medium text-slate-400 block truncate leading-none mt-0.5">{{ $displaySub }}</span>
                                    </div>
                                </div>

                                {{-- Amount Input (Compact h-8 / 32px height) --}}
                                <div class="relative shrink-0 w-28 sm:w-32">
                                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-slate-400 font-bold text-xs">₹</span>
                                    <input type="number"
                                           inputmode="decimal"
                                           min="0"
                                           step="0.01"
                                           id="input-s-{{ $s->id }}"
                                           data-setting-id="{{ $s->id }}"
                                           oninput="onOwnerInputChange(this)"
                                           onblur="formatInputOnBlur(this)"
                                           placeholder="0.00"
                                           class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-5 pr-2 text-right text-sm font-black font-mono text-slate-950 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none shadow-2xs transition">
                                </div>
                            </div>

                            {{-- Optional or Required Note --}}
                            @if($showNote)
                                <div class="pt-0.5">
                                    @if($requiresNote)
                                        <div>
                                            <input type="text"
                                                   id="input-note-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   oninput="onOwnerNoteInputChange(this, {{ $s->id }})"
                                                   placeholder="Note (Required for this entry)..."
                                                   class="h-7 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-semibold text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none transition">
                                            <span id="note-error-{{ $s->id }}" class="text-[10px] font-bold text-rose-600 hidden mt-0.5 block">Note required for this entry</span>
                                        </div>
                                    @else
                                        <div id="note-wrapper-{{ $s->id }}" class="hidden">
                                            <input type="text"
                                                   id="input-note-{{ $s->id }}"
                                                   data-setting-id="{{ $s->id }}"
                                                   oninput="onOwnerNoteInputChange(this, {{ $s->id }})"
                                                   placeholder="Add optional note..."
                                                   class="h-7 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-semibold text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none transition">
                                        </div>
                                        <button type="button"
                                                onclick="toggleNoteInput({{ $s->id }})"
                                                id="note-toggle-btn-{{ $s->id }}"
                                                class="text-[10px] font-bold text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-0.5 cursor-pointer">
                                            <i data-lucide="plus" class="h-2.5 w-2.5"></i> Add Note
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Product Tagged Rows (If enabled for this Header) --}}
                    @if(!empty($hSec['product_tagging_enabled']))
                        <div class="pt-2 border-t border-slate-100 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Product Items</span>
                                <button type="button" onclick='openOwnerProductModal(@json($hSec["id"]), @json($hSec["name"]))'
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 transition cursor-pointer active:scale-95">
                                    <i data-lucide="plus" class="h-3 w-3"></i> Add Product
                                </button>
                            </div>
                            <div id="product-rows-container-{{ $hSec['id'] }}" class="space-y-1.5">
                                <!-- Dynamic product rows rendered by JS -->
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Footer Save Button --}}
        <div class="pt-2 border-t border-slate-100 shrink-0">
            <button type="button"
                    id="save-active-header-btn"
                    onclick="saveActiveHeaderEntries()"
                    class="w-full flex h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 text-white font-black text-xs sm:text-sm hover:bg-emerald-700 active:scale-[0.98] transition shadow-md cursor-pointer">
                <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                <span id="save-active-header-text">Save Header</span>
            </button>
        </div>
    </div>
</div>
