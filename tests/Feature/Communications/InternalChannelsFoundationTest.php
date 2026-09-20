<?php

use App\Ark\Operations\Communications\InternalChannel;
use App\Ark\Operations\Communications\InternalChannelMember;
use App\Ark\Operations\Communications\InternalMessage;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\InternalChannelSeeder;


beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('default internal channels seed correctly', function (): void {
    $this->seed(InternalChannelSeeder::class);

    $slugs = InternalChannel::query()->orderBy('slug')->pluck('slug')->all();

    expect($slugs)->toBe([
        'general',
        'management',
        'parts',
        'service-advisors',
        'technicians',
    ]);

    expect(InternalChannel::query()->where('slug', 'general')->value('name'))->toBe('General');
});

test('internal channel can have members', function (): void {
    $channel = InternalChannel::query()->create([
        'name' => 'Test Channel',
        'slug' => 'test-channel',
    ]);

    $user = User::factory()->create();

    InternalChannelMember::query()->create([
        'internal_channel_id' => $channel->id,
        'user_id' => $user->id,
        'role' => 'member',
    ]);

    $channel->refresh();

    expect($channel->members)->toHaveCount(1)
        ->and($channel->members->first()->user_id)->toBe($user->id)
        ->and($channel->users->pluck('id'))->toContain($user->id);
});

test('internal message belongs to user and channel', function (): void {
    $channel = InternalChannel::query()->create([
        'name' => 'Parts',
        'slug' => 'parts-test',
    ]);

    $user = User::factory()->create();

    $message = InternalMessage::query()->create([
        'internal_channel_id' => $channel->id,
        'user_id' => $user->id,
        'body' => 'Heads up — condenser order is delayed.',
    ]);

    expect($message->channel->is($channel))->toBeTrue()
        ->and($message->user->is($user))->toBeTrue()
        ->and($channel->messages->first()->is($message))->toBeTrue();
});

test('attention config exists and exposes expected keys', function (): void {
    $config = config('ark_attention');

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys([
            'unassigned_conversation_minutes',
            'inbound_response_minutes',
            'estimate_followup_hours',
            'estimate_cold_hours',
            'ready_pickup_followup_hours',
            'lead_stale_hours',
            'parts_stale_days',
        ])
        ->and($config['unassigned_conversation_minutes'])->toBe(15)
        ->and($config['lead_stale_hours'])->toBe(4);
});

test('technician permissions allow internal view only not external communications or attention manage', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($technician->can(ArkCapability::CommunicationsInternalView->value))->toBeTrue()
        ->and($technician->can(ArkCapability::CommunicationsInternalManage->value))->toBeFalse()
        ->and($technician->can(ArkCapability::AttentionView->value))->toBeFalse()
        ->and($technician->can(ArkCapability::AttentionManage->value))->toBeFalse()
        ->and($technician->can(ArkCapability::OperationsAccess->value))->toBeFalse();

    expect($advisor->can(ArkCapability::CommunicationsInternalView->value))->toBeTrue()
        ->and($advisor->can(ArkCapability::CommunicationsInternalManage->value))->toBeTrue()
        ->and($advisor->can(ArkCapability::AttentionView->value))->toBeTrue()
        ->and($advisor->can(ArkCapability::AttentionManage->value))->toBeTrue()
        ->and($advisor->can(ArkCapability::OperationsAccess->value))->toBeTrue();
});
