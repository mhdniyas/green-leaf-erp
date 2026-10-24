<x-layouts.admin title="Database Backup & Restore">

    <x-slot:actions>
        <a href="{{ route('admin.backup.download') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-800 transition-colors shadow-xs">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span>Download DB (.sql.gz)</span>
        </a>
    </x-slot:actions>

    <div x-data="backupRestoreManager()" class="space-y-6">

        {{-- Flash Notifications --}}
        @if(session('success'))
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 shadow-xs">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 mt-0.5">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <p>{{ session('error') }}</p>
                    @if(session('safety_backup'))
                        <p class="text-xs font-mono font-bold text-rose-900">
                            Automatic safety backup available:
                            <a href="{{ route('admin.backup.safety.download', session('safety_backup')) }}" class="underline hover:text-rose-700">
                                Download {{ session('safety_backup') }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        @endif

        @if($isProduction && !$productionRestoreAllowed)
            <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 text-xs font-semibold text-amber-900 shadow-xs">
                <svg class="h-5 w-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                <span>Production Environment: Database restoration is currently <strong>locked</strong>. To enable, configure <code class="bg-amber-100 px-1 py-0.5 rounded font-mono text-[11px]">BACKUP_ALLOW_PRODUCTION_RESTORE=true</code>.</span>
            </div>
        @endif

        {{-- Last Restore Status Card (if recorded) --}}
        @if($lastRestoreStatus)
            <div class="rounded-2xl border @if(($lastRestoreStatus['status'] ?? '') === 'success') border-emerald-200 bg-emerald-50/50 @elseif(($lastRestoreStatus['status'] ?? '') === 'failed_and_recovered') border-amber-200 bg-amber-50/50 @else border-rose-200 bg-rose-50/50 @endif p-4 shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-extrabold uppercase tracking-wider
                            @if(($lastRestoreStatus['status'] ?? '') === 'success') bg-emerald-100 text-emerald-800
                            @elseif(($lastRestoreStatus['status'] ?? '') === 'failed_and_recovered') bg-amber-100 text-amber-800
                            @else bg-rose-100 text-rose-800 @endif">
                            Last Restore: {{ str_replace('_', ' ', strtoupper($lastRestoreStatus['status'] ?? 'unknown')) }}
                        </span>
                        <span class="text-xs text-slate-600">
                            <strong>{{ $lastRestoreStatus['source_filename'] ?? 'Backup file' }}</strong>
                            &bull; {{ $lastRestoreStatus['completed_at'] ?? $lastRestoreStatus['failed_at'] ?? $lastRestoreStatus['started_at'] ?? '' }}
                        </span>
                    </div>

                    @if(!empty($lastRestoreStatus['safety_backup']))
                        <a href="{{ route('admin.backup.safety.download', $lastRestoreStatus['safety_backup']) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span>Download Safety Backup ({{ $lastRestoreStatus['safety_backup'] }})</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif

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

        {{-- Main Action Cards: Email, Direct Download & Upload Restore --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Email Backup Card --}}
            <div class="rounded-2xl border border-emerald-200 bg-linear-to-b from-white to-emerald-50/20 p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Email Backup</h2>
                            <p class="text-xs text-slate-500">Send SQL dump via email</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.backup.email') }}" class="space-y-3" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='Compressing & Sending…';">
                        @csrf
                        <div>
                            <label for="email" class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Recipient Email
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email', $defaultRecipient) }}" required
                                   placeholder="admin@example.com"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                            @error('email')
                                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-extrabold uppercase tracking-wider text-white hover:bg-emerald-700 transition-colors shadow-2xs cursor-pointer">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                            <span>Send Backup</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Direct Download Card --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-800">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Direct Download</h2>
                            <p class="text-xs text-slate-500">Instant database archive download</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 font-semibold">Database:</span>
                            <span class="font-mono font-bold text-slate-900">{{ $stats['database'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600 font-semibold">Format:</span>
                            <span class="font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded text-[11px] font-bold">.sql.gz</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <a href="{{ route('admin.backup.download') }}"
                       class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2.5 text-xs font-extrabold uppercase tracking-wider text-white hover:bg-slate-800 transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Download Backup</span>
                    </a>
                </div>
            </div>

            {{-- Upload & Restore Card --}}
            <div class="rounded-2xl border border-amber-200 bg-linear-to-b from-white to-amber-50/20 p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Upload & Restore</h2>
                            <p class="text-xs text-slate-500">Restore from Spatie ZIP or SQL</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider">
                            Choose Backup File (.zip, .sql.gz, .sql)
                        </label>
                        <input type="file" id="upload_file_input" accept=".zip,.gz,.sql"
                               class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer">
                        <p class="text-[11px] text-slate-500">Supports Spatie backup ZIPs and gzip dumps up to 500 MB.</p>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="button" @click="openForUpload()"
                            @if($isProduction && !$productionRestoreAllowed) disabled @endif
                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-600 px-3 py-2.5 text-xs font-extrabold uppercase tracking-wider text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-2xs cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Inspect & Restore File</span>
                    </button>
                </div>
            </div>

        </div>

        {{-- Recent Server Backups Table --}}
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Recent Server Backups</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Compressed snapshots saved in server storage.</p>
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
                                            {{-- Restore Action --}}
                                            <button type="button"
                                                    @click="openForExisting({{ Js::from($backup) }})"
                                                    @if($isProduction && !$productionRestoreAllowed) disabled @endif
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 hover:bg-amber-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                                                    title="Restore and replace database with this backup">
                                                <svg class="h-3.5 w-3.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                </svg>
                                                <span>Restore</span>
                                            </button>

                                            {{-- Download Action --}}
                                            <a href="{{ route('admin.backup.download', ['file' => $backup['filename']]) }}"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors"
                                               title="Download this backup file">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                <span>Download</span>
                                            </a>

                                            {{-- Delete Action --}}
                                            <form method="POST" action="{{ route('admin.backup.delete', $backup['filename']) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete backup file {{ $backup['filename'] }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
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

        {{-- Safety Recovery Backups Table (if any exist) --}}
        @if(!empty($safetyBackups))
            <div class="rounded-2xl border border-indigo-200 bg-white overflow-hidden shadow-xs">
                <div class="flex items-center justify-between px-6 py-4 border-b border-indigo-100 bg-indigo-50/30">
                    <div>
                        <h3 class="text-sm font-bold text-indigo-950">Safety Recovery Backups</h3>
                        <p class="text-xs text-indigo-700/80 mt-0.5">Automatic snapshots captured immediately before database replacements.</p>
                    </div>
                    <span class="text-xs font-bold text-indigo-700 bg-indigo-100 px-2.5 py-1 rounded-full">
                        {{ count($safetyBackups) }} safety snapshots
                    </span>
                </div>

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
                            @foreach($safetyBackups as $safety)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-3.5 font-mono text-xs font-bold text-indigo-900">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                            </svg>
                                            <span>{{ $safety['filename'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5 text-xs font-bold text-slate-600">
                                        {{ $safety['size_formatted'] }}
                                    </td>
                                    <td class="px-6 py-3.5 text-xs text-slate-500">
                                        {{ $safety['created_at_formatted'] }}
                                    </td>
                                    <td class="px-6 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.backup.safety.download', $safety['filename']) }}"
                                               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors">
                                                <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                <span>Download</span>
                                            </a>

                                            <form method="POST" action="{{ route('admin.backup.safety.delete', $safety['filename']) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete safety backup {{ $safety['filename'] }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                                        title="Delete safety backup">
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
            </div>
        @endif

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

        {{-- Restore Preview & Confirmation Modal --}}
        <div x-show="modalOpen" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                {{-- Backdrop --}}
                <div x-show="modalOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="if(!isSubmitting) modalOpen = false"
                     class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"></div>

                {{-- Modal Panel --}}
                <div x-show="modalOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform overflow-hidden rounded-3xl border border-rose-200 bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl">

                    <form method="POST" action="{{ route('admin.backup.restore') }}" enctype="multipart/form-data" @submit="submitRestore($event)">
                        @csrf
                        <input type="hidden" name="confirmation" :value="confirmationInput">
                        <input type="hidden" name="backup_file" :value="mode === 'existing' ? selectedExistingFile : ''">
                        <input type="file" id="modal_upload_file" name="upload_file" class="hidden">

                        <div class="bg-white p-6 sm:p-7">
                            {{-- Modal Header --}}
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-black text-slate-900" id="modal-title">
                                        Restore & Replace Database
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Full database replacement preview and destruction confirmation.
                                    </p>
                                </div>
                            </div>

                            {{-- High-Risk Warning Alert --}}
                            <div class="mt-5 rounded-2xl border border-rose-300 bg-rose-50/90 p-4 text-xs text-rose-900 space-y-1.5">
                                <p class="font-extrabold uppercase tracking-wide text-rose-950 flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                    </svg>
                                    Destructive Action: Complete Database Replacement
                                </p>
                                <p>All current tables and records in target database <code class="font-mono font-bold text-rose-950" x-text="targetDatabase"></code> will be completely dropped and replaced with the backup contents.</p>
                                <p class="font-semibold text-rose-800">
                                    &bull; Existing data will NOT be merged or appended.<br>
                                    &bull; An automated safety backup will be created and verified before wiping.<br>
                                    &bull; If restoration fails, the database will be rolled back using the safety backup.
                                </p>
                            </div>

                            {{-- Preview Details Grid --}}
                            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold">Source Backup:</span>
                                    <span class="font-mono font-bold text-slate-900 truncate max-w-xs" x-text="sourceFilename"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold">Archive Size:</span>
                                    <span class="font-bold text-slate-800" x-text="sourceSize"></span>
                                </div>

                                <template x-if="dumpFilename">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500 font-semibold">Detected Dump:</span>
                                        <span class="font-mono text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded text-[11px] font-bold" x-text="dumpFilename"></span>
                                    </div>
                                </template>

                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold">Target Environment:</span>
                                    <span class="font-bold text-slate-900 uppercase tracking-wider text-[11px] bg-slate-200 px-2 py-0.5 rounded" x-text="targetEnvironment"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold">Target Database:</span>
                                    <span class="font-mono font-bold text-slate-900" x-text="targetDatabase"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold">Target Host:</span>
                                    <span class="font-mono text-slate-700" x-text="targetHost"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500 font-semibold">Current Active Tables:</span>
                                    <span class="font-bold text-slate-900" x-text="currentTables + ' tables'"></span>
                                </div>
                            </div>

                            {{-- Preview Loading or Error --}}
                            <template x-if="loadingPreview">
                                <div class="mt-3 flex items-center justify-center gap-2 rounded-xl bg-slate-100 p-2.5 text-xs font-semibold text-slate-600">
                                    <svg class="h-4 w-4 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Inspecting backup archive & SQL dump integrity...</span>
                                </div>
                            </template>

                            <template x-if="previewError">
                                <div class="mt-3 rounded-xl border border-rose-300 bg-rose-50 p-3 text-xs font-semibold text-rose-800" x-text="previewError"></div>
                            </template>

                            {{-- Destructive Confirmation Input --}}
                            <div class="mt-5 space-y-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Type <span class="font-mono font-black text-rose-700" x-text="expectedPhrase"></span> to confirm:
                                </label>
                                <input type="text" x-model="confirmationInput"
                                       :placeholder="expectedPhrase"
                                       :disabled="isSubmitting"
                                       class="w-full rounded-xl border-2 border-slate-300 bg-white px-3.5 py-2.5 font-mono text-sm font-bold text-slate-900 placeholder-slate-300 focus:border-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                            </div>

                            {{-- Progress / Submitting Banner --}}
                            <div x-show="isSubmitting" class="mt-4 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold text-amber-900">
                                <svg class="h-5 w-5 animate-spin text-amber-700 shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Restoring and replacing database... Creating safety backup and importing schema. Please do NOT close or refresh this page.</span>
                            </div>
                        </div>

                        {{-- Modal Action Buttons --}}
                        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button type="button" @click="modalOpen = false"
                                    :disabled="isSubmitting"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50 transition-colors cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="!canConfirm() || isSubmitting"
                                    class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-sm cursor-pointer">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <span x-text="isSubmitting ? 'Replacing Database...' : 'Confirm & Replace Database'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @endpush

    <script>
        function backupRestoreManager() {
            return {
                modalOpen: false,
                loadingPreview: false,
                isSubmitting: false,
                mode: 'existing', // 'existing' or 'upload'
                sourceFilename: '',
                sourceSize: '',
                sourceDate: '',
                detectedDriver: '',
                dumpFilename: '',
                targetDatabase: '{{ $stats['database'] }}',
                targetHost: '{{ $stats['host'] }}',
                targetEnvironment: '{{ app()->environment() }}',
                currentTables: '{{ count($stats['tables']) }}',
                confirmationInput: '',
                expectedPhrase: '{{ $confirmationPhrase }}',
                previewError: '',
                selectedExistingFile: '',

                openForExisting(backup) {
                    this.mode = 'existing';
                    this.selectedExistingFile = backup.filename;
                    this.sourceFilename = backup.filename;
                    this.sourceSize = backup.size_formatted;
                    this.sourceDate = backup.created_at_formatted;
                    this.dumpFilename = '';
                    this.confirmationInput = '';
                    this.previewError = '';
                    this.modalOpen = true;

                    this.fetchPreview({ backup_file: backup.filename });
                },

                openForUpload() {
                    const fileInput = document.getElementById('upload_file_input');
                    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                        alert('Please select a backup archive file (.zip, .sql.gz, or .sql) first.');
                        return;
                    }
                    const file = fileInput.files[0];
                    this.mode = 'upload';
                    this.sourceFilename = file.name;
                    this.sourceSize = this.formatBytes(file.size);
                    this.sourceDate = 'Just uploaded';
                    this.dumpFilename = '';
                    this.confirmationInput = '';
                    this.previewError = '';
                    this.modalOpen = true;

                    // Sync file to modal form input
                    const modalFileInput = document.getElementById('modal_upload_file');
                    if (modalFileInput) {
                        modalFileInput.files = fileInput.files;
                    }

                    const formData = new FormData();
                    formData.append('upload_file', file);
                    this.fetchPreview(formData, true);
                },

                fetchPreview(payload, isFormData = false) {
                    this.loadingPreview = true;
                    this.previewError = '';

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    let options = {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    };

                    if (isFormData) {
                        options.body = payload;
                    } else {
                        options.headers['Content-Type'] = 'application/json';
                        options.body = JSON.stringify(payload);
                    }

                    fetch('{{ route('admin.backup.preview') }}', options)
                        .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, body: data })))
                        .then(result => {
                            this.loadingPreview = false;
                            if (result.ok && result.body.success) {
                                const d = result.body.data;
                                this.sourceFilename = d.source_filename;
                                this.sourceSize = d.source_size_formatted;
                                this.detectedDriver = d.detected_driver.toUpperCase();
                                this.dumpFilename = d.dump_filename;
                                this.targetDatabase = d.target_database;
                                this.targetHost = d.target_host;
                                this.targetEnvironment = d.target_environment;
                                this.currentTables = d.current_table_count;
                            } else {
                                this.previewError = result.body.message || 'Failed to inspect backup file.';
                            }
                        })
                        .catch(err => {
                            this.loadingPreview = false;
                            this.previewError = 'Failed to connect to inspection preview service.';
                        });
                },

                formatBytes(bytes) {
                    if (bytes <= 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                },

                canConfirm() {
                    return this.confirmationInput.trim() === this.expectedPhrase && !this.loadingPreview && !this.previewError;
                },

                submitRestore(e) {
                    if (!this.canConfirm()) {
                        e.preventDefault();
                        return;
                    }
                    this.isSubmitting = true;
                }
            };
        }
    </script>

</x-layouts.admin>
