<?php

namespace App\Ark\Website;

/**
 * Customer-facing service menu for /services and /llms.txt.
 *
 * This is the shop's work, not stored SEO landing titles.
 */
final class PublicServiceIndex
{
    /**
     * @return list<array{id: string, name: string, summary: string, links: list<array{slug: string, label: string}>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'id' => 'diagnostics',
                'name' => 'Diagnostics',
                'summary' => 'We test the vehicle against how that system is supposed to work, then tell you what failed before we recommend parts.',
                'links' => [
                    ['slug' => 'check-engine-light', 'label' => 'Check engine light'],
                    ['slug' => 'electrical-diagnostics', 'label' => 'Electrical diagnostics'],
                    ['slug' => 'misfire-under-load', 'label' => 'Running poorly'],
                ],
            ],
            [
                'id' => 'brakes',
                'name' => 'Brakes',
                'summary' => 'Squeals, a pedal that feels wrong, and brake fluid that is due for service.',
                'links' => [
                    ['slug' => 'brake-noise', 'label' => 'Brake noise'],
                    ['slug' => 'brake-fluid-service', 'label' => 'Brake fluid service'],
                ],
            ],
            [
                'id' => 'maintenance',
                'name' => 'Maintenance',
                'summary' => 'Fluid service and the upkeep that keeps a working car working.',
                'links' => [
                    ['slug' => 'car-fluid-service', 'label' => 'Fluid service'],
                ],
            ],
            [
                'id' => 'electrical',
                'name' => 'Electrical',
                'summary' => 'Charging, drains, no-start electrics, and warning lights that need a measurement.',
                'links' => [
                    ['slug' => 'electrical-diagnostics', 'label' => 'Electrical diagnostics'],
                    ['slug' => 'battery-keeps-dying', 'label' => 'Battery keeps dying'],
                    ['slug' => 'abs-light', 'label' => 'ABS light'],
                ],
            ],
            [
                'id' => 'cooling',
                'name' => 'Cooling and heating',
                'summary' => 'Overheating, coolant loss, and air conditioning that will not get cold.',
                'links' => [
                    ['slug' => 'engine-overheating', 'label' => 'Engine overheating'],
                    ['slug' => 'ac-not-cold', 'label' => 'A/C not cold'],
                ],
            ],
            [
                'id' => 'engine',
                'name' => 'Engine and performance',
                'summary' => 'Misfires, a rough idle, and codes that are a clue, not a parts list.',
                'links' => [
                    ['slug' => 'misfire-under-load', 'label' => 'Misfire under load'],
                    ['slug' => 'rough-idle', 'label' => 'Rough idle'],
                    ['slug' => 'p0300', 'label' => 'P0300'],
                ],
            ],
            [
                'id' => 'starting',
                'name' => 'Starting and charging',
                'summary' => 'A car that will not crank, will not start, or goes dead after it sits.',
                'links' => [
                    ['slug' => 'car-wont-start', 'label' => 'Car won\'t start'],
                    ['slug' => 'battery-keeps-dying', 'label' => 'Battery keeps dying'],
                ],
            ],
            [
                'id' => 'suspension',
                'name' => 'Suspension and steering',
                'summary' => 'Clunks, a speed-related hum, and a shake in the wheel.',
                'links' => [
                    ['slug' => 'suspension-noise', 'label' => 'Suspension noise'],
                    ['slug' => 'wheel-bearing-noise', 'label' => 'Wheel bearing noise'],
                    ['slug' => 'jeep-death-wobble', 'label' => 'Jeep death wobble'],
                ],
            ],
            [
                'id' => 'drivetrain',
                'name' => 'Drivetrain',
                'summary' => 'Slip, burnt fluid, and a fluid service when that is the actual job.',
                'links' => [
                    ['slug' => 'transmission-slipping', 'label' => 'Transmission slipping'],
                    ['slug' => 'transmission-fluid-change', 'label' => 'Transmission fluid change'],
                    ['slug' => 'burnt-transmission-fluid', 'label' => 'Burnt transmission fluid'],
                ],
            ],
            [
                'id' => 'timing',
                'name' => 'Timing belts and chains',
                'summary' => 'Interval replacement and the inspection that goes with it.',
                'links' => [
                    ['slug' => 'honda-timing-belt', 'label' => 'Honda timing belt'],
                ],
            ],
            [
                'id' => 'fluids',
                'name' => 'Oil and fluid service',
                'summary' => 'Oil leaks are diagnosed. Fluid changes are scheduled. Both are work we do.',
                'links' => [
                    ['slug' => 'car-fluid-service', 'label' => 'Fluid service'],
                    ['slug' => 'oil-leak', 'label' => 'Oil leak'],
                    ['slug' => 'brake-fluid-service', 'label' => 'Brake fluid service'],
                    ['slug' => 'transmission-fluid-change', 'label' => 'Transmission fluid change'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, name: string, summary: string, links: list<array{href: string, label: string}>}>
     */
    public static function categories(PublishedWebsite $website): array
    {
        $categories = [];

        foreach (self::definitions() as $definition) {
            $links = [];
            foreach ($definition['links'] as $link) {
                if ($website->problem($link['slug']) === null) {
                    continue;
                }

                $links[] = [
                    'href' => route('public.common-problems.show', $link['slug']),
                    'label' => $link['label'],
                ];
            }

            $categories[] = [
                'id' => $definition['id'],
                'name' => $definition['name'],
                'summary' => $definition['summary'],
                'links' => $links,
            ];
        }

        return $categories;
    }
}
