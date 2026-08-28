<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use App\Ark\Growth\Sitemap\SitemapEngine;
use Illuminate\Http\Response;

final class GrowthSitemapIndexController
{
    public function __invoke(SitemapEngine $engine): Response
    {
        abort_unless(GrowthIntegrationSettings::current()->isPublicSitemapEnabled(), 404);

        $entries = $engine->indexEntries();

        if ($entries === []) {
            return response($this->renderUrlset($engine->allEntries()), 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ]);
        }

        return response($this->renderIndex($entries), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<array{loc: string, lastmod: string}>  $entries
     */
    private function renderIndex(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($entries as $entry) {
            $xml .= '<sitemap>';
            $xml .= '<loc>'.e($entry['loc']).'</loc>';
            $xml .= '<lastmod>'.e($entry['lastmod']).'</lastmod>';
            $xml .= '</sitemap>';
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * @param  list<array{loc: string, lastmod: string, changefreq: string, priority: string}>  $entries
     */
    private function renderUrlset(array $entries): string
    {
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

        return $xml;
    }
}
