<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadDuplicateConsolidator;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;

test('consolidator closes duplicate open leads on same phone and keeps richest lead', function (): void {
    $recorder = app(LeadRecorder::class);

    $canonical = $recorder->recordWebsiteSubmission([
        'concern' => 'Looking to buy a 2010 Honda Element from a private owner and need a mechanic inspection.',
        'contact_phone' => '8086660908',
        'contact_name' => 'Art',
        'contact_email' => 'candelasart@gmail.com',
        'vehicle_year' => 2010,
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Element',
    ]);

    $duplicateSms = Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Hello - My name is Art Candelas and I am purchasing a used Honda Element from a private owner.',
        'contact_phone' => '8086660908',
        'conversation_id' => $canonical->conversation_id,
    ]);

    $duplicateAck = Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'Ok thank you',
        'contact_phone' => '8086660908',
        'conversation_id' => $canonical->conversation_id,
    ]);

    $closed = app(LeadDuplicateConsolidator::class)->consolidateOpenDuplicates('8086660908');

    expect($closed)->toHaveCount(2)
        ->and(Lead::query()->open()->where('contact_phone', '8086660908')->count())->toBe(1)
        ->and(Lead::query()->open()->where('contact_phone', '8086660908')->sole()->id)->toBe($canonical->id)
        ->and($duplicateSms->fresh()->state)->toBe(LeadState::Lost)
        ->and($duplicateAck->fresh()->state)->toBe(LeadState::Lost);
});

test('consolidator keeps distinct open concerns on same phone separate', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'My AC is not cold.',
        'contact_phone' => '5550100999',
    ]);

    Lead::query()->create([
        'source' => LeadSource::Sms,
        'state' => LeadState::Received,
        'concern' => 'My brakes are grinding.',
        'contact_phone' => '5550100999',
    ]);

    $closed = app(LeadDuplicateConsolidator::class)->consolidateOpenDuplicates('5550100999');

    expect($closed)->toBe([])
        ->and(Lead::query()->open()->where('contact_phone', '5550100999')->count())->toBe(2);
});
