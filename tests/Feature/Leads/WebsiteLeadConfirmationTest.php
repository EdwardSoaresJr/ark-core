<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\SendWebsiteLeadConfirmationAction;
use App\Ark\Operations\Settings\ShopSettings;
use App\Mail\WebsiteLeadConfirmationMail;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'learn_training_gate_enabled' => false,
        'telephony_inbound_number' => '7194136227',
    ]);

    config()->set('public_lead.send_confirmation', true);
});

test('website lead sends sms confirmation', function (): void {
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

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Brakes squeal when stopping.',
        'contact_name' => 'Alex Morgan',
        'contact_phone' => '7195550142',
        'source' => LeadSource::Website,
    ]);

    // Confirmation is also queued after response; run action explicitly for the assertion.
    app(SendWebsiteLeadConfirmationAction::class)->execute($lead->fresh());

    $confirmation = ConversationMessage::query()
        ->orderBy('id')
        ->get()
        ->first(fn (ConversationMessage $message): bool => ($message->metadata['website_lead_confirmation'] ?? false) === true);

    expect($confirmation)->not->toBeNull()
        ->and($confirmation->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($confirmation->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($confirmation->body)->toContain('We received your request')
        ->and($confirmation->body)->toContain('Reply STOP to opt out')
        ->and($confirmation->participant->participant_type)->toBe(ConversationParticipantType::System);
});

test('website lead sends email confirmation when address provided', function (): void {
    bindFakeOutboundSms();
    Mail::fake();

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'AC is not cold.',
        'contact_name' => 'Jordan Lee',
        'contact_phone' => '7195550143',
        'contact_email' => 'jordan@example.test',
        'contact_preference' => LeadContactPreference::Email,
        'source' => LeadSource::Website,
    ]);

    app(SendWebsiteLeadConfirmationAction::class)->execute($lead->fresh());

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

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Spam',
        'contact_name' => 'Bot Spam',
        'contact_phone' => '7195550000',
    ]);

    app(SendWebsiteLeadConfirmationAction::class)->execute($lead);

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('website lead confirmation can be disabled', function (): void {
    config()->set('public_lead.send_confirmation', false);

    Http::fake();
    Mail::fake();

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Brakes squeal when stopping.',
        'contact_name' => 'Alex Morgan',
        'contact_phone' => '7195550142',
        'source' => LeadSource::Website,
    ]);

    app(SendWebsiteLeadConfirmationAction::class)->execute($lead->fresh());

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('connected shop sends website confirmation through platform mail only', function (): void {
    enableHostedPlatformMail();
    Mail::fake();
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => true,
                'status' => 'provider_sent',
                'message_id' => 'mail-lead-1',
            ], 200);
        }

        return Http::response(['unexpected' => $request->url()], 599);
    });
    bindFakeOutboundSms();

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'AC is not cold.',
        'contact_name' => 'Riley Chen',
        'contact_phone' => '7195550148',
        'contact_email' => 'riley@example.test',
        'contact_preference' => LeadContactPreference::Email,
        'source' => LeadSource::Website,
    ]);

    app(SendWebsiteLeadConfirmationAction::class)->execute($lead->fresh());

    Mail::assertNothingSent();

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        if (! str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return false;
        }

        $body = $request->data();
        if ($body === []) {
            $decoded = json_decode($request->body(), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return ($body['operation'] ?? null) === 'customer.transactional_message'
            && ($body['to'] ?? null) === 'riley@example.test'
            && str_starts_with((string) ($body['idempotency_key'] ?? ''), 'website-lead-confirmation-')
            && ($body['domain_object_type'] ?? null) === 'lead';
    });

    expect(
        ConversationMessage::query()
            ->where('channel', OperationalCommunicationChannel::Email)
            ->where('metadata->website_lead_confirmation', true)
            ->exists()
    )->toBeTrue();
});

test('platform mail rejection does not fall back to local mail', function (): void {
    enableHostedPlatformMail();
    Mail::fake();
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => false,
                'reason_code' => 'quota_exceeded',
                'message' => 'Monthly included send allowance exceeded.',
            ], 422);
        }

        return Http::response(['unexpected' => $request->url()], 599);
    });
    bindFakeOutboundSms();

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Needs brakes.',
        'contact_name' => 'Sam Patel',
        'contact_phone' => '7195550149',
        'contact_email' => 'sam@example.test',
        'contact_preference' => LeadContactPreference::Email,
        'source' => LeadSource::Website,
    ]);

    app(SendWebsiteLeadConfirmationAction::class)->execute($lead->fresh());

    Mail::assertNothingSent();

    expect(
        ConversationMessage::query()
            ->where('channel', OperationalCommunicationChannel::Email)
            ->exists()
    )->toBeFalse();
});
