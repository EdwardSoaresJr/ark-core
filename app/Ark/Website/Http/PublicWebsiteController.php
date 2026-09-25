<?php

namespace App\Ark\Website\Http;

use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use App\Ark\Website\PublishedWebsite;
use App\Ark\Website\PublishedWebsiteResolver;
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

    public function home(Request $request): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
            return $website;
        }

        return view('website.home', [
            'website' => $website,
            'seo' => $this->seo($website, 'home', $request),
        ]);
    }

    public function book(Request $request): View|Response
    {
        return $this->formPage($request, 'book', 'website.book');
    }

    public function contact(Request $request): View|Response
    {
        return $this->formPage($request, 'contact', 'website.contact');
    }

    public function financing(Request $request): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
            return $website;
        }

        return view('website.financing', [
            'website' => $website,
            'seo' => $this->seo($website, 'financing', $request),
        ]);
    }

    public function page(Request $request, string $key): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
            return $website;
        }

        return view('website.page', [
            'website' => $website,
            'page' => $website->page($key),
            'seo' => $this->seo($website, $key, $request),
            'pageKey' => $key,
        ]);
    }

    public function problems(Request $request): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
            return $website;
        }

        return view('website.problems-index', [
            'website' => $website,
            'seo' => $this->seo($website, 'common_problems', $request),
        ]);
    }

    public function problem(Request $request, string $slug): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
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
            'seo' => $this->meta($request, $title, $description),
        ]);
    }

    public function thanks(Request $request): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
            return $website;
        }

        return view('website.thanks', [
            'website' => $website,
            'seo' => $this->meta($request, 'Message received', 'Your message reached the shop.'),
            'indexable' => false,
        ]);
    }

    public function storeLead(Request $request): RedirectResponse|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
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
            ],
        ]);

        return redirect()->route('public.leads.thanks');
    }

    public function robots(Request $request): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /app/',
            'Disallow: /portal/',
            'Disallow: /leads/thanks',
            '',
            'Sitemap: '.$this->absolute($request, '/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(Request $request): Response
    {
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
        if ($website instanceof PublishedWebsite) {
            foreach ($website->problems() as $problem) {
                $slug = trim((string) ($problem['slug'] ?? ''));
                if ($slug !== '') {
                    $paths[] = '/common-problems/'.$slug;
                }
            }
        }

        $urls = '';
        foreach ($paths as $path) {
            $loc = htmlspecialchars($this->absolute($request, $path), ENT_XML1);
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

    private function formPage(Request $request, string $seoKey, string $view): View|Response
    {
        $website = $this->requireWebsite($request);
        if ($website instanceof Response) {
            return $website;
        }

        return view($view, [
            'website' => $website,
            'seo' => $this->seo($website, $seoKey, $request),
        ]);
    }

    private function requireWebsite(Request $request): PublishedWebsite|Response
    {
        return $this->websites->forRequest($request) ?? $this->missing();
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

        return $this->meta($request, $seo['title'], $seo['description'], $website->shopName());
    }

    /**
     * @return array{title: string, description: string, canonical: string, shop_name: string}
     */
    private function meta(Request $request, string $title, string $description, string $shopName = ''): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $this->absolute($request, '/'.ltrim($request->path(), '/')),
            'shop_name' => $shopName,
        ];
    }

    private function absolute(Request $request, string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        if (SurfaceRouting::publicEnabled()) {
            $host = SurfaceRouting::publicHost();
            if (is_string($host) && $host !== '') {
                return SurfaceRouting::urlForHost($host, $path === '//' ? '/' : $path);
            }
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').($path === '/' ? '/' : $path);
    }
}
