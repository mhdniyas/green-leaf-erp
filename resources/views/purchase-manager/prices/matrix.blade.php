@extends('purchase-manager.layouts.app')

@section('title', 'Selling Price Matrix')
@section('page_title', 'Selling Price Matrix')
@section('page_description', 'Dedicated selling-price editor with daily comparison against purchasing cost.')

@push('styles')
<style>
/* ─── Core cell components ─────────────────────────────────────────────── */
.matrix-cell-input{flex:1 1 0%;min-width:52px;height:1.875rem;border-radius:0.375rem;border:1px solid #e2e8f0;background-color:#fff;padding:0.125rem 0.25rem;text-align:center;font-weight:700;font-size:0.75rem;line-height:1rem;transition:border-color 120ms,box-shadow 120ms;touch-action:manipulation}
.matrix-cell-input:focus{outline:none;border-color:#0284c7;box-shadow:0 0 0 2px rgba(2,132,199,0.15)}
.matrix-cell-input.is-changed{color:#dc2626!important;font-weight:900!important;background-color:rgba(254,242,242,0.5)!important;border-color:#fca5a5!important}
.matrix-cell-input[readonly]{background:#f8fafc;color:#94a3b8;cursor:not-allowed}
.matrix-cell-save-btn{flex-shrink:0;height:1.875rem;width:1.875rem;display:inline-flex;align-items:center;justify-content:center;border-radius:0.375rem;background-color:#0f172a;color:#fff;transition:background-color 120ms,transform 80ms;touch-action:manipulation}
.matrix-cell-save-btn:hover{background-color:#0284c7}
.matrix-cell-save-btn:active{transform:scale(0.93)}
.matrix-cell-unit-btn{height:1.125rem;display:inline-flex;align-items:center;gap:0.125rem;padding:0 0.3rem;border-radius:0.2rem;background-color:#f1f5f9;color:#475569;font-size:8px;font-weight:700;transition:background-color 120ms;touch-action:manipulation;cursor:pointer}
.matrix-cell-unit-btn:hover{background-color:#e0f2fe;color:#0369a1}
.matrix-cell-unit-btn:active{transform:scale(0.95)}

/* ─── Segment controls (grade / category switchers) ────────────────────── */
.seg-btn{height:1.75rem;border-radius:0.375rem;padding:0 0.75rem;font-size:0.6875rem;font-weight:700;transition:background-color 120ms,color 120ms,box-shadow 120ms;touch-action:manipulation;white-space:nowrap;cursor:pointer}
.seg-btn.active{background-color:#0f172a;color:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.15)}
.seg-btn.inactive{color:#475569}
.seg-btn.inactive:hover{background-color:#e2e8f0;color:#0f172a}

/* ─── Export / action dropdowns ────────────────────────────────────────── */
.matrix-dropdown{position:relative;display:inline-flex}
.matrix-dropdown-menu{position:absolute;top:calc(100% + 4px);right:0;min-width:11rem;background:#fff;border:1px solid #e2e8f0;border-radius:0.5rem;box-shadow:0 8px 24px rgba(0,0,0,0.1);padding:0.25rem;z-index:50;display:none}
.matrix-dropdown-menu.open{display:block}
.matrix-dropdown-menu a,.matrix-dropdown-menu button{display:flex;align-items:center;gap:0.5rem;width:100%;padding:0.375rem 0.625rem;border-radius:0.3rem;font-size:0.6875rem;font-weight:600;color:#1e293b;text-align:left;transition:background-color 100ms;white-space:nowrap;background:none;border:none;cursor:pointer;text-decoration:none}
.matrix-dropdown-menu a:hover,.matrix-dropdown-menu button:hover{background-color:#f1f5f9}
.matrix-dropdown-menu .dd-section{padding:0.125rem 0.625rem 0.25rem;font-size:0.625rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-top:0.25rem}
.matrix-dropdown-menu .dd-sep{height:1px;background:#f1f5f9;margin:0.25rem 0}

/* ─── Table & responsive containment ───────────────────────────────────── */
.matrix-table-wrap{width:100%;overflow-x:auto;overflow-y:visible;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain}
.matrix-table-wrap table{min-width:680px;width:100%;border-collapse:collapse}

/* ─── Status pill ───────────────────────────────────────────────────────── */
.status-pill{display:inline-flex;align-items:center;gap:0.375rem;border-radius:9999px;padding:0.1875rem 0.625rem;font-size:0.6875rem;font-weight:700;line-height:1}
.status-pill.published{background:#d1fae5;color:#065f46}
.status-pill.unpublished{background:#fef3c7;color:#92400e}

/* ─── Page-level overflow guard ────────────────────────────────────────── */
.matrix-page-root{min-width:0;width:100%;overflow-x:hidden}

/* ─── Category chip strip ───────────────────────────────────────────────── */
.cat-chip{display:inline-flex;align-items:center;gap:0.25rem;height:1.625rem;border-radius:9999px;padding:0 0.625rem;font-size:0.6875rem;font-weight:600;transition:background-color 120ms,color 120ms;cursor:pointer;border:1px solid transparent;touch-action:manipulation}
.cat-chip.selected{background:#0f172a;color:#fff;border-color:#0f172a}
.cat-chip.unselected{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
.cat-chip.unselected:hover{background:#e2e8f0;color:#0f172a}

/* ─── Responsive layout helpers ─────────────────────────────────────────── */
@media(max-width:639px){
  .matrix-controls-grid{grid-template-columns:1fr}
  .matrix-action-row{flex-wrap:wrap}
}
@media(min-width:640px) and (max-width:1023px){
  .matrix-controls-grid{grid-template-columns:1fr 1fr}
}
@media(min-width:1024px){
  .matrix-controls-grid{grid-template-columns:1fr 1fr auto}
}

/* ─── Week nav display ───────────────────────────────────────────────────── */
.week-badge{display:inline-flex;align-items:center;gap:0.375rem;height:2rem;padding:0 0.75rem;border-radius:0.5rem;border:1px solid #e2e8f0;background:#f8fafc;font-size:0.6875rem;font-weight:700;color:#334155;white-space:nowrap}

/* ─── Action buttons unified height ────────────────────────────────────── */
.mx-btn{height:2rem;display:inline-flex;align-items:center;justify-content:center;gap:0.375rem;border-radius:0.5rem;padding:0 0.75rem;font-size:0.6875rem;font-weight:700;transition:background-color 120ms,border-color 120ms,transform 80ms;touch-action:manipulation;white-space:nowrap;cursor:pointer;border:1px solid transparent;text-decoration:none}
.mx-btn:active{transform:scale(0.96)}
.mx-btn-primary{background:#0f172a;color:#fff}
.mx-btn-primary:hover{background:#1e293b}
.mx-btn-accent{background:#0284c7;color:#fff}
.mx-btn-accent:hover{background:#0369a1}
.mx-btn-ghost{background:#fff;color:#334155;border-color:#e2e8f0}
.mx-btn-ghost:hover{background:#f8fafc}
.mx-btn-success{background:#059669;color:#fff}
.mx-btn-success:hover{background:#047857}
.mx-btn-warning{background:#d97706;color:#fff}
.mx-btn-warning:hover{background:#b45309}
.mx-btn-danger-ghost{background:#fff5f5;color:#b91c1c;border-color:#fecaca}
.mx-btn-danger-ghost:hover{background:#fee2e2}
.mx-btn-sm{height:1.75rem;padding:0 0.625rem;font-size:0.625rem}

/* ─── Table col/row accents ─────────────────────────────────────────────── */
.col-today{background-color:rgba(224,242,254,0.5)}
.col-today-header{background:#bae6fd!important;color:#0c4a6e!important}
.col-prev{background-color:rgba(255,251,235,0.5)}
.col-prev-header{background:#fef3c7!important;color:#713f12!important}
.row-approved{background-color:rgba(240,253,244,0.5)}

/* ─── Table sticky shadow ───────────────────────────────────────────────── */
.sticky-shadow{box-shadow:2px 0 6px -2px rgba(0,0,0,0.07)}
</style>
@endpush

@section('content')
{{-- SVG sprite for reused icons --}}
<svg class="hidden" aria-hidden="true">
    <defs>
        <symbol id="mx-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
        </symbol>
        <symbol id="mx-chevron-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
        </symbol>
        <symbol id="mx-chevron-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
        </symbol>
        <symbol id="mx-chevron-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
        </symbol>
        <symbol id="mx-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
        </symbol>
        <symbol id="mx-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
        </symbol>
        <symbol id="mx-export" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </symbol>
        <symbol id="mx-more" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/>
        </symbol>
        <symbol id="mx-whatsapp" viewBox="0 0 24 24" fill="currentColor">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </symbol>
    </defs>
</svg>

@php
    $canEditPrices = auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('admin');
    $currentCarbonDate = \Illuminate\Support\Carbon::parse($purchaseDate);
    $prevDayDate = $currentCarbonDate->copy()->subDay()->toDateString();
    $nextDayDate = $currentCarbonDate->copy()->addDay()->toDateString();
    $prevDayWeekStart = $currentCarbonDate->copy()->subDay()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString();
    $nextDayWeekStart = $currentCarbonDate->copy()->addDay()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString();
    $categoryIds = $categoryIds ?? ($categoryId ? [(int)$categoryId] : []);
    $categoryParams = !empty($categoryIds) ? ['category_ids' => $categoryIds] : [];
    $matrixExportParams = array_merge([
        'date'           => $purchaseDate,
        'search'         => $search,
        'matrix_category'=> $matrixCategory,
        'quality_grade'  => $qualityGrade ?? 'A',
        'week_start'     => $weekStartDate,
    ], $categoryParams);
    $totalProducts = isset($paginatedProducts) ? $paginatedProducts->total() : count($matrixProducts);
    $isAllCatsSelected = empty($categoryIds);
@endphp

<div class="matrix-page-root space-y-3">

    {{-- ═══════════════════════════════════════════════════════════
         SECTION A — Page header
    ═══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-center justify-between gap-2 min-w-0">
        <div class="min-w-0">
            <h1 class="text-base font-bold text-slate-900 leading-tight">Selling Price Matrix</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage daily selling prices by product grade and customer group.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            {{-- Publish toggle --}}
            <form method="POST" action="{{ route('purchasing.prices.toggle-publish') }}" id="matrix-toggle-publish-form" class="contents">
                @csrf
                <input type="hidden" name="date" value="{{ $purchaseDate }}">
                <input type="hidden" name="is_published" value="{{ $isPublished ? '0' : '1' }}">
                <button type="submit" class="mx-btn {{ $isPublished ? 'mx-btn-success' : 'mx-btn-warning' }}"
                        title="{{ $isPublished ? 'Click to unpublish prices' : 'Publish daily prices to shop incharges' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $isPublished ? 'bg-white animate-pulse' : 'bg-amber-200' }}"></span>
                    <span>{{ $isPublished ? 'Published' : 'Publish Prices' }}</span>
                </button>
            </form>
            {{-- Proposal Board --}}
            <a href="{{ route('purchasing.prices.index', ['date' => $purchaseDate]) }}"
               class="mx-btn mx-btn-ghost">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/></svg>
                Proposal Board
            </a>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         SECTION B+C — Filter control card
    ═══════════════════════════════════════════════════════════ --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <form method="GET" action="{{ route('purchasing.prices.matrix.index') }}" id="matrix-filter-form">
            <input type="hidden" name="matrix_category" id="filter-matrix-category" value="{{ $matrixCategory }}">
            <input type="hidden" name="quality_grade" id="matrix-filter-quality-grade" value="{{ $qualityGrade ?? 'A' }}">
            <input type="hidden" name="week_start" id="filter-week-start" value="{{ $weekStartDate }}">
            <div id="category-tags-hidden-inputs">
                @foreach ($categoryIds as $cId)
                    <input type="hidden" name="category_ids[]" value="{{ $cId }}">
                @endforeach
            </div>

            {{-- ── Row 1: Selectors ──────────────────────────── --}}
            <div class="px-3 pt-3 pb-2 border-b border-slate-100 grid matrix-controls-grid gap-3 items-end">

                {{-- Product search --}}
                <div class="min-w-0">
                    <label for="search" class="block text-[10px] font-semibold text-slate-500 mb-1">Product Search</label>
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none"><use href="#mx-search"/></svg>
                        <input id="search" name="search" value="{{ $search }}"
                               placeholder="Search product or SKU…"
                               autocomplete="off"
                               class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-2 focus:ring-sky-200 focus:outline-none transition touch-manipulation min-w-0">
                    </div>
                </div>

                {{-- Business date --}}
                <div class="min-w-0">
                    <label for="date" class="block text-[10px] font-semibold text-slate-500 mb-1">Business Date</label>
                    <div class="flex items-center rounded-lg border border-slate-200 bg-white overflow-hidden h-8 min-w-0">
                        <a href="{{ route('purchasing.prices.matrix.index', array_merge(['date' => $prevDayDate, 'search' => $search, 'matrix_category' => $matrixCategory, 'quality_grade' => $qualityGrade ?? 'A', 'week_start' => $prevDayWeekStart], $categoryParams)) }}"
                           aria-label="Previous day"
                           class="w-8 h-8 flex-shrink-0 inline-flex items-center justify-center bg-slate-50 text-slate-500 hover:bg-sky-600 hover:text-white transition">
                            <svg class="w-3.5 h-3.5"><use href="#mx-chevron-left"/></svg>
                        </a>
                        <input id="date" type="date" name="date" value="{{ $purchaseDate }}"
                               onchange="this.form.submit()"
                               class="flex-1 min-w-0 h-8 border-0 bg-white px-1 text-center text-xs font-bold text-sky-800 focus:ring-0 focus:outline-none touch-manipulation">
                        <a href="{{ route('purchasing.prices.matrix.index', array_merge(['date' => $nextDayDate, 'search' => $search, 'matrix_category' => $matrixCategory, 'quality_grade' => $qualityGrade ?? 'A', 'week_start' => $nextDayWeekStart], $categoryParams)) }}"
                           aria-label="Next day"
                           class="w-8 h-8 flex-shrink-0 inline-flex items-center justify-center bg-slate-50 text-slate-500 hover:bg-sky-600 hover:text-white transition">
                            <svg class="w-3.5 h-3.5"><use href="#mx-chevron-right"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Apply + secondary actions --}}
                <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                    <button type="submit" class="mx-btn mx-btn-primary">
                        Apply
                    </button>
                    @if ($canEditPrices)
                        <button type="submit" form="matrix-fill-forward-form"
                                data-submit-label="Fill Missing"
                                class="mx-btn mx-btn-ghost">
                            Fill Missing
                        </button>
                    @endif
                    {{-- Export dropdown --}}
                    <div class="matrix-dropdown">
                        <button type="button" onclick="toggleDropdown('dd-export')"
                                class="mx-btn mx-btn-ghost" aria-haspopup="true" aria-expanded="false" id="dd-export-btn">
                            <svg class="w-3.5 h-3.5"><use href="#mx-export"/></svg>
                            Export
                            <svg class="w-3 h-3 text-slate-400"><use href="#mx-chevron-down"/></svg>
                        </button>
                        <div id="dd-export" class="matrix-dropdown-menu" role="menu">
                            <div class="dd-section">Today</div>
                            <a href="{{ route('purchasing.prices.matrix.export.whatsapp', array_merge($matrixExportParams, ['scope' => 'today'])) }}" target="_blank" rel="noopener" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-emerald-600"><use href="#mx-whatsapp"/></svg> WhatsApp
                            </a>
                            <a href="{{ route('purchasing.prices.matrix.export.excel', array_merge($matrixExportParams, ['scope' => 'today'])) }}" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/></svg> Excel
                            </a>
                            <a href="{{ route('purchasing.prices.matrix.export.pdf', array_merge($matrixExportParams, ['scope' => 'today'])) }}" target="_blank" rel="noopener" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg> PDF
                            </a>
                            <div class="dd-sep"></div>
                            <div class="dd-section">Full Week</div>
                            <a href="{{ route('purchasing.prices.matrix.export.whatsapp', array_merge($matrixExportParams, ['scope' => 'week'])) }}" target="_blank" rel="noopener" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-emerald-600"><use href="#mx-whatsapp"/></svg> WhatsApp
                            </a>
                            <a href="{{ route('purchasing.prices.matrix.export.excel', array_merge($matrixExportParams, ['scope' => 'week'])) }}" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/></svg> Excel
                            </a>
                            <a href="{{ route('purchasing.prices.matrix.export.pdf', array_merge($matrixExportParams, ['scope' => 'week'])) }}" target="_blank" rel="noopener" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg> PDF
                            </a>
                            <div class="dd-sep"></div>
                            <div class="dd-section">Today Changed</div>
                            <a href="{{ route('purchasing.prices.matrix.export.whatsapp', array_merge($matrixExportParams, ['scope' => 'today_changed'])) }}" target="_blank" rel="noopener" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-emerald-600"><use href="#mx-whatsapp"/></svg> WhatsApp
                            </a>
                            <a href="{{ route('purchasing.prices.matrix.export.excel', array_merge($matrixExportParams, ['scope' => 'today_changed'])) }}" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/></svg> Excel
                            </a>
                            <a href="{{ route('purchasing.prices.matrix.export.pdf', array_merge($matrixExportParams, ['scope' => 'today_changed'])) }}" target="_blank" rel="noopener" role="menuitem">
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg> PDF
                            </a>
                        </div>
                    </div>
                    @if ($canEditPrices)
                        {{-- More Actions dropdown --}}
                        <div class="matrix-dropdown">
                            <button type="button" onclick="toggleDropdown('dd-more')"
                                    class="mx-btn mx-btn-ghost" aria-haspopup="true" id="dd-more-btn">
                                <svg class="w-4 h-4"><use href="#mx-more"/></svg>
                            </button>
                            <div id="dd-more" class="matrix-dropdown-menu" role="menu" style="right:0">
                                <button type="submit" form="matrix-remove-future-form"
                                        onclick="return confirm('Remove all visible product prices after {{ $purchaseDate }}?')"
                                        class="text-rose-700 hover:!bg-rose-50" role="menuitem">
                                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                    Clear Future Prices
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── Row 2: Grade + Price Group + Week Nav ────── --}}
            <div class="px-3 py-2 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-100 min-w-0">
                {{-- Quality Grade --}}
                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-[10px] font-semibold text-slate-500 shrink-0">Quality Grade</span>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 gap-0.5">
                        @foreach (['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C'] as $g => $gLabel)
                            <button type="button" onclick="switchQualityGrade('{{ $g }}')"
                                    id="btn-quality-grade-{{ strtolower($g) }}"
                                    class="seg-btn {{ ($qualityGrade ?? 'A') === $g ? 'active' : 'inactive' }}">
                                {{ $gLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="h-4 w-px bg-slate-200 hidden sm:block shrink-0"></div>

                {{-- Customer Price Group --}}
                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-[10px] font-semibold text-slate-500 shrink-0">Price Group</span>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 gap-0.5">
                        @foreach (['a' => 'Group A', 'b' => 'Group B', 'c' => 'Group C'] as $c => $cLabel)
                            <button type="button" onclick="switchMatrixCategory('{{ $c }}')"
                                    id="btn-matrix-cat-{{ $c }}"
                                    class="seg-btn {{ $matrixCategory === $c ? 'active' : 'inactive' }}">
                                {{ $cLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex-1 min-w-0"></div>

                {{-- 3-day window navigation --}}
                <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                    <a href="{{ route('purchasing.prices.matrix.index', array_merge(['date' => $previousWeekStartDate, 'search' => $search, 'matrix_category' => $matrixCategory, 'quality_grade' => $qualityGrade ?? 'A', 'week_start' => $previousWeekStartDate], $categoryParams)) }}"
                       aria-label="Previous 3 days"
                       class="mx-btn mx-btn-ghost mx-btn-sm">
                        <svg class="w-3.5 h-3.5"><use href="#mx-chevron-left"/></svg>
                        Prev
                    </a>
                    <div class="week-badge">
                        <svg class="w-3.5 h-3.5 text-sky-500 shrink-0"><use href="#mx-calendar"/></svg>
                        <span>{{ \Illuminate\Support\Carbon::parse($weekStartDate)->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($weekEndDate)->format('d M Y') }}</span>
                    </div>
                    <a href="{{ route('purchasing.prices.matrix.index', array_merge(['date' => $nextWeekStartDate, 'search' => $search, 'matrix_category' => $matrixCategory, 'quality_grade' => $qualityGrade ?? 'A', 'week_start' => $nextWeekStartDate], $categoryParams)) }}"
                       aria-label="Next 3 days"
                       class="mx-btn mx-btn-ghost mx-btn-sm">
                        Next
                        <svg class="w-3.5 h-3.5"><use href="#mx-chevron-right"/></svg>
                    </a>
                </div>
            </div>

            {{-- ── Row 3: Category chips ────────────────────── --}}
            <div class="px-3 py-2 flex flex-wrap items-center gap-1.5 min-w-0">
                <span class="text-[10px] font-semibold text-slate-400 shrink-0 mr-0.5">Categories:</span>
                <button type="button" onclick="toggleCategoryTag('all')"
                        class="cat-chip {{ $isAllCatsSelected ? 'selected' : 'unselected' }}">
                    @if ($isAllCatsSelected)
                        <span class="h-1.5 w-1.5 rounded-full bg-sky-400 animate-pulse"></span>
                    @endif
                    All
                </button>
                @foreach ($categories as $cat)
                    @php $isCatSelected = in_array((int) $cat->id, array_map('intval', $categoryIds), true); @endphp
                    <button type="button" onclick="toggleCategoryTag({{ $cat->id }})"
                            class="cat-chip {{ $isCatSelected ? 'selected' : 'unselected' }}">
                        @if ($isCatSelected)
                            <svg class="w-3 h-3 shrink-0"><use href="#mx-check"/></svg>
                        @endif
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </form>
    </section>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('warning'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-xs font-semibold text-amber-800 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800 space-y-1">
            @foreach ($errors->all() as $error)
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Hidden utility forms --}}
    @if ($canEditPrices)
        <form method="POST" action="{{ route('purchasing.prices.matrix.fill-forward') }}" id="matrix-fill-forward-form" class="hidden">
            @csrf
            <input type="hidden" name="date" value="{{ $purchaseDate }}">
            <input type="hidden" name="search" value="{{ $search }}">
            @foreach ($categoryIds as $cId)
                <input type="hidden" name="category_ids[]" value="{{ $cId }}">
            @endforeach
            @if ($categoryId)
                <input type="hidden" name="category_id" value="{{ $categoryId }}">
            @endif
            <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
            <input type="hidden" name="matrix_category" value="{{ $matrixCategory }}">
            <input type="hidden" name="quality_grade" value="{{ $qualityGrade ?? 'A' }}">
            @foreach ($matrixProducts as $prod)
                <input type="hidden" name="all_product_ids[]" value="{{ $prod['product_id'] }}">
            @endforeach
            @foreach ($matrixDates as $dateStr => $dateInfo)
                <input type="hidden" name="all_dates[]" value="{{ $dateStr }}">
            @endforeach
        </form>

        <form method="POST" action="{{ route('purchasing.prices.matrix.remove-future') }}" id="matrix-remove-future-form" class="hidden">
            @csrf
            <input type="hidden" name="date" value="{{ $purchaseDate }}">
            <input type="hidden" name="search" value="{{ $search }}">
            @foreach ($categoryIds as $cId)
                <input type="hidden" name="category_ids[]" value="{{ $cId }}">
            @endforeach
            @if ($categoryId)
                <input type="hidden" name="category_id" value="{{ $categoryId }}">
            @endif
            <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
            <input type="hidden" name="matrix_category" value="{{ $matrixCategory }}">
            <input type="hidden" name="quality_grade" value="{{ $qualityGrade ?? 'A' }}">
            @foreach ($matrixProducts as $prod)
                <input type="hidden" name="all_product_ids[]" value="{{ $prod['product_id'] }}">
            @endforeach
            @foreach ($matrixDates as $dateStr => $dateInfo)
                <input type="hidden" name="all_dates[]" value="{{ $dateStr }}">
            @endforeach
        </form>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         SECTION F — Main Matrix Table
    ═══════════════════════════════════════════════════════════ --}}
    <form method="POST" action="{{ url('/purchasing/prices/matrix') }}"
          class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden min-w-0"
          id="matrix-edit-form">
        <h2 class="sr-only">Daily Price Matrix Table</h2>
        @csrf
        <input type="hidden" name="date" value="{{ $purchaseDate }}">
        <input type="hidden" name="search" value="{{ $search }}">
        @foreach ($categoryIds as $cId)
            <input type="hidden" name="category_ids[]" value="{{ $cId }}">
        @endforeach
        @if ($categoryId)
            <input type="hidden" name="category_id" value="{{ $categoryId }}">
        @endif
        <input type="hidden" name="week_start" value="{{ $weekStartDate }}">
        <input type="hidden" name="matrix_category" id="update-matrix-category" value="{{ $matrixCategory }}">
        <input type="hidden" name="quality_grade" value="{{ $qualityGrade ?? 'A' }}">
        <input type="hidden" name="action" id="matrix-form-action" value="update">
        @foreach ($matrixProducts as $prod)
            <input type="hidden" name="all_product_ids[]" value="{{ $prod['product_id'] }}">
        @endforeach
        @foreach ($matrixDates as $dateStr => $dateInfo)
            <input type="hidden" name="all_dates[]" value="{{ $dateStr }}">
        @endforeach

        {{-- Table toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border-b border-slate-100 bg-slate-50/60">
            <p class="text-xs text-slate-600 flex items-center gap-1.5 min-w-0">
                <span class="inline-block h-2 w-2 rounded-full bg-rose-500 shrink-0"></span>
                <span class="font-medium">{{ $totalProducts }} product{{ $totalProducts !== 1 ? 's' : '' }}</span>
                <span class="text-slate-400">—</span>
                <span>Grade <strong class="text-slate-700">{{ $qualityGrade ?? 'A' }}</strong>, Group <strong class="text-slate-700">{{ strtoupper($matrixCategory) }}</strong></span>
                <span class="text-slate-400 hidden sm:inline">— Red values indicate modified prices</span>
            </p>
            @if ($canEditPrices)
                <button type="submit" data-submit-label="Save Final Price"
                        class="mx-btn mx-btn-accent shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none"><use href="#mx-check"/></svg>
                    Save Final Price
                </button>
            @endif
        </div>

        {{-- Mobile swipe hint --}}
        <div class="sm:hidden flex items-center justify-center gap-1.5 bg-sky-50 border-b border-sky-100 px-3 py-1.5 text-[10px] font-semibold text-sky-700">
            <svg class="w-3 h-3 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
            Scroll left/right to view all dates
        </div>

        {{-- Top scrollbar mirror --}}
        <div class="overflow-x-auto overflow-y-hidden border-b border-slate-100 bg-slate-50 scrollbar-thin scrollbar-thumb-slate-300" id="matrix-top-scrollbar" style="touch-action:pan-x">
            <div id="matrix-top-scrollbar-dummy" style="height:6px"></div>
        </div>

        {{-- Table --}}
        <div class="matrix-table-wrap" id="matrix-table-container">
            <table class="w-full text-left text-xs border-collapse" style="min-width:540px">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 text-[10px] font-semibold uppercase tracking-wider">
                        <th scope="col" class="sticky left-0 z-30 bg-slate-50 px-2 py-2 text-center border-r border-slate-200 w-9 min-w-[36px] max-w-[36px]">#</th>
                        <th scope="col" class="sticky left-9 z-30 bg-slate-50 px-2 py-2 text-center border-r border-slate-200 w-20 min-w-[80px] max-w-[80px]">SKU</th>
                        <th scope="col" class="sticky left-[116px] z-30 bg-slate-50 px-2.5 py-2 border-r border-slate-200 w-44 min-w-[176px] max-w-[176px] sticky-shadow">Product</th>
                        @foreach ($matrixDates as $dateStr => $dateInfo)
                            <th scope="col" class="px-2 py-2 text-center border-r border-slate-200 min-w-[96px] {{ $dateInfo['is_selected'] ? 'col-today-header font-bold' : '' }}">
                                <div class="flex flex-col items-center gap-0.5">
                                    <span class="font-bold">{{ $dateInfo['label'] }}</span>
                                    @if ($dateInfo['is_selected'])
                                        <span class="rounded-full bg-sky-700 px-1.5 py-px text-[8px] font-bold text-white uppercase tracking-wide">Today</span>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white text-slate-800">
                    @forelse ($matrixProducts as $prod)
                        <tr class="hover:bg-slate-50/60 transition-colors {{ ($prod['latest_approved_date'] ?? null) !== null ? 'row-approved' : '' }}"
                            data-product-units="{{ json_encode($prod['row_unit_options'] ?? []) }}">

                            {{-- # --}}
                            <td class="sticky left-0 z-20 bg-white px-2 py-1.5 text-center text-[10px] font-semibold text-slate-400 border-r border-slate-100 w-9 min-w-[36px] max-w-[36px]">{{ $prod['sl_no'] }}</td>

                            {{-- SKU --}}
                            <td class="sticky left-9 z-20 bg-white px-2 py-1.5 text-center border-r border-slate-100 w-20 min-w-[80px] max-w-[80px]">
                                <span class="block truncate text-[10px] font-semibold text-slate-600" title="{{ $prod['sku'] }}">{{ $prod['sku'] ?: '—' }}</span>
                            </td>

                            {{-- Product name --}}
                            <td class="sticky left-[116px] z-20 bg-white px-2.5 py-1.5 border-r border-slate-100 w-44 min-w-[176px] max-w-[176px] sticky-shadow">
                                <span class="block font-semibold text-slate-900 text-xs leading-snug truncate" title="{{ $prod['name'] }}">{{ $prod['name'] }}</span>
                                <div class="flex flex-wrap items-center gap-1 mt-0.5">
                                    @if (($prod['latest_approved_date'] ?? null) !== null && ($prod['latest_approved_price'] ?? null) !== null)
                                        <span class="inline-flex items-center gap-0.5 rounded border border-emerald-200 bg-emerald-50 px-1 py-px text-[9px] font-semibold text-emerald-700">
                                            <svg class="h-2 w-2 text-emerald-600" fill="none"><use href="#mx-check"/></svg>
                                            {{ number_format($prod['latest_approved_price'], 2) }}
                                            <span class="text-emerald-500">({{ \Illuminate\Support\Carbon::parse($prod['latest_approved_date'])->format('d M') }})</span>
                                        </span>
                                    @endif
                                    @if ($prod['sku'])
                                        <span class="text-[9px] text-slate-400 font-medium">{{ strtoupper($prod['unit'] ?: 'KG') }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Price cells --}}
                            @foreach ($matrixDates as $dateStr => $dateInfo)
                                @php
                                    $cellData    = $prod['prices'][$dateStr] ?? null;
                                    $priceValA   = $cellData['price_a'] ?? null;
                                    $priceValB   = $cellData['price_b'] ?? null;
                                    $priceValC   = $cellData['price_c'] ?? null;
                                    $cellUnit    = strtolower((string) ($cellData['unit'] ?? 'kg'));
                                    $cellUnitDisplay = strtoupper(str_replace('_', ' ', $cellUnit));
                                    $hasChangedA = $cellData['changed_a'] ?? false;
                                    $hasChangedB = $cellData['changed_b'] ?? false;
                                    $hasChangedC = $cellData['changed_c'] ?? false;
                                    $activeCellVal = match ($matrixCategory) {
                                        'b' => $priceValB !== null ? number_format($priceValB, 2, '.', '') : '',
                                        'c' => $priceValC !== null ? number_format($priceValC, 2, '.', '') : '',
                                        default => $priceValA !== null ? number_format($priceValA, 2, '.', '') : '',
                                    };
                                    $isCellChanged = match ($matrixCategory) {
                                        'b' => $hasChangedB,
                                        'c' => $hasChangedC,
                                        default => $hasChangedA,
                                    };
                                    $isApprovedCell = ($prod['latest_approved_date'] ?? null) === $dateStr;
                                @endphp
                                <td class="p-1.5 border-r border-slate-100 text-center {{ $dateInfo['is_selected'] ? 'col-today' : '' }} {{ $isApprovedCell ? 'bg-emerald-50/60 ring-1 ring-inset ring-emerald-200' : '' }}">
                                    <div class="matrix-cell-container relative space-y-0.5">
                                        <div class="flex items-center gap-0.5">
                                            <input
                                                type="number" step="0.01" min="0"
                                                name="matrix_prices[{{ $prod['product_id'] }}][{{ $dateStr }}]"
                                                data-product-id="{{ $prod['product_id'] }}"
                                                data-date="{{ $dateStr }}"
                                                data-price-a="{{ $priceValA !== null ? number_format($priceValA, 2, '.', '') : '' }}"
                                                data-price-b="{{ $priceValB !== null ? number_format($priceValB, 2, '.', '') : '' }}"
                                                data-price-c="{{ $priceValC !== null ? number_format($priceValC, 2, '.', '') : '' }}"
                                                data-changed-a="{{ $hasChangedA ? '1' : '0' }}"
                                                data-changed-b="{{ $hasChangedB ? '1' : '0' }}"
                                                data-changed-c="{{ $hasChangedC ? '1' : '0' }}"
                                                value="{{ $activeCellVal }}"
                                                onkeydown="if(event.key==='Enter'){event.preventDefault();saveMatrixCell(this.nextElementSibling);}"
                                                @if (!$canEditPrices) readonly @endif
                                                class="matrix-cell-input {{ $isCellChanged ? 'is-changed' : 'text-slate-800' }}"
                                            >
                                            @if ($canEditPrices)
                                                <button type="button" title="Save price" onclick="saveMatrixCell(this)" class="matrix-cell-save-btn">
                                                    <svg class="w-3.5 h-3.5" fill="none"><use href="#mx-check"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                        @if ($canEditPrices)
                                            <input type="hidden" class="matrix-cell-unit" name="matrix_price_units[{{ $prod['product_id'] }}][{{ $dateStr }}]" value="{{ $cellUnit }}">
                                            <button type="button" title="Change unit" onclick="toggleUnitSelector(this)" class="matrix-cell-unit-btn">
                                                <span class="unit-display">{{ $cellUnitDisplay }}</span>
                                                <svg class="w-2.5 h-2.5 text-slate-400" fill="none"><use href="#mx-chevron-down"/></svg>
                                            </button>
                                        @else
                                            <span class="text-[9px] font-medium text-slate-400">{{ $cellUnitDisplay }}</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($matrixDates) + 3 }}" class="py-12 text-center">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <svg class="w-8 h-8 mx-auto text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                    <p class="text-sm font-semibold text-slate-500">No products found</p>
                                    <p class="text-xs text-slate-400">Try adjusting your search or category filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if (isset($paginatedProducts) && $paginatedProducts->hasPages())
            <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-xs text-slate-500">
                    Showing <span class="font-semibold text-slate-700">{{ $paginatedProducts->firstItem() }}</span>
                    to <span class="font-semibold text-slate-700">{{ $paginatedProducts->lastItem() }}</span>
                    of <span class="font-semibold text-slate-700">{{ $paginatedProducts->total() }}</span> products
                </p>
                <div class="matrix-pagination">
                    {{ $paginatedProducts->links() }}
                </div>
            </div>
        @endif

        {{-- Shared unit selector --}}
        <div id="shared-unit-selector" class="unit-selector hidden fixed z-[200] bg-white border border-slate-200 rounded-lg shadow-xl p-1 min-w-[110px] space-y-0.5"></div>
    </form>

</div>

{{-- Loading overlay --}}
<div id="matrix-loading-overlay"
     class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4"
     role="dialog" aria-modal="true" aria-hidden="true" aria-label="Saving prices">
    <div class="w-full max-w-xs rounded-xl bg-white p-6 text-center shadow-2xl border border-slate-200">
        <div class="relative mx-auto h-11 w-11 flex items-center justify-center">
            <div class="absolute inset-0 animate-ping rounded-full bg-sky-400/20"></div>
            <div class="h-9 w-9 animate-spin rounded-full border-4 border-slate-200 border-t-sky-600 border-r-sky-600"></div>
        </div>
        <p class="mt-3 text-sm font-bold text-slate-900">Updating Prices</p>
        <p class="mt-1 text-xs text-slate-500">Please wait…</p>
    </div>
</div>

@once
    @push('scripts')
    <script>
    /* ── Dropdown helpers ─────────────────────────────────────── */
    function toggleDropdown(id) {
        const menu = document.getElementById(id);
        if (!menu) return;
        const isOpen = menu.classList.contains('open');
        // Close all
        document.querySelectorAll('.matrix-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        if (!isOpen) menu.classList.add('open');
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.matrix-dropdown')) {
            document.querySelectorAll('.matrix-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        }
    });

    /* ── Category tag toggle ──────────────────────────────────── */
    function toggleCategoryTag(catId) {
        const container = document.getElementById('category-tags-hidden-inputs');
        const form = document.getElementById('matrix-filter-form');
        if (!container || !form) return;

        if (catId === 'all') {
            container.innerHTML = '';
        } else {
            const catIdNum = Number(catId);
            const current = Array.from(container.querySelectorAll('input[name="category_ids[]"]'))
                .map(inp => Number(inp.value))
                .filter(id => Number.isInteger(id) && id > 0);

            const newSelected = current.includes(catIdNum)
                ? current.filter(id => id !== catIdNum)
                : [...current, catIdNum];

            container.innerHTML = newSelected
                .map(id => `<input type="hidden" name="category_ids[]" value="${id}">`)
                .join('');
        }

        showLoadingOverlay();
        form.submit();
    }

    /* ── State ────────────────────────────────────────────────── */
    let currentMatrixCategory = "{{ $matrixCategory }}";
    let currentQualityGrade   = "{{ $qualityGrade ?? 'A' }}";
    let matrixSaving          = false;
    const matrixLoadingOverlay = document.getElementById('matrix-loading-overlay');

    /* ── Loading overlay ──────────────────────────────────────── */
    function showLoadingOverlay() {
        if (!matrixLoadingOverlay) return;
        matrixLoadingOverlay.classList.remove('hidden');
        matrixLoadingOverlay.classList.add('flex');
        matrixLoadingOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function hideLoadingOverlay() {
        if (!matrixLoadingOverlay) return;
        matrixLoadingOverlay.classList.add('hidden');
        matrixLoadingOverlay.classList.remove('flex');
        matrixLoadingOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    /* ── Quality grade switcher ───────────────────────────────── */
    function switchQualityGrade(grade) {
        const input = document.getElementById('matrix-filter-quality-grade');
        if (input) input.value = grade;

        ['A', 'B', 'C'].forEach(g => {
            const btn = document.getElementById('btn-quality-grade-' + g.toLowerCase());
            if (!btn) return;
            btn.className = 'seg-btn ' + (g === grade ? 'active' : 'inactive');
        });

        const form = document.getElementById('matrix-filter-form');
        if (form) { showLoadingOverlay(); form.submit(); }
    }

    /* ── Price group (matrix category) switcher ───────────────── */
    function switchMatrixCategory(cat) {
        currentMatrixCategory = cat;
        document.getElementById('filter-matrix-category').value = cat;
        const updateInput = document.getElementById('update-matrix-category');
        if (updateInput) updateInput.value = cat;

        ['a', 'b', 'c'].forEach(c => {
            const btn = document.getElementById('btn-matrix-cat-' + c);
            if (!btn) return;
            btn.className = 'seg-btn ' + (c === cat ? 'active' : 'inactive');
        });

        document.querySelectorAll('.matrix-cell-input').forEach(input => {
            const vals = { a: input.dataset.priceA, b: input.dataset.priceB, c: input.dataset.priceC };
            const changed = { a: input.dataset.changedA === '1', b: input.dataset.changedB === '1', c: input.dataset.changedC === '1' };

            input.value = vals[cat] || '';
            input.classList.toggle('is-changed', !!changed[cat]);
            input.classList.toggle('text-slate-800', !changed[cat]);
        });
    }

    /* ── Cell save (AJAX) ─────────────────────────────────────── */
    async function saveMatrixCell(btn) {
        const container = btn.closest('.matrix-cell-container');
        if (!container) return;
        const input       = container.querySelector('.matrix-cell-input');
        const unitInput   = container.querySelector('.matrix-cell-unit');
        const unitDisplay = container.querySelector('.unit-display');
        if (!input) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                       || document.querySelector('input[name="_token"]')?.value || '';

        btn.disabled = true;
        btn.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 018-8"/></svg>';
        btn.classList.add('bg-amber-500');

        try {
            const response = await fetch("{{ route('purchasing.prices.matrix.cell.update') }}", {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    product_id:     input.dataset.productId,
                    date:           input.dataset.date,
                    price_category: currentMatrixCategory,
                    product_grade:  currentQualityGrade,
                    price:          input.value,
                    price_unit:     unitInput ? unitInput.value : null,
                }),
            });

            const data = await response.json();

            if (data.success) {
                input.dataset.priceA = data.price_a !== null ? Number(data.price_a).toFixed(2) : '';
                input.dataset.priceB = data.price_b !== null ? Number(data.price_b).toFixed(2) : '';
                input.dataset.priceC = data.price_c !== null ? Number(data.price_c).toFixed(2) : '';

                if (unitInput && data.price_unit) {
                    const normalized = String(data.price_unit).toLowerCase();
                    unitInput.value = normalized;
                    if (unitDisplay) unitDisplay.textContent = normalized.replace('_', ' ').toUpperCase();
                }

                btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none"><use href="#mx-check"/></svg>';
                btn.className = 'matrix-cell-save-btn bg-emerald-600';
                input.classList.add('bg-emerald-50', 'border-emerald-300');

                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none"><use href="#mx-check"/></svg>';
                    btn.className = 'matrix-cell-save-btn';
                    input.classList.remove('bg-emerald-50', 'border-emerald-300');
                }, 1200);
            } else {
                alert(data.message || 'Error saving price.');
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none"><use href="#mx-check"/></svg>';
                btn.className = 'matrix-cell-save-btn bg-rose-600';
            }
        } catch {
            alert('Network error saving price.');
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none"><use href="#mx-check"/></svg>';
            btn.className = 'matrix-cell-save-btn';
        }
    }

    /* ── Bulk save form ───────────────────────────────────────── */
    document.getElementById('matrix-edit-form')?.addEventListener('submit', function() {
        if (matrixSaving) return;
        matrixSaving = true;
        showLoadingOverlay();
        this.querySelectorAll('button[type="submit"]').forEach(btn => {
            btn.disabled = true;
            const label = btn.dataset.submitLabel || btn.textContent.trim();
            btn.textContent = label + '…';
            btn.classList.add('opacity-70', 'cursor-not-allowed');
        });
    });

    document.getElementById('matrix-filter-form')?.addEventListener('submit', showLoadingOverlay);

    document.getElementById('matrix-fill-forward-form')?.addEventListener('submit', function(e) {
        if (!window.confirm("Fill all missing visible matrix prices from each product's latest approved price? Existing positive prices will not be overwritten.")) {
            e.preventDefault();
            return;
        }
        showLoadingOverlay();
    });

    /* ── Unit selector ────────────────────────────────────────── */
    let activeUnitBtn = null;

    function toggleUnitSelector(btn) {
        const selector = document.getElementById('shared-unit-selector');
        if (!selector) return;

        if (activeUnitBtn === btn && !selector.classList.contains('hidden')) {
            selector.classList.add('hidden');
            activeUnitBtn = null;
            return;
        }

        activeUnitBtn = btn;
        let options = [];
        try { options = JSON.parse(btn.dataset.options || btn.closest('tr')?.dataset.productUnits || '[]'); } catch {}

        selector.innerHTML = options.map(opt =>
            `<button type="button" onclick="selectUnitFromShared('${opt.unit}','${opt.label}')"
                class="w-full text-left px-2 py-1.5 rounded text-[10px] font-semibold text-slate-700 hover:bg-sky-50 hover:text-sky-800 transition">${opt.label}</button>`
        ).join('');

        const rect = btn.getBoundingClientRect();
        selector.style.left = Math.max(8, Math.min(window.innerWidth - 130, rect.left)) + 'px';
        selector.style.top  = (rect.bottom + 4) + 'px';
        selector.classList.remove('hidden');
    }

    function selectUnitFromShared(unit, label) {
        if (!activeUnitBtn) return;
        const container = activeUnitBtn.closest('.matrix-cell-container');
        if (container) {
            const hidden  = container.querySelector('.matrix-cell-unit');
            const display = activeUnitBtn.querySelector('.unit-display');
            if (hidden)  hidden.value = unit;
            if (display) display.textContent = label || unit.toUpperCase();
            const saveBtn = container.querySelector('.matrix-cell-save-btn');
            if (saveBtn) saveMatrixCell(saveBtn);
        }
        document.getElementById('shared-unit-selector')?.classList.add('hidden');
        activeUnitBtn = null;
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.matrix-cell-unit-btn') && !e.target.closest('#shared-unit-selector')) {
            document.getElementById('shared-unit-selector')?.classList.add('hidden');
            activeUnitBtn = null;
        }
    });

    /* ── Unsaved-change protection ────────────────────────────── */
    let hasUnsavedChanges = false;
    document.addEventListener('input', e => {
        if (e.target.classList.contains('matrix-cell-input')) hasUnsavedChanges = true;
    });
    window.addEventListener('beforeunload', e => {
        if (hasUnsavedChanges && !matrixSaving) { e.preventDefault(); e.returnValue = ''; }
    });
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && hasUnsavedChanges && !matrixSaving) {
            const isNav = link.closest('.matrix-pagination') || /[?&](page|week_start|date)=/.test(link.href);
            if (isNav && !confirm('You have unsaved price changes. Leave without saving?')) {
                e.preventDefault(); e.stopPropagation();
            }
        }
    }, true);

    /* ── Top/bottom scrollbar sync ────────────────────────────── */
    const topScroll = document.getElementById('matrix-top-scrollbar');
    const botScroll = document.getElementById('matrix-table-container');
    const topDummy  = document.getElementById('matrix-top-scrollbar-dummy');

    function syncScrollWidth() {
        if (!botScroll || !topDummy) return;
        const table = botScroll.querySelector('table');
        topDummy.style.width = (table ? table.offsetWidth : botScroll.scrollWidth) + 'px';
    }

    if (topScroll && botScroll) {
        let syncTop = false, syncBot = false;
        topScroll.addEventListener('scroll', () => { if (!syncTop) { syncBot = true; botScroll.scrollLeft = topScroll.scrollLeft; } syncTop = false; });
        botScroll.addEventListener('scroll', () => { if (!syncBot) { syncTop = true; topScroll.scrollLeft = botScroll.scrollLeft; } syncBot = false; });
        window.addEventListener('resize', syncScrollWidth);
        setTimeout(syncScrollWidth, 100);
    }
    </script>
    @endpush
@endonce
@endsection
