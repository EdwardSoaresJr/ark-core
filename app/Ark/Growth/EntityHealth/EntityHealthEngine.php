<?php

namespace App\Ark\Growth\EntityHealth;

use App\Ark\Customer\CustomerSurfaceFooterData;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Settings\AudienceSurfaceVerifications;
use App\Ark\Operations\Leads\Public\PublicLegacyRedirect;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Growth\Seo\ShopSeoContext;
use Carbon\CarbonImmutable;

final class EntityHealthEngine
{
    /**
     * @return array{
     *     canonical: list<array{key: string, label: string, value: string, complete: bool}>,
     *     audiences: array<string, array{key: string, label: string, last_verified_at: ?string, days_since_verified: ?int, is_stale: bool, status: string}>,
     *     identity_observations: list<array<string, string>>,
     *     notebook: array{summary: string, bullets: list<string>, last_reviewed_at: string},
     * }
     */
    public function summarize(): array
    {
        $findings = array_map(
            static fn (EntityHealthFinding $finding): array => $finding->toArray(),
            $this->identityObservations(),
        );

        return [
            'canonical' => CanonicalEntityProjection::fields(),
            'audiences' => AudienceSurfaceVerifications::surfacesForDisplay(),
            'identity_observations' => $findings,
            'notebook' => $this->notebook($findings),
        ];
    }

    /**
     * @return list<EntityHealthFinding>
     */
    private function identityObservations(): array
    {
        $findings = [];

        foreach ([
            $this->napIncompleteFinding(),
            $this->websiteHostDriftFinding(),
            $this->suiteSchemaDriftFinding(),
            $this->jsonLdHoursGapFinding(),
            $this->legacyBrandingFinding(),
            $this->legacyAppointmentRedirectFinding(),
        ] as $finding) {
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    private function napIncompleteFinding(): ?EntityHealthFinding
    {
        $shop = ShopSettings::current();
        $missing = array_values(array_filter([
            trim((string) $shop->shop_name) === '' ? 'business name' : null,
            trim((string) $shop->address_line_1) === '' ? 'street address' : null,
            trim((string) $shop->city) === '' ? 'city' : null,
            trim((string) $shop->state) === '' ? 'state' : null,
            trim((string) $shop->postal_code) === '' ? 'postal code' : null,
            PhoneNumber::normalize($shop->phone) === null ? 'phone' : null,
        ]));

        if ($missing === []) {
            return null;
        }

        return new EntityHealthFinding(
            id: 'nap_incomplete',
            title: 'Incomplete canonical identity',
            canonicalLabel: 'ShopSettings',
            canonicalValue: 'Required NAP fields must be complete in Settings → Shop.',
            projectionLabel: 'Missing fields',
            projectionValue: implode(', ', $missing),
            recommendedAction: 'Complete the missing shop identity fields in Settings → Shop → General before verifying audiences.',
            notebookLine: 'Canonical identity is incomplete.',
            severity: 'high',
        );
    }

    private function websiteHostDriftFinding(): ?EntityHealthFinding
    {
        $shop = ShopSettings::current();
        $settingsWebsite = trim((string) $shop->website);

        if ($settingsWebsite === '') {
            return null;
        }

        $settingsHost = $this->normalizeHost(parse_url($settingsWebsite, PHP_URL_HOST));
        $publicHost = $this->normalizeHost(parse_url(PublicMarketingUrl::baseUrl(), PHP_URL_HOST));

        if ($settingsHost === null || $publicHost === null || $settingsHost === $publicHost) {
            return null;
        }

        return new EntityHealthFinding(
            id: 'website_host_drift',
            title: 'Website host mismatch',
            canonicalLabel: 'ShopSettings.website',
            canonicalValue: $settingsWebsite,
            projectionLabel: 'Public marketing host',
            projectionValue: PublicMarketingUrl::baseUrl(),
            recommendedAction: 'Confirm which hostname is correct for citations and Google Business Profile, then align Settings → Shop website with the public marketing host.',
            notebookLine: 'Public website host differs from the shop website setting.',
            severity: 'warning',
        );
    }

    private function suiteSchemaDriftFinding(): ?EntityHealthFinding
    {
        $shop = ShopSettings::current();
        $suite = trim((string) $shop->address_line_2);

        if ($suite === '') {
            return null;
        }

        $googleStreet = $shop->googleMatchedStreetAddress();
        $schema = ShopSeoContext::resolve()['address'] ?? null;
        $schemaStreet = is_array($schema) ? trim((string) ($schema['streetAddress'] ?? '')) : '';

        if ($schemaStreet === '' || $schemaStreet === $googleStreet) {
            return null;
        }

        $footerStreet = (string) (CustomerSurfaceFooterData::viewData()['street_address'] ?? $googleStreet);

        return new EntityHealthFinding(
            id: 'suite_schema_drift',
            title: 'Suite or unit not projected publicly',
            canonicalLabel: 'ShopSettings address',
            canonicalValue: $googleStreet,
            projectionLabel: 'JSON-LD streetAddress',
            projectionValue: $schemaStreet !== '' ? $schemaStreet : $footerStreet,
            recommendedAction: 'Verify whether the suite or unit should appear on the public address. If yes, include it in the street address authority or update audience listings to match the canonical format.',
            notebookLine: 'JSON-LD address omits suite designation.',
            severity: 'warning',
        );
    }

    private function jsonLdHoursGapFinding(): ?EntityHealthFinding
    {
        $hoursLabel = trim((string) (PublicSurfaceSettings::current()['business_hours_label'] ?? ''));

        if ($hoursLabel === '') {
            return null;
        }

        $context = ShopSeoContext::resolve();

        if (array_key_exists('openingHoursSpecification', $context)) {
            return null;
        }

        return new EntityHealthFinding(
            id: 'json_ld_hours_gap',
            title: 'Hours visible on site but not in JSON-LD',
            canonicalLabel: 'Public hours label',
            canonicalValue: $hoursLabel,
            projectionLabel: 'LocalBusiness schema',
            projectionValue: 'No openingHoursSpecification projected',
            recommendedAction: 'Decide whether business hours should appear in structured data for local search. If yes, project telephony hours into ShopSeoContext.',
            notebookLine: 'JSON-LD omits business hours.',
            severity: 'warning',
        );
    }

    private function legacyBrandingFinding(): ?EntityHealthFinding
    {
        $hits = [];

        foreach (config('entity_health.legacy_identity_phrases', []) as $phrase) {
            if (! is_string($phrase) || trim($phrase) === '') {
                continue;
            }

            $locations = $this->scanForPhrase($phrase);

            if ($locations !== []) {
                $hits[$phrase] = $locations;
            }
        }

        if ($hits === []) {
            return null;
        }

        $lines = [];

        foreach ($hits as $phrase => $locations) {
            $lines[] = '"'.$phrase.'" in '.implode(', ', $locations);
        }

        $phrase = array_key_first($hits);
        $phraseLabel = is_string($phrase) ? $phrase : 'legacy branding';

        return new EntityHealthFinding(
            id: 'legacy_branding_drift',
            title: 'Legacy branding phrase detected',
            canonicalLabel: 'ShopSettings.shop_name',
            canonicalValue: trim((string) ShopSettings::current()->shop_name) ?: '—',
            projectionLabel: 'ARK-controlled surfaces',
            projectionValue: implode('; ', $lines),
            recommendedAction: 'Remove or update legacy branding in ARK-controlled copy, then verify external directories manually during audience verification.',
            notebookLine: 'Legacy "'.$phraseLabel.'" branding remains.',
            severity: 'warning',
        );
    }

    private function legacyAppointmentRedirectFinding(): ?EntityHealthFinding
    {
        $target = PublicLegacyRedirect::resolve('appointment');

        if ($target === '/') {
            return null;
        }

        return new EntityHealthFinding(
            id: 'legacy_appointment_redirect',
            title: 'Legacy appointment URL not redirected home',
            canonicalLabel: 'public_legacy_redirects',
            canonicalValue: '/appointment → /',
            projectionLabel: 'PublicLegacyRedirect',
            projectionValue: $target === null ? 'No redirect configured' : '/appointment → '.$target,
            recommendedAction: 'Restore a redirect from /appointment to / so indexed legacy booking URLs do not 404.',
            notebookLine: 'Legacy /appointment URL is not redirected home.',
            severity: 'high',
        );
    }

    /**
     * @return list<string>
     */
    private function scanForPhrase(string $phrase): array
    {
        $locations = [];
        $paths = [
            base_path('config/public_seo.php'),
            base_path('config/public_legacy_redirects.php'),
            base_path('app/Ark/Customer/CustomerSurfaceFooterData.php'),
            base_path('app/Ark/Operations/Leads/Public/PublicSurfaceSettings.php'),
        ];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            if (str_contains($contents, $phrase)) {
                $locations[] = str_replace(base_path().'/', '', $path);
            }
        }

        return $locations;
    }

    /**
     * @param  list<array<string, string>>  $findings
     * @return array{summary: string, bullets: list<string>, last_reviewed_at: string}
     */
    private function notebook(array $findings): array
    {
        $count = count($findings);
        $timezone = ShopSettings::current()->shop_timezone ?: config('app.timezone');
        $reviewedAt = CarbonImmutable::now($timezone)->format('F j, Y');

        if ($count === 0) {
            return [
                'summary' => 'Public identity remains consistent.',
                'bullets' => [],
                'last_reviewed_at' => $reviewedAt,
            ];
        }

        if ($count === 1) {
            return [
                'summary' => '1 public identity inconsistency requires attention.',
                'bullets' => [$findings[0]['notebook_line']],
                'last_reviewed_at' => $reviewedAt,
            ];
        }

        return [
            'summary' => $count.' public identity inconsistencies require attention.',
            'bullets' => array_values(array_map(
                static fn (array $finding): string => $finding['notebook_line'],
                $findings,
            )),
            'last_reviewed_at' => $reviewedAt,
        ];
    }

    private function normalizeHost(mixed $host): ?string
    {
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return strtolower(trim($host));
    }
}
