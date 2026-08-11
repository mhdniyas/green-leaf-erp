<x-layouts.inventory title="Inventory Settings">
    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('inventory.settings.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Batch Sorting</p>
                <h2 class="mt-2 text-xl font-black text-slate-950">Default grade allocation</h2>
                <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">Choose whether a new batch sorting form starts with the entire received quantity assigned to Grade A.</p>

                <label class="mt-6 flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <input type="hidden" name="sort_all_as_grade_a" value="0">
                    <input type="checkbox" name="sort_all_as_grade_a" value="1" @checked(old('sort_all_as_grade_a', $sortAllAsGradeA)) class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>
                        <span class="block text-sm font-black text-slate-900">Sort all as Grade A by default</span>
                        <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">When enabled, “Sort Now” fills the full batch quantity into Grade A. Staff can still adjust the grade breakdown before confirming.</span>
                    </span>
                </label>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-emerald-500">
                        Save Inventory Settings
                    </button>
                </div>
            </section>
        </form>
    </div>
</x-layouts.inventory>
