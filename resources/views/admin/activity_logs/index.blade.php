<x-layouts.admin title="System Activity Logs">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">System Activity Logs</h1>
                    <p class="text-xs text-slate-500 mt-1">Audit trail of operations, actions, data modifications, and state transitions across the application.</p>
                </div>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 mt-6 pt-6 border-t border-slate-100">
                <div>
                    <label for="start_date" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate->format('Y-m-d') }}"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate->format('Y-m-d') }}"
                           class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                </div>
                <div>
                    <label for="causer_id" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">User (Causer)</label>
                    <select name="causer_id" id="causer_id"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(request('causer_id') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="event" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Event Type</label>
                    <select name="event" id="event"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white shadow-sm">
                        <option value="">All Events</option>
                        @foreach($events as $ev)
                            <option value="{{ $ev }}" @selected(request('event') == $ev)>{{ ucfirst($ev) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold py-2.5 rounded-xl transition-all shadow-sm cursor-pointer flex items-center justify-center gap-1.5 border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A50.06 50.06 0 0112 3z" /></svg>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Activity Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Audit Log Listing</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                            <th class="py-3 px-6">Timestamp</th>
                            <th class="py-3 px-6">Causer (User)</th>
                            <th class="py-3 px-6 text-center">Event</th>
                            <th class="py-3 px-6">Affected Resource</th>
                            <th class="py-3 px-6">Description</th>
                            <th class="py-3 px-6 text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse($activities as $activity)
                            <tr class="hover:bg-slate-50/10">
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    {{ $activity->created_at->format('d M Y') }}
                                    <span class="block text-[10px] text-slate-400 font-normal mt-0.5">{{ $activity->created_at->format('H:i:s') }}</span>
                                </td>
                                <td class="py-4 px-6 font-semibold text-slate-900">
                                    @if($activity->causer)
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 text-[10px] font-black">
                                                {{ strtoupper(substr($activity->causer->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <span>{{ $activity->causer->name }}</span>
                                                <span class="block text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">
                                                    {{ $activity->causer->roles->first() ? $activity->causer->roles->first()->name : 'No Role' }}
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">System / Auto</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @php
                                        $ev = strtolower($activity->event ?? 'action');
                                        $badgeColor = match($ev) {
                                            'created' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'updated' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            'deleted' => 'bg-red-50 text-red-700 border-red-200',
                                            'submitted' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'approved' => 'bg-teal-50 text-teal-700 border-teal-200',
                                            default => 'bg-slate-50 text-slate-700 border-slate-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black border uppercase tracking-wider {{ $badgeColor }}">
                                        {{ $activity->event ?? 'Action' }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-mono text-[10px] text-slate-600">
                                    @if($activity->subject_type)
                                        <span class="font-bold text-slate-800">{{ class_basename($activity->subject_type) }}</span>
                                        <span class="text-slate-400">#{{ $activity->subject_id }}</span>
                                    @else
                                        <span class="text-slate-300 italic">N/A</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 leading-relaxed max-w-xs truncate" title="{{ $activity->description }}">
                                    {{ $activity->description }}
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @php
                                        $props = $activity->properties;
                                        $hasChanges = !empty($props['attributes']) || !empty($props['old']) || !empty($activity->attribute_changes);
                                    @endphp
                                    @if($hasChanges)
                                        <button type="button" onclick="toggleDetails({{ $activity->id }})" class="text-xs text-emerald-600 hover:text-emerald-700 underline font-black cursor-pointer border-0 bg-transparent p-0">
                                            View Changes
                                        </button>
                                    @else
                                        <span class="text-slate-300 italic">-</span>
                                    @endif
                                </td>
                            </tr>
                            
                            <!-- Expandable Details Row -->
                            @if($hasChanges)
                                <tr id="details-{{ $activity->id }}" class="hidden bg-slate-50/50">
                                    <td colspan="6" class="p-6">
                                        <div class="border border-slate-200 rounded-2xl bg-white overflow-hidden shadow-inner max-w-4xl mx-auto">
                                            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                                                <span class="text-[10px] font-black uppercase text-slate-500 tracking-wider">Modification Audit Details</span>
                                                <button type="button" onclick="toggleDetails({{ $activity->id }})" class="text-slate-400 hover:text-slate-600 text-xs font-bold border-0 bg-transparent">Close</button>
                                            </div>
                                            
                                            <div class="p-4 overflow-x-auto">
                                                @php
                                                    $attributes = $props['attributes'] ?? $activity->attribute_changes ?? [];
                                                    $oldValues = $props['old'] ?? [];
                                                @endphp
                                                <table class="w-full text-left border-collapse text-xs">
                                                    <thead>
                                                        <tr class="border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-wider">
                                                            <th class="py-2 px-4">Field / Property</th>
                                                            <th class="py-2 px-4 bg-red-50/30 text-red-700">Original (Old)</th>
                                                            <th class="py-2 px-4 bg-emerald-50/30 text-emerald-700">Updated (New)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        @foreach($attributes as $key => $newValue)
                                                            @if($key === 'password' || $key === 'remember_token')
                                                                @continue
                                                            @endif
                                                            @php
                                                                $oldValue = $oldValues[$key] ?? null;
                                                            @endphp
                                                            <tr class="hover:bg-slate-50/10">
                                                                <td class="py-2 px-4 font-mono font-bold text-slate-700">{{ $key }}</td>
                                                                <td class="py-2 px-4 font-mono text-red-600 bg-red-50/20 max-w-xs break-all">
                                                                    @if(is_array($oldValue))
                                                                        {{ json_encode($oldValue) }}
                                                                    @elseif(is_null($oldValue))
                                                                        <span class="text-slate-300 italic">null</span>
                                                                    @elseif(is_bool($oldValue))
                                                                        {{ $oldValue ? 'true' : 'false' }}
                                                                    @else
                                                                        {{ $oldValue }}
                                                                    @endif
                                                                </td>
                                                                <td class="py-2 px-4 font-mono text-emerald-600 bg-emerald-50/20 max-w-xs break-all">
                                                                    @if(is_array($newValue))
                                                                        {{ json_encode($newValue) }}
                                                                    @elseif(is_null($newValue))
                                                                        <span class="text-slate-300 italic">null</span>
                                                                    @elseif(is_bool($newValue))
                                                                        {{ $newValue ? 'true' : 'false' }}
                                                                    @else
                                                                        {{ $newValue }}
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">
                                    No activity log entries matched the filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Footer -->
            @if($activities->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleDetails(id) {
            const el = document.getElementById('details-' + id);
            if (el) {
                el.classList.toggle('hidden');
            }
        }
    </script>
    @endpush
</x-layouts.app>
