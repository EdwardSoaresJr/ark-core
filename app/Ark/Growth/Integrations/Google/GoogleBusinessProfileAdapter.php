<?php

namespace App\Ark\Growth\Integrations\Google;

use App\Ark\Growth\Integrations\Contracts\BusinessProfileAdapter;
use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleBusinessProfileAdapter implements BusinessProfileAdapter
{
    private const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    private const API_BASE = 'https://businessprofileperformance.googleapis.com/v1';

    /** @var list<string> */
    private const DAILY_METRICS = [
        'BUSINESS_IMPRESSIONS_DESKTOP_MAPS',
        'BUSINESS_IMPRESSIONS_MOBILE_MAPS',
        'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH',
        'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
        'BUSINESS_CONVERSATIONS',
        'BUSINESS_DIRECTION_REQUESTS',
        'CALL_CLICKS',
        'WEBSITE_CLICKS',
    ];

    public function __construct(
        private readonly GoogleServiceAccountAccessToken $tokens,
    ) {}

    public function isConfigured(): bool
    {
        return GrowthIntegrationSettings::current()->isGoogleBusinessProfileConfigured();
    }

    public function fetchDailyMetrics(string $reportDate): array
    {
        return $this->fetchMetricsBetween($reportDate, $reportDate);
    }

    public function fetchMetricsBetween(string $startDate, string $endDate): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $settings = GrowthIntegrationSettings::current();
        $credentials = $settings->googleServiceAccountCredentials();
        if ($credentials === null) {
            return [];
        }

        $token = $this->tokens->accessToken(
            $credentials,
            self::SCOPE,
            'growth:google:gbp:access_token',
        );

        if ($token === null) {
            throw new RuntimeException('Unable to obtain Google Business Profile access token.');
        }

        $location = (string) $settings->googleBusinessProfileLocationResource();

        $response = Http::withToken($token)
            ->post(self::API_BASE.'/'.$location.':fetchMultiDailyMetricsTimeSeries', [
                'dailyMetrics' => self::DAILY_METRICS,
                'dailyRange' => [
                    'startDate' => $this->apiDate($startDate),
                    'endDate' => $this->apiDate($endDate),
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Business Profile API request failed: '.$response->status().' '.$response->body(),
            );
        }

        return $this->parseMetrics($response->json(), $location);
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    private function apiDate(string $reportDate): array
    {
        [$year, $month, $day] = array_map('intval', explode('-', $reportDate));

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<array{metric: string, value: int, report_date: string, metadata: array<string, mixed>}>
     */
    private function parseMetrics(?array $payload, string $location): array
    {
        $rows = [];

        foreach ($payload['multiDailyMetricTimeSeries'] ?? [] as $seriesGroup) {
            foreach ($seriesGroup['dailyMetricTimeSeries'] ?? [] as $series) {
                $metric = (string) ($series['dailyMetric'] ?? '');
                if ($metric === '') {
                    continue;
                }

                foreach ($series['timeSeries']['datedValues'] ?? [] as $datedValue) {
                    $reportDate = $this->formatApiDate($datedValue['date'] ?? null);
                    if ($reportDate === null) {
                        continue;
                    }

                    $rows[] = [
                        'metric' => $metric,
                        'value' => (int) ($datedValue['value'] ?? 0),
                        'report_date' => $reportDate,
                        'metadata' => [
                            'source' => 'google_business_profile',
                            'location' => $location,
                            'report_date' => $reportDate,
                        ],
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $date
     */
    private function formatApiDate(?array $date): ?string
    {
        if (! is_array($date)) {
            return null;
        }

        $year = (int) ($date['year'] ?? 0);
        $month = (int) ($date['month'] ?? 0);
        $day = (int) ($date['day'] ?? 0);

        if ($year <= 0 || $month <= 0 || $day <= 0) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
