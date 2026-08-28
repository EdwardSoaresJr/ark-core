<?php

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Messaging\TwilioInboundMessageParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('forContactKey normalizes phone numbers onto the phone surface', function () {
    $resolver = app(ConversationResolver::class);

    $conversation = $resolver->forContactKey(ConversationContactSurface::Phone, '+1 (719) 555-1234');

    expect($conversation->contact_surface)->toBe(ConversationContactSurface::Phone)
        ->and($conversation->contact_address)->toBe('7195551234')
        ->and($resolver->findForContactKey(ConversationContactSurface::Phone, '7195551234')?->is($conversation))->toBeTrue();
});

test('forContactKey normalizes email addresses onto the email surface', function () {
    $resolver = app(ConversationResolver::class);

    $conversation = $resolver->forContactKey(ConversationContactSurface::Email, ' Customer@Example.TEST ');

    expect($conversation->contact_surface)->toBe(ConversationContactSurface::Email)
        ->and($conversation->contact_address)->toBe('customer@example.test');
});

test('twilio parser produces phone-surface inbound conversation payload', function () {
    $payload = app(TwilioInboundMessageParser::class)->parse(Request::create('/', 'POST', [
        'MessageSid' => 'SMparser001',
        'From' => '+17195551234',
        'To' => '+17195559999',
        'Body' => 'Parser test',
        'NumMedia' => '0',
    ]));

    expect($payload->contactSurface)->toBe(ConversationContactSurface::Phone)
        ->and($payload->contactKey)->toBe('7195551234')
        ->and($payload->providerMessageId)->toBe('SMparser001')
        ->and($payload->body)->toBe('Parser test')
        ->and($payload->metadata['provider'])->toBe('twilio_sms')
        ->and($payload->metadata['to_number'])->toBe('+17195559999');
});
