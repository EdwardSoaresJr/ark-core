<?php

use App\Ark\Platform\Website\WebsiteDraftDocumentMerger;

it('edits one homepage field without changing other document fields', function (): void {
    $document = storedWebsiteDocument();
    $merged = (new WebsiteDraftDocumentMerger)->merge($document, formInput([
        'headline' => 'Edited headline',
        'wisetack_url' => null,
        'synchrony_url' => '',
        'facebook_url' => null,
        'instagram_url' => null,
        'nextdoor_url' => null,
        'youtube_url' => null,
        'google_rating' => 4.9,
        'google_review_count' => '56',
    ]));

    $expected = $document;
    $expected['headline'] = 'Edited headline';

    expect($merged)->toBe($expected)
        ->and(array_key_exists('facebook', $merged['social_profiles'] ?? []))->toBeFalse();
});

it('keeps absent keys, empty strings, and nulls when the form sends blanks', function (): void {
    $document = storedWebsiteDocument();
    unset($document['social_profiles']['youtube_url']);
    $document['local_tagline'] = '';
    $document['customer_quote'] = null;

    $merged = (new WebsiteDraftDocumentMerger)->merge($document, formInput([
        'local_tagline' => '',
        'customer_quote' => null,
        'youtube_url' => null,
        'facebook_url' => null,
    ]));

    expect($merged)->toBe($document)
        ->and(array_key_exists('youtube_url', $merged['social_profiles']))->toBeFalse()
        ->and($merged['social_profiles']['facebook_url'])->toBeNull()
        ->and($merged['local_tagline'])->toBe('')
        ->and($merged['customer_quote'])->toBeNull();
});

it('updates one trust url without rewriting sibling trust or social fields', function (): void {
    $document = storedWebsiteDocument();

    $merged = (new WebsiteDraftDocumentMerger)->merge($document, formInput([
        'synchrony_url' => 'https://example.test/synchrony',
        'wisetack_url' => null,
        'facebook_url' => null,
    ]));

    expect($merged['trust_signals']['synchrony_url'])->toBe('https://example.test/synchrony')
        ->and($merged['trust_signals']['wisetack_url'])->toBe($document['trust_signals']['wisetack_url'])
        ->and($merged['trust_signals']['repairpal_certified'])->toBeTrue()
        ->and($merged['social_profiles'])->toBe($document['social_profiles'])
        ->and($merged['shop_photos'])->toBe($document['shop_photos']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function formInput(array $overrides = []): array
{
    return array_merge([
        'headline' => 'Accurate Diagnostics. Honest Repairs.',
        'positioning_lede' => 'We find the real problem first.',
        'google_rating' => '4.9',
        'google_review_count' => 56,
        'google_reviews_url' => 'https://g.page/r/example/review',
        'local_tagline' => 'Family owned.',
        'customer_quote' => 'Clear estimate.',
        'customer_quote_attribution' => 'Eric',
        'response_time_hint' => 'Within an hour.',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function storedWebsiteDocument(): array
{
    return [
        'headline' => 'Accurate Diagnostics. Honest Repairs.',
        'positioning_lede' => 'We find the real problem first.',
        'google_rating' => '4.9',
        'google_review_count' => 56,
        'google_reviews_url' => 'https://g.page/r/example/review',
        'local_tagline' => 'Family owned.',
        'customer_quote' => 'Clear estimate.',
        'customer_quote_attribution' => 'Eric',
        'response_time_hint' => 'Within an hour.',
        'shop_photos' => [
            ['path' => 'public-surface-photos/bay.jpg', 'alt' => 'Bay'],
        ],
        'trust_signals' => [
            'repairpal_certified' => true,
            'repairpal_url' => 'https://www.repairpal.com/example',
            'financing_available' => true,
            'wisetack_url' => 'https://wisetack.us/#/uz8sh8e/prequalify',
            'synchrony_url' => 'https://www.synchrony.com/mmc/CR243778456?sitecode=acewel401',
        ],
        'social_profiles' => [
            'facebook_url' => null,
            'instagram_url' => 'https://instagram.com/lugsnplugs',
            'nextdoor_url' => '',
        ],
    ];
}
