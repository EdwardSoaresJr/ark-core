<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopPublicSurfaceSettingsController
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:255'],
            'positioning_lede' => ['nullable', 'string', 'max:500'],
            'google_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'google_review_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'google_reviews_url' => ['required', 'url', 'max:2048'],
            'local_tagline' => ['nullable', 'string', 'max:255'],
            'customer_quote' => ['nullable', 'string', 'max:500'],
            'customer_quote_attribution' => ['nullable', 'string', 'max:120'],
            'response_time_hint' => ['nullable', 'string', 'max:255'],
            'instrumentation_enabled' => ['nullable', 'boolean'],
            'photo_alt' => ['array'],
            'photo_alt.*' => ['nullable', 'string', 'max:255'],
            'photo' => ['array'],
            'photo.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo_remove' => ['array'],
            'photo_remove.*' => ['nullable', 'boolean'],
            'composition_photos' => ['nullable', 'array'],
            'composition_photos.hero' => ['nullable', 'integer', 'min:0', 'max:3'],
            'composition_photos.diagnostic_evidence' => ['nullable', 'integer', 'min:0', 'max:3'],
            'composition_photos.appointment_process' => ['nullable', 'integer', 'min:0', 'max:3'],
            'wisetack_url' => ['nullable', 'url', 'max:2048'],
            'synchrony_url' => ['nullable', 'url', 'max:2048'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'instagram_url' => ['nullable', 'url', 'max:2048'],
            'nextdoor_url' => ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'arkademy_url' => ['nullable', 'url', 'max:2048'],
            'contact_visit_notes' => ['nullable', 'string', 'max:500'],
            'contact_faqs' => ['nullable', 'array', 'max:8'],
            'contact_faqs.*.question' => ['nullable', 'string', 'max:200'],
            'contact_faqs.*.answer' => ['nullable', 'string', 'max:1000'],
            'shop_services' => ['nullable', 'array', 'max:24'],
            'shop_services.*.title' => ['nullable', 'string', 'max:120'],
            'shop_services.*.common_problem_slug' => ['nullable', 'string', 'max:120'],
            'shop_services.*.enabled' => ['nullable', 'boolean'],
        ]);

        $photos = PublicSurfaceSettings::current()['shop_photos'];

        foreach (range(0, 3) as $index) {
            $existingPath = $photos[$index]['path'] ?? '';

            if ($request->boolean("photo_remove.$index") && $existingPath !== '') {
                self::deleteStoredPhoto($existingPath);
                $existingPath = '';
            }

            if ($request->hasFile("photo.$index")) {
                if ($existingPath !== '') {
                    self::deleteStoredPhoto($existingPath);
                }

                $existingPath = $request->file("photo.$index")->store('public-surface-photos', 'public');
            }

            $photos[$index] = [
                'path' => $existingPath,
                'alt' => trim((string) ($data['photo_alt'][$index] ?? $photos[$index]['alt'] ?? '')),
            ];
        }

        $trustSignals = PublicSurfaceSettings::current()['trust_signals'];

        $trustSignalUpdates = [];

        if (array_key_exists('wisetack_url', $data)) {
            $trustSignalUpdates['wisetack_url'] = $data['wisetack_url'] ?? '';
        }

        if (array_key_exists('synchrony_url', $data)) {
            $trustSignalUpdates['synchrony_url'] = $data['synchrony_url'] ?? '';
        }

        $shopServices = null;

        if (array_key_exists('shop_services', $data)) {
            $shopServices = collect($data['shop_services'] ?? [])
                ->map(function (array $service, int $index) use ($request): array {
                    return [
                        'title' => trim((string) ($service['title'] ?? '')),
                        'common_problem_slug' => trim((string) ($service['common_problem_slug'] ?? '')) ?: null,
                        'enabled' => $request->boolean("shop_services.$index.enabled"),
                    ];
                })
                ->values()
                ->all();
        }

        $contactFaqs = null;

        if (array_key_exists('contact_faqs', $data)) {
            $contactFaqs = collect($data['contact_faqs'] ?? [])
                ->map(fn (array $faq): array => [
                    'question' => trim((string) ($faq['question'] ?? '')),
                    'answer' => trim((string) ($faq['answer'] ?? '')),
                ])
                ->values()
                ->all();
        }

        PublicSurfaceSettings::persist([
            'headline' => $data['headline'] ?? null,
            'positioning_lede' => $data['positioning_lede'] ?? null,
            'google_rating' => $data['google_rating'],
            'google_review_count' => $data['google_review_count'],
            'google_reviews_url' => $data['google_reviews_url'],
            'local_tagline' => $data['local_tagline'] ?? null,
            'customer_quote' => $data['customer_quote'] ?? null,
            'customer_quote_attribution' => $data['customer_quote_attribution'] ?? null,
            'response_time_hint' => $data['response_time_hint'] ?? null,
            'instrumentation_enabled' => $request->boolean('instrumentation_enabled'),
            'trust_signals' => array_merge($trustSignals, $trustSignalUpdates),
            'social_profiles' => [
                'facebook_url' => $data['facebook_url'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null,
                'nextdoor_url' => $data['nextdoor_url'] ?? null,
                'youtube_url' => $data['youtube_url'] ?? null,
                'arkademy_url' => $data['arkademy_url'] ?? null,
            ],
            'contact_visit_notes' => $data['contact_visit_notes'] ?? null,
            'shop_photos' => $photos,
            'composition_photos' => [
                PublicSurfaceSettings::PHOTO_ROLE_HERO => (int) ($data['composition_photos']['hero']
                    ?? PublicSurfaceSettings::DEFAULTS['composition_photos'][PublicSurfaceSettings::PHOTO_ROLE_HERO]),
                PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE => (int) ($data['composition_photos']['diagnostic_evidence']
                    ?? PublicSurfaceSettings::DEFAULTS['composition_photos'][PublicSurfaceSettings::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE]),
                PublicSurfaceSettings::PHOTO_ROLE_APPOINTMENT_PROCESS => (int) ($data['composition_photos']['appointment_process']
                    ?? PublicSurfaceSettings::DEFAULTS['composition_photos'][PublicSurfaceSettings::PHOTO_ROLE_APPOINTMENT_PROCESS]),
            ],
            ...($shopServices !== null ? ['shop_services' => $shopServices] : []),
            ...($contactFaqs !== null ? ['contact_faqs' => $contactFaqs] : []),
        ]);

        return redirect()
            ->route('website.manage')
            ->with('status', 'Website settings saved.');
    }

    private static function deleteStoredPhoto(string $path): void
    {
        if (! str_starts_with($path, 'public-surface-photos/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
