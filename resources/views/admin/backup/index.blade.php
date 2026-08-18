<x-layouts.admin title="Database Backup">

    <x-slot:actions>
        <a href="{{ route('admin.backup.download') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-800 transition-colors shadow-xs">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span>Download DB (.sql.gz)</span>
        </a>
    </x-slot:actions>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="mb-5 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 shadow-xs">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="space-y-6">

        {{-- Metrics Overview --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Database Engine</span>
                    <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-[11px] font-extrabold text-emerald-700 uppercase">
                        {{ $stats['driver'] }}
                    </span>
                </div>
                <p class="mt-2 text-lg font-black text-slate-900 truncate" title="{{ $stats['database'] }}">
                    {{ $stats['database'] }}
                </p>
                <p class="mt-0.5 text-xs text-slate-400 font-mono">Host: {{ $stats['host'] }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Tables</span>
                    <span class="rounded-lg bg-brand-50 px-2 py-0.5 text-[11px] font-extrabold text-brand-700">
                        Active
                    </span>
                </div>
                <p class="mt-2 text-2xl font-black text-slate-900">
                    {{ count($stats['tables']) }}
                </p>
                <p class="mt-0.5 text-xs text-slate-400">Database schema tables</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Database Size</span>
                    <span class="rounded-lg bg-indigo-50 px-2 py-0.5 text-[11px] font-extrabold text-indigo-700">
                        Estimated
                    </span>
                </div>
                <p class="mt-2 text-2xl font-black text-slate-900">
                    {{ $stats['total_size'] }}
                </p>
                <p class="mt-0.5 text-xs text-slate-400">Data & index volume</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Server Backups</span>
                    <span class="rounded-lg bg-amber-50 px-2 py-0.5 text-[11px] font-extrabold text-amber-700">
                        {{ count($backups) }} files
                    </span>
                </div>
                <p class="mt-2 text-lg font-black text-slate-900 truncate">
                    {{ !empty($backups) ? $backups[0]['created_at_formatted'] : 'None yet' }}
                </p>
                <p class="mt-0.5 text-xs text-slate-400">Latest stored backup</p>
            </div>
        </div>

        {{-- Main Action Cards: Email & Download --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Email Backup Card --}}
            <div class="rounded-2xl border border-emerald-200 bg-linear-to-b from-white to-emerald-50/20 p-6 shadow-xs">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Email Database Backup</h2>
                        <p class="text-xs text-slate-500">Generates a complete gzip compressed SQL dump and sends it as an email attachment.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.backup.email') }}" class="space-y-4" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='Compressing & Sending…';">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Recipient Email Address
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <input type="email" name="email" id="email" value="{{ old('email', $defaultRecipient) }}" required
                                   placeholder="admin@example.com"
                                   class="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        </div>
                        @error('email')
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 space-y-1">
                        <p class="font-semibold text-slate-800">What is included:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px] text-slate-500">
                            <li>All database schemas, table structures, and indexes</li>
                            <li>Full row data with transactional consistency</li>
                            <li>High-ratio GZIP compression (<span class="font-mono">.sql.gz</span>)</li>
                        </ul>
                    </div>

                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-white hover:bg-emerald-700 transition-colors shadow-sm cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                        <span>Send Backup to Email</span>
                    </button>
                </form>
            </div>

            {{-- Direct Download Card --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-800">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Direct Download</h2>
                            <p class="text-xs text-slate-500">Download the fresh database backup archive directly to your local computer.</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-600">Database Name</span>
                            <span class="font-mono font-bold text-slate-900">{{ $stats['database'] }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-600">Archive Format</span>
                            <span class="font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">.sql.gz (Gzip)</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-600">Table Count</span>
                            <span class="font-bold text-slate-900">{{ count($stats['tables']) }} tables</span>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <a href="{{ route('admin.backup.download') }}"
                       class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-white hover:bg-slate-800 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Generate & Download Backup Now</span>
                    </a>
                </div>
            </div>

        </div>

        {{-- Recent Server Backups Table --}}
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Recent Server Backups</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Stored compressed snapshots in server storage.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ count($backups) }} saved files
                </span>
            </div>

            @if(empty($backups))
                <div class="py-12 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                        </svg>
                    </div>
                    <p class="text-sm font-bold text-slate-900">No backup files stored on server yet</p>
                    <p class="text-xs text-slate-500 mt-1">Send an email or click download to generate the first backup file.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">File Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Archive Size</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Created Date</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($backups as $backup)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-3.5 font-mono text-xs font-bold text-slate-800">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            <span>{{ $backup['filename'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs font-bold text-slate-600">
                                        {{ $backup['size_formatted'] }}
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-500">
                                        {{ $backup['created_at_formatted'] }}
                                    </td>
                                    <td class="px-6 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.backup.download', ['file' => $backup['filename']]) }}"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors"
                                               title="Download this backup file">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                <span>Download</span>
                                            </a>

                                            <form method="POST" action="{{ route('admin.backup.delete', $backup['filename']) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete backup file {{ $backup['filename'] }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                                        title="Delete backup file">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Database Schema Summary Table --}}
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Database Tables Summary</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Summary of tables included in the backup archive.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ count($stats['tables']) }} tables
                </span>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 z-10">
                        <tr class="border-b border-slate-200">
                            <th class="px-6 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Table Name</th>
                            <th class="px-6 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Records</th>
                            <th class="px-6 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Data Size</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($stats['tables'] as $table)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-2 font-mono text-xs font-bold text-slate-800">
                                    {{ $table['name'] }}
                                </td>
                                <td class="px-6 py-2 text-right font-mono text-xs text-slate-600">
                                    {{ number_format($table['rows']) }}
                                </td>
                                <td class="px-6 py-2 text-right font-mono text-xs text-slate-500">
                                    {{ $table['size'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</x-layouts.admin>
