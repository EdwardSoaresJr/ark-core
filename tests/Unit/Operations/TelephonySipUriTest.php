<?php

use App\Ark\Operations\Telephony\TelephonySipUri;

test('sip uri parser extracts dialed number from sip to header', function () {
    expect(TelephonySipUri::dialedNumber('+17195551234'))->toBe('7195551234')
        ->and(TelephonySipUri::dialedNumber('sip:+17195551234@example.sip.us1.twilio.com'))->toBe('7195551234')
        ->and(TelephonySipUri::dialedNumber('sip:7195551234@example.sip.us1.twilio.com'))->toBe('7195551234');
});

test('sip uri parser normalizes endpoint addresses for matching', function () {
    expect(TelephonySipUri::normalizeForMatch('101@example.sip.us1.twilio.com'))
        ->toBe('sip:101@example.sip.us1.twilio.com');
});
