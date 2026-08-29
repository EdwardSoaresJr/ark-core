@php
    /** @var array<string, mixed> $publicSurfaceSettings */
@endphp

<form method="POST" action="{{ route('website.manage.update') }}" enctype="multipart/form-data" class="mt-4 max-w-3xl space-y-5">
    @csrf
    @method('PATCH')

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Homepage headline
            <input
                type="text"
                name="headline"
                value="{{ old('headline', $publicSurfaceSettings['headline'] ?? '') }}"
                placeholder="Tell us what's going on with your vehicle."
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
        </label>
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Homepage positioning line
            <input
                type="text"
                name="positioning_lede"
                value="{{ old('positioning_lede', $publicSurfaceSettings['positioning_lede'] ?? '') }}"
                placeholder="When other shops can't find the problem, start here. Dealer-level diagnostics in Demo City."
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
            <span class="mt-1 block text-[11px] text-slate-500">Shown under the homepage headline — how you want to be remembered in the first 20 seconds.</span>
        </label>
        <div class="sm:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p class="text-xs font-medium text-slate-500">Business hours (public)</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $publicSurfaceSettings['business_hours_label'] ?? '' }}</p>
            <p class="mt-1 text-[11px] leading-5 text-slate-500">
                Synced from
                <a
                    href="{{ route('operations.settings.shop.edit', ['section' => 'communications', 'communications-tab' => 'hours']) }}"
                    class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
                >Communications → Weekly hours</a>.
                Shown near the phone number on the lead intake page and thank-you page.
            </p>
        </div>
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Thank-you response time
            <input
                type="text"
                name="response_time_hint"
                value="{{ old('response_time_hint', $publicSurfaceSettings['response_time_hint'] ?? '') }}"
                placeholder="During business hours we typically respond within 30–60 minutes."
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
        </label>
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Customer quote
            <textarea
                name="customer_quote"
                rows="2"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >{{ old('customer_quote', $publicSurfaceSettings['customer_quote'] ?? '') }}</textarea>
            <span class="mt-1 block text-[11px] text-slate-500">First featured quote (excerpt from a Google review). Three additional Google excerpts display on the homepage by default.</span>
        </label>
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Quote attribution
            <input
                type="text"
                name="customer_quote_attribution"
                value="{{ old('customer_quote_attribution', $publicSurfaceSettings['customer_quote_attribution'] ?? '') }}"
                placeholder="Sarah M."
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
        </label>
        <label class="block text-xs font-medium text-slate-500">
            Google rating
            <input
                type="number"
                name="google_rating"
                step="0.1"
                min="1"
                max="5"
                required
                value="{{ old('google_rating', $publicSurfaceSettings['google_rating']) }}"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
        </label>
        <label class="block text-xs font-medium text-slate-500">
            Google review count
            <input
                type="number"
                name="google_review_count"
                min="0"
                required
                value="{{ old('google_review_count', $publicSurfaceSettings['google_review_count']) }}"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
            <span class="mt-1 block text-[11px] text-slate-500">Use the real count only.</span>
        </label>
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Google reviews URL
            <input
                type="url"
                name="google_reviews_url"
                required
                value="{{ old('google_reviews_url', $publicSurfaceSettings['google_reviews_url']) }}"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
            <span class="mt-1 block text-[11px] text-slate-500">Prefer the Google Business Profile review link (ends in <code class="text-[10px]">/review</code>) so customers land on the review form.</span>
        </label>
        <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
            Local trust line
            <input
                type="text"
                name="local_tagline"
                value="{{ old('local_tagline', $publicSurfaceSettings['local_tagline']) }}"
                placeholder="Family owned in Demo City."
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >
            <span class="mt-1 block text-[11px] text-slate-500">Shown under the review badge.</span>
        </label>
    </div>

    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Connect with us</p>
        <p class="mt-1 text-xs text-slate-500">
            Official shop profiles for the public website, thank-you page, and search metadata (sameAs). Leave blank to hide that channel. Google Reviews uses the URL above.
        </p>

        @php
            $social = $publicSurfaceSettings['social_profiles'] ?? [];
        @endphp

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                Facebook page URL
                <input
                    type="url"
                    name="facebook_url"
                    value="{{ old('facebook_url', $social['facebook_url'] ?? '') }}"
                    placeholder="https://www.facebook.com/your-shop"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                Instagram profile URL
                <input
                    type="url"
                    name="instagram_url"
                    value="{{ old('instagram_url', $social['instagram_url'] ?? '') }}"
                    placeholder="https://www.instagram.com/your-shop"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                Nextdoor business URL
                <input
                    type="url"
                    name="nextdoor_url"
                    value="{{ old('nextdoor_url', $social['nextdoor_url'] ?? '') }}"
                    placeholder="https://nextdoor.com/pages/..."
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                YouTube channel URL <span class="font-normal text-slate-400">(optional)</span>
                <input
                    type="url"
                    name="youtube_url"
                    value="{{ old('youtube_url', $social['youtube_url'] ?? '') }}"
                    placeholder="https://www.youtube.com/@your-shop"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                ARKademy public URL <span class="font-normal text-slate-400">(optional)</span>
                <input
                    type="url"
                    name="arkademy_url"
                    value="{{ old('arkademy_url', $social['arkademy_url'] ?? '') }}"
                    placeholder="https://learn.demo-auto.test"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
        </div>
    </div>

    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact page</p>
        <p class="mt-1 text-xs text-slate-500">
            Parking / drop-off notes and FAQs on <a href="{{ route('public.contact') }}" class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]" target="_blank" rel="noopener noreferrer">/contact</a>. Leave a FAQ blank to remove it.
        </p>

        <label class="mt-4 block text-xs font-medium text-slate-500">
            Parking &amp; drop-off notes
            <textarea
                name="contact_visit_notes"
                rows="2"
                maxlength="500"
                placeholder="Visitor parking is in front of Unit D. After-hours drop-off by arrangement."
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
            >{{ old('contact_visit_notes', $publicSurfaceSettings['contact_visit_notes'] ?? '') }}</textarea>
        </label>

        @php
            $contactFaqs = old('contact_faqs', $publicSurfaceSettings['contact_faqs'] ?? []);
            if (! is_array($contactFaqs) || $contactFaqs === []) {
                $contactFaqs = \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::DEFAULTS['contact_faqs'];
            }
            while (count($contactFaqs) < 5) {
                $contactFaqs[] = ['question' => '', 'answer' => ''];
            }
            $contactFaqs = array_slice($contactFaqs, 0, 8);
        @endphp

        <div class="mt-4 space-y-3">
            @foreach ($contactFaqs as $index => $faq)
                <div class="grid gap-2 rounded-md border border-slate-200 bg-slate-50/60 p-3">
                    <label class="block text-xs font-medium text-slate-500">
                        FAQ question {{ $index + 1 }}
                        <input
                            type="text"
                            name="contact_faqs[{{ $index }}][question]"
                            value="{{ old('contact_faqs.'.$index.'.question', $faq['question'] ?? '') }}"
                            maxlength="200"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        >
                    </label>
                    <label class="block text-xs font-medium text-slate-500">
                        Answer
                        <textarea
                            name="contact_faqs[{{ $index }}][answer]"
                            rows="2"
                            maxlength="1000"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        >{{ old('contact_faqs.'.$index.'.answer', $faq['answer'] ?? '') }}</textarea>
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
        <input
            type="checkbox"
            name="instrumentation_enabled"
            value="1"
            @checked(old('instrumentation_enabled', $publicSurfaceSettings['instrumentation_enabled']))
            class="rounded border-slate-300"
        >
        Record public surface funnel events (views, starts, calls, texts — no PII)
    </label>

    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Financing links</p>
        <p class="mt-1 text-xs text-slate-500">Shop-specific prequal and apply URLs on the public financing page. Synchrony site codes track channel: 401 text links, 402 QR, 403 web button.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                Wisetack prequalify URL
                <input
                    type="url"
                    name="wisetack_url"
                    value="{{ old('wisetack_url', $publicSurfaceSettings['trust_signals']['wisetack_url'] ?? '') }}"
                    placeholder="{{ \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::WISETACK_PREQUAL_URL }}"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
            <label class="block text-xs font-medium text-slate-500 sm:col-span-2">
                Synchrony link URL (401 — text links)
                <input
                    type="url"
                    name="synchrony_url"
                    value="{{ old('synchrony_url', $publicSurfaceSettings['trust_signals']['synchrony_url'] ?? '') }}"
                    placeholder="{{ \App\Ark\Operations\Leads\Public\SynchronyCarCareUrls::linkUrl() }}"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                >
            </label>
            <div class="sm:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                <p><span class="font-semibold text-slate-700">Synchrony Apply button</span> uses 403 automatically.</p>
                <p class="mt-1"><span class="font-semibold text-slate-700">QR / signage</span> uses 402 — ready for estimate QR when you need it.</p>
                <p class="mt-1 break-all">402: {{ \App\Ark\Operations\Leads\Public\SynchronyCarCareUrls::qrUrl() }}</p>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Services we provide</p>
        <p class="mt-1 text-xs text-slate-500">
            Shown on the homepage Auto repair list. Link a service page when you have one. Prefer an even count for a balanced grid.
        </p>

        @php
            $servicePageOptions = $servicePageOptions ?? [];
            $initialShopServices = old('shop_services', $publicSurfaceSettings['shop_services'] ?? []);
            if (! is_array($initialShopServices) || $initialShopServices === []) {
                $initialShopServices = \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::DEFAULTS['shop_services'];
            }
            $initialShopServices = collect($initialShopServices)
                ->map(fn (array $service): array => [
                    'title' => (string) ($service['title'] ?? ''),
                    'common_problem_slug' => (string) ($service['common_problem_slug'] ?? ''),
                    'enabled' => (bool) ($service['enabled'] ?? true),
                ])
                ->values()
                ->all();
        @endphp

        <div
            class="mt-4 space-y-3"
            x-data="{
                services: {{ \Illuminate\Support\Js::from($initialShopServices) }},
                add() {
                    this.services.push({ title: '', common_problem_slug: '', enabled: true });
                },
                remove(index) {
                    this.services.splice(index, 1);
                    if (this.services.length === 0) {
                        this.add();
                    }
                },
            }"
        >
            <template x-for="(service, index) in services" :key="index">
                <div class="grid gap-2 rounded-md border border-slate-200 bg-slate-50/60 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                    <label class="block text-xs font-medium text-slate-500">
                        Label
                        <input
                            type="text"
                            :name="'shop_services[' + index + '][title]'"
                            x-model="service.title"
                            maxlength="120"
                            placeholder="Oil Changes"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        >
                    </label>
                    <label class="block text-xs font-medium text-slate-500">
                        Service page (optional)
                        <select
                            :name="'shop_services[' + index + '][common_problem_slug]'"
                            x-model="service.common_problem_slug"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        >
                            <option value="">Browse all services</option>
                            @foreach ($servicePageOptions as $option)
                                <option value="{{ $option['slug'] }}">{{ $option['title'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="inline-flex items-end gap-2 pb-2 text-xs font-medium text-slate-600">
                        <input
                            type="checkbox"
                            :name="'shop_services[' + index + '][enabled]'"
                            value="1"
                            x-model="service.enabled"
                            class="rounded border-slate-300"
                        >
                        Show
                    </label>
                    <div class="flex items-end pb-1">
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            @click="remove(index)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </template>

            <button
                type="button"
                class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                @click="add()"
            >
                Add service
            </button>
        </div>
    </div>

    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shop photo gallery</p>
        <p class="mt-1 text-xs text-slate-500">
            Reusable shop imagery (up to four). Assign which gallery slot fills each homepage job below.
            JPG, PNG, or WebP up to 4 MB. Alt text must describe the photo that is actually in the slot.
        </p>

        <div class="mt-4 grid gap-4">
            @foreach (range(0, 3) as $index)
                @php
                    $photo = $publicSurfaceSettings['shop_photos'][$index] ?? ['path' => '', 'alt' => ''];
                    $photoUrl = filled($photo['path'] ?? null) ? \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::photoUrl($photo['path']) : null;
                @endphp
                <div class="grid gap-3 rounded-md border border-slate-200 bg-slate-50/60 p-3 sm:grid-cols-[140px_minmax(0,1fr)]">
                    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md border border-slate-300 bg-white">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="" class="h-full w-full object-cover">
                        @else
                            <span class="px-2 text-center text-[11px] font-medium text-slate-400">Gallery slot {{ $index + 1 }}</span>
                        @endif
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-medium text-slate-500">
                            Alt text
                            <input
                                type="text"
                                name="photo_alt[{{ $index }}]"
                                value="{{ old("photo_alt.$index", $photo['alt'] ?? '') }}"
                                placeholder="Describe what is in this photo"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                            >
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Replace image
                            <input type="file" name="photo[{{ $index }}]" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                        </label>
                        @if (filled($photo['path'] ?? null))
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                <input type="checkbox" name="photo_remove[{{ $index }}]" value="1" class="rounded border-slate-300">
                                Remove photo
                            </label>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @php
        $compositionPhotos = $publicSurfaceSettings['composition_photos']
            ?? \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::DEFAULTS['composition_photos'];
        $compositionRoleLabels = [
            \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::PHOTO_ROLE_HERO => 'Hero photo',
            \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE => 'Diagnostic / testing evidence',
            \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::PHOTO_ROLE_APPOINTMENT_PROCESS => 'Appointment / process shop photo',
        ];
        $compositionRoleHints = [
            \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::PHOTO_ROLE_HERO => 'Homepage hero background.',
            \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE => '“Still having the same problem?” — scan, meter, pressure, smoke, or active diagnosis. Not a generic bay or underside shot.',
            \App\Ark\Operations\Leads\Public\PublicSurfaceSettings::PHOTO_ROLE_APPOINTMENT_PROCESS => '“How an appointment works” shop evidence.',
        ];
    @endphp

    <div class="border-t border-slate-200 pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Homepage composition photos</p>
        <p class="mt-1 text-xs text-slate-500">
            Each homepage job picks a gallery slot. Reordering the gallery alone does not change these assignments.
        </p>

        <div class="mt-4 grid gap-3">
            @foreach ($compositionRoleLabels as $role => $label)
                @php
                    $selected = old("composition_photos.$role", $compositionPhotos[$role] ?? null);
                @endphp
                <label class="block rounded-md border border-slate-200 bg-white p-3">
                    <span class="text-sm font-semibold text-slate-900">{{ $label }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ $compositionRoleHints[$role] }}</span>
                    <select
                        name="composition_photos[{{ $role }}]"
                        class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                    >
                        @foreach (range(0, 3) as $index)
                            @php
                                $slot = $publicSurfaceSettings['shop_photos'][$index] ?? ['path' => '', 'alt' => ''];
                                $slotLabel = filled($slot['alt'] ?? null)
                                    ? 'Slot '.($index + 1).' — '.$slot['alt']
                                    : (filled($slot['path'] ?? null) ? 'Slot '.($index + 1).' — (photo, no alt yet)' : 'Slot '.($index + 1).' — empty');
                            @endphp
                            <option value="{{ $index }}" @selected((string) $selected === (string) $index)>
                                {{ $slotLabel }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
    </div>

    <button type="submit" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
        Save website settings
    </button>
</form>
