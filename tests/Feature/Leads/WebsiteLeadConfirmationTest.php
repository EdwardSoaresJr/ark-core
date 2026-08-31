<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\WebsiteLeadConfirmationMail;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);

    config()->set('public_lead.send_confirmation', true);
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7194136227',
    ]);
});

test('website lead sends sms confirmation after submit', function (): void {
    Http::fake([
        'lookups.twilio.com/*' => Http::response([
            'valid' => true,
            'line_type_intelligence' => [
                'type' => 'mobile',
                'carrier_name' => 'Test Carrier',
            ],
        ], 200),
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMleadconfirm01',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    $messages = ConversationMessage::query()->orderBy('id')->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[1]->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($messages[1]->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($messages[1]->body)->toContain('We received your request')
        ->and($messages[1]->body)->not->toContain('Ref LEAD-')
        ->and($messages[1]->body)->not->toContain('LEAD-')
        ->and($messages[1]->body)->toContain('Reply STOP to opt out')
        ->and($messages[1]->participant->participant_type)->toBe(ConversationParticipantType::System)
        ->and($messages[1]->metadata['website_lead_confirmation'] ?? null)->toBeTrue();
});

test('website lead sends email confirmation when address provided', function (): void {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMleadconfirm02',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

    Mail::fake();

    $this->post(route('public.leads.store'), [
        'concern' => 'AC is not cold.',
        'phone' => '719-555-0143',
        'first_name' => 'Jordan',
        'last_name' => 'Lee',
        'email' => 'jordan@example.test',
        'contact_preference' => LeadContactPreference::Email->value,
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    Mail::assertSent(WebsiteLeadConfirmationMail::class, function (WebsiteLeadConfirmationMail $mail): bool {
        $from = $mail->envelope()->from;

        return $mail->hasTo('jordan@example.test')
            && str_contains($mail->intro, 'received your vehicle concern')
            && $from instanceof \Illuminate\Mail\Mailables\Address
            && $from->name !== 'Laravel'
            && $mail->shopName !== 'Laravel';
    });

    expect(
        ConversationMessage::query()
            ->where('channel', OperationalCommunicationChannel::Email)
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->exists()
    )->toBeTrue();
});

test('spam website lead does not send confirmation', function (): void {
    Http::fake();
    Mail::fake();

    $this->post(route('public.leads.store'), [
        'concern' => 'Spam',
        'phone' => '719-555-0000',
        'first_name' => 'Bot',
        'last_name' => 'Spam',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('website lead confirmation can be disabled', function (): void {
    config()->set('public_lead.send_confirmation', false);

    Http::fake();
    Mail::fake();

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    Http::assertNothingSent();
    Mail::assertNothingSent();
});
