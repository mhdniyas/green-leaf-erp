<x-layouts.admin title="System Activity Logs">
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-slate-950">System Activity Logs</h1>
                    <p class="mt-1 text-xs text-slate-500">Audit trail with user, IP address, resource, request, and changed data details.</p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Entries</p>
                        <p class="mt-1 text-base font-black text-slate-900">{{ number_format($filteredActivitiesCount) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Users</p>
                        <p class="mt-1 text-base font-black text-slate-900">{{ number_format($filteredUsersCount) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Resources</p>
                        <p class="mt-1 text-base font-black text-slate-900">{{ number_format($filteredSubjectTypesCount) }}</p>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-7">
                <div>
                    <label for="start_date" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate->format('Y-m-d') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label for="end_date" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate->format('Y-m-d') }}"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label for="causer_id" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">User</label>
                    <select name="causer_id" id="causer_id"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">All Users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(request('causer_id') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="event" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">Event</label>
                    <select name="event" id="event"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">All Events</option>
                        @foreach($events as $event)
                            <option value="{{ $event }}" @selected(request('event') === $event)>{{ str($event)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="subject_type" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">Resource</label>
                    <select name="subject_type" id="subject_type"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">All Resources</option>
                        @foreach($subjectTypes as $subjectType)
                            <option value="{{ $subjectType }}" @selected(request('subject_type') === $subjectType)>{{ class_basename($subjectType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="ip_address" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">IP Address</label>
                    <input type="search" name="ip_address" id="ip_address" value="{{ request('ip_address') }}" placeholder="192.168..."
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label for="search" class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-slate-400">Search</label>
                    <input type="search" name="search" id="search" value="{{ request('search') }}" placeholder="Description or URL"
                           class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-7 lg:justify-end">
                    <a href="{{ route('admin.activity-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50">Reset</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A50.06 50.06 0 0 1 12 3Z" />
                        </svg>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Audit Entries</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Showing {{ $activities->firstItem() ?? 0 }}-{{ $activities->lastItem() ?? 0 }} of {{ number_format($activities->total()) }} entries.
                    </p>
                </div>
                @if($dailyActivityCounts->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach($dailyActivityCounts->take(7) as $date => $count)
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($date)->format('d M') }}: {{ $count }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($activities as $activity)
                    @php
                        $properties = $activity->properties;
                        $ipAddress = data_get($properties, 'ip_address') ?? data_get($properties, 'ip') ?? data_get($properties, 'request.ip');
                        $userAgent = data_get($properties, 'user_agent') ?? data_get($properties, 'request.user_agent');
                        $method = data_get($properties, 'method') ?? data_get($properties, 'request.method');
                        $url = data_get($properties, 'url') ?? data_get($properties, 'request.url');
                        $attributes = data_get($properties, 'attributes', []);
                        $oldValues = data_get($properties, 'old', []);
                        $attributeChanges = $activity->attribute_changes ?? [];
                        $changedValues = filled($attributes) ? $attributes : $attributeChanges;
                        $hasDetails = filled($changedValues) || filled($oldValues) || filled($properties);
                        $event = strtolower($activity->event ?? $activity->description);
                        $badgeColor = match($event) {
                            'created', 'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            'updated', 'sent to supplier' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                            'deleted', 'rejected' => 'border-red-200 bg-red-50 text-red-700',
                            'submitted' => 'border-amber-200 bg-amber-50 text-amber-700',
                            default => 'border-slate-200 bg-slate-50 text-slate-700',
                        };
                    @endphp

                    <article class="p-5 transition-colors hover:bg-slate-50/60">
                        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[180px_230px_1fr_230px]">
                            <div>
                                <p class="text-xs font-black text-slate-900">{{ $activity->created_at?->format('d M Y') }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-400">{{ $activity->created_at?->format('h:i:s A') }}</p>
                                <span class="mt-3 inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $badgeColor }}">
                                    {{ $activity->event ? str($activity->event)->headline() : 'Action' }}
                                </span>
                            </div>

                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">User</p>
                                @if($activity->causer)
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-xs font-black text-slate-700">
                                            {{ strtoupper(substr($activity->causer->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $activity->causer->name }}</p>
                                            <p class="truncate text-[11px] text-slate-500">{{ $activity->causer->email ?? 'No email recorded' }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @forelse($activity->causer->roles ?? [] as $role)
                                            <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ $role->name }}</span>
                                        @empty
                                            <span class="text-[11px] text-slate-400">No role assigned</span>
                                        @endforelse
                                    </div>
                                @else
                                    <p class="mt-2 text-sm font-semibold text-slate-500">System / Auto</p>
                                @endif
                            </div>

                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Action Details</p>
                                <p class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ $activity->description }}</p>

                                <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Resource</p>
                                        @if($activity->subject_type)
                                            <p class="mt-1 truncate text-xs font-bold text-slate-800">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</p>
                                        @else
                                            <p class="mt-1 text-xs font-semibold text-slate-400">No resource attached</p>
                                        @endif
                                    </div>
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Log Name</p>
                                        <p class="mt-1 truncate text-xs font-bold text-slate-800">{{ $activity->log_name ?: 'default' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Request Metadata</p>
                                <dl class="mt-2 space-y-2 text-xs">
                                    <div>
                                        <dt class="font-black uppercase tracking-wider text-slate-400">IP Address</dt>
                                        <dd class="mt-0.5 font-mono font-bold text-slate-900">{{ $ipAddress ?: 'Not recorded' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-black uppercase tracking-wider text-slate-400">Method / URL</dt>
                                        <dd class="mt-0.5 break-all font-semibold text-slate-700">
                                            @if($method)
                                                <span class="font-mono text-slate-900">{{ $method }}</span>
                                            @endif
                                            {{ $url ?: 'Not recorded' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-black uppercase tracking-wider text-slate-400">Browser</dt>
                                        <dd class="mt-0.5 line-clamp-2 break-all font-semibold text-slate-700">{{ $userAgent ?: 'Not recorded' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        @if($hasDetails)
                            <details class="mt-4 rounded-lg border border-slate-200 bg-white">
                                <summary class="cursor-pointer px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-600 transition-colors hover:bg-slate-50">
                                    View Stored Properties and Changes
                                </summary>
                                <div class="border-t border-slate-100 p-4">
                                    @if(filled($changedValues))
                                        <div class="overflow-x-auto">
                                            <table class="w-full min-w-[640px] text-left text-xs">
                                                <thead>
                                                    <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                                        <th class="px-3 py-2">Field</th>
                                                        <th class="px-3 py-2 text-red-700">Old</th>
                                                        <th class="px-3 py-2 text-emerald-700">New</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($changedValues as $key => $newValue)
                                                        @if(in_array($key, ['password', 'remember_token'], true))
                                                            @continue
                                                        @endif
                                                        @php($oldValue = data_get($oldValues, $key))
                                                        <tr>
                                                            <td class="px-3 py-2 font-mono font-bold text-slate-700">{{ $key }}</td>
                                                            <td class="max-w-sm break-all px-3 py-2 font-mono text-red-700">
                                                                @if(is_array($oldValue) || is_object($oldValue))
                                                                    {{ json_encode($oldValue) }}
                                                                @elseif(is_bool($oldValue))
                                                                    {{ $oldValue ? 'true' : 'false' }}
                                                                @elseif(blank($oldValue))
                                                                    <span class="text-slate-300">null</span>
                                                                @else
                                                                    {{ $oldValue }}
                                                                @endif
                                                            </td>
                                                            <td class="max-w-sm break-all px-3 py-2 font-mono text-emerald-700">
                                                                @if(is_array($newValue) || is_object($newValue))
                                                                    {{ json_encode($newValue) }}
                                                                @elseif(is_bool($newValue))
                                                                    {{ $newValue ? 'true' : 'false' }}
                                                                @elseif(blank($newValue))
                                                                    <span class="text-slate-300">null</span>
                                                                @else
                                                                    {{ $newValue }}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if(filled($properties))
                                        <div class="mt-4 rounded-lg bg-slate-950 p-4">
                                            <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Raw Properties</p>
                                            <pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words text-xs leading-5 text-slate-100">{{ json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="px-5 py-16 text-center">
                        <p class="text-sm font-semibold text-slate-900">No activity logs found</p>
                        <p class="mt-1 text-xs text-slate-500">Try widening the date range or clearing filters.</p>
                    </div>
                @endforelse
            </div>

            @if($activities->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
