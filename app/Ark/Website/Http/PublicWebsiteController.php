<?php

namespace App\Ark\Website\Http;

use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Website\PublishedWebsite;
use App\Ark\Website\PublishedWebsiteResolver;
use App\Ark\Website\WebsiteHosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class PublicWebsiteController
{
    public function __construct(
        private readonly PublishedWebsiteResolver $websites,
        private readonly LeadRecorder $leads,
    ) {}

    public function home(Request $request): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        return view('website.home', [
            'website' => $website,
            'seo' => $this->seo($website, 'home', $request),
        ]);
    }

    public function book(Request $request): View|Response|RedirectResponse
    {
        return $this->formPage($request, 'book', 'website.book');
    }

    public function contact(Request $request): View|Response|RedirectResponse
    {
        return $this->formPage($request, 'contact', 'website.contact');
    }

    public function financing(Request $request): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        return view('website.financing', [
            'website' => $website,
            'seo' => $this->seo($website, 'financing', $request),
        ]);
    }

    public function page(Request $request, string $key): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        return view('website.page', [
            'website' => $website,
            'page' => $website->page($key),
            'seo' => $this->seo($website, $key, $request),
            'pageKey' => $key,
        ]);
    }

    public function problems(Request $request): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        return view('website.problems-index', [
            'website' => $website,
            'seo' => $this->seo($website, 'common_problems', $request),
        ]);
    }

    public function problem(Request $request, string $slug): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        $problem = $website->problem($slug);
        if ($problem === null) {
            return $this->missing();
        }

        $title = trim((string) ($problem['seo_title'] ?? $problem['page_title'] ?? $problem['title'] ?? 'Common problem'));
        $description = trim((string) ($problem['meta_description'] ?? ''));

        return view('website.problem', [
            'website' => $website,
            'problem' => $problem,
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $website->canonicalUrl('/common-problems/'.$slug),
                'shop_name' => $website->shopName(),
            ],
        ]);
    }

    public function thanks(Request $request): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        return view('website.thanks', [
            'website' => $website,
            'seo' => [
                'title' => 'Message received',
                'description' => 'Your message reached the shop.',
                'canonical' => $website->canonicalUrl('/leads/thanks'),
                'shop_name' => $website->shopName(),
            ],
            'indexable' => false,
        ]);
    }

    public function storeLead(Request $request): RedirectResponse|Response
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        if (filled($request->input('company_website'))) {
            return redirect()->route('public.leads.thanks');
        }

        $data = $request->validate([
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'concern' => ['required', 'string', 'max:2000'],
            'vehicle_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'vehicle_make' => ['nullable', 'string', 'max:80'],
            'vehicle_model' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'string', 'max:40'],
        ]);

        $phone = PhoneNumber::normalize($data['contact_phone']);
        if ($phone === null || strlen($phone) < 10) {
            return back()->withErrors(['contact_phone' => 'Enter a 10-digit phone number.'])->withInput();
        }

        $this->leads->recordWebsiteSubmission([
            'source' => LeadSource::Website,
            'concern' => $data['concern'],
            'contact_phone' => $phone,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'vehicle_year' => $data['vehicle_year'] ?? null,
            'vehicle_make' => $data['vehicle_make'] ?? null,
            'vehicle_model' => $data['vehicle_model'] ?? null,
            'metadata' => [
                'public_page' => $data['page'] ?? 'contact',
                'public_host' => $request->getHost(),
                'canonical_host' => $website->canonicalHost(),
            ],
        ]);

        return redirect()->route('public.leads.thanks');
    }

    public function robots(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->wwwRedirect($request)) {
            return $redirect;
        }

        $website = $this->websites->forRequest($request);
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /app/',
            'Disallow: /portal/',
            'Disallow: /leads/thanks',
            '',
        ];

        if ($website instanceof PublishedWebsite) {
            $lines[] = 'Sitemap: '.$website->canonicalUrl('/sitemap.xml');
            $lines[] = '';
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->wwwRedirect($request)) {
            return $redirect;
        }

        $paths = [
            '/',
            '/book',
            '/contact',
            '/common-problems',
            '/financing',
            '/warranty',
            '/privacy',
            '/terms',
        ];

        $website = $this->websites->forRequest($request);
        if (! $website instanceof PublishedWebsite) {
            $paths = [];
        } else {
            foreach ($website->problems() as $problem) {
                $slug = trim((string) ($problem['slug'] ?? ''));
                if ($slug !== '') {
                    $paths[] = '/common-problems/'.$slug;
                }
            }
        }

        $urls = '';
        foreach ($paths as $path) {
            $loc = htmlspecialchars($website->canonicalUrl($path), ENT_XML1);
            $urls .= "  <url><loc>{$loc}</loc></url>\n";
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$urls}</urlset>
XML;

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function formPage(Request $request, string $seoKey, string $view): View|Response|RedirectResponse
    {
        $website = $this->requireWebsite($request);
        if (! $website instanceof PublishedWebsite) {
            return $website;
        }

        return view($view, [
            'website' => $website,
            'seo' => $this->seo($website, $seoKey, $request),
        ]);
    }

    private function requireWebsite(Request $request): PublishedWebsite|Response|RedirectResponse
    {
        if ($redirect = $this->wwwRedirect($request)) {
            return $redirect;
        }

        return $this->websites->forRequest($request) ?? $this->missing();
    }

    private function wwwRedirect(Request $request): ?RedirectResponse
    {
        $apex = WebsiteHosts::wwwApex($request->getHost());
        if ($apex === null) {
            return null;
        }

        return redirect()->away('https://'.$apex.$request->getRequestUri(), 301);
    }

    private function missing(): Response
    {
        return response()->view('website.unpublished', [], 404);
    }

    /**
     * @return array{title: string, description: string, canonical: string, shop_name: string}
     */
    private function seo(PublishedWebsite $website, string $key, Request $request): array
    {
        $seo = $website->seo($key);
        $path = '/'.ltrim($request->path(), '/');
        if ($path === '//') {
            $path = '/';
        }

        return [
            'title' => $seo['title'],
            'description' => $seo['description'],
            'canonical' => $website->canonicalUrl($path),
            'shop_name' => $website->shopName(),
        ];
    }
}
