<x-dynamic-component component="layouts.printing" title="Custom Presets Manager">
    <div class="max-w-[1600px] mx-auto space-y-6">

        {{-- Alerts --}}
        @if(session('success'))
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-xs font-bold text-emerald-900 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                {{ session('success') }}
            </span>
        </div>
        @endif

        {{-- Header Card --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    Custom Presets & Batches Manager
                </h1>
                <p class="text-xs text-slate-500 mt-1 ml-[52px]">
                    Create, edit, save preset batches, and print multiple custom order presets in order.
                </p>
            </div>
            <a href="#create-preset-card"
               class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-md transition flex items-center gap-2 shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create New Custom Preset
            </a>
        </div>

        {{-- Saved Preset Batches Card --}}
        @if(isset($presetBatches) && $presetBatches->isNotEmpty())
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <h2 class="text-sm font-black text-slate-900 tracking-tight flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 18.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                </svg>
                Saved Preset Batches (1-Click Batch Printing)
            </h2>
            <div class="flex flex-wrap items-center gap-3">
                @foreach($presetBatches as $batch)
                <div class="inline-flex items-center gap-2 bg-purple-50 border border-purple-200/90 rounded-2xl p-2.5 shadow-sm text-xs font-bold">
                    <span class="text-purple-900 font-black">{{ $batch->name }}</span>
                    <span class="text-[10px] bg-purple-200 text-purple-800 font-extrabold px-1.5 py-0.5 rounded-md">
                        {{ count($batch->preset_ids ?? []) }} Presets
                    </span>
                    <a href="{{ route('sort-sheet.presets.batch-print', ['batch_id' => $batch->uuid, 'date' => date('Y-m-d')]) }}" target="_blank"
                       class="px-3 py-1.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white text-[11px] font-bold shadow-sm transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 18.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        Print Batch
                    </a>
                    <form method="POST" action="{{ route('sort-sheet.presets.batches.destroy', $batch) }}" class="inline ml-1" onsubmit="return confirm('Delete batch \'{{ $batch->name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-1 text-purple-400 hover:text-red-600 transition" title="Delete batch">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Presets Table & Batch Print Card --}}
        <form method="GET" action="{{ route('sort-sheet.presets.batch-print') }}" target="_blank" id="batch-print-form">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-0">
                {{-- Batch Print Action Header Bar --}}
                <div class="px-6 py-3.5 border-b border-slate-200 bg-amber-50/80 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs font-bold text-amber-900">
                        <svg class="w-4 h-4 text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 18.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        <span>Batch Print Multiple Presets in Order</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <label class="text-xs font-bold text-slate-600 flex items-center gap-1.5 mr-2">
                            <span>Target Date:</span>
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold bg-white focus:border-amber-500 focus:outline-none">
                        </label>
                        <button type="button" id="save-batch-btn" disabled
                                class="px-4 py-2 rounded-xl border border-purple-300 bg-purple-50 text-purple-900 text-xs font-bold transition flex items-center gap-1.5 opacity-50 cursor-not-allowed">
                            <svg class="w-4 h-4 text-purple-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Save Selection as Batch
                        </button>
                        <button type="submit" id="batch-print-submit-btn" disabled
                                class="px-5 py-2 rounded-xl bg-slate-300 text-slate-500 text-xs font-bold transition flex items-center gap-2 cursor-not-allowed shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 18.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                            </svg>
                            <span id="batch-btn-text">Print Selected Presets (0)</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="bg-slate-800 text-white text-[10px] font-bold uppercase tracking-wider">
                                <th class="py-3 px-3 w-10 text-center">
                                    <input type="checkbox" id="select-all-presets-chk" class="rounded border-slate-600 text-amber-500 focus:ring-amber-500">
                                </th>
                                <th class="py-3 px-4">Preset Name</th>
                                <th class="py-3 px-4">Default Format</th>
                                <th class="py-3 px-4">Category Print Order</th>
                                <th class="py-3 px-4">Page Breaks</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            @forelse($presets as $preset)
                            @php
                                $catIds = $preset->category_ids ?? [];
                                $presetCategories = collect($catIds)
                                    ->map(fn($id) => $categories->firstWhere('id', $id))
                                    ->filter();

                                $routeName = match($preset->surface) {
                                    'segregation' => 'segregation.index',
                                    'portrait' => 'segregation.shop-wise-portrait',
                                    'wide' => 'segregation.shop-wise-wide',
                                    'grid' => 'segregation.grid',
                                    default => 'sort-sheet.index',
                                };

                                $queryArray = array_filter([
                                    'warehouse_id' => $preset->warehouse_id,
                                    'price_group_id' => $preset->price_group_id,
                                    'shop_id' => $preset->shop_id,
                                    'separate_category_pages' => $preset->separate_category_pages ? 1 : null,
                                ]);
                            @endphp
                            <tr class="preset-row hover:bg-slate-50 transition-all duration-200" data-preset-uuid="{{ $preset->uuid }}">
                                <td class="py-3.5 px-3 text-center">
                                    <input type="checkbox" name="preset_ids[]" value="{{ $preset->uuid }}" class="preset-batch-checkbox rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-black text-slate-900 text-sm flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                        </svg>
                                        {{ $preset->name }}
                                    </div>
                                    @if($preset->warehouse || $preset->shop)
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $preset->warehouse ? 'Warehouse: '.$preset->warehouse->name : '' }}
                                        {{ $preset->shop ? ' Shop: '.$preset->shop->name : '' }}
                                    </div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[10px] font-bold capitalize uppercase tracking-wider
                                        {{ $preset->surface === 'portrait' ? 'bg-cyan-100 text-cyan-900 border border-cyan-200' : '' }}
                                        {{ $preset->surface === 'wide' ? 'bg-sky-100 text-sky-900 border border-sky-200' : '' }}
                                        {{ $preset->surface === 'grid' ? 'bg-indigo-100 text-indigo-900 border border-indigo-200' : '' }}
                                        {{ in_array($preset->surface, ['sort-sheet', 'segregation']) ? 'bg-emerald-100 text-emerald-900 border border-emerald-200' : '' }}
                                    ">
                                        {{ str_replace('-', ' ', $preset->surface) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($presetCategories->isNotEmpty())
                                    <div class="flex flex-wrap items-center gap-1 max-w-md">
                                        @foreach($presetCategories as $idx => $cat)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-900 border border-emerald-200">
                                            <span class="text-[9px] font-black text-emerald-600">#{{ $idx + 1 }}</span> {{ $cat->name }}
                                        </span>
                                        @endforeach
                                    </div>
                                    @else
                                    <span class="text-slate-400 italic text-[11px]">All Categories</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    @if($preset->separate_category_pages)
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-200 rounded-lg px-2 py-0.5">
                                        Every Category New Page
                                    </span>
                                    @elseif(!empty($preset->page_break_category_ids))
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-200 rounded-lg px-2 py-0.5">
                                        {{ count($preset->page_break_category_ids) }} Custom Breaks
                                    </span>
                                    @else
                                    <span class="text-slate-400 text-[11px]">Continuous</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="GET" action="{{ route($routeName) }}" class="inline">
                                            @foreach($queryArray as $k => $v)
                                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                            @endforeach
                                            @foreach($catIds as $cid)
                                                <input type="hidden" name="category_ids[]" value="{{ $cid }}">
                                            @endforeach
                                            @foreach(($preset->page_break_category_ids ?? []) as $pbcid)
                                                <input type="hidden" name="page_break_category_ids[]" value="{{ $pbcid }}">
                                            @endforeach
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                                </svg>
                                                Run
                                            </button>
                                        </form>

                                        <button type="button" class="preset-move-up-btn p-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition {{ $loop->first ? 'hidden' : '' }}" title="Move Up in sequence">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                            </svg>
                                        </button>

                                        <button type="button" class="preset-move-down-btn p-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition {{ $loop->last ? 'hidden' : '' }}" title="Move Down in sequence">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <a href="{{ route('sort-sheet.presets.edit', $preset) }}"
                                           class="p-1.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                                           title="Edit preset">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </a>

                                        <form method="POST" action="{{ route('sort-sheet.presets.destroy', $preset) }}" class="inline" onsubmit="return confirm('Delete preset \'{{ $preset->name }}\'?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition" title="Delete preset">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 font-medium">
                                    No custom order presets saved yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        {{-- Create Preset Form Card --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-6" id="create-preset-card">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create New Custom Preset
                </h2>
                <p class="text-xs text-slate-500 mt-1">Configure your preset name, category print priority order, and layout options.</p>
            </div>

            <form method="POST" action="{{ route('sort-sheet.presets.store') }}" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Preset Name *</label>
                        <input type="text" name="name" placeholder="e.g. Daily Priority Vegetables" required
                               class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Default Format *</label>
                        <select name="surface" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="sort-sheet">Sort Sheet</option>
                            <option value="segregation">Selection</option>
                            <option value="portrait">Shop Wise Portrait</option>
                            <option value="wide">Shop Wise Wide</option>
                            <option value="grid">Segregate Grid</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Warehouse (Optional)</label>
                        <select name="warehouse_id" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Category Priority Selector --}}
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Category Print Sequence (Select in Order of Priority)
                    </label>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                        <p class="text-[11px] text-slate-500 font-medium">Click categories to select/re-order. Number badges (#1, #2, #3...) show the exact print priority sequence.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2" id="create-preset-category-selector">
                            @foreach($categories as $cat)
                            <div class="preset-cat-card flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-emerald-400 transition text-xs font-semibold select-none" data-cat-id="{{ $cat->id }}">
                                <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" class="preset-cat-checkbox hidden">
                                <span class="preset-cat-badge flex min-w-7 h-5 px-1 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-[10px] font-black text-slate-400"></span>
                                <span class="truncate pointer-events-none">{{ $cat->name }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Page Break Rules --}}
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Page Break Rules (Custom Category Grouping & Page Starts)
                    </label>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer bg-white px-3.5 py-2 rounded-xl border border-slate-200">
                            <input type="checkbox" name="separate_category_pages" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Global Rule: Start EVERY category on a new page</span>
                        </label>
                        <div class="pt-2 border-t border-slate-200">
                            <p class="text-[11px] font-bold text-slate-600 mb-2">Or select specific categories after which a page break occurs:</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                @foreach($categories as $cat)
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 cursor-pointer hover:border-purple-400 transition">
                                    <input type="checkbox" name="page_break_category_ids[]" value="{{ $cat->id }}" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                                    <span class="truncate">Break after {{ $cat->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-md transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Save Custom Order Preset
                    </button>
                </div>
            </form>
        </div>

        {{-- Tailwind Save Batch Modal Popup --}}
        <div id="save-batch-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-sm p-4 flex items-center justify-center">
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-2xl w-full max-w-md space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-purple-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                        Save Selection as Preset Batch
                    </h3>
                    <button type="button" id="close-batch-modal-btn" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('sort-sheet.presets.batches.store') }}" id="save-batch-modal-form" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                            Batch Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="batch-name-input" placeholder="e.g. Morning Print Batch" required
                               class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
                    </div>

                    <div id="batch-preset-hidden-inputs"></div>

                    <div class="pt-2 flex items-center justify-end gap-3 border-t border-slate-100">
                        <button type="button" id="cancel-batch-modal-btn" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-purple-700 hover:bg-purple-800 text-xs font-bold text-white shadow-md transition flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Save Preset Batch
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.preset-cat-card');
    const selectedSequence = [];

    const updateAllCards = () => {
        cards.forEach((card) => {
            const chk = card.querySelector('.preset-cat-checkbox');
            const badge = card.querySelector('.preset-cat-badge');
            const catId = String(card.dataset.catId);
            const seqIndex = selectedSequence.indexOf(catId);

            if (chk.checked && seqIndex !== -1) {
                card.className = 'preset-cat-card flex items-center gap-2 p-2.5 rounded-xl border border-emerald-500 bg-emerald-50 cursor-pointer hover:border-emerald-600 transition text-xs font-semibold select-none';
                badge.className = 'preset-cat-badge flex min-w-7 h-5 px-1 shrink-0 items-center justify-center rounded-md border border-emerald-600 bg-emerald-600 text-white text-[10px] font-black';
                badge.textContent = `#${seqIndex + 1}`;
            } else {
                card.className = 'preset-cat-card flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-emerald-400 transition text-xs font-semibold select-none';
                badge.className = 'preset-cat-badge flex min-w-7 h-5 px-1 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-[10px] font-black text-slate-400';
                badge.textContent = '';
            }
        });
    };

    cards.forEach((card) => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const chk = card.querySelector('.preset-cat-checkbox');
            const catId = String(card.dataset.catId);

            chk.checked = !chk.checked;

            if (chk.checked) {
                if (!selectedSequence.includes(catId)) {
                    selectedSequence.push(catId);
                }
            } else {
                const idx = selectedSequence.indexOf(catId);
                if (idx !== -1) {
                    selectedSequence.splice(idx, 1);
                }
            }

            updateAllCards();
        });
    });

    // Batch Print Checkbox Handler
    const batchCheckboxes = document.querySelectorAll('.preset-batch-checkbox');
    const selectAllChk = document.getElementById('select-all-presets-chk');
    const submitBtn = document.getElementById('batch-print-submit-btn');
    const saveBatchBtn = document.getElementById('save-batch-btn');
    const btnText = document.getElementById('batch-btn-text');

    const updateBatchBtnState = () => {
        const checkedCount = document.querySelectorAll('.preset-batch-checkbox:checked').length;
        if (btnText) {
            btnText.textContent = `Print Selected Presets (${checkedCount})`;
        }

        if (checkedCount > 0) {
            submitBtn.disabled = false;
            submitBtn.className = 'px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-sm';

            saveBatchBtn.disabled = false;
            saveBatchBtn.className = 'px-4 py-2 rounded-xl border border-purple-300 bg-purple-100 hover:bg-purple-200 text-purple-900 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-sm';
        } else {
            submitBtn.disabled = true;
            submitBtn.className = 'px-5 py-2 rounded-xl bg-slate-300 text-slate-500 text-xs font-bold transition flex items-center gap-2 cursor-not-allowed shadow-sm';

            saveBatchBtn.disabled = true;
            saveBatchBtn.className = 'px-4 py-2 rounded-xl border border-purple-300 bg-purple-50 text-purple-900 text-xs font-bold transition flex items-center gap-1.5 opacity-50 cursor-not-allowed';
        }
    };

    batchCheckboxes.forEach((chk) => {
        chk.addEventListener('change', updateBatchBtnState);
    });

    if (selectAllChk) {
        selectAllChk.addEventListener('change', () => {
            batchCheckboxes.forEach((chk) => chk.checked = selectAllChk.checked);
            updateBatchBtnState();
        });
    }

    // Tailwind Modal Popup Handler for Save Preset Batch
    const saveBatchModal = document.getElementById('save-batch-modal');
    const closeBatchModalBtn = document.getElementById('close-batch-modal-btn');
    const cancelBatchModalBtn = document.getElementById('cancel-batch-modal-btn');
    const batchHiddenContainer = document.getElementById('batch-preset-hidden-inputs');
    const batchNameInput = document.getElementById('batch-name-input');

    if (saveBatchBtn && saveBatchModal) {
        saveBatchBtn.addEventListener('click', () => {
            const checkedBoxes = Array.from(document.querySelectorAll('.preset-batch-checkbox:checked'));
            if (checkedBoxes.length === 0) return;

            batchHiddenContainer.replaceChildren();
            checkedBoxes.forEach((chk) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'preset_ids[]';
                hidden.value = chk.value;
                batchHiddenContainer.appendChild(hidden);
            });

            batchNameInput.value = '';
            saveBatchModal.classList.remove('hidden');
            setTimeout(() => batchNameInput.focus(), 50);
        });

        const closeBatchModal = () => saveBatchModal.classList.add('hidden');
        closeBatchModalBtn?.addEventListener('click', closeBatchModal);
        cancelBatchModalBtn?.addEventListener('click', closeBatchModal);
        saveBatchModal.addEventListener('click', (e) => {
            if (e.target === saveBatchModal) closeBatchModal();
        });
    }

    // Instant Smooth AJAX Reordering Handler
    const presetTbody = document.querySelector('table tbody');
    const updateMoveButtons = () => {
        const rows = document.querySelectorAll('.preset-row');
        rows.forEach((row, idx) => {
            const upBtn = row.querySelector('.preset-move-up-btn');
            const downBtn = row.querySelector('.preset-move-down-btn');
            if (upBtn) upBtn.classList.toggle('hidden', idx === 0);
            if (downBtn) downBtn.classList.toggle('hidden', idx === rows.length - 1);
        });
    };

    const savePresetOrder = async () => {
        const rows = Array.from(document.querySelectorAll('.preset-row'));
        const presetIds = rows.map(r => r.dataset.presetUuid).filter(Boolean);

        try {
            await fetch("{{ route('sort-sheet.presets.reorder') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ preset_ids: presetIds })
            });
        } catch (e) {
            console.error('Failed to save preset order:', e);
        }
    };

    document.addEventListener('click', (e) => {
        const upBtn = e.target.closest('.preset-move-up-btn');
        const downBtn = e.target.closest('.preset-move-down-btn');

        if (upBtn) {
            e.preventDefault();
            const row = upBtn.closest('.preset-row');
            const prevRow = row?.previousElementSibling;
            if (row && prevRow && prevRow.classList.contains('preset-row')) {
                row.classList.add('bg-amber-100/90');
                presetTbody.insertBefore(row, prevRow);
                updateMoveButtons();
                savePresetOrder();
                setTimeout(() => row.classList.remove('bg-amber-100/90'), 400);
            }
        }

        if (downBtn) {
            e.preventDefault();
            const row = downBtn.closest('.preset-row');
            const nextRow = row?.nextElementSibling;
            if (row && nextRow && nextRow.classList.contains('preset-row')) {
                row.classList.add('bg-amber-100/90');
                presetTbody.insertBefore(nextRow, row);
                updateMoveButtons();
                savePresetOrder();
                setTimeout(() => row.classList.remove('bg-amber-100/90'), 400);
            }
        }
    });

    updateAllCards();
    updateBatchBtnState();
    updateMoveButtons();
});
</script>
@endpush
</x-dynamic-component>
