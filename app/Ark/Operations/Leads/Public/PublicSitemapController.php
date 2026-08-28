<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Sitemap\SitemapEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicSitemapController
{
    public function __invoke(Request $request, SitemapEngine $sitemap): Response
    {
        abort_unless(PublicMarketingUrl::servesPublicSeo($request), 404);

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemap->allEntries() as $entry) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.e($entry['loc']).'</loc>';
            $xml[] = '    <lastmod>'.e($entry['lastmod']).'</lastmod>';
            $xml[] = '    <changefreq>'.e($entry['changefreq']).'</changefreq>';
            $xml[] = '    <priority>'.e($entry['priority']).'</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
