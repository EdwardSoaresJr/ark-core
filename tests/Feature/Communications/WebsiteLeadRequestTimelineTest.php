<?php

use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\Leads\WebsiteLeadRequestPresentation;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    config()->set('services.ark_platform.communications_authority', true);
    config()->set('services.ark_platform.communications_inbox', true);
    config()->set('services.ark_platform.communications_core_mirror', false);

    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'test-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);
});

test('appointment request card is built from the lead record', function (): void {
    $lead = websiteRequestLead(
        phone: '7195550111',
        name: 'Riley Chen',
        email: 'riley.chen@example.com',
        concern: PublicAppointmentRequest::composeConcern(
            'There is a ticking noise from the engine when it is cold.',
            'Monday, October 5 · Morning',
        ),
        vehicle: [2004, 'Toyota', 'Corolla'],
        metadata: PublicAppointmentRequest::metadataWithAvailability([
            'public_page' => 'book',
            'canonical_host' => 'www.lugsnplugs.com',
        ], '2026-10-05', 'morning', 'Monday, October 5 · Morning'),
        preference: LeadContactPreference::Email,
        at: '2026-09-27T08:08:00Z',
    );

    $card = app(WebsiteLeadRequestPresentation::class)->card($lead);
    $when = Carbon::parse('2026-09-27T08:08:00Z')
        ->timezone(config('app.display_timezone') ?: config('app.timezone'))
        ->format('M j, g:i A');

    expect($card['title'])->toBe('Appointment request')
        ->and($card['body_label'])->toBe('Concern')
        ->and($card['body'])->toBe('There is a ticking noise from the engine when it is cold.')
        ->and($card['body'])->not->toContain('Preferred visit')
        ->and(collect($card['fields'])->firstWhere('label', 'Preferred visit')['value'])->toBe('Monday, October 5 · Morning')
        ->and(collect($card['fields'])->firstWhere('label', 'Vehicle')['value'])->toBe('2004 Toyota Corolla')
        ->and(collect($card['fields'])->firstWhere('label', 'Email')['value'])->toBe('riley.chen@example.com')
        ->and(collect($card['fields'])->firstWhere('label', 'Phone')['value'])->toBe('(719) 555-0111')
        ->and(collect($card['fields'])->firstWhere('label', 'Contact')['value'])->toBe('Email me')
        ->and($card['attribution'])->toBe('Submitted from lugsnplugs.com · '.$when);
});

test('website message card keeps the submitted message when there is no visit window', function (): void {
    $lead = websiteRequestLead(
        phone: '2025550111',
        name: 'Thomas Davis',
        email: null,
        concern: 'I would like more information. Please contact me by email.',
        vehicle: [null, null, null],
        metadata: [
            'public_page' => 'contact',
            'canonical_host' => 'lugsnplugs.com',
        ],
        at: '2026-09-26T07:02:00Z',
    );

    $card = app(WebsiteLeadRequestPresentation::class)->card($lead);

    expect($card['title'])->toBe('Website request')
        ->and($card['body_label'])->toBe('Message')
        ->and($card['body'])->toBe('I would like more information. Please contact me by email.')
        ->and(collect($card['fields'])->pluck('label')->all())->not->toContain('Preferred visit')
        ->and(collect($card['fields'])->pluck('label')->all())->not->toContain('Vehicle')
        ->and($card['attribution'])->toStartWith('Submitted from lugsnplugs.com ·');
});

test('inbox timeline shows the appointment request before the acknowledgement text', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $concern = 'There is a ticking noise from the engine when it is cold.';
    $lead = websiteRequestLead(
        phone: '7195550164',
        name: 'Riley Chen',
        email: 'riley.chen@example.com',
        concern: PublicAppointmentRequest::composeConcern($concern, 'Monday, October 5 · Morning'),
        vehicle: [2004, 'Toyota', 'Corolla'],
        metadata: PublicAppointmentRequest::metadataWithAvailability([
            'public_page' => 'book',
            'canonical_host' => 'lugsnplugs.com',
        ], '2026-10-05', 'morning', 'Monday, October 5 · Morning'),
        at: '2026-09-27T08:08:00Z',
    );

    fakePlatformThread('pc_aja', '+17195550164', [
        [
            'public_id' => 'pm_code',
            'direction' => 'outbound',
            'body' => 'Your verification code is 100200',
            'occurred_at' => '2026-09-27T08:04:00Z',
            'delivery_status' => 'delivered',
        ],
        [
            'public_id' => 'pm_ack',
            'direction' => 'outbound',
            'body' => 'We received your appointment request. We will confirm the appointment time with you soon.',
            'occurred_at' => '2026-09-27T08:09:00Z',
            'delivery_status' => 'delivered',
        ],
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_aja',
        ]))
        ->assertOk()
        ->assertSee('data-website-request="'.$lead->id.'"', false)
        ->assertSeeInOrder([
            'Your verification code is 100200',
            'Appointment request',
            'Preferred visit:',
            'Monday, October 5 · Morning',
            'Vehicle:',
            '2004 Toyota Corolla',
            'Email:',
            'riley.chen@example.com',
            'Concern:',
            'when it is cold.',
            'Submitted from lugsnplugs.com',
            'We received your appointment request. We will confirm the appointment time with you soon.',
        ]);
});

test('inbox timeline shows a website message that is not an appointment', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $lead = websiteRequestLead(
        phone: '2025550111',
        name: 'Thomas Davis',
        email: 'thomas@example.com',
        concern: 'I would like more information. Please contact me by email so I can send the photos.',
        vehicle: [null, null, null],
        metadata: [
            'public_page' => 'contact',
            'canonical_host' => 'lugsnplugs.com',
        ],
        at: '2026-09-26T07:02:00Z',
    );

    fakePlatformThread('pc_thomas', '+12025550111', [
        [
            'public_id' => 'pm_thomas_ack',
            'direction' => 'outbound',
            'body' => 'We received your request. A service advisor will review it and follow up soon.',
            'occurred_at' => '2026-09-26T07:03:00Z',
            'delivery_status' => 'undelivered',
        ],
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_thomas',
        ]))
        ->assertOk()
        ->assertSee('data-website-request="'.$lead->id.'"', false)
        ->assertSeeInOrder([
            'Website request',
            'Message:',
            'Please contact me by email so I can send the photos.',
            'Submitted from lugsnplugs.com',
            'We received your request. A service advisor will review it and follow up soon.',
        ])
        ->assertDontSee('Appointment request');
});

test('a text thread with no website lead does not invent a request card', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    fakePlatformThread('pc_plain', '+17195557700', [
        [
            'public_id' => 'pm_plain',
            'direction' => 'inbound',
            'body' => 'Can I drop the truck off tomorrow?',
            'occurred_at' => '2026-09-27T15:00:00Z',
        ],
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.communications.inbox', [
            'filter' => 'needs',
            'platform_conversation' => 'pc_plain',
        ]))
        ->assertOk()
        ->assertSee('Can I drop the truck off tomorrow?')
        ->assertDontSee('data-website-request', false)
        ->assertDontSee('Appointment request')
        ->assertDontSee('Website request');
});

/**
 * @param  array{0: int|null, 1: string|null, 2: string|null}  $vehicle
 * @param  array<string, mixed>  $metadata
 */
function websiteRequestLead(
    string $phone,
    string $name,
    ?string $email,
    string $concern,
    array $vehicle,
    array $metadata,
    string $at,
    ?LeadContactPreference $preference = null,
): Lead {
    $message = app(ConversationRecorder::class)->recordWebsiteLead(null, $concern, $phone, $name);

    $lead = Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => $concern,
        'contact_name' => $name,
        'contact_phone' => $phone,
        'contact_email' => $email,
        'contact_preference' => $preference ?? LeadContactPreference::Text,
        'vehicle_year' => $vehicle[0],
        'vehicle_make' => $vehicle[1],
        'vehicle_model' => $vehicle[2],
        'conversation_id' => $message->conversation_id,
        'metadata' => $metadata,
    ]);

    $lead->forceFill([
        'created_at' => Carbon::parse($at),
        'updated_at' => Carbon::parse($at),
    ])->save();

    return $lead->fresh();
}

/**
 * @param  list<array<string, mixed>>  $messages
 */
function fakePlatformThread(string $publicId, string $contact, array $messages): void
{
    $last = $messages[array_key_last($messages)] ?? [];

    Http::fake([
        'https://cloud.test/api/v1/services/communications/conversations*' => function ($request) use ($publicId, $contact, $messages, $last) {
            if (str_contains($request->url(), '/read')) {
                return Http::response(['ok' => true], 200);
            }

            if (str_contains($request->url(), '/conversations/'.$publicId)) {
                return Http::response([
                    'ok' => true,
                    'conversation' => [
                        'public_id' => $publicId,
                        'contact_address' => $contact,
                        'core_customer_id' => null,
                    ],
                    'messages' => $messages,
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'conversations' => [[
                    'public_id' => $publicId,
                    'contact_address' => $contact,
                    'core_customer_id' => null,
                    'preview' => (string) ($last['body'] ?? ''),
                    'last_message_at' => $last['occurred_at'] ?? now()->toIso8601String(),
                    'unread' => true,
                ]],
            ], 200);
        },
    ]);
}
