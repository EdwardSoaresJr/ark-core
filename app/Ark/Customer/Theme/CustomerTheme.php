<?php

declare(strict_types=1);

namespace App\Ark\Customer\Theme;

/**
 * The shop's visual identity on every customer page (website, booking, portal, estimates,
 * inspections, payments): colors, type, corners, and header treatment. Business identity
 * (name, logo, phone, address, hours) stays in ShopSettings.
 *
 * Values come from a CustomerThemeSource when the host binds one (a hosted website builder, for
 * example), otherwise Core's defaults. Every value is validated against a hex pattern or a fixed list, so nothing a
 * shop stores reaches the page as free-form CSS or as an outside URL.
 */
final class CustomerTheme
{
    public const DEFAULTS = [
        'brand' => '#0b5ed7',
        'accent' => '#f59f00',
        'ink' => '#0f172a',
        'surface' => '#ffffff',
        'font_body' => 'figtree',
        'font_heading' => 'figtree',
        'radius' => 'soft',
        'header' => 'light',
        'favicon' => null,
    ];

    /** Font key => [CSS stack, fonts.bunny.net family spec or null for system fonts]. */
    public const FONTS = [
        'figtree' => ["'Figtree', ui-sans-serif, system-ui, sans-serif", 'figtree:400,500,600,700,800'],
        'inter' => ["'Inter', ui-sans-serif, system-ui, sans-serif", 'inter:400,500,600,700,800'],
        'roboto' => ["'Roboto', ui-sans-serif, system-ui, sans-serif", 'roboto:400,500,700,900'],
        'source-sans-3' => ["'Source Sans 3', ui-sans-serif, system-ui, sans-serif", 'source-sans-3:400,500,600,700,800'],
        'barlow' => ["'Barlow', ui-sans-serif, system-ui, sans-serif", 'barlow:400,500,600,700,800'],
        'montserrat' => ["'Montserrat', ui-sans-serif, system-ui, sans-serif", 'montserrat:400,500,600,700,800'],
        'oswald' => ["'Oswald', ui-sans-serif, system-ui, sans-serif", 'oswald:400,500,600,700'],
        'merriweather' => ["'Merriweather', ui-serif, Georgia, serif", 'merriweather:400,700,900'],
        'system' => ['ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', null],
    ];

    public const FONT_LABELS = [
        'figtree' => 'Figtree (rounded, friendly)',
        'inter' => 'Inter (clean, modern)',
        'roboto' => 'Roboto (neutral)',
        'source-sans-3' => 'Source Sans (readable)',
        'barlow' => 'Barlow (industrial)',
        'montserrat' => 'Montserrat (geometric)',
        'oswald' => 'Oswald (condensed, headings)',
        'merriweather' => 'Merriweather (serif, traditional)',
        'system' => 'Device default',
    ];

    /** Radius key => [buttons and inputs, cards and panels]. */
    public const RADII = [
        'square' => ['0.125rem', '0.25rem'],
        'soft' => ['0.375rem', '0.5rem'],
        'round' => ['0.75rem', '0.875rem'],
        'pill' => ['9999px', '0.875rem'],
    ];

    public const RADIUS_LABELS = ['square' => 'Square', 'soft' => 'Slightly rounded', 'round' => 'Rounded', 'pill' => 'Pill buttons'];

    public const HEADERS = ['light' => 'Light', 'dark' => 'Dark', 'brand' => 'Main color'];

    private const DARK_SURFACE = '#0f172a';

    /** @param array<string, string|null> $values */
    private function __construct(private readonly array $values) {}

    public static function default(): self
    {
        return self::fromArray([]);
    }

    /**
     * Unknown or invalid values fall back to Core's defaults one by one. The page background must be
     * light and the ink dark, so body text always keeps its contrast.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $d = self::DEFAULTS;
        $color = fn (string $key): string => ThemeColor::normalize($input[$key] ?? null) ?? $d[$key];
        $choice = fn (string $key, array $allowed): string => is_string($input[$key] ?? null) && array_key_exists($input[$key], $allowed) ? $input[$key] : $d[$key];

        $surface = $color('surface');
        if (ThemeColor::luminance($surface) < 0.8) {
            $surface = $d['surface'];
        }
        $ink = $color('ink');
        if (ThemeColor::contrast($ink, '#ffffff') < 10 || ThemeColor::contrast($ink, $surface) < 7) {
            $ink = $d['ink'];
        }

        return new self([
            'brand' => $color('brand'),
            'accent' => $color('accent'),
            'ink' => $ink,
            'surface' => $surface,
            'font_body' => $choice('font_body', self::FONTS),
            'font_heading' => $choice('font_heading', self::FONTS),
            'radius' => $choice('radius', self::RADII),
            'header' => $choice('header', self::HEADERS),
            'favicon' => self::sameOriginPath($input['favicon'] ?? null),
        ]);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function header(): string
    {
        return (string) $this->values['header'];
    }

    public function faviconUrl(): ?string
    {
        return $this->values['favicon'];
    }

    /**
     * The browser icon for a shop without one of its own: its initial on its main color, as an inline
     * SVG. Only a letter or digit from the name and a generated color reach the markup.
     */
    public function monogramIcon(string $shopName): string
    {
        preg_match('/[A-Za-z0-9]/', $shopName, $initial);
        $letter = strtoupper($initial[0] ?? '');
        $fill = $this->variables()['all']['--public-brand'];
        $text = $this->variables()['all']['--public-on-brand'];
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='{$fill}'/>"
            .($letter === '' ? '' : "<text x='32' y='45' text-anchor='middle' font-family='Arial,Helvetica,sans-serif' font-size='38' font-weight='700' fill='{$text}'>{$letter}</text>")
            .'</svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    /** @return list<string> stylesheets for the chosen fonts, all on fonts.bunny.net */
    public function fontStylesheets(): array
    {
        $families = array_values(array_unique(array_filter([
            self::FONTS[$this->values['font_body']][1],
            self::FONTS[$this->values['font_heading']][1],
        ])));

        return $families === [] ? [] : ['https://fonts.bunny.net/css?family='.implode('|', $families).'&display=swap'];
    }

    /**
     * Custom properties for .public-surface, derived so text on every surface meets WCAG AA:
     * link colors are the main color darkened (light pages) or lightened (dark mode) until they
     * reach 4.5:1, and text on filled buttons is white or ink, whichever reads better (a mid-tone
     * fill that neither reaches is darkened just enough for white).
     *
     * @return array{light: array<string, string>, dark: array<string, string>, all: array<string, string>}
     */
    public function variables(): array
    {
        $v = $this->values;
        $brand = (string) $v['brand'];
        $accent = (string) $v['accent'];
        $ink = (string) $v['ink'];
        $surface = (string) $v['surface'];
        $onBrand = ThemeColor::readableOn($brand, '#ffffff', $ink);
        if (ThemeColor::contrast($brand, $onBrand) < 4.5) {
            // Mid-tones reach 4.5:1 with neither white nor ink: the fill darkens just enough for white text.
            $brand = ThemeColor::untilContrast($brand, '#000000', ['#ffffff'], 4.5);
            $onBrand = '#ffffff';
        }
        $brandHover = ThemeColor::mix($brand, $onBrand === '#ffffff' ? '#000000' : '#ffffff', 0.14);
        $link = ThemeColor::untilContrast($brand, '#000000', [$surface, '#ffffff'], 4.5);
        $linkHover = ThemeColor::mix($link, '#000000', 0.18);
        $linkDark = ThemeColor::untilContrast($brand, '#ffffff', [self::DARK_SURFACE, '#111827'], 4.5);
        $linkDarkHover = ThemeColor::mix($linkDark, '#ffffff', 0.2);
        [$control, $card] = self::RADII[$v['radius']];

        return [
            'all' => [
                '--public-brand' => $brand,
                '--public-brand-rgb' => ThemeColor::channels($brand),
                '--public-brand-hover' => $brandHover,
                '--public-on-brand' => $onBrand,
                '--public-on-brand-rgb' => ThemeColor::channels($onBrand),
                '--public-accent' => $accent,
                '--public-accent-rgb' => ThemeColor::channels($accent),
                '--public-on-accent' => ThemeColor::readableOn($accent, '#ffffff', $ink),
                '--public-ink' => $ink,
                '--public-ink-rgb' => ThemeColor::channels($ink),
                '--public-on-ink' => '#ffffff',
                '--public-font-body' => self::FONTS[$v['font_body']][0],
                '--public-font-heading' => self::FONTS[$v['font_heading']][0],
                '--public-radius-control' => $control,
                '--public-radius' => $card,
                '--public-radius-media' => $card,
            ],
            'light' => [
                '--public-link' => $link,
                '--public-link-rgb' => ThemeColor::channels($link),
                '--public-link-hover' => $linkHover,
                '--public-surface-shop' => $surface,
                '--public-surface-shop-alt' => ThemeColor::mix($surface, $ink, 0.035),
                '--public-text' => $ink,
                '--public-border' => 'rgb('.ThemeColor::channels($ink).' / 0.12)',
            ],
            'dark' => [
                '--public-link' => $linkDark,
                '--public-link-rgb' => ThemeColor::channels($linkDark),
                '--public-link-hover' => $linkDarkHover,
            ],
        ];
    }

    /** The stylesheet body for the shell's theme block; every value is generated, never stored text. */
    public function css(): string
    {
        $block = fn (array $vars): string => implode('', array_map(fn (string $k, string $val): string => $k.':'.$val.';', array_keys($vars), $vars));
        $vars = $this->variables();

        return '.public-surface{'.$block($vars['all']).'}'
            .'html:not(.dark) .public-surface{'.$block($vars['light']).'}'
            .'html.dark .public-surface{'.$block($vars['dark']).'}';
    }

    /** A path on the shop's own host (no scheme, no other host, no traversal), or null. */
    private static function sameOriginPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return preg_match('#^/(?!/)[A-Za-z0-9._~/-]{1,240}$#', $value) === 1 && ! str_contains($value, '..') ? $value : null;
    }
}
