<?php

use App\Ark\Operations\Messaging\SmsConsentKeyword;

test('sms consent keywords match exact stop and start bodies', function () {
    expect(SmsConsentKeyword::matchOptOut('STOP'))->toBe(SmsConsentKeyword::Stop)
        ->and(SmsConsentKeyword::matchOptOut(' stop '))->toBe(SmsConsentKeyword::Stop)
        ->and(SmsConsentKeyword::matchOptOut('UNSUBSCRIBE'))->toBe(SmsConsentKeyword::Unsubscribe)
        ->and(SmsConsentKeyword::matchOptIn('START'))->toBe(SmsConsentKeyword::Start)
        ->and(SmsConsentKeyword::matchOptIn('UNSTOP'))->toBe(SmsConsentKeyword::Unstop)
        ->and(SmsConsentKeyword::matchOptIn('YES'))->toBe(SmsConsentKeyword::Yes);
});

test('sms consent keywords ignore partial or conversational bodies', function () {
    expect(SmsConsentKeyword::matchOptOut('Please STOP texting me'))->toBeNull()
        ->and(SmsConsentKeyword::matchOptIn('Yes please approve'))->toBeNull();
});
