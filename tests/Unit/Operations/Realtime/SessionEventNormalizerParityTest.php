<?php

use App\Ark\Operations\Realtime\CanonicalSessionStream;
use App\Ark\Operations\Realtime\Normalizers\TwilioSessionEventNormalizer;
use App\Ark\Operations\Realtime\Scenarios\StandardSessionLifecycleScenario;
use App\Ark\Operations\Realtime\SessionEventIngress;
use App\Ark\Operations\Telephony\TelephonyProviderType;

test('twilio normalizer produces golden canonical stream', function (): void {
    $golden = StandardSessionLifecycleScenario::goldenStream();
    $normalizer = new TwilioSessionEventNormalizer;

    $events = [];

    foreach (StandardSessionLifecycleScenario::twilioRawEvents(1, 2) as $raw) {
        $canonical = $normalizer->fromRaw($raw);

        if ($canonical !== null) {
            $events[] = $canonical;
        }
    }

    $stream = new CanonicalSessionStream($events);

    expect($stream->equals($golden))->toBeTrue();
});

test('session event ingress normalizes twilio stream to golden fixture', function (): void {
    $ingress = app(SessionEventIngress::class);
    $golden = StandardSessionLifecycleScenario::goldenStream();

    $twilio = $ingress->normalizeRawStream(
        TelephonyProviderType::Twilio,
        StandardSessionLifecycleScenario::twilioRawEvents(10, 20),
    );

    expect($twilio->equals($golden))->toBeTrue();
});

test('twilio normalizer is pure and does not require laravel boot beyond helpers', function (): void {
    $twilio = new TwilioSessionEventNormalizer;

    expect($twilio->fromRaw(['CallStatus' => 'ringing', 'CallSid' => 'CA123', 'From' => '+17195550100', 'To' => '+17195551000']))
        ->not->toBeNull();
});
