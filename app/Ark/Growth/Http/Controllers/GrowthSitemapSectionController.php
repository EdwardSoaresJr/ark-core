<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Ark\Growth\Sitemap\SitemapEngine;
use App\Ark\Growth\Sitemap\SitemapSection;
use Illuminate\Http\Response;

final class GrowthSitemapSectionController
{
    public function __invoke(string $section, SitemapEngine $engine): Response
    {
        abort_unless(GrowthIntegrationSettings::current()->isPublicSitemapEnabled(), 404);

        $sectionEnum = SitemapSection::tryFrom($section);
        if ($sectionEnum === null) {
            abort(404);
        }

        $entries = $engine->entriesForSection($sectionEnum);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            $xml .= '<url>';
            $xml .= '<loc>'.e($entry['loc']).'</loc>';
            $xml .= '<lastmod>'.e($entry['lastmod']).'</lastmod>';
            $xml .= '<changefreq>'.e($entry['changefreq']).'</changefreq>';
            $xml .= '<priority>'.e($entry['priority']).'</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
