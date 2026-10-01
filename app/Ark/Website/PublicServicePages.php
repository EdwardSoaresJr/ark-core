<?php

namespace App\Ark\Website;

/**
 * Diagnostics, brakes, and maintenance.
 *
 * Each page is a lede, sections, links to existing articles, and one appointment request.
 */
final class PublicServicePages
{
    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return ['diagnostics', 'brakes', 'maintenance'];
    }

    public static function has(string $id): bool
    {
        return in_array($id, self::ids(), true);
    }

    public static function path(string $id): ?string
    {
        return self::has($id) ? '/services/'.$id : null;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     lede: string,
     *     description: string,
     *     index_label: string,
     *     concern: string,
     *     promise: string,
     *     close_heading: string,
     *     close_lede: string,
     *     sections: list<array{heading: string, paragraphs: list<string>, items: list<string>}>,
     *     related: list<array{slug: string, label: string}>
     * }|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::definitions() as $page) {
            if ($page['id'] === $id) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     id: string,
     *     name: string,
     *     lede: string,
     *     description: string,
     *     index_label: string,
     *     concern: string,
     *     promise: string,
     *     close_heading: string,
     *     close_lede: string,
     *     sections: list<array{heading: string, paragraphs: list<string>, items: list<string>}>,
     *     related: list<array{slug: string, label: string}>
     * }  $page
     * @return array{
     *     id: string,
     *     name: string,
     *     lede: string,
     *     description: string,
     *     index_label: string,
     *     concern: string,
     *     promise: string,
     *     close_heading: string,
     *     close_lede: string,
     *     sections: list<array{heading: string, paragraphs: list<string>, items: list<string>}>,
     *     related: list<array{href: string, label: string}>
     * }
     */
    public static function present(PublishedWebsite $website, array $page): array
    {
        $related = [];
        foreach ($page['related'] as $link) {
            $problem = $website->problem($link['slug']);
            if ($problem === null) {
                continue;
            }

            $title = trim((string) ($problem['title'] ?? ''));
            $related[] = [
                'href' => route('public.common-problems.show', $link['slug']),
                'label' => $title !== '' ? $title : $link['label'],
            ];
        }

        $page['related'] = $related;

        return $page;
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     lede: string,
     *     description: string,
     *     index_label: string,
     *     concern: string,
     *     promise: string,
     *     close_heading: string,
     *     close_lede: string,
     *     sections: list<array{heading: string, paragraphs: list<string>, items: list<string>}>,
     *     related: list<array{slug: string, label: string}>
     * }>
     */
    private static function definitions(): array
    {
        return [
            [
                'id' => 'diagnostics',
                'name' => 'Diagnostics',
                'lede' => 'A complaint is where we start. We test the vehicle, show you what we found, and recommend parts only after that.',
                'description' => 'How LugsNPlugs diagnoses a complaint: testing, evidence, a finding, a recommendation, and a check after the repair.',
                'index_label' => 'How a diagnosis works',
                'concern' => 'I need a diagnosis before parts are recommended.',
                'promise' => 'We test the vehicle before we recommend parts.',
                'close_heading' => 'Not sure what your car needs?',
                'close_lede' => 'Tell us what it\'s doing. We\'ll start with the evidence.',
                'sections' => [
                    [
                        'heading' => 'Complaint',
                        'paragraphs' => [
                            'You tell us what the car is doing, when it happens, and what has already been tried. A warning light, a no-start, or a misfire is the complaint. It is not yet the repair.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Testing',
                        'paragraphs' => [
                            'We test the system against how it is supposed to work. A code, a noise, or a low reading is a clue. We do not treat the clue as the failed part.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Evidence',
                        'paragraphs' => [
                            'The test is whatever the complaint calls for: scan data, electrical measurements, fuel trims, a pressure or vacuum test, or a road test.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Finding',
                        'paragraphs' => [
                            'We tell you what failed, and what did not. If a previous shop named a part, we still test that claim against the car in front of us.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Recommendation',
                        'paragraphs' => [
                            'You see what needs attention now and what can wait. You decide what work to do.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Verification',
                        'paragraphs' => [
                            'After the repair, we check the same complaint again. The job is finished when the evidence says the problem is gone, not when a part is on the car.',
                        ],
                        'items' => [],
                    ],
                ],
                'related' => [
                    ['slug' => 'check-engine-light', 'label' => 'Check engine light'],
                    ['slug' => 'car-wont-start', 'label' => 'Car won\'t start'],
                    ['slug' => 'electrical-diagnostics', 'label' => 'Electrical diagnostics'],
                    ['slug' => 'misfire-under-load', 'label' => 'Misfire under load'],
                    ['slug' => 'rough-idle', 'label' => 'Rough idle'],
                    ['slug' => 'p0300', 'label' => 'P0300'],
                ],
            ],
            [
                'id' => 'brakes',
                'name' => 'Brakes',
                'lede' => 'Squeals, a soft pedal, a pull, or a warning light. We measure the brakes before we sell parts.',
                'description' => 'Brake inspection and repair at LugsNPlugs: what we measure, the repair standard, and how to request an appointment.',
                'index_label' => 'How brake repair works',
                'concern' => 'My brakes need to be inspected.',
                'promise' => 'We measure what is worn before we replace it.',
                'close_heading' => 'Not sure the noise is the brakes?',
                'close_lede' => 'Tell us what it\'s doing. We\'ll measure before we recommend parts.',
                'sections' => [
                    [
                        'heading' => 'Symptoms',
                        'paragraphs' => [],
                        'items' => [
                            'Squeal, grind, or a pedal that pulses',
                            'The car pulls while braking',
                            'A pedal that goes low or feels soft',
                            'A brake warning light',
                        ],
                    ],
                    [
                        'heading' => 'What we measure',
                        'paragraphs' => [
                            'Noise is not always a brake. A hum that rises with speed can be a wheel bearing. We separate those before recommending parts.',
                        ],
                        'items' => [
                            'Pad and rotor thickness',
                            'Whether the calipers move and the hoses are sound',
                            'Brake fluid condition, when the pedal or the fluid is part of the complaint',
                        ],
                    ],
                    [
                        'heading' => 'Repair standard',
                        'paragraphs' => [
                            'We replace what the measurement says is worn or unsafe. We do not replace a full set of parts because one side is noisy. After the work, the pedal and the original complaint are checked again.',
                        ],
                        'items' => [],
                    ],
                ],
                'related' => [
                    ['slug' => 'brake-noise', 'label' => 'Brake noise'],
                    ['slug' => 'brake-fluid-service', 'label' => 'Brake fluid service'],
                ],
            ],
            [
                'id' => 'maintenance',
                'name' => 'Maintenance',
                'lede' => 'Maintenance is how a working car stays reliable.',
                'description' => 'Fluids, scheduled maintenance, inspection, and what a tune-up means on a modern car at LugsNPlugs.',
                'index_label' => 'How maintenance works',
                'concern' => 'The car is due for maintenance.',
                'promise' => 'When the interval is known, we use it.',
                'close_heading' => 'Not sure what service is due?',
                'close_lede' => 'Tell us the mileage and what the car is doing.',
                'sections' => [
                    [
                        'heading' => 'Fluids',
                        'paragraphs' => [
                            'Oil, coolant, brake fluid, and transmission fluid each have a job. We change a fluid when it is due, contaminated, or part of a repair we already measured. An oil leak is diagnosed on its own.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Scheduled maintenance',
                        'paragraphs' => [
                            'When the manufacturer interval is known, we use it. When it is not, we look at the fluid, the mileage you give us, and what the car is doing.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Inspection',
                        'paragraphs' => [
                            'A maintenance visit is also a look at the things that fail quietly: leaks, belts, brakes, tires, and warning lights. We tell you what we found. You choose what to do.',
                        ],
                        'items' => [],
                    ],
                    [
                        'heading' => 'Tune-up',
                        'paragraphs' => [
                            'A tune-up used to mean plugs, points, and a carburetor. On a modern car it means the maintenance the engine actually needs: plugs when they are due, filters, and a check for a misfire or a rough idle.',
                        ],
                        'items' => [],
                    ],
                ],
                'related' => [
                    ['slug' => 'car-fluid-service', 'label' => 'Fluid service'],
                    ['slug' => 'oil-leak', 'label' => 'Oil leak'],
                ],
            ],
        ];
    }
}
