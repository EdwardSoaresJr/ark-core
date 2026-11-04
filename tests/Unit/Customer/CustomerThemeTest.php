<?php

use App\Ark\Customer\Theme\CustomerTheme;
use App\Ark\Customer\Theme\ThemeColor;

test('the default theme is a neutral shop identity that meets WCAG AA', function () {
    $vars = CustomerTheme::default()->variables();

    expect($vars['all']['--public-brand'])->toBe('#0b5ed7')
        ->and($vars['all']['--public-on-brand'])->toBe('#ffffff')
        ->and($vars['light']['--public-link'])->toBe('#0b5ed7')
        ->and(ThemeColor::contrast($vars['all']['--public-brand'], $vars['all']['--public-on-brand']))->toBeGreaterThanOrEqual(4.5)
        ->and(CustomerTheme::default()->header())->toBe('light')
        ->and(CustomerTheme::default()->faviconUrl())->toBeNull()
        ->and(CustomerTheme::default()->fontStylesheets())->toBe(['https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap']);
});

test('a light brand color gets dark button text and a darkened link color that stays readable', function () {
    $theme = CustomerTheme::fromArray(['brand' => '#e37b24', 'ink' => '#1c1b17', 'surface' => '#f4f1ea']);
    $vars = $theme->variables();

    expect($vars['all']['--public-on-brand'])->toBe('#1c1b17')
        ->and(ThemeColor::contrast('#e37b24', '#1c1b17'))->toBeGreaterThanOrEqual(4.5)
        ->and($vars['light']['--public-link'])->not->toBe('#e37b24')
        ->and(ThemeColor::contrast($vars['light']['--public-link'], '#f4f1ea'))->toBeGreaterThanOrEqual(4.5)
        ->and(ThemeColor::contrast($vars['light']['--public-link'], '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and(ThemeColor::contrast($vars['dark']['--public-link'], '#0f172a'))->toBeGreaterThanOrEqual(4.5);
});

test('every brand color yields readable button text and links', function (string $brand) {
    $vars = CustomerTheme::fromArray(['brand' => $brand])->variables();

    expect(ThemeColor::contrast($vars['all']['--public-brand'], $vars['all']['--public-on-brand']))->toBeGreaterThanOrEqual(4.5)
        ->and(ThemeColor::contrast($vars['light']['--public-link'], '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and(ThemeColor::contrast($vars['dark']['--public-link'], '#0f172a'))->toBeGreaterThanOrEqual(4.5);
})->with(['#ffff00', '#00ff00', '#0099cc', '#777777', '#ffffff', '#000000', '#c41e3a', '#7c3aed']);

test('invalid values fall back to the default one by one and nothing free-form reaches the stylesheet', function () {
    $theme = CustomerTheme::fromArray([
        'brand' => 'red;}</style><script>alert(1)</script>',
        'accent' => 'url(https://evil.test/x)',
        'ink' => '#eeeeee',
        'surface' => '#111111',
        'font_body' => "x'}body{display:none",
        'font_heading' => 'oswald',
        'radius' => '0;background:url(x)',
        'header' => 'dark',
        'favicon' => 'https://evil.test/icon.png',
    ]);
    $css = $theme->css();

    expect($theme->toArray())->toMatchArray([
        'brand' => '#0b5ed7', 'accent' => '#f59f00', 'ink' => '#0f172a', 'surface' => '#ffffff',
        'font_body' => 'figtree', 'font_heading' => 'oswald', 'radius' => 'soft', 'header' => 'dark', 'favicon' => null,
    ])
        ->and($css)->not->toContain('<')->not->toContain('url(')->not->toContain('evil')->not->toContain('alert')
        ->and($theme->fontStylesheets())->toBe(['https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|oswald:400,500,600,700&display=swap']);
});

test('a favicon must be a path on the shop host', function (mixed $favicon, ?string $expected) {
    expect(CustomerTheme::fromArray(['favicon' => $favicon])->faviconUrl())->toBe($expected);
})->with([
    ['/_sites/assets/0123456789abcdef0123456789abcdef01234567.png', '/_sites/assets/0123456789abcdef0123456789abcdef01234567.png'],
    ['//evil.test/icon.png', null],
    ['https://evil.test/icon.png', null],
    ['/../../etc/passwd', null],
    ['/icon.png" onload="alert(1)', null],
    ['javascript:alert(1)', null],
    [42, null],
]);

test('the system font loads nothing from outside', function () {
    expect(CustomerTheme::fromArray(['font_body' => 'system', 'font_heading' => 'system'])->fontStylesheets())->toBe([]);
});
