<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadRecorder;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('the same website message is filtered after it is marked spam', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $concern = 'Xin chào, tôi muốn biết giá của bạn.';
    $first = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => $concern,
        'contact_phone' => '7195550101',
        'contact_name' => 'Robertber',
        'contact_email' => 'joshuaguerrero2v744d@gmail.com',
        'source' => LeadSource::Website,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.leads.state', $first), ['state' => LeadState::Spam->value])
        ->assertRedirect();

    $repeat = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => $concern,
        'contact_phone' => '7195550102',
        'contact_name' => 'Robertber',
        'contact_email' => 'potuaaguerrerc2v744d@gmail.com',
        'source' => LeadSource::Website,
    ]);

    expect($first->fresh()->state)->toBe(LeadState::Spam)
        ->and($repeat->state)->toBe(LeadState::Spam)
        ->and($repeat->conversation_id)->toBeNull()
        ->and($repeat->spam_signals)->toContain('learned_signature');
});

test('a rewritten website message is filtered when it repeats a spam link', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $first = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Стратегии получения параллельного дохода. https://best-marafon.ru/2778176/',
        'contact_phone' => '7195550201',
        'contact_name' => 'Sydneycof',
        'source' => LeadSource::Website,
    ]);

    $this->actingAs($advisor)
        ->patch(route('operations.leads.state', $first), ['state' => LeadState::Spam->value])
        ->assertRedirect();

    $repeat = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Как совмещать несколько трудовых доходов. https://best-marafon.ru/2778176',
        'contact_phone' => '7195550202',
        'contact_name' => 'Someone Else',
        'source' => LeadSource::Website,
    ]);

    expect($repeat->state)->toBe(LeadState::Spam)
        ->and($repeat->conversation_id)->toBeNull()
        ->and($repeat->spam_signals)->toContain('learned_signature');
});

test('a different website concern still arrives after another message was marked spam', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Xin chào, tôi muốn biết giá của bạn.',
        'contact_phone' => '7195550301',
    ]);

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Brakes squeal when stopping on the highway.',
        'contact_phone' => '7195550302',
        'contact_name' => 'Alex Morgan',
        'source' => LeadSource::Website,
    ]);

    expect($lead->state)->toBe(LeadState::Received)
        ->and($lead->conversation_id)->not->toBeNull();
});

test('a short marked message does not filter later short messages', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Spam,
        'concern' => 'Hello dear',
        'contact_phone' => '7195550401',
    ]);

    $lead = app(LeadRecorder::class)->recordWebsiteSubmission([
        'concern' => 'Hello dear',
        'contact_phone' => '7195550402',
        'contact_name' => 'Alex Morgan',
        'source' => LeadSource::Website,
    ]);

    expect($lead->state)->toBe(LeadState::Received)
        ->and($lead->conversation_id)->not->toBeNull();
});
