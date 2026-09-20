<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationTimeline;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Encounters\Encounter;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('estimate email records a conversation message linked to the repair order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
    Mail::fake();

    $this->app->bind(PdfRenderer::class, function (): PdfRenderer {
        return new class implements PdfRenderer
        {
            public function renderEstimate(EstimateDocument $document): string
            {
                $path = 'estimate-documents/ro-'.$document->repair_order_id.'/current-estimate.pdf';
                Storage::disk('local')->put($path, 'PDF');
                $document->forceFill([
                    'status' => 'generated',
                    'pdf_path' => $path,
                    'generated_at' => now(),
                    'needs_pdf_refresh' => false,
                    'pdf_refreshed_at' => now(),
                ])->save();

                return $path;
            }
        };
    });

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review the attached estimate today.',
        ])
        ->assertRedirect();

    $conversation = Conversation::query()->sole();

    expect($conversation->contact_surface)->toBe(ConversationContactSurface::Email)
        ->and($conversation->contact_address)->toBe('customer@example.test');

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Outbound)
        ->and($message->body)->toContain('customer@example.test')
        ->and($message->participant->participant_type)->toBe(ConversationParticipantType::System);

    expect(ConversationLink::query()
        ->where('linkable_type', RepairOrder::class)
        ->where('linkable_id', $repairOrder->id)
        ->exists())->toBeTrue();

    expect(CommunicationEvent::query()->count())->toBe(1);
});

test('manual advisor log and estimate viewed both record conversation messages', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.communications.store', $repairOrder), [
            'communication_type' => OperationalCommunicationType::AdvisorNote->value,
            'channel' => OperationalCommunicationChannel::Phone->value,
            'direction' => OperationalCommunicationDirection::Outbound->value,
            'summary' => 'Called customer and left voicemail about brakes.',
        ])
        ->assertRedirect();

    expect(ConversationMessage::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->first()->body)->toBe('Called customer and left voicemail about brakes.')
        ->and(ConversationMessage::query()->first()->participant->participant_type)->toBe(ConversationParticipantType::Advisor);

    $this->post(route('operations.repair-orders.communications.store', $repairOrder), [
        'communication_type' => OperationalCommunicationType::EstimateViewed->value,
        'channel' => OperationalCommunicationChannel::Email->value,
        'direction' => OperationalCommunicationDirection::Inbound->value,
        'summary' => 'Customer viewed estimate but has not approved.',
    ])->assertRedirect();

    expect(ConversationMessage::query()->count())->toBe(2)
        ->and(CommunicationEvent::query()->count())->toBe(2);
});

test('website lead intake records conversation without creating encounters', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->post(route('operations.intake.leads.store'), [
            'concern' => 'Need oil change and tire rotation this week.',
            'callback_name' => 'Jordan Lee',
            'callback_phone' => '555-0199',
            'rough_vehicle' => '2019 Subaru Outback',
            'source' => EncounterSource::Website->value,
        ])
        ->assertRedirect();

    $lead = \App\Ark\Operations\Leads\Lead::query()->sole();

    expect($lead->concern)->toBe('Need oil change and tire rotation this week.');

    $conversation = Conversation::query()->sole();

    expect($conversation->contact_surface)->toBe(ConversationContactSurface::Phone)
        ->and($conversation->contact_address)->toBe('5550199');

    $message = ConversationMessage::query()->sole();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Website)
        ->and($message->body)->toBe('Need oil change and tire rotation this week.')
        ->and($message->participant->display_name)->toBe('Jordan Lee');

    expect(Encounter::query()->count())->toBe(0);
});

test('repair order conversation timeline unifies email and advisor messages', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
    Mail::fake();

    $this->app->bind(PdfRenderer::class, function (): PdfRenderer {
        return new class implements PdfRenderer
        {
            public function renderEstimate(EstimateDocument $document): string
            {
                $path = 'estimate-documents/ro-'.$document->repair_order_id.'/current-estimate.pdf';
                Storage::disk('local')->put($path, 'PDF');
                $document->forceFill([
                    'status' => 'generated',
                    'pdf_path' => $path,
                    'generated_at' => now(),
                    'needs_pdf_refresh' => false,
                    'pdf_refreshed_at' => now(),
                ])->save();

                return $path;
            }
        };
    });

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [])
        ->assertRedirect();

    $this->post(route('operations.repair-orders.communications.store', $repairOrder), [
        'communication_type' => OperationalCommunicationType::CustomerReply->value,
        'channel' => OperationalCommunicationChannel::Sms->value,
        'direction' => OperationalCommunicationDirection::Inbound->value,
        'summary' => 'Can I pick up my truck tomorrow morning?',
    ])->assertRedirect();

    $timeline = app(ConversationTimeline::class)->forRepairOrder($repairOrder->fresh());

    expect($timeline)->toHaveCount(2)
        ->and($timeline->pluck('body')->join(' '))->toContain('customer@example.test')
        ->and($timeline->pluck('body')->join(' '))->toContain('Can I pick up my truck tomorrow morning?');

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Can I pick up my truck tomorrow morning?')
        ->assertSee('customer@example.test');
});

test('conversation messages are append only', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.communications.store', $repairOrder), [
            'communication_type' => OperationalCommunicationType::AdvisorNote->value,
            'channel' => OperationalCommunicationChannel::Internal->value,
            'direction' => OperationalCommunicationDirection::Internal->value,
            'summary' => 'Internal advisor note.',
        ]);

    $message = ConversationMessage::query()->sole();

    expect(fn () => $message->update(['body' => 'changed']))
        ->toThrow(LogicException::class);
});
