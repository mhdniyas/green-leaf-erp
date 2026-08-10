@if ($pagination['last_page'] > 1)
    <nav class="flex items-center justify-between border-t border-slate-200 px-3 py-3 sm:px-5" aria-label="Report pagination">
        <p class="text-xs font-semibold text-slate-500">Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }} · {{ $pagination['total'] }} results</p>
        <div class="flex gap-2">
            @if ($pagination['current_page'] > 1)
                <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">Previous</a>
            @endif
            @if ($pagination['current_page'] < $pagination['last_page'])
                <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">Next</a>
            @endif
        </div>
    </nav>
@endif
