<?php

use App\Ark\Operations\Learn\LearnArkCurriculum;
use App\Ark\Operations\Learn\LearnCheckpoint;
use App\Ark\Operations\Learn\LearnCompletion;
use App\Ark\Operations\Learn\LearnSession;
use App\Ark\Operations\Learn\LearnTrainingSnooze;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    ShopSettings::current()->update(['learn_training_gate_enabled' => true]);
});

test('workboard redirects to required learn guide when training is incomplete', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $this->get(route('operations.index'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'advisor',
            'article' => 'getting-started',
        ]));
});

test('staff can snooze required training to access the workboard temporarily', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $this->post(route('operations.learn.progress.snooze'))
        ->assertRedirect(route('operations.index'))
        ->assertSessionHas('learn_snoozed');

    $this->get(route('operations.index'))
        ->assertOk();

    expect(LearnTrainingSnooze::query()
        ->where('user_id', $advisor->id)
        ->where('snoozed_until', '>', now())
        ->exists())->toBeTrue();
});

test('expired snooze requires training progress before staff can snooze again', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    LearnTrainingSnooze::query()->create([
        'user_id' => $advisor->id,
        'snoozed_at' => now()->subHours(5),
        'snoozed_until' => now()->subHour(),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.learn.progress.snooze'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'advisor',
            'article' => 'getting-started',
        ]))
        ->assertSessionHas('learn_snooze_blocked');

    LearnCheckpoint::query()->create([
        'user_id' => $advisor->id,
        'article_key' => 'advisor:getting-started',
        'checkpoint_key' => 'h-0',
        'checkpoint_index' => 0,
        'active_seconds_at_reach' => 25,
        'reached_at' => now(),
    ]);

    $this->post(route('operations.learn.progress.snooze'))
        ->assertRedirect(route('operations.index'));
});

test('completing a required guide allows snoozing again when more guides remain', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    LearnTrainingSnooze::query()->create([
        'user_id' => $advisor->id,
        'snoozed_at' => now()->subHours(5),
        'snoozed_until' => now()->subHour(),
    ]);

    LearnCompletion::query()->create([
        'user_id' => $advisor->id,
        'article_key' => 'advisor:getting-started',
        'catalog_version' => LearnArkCurriculum::VERSION,
        'article_version' => LearnArkCurriculum::articleContentVersion('advisor:getting-started'),
        'active_seconds' => 90,
        'completed_at' => now(),
    ]);

    $this->actingAs($advisor)
        ->post(route('operations.learn.progress.snooze'))
        ->assertRedirect(route('operations.index'));
});

test('expired training snooze sends staff back to required guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    LearnTrainingSnooze::query()->create([
        'user_id' => $advisor->id,
        'snoozed_at' => now()->subHours(5),
        'snoozed_until' => now()->subHour(),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'advisor',
            'article' => 'getting-started',
        ]));
});

test('home opens when required learn guides are complete', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);
    $this->actingAs($advisor);

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Work')
        ->assertSee('Communications');
});

test('learn heartbeat accrues active seconds only while interacting', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    Carbon::setTestNow('2026-06-03 10:00:00');

    $this->postJson(route('operations.learn.progress.heartbeat'), [
        'article_key' => 'advisor:getting-started',
        'visible' => true,
        'interacting' => true,
    ])->assertOk()
        ->assertJsonPath('active_seconds', 0);

    Carbon::setTestNow('2026-06-03 10:00:16');

    $this->postJson(route('operations.learn.progress.heartbeat'), [
        'article_key' => 'advisor:getting-started',
        'visible' => true,
        'interacting' => true,
    ])->assertOk()
        ->assertJsonPath('active_seconds', 15);

    Carbon::setTestNow('2026-06-03 10:00:32');

    $this->postJson(route('operations.learn.progress.heartbeat'), [
        'article_key' => 'advisor:getting-started',
        'visible' => true,
        'interacting' => false,
    ])->assertOk()
        ->assertJsonPath('active_seconds', 15);

    Carbon::setTestNow();
});

test('learn checkpoints must be reached in order', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $this->postJson(route('operations.learn.progress.checkpoint'), [
        'article_key' => 'advisor:getting-started',
        'checkpoint_key' => 'h-1',
        'checkpoint_index' => 1,
        'section_active_seconds' => 25,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'First checkpoint must be index zero.');

    $this->postJson(route('operations.learn.progress.checkpoint'), [
        'article_key' => 'advisor:getting-started',
        'checkpoint_key' => 'h-0',
        'checkpoint_index' => 0,
        'section_active_seconds' => 25,
    ])->assertOk();

    $this->postJson(route('operations.learn.progress.checkpoint'), [
        'article_key' => 'advisor:getting-started',
        'checkpoint_key' => 'h-2',
        'checkpoint_index' => 2,
        'section_active_seconds' => 25,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Checkpoints must be reached in order.');
});

test('learn complete requires active time and all checkpoints', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    LearnSession::query()->create([
        'user_id' => $advisor->id,
        'article_key' => 'advisor:getting-started',
        'active_seconds' => 40,
    ]);

    $this->postJson(route('operations.learn.progress.complete'), [
        'article_key' => 'advisor:getting-started',
        'checkpoint_keys' => ['h-0', 'h-1', 'h-2', 'h-3'],
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Not enough active reading time.');

    LearnSession::query()
        ->where('user_id', $advisor->id)
        ->where('article_key', 'advisor:getting-started')
        ->update(['active_seconds' => 120]);

    $this->postJson(route('operations.learn.progress.complete'), [
        'article_key' => 'advisor:getting-started',
        'checkpoint_keys' => ['h-0', 'h-1', 'h-2', 'h-3'],
    ])->assertStatus(422)
        ->assertJsonPath('message', 'All section checkpoints must be completed.');

    foreach (['h-0', 'h-1', 'h-2', 'h-3'] as $index => $key) {
        LearnCheckpoint::query()->create([
            'user_id' => $advisor->id,
            'article_key' => 'advisor:getting-started',
            'checkpoint_key' => $key,
            'checkpoint_index' => $index,
            'active_seconds_at_reach' => 90,
            'reached_at' => now()->subSeconds(60 - ($index * 20)),
        ]);
    }

    $this->postJson(route('operations.learn.progress.complete'), [
        'article_key' => 'advisor:getting-started',
        'checkpoint_keys' => ['h-0', 'h-1', 'h-2', 'h-3'],
    ])->assertOk()
        ->assertJsonPath('current', false);

    expect(LearnCompletion::query()
        ->where('user_id', $advisor->id)
        ->where('article_key', 'advisor:getting-started')
        ->exists())->toBeTrue();
});

test('managers can view team training roster', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create(['name' => 'Shop Admin'])->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create(['name' => 'Counter Advisor'])->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);

    $this->actingAs($admin)
        ->get(route('operations.learn.team-progress'))
        ->assertOk()
        ->assertSee('Team training progress')
        ->assertSee('Counter Advisor')
        ->assertSee('Current');

    $this->actingAs($advisor)
        ->get(route('operations.learn.team-progress'))
        ->assertForbidden();
});

test('stale article completions require staff to re-read updated guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    LearnCompletion::query()->create([
        'user_id' => $advisor->id,
        'article_key' => 'advisor:scopes-and-intent',
        'catalog_version' => 1,
        'article_version' => 3,
        'active_seconds' => 90,
        'completed_at' => now()->subDay(),
    ]);

    $this->actingAs($advisor);

    expect(app(\App\Ark\Operations\Learn\LearnArkProgressResolver::class)->isCurrent($advisor))->toBeFalse();

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'scopes-and-intent']))
        ->assertOk()
        ->assertSee('This guide was updated');
});

test('unchanged required guides stay current when another article version bumps', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    foreach (LearnArkCurriculum::requiredArticlesFor($advisor) as $article) {
        $articleKey = $article['article_key'];
        $requiredVersion = LearnArkCurriculum::articleContentVersion($articleKey);
        $completedVersion = $articleKey === 'advisor:scopes-and-intent' ? $requiredVersion - 1 : $requiredVersion;

        LearnCompletion::query()->create([
            'user_id' => $advisor->id,
            'article_key' => $articleKey,
            'catalog_version' => LearnArkCurriculum::VERSION,
            'article_version' => $completedVersion,
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now()->subDay(),
        ]);
    }

    $resolver = app(\App\Ark\Operations\Learn\LearnArkProgressResolver::class);

    expect($resolver->articleState($advisor, 'advisor:getting-started')['completed'])->toBeTrue()
        ->and($resolver->articleState($advisor, 'advisor:scopes-and-intent')['content_stale'])->toBeTrue()
        ->and($resolver->summaryFor($advisor)['stale'])->toBe(1)
        ->and($resolver->isCurrent($advisor))->toBeFalse();
});

test('new required guides only block staff who have not completed them', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $requiredCount = LearnArkCurriculum::requiredArticlesFor($advisor)->count();

    foreach (LearnArkCurriculum::requiredArticlesFor($advisor) as $article) {
        if ($article['article_key'] === 'advisor:comms-queue') {
            continue;
        }

        LearnCompletion::query()->create([
            'user_id' => $advisor->id,
            'article_key' => $article['article_key'],
            'catalog_version' => LearnArkCurriculum::VERSION,
            'article_version' => LearnArkCurriculum::articleContentVersion($article['article_key']),
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now()->subDay(),
        ]);
    }

    $resolver = app(\App\Ark\Operations\Learn\LearnArkProgressResolver::class);

    expect($resolver->isCurrent($advisor))->toBeFalse()
        ->and($resolver->nextRequiredArticle($advisor)['article_key'])->toBe('advisor:comms-queue')
        ->and($resolver->summaryFor($advisor)['completed'])->toBe($requiredCount - 1);
});

test('learn video progress records watch state', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor);

    $this->postJson(route('operations.learn.progress.video'), [
        'article_key' => 'advisor:advisor-intake',
        'video_key' => 'main',
        'percent_watched' => 42,
        'watched_seconds' => 120,
        'last_position_seconds' => 120,
        'completed' => false,
    ])->assertOk()
        ->assertJsonPath('percent_watched', 42)
        ->assertJsonPath('completed', false);

    $this->postJson(route('operations.learn.progress.video'), [
        'article_key' => 'advisor:advisor-intake',
        'video_key' => 'main',
        'percent_watched' => 96,
        'watched_seconds' => 280,
        'last_position_seconds' => 280,
        'completed' => true,
    ])->assertOk()
        ->assertJsonPath('percent_watched', 96)
        ->assertJsonPath('completed', true);
});

test('expanded learn catalog articles render with media components', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']))
        ->assertOk()
        ->assertSee('Service counter posture')
        ->assertSee('ops-learn-figure', false)
        ->assertSee('advisor/advisor-intake/intake-recognition-band.png', false);

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-hub']))
        ->assertOk()
        ->assertSee('Customer Service Hub');
});

test('required article page shows training progress footer', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']))
        ->assertOk()
        ->assertSee('Required guide — read each section')
        ->assertSee('Mark guide complete')
        ->assertSee('Snooze '.LearnArkCurriculum::SNOOZE_HOURS.'h — workboard');
});

test('shop owner bypasses required training gate without completing guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $owner = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $this->actingAs($owner);

    $this->get(route('operations.index'))
        ->assertOk();
});

test('owner can pause required training gate for all staff', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $owner = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($owner)
        ->post(route('operations.learn.training-gate'), ['enabled' => false])
        ->assertRedirect()
        ->assertSessionHas('learn_gate_control');

    expect(ShopSettings::current()->fresh()->learn_training_gate_enabled)->toBeFalse();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk();
});

test('owner can turn required training gate back on', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $owner = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    ShopSettings::current()->update(['learn_training_gate_enabled' => false]);

    $this->actingAs($owner)
        ->post(route('operations.learn.training-gate'), ['enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('learn_gate_control');

    expect(ShopSettings::current()->fresh()->learn_training_gate_enabled)->toBeTrue();

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'advisor',
            'article' => 'getting-started',
        ]));
});

test('non-owner admin cannot change training gate setting', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    $this->post(route('operations.learn.training-gate'), ['enabled' => false])
        ->assertForbidden();
});
