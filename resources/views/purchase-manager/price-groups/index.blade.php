@extends('purchase-manager.layouts.app')

@section('title', 'Shop Price Categories')
@section('page_title', 'Shop Price Categories')
@section('page_description', 'Assign shops to price groups and control the default margin for each group.')

@section('content')
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($groups as $group)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col justify-between">
                    <form method="POST" action="{{ route('purchasing.price-groups.update', $group) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Category Name</label>
                                <input name="name" value="{{ old("groups.{$group->id}.name", $group->name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-950">
                            </div>
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Margin %</label>
                                <input name="default_margin_percent" type="number" min="0" step="0.01" value="{{ number_format((float) $group->default_margin_percent, 2, '.', '') }}" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-right text-sm font-black text-slate-950">
                            </div>
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-xs font-bold text-slate-600">
                            <input type="checkbox" name="is_active" value="1" @checked($group->is_active) class="rounded border-slate-300">
                            Active
                        </label>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-slate-500">{{ $group->shops_count ?? $group->shops->count() }} shops</span>
                            <div class="flex gap-2">
                                <button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-black text-white">Update</button>
                            </div>
                        </div>
                    </form>
                    @if ($group->shops->isEmpty())
                        <form method="POST" action="{{ route('purchasing.price-groups.destroy', $group) }}" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <button class="w-full rounded-2xl border border-red-100 bg-red-50 px-3 py-2 text-xs font-black text-red-700">Delete {{ $group->display_name }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Create Price Category</h2>
            <form method="POST" action="{{ route('purchasing.price-groups.store') }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_160px_auto] md:items-end">
                @csrf
                <div>
                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Category Name</label>
                    <input name="name" placeholder="A, B, C, VIP" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900">
                </div>
                <div>
                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Margin %</label>
                    <input name="default_margin_percent" type="number" min="0" step="0.01" value="10.00" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-semibold text-slate-900">
                </div>
                <input type="hidden" name="is_active" value="1">
                <button class="rounded-2xl bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950">Create Category</button>
            </form>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-5">
                <h2 class="text-lg font-black text-slate-950">Shop Assignment</h2>
                <p class="mt-1 text-sm text-slate-500">A shop receives prices from one assigned group. It does not carry custom product prices.</p>
            </div>
            <form method="POST" action="{{ route('purchasing.price-groups.assign-shops') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Shop</th>
                                <th class="px-5 py-4">Code</th>
                                <th class="px-5 py-4">Price Category</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($shops as $shop)
                                <tr>
                                    <td class="px-5 py-4 font-black text-slate-950">{{ $shop->name }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-500">{{ $shop->code }}</td>
                                    <td class="px-5 py-4">
                                        <select name="shops[{{ $shop->id }}]" class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900">
                                            <option value="">Default A</option>
                                            @foreach ($groups as $group)
                                                <option value="{{ $group->id }}" @selected($shop->shop_price_group_id === $group->id)>{{ $group->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end border-t border-slate-200 px-5 py-5">
                    <x-purchase-manager.components.action-button type="submit" variant="primary">Save Shop Assignments</x-purchase-manager.components.action-button>
                </div>
            </form>
        </section>
    </div>
@endsection
