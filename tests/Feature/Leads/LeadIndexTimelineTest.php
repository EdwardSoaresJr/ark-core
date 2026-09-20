<?php

use App\Ark\Operations\Communications\CommunicationsNeedsYou;
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

test('lead-linked inbox context shows check in and disposal actions', function (): void {
    $recorder = app(LeadRecorder::class);

    $recorder->recordWebsiteSubmission([
        'concern' => 'Need a pre-purchase inspection on a Honda Element.',
        'contact_phone' => '8086660908',
        'contact_name' => 'Art',
    ]);

    $lead = Lead::query()->open()->where('contact_phone', '8086660908')->sole();
    expect($lead->conversation_id)->not->toBeNull();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(CommunicationsNeedsYou::url(['conversation' => $lead->conversation_id]))
        ->assertOk()
        ->assertSee('Check In', false)
        ->assertSee('Mark contacted', false)
        ->assertSee('Lost', false)
        ->assertSee('Spam', false)
        ->assertSee('Website Lead', false)
        ->assertSee(route('operations.leads.intake', $lead), false);
});

test('leads index redirects instead of rendering timeline drawer', function (): void {
    Lead::query()->create([
        'source' => LeadSource::Website,
        'state' => LeadState::Received,
        'concern' => 'AC not cold',
        'contact_phone' => '7195550101',
        'contact_name' => 'Sam',
    ]);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)
        ->get(route('operations.leads.index'))
        ->assertRedirect(CommunicationsNeedsYou::url())
        ->assertDontSee('ops-lead-message-drawer', false);
});
