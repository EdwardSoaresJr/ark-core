@php
    $publicSitemap = $integrations['public_sitemap'];
    $gbp = $integrations['google_business_profile'];
    $gsc = $integrations['google_search_console'];
    $seoAutomation = $integrations['seo_automation'];
    $indexNow = $integrations['indexnow'];
    $googleIndexing = $integrations['google_indexing'];
@endphp

<x-operations.app title="Growth · Integrations">
    <section class="space-y-3" x-data="growthIntegrations()">
        @include('growth.partials.subnav')

        @if (session('status'))
            <div class="border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
                <p class="font-semibold">Could not save integrations.</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-4 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('growth.integrations.update') }}" class="space-y-3">
            @csrf
            @method('PATCH')

            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Public website</p>
                    <h1 class="mt-0.5 text-lg font-black text-slate-950">Sitemap</h1>
                    <p class="mt-1 max-w-3xl text-xs text-slate-500">
                        ARK serves <code class="font-mono text-[11px]">{{ $publicSitemap['url'] }}</code> from Growth. Mark this on after you submit it in Search Console.
                    </p>
                </div>
                <div class="p-3">
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="public_sitemap_enabled" value="0">
                        <input
                            type="checkbox"
                            name="public_sitemap_enabled"
                            value="1"
                            @checked(old('public_sitemap_enabled', $publicSitemap['enabled']))
                            class="mt-0.5 rounded border-slate-300"
                        >
                        <span>
                            <span class="font-semibold text-slate-900">Sitemap submitted in Search Console</span>
                            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">
                                Check this after submitting <a href="{{ $publicSitemap['url'] }}" class="font-semibold text-sky-700 underline" target="_blank" rel="noopener">{{ $publicSitemap['url'] }}</a> in Google Search Console. Website Health uses this flag.
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Google listing</p>
                    <h2 class="mt-0.5 text-lg font-black text-slate-950">Google Business Profile</h2>
                    <p class="mt-1 max-w-3xl text-xs text-slate-500">
                        Connect your shop listing so ARK can sync local presence metrics nightly — calls, website clicks, map impressions, and directions.
                    </p>
                </div>

            <div class="grid gap-3 p-3 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <div class="space-y-3">
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="hidden" name="google_business_profile_enabled" value="0">
                            <input
                                type="checkbox"
                                name="google_business_profile_enabled"
                                value="1"
                                @checked(old('google_business_profile_enabled', $gbp['enabled']))
                                class="mt-0.5 rounded border-slate-300"
                            >
                            <span>
                                <span class="font-semibold text-slate-900">Enable Google Business Profile sync</span>
                                <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">When off, ARK may still use fixture metrics in non-production environments.</span>
                            </span>
                        </label>

                        <div>
                            <label for="growth_google_service_account_json" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Google service account JSON
                            </label>
                            <textarea
                                id="growth_google_service_account_json"
                                name="growth_google_service_account_json"
                                rows="8"
                                placeholder='Paste the full JSON key file from Google Cloud…'
                                class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 font-mono text-[11px] leading-5 text-slate-800"
                            >{{ old('growth_google_service_account_json') }}</textarea>
                            @error('growth_google_service_account_json')
                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                            @enderror
                            @if ($gbp['has_credentials'])
                                <p class="mt-2 rounded-sm border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-[11px] leading-4 text-emerald-900">
                                    <span class="font-semibold">Saved on server.</span>
                                    @if ($gbp['client_email'])
                                        Using <span class="font-mono">{{ $gbp['client_email'] }}</span>
                                        @if ($gbp['credentials_source'] === 'server_file')
                                            from the server Firebase file.
                                        @else
                                            .
                                        @endif
                                    @endif
                                    The box stays empty on purpose — ARK never re-displays private keys. Leave blank to keep the saved key, or paste a new JSON to replace it.
                                </p>
                            @elseif ($gbp['server_firebase_client_email'])
                                <p class="mt-2 rounded-sm border border-sky-200 bg-sky-50 px-2 py-1.5 text-[11px] leading-4 text-sky-900">
                                    <span class="font-semibold">Server Firebase account available.</span>
                                    ARK can use <span class="font-mono">{{ $gbp['server_firebase_client_email'] }}</span> from <code class="font-mono text-[10px]">firebase-mobile-service-account.json</code> — the same <code class="font-mono text-[10px]">lugsnplugs-ark-mobile</code> project as mobile push. No paste required.
                                </p>
                            @else
                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Download from Google Cloud → IAM → Service accounts → Keys → Add key → JSON. Click <strong>Save integrations</strong> after pasting.
                                </p>
                            @endif

                            @if ($gbp['server_firebase_overridden'])
                                <label class="mt-2 flex items-start gap-2 rounded-sm border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] leading-4 text-amber-950">
                                    <input type="hidden" name="use_server_firebase_service_account" value="0">
                                    <input
                                        type="checkbox"
                                        name="use_server_firebase_service_account"
                                        value="1"
                                        @checked(old('use_server_firebase_service_account'))
                                        class="mt-0.5 rounded border-amber-300"
                                    >
                                    <span>
                                        <span class="font-semibold">Use server Firebase account instead.</span>
                                        Switch from <span class="font-mono">{{ $gbp['client_email'] }}</span> to
                                        <span class="font-mono">{{ $gbp['server_firebase_client_email'] }}</span>
                                        (<code class="font-mono text-[10px]">lugsnplugs-ark-mobile</code>). Save integrations, then try Discover locations.
                                    </span>
                                </label>
                            @else
                                <input type="hidden" name="use_server_firebase_service_account" value="0">
                            @endif
                        </div>

                        <div class="rounded-sm border border-slate-200 bg-slate-50/70 p-3">
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <label for="google_business_profile_location" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Listing location ID
                                    </label>
                                    <input
                                        id="google_business_profile_location"
                                        name="google_business_profile_location"
                                        type="text"
                                        value="{{ old('google_business_profile_location', $gbp['location']) }}"
                                        placeholder="Discover locations first"
                                        class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 font-mono text-sm text-slate-900"
                                    >
                                    @error('google_business_profile_location')
                                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                        Required before enabling sync. Click <strong>Discover locations</strong> and pick the Colorado Springs listing.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="discoverLocations()"
                                    :disabled="discovering"
                                    class="inline-flex min-h-10 items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50"
                                >
                                    <span x-show="!discovering">Discover locations</span>
                                    <span x-show="discovering" x-cloak>Discovering…</span>
                                </button>
                            </div>

                            <template x-if="discoverError">
                                <p class="mt-2 text-xs text-red-700" x-text="discoverError"></p>
                            </template>

                            <template x-if="discoverNotice">
                                <p class="mt-2 text-xs text-emerald-800" x-text="discoverNotice"></p>
                            </template>

                            <template x-if="locations.length">
                                <div class="mt-3 space-y-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pick a listing</p>
                                    <template x-for="location in locations" :key="location.id">
                                        <button
                                            type="button"
                                            @click="selectLocation(location.id)"
                                            class="block w-full rounded-sm border border-slate-200 bg-white px-3 py-2 text-left hover:bg-slate-50"
                                        >
                                            <span class="block text-sm font-semibold text-slate-900" x-text="location.title"></span>
                                            <span class="mt-0.5 block font-mono text-[11px] text-slate-600" x-text="location.id"></span>
                                            <span class="mt-0.5 block text-[11px] text-slate-500" x-show="location.address" x-text="location.address"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="hidden" name="backfill_after_save" value="0">
                            <input
                                type="checkbox"
                                name="backfill_after_save"
                                value="1"
                                @checked(old('backfill_after_save'))
                                class="mt-0.5 rounded border-slate-300"
                            >
                            <span>
                                <span class="font-semibold text-slate-900">Backfill last 28 days after saving</span>
                                <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Imports historical daily metrics once credentials and location are saved.</span>
                            </span>
                        </label>
                </div>

                <div class="space-y-3">
                    <div class="rounded-sm border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Connection status</p>
                        <p class="mt-1 text-sm font-black text-slate-950">
                            @if ($gbp['configured'])
                                Connected
                            @elseif ($gbp['using_fixture'])
                                Fixture data (not live Google)
                            @else
                                Not configured
                            @endif
                        </p>
                        <dl class="mt-3 space-y-2 text-xs text-slate-600">
                            <div>
                                <dt class="font-semibold text-slate-500">Last sync</dt>
                                <dd class="mt-0.5 text-slate-800">{{ $integrations['sync']['status_label'] }} — {{ $integrations['sync']['last_ran_label'] ?? 'Never' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-500">Sync message</dt>
                                <dd class="mt-0.5 text-slate-800">{{ $integrations['sync']['message'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-500">Stored metrics</dt>
                                <dd class="mt-0.5 text-slate-800">{{ number_format($integrations['metric_rows']) }} rows across {{ number_format($integrations['metric_days']) }} days</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-sm border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Setup checklist</p>
                        <ol class="mt-2 list-decimal space-y-2 pl-4 text-[11px] leading-5 text-slate-700">
                            <li>
                                In <a href="https://console.cloud.google.com/apis/library/businessprofileperformance.googleapis.com" class="font-semibold text-sky-700 underline" target="_blank" rel="noopener">Google Cloud</a>, enable <strong>Business Profile Performance API</strong>, <strong>My Business Account Management API</strong>, and <strong>My Business Business Information API</strong> for your project.
                            </li>
                            <li>
                                Create a <strong>service account</strong> → Keys → Add key → JSON. Paste the full file above.
                            </li>
                            <li>
                                In <a href="https://business.google.com/" class="font-semibold text-sky-700 underline" target="_blank" rel="noopener">Google Business Profile</a>, open Lugs N Plugs → Users → add the service account email as a <strong>Manager</strong>.
                            </li>
                            <li>
                                Save the JSON here, click <strong>Discover locations</strong>, and pick the Colorado Springs listing.
                            </li>
                            <li>
                                Enable sync, check backfill if you want history, then save. Use <strong>Rebuild now</strong> on Opportunities anytime.
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
            </div>

            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Search demand loop</p>
                    <h2 class="mt-0.5 text-lg font-black text-slate-950">Search Console + nightly automation</h2>
                    <p class="mt-1 max-w-3xl text-xs text-slate-500">
                        ARK ingests queries nightly, queues opportunities, auto-publishes qualified Common Problems pages, and notifies IndexNow + Google.
                    </p>
                </div>
                <div class="space-y-3 p-3">
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="google_search_console_enabled" value="0">
                        <input type="checkbox" name="google_search_console_enabled" value="1" @checked(old('google_search_console_enabled', $gsc['enabled'])) class="mt-0.5 rounded border-slate-300">
                        <span>
                            <span class="font-semibold text-slate-900">Enable Google Search Console ingest</span>
                            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Uses the same service account. Add it as an <strong>Owner</strong> on the Search Console property.</span>
                        </span>
                    </label>

                    <div>
                        <label for="google_search_console_property" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Console property</label>
                        <input
                            id="google_search_console_property"
                            name="google_search_console_property"
                            type="text"
                            value="{{ old('google_search_console_property', $gsc['property']) }}"
                            placeholder="sc-domain:demo-auto.test"
                            class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 font-mono text-sm text-slate-900"
                        >
                    </div>

                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="google_indexing_enabled" value="0">
                        <input type="checkbox" name="google_indexing_enabled" value="1" @checked(old('google_indexing_enabled', $googleIndexing['enabled'])) class="mt-0.5 rounded border-slate-300">
                        <span>
                            <span class="font-semibold text-slate-900">Request Google recrawl via Indexing API</span>
                            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Submits new/updated URLs after auto-publish. Same service account must be Owner in Search Console.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="hidden" name="seo_automation_enabled" value="0">
                        <input type="checkbox" name="seo_automation_enabled" value="1" @checked(old('seo_automation_enabled', $seoAutomation['enabled'])) class="mt-0.5 rounded border-slate-300">
                        <span>
                            <span class="font-semibold text-slate-900">Auto-publish qualified search-demand pages</span>
                            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Up to {{ $seoAutomation['auto_publish_max_per_night'] }} Create opportunities per night when impressions and draft quality pass gates.</span>
                        </span>
                    </label>

                    <div class="rounded-sm border border-slate-200 bg-slate-50/70 p-3 text-xs text-slate-600">
                        <p class="font-semibold text-slate-700">IndexNow (Bing, Yandex, and partners)</p>
                        @if ($indexNow['configured'])
                            <p class="mt-1">Key file: <a href="{{ $indexNow['key_url'] }}" class="font-mono text-sky-700 underline" target="_blank" rel="noopener">{{ $indexNow['key_url'] }}</a></p>
                        @else
                            <p class="mt-1">ARK generates a key on the first nightly notify run.</p>
                        @endif
                        <p class="mt-1">Sitemap + recent URLs are submitted automatically when notify is enabled.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                    Save integrations
                </button>
            </div>
        </form>
    </section>

    <script>
        function growthIntegrations() {
            return {
                locations: [],
                discovering: false,
                discoverError: null,
                discoverNotice: null,
                async discoverLocations() {
                    this.discovering = true;
                    this.discoverError = null;
                    this.discoverNotice = null;
                    this.locations = [];

                    const jsonField = document.getElementById('growth_google_service_account_json');
                    const inlineJson = jsonField ? jsonField.value.trim() : '';

                    try {
                        const response = await fetch(@js(route('growth.integrations.discover-locations')), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                            },
                            body: JSON.stringify({
                                growth_google_service_account_json: inlineJson,
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            throw new Error(payload.message || 'Could not discover locations.');
                        }

                        this.locations = payload.locations || [];

                        if (this.locations.length === 0) {
                            this.discoverError = 'No locations returned. Confirm the service account is a Manager on the listing and the Business Information API is enabled.';
                        } else if (inlineJson !== '') {
                            this.discoverNotice = 'Locations found using pasted JSON. Click Save integrations to store the key before leaving this page.';
                        }
                    } catch (error) {
                        this.discoverError = error.message || 'Could not discover locations.';
                    } finally {
                        this.discovering = false;
                    }
                },
                selectLocation(id) {
                    document.getElementById('google_business_profile_location').value = id;
                },
            };
        }
    </script>
</x-operations.app>
