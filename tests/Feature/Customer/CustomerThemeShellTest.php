<?php

use App\Ark\Customer\CustomerSiteNavigation;
use App\Ark\Customer\CustomerSurfaceNavigation;
use App\Ark\Customer\Theme\CustomerThemeSource;
use App\Ark\Customer\Theme\CustomerThemes;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Blade;

function customerShellHtml(): string
{
    return Blade::render('<x-customer.shell>body</x-customer.shell>');
}

beforeEach(function () {
    ShopSettings::current()->update(['shop_name' => 'Fir Auto', 'phone' => '5550100199']);
    ShopSettings::forgetCurrent();
});

test('without a host theme source the customer shell uses Core defaults and the shop name', function () {
    if (! file_exists(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Vite build assets are not present.');
    }

    $html = customerShellHtml();

    expect($html)
        ->toContain('<style data-customer-theme>.public-surface{--public-brand:#0b5ed7;')
        ->toContain('data-customer-header="light"')
        ->toContain('<title>Fir Auto</title>')
        ->toContain('ark-32x32.png');
});

test('a host theme source sets the brand, header, fonts, and favicon of every customer page', function () {
    if (! file_exists(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Vite build assets are not present.');
    }

    app()->instance(CustomerThemeSource::class, new class implements CustomerThemeSource
    {
        public function theme(): ?array
        {
            return ['brand' => '#e37b24', 'header' => 'dark', 'font_body' => 'roboto', 'favicon' => '/_sites/assets/0123456789abcdef0123456789abcdef01234567.png'];
        }
    });

    $html = customerShellHtml();

    expect($html)
        ->toContain('--public-brand:#e37b24;')
        ->toContain('data-customer-header="dark"')
        ->toContain('family=roboto:400,500,700,900')
        ->toContain('<link rel="icon" href="/_sites/assets/0123456789abcdef0123456789abcdef01234567.png">')
        ->not->toContain('ark-32x32.png');
});

test('a failing theme source never breaks a customer page', function () {
    app()->instance(CustomerThemeSource::class, new class implements CustomerThemeSource
    {
        public function theme(): ?array
        {
            throw new RuntimeException('website unavailable');
        }
    });

    expect(CustomerThemes::current()->toArray()['brand'])->toBe('#0b5ed7');
});

test('a host site navigation replaces the website links, keeps booking and sign-in, and drops links off this host', function () {
    app()->instance(CustomerSiteNavigation::class, new class implements CustomerSiteNavigation
    {
        public function links(): ?array
        {
            return [
                ['label' => 'Home', 'href' => '/', 'active' => true],
                ['label' => 'Services', 'href' => '/services', 'active' => false],
                ['label' => 'Elsewhere', 'href' => 'https://evil.test/', 'active' => false],
                ['label' => 'Protocol relative', 'href' => '//evil.test/', 'active' => false],
                ['label' => '', 'href' => '/blank', 'active' => false],
            ];
        }
    });

    $items = app(CustomerSurfaceNavigation::class)->items();
    $labels = array_column($items, 'label');

    expect($labels)->toBe(['Home', 'Services', 'Request an appointment', 'Sign In'])
        ->and(array_column($items, 'href'))->not->toContain('https://evil.test/')->not->toContain('//evil.test/');
});

test('a host site navigation that returns null keeps Core links', function () {
    app()->instance(CustomerSiteNavigation::class, new class implements CustomerSiteNavigation
    {
        public function links(): ?array
        {
            return null;
        }
    });

    expect(array_column(app(CustomerSurfaceNavigation::class)->shopLinks(), 'label'))->toContain('Problems', 'Warranty', 'Financing');
});
