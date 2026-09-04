{{-- OUT HEADERS BOTTOM SHEET / MODAL --}}
<div id="out-header-modal" onclick="handleModalBackdropClick(event, 'out-header-modal')" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 backdrop-blur-xs hidden transition-all duration-200">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-t-2xl sm:rounded-2xl border border-slate-100 bg-white p-3 sm:p-4 shadow-2xl space-y-2.5 max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-full bg-white border border-slate-200/80 shadow-2xs flex items-center justify-center shrink-0">
                    <img src="{{ asset('images/greenleaf-logo.png') }}" alt="GL" class="h-4 w-4 object-contain">
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wide text-slate-900 truncate">
                        ADD EXPENSE
                    </h3>
                    <p class="text-[10px] font-bold text-slate-400">Choose category to record</p>
                </div>
            </div>
            <button type="button" aria-label="Close" onclick="closeOutHeaderModal()"
                    class="h-8 w-8 min-h-[32px] min-w-[32px] inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div class="space-y-1.5">
            @forelse($expenseHeaders as $hSec)
                @php
                    $firstLetter = strtoupper(substr($hSec['name'], 0, 1));
                @endphp
                <div onclick='selectHeaderForEntry(@json($hSec["id"]))'
                     class="p-2.5 sm:p-3 rounded-xl bg-slate-50/70 hover:bg-rose-50/60 border border-slate-100 hover:border-rose-200/80 transition cursor-pointer group flex items-center justify-between gap-2.5 active:scale-[0.99]">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <div class="w-7 h-7 rounded-full bg-white border border-slate-200/60 shadow-2xs flex items-center justify-center text-rose-700 font-black text-xs uppercase shrink-0 group-hover:scale-105 transition">
                            {{ $firstLetter }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs sm:text-sm font-black text-slate-900 group-hover:text-rose-800 transition truncate">
                                {{ $hSec['name'] }}
                            </div>
                            <div class="text-[10px] font-semibold text-slate-400 mt-0.5" id="out-modal-sub-{{ $hSec['id'] }}">
                                @if(!empty($hSec['product_tagging_enabled']))
                                    Product Tagging Enabled
                                @else
                                    {{ count($hSec['settings']) }} categories
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="font-mono text-xs font-black text-slate-900 bg-white px-2.5 py-0.5 rounded-full border border-slate-200/60 shadow-2xs" id="out-modal-total-{{ $hSec['id'] }}">
                            ₹0.00
                        </span>
                        <div class="w-6 h-6 rounded-full bg-white border border-slate-200/60 flex items-center justify-center text-slate-400 group-hover:text-rose-700 transition">
                            <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-xs font-bold text-slate-400 bg-slate-50 rounded-xl border border-slate-100">
                    No expense headers configured.
                </div>
            @endforelse
        </div>
    </div>
</div>
