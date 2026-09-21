@props([
    'name',
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option...',
    'searchable' => true,
    'disabled' => false,
    'class' => '',
])

@php
    $selectId = $id ?? 'custom_select_' . str_replace(['[', ']', '.'], '_', $name) . '_' . uniqid();
    
    // Normalize options into array of ['value' => ..., 'label' => ..., 'subtitle' => ..., 'disabled' => ...]
    $normalizedOptions = [];
    foreach ($options as $key => $opt) {
        if (is_array($opt)) {
            $normalizedOptions[] = [
                'value' => (string) ($opt['value'] ?? $opt['id'] ?? $key),
                'label' => (string) ($opt['label'] ?? $opt['name'] ?? $opt['title'] ?? $opt['value'] ?? $key),
                'subtitle' => $opt['subtitle'] ?? null,
                'disabled' => (bool) ($opt['disabled'] ?? false),
            ];
        } elseif (is_object($opt)) {
            $normalizedOptions[] = [
                'value' => (string) ($opt->id ?? $opt->value ?? $key),
                'label' => (string) ($opt->name ?? $opt->title ?? $opt->label ?? $opt->id ?? $key),
                'subtitle' => $opt->subtitle ?? $opt->account_number ?? $opt->code ?? null,
                'disabled' => (bool) ($opt->disabled ?? false),
            ];
        } else {
            $normalizedOptions[] = [
                'value' => (string) $key,
                'label' => (string) $opt,
                'subtitle' => null,
                'disabled' => false,
            ];
        }
    }

    $selectedStr = $selected !== null ? (string) $selected : '';
    $selectedOption = collect($normalizedOptions)->first(fn($o) => (string)$o['value'] === $selectedStr);
    $selectedLabel = $selectedOption ? $selectedOption['label'] : '';
@endphp

<div class="custom-select-wrapper relative w-full {{ $class }}" id="{{ $selectId }}_wrapper" data-custom-select>
    {{-- Hidden Actual Value Input --}}
    <input type="hidden"
           name="{{ $name }}"
           id="{{ $selectId }}"
           value="{{ $selectedStr }}"
           class="custom-select-input"
           @if($disabled) disabled @endif>

    {{-- Visible Trigger Button --}}
    <button type="button"
            class="custom-select-trigger flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-800 shadow-2xs hover:border-slate-400 focus:border-violet-600 focus:outline-hidden focus:ring-2 focus:ring-violet-500/20 transition cursor-pointer disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed"
            @if($disabled) disabled @endif
            aria-haspopup="listbox"
            aria-expanded="false">
        <span class="custom-select-label truncate {{ empty($selectedLabel) ? 'text-slate-400 font-normal' : 'text-slate-900 font-bold' }}">
            {{ !empty($selectedLabel) ? $selectedLabel : $placeholder }}
        </span>
        <i data-lucide="chevron-down" class="custom-select-chevron h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"></i>
    </button>

    {{-- Dropdown Menu --}}
    <div class="custom-select-menu absolute left-0 right-0 z-50 mt-1.5 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl transition-all duration-150 transform opacity-0 scale-95"
         role="listbox">
        @if($searchable && count($normalizedOptions) > 5)
            <div class="relative mb-2 px-1">
                <i data-lucide="search" class="absolute left-3.5 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                <input type="text"
                       placeholder="Search options..."
                       class="custom-select-search w-full rounded-xl border border-slate-200 bg-slate-50 py-1.5 pl-8 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-violet-600 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-violet-500 transition">
            </div>
        @endif

        <div class="custom-select-options max-h-52 overflow-y-auto space-y-0.5 scrollbar-thin">
            {{-- Empty Placeholder Option if selectable --}}
            @if(!empty($placeholder))
                <div class="custom-select-option flex items-center justify-between rounded-xl px-3 py-2 text-xs font-medium text-slate-400 hover:bg-slate-50 cursor-pointer {{ $selectedStr === '' ? 'bg-slate-50 text-violet-700 font-bold' : '' }}"
                     data-value=""
                     data-label="{{ $placeholder }}">
                    <span>{{ $placeholder }}</span>
                    @if($selectedStr === '')
                        <i data-lucide="check" class="h-3.5 w-3.5 text-violet-700 shrink-0"></i>
                    @endif
                </div>
            @endif

            @foreach($normalizedOptions as $option)
                @php
                    $isSelected = ($selectedStr !== '' && (string)$option['value'] === $selectedStr);
                @endphp
                <div class="custom-select-option flex items-center justify-between rounded-xl px-3 py-2 text-xs transition cursor-pointer {{ $option['disabled'] ? 'opacity-40 cursor-not-allowed bg-slate-50 text-slate-400' : 'hover:bg-violet-50/80 hover:text-violet-900 text-slate-800' }} {{ $isSelected ? 'bg-violet-50 text-violet-800 font-black' : 'font-semibold' }}"
                     data-value="{{ $option['value'] }}"
                     data-label="{{ $option['label'] }}"
                     data-subtitle="{{ $option['subtitle'] ?? '' }}"
                     @if($option['disabled']) data-disabled="true" @endif>
                    <div class="flex flex-col truncate pr-2">
                        <span class="custom-option-label truncate">{{ $option['label'] }}</span>
                        @if(!empty($option['subtitle']))
                            <span class="custom-option-subtitle text-[10px] text-slate-400 font-normal truncate">{{ $option['subtitle'] }}</span>
                        @endif
                    </div>
                    @if($isSelected)
                        <i data-lucide="check" class="custom-option-check h-3.5 w-3.5 text-violet-700 shrink-0"></i>
                    @endif
                </div>
            @endforeach

            <div class="custom-select-no-results hidden py-4 text-center text-xs text-slate-400">
                No matching options
            </div>
        </div>
    </div>
</div>
