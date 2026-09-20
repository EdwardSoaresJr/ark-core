<?php

namespace App\Ark\Operations\Portal;

use Illuminate\Http\Request;

/**
 * Suppress portal "customer opened" attribution for SMS/iMessage link-preview crawlers.
 * Page still renders; EstimateViewed / last_viewed_at must not fire for automated fetches.
 *
 * iMessage previews are especially sneaky: the request comes from the phone's IP with a
 * Safari-looking UA that also embeds facebookexternalhit / Facebot / Twitterbot so OG
 * tags are served. Matching those tokens (below) is the reliable signal — not Applebot.
 */
final class PortalCustomerViewGate
{
    /**
     * Known preview/crawler tokens in User-Agent (substring, case-insensitive).
     *
     * @var list<string>
     */
    private const USER_AGENT_MARKERS = [
        'applebot',
        'bingpreview',
        'bingbot',
        'bytespider',
        'com.apple.webkit.networking',
        'discordbot',
        'duckduckbot',
        'embedly',
        'facebookexternalhit',
        'facebot',
        'googlebot',
        'google-read-aloud',
        'google-pagerenderer',
        'headlesschrome',
        'ia_archiver',
        'iframely',
        'linkedinbot',
        'meta-externalagent',
        'meta-externalfetcher',
        'petalbot',
        'pinterest',
        'quora link preview',
        'redditbot',
        'skypeuripreview',
        'slackbot',
        'snap url preview',
        'telegrambot',
        'twitterbot',
        'vkshare',
        'whatsapp',
        'yandexbot',
        'preview',
        'spider',
        'crawler',
        'crawl/',
        'bot/',
        'bot;',
        'wget',
        'curl/',
        'python-requests',
        'go-http-client',
        'scrapy',
        'phantomjs',
        'selenium',
        'httpclient',
        'libwww-perl',
        'okhttp',
    ];

    public static function shouldRecordCustomerView(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (self::hasPreviewOrPrefetchPurpose($request)) {
            return false;
        }

        if (self::hasNonUserFetchMetadata($request)) {
            return false;
        }

        if (self::userAgentLooksAutomated($request->userAgent())) {
            return false;
        }

        return true;
    }

    private static function hasPreviewOrPrefetchPurpose(Request $request): bool
    {
        foreach (['Purpose', 'Sec-Purpose', 'X-Purpose', 'X-Moz'] as $header) {
            $value = strtolower(trim((string) $request->headers->get($header, '')));
            if ($value === '') {
                continue;
            }

            if (str_contains($value, 'prefetch') || str_contains($value, 'preview')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch Metadata: automated previews often omit Sec-Fetch-User while sending Mode.
     * Require ?1 only when Mode is present — never require the header for older phones.
     *
     * Do not require Sec-Fetch-User whenever Mode is set: browsers omit User on
     * redirect follow-ups (http→https), which would miss real customer opens.
     */
    private static function hasNonUserFetchMetadata(Request $request): bool
    {
        $mode = strtolower(trim((string) $request->headers->get('Sec-Fetch-Mode', '')));
        $user = trim((string) $request->headers->get('Sec-Fetch-User', ''));
        $dest = strtolower(trim((string) $request->headers->get('Sec-Fetch-Dest', '')));

        if (in_array($mode, ['no-cors', 'cors'], true) && $dest !== 'document' && $dest !== 'iframe') {
            return true;
        }

        if ($mode === 'navigate' && $user !== '' && $user !== '?1') {
            return true;
        }

        return false;
    }

    private static function userAgentLooksAutomated(?string $userAgent): bool
    {
        $ua = strtolower(trim((string) $userAgent));

        if ($ua === '') {
            // Empty UA is common for scrapers; real phones always send one.
            return true;
        }

        foreach (self::USER_AGENT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }
}
