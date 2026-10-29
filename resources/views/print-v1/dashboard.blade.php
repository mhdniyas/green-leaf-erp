@extends('print-v1.layouts.app')

@section('content')
    <div class="space-y-8">
        {{-- Top Bar: Date Selector & Info --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Printing Operations Desk</h1>
                <p class="text-sm text-slate-500 mt-1">Clean, deterministic printing outputs powered by centralized section settings.</p>
            </div>

            <form method="GET" action="{{ route('print.dashboard') }}" class="flex items-center gap-3">
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 shadow-2xs">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Date:</span>
                    <input type="date" name="date" value="{{ $date }}"
                           class="bg-transparent border-none text-sm font-black text-slate-900 focus:outline-hidden focus:ring-0 cursor-pointer"
                           onchange="this.form.submit()">
                </div>
                <noscript><button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold">Go</button></noscript>
            </form>
        </div>

        {{-- 4 Main Printing Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- 1. Sort Print --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between hover:border-indigo-300 transition group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-black">
                                01
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-900 group-hover:text-indigo-600 transition">Sort Print</h3>
                                <p class="text-xs text-slate-500">Shop-wise sorting matrix across products & categories.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                            A4 Landscape
                        </span>
                    </div>

                    {{-- Settings Snapshot --}}
                    <div class="bg-slate-50 rounded-xl p-3 text-xs space-y-1.5 border border-slate-100">
                        <div class="flex justify-between text-slate-600">
                            <span>Grades:</span>
                            <span class="font-bold text-slate-900">{{ implode(', ', $sortSettings['grades'] ?? ['A']) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Empty Shops:</span>
                            <span class="font-bold {{ ($sortSettings['skip_empty_shops'] ?? true) ? 'text-emerald-700' : 'text-slate-900' }}">
                                {{ ($sortSettings['skip_empty_shops'] ?? true) ? 'Auto-Skipped (Zero Waste)' : 'Shown' }}
                            </span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Item Order:</span>
                            <span class="font-bold text-slate-900">{{ ($sortSettings['code_sort'] ?? true) ? 'By Item SKU' : 'By Name' }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('print.settings.index', ['tab' => 'sort-sheet']) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                        Configure Settings &rarr;
                    </a>
                    <a href="{{ route('print.sort-sheet', ['date' => $date]) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition shadow-sm">
                        <span>Print Sort Sheet</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            {{-- 2. Selection --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between hover:border-indigo-300 transition group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-black">
                                02
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-900 group-hover:text-indigo-600 transition">Selection List</h3>
                                <p class="text-xs text-slate-500">Shop-wise sequential picking and packing list.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                            A4 Portrait
                        </span>
                    </div>

                    {{-- Settings Snapshot --}}
                    <div class="bg-slate-50 rounded-xl p-3 text-xs space-y-1.5 border border-slate-100">
                        <div class="flex justify-between text-slate-600">
                            <span>Grades:</span>
                            <span class="font-bold text-slate-900">{{ implode(', ', $selectionSettings['grades'] ?? ['A', 'B', 'C', 'U']) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Paging:</span>
                            <span class="font-bold text-slate-900">{{ $selectionSettings['items_per_page'] ?? 36 }} items per page</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Empty Shops:</span>
                            <span class="font-bold text-emerald-700">Auto-Skipped</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('print.settings.index', ['tab' => 'selection']) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                        Configure Settings &rarr;
                    </a>
                    <a href="{{ route('print.selection', ['date' => $date]) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition shadow-sm">
                        <span>Print Selection</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            {{-- 3. Segregation Grid --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between hover:border-indigo-300 transition group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-black">
                                03
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-900 group-hover:text-indigo-600 transition">Segregation Grid</h3>
                                <p class="text-xs text-slate-500">High-contrast product cards with shop warehouse tags.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                            A4 Landscape
                        </span>
                    </div>

                    {{-- Settings Snapshot --}}
                    <div class="bg-slate-50 rounded-xl p-3 text-xs space-y-1.5 border border-slate-100">
                        <div class="flex justify-between text-slate-600">
                            <span>Grades:</span>
                            <span class="font-bold text-slate-900">{{ implode(', ', $gridSettings['grades'] ?? ['A', 'B', 'C', 'U']) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Paging:</span>
                            <span class="font-bold text-slate-900">{{ $gridSettings['items_per_page'] ?? 9 }} items per card grid</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Empty Shops:</span>
                            <span class="font-bold text-emerald-700">Auto-Skipped</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('print.settings.index', ['tab' => 'segregation-grid']) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                        Configure Settings &rarr;
                    </a>
                    <a href="{{ route('print.segregation-grid', ['date' => $date]) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition shadow-sm">
                        <span>Print Grid</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>

            {{-- 4. Shop Wise Portrait --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between hover:border-indigo-300 transition group">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-black">
                                04
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-900 group-hover:text-indigo-600 transition">Shop Wise Portrait</h3>
                                <p class="text-xs text-slate-500">Dedicated page per shop with item verification columns.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                            A4 Portrait
                        </span>
                    </div>

                    {{-- Settings Snapshot --}}
                    <div class="bg-slate-50 rounded-xl p-3 text-xs space-y-1.5 border border-slate-100">
                        <div class="flex justify-between text-slate-600">
                            <span>Grades:</span>
                            <span class="font-bold text-slate-900">{{ implode(', ', $portraitSettings['grades'] ?? ['A', 'B', 'C', 'U']) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Layout:</span>
                            <span class="font-bold text-slate-900">1 Shop Per Page</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Empty Shops:</span>
                            <span class="font-bold text-emerald-700">Auto-Skipped</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('print.settings.index', ['tab' => 'shop-wise-portrait']) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                        Configure Settings &rarr;
                    </a>
                    <a href="{{ route('print.shop-wise-portrait', ['date' => $date]) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition shadow-sm">
                        <span>Print Portrait</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Presets & Batches Section --}}
        @if($presets->isNotEmpty() || $batches->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Saved Presets & Batch Printing</h3>
                        <p class="text-xs text-slate-500">Run quick prints with pre-configured category, warehouse and grade combinations.</p>
                    </div>
                    <a href="{{ route('print.settings.index', ['tab' => 'presets']) }}" class="text-xs font-semibold text-indigo-600 hover:underline">
                        Manage Presets &rarr;
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($presets->take(6) as $preset)
                        <div class="border border-slate-200 rounded-xl p-3 flex items-center justify-between bg-slate-50/50">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-900 truncate">{{ $preset->name }}</p>
                                <p class="text-[11px] text-slate-500 uppercase font-semibold">{{ str_replace('-', ' ', $preset->section) }}</p>
                            </div>
                            <a href="{{ route('print.'.$preset->section, ['preset' => $preset->uuid, 'date' => $date]) }}" target="_blank"
                               class="ml-3 shrink-0 px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-300 transition">
                                Print
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
