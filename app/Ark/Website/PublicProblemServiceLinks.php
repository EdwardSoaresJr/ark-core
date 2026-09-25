<?php

namespace App\Ark\Website;

use Illuminate\Support\Facades\Route;

/**
 * Curated links from a complaint or code to a published service page.
 */
final class PublicProblemServiceLinks
{
    /**
     * @var array<string, list<string>>
     */
    private const LINKS = [
        'check-engine-light' => ['diagnostics'],
        'car-wont-start' => ['diagnostics'],
        'rough-idle' => ['diagnostics'],
        'misfire-under-load' => ['diagnostics'],
        'brake-noise' => ['brakes'],
        'abs-light' => ['brakes'],
        'p0300' => ['diagnostics'],
        'p0301' => ['diagnostics'],
        'p0302' => ['diagnostics'],
        'p0303' => ['diagnostics'],
        'p0304' => ['diagnostics'],
        'p0171' => ['diagnostics'],
        'p0174' => ['diagnostics'],
        'p0420' => ['diagnostics'],
        'p0430' => ['diagnostics'],
        'p0442' => ['diagnostics'],
        'p0455' => ['diagnostics'],
        'p0128' => ['diagnostics'],
        'p0101' => ['diagnostics'],
        'p0401' => ['diagnostics'],
        'p0340' => ['diagnostics'],
        'p0135' => ['diagnostics'],
        'p0016' => ['diagnostics'],
    ];

    /**
     * @var array<string, string>
     */
    private const NOTES = [
        'diagnostics' => 'See how we test a vehicle before recommending parts.',
        'brakes' => 'See how we measure what is worn before we replace it.',
        'maintenance' => 'See how we follow the interval the vehicle actually has.',
    ];

    /**
     * @return list<array{href: string, label: string, note: string}>
     */
    public static function forSlug(string $slug): array
    {
        if (! Route::has('public.services.show')) {
            return [];
        }

        $links = [];
        foreach (self::LINKS[$slug] ?? [] as $id) {
            $page = PublicServicePages::find($id);
            if ($page === null) {
                continue;
            }

            $links[] = [
                'href' => route('public.services.show', ['service' => $id]),
                'label' => $page['name'],
                'note' => self::NOTES[$id] ?? '',
            ];
        }

        return $links;
    }
}
