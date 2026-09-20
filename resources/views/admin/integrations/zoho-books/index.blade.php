<x-layouts.admin title="Zoho Books Integration">
    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        activeTab: '{{ $activeTab }}',
        statusFilter: 'all',
        typeFilter: 'all',
        searchQuery: ''
    }">
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
                        Manage OAuth connection and map Green Leaf ERP accounting categories to your Zoho Books Chart of Accounts.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
            </div>

            <!-- Tab Navigation Header -->
            <div class="mt-6 flex border-b border-slate-200">
                <a href="{{ route('admin.integrations.zoho-books.index', ['tab' => 'connection']) }}"
                   class="border-b-2 px-5 py-3 text-xs font-black transition {{ $activeTab === 'connection' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                    Connection
                </a>
                <a href="{{ route('admin.integrations.zoho-books.index', ['tab' => 'mapping']) }}"
                   class="border-b-2 px-5 py-3 text-xs font-black transition flex items-center gap-2 {{ $activeTab === 'mapping' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                    Account Mapping
                    @if ($unmappedCount > 0 && $connection?->isConnected())
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $unmappedCount }} unmapped</span>
                    @endif
                </a>
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

        <!-- TAB 1: Connection -->
        @if ($activeTab === 'connection')
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    @if ($connection?->isConnected())
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
                                    Connect Green Leaf ERP with your Zoho Books organization to enable category mapping and accounting sync.
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
                                <p class="mt-1 text-slate-800">Tokens are encrypted at rest using AES-256-CBC via Laravel Encrypted casts.</p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        @endif

        <!-- TAB 2: Account Mapping -->
        @if ($activeTab === 'mapping')
            @if (! $connection?->isConnected())
                <div class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-6 text-amber-900 shadow-sm">
                    <h3 class="text-base font-black">Zoho Books Not Connected</h3>
                    <p class="mt-1 text-xs font-semibold">Please connect your Zoho Books organization under the <strong>Connection</strong> tab before mapping accounts.</p>
                    <div class="mt-4">
                        <a href="{{ route('admin.integrations.zoho-books.index', ['tab' => 'connection']) }}"
                           class="inline-flex h-10 items-center justify-center rounded-xl bg-amber-900 px-4 text-xs font-black text-white">
                            Go to Connection Tab
                        </a>
                    </div>
                </div>
            @elseif ($missingAccountantsScope)
                <div class="rounded-[1.75rem] border border-amber-200 bg-amber-50 p-6 text-amber-900 shadow-sm">
                    <div class="flex items-start gap-3">
                        <svg class="h-6 w-6 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <div>
                            <h3 class="text-base font-black">Additional Zoho permission required</h3>
                            <p class="mt-1 text-xs font-semibold leading-relaxed">
                                Accessing your Zoho Books Chart of Accounts requires the <code class="font-mono bg-amber-100 px-1 py-0.5 rounded">ZohoBooks.accountants.READ</code> scope.
                                Please reconnect Zoho Books to grant the updated read-only permissions.
                            </p>
                            <div class="mt-4">
                                <a href="{{ route('admin.integrations.zoho-books.connect') }}"
                                   class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-amber-950 px-5 text-xs font-black text-white shadow-sm transition hover:bg-amber-900">
                                    Reconnect Zoho Books
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Summary Stats Bar -->
                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">ERP Categories</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ $totalCategories }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-600">Mapped</p>
                        <p class="mt-1 text-2xl font-black text-emerald-900">{{ $mappedCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-600">Unmapped</p>
                        <p class="mt-1 text-2xl font-black text-amber-900">{{ $unmappedCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Zoho Accounts Available</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ count($zohoAccounts) }}</p>
                    </div>
                </div>

                @if ($coaError)
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800">
                        Failed to load Chart of Accounts: {{ $coaError }}
                    </div>
                @endif

                <!-- Filtering & Actions Header -->
                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Filters -->
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 block mb-1">Status</label>
                                <select x-model="statusFilter" class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500">
                                    <option value="all">All Statuses</option>
                                    <option value="mapped">Mapped Only</option>
                                    <option value="unmapped">Unmapped Only</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 block mb-1">Category Type</label>
                                <select x-model="typeFilter" class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500">
                                    <option value="all">All Category Types</option>
                                    <option value="income">Income</option>
                                    <option value="expense">Expense</option>
                                    <option value="transfer">Transfer / Other</option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <label class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 block mb-1">Search</label>
                                <input type="text" x-model="searchQuery" placeholder="Search category name or code..." class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500">
                            </div>
                        </div>

                        <!-- Action: Refresh Chart of Accounts -->
                        <div class="pt-3 sm:pt-0">
                            <form method="POST" action="{{ route('admin.integrations.zoho-books.refresh-accounts') }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50 shadow-sm">
                                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    Refresh Accounts
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Category Mappings Table -->
                <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs font-semibold text-slate-700">
                            <thead class="border-b border-slate-200 bg-slate-50/80 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                                <tr>
                                    <th class="px-5 py-4">ERP Category</th>
                                    <th class="px-4 py-4">ERP Type</th>
                                    <th class="px-4 py-4">Zoho Books Account</th>
                                    <th class="px-4 py-4">Zoho Type</th>
                                    <th class="px-4 py-4">Status</th>
                                    <th class="px-5 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($entryTypes as $type)
                                    @php
                                        $mapping = $mappings->get($type->id);
                                        $isMapped = $mapping !== null;
                                        $mappedAccountExists = false;

                                        if ($isMapped) {
                                            $mappedAccountExists = collect($zohoAccounts)->contains('account_id', $mapping->zoho_account_id);
                                        }

                                        $isUnavailable = $isMapped && ! $mappedAccountExists && count($zohoAccounts) > 0;
                                    @endphp
                                    <tr x-show="
                                        (statusFilter === 'all' || (statusFilter === 'mapped' && {{ $isMapped ? 'true' : 'false' }}) || (statusFilter === 'unmapped' && {{ ! $isMapped ? 'true' : 'false' }})) &&
                                        (typeFilter === 'all' || typeFilter === '{{ strtolower($type->category) }}') &&
                                        (searchQuery === '' || '{{ strtolower($type->name.' '.$type->code) }}'.includes(searchQuery.toLowerCase()))
                                    " class="hover:bg-slate-50/60 transition">
                                        <!-- Category Name & Code -->
                                        <td class="px-5 py-4 font-bold text-slate-900">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $type->name }}</span>
                                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-500">{{ $type->code }}</span>
                                            </div>
                                        </td>

                                        <!-- ERP Type -->
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                                {{ $type->category === 'income' ? 'bg-emerald-100 text-emerald-800' : ($type->category === 'expense' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700') }}">
                                                {{ $type->category }}
                                            </span>
                                        </td>

                                        <!-- Zoho Account Select Form -->
                                        <td class="px-4 py-4 min-w-[260px]">
                                            <form id="mapping-form-{{ $type->id }}" method="POST" action="{{ route('admin.integrations.zoho-books.mappings.save') }}">
                                                @csrf
                                                <input type="hidden" name="ledger_entry_type_id" value="{{ $type->id }}">

                                                <select name="zoho_account_id" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                                    <option value="" disabled @selected(! $isMapped)>-- Select Zoho Account --</option>
                                                    @foreach ($groupedZohoAccounts as $accountType => $accountsInGroup)
                                                        <optgroup label="{{ strtoupper(str_replace('_', ' ', $accountType)) }}">
                                                            @foreach ($accountsInGroup as $acc)
                                                                <option value="{{ $acc['account_id'] }}" @selected($isMapped && $mapping->zoho_account_id == $acc['account_id'])>
                                                                    {{ ! empty($acc['account_code']) ? '['.$acc['account_code'].'] ' : '' }}{{ $acc['account_name'] }}
                                                                    @if (! empty($acc['is_system_account'])) (System Account) @endif
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>

                                        <!-- Zoho Type -->
                                        <td class="px-4 py-4">
                                            @if ($isMapped)
                                                <span class="font-mono text-[11px] font-bold text-slate-600 uppercase">{{ str_replace('_', ' ', $mapping->zoho_account_type) }}</span>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>

                                        <!-- Status -->
                                        <td class="px-4 py-4">
                                            @if ($isUnavailable)
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700" title="Mapped account ID was not found in active Zoho accounts">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                    Account Inactive/Unavailable
                                                </span>
                                            @elseif ($isMapped)
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                    Mapped
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-500">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                                    Unmapped
                                                </span>
                                            @endif
                                        </td>

                                        <!-- Action Buttons -->
                                        <td class="px-5 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="submit" form="mapping-form-{{ $type->id }}" class="inline-flex h-8 items-center justify-center rounded-lg bg-slate-900 px-3 text-[11px] font-black text-white transition hover:bg-slate-800">
                                                    {{ $isMapped ? 'Update' : 'Map' }}
                                                </button>

                                                @if ($isMapped)
                                                    <form method="POST" action="{{ route('admin.integrations.zoho-books.mappings.remove', $type->id) }}" onsubmit="return confirm('Remove mapping for {{ addslashes($type->name) }}?');" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 text-[11px] font-black text-rose-700 transition hover:bg-rose-100">
                                                            Remove
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-8 text-center text-slate-500 font-medium">
                                            No ERP accounting categories found in database.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-layouts.admin>
