<?php

namespace App\Ark\Operations\Leads\Public;

/**
 * Groups common problems for scannable homepage / index symptom choosers.
 */
final class CommonProblemSymptomGroups
{
    /**
     * @return list<array{label: string, problems: list<array{slug: string, title: string}>}>
     */
    public static function forDisplay(): array
    {
        $groups = [
            [
                'label' => 'Starting & battery',
                'slugs' => ['car-wont-start', 'battery-keeps-dying', 'electrical-diagnostics', 'check-engine-light'],
            ],
            [
                'label' => 'Brakes & suspension',
                'slugs' => ['brake-noise', 'suspension-noise', 'brake-fluid-service', 'wheel-bearing-noise', 'abs-light'],
            ],
            [
                'label' => 'Engine & drivetrain',
                'slugs' => ['engine-overheating', 'rough-idle', 'misfire-under-load', 'p0171', 'oil-leak', 'transmission-slipping', 'burnt-transmission-fluid', 'transmission-fluid-change'],
            ],
            [
                'label' => 'Check engine codes',
                'slugs' => [
                    'p0300', 'p0420', 'p0174', 'p0442', 'p0455', 'p0128',
                    'p0101', 'p0301', 'p0304', 'p0401', 'p0340', 'p0016',
                ],
            ],
            [
                'label' => 'Comfort & climate',
                'slugs' => ['ac-not-cold'],
            ],
            [
                'label' => 'Popular makes',
                'slugs' => ['audi-repair-colorado-springs', 'subaru-overheating', 'jeep-overheating', 'jeep-death-wobble', 'honda-timing-belt'],
            ],
            [
                'label' => 'Colorado Springs services',
                'slugs' => [
                    'auto-repair-colorado-springs',
                    'mechanic-colorado-springs',
                    'car-diagnostics-colorado-springs',
                    'brake-repair-colorado-springs',
                    'tune-up-colorado-springs',
                ],
            ],
            [
                'label' => 'Maintenance & service',
                'slugs' => ['car-fluid-service'],
            ],
        ];

        return collect($groups)
            ->map(function (array $group): array {
                $problems = collect($group['slugs'])
                    ->map(fn (string $slug): ?array => CommonProblemRegistry::find($slug))
                    ->filter()
                    ->map(fn (array $problem): array => [
                        'slug' => $problem['slug'],
                        'title' => $problem['title'],
                    ])
                    ->values()
                    ->all();

                return [
                    'label' => $group['label'],
                    'problems' => $problems,
                ];
            })
            ->filter(fn (array $group): bool => $group['problems'] !== [])
            ->values()
            ->all();
    }
}
