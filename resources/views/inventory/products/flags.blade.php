<x-layouts.inventory title="Product Flags">

    <x-slot:actions>
        <a href="{{ route('inventory.products.flags') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
            Refresh
        </a>
        <a href="{{ route('inventory.products.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            Add Product
        </a>
    </x-slot:actions>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-6">
            <a href="{{ route('inventory.products.index') }}"
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium whitespace-nowrap pb-3 px-1 border-b-2 text-sm">
                All Products
            </a>
            <a href="{{ route('inventory.products.index', ['status' => 'active']) }}"
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium whitespace-nowrap pb-3 px-1 border-b-2 text-sm">
                Active
            </a>
            <a href="{{ route('inventory.products.index', ['status' => 'inactive']) }}"
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium whitespace-nowrap pb-3 px-1 border-b-2 text-sm">
                Inactive
            </a>
            @if(auth()->user()?->hasRole('admin'))
                <a href="{{ route('inventory.products.trash') }}"
                   class="border-transparent text-gray-500 hover:text-red-600 hover:border-red-300 whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                    Deleted Products
                </a>
                <a href="{{ route('inventory.products.flags') }}"
                   class="border-orange-500 text-orange-600 font-semibold whitespace-nowrap pb-3 px-1 border-b-2 text-sm flex items-center gap-2">
                    <i data-lucide="flag" class="w-3.5 h-3.5"></i>
                    Flags
                    @if($criticalCount > 0)
                        <span class="rounded-full bg-red-100 text-red-700 font-bold px-2 py-0.5 text-xs">{{ $criticalCount }}</span>
                    @elseif($warningCount > 0)
                        <span class="rounded-full bg-amber-100 text-amber-700 font-bold px-2 py-0.5 text-xs">{{ $warningCount }}</span>
                    @else
                        <span class="rounded-full bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 text-xs">All clear</span>
                    @endif
                </a>
            @endif
        </nav>
    </div>

    {{-- Summary header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Product Health Flags</h2>
            <p class="text-xs text-gray-500 mt-0.5">Scanned for {{ $today }}. Fix critical issues before dispatching loadouts.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($criticalCount === 0 && $warningCount === 0 && $infoCount === 0)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-sm font-semibold text-emerald-700">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    All products healthy
                </span>
            @else
                @if($criticalCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 border border-red-200 px-3 py-1.5 text-sm font-semibold text-red-700">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        {{ $criticalCount }} Critical
                    </span>
                @endif
                @if($warningCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1.5 text-sm font-semibold text-amber-700">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                        {{ $warningCount }} Warning
                    </span>
                @endif
                @if($infoCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200 px-3 py-1.5 text-sm font-semibold text-blue-700">
                        <i data-lucide="info" class="w-4 h-4"></i>
                        {{ $infoCount }} Info
                    </span>
                @endif
            @endif
        </div>
    </div>

    @php
        $sections = [
            [
                'id'          => 'base-unit-not-orderable',
                'severity'    => 'critical',
                'icon'        => 'x-circle',
                'title'       => 'Base Unit Not Orderable',
                'description' => 'Product base unit has is_orderable=0. Invoice will throw "cannot be invoiced in kg/piece/etc." and block loadout save.',
                'rows'        => $baseUnitNotOrderable,
                'fix'         => 'Open product → Measures tab → set the base unit to Orderable.',
            ],
            [
                'id'          => 'missing-today-price',
                'severity'    => 'critical',
                'icon'        => 'x-circle',
                'title'       => 'No Approved Price Today (' . $today . ')',
                'description' => 'No approved daily_price_approval row for today. Loadout save and invoice reprice will fail for these products.',
                'rows'        => $missingTodayPrice,
                'fix'         => 'Go to Purchasing → Daily Price Matrix and approve a price, or run: php artisan greenleaf:seed-daily-price-matrix --date=' . $today . ' --force',
            ],
            [
                'id'          => 'price-unit-mismatch',
                'severity'    => 'critical',
                'icon'        => 'x-circle',
                'title'       => 'Price Unit Has No Matching Orderable Product Unit',
                'description' => 'Today\'s price is set in a unit (e.g. "box") but no orderable product unit exists with that code. Invoice cannot calculate the conversion.',
                'rows'        => $priceUnitMismatch,
                'fix'         => 'Add the missing unit to the product Measures, or change the daily price unit to match the base unit.',
            ],
            [
                'id'          => 'no-product-units',
                'severity'    => 'critical',
                'icon'        => 'x-circle',
                'title'       => 'No Product Units Defined',
                'description' => 'Product has no rows in product_units. All conversions and invoicing will fail.',
                'rows'        => $noProductUnits,
                'fix'         => 'Open product → Measures tab → add at least one unit.',
            ],
            [
                'id'          => 'null-base-unit',
                'severity'    => 'critical',
                'icon'        => 'x-circle',
                'title'       => 'NULL Base Unit on Product',
                'description' => 'Product.unit column is NULL. Invoice behaviour is unpredictable.',
                'rows'        => $nullBaseUnit,
                'fix'         => 'Open product → set the base unit field.',
            ],
            [
                'id'          => 'null-price-unit',
                'severity'    => 'warning',
                'icon'        => 'alert-triangle',
                'title'       => 'NULL Price Unit in Today\'s Approval',
                'description' => 'Today\'s price approval has a NULL price_unit. The system defaults to the product base unit — this may not be intended.',
                'rows'        => $nullPriceUnit,
                'fix'         => 'Go to Purchasing → Daily Price Matrix and re-save the price with a unit selected.',
            ],
            [
                'id'          => 'no-price-7-days',
                'severity'    => 'warning',
                'icon'        => 'alert-triangle',
                'title'       => 'No Approved Price in Last 7 Days',
                'description' => 'Suggests the daily price seeder may have missed this product (newly added, or not present on the seeder source date).',
                'rows'        => $noPriceLast7Days,
                'fix'         => 'Go to Purchasing → Daily Price Matrix and approve a price for this product.',
            ],
            [
                'id'          => 'duplicate-units',
                'severity'    => 'warning',
                'icon'        => 'alert-triangle',
                'title'       => 'Duplicate Product Units',
                'description' => 'The same unit code appears more than once for a product. May cause the wrong conversion to be picked up.',
                'rows'        => $duplicateUnits,
                'fix'         => 'Open product → Measures tab → remove the duplicate unit rows.',
            ],
            [
                'id'          => 'price-above-5000',
                'severity'    => 'info',
                'icon'        => 'info',
                'title'       => 'Today\'s Price Above ₹5,000',
                'description' => 'Informational — these products have an approved price above ₹5,000 today. Verify this is correct and not a data entry error (e.g. extra zero typed in).',
                'rows'        => $priceAbove5000,
                'fix'         => 'If the price looks wrong, go to Purchasing → Daily Price Matrix and correct it before loadout.',
            ],
        ];
    @endphp

    <div class="space-y-4">
        @foreach($sections as $section)
            @php $count = $section['rows']->count(); @endphp
            @php
                $severityColors = match($section['severity']) {
                    'critical' => [
                        'border'    => $count > 0 ? 'border-red-200'   : 'border-gray-200',
                        'bg'        => $count > 0 ? 'bg-red-50/40'     : 'bg-white',
                        'hdrBorder' => $count > 0 ? 'border-b border-red-200'   : 'border-b border-gray-100',
                        'iconColor' => $count > 0 ? 'text-red-500'     : 'text-gray-300',
                        'titleColor'=> $count > 0 ? 'text-red-800'     : 'text-gray-600',
                        'badge'     => 'bg-red-100 text-red-700',
                        'fixBg'     => 'bg-red-50 border-b border-red-100',
                        'fixText'   => 'text-red-700',
                        'rowIcon'   => 'bg-red-100 text-red-600',
                    ],
                    'warning' => [
                        'border'    => $count > 0 ? 'border-amber-200' : 'border-gray-200',
                        'bg'        => $count > 0 ? 'bg-amber-50/30'   : 'bg-white',
                        'hdrBorder' => $count > 0 ? 'border-b border-amber-200' : 'border-b border-gray-100',
                        'iconColor' => $count > 0 ? 'text-amber-500'   : 'text-gray-300',
                        'titleColor'=> $count > 0 ? 'text-amber-800'   : 'text-gray-600',
                        'badge'     => 'bg-amber-100 text-amber-700',
                        'fixBg'     => 'bg-amber-50 border-b border-amber-100',
                        'fixText'   => 'text-amber-700',
                        'rowIcon'   => 'bg-amber-100 text-amber-600',
                    ],
                    default => [
                        'border'    => $count > 0 ? 'border-blue-200'  : 'border-gray-200',
                        'bg'        => $count > 0 ? 'bg-blue-50/20'    : 'bg-white',
                        'hdrBorder' => $count > 0 ? 'border-b border-blue-200'  : 'border-b border-gray-100',
                        'iconColor' => $count > 0 ? 'text-blue-500'    : 'text-gray-300',
                        'titleColor'=> $count > 0 ? 'text-blue-800'    : 'text-gray-600',
                        'badge'     => 'bg-blue-100 text-blue-700',
                        'fixBg'     => 'bg-blue-50 border-b border-blue-100',
                        'fixText'   => 'text-blue-700',
                        'rowIcon'   => 'bg-blue-100 text-blue-600',
                    ],
                };
            @endphp

            <div id="{{ $section['id'] }}" class="rounded-2xl border overflow-hidden {{ $severityColors['border'] }} {{ $severityColors['bg'] }}">

                {{-- Section header --}}
                <div class="flex items-center justify-between px-5 py-3.5 {{ $severityColors['hdrBorder'] }}">
                    <div class="flex items-center gap-3">
                        <i data-lucide="{{ $section['icon'] }}" class="w-4 h-4 shrink-0 {{ $severityColors['iconColor'] }}"></i>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold {{ $severityColors['titleColor'] }}">
                                    {{ $section['title'] }}
                                </span>
                                @if($count > 0)
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $severityColors['badge'] }}">
                                        {{ $count }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-semibold flex items-center gap-1">
                                        <i data-lucide="check" class="w-3 h-3"></i> Clear
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">{{ $section['description'] }}</p>
                        </div>
                    </div>
                </div>

                @if($count > 0)
                    {{-- Fix hint --}}
                    <div class="px-5 py-2 {{ $severityColors['fixBg'] }}">
                        <p class="text-xs {{ $severityColors['fixText'] }} flex items-start gap-1.5">
                            <i data-lucide="wrench" class="w-3.5 h-3.5 mt-px shrink-0"></i>
                            <span><span class="font-semibold">How to fix:</span> {{ $section['fix'] }}</span>
                        </p>
                    </div>

                    {{-- Product rows --}}
                    <div class="divide-y divide-gray-100">
                        @foreach($section['rows'] as $row)
                            <div class="flex items-center justify-between px-5 py-3 hover:bg-white/60 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $severityColors['rowIcon'] }}">
                                        <span class="text-xs font-bold">{{ strtoupper(substr($row->name, 0, 2)) }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ $row->name }}</div>
                                        <div class="text-xs text-gray-400 truncate">
                                            SKU: {{ $row->sku ?? '—' }}
                                            @if(isset($row->price_a))
                                                &nbsp;·&nbsp;
                                                A: ₹{{ number_format($row->price_a, 0) }}
                                                &nbsp;B: ₹{{ number_format($row->price_b, 0) }}
                                                &nbsp;C: ₹{{ number_format($row->price_c, 0) }}
                                                &nbsp;({{ strtoupper($row->price_unit ?? '') }})
                                            @else
                                                &nbsp;·&nbsp; {{ $row->detail }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('inventory.products.edit', $row->id) }}"
                                   class="ml-4 shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                                    <i data-lucide="pencil" class="w-3 h-3"></i>
                                    Fix
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Footer note --}}
    <div class="mt-6 rounded-xl bg-slate-50 border border-slate-200 px-5 py-4 flex gap-3">
        <i data-lucide="clock" class="w-4 h-4 text-slate-400 mt-0.5 shrink-0"></i>
        <p class="text-xs text-slate-600">
            <span class="font-semibold">Daily price seeder</span> runs at midnight IST via
            <code class="bg-slate-100 px-1 rounded text-slate-700">greenleaf:seed-daily-price-matrix</code>.
            If <em>No Approved Price Today</em> shows products, your server cron may be down.
            Manually reseed with:
            <code class="bg-slate-100 px-1 rounded text-slate-700">php artisan greenleaf:seed-daily-price-matrix --date={{ $today }} --force</code>
        </p>
    </div>

</x-layouts.inventory>

@push('scripts')
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) { lucide.createIcons(); }
    });
</script>
@endpush
