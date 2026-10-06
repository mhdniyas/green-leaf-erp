@php
    $settings = $header->entrySettings->values();
    $totalSettings = $settings->count();
    $isIncome = ($type ?? $header->type) === 'income';
    $modeBadge = match($header->cash_flow_mode) {
        'shop_cash' => ['label' => 'Daily Cash', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'petty' => ['label' => 'Petty Cash', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'company', 'company_account' => ['label' => 'Company', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'none' => ['label' => 'Non-Cash', 'class' => 'bg-slate-50 text-slate-700 border-slate-200'],
        default => ['label' => ucfirst($header->cash_flow_mode ?? 'Default'), 'class' => 'bg-slate-50 text-slate-700 border-slate-200']
    };
@endphp

<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs flex flex-col overflow-hidden hover:border-slate-300 transition duration-150 header-card-container" data-header-name="{{ strtolower($header->name) }}">
    <!-- Header Card Top Bar -->
    <div class="p-4 bg-slate-50/70 border-b border-slate-200/70 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-xl {{ $isIncome ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }} flex items-center justify-center shrink-0">
                <i data-lucide="{{ $isIncome ? 'arrow-down-left' : 'arrow-up-right' }}" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
                <h3 class="text-sm font-black text-slate-900 truncate" title="{{ $header->name }}">
                    {{ $header->name }}
                </h3>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold border {{ $modeBadge['class'] }}">
                        {{ $modeBadge['label'] }}
                    </span>
                    <span class="text-[11px] font-medium text-slate-400">
                        {{ $totalSettings }} {{ \Illuminate\Support\Str::plural('category', $totalSettings) }}
                    </span>
                </div>
            </div>
        </div>

        <button type="button"
                onclick="openAddCategoryModal('{{ $header->id }}', '{{ addslashes($header->name) }}', '{{ $header->type }}')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-black text-slate-700 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50/30 transition shadow-2xs shrink-0">
            <i data-lucide="plus" class="w-3.5 h-3.5 text-indigo-600"></i>
            <span>Add Category</span>
        </button>
    </div>

    <!-- Category List -->
    <div class="p-3 divide-y divide-slate-100 flex-1">
        @forelse($settings as $idx => $setting)
            @php
                $entryType = $setting->entryType;
                $isMultiHeader = !empty($entryType) && isset($multiHeaderCodes[$entryType->code]);
                $otherHeaders = $isMultiHeader ? $multiHeaderCodes[$entryType->code] : [];
                $isProtected = $setting->is_readonly
                    || in_array($entryType?->code, ['cash_sales', 'purchase_bill', 'gl_bill', 'salary', 'staff_advance'], true)
                    || $entryType?->system_type === 'system';
            @endphp
            <div class="py-2.5 px-2 flex items-center justify-between gap-3 hover:bg-slate-50/60 rounded-xl transition group category-item-row" data-category-name="{{ strtolower($setting->displayName()) }}" data-category-code="{{ strtolower($entryType?->code ?? '') }}" data-base-name="{{ strtolower($entryType?->name ?? '') }}">
                <!-- Left: Name & Badges -->
                <div class="flex items-center gap-2.5 min-w-0">
                    <!-- Order Buttons -->
                    <div class="flex flex-col gap-0.5 shrink-0 opacity-60 group-hover:opacity-100 transition">
                        @if($idx > 0)
                            @php
                                $upOrder = $settings->pluck('id')->all();
                                $temp = $upOrder[$idx];
                                $upOrder[$idx] = $upOrder[$idx - 1];
                                $upOrder[$idx - 1] = $temp;
                            @endphp
                            <form method="POST" action="{{ route('admin.cashbook.categories.headers.reorder', $header->id) }}" class="inline">
                                @csrf
                                @foreach($upOrder as $orderId)
                                    <input type="hidden" name="order[]" value="{{ $orderId }}">
                                @endforeach
                                <button type="submit" title="Move Up" class="p-0.5 text-slate-400 hover:text-indigo-600 rounded hover:bg-slate-200/60 transition">
                                    <i data-lucide="chevron-up" class="w-3 h-3"></i>
                                </button>
                            </form>
                        @else
                            <span class="w-4 h-3 inline-block"></span>
                        @endif

                        @if($idx < $totalSettings - 1)
                            @php
                                $downOrder = $settings->pluck('id')->all();
                                $temp = $downOrder[$idx];
                                $downOrder[$idx] = $downOrder[$idx + 1];
                                $downOrder[$idx + 1] = $temp;
                            @endphp
                            <form method="POST" action="{{ route('admin.cashbook.categories.headers.reorder', $header->id) }}" class="inline">
                                @csrf
                                @foreach($downOrder as $orderId)
                                    <input type="hidden" name="order[]" value="{{ $orderId }}">
                                @endforeach
                                <button type="submit" title="Move Down" class="p-0.5 text-slate-400 hover:text-indigo-600 rounded hover:bg-slate-200/60 transition">
                                    <i data-lucide="chevron-down" class="w-3 h-3"></i>
                                </button>
                            </form>
                        @else
                            <span class="w-4 h-3 inline-block"></span>
                        @endif
                    </div>

                    <!-- Category Title & Tags -->
                    <div class="min-w-0">
                        <div class="flex items-center flex-wrap gap-1.5">
                            <span class="text-xs font-black {{ $setting->enabled ? 'text-slate-800' : 'text-slate-400 line-through' }} truncate">
                                {{ $setting->displayName() }}
                            </span>

                            @if($isMultiHeader)
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/80 shrink-0"
                                      title="Also appears in: {{ implode(', ', $otherHeaders) }}">
                                    <i data-lucide="layers" class="w-2.5 h-2.5 text-indigo-600"></i>
                                    <span>In {{ count($otherHeaders) }} Headers</span>
                                </span>
                            @endif

                            @if($isProtected)
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200/80 shrink-0"
                                      title="Protected system category">
                                    <i data-lucide="shield-check" class="w-2.5 h-2.5 text-amber-600"></i>
                                    <span>System</span>
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400 font-mono">
                            <span>{{ $entryType?->code ?? 'code-n/a' }}</span>
                            @if($setting->display_name && $entryType && $setting->display_name !== $entryType->name)
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-500 font-sans italic truncate">Base: {{ $entryType->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Right: Status & Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Status Toggle -->
                    <form method="POST" action="{{ route('admin.cashbook.categories.settings.toggle', $setting->id) }}" class="inline">
                        @csrf
                        <button type="submit"
                                title="{{ $setting->enabled ? 'Click to disable' : 'Click to enable' }}"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black transition {{ $setting->enabled ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200 hover:bg-slate-200' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $setting->enabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            <span>{{ $setting->enabled ? 'Active' : 'Disabled' }}</span>
                        </button>
                    </form>

                    <!-- Rename (Only if not readonly) -->
                    @if(!$setting->is_readonly)
                        <button type="button"
                                onclick="openRenameModal('{{ $setting->id }}', '{{ addslashes($setting->displayName()) }}')"
                                title="Rename Category"
                                class="p-1.5 text-slate-400 hover:text-indigo-600 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </button>
                    @endif

                    <!-- Detach / Remove (Only if not protected) -->
                    @if(!$isProtected)
                        <form method="POST" action="{{ route('admin.cashbook.categories.settings.detach', $setting->id) }}"
                              onsubmit="return confirm('Remove category \'{{ addslashes($setting->displayName()) }}\' from header \'{{ addslashes($header->name) }}\'?');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    title="Remove from this header"
                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    @endif

                    <!-- Global Config Link -->
                    <a href="{{ route('admin.cashbook.categories.show', $entryType?->code ?? $setting->entry_type_id) }}"
                       title="View Global Category Configuration"
                       class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        @empty
            <div class="py-8 text-center space-y-2">
                <i data-lucide="inbox" class="w-8 h-8 text-slate-300 mx-auto"></i>
                <p class="text-xs font-bold text-slate-400">No categories attached to this header.</p>
                <button type="button"
                        onclick="openAddCategoryModal('{{ $shop->id }}', '{{ $header->id }}', '{{ addslashes($header->name) }}', '{{ $header->type }}')"
                        class="text-xs font-black text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                    <i data-lucide="plus" class="w-3 h-3"></i>
                    <span>Attach or create category</span>
                </button>
            </div>
        @endforelse
    </div>
</div>
