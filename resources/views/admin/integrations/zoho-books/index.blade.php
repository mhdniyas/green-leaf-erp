<x-layouts.admin title="Zoho Books Integration">
    <div class="mx-auto max-w-5xl space-y-6">
        <!-- Header Banner -->
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Integrations</p>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $connection?->isConnected() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $connection?->isConnected() ? 'Connected' : 'Not Connected' }}
                        </span>
                    </div>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Zoho Books Integration</h1>
                    <p class="mt-1 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        Secure OAuth 2.0 connection between Green Leaf ERP and your Zoho Books accounting organization.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
            </div>
        </section>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-sm">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800 shadow-sm">
                <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (session('info'))
            <div class="flex items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-bold text-sky-800 shadow-sm">
                <svg class="h-5 w-5 shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <span>{{ session('info') }}</span>
            </div>
        @endif

        <!-- Main Integration Card -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if ($connection?->isConnected())
                    <!-- Connected State Card -->
                    <section class="rounded-[1.75rem] border border-emerald-200/80 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-900">Zoho Books Connected</h2>
                                    <p class="text-xs font-semibold text-slate-500">OAuth 2.0 active with offline refresh</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Status: Connected
                            </span>
                        </div>

                        <!-- Organization & Connection Details -->
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Organization Name</p>
                                <p class="mt-1 text-base font-black text-slate-900">{{ $connection->organization_name ?: 'Not selected' }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Organization ID</p>
                                <p class="mt-1 font-mono text-sm font-black text-slate-900">{{ $connection->organization_id ?: '—' }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Data Center / Location</p>
                                <p class="mt-1 font-mono text-sm font-black text-slate-900">{{ $connection->data_center ?: 'US' }}</p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Connected At</p>
                                <p class="mt-1 text-sm font-bold text-slate-900">
                                    {{ $connection->connected_at ? $connection->connected_at->format('M d, Y h:i A') : '—' }}
                                    @if ($connection->connectedByUser)
                                        <span class="text-xs text-slate-500 font-normal">by {{ $connection->connectedByUser->name }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <!-- Organization Switcher if multiple available -->
                        @if (count($availableOrganizations) > 1)
                            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                                <form method="POST" action="{{ route('admin.integrations.zoho-books.select-organization') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    @csrf
                                    <div class="flex-1">
                                        <label for="organization_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Switch Organization</label>
                                        <select id="organization_id" name="organization_id" class="mt-1 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                            @foreach ($availableOrganizations as $org)
                                                <option value="{{ $org['organization_id'] }}" @selected($org['organization_id'] == $connection->organization_id)>
                                                    {{ $org['name'] }} (ID: {{ $org['organization_id'] }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 px-4 text-xs font-black text-white shadow-sm transition hover:bg-slate-800">
                                        Update Organization
                                    </button>
                                </form>
                            </div>
                        @endif

                        <!-- Actions Bar -->
                        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                            <form method="POST" action="{{ route('admin.integrations.zoho-books.test') }}" class="inline-block">
                                @csrf
                                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-emerald-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                    Test Connection
                                </button>
                            </form>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.integrations.zoho-books.connect') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                                    Re-Authorize
                                </a>

                                <form method="POST" action="{{ route('admin.integrations.zoho-books.disconnect') }}" onsubmit="return confirm('Are you sure you want to disconnect Zoho Books? This will remove the active OAuth tokens.');" class="inline-block">
                                    @csrf
                                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-4 text-xs font-black text-rose-700 transition hover:bg-rose-100">
                                        Disconnect
                                    </button>
                                </form>
                            </div>
                        </div>
                    </section>
                @else
                    <!-- Disconnected State Card -->
                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-900">Zoho Books</h2>
                                    <p class="text-xs font-semibold text-slate-500">Not currently connected to any Zoho Books organization</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                Status: Not Connected
                            </span>
                        </div>

                        <div class="mt-6 space-y-4">
                            <p class="text-sm font-semibold text-slate-600 leading-relaxed">
                                Connect Green Leaf ERP with your Zoho Books organization to enable automated accounting sync and financial reporting.
                            </p>

                            @if (! $isConfigured)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-bold text-amber-900">
                                    <p class="font-black">Configuration required:</p>
                                    <p class="mt-1 font-semibold">Please ensure <code class="font-mono text-amber-950">ZOHO_CLIENT_ID</code> and <code class="font-mono text-amber-950">ZOHO_CLIENT_SECRET</code> are set in your <code class="font-mono text-amber-950">.env</code> file before connecting.</p>
                                </div>
                            @endif

                            <div class="pt-2">
                                <a href="{{ route('admin.integrations.zoho-books.connect') }}"
                                   class="inline-flex h-12 items-center justify-center gap-2.5 rounded-xl bg-emerald-600 px-6 text-sm font-black text-white shadow-sm transition hover:bg-emerald-500 {{ ! $isConfigured ? 'opacity-60' : '' }}">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                    Connect Zoho Books
                                </a>
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            <!-- Configuration & Security Info Sidebar -->
            <div class="space-y-6">
                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-black text-slate-900">Security & OAuth Details</h3>

                    <div class="mt-4 space-y-3 text-xs font-semibold text-slate-600">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">OAuth Redirect URI</p>
                            <p class="mt-1 break-all font-mono text-[11px] font-bold text-slate-800">{{ $redirectUri ?: route('admin.integrations.zoho-books.callback') }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Client ID</p>
                            <p class="mt-1 font-mono text-[11px] font-bold text-slate-800">{{ $clientId ? Str::mask($clientId, '*', 8, -4) : 'Not configured' }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Token Storage</p>
                            <p class="mt-1 text-slate-800">Tokens are encrypted at rest using AES-256-CBC and cast through Laravel Encrypted attributes.</p>
                        </div>

                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Permissions (Least Privilege)</p>
                            <p class="mt-1 text-slate-800">Read-only scopes for organization discovery, contacts, bills, and invoices.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-layouts.admin>
