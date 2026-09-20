<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Attention\AdvisorNudgeResponse;
use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\AdvisorNudgeWeeklyInsightProjection;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('weekly nudge insight calculates per nudge and overall action rates', function (): void {
    $advisor = User::factory()->create();

    AdvisorNudgeResponse::query()->create([
        'user_id' => $advisor->id,
        'entity_key' => 'conversation:1',
        'nudge_key' => 'conversation.waiting_response',
        'response' => AdvisorNudgeResponseKind::Acted,
    ]);

    AdvisorNudgeResponse::query()->create([
        'user_id' => $advisor->id,
        'entity_key' => 'conversation:2',
        'nudge_key' => 'conversation.waiting_response',
        'response' => AdvisorNudgeResponseKind::Acted,
    ]);

    AdvisorNudgeResponse::query()->create([
        'user_id' => $advisor->id,
        'entity_key' => 'call:1',
        'nudge_key' => 'call.mark_handled',
        'response' => AdvisorNudgeResponseKind::Dismissed,
    ]);

    $insight = app(AdvisorNudgeWeeklyInsightProjection::class)->lastSevenDays();

    expect($insight['acted'])->toBe(2)
        ->and($insight['dismissed'])->toBe(1)
        ->and($insight['action_rate'])->toBe(67)
        ->and($insight['rows'])->toHaveCount(2)
        ->and($insight['rows'][0]['key'])->toBe('conversation.waiting_response')
        ->and($insight['rows'][0]['label'])->toBe('Customer waiting')
        ->and($insight['rows'][0]['action_rate'])->toBe(100)
        ->and($insight['rows'][1]['action_rate'])->toBe(0);
});

test('owner day review surfaces weekly nudge measurement when responses exist', function (): void {
    $admin = User::factory()->create()->assignRole(\App\Ark\Runtime\Authorization\ArkRole::Admin->value);

    AdvisorNudgeResponse::query()->create([
        'user_id' => $admin->id,
        'entity_key' => 'conversation:1',
        'nudge_key' => 'conversation.waiting_response',
        'response' => AdvisorNudgeResponseKind::Acted,
    ]);

    $this->actingAs($admin)
        ->get(route('operations.owner.day-review'))
        ->assertOk()
        ->assertSee('Comms nudge measurement', false)
        ->assertSee('Acted', false)
        ->assertSee('Dismissed', false);
});
