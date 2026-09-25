<?php

namespace App\Ark\Website;

/**
 * Plain-text description of the published website for /llms.txt.
 *
 * Uses the current publication and shop settings. Does not call Foundry or Platform.
 */
final class PublicLlmsTxt
{
    public static function body(PublishedWebsite $website): string
    {
        $lines = ['# '.$website->shopName(), ''];

        foreach ([$website->headline(), $website->lede(), $website->localTagline()] as $line) {
            if ($line !== '') {
                $lines[] = '> '.$line;
            }
        }

        $lines[] = '';
        $lines[] = 'Canonical: '.$website->canonicalUrl('/');
        $lines[] = '';
        $lines[] = '## Location';

        if ($website->address() !== '') {
            $lines[] = $website->address();
        }
        if ($website->phone() !== '') {
            $lines[] = 'Phone: '.$website->phoneDisplay();
        }
        if ($website->email() !== '') {
            $lines[] = 'Email: '.$website->email();
        }

        $services = $website->serviceNames();
        if ($services !== []) {
            $lines[] = '';
            $lines[] = '## Services';
            foreach ($services as $service) {
                $lines[] = '- '.$service;
            }
        }

        $facts = [];
        $warranty = $website->page('warranty');
        if ($warranty['lede'] !== '') {
            $facts[] = 'Shop warranty: '.$warranty['lede'];
        }
        $repairPalWarranty = $website->page('repairpal-warranty');
        if ($repairPalWarranty['lede'] !== '') {
            $facts[] = 'RepairPal warranty: '.$repairPalWarranty['lede'];
        }
        if ($website->financingLede() !== '') {
            $facts[] = 'Financing: '.$website->financingLede();
        }
        if ($facts !== []) {
            $lines[] = '';
            $lines[] = '## Published facts';
            foreach ($facts as $fact) {
                $lines[] = '- '.$fact;
            }
        }

        $pages = [
            '/' => 'Home',
            '/about' => 'About',
            '/book' => 'Appointment request',
            '/contact' => 'Contact',
            '/common-problems' => 'Common problems',
        ];
        foreach ([
            'financing' => '/financing',
            'warranty' => '/warranty',
            'privacy' => '/privacy',
            'terms' => '/terms',
            'repairpal' => '/repairpal',
            'repairpal-certified' => '/repairpal-certified',
            'repairpal-reviews' => '/repairpal-reviews',
            'repairpal-warranty' => '/repairpal-warranty',
        ] as $key => $path) {
            if ($key === 'financing') {
                if ($website->financingLede() !== '' || $website->financingPrograms() !== []) {
                    $pages[$path] = 'Financing';
                }

                continue;
            }
            if ($website->page($key)['title'] !== '') {
                $pages[$path] = $website->page($key)['title'];
            }
        }

        $lines[] = '';
        $lines[] = '## Pages';
        foreach ($pages as $path => $label) {
            $lines[] = '- '.$label.': '.$website->canonicalUrl($path);
        }

        $problems = [];
        foreach ($website->problems() as $problem) {
            $slug = trim((string) ($problem['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $title = trim((string) ($problem['title'] ?? $slug));
            $problems[] = '- '.$title.': '.$website->canonicalUrl('/common-problems/'.$slug);
        }
        if ($problems !== []) {
            $lines[] = '';
            $lines[] = '## Common problems';
            array_push($lines, ...$problems);
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
