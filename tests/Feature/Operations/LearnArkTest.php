<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function () {
    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);
});

test('advisors can read remote sell and updated authorization guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'remote-sell']))
        ->assertOk()
        ->assertSee('Remote sell after check-in')
        ->assertSee('Copy portal link')
        ->assertSee('Awaiting approval');

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-authorization']))
        ->assertOk()
        ->assertSee('Draft')
        ->assertSee('Read-only')
        ->assertSee('ApprovalEvent');
});

test('advisors can read workspace tab signal guide', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'workspace-tabs']))
        ->assertOk()
        ->assertSee('Workspace tabs')
        ->assertSee('Yellow bar')
        ->assertSee('Awaiting Approval')
        ->assertSee('Unsaved changes')
        ->assertSee('kept visible');
});

test('advisors see advisor and technician learn guides but not admin or owner tracks', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']))
        ->assertOk()
        ->assertSee('Repair actions')
        ->assertSee('Three layers')
        ->assertSee('what is wrong')
        ->assertSee('Replace radiator')
        ->assertSee('Technician')
        ->assertSee('Reading approved work');

    $this->get(route('operations.learn.index'))
        ->assertRedirect(route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']));

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'getting-started']))
        ->assertOk()
        ->assertSee('Advisor basics')
        ->assertSee('Technician basics');

    $this->get(route('operations.learn.show', ['role' => 'technician', 'article' => 'reading-estimates']))
        ->assertOk()
        ->assertSee('How work is grouped');
});

test('technicians see technician guides and cannot open advisor articles', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value));

    $this->get(route('operations.learn.show', ['role' => 'technician', 'article' => 'getting-started']))
        ->assertOk()
        ->assertSee('Technician basics')
        ->assertSee('You do not build estimates or change pricing')
        ->assertDontSee('Repair action title');

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']))
        ->assertNotFound();
});

test('admins can open all staff learn sections', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'getting-started']))
        ->assertOk()
        ->assertSee('Admin overview');

    $this->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']))
        ->assertOk()
        ->assertSee('Desk phones and voice transport')
        ->assertSee('Stock Core does not ship')
        ->assertSee('Settings → Communications')
        ->assertDontSee('Elastic SIP Trunking');

    $this->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'shop-overhead-setup']))
        ->assertOk()
        ->assertSee('Shop overhead and loaded labor cost')
        ->assertSee('Office and advisor payroll')
        ->assertSee('Do not enter technician straight wages')
        ->assertSee('Loaded labor cost');

    $this->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'messenger-setup']))
        ->assertOk()
        ->assertSee('Facebook Messenger setup')
        ->assertSee('24-hour window')
        ->assertSee('Messenger outbound is not configured');

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']))
        ->assertOk()
        ->assertSee('Repair actions');

    $this->get(route('operations.learn.show', ['role' => 'technician', 'article' => 'reading-estimates']))
        ->assertOk()
        ->assertSee('How work is grouped');
});

test('admins can read owner excellence guides and default learn entry opens owner track', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin);

    $this->get(route('operations.learn.index'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'admin',
            'article' => 'getting-started',
        ]));

    foreach (\App\Ark\Operations\Learn\LearnArkCurriculum::requiredArticlesFor($admin) as $article) {
        \App\Ark\Operations\Learn\LearnCompletion::query()->create([
            'user_id' => $admin->id,
            'article_key' => $article['article_key'],
            'catalog_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::VERSION,
            'article_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::articleContentVersion($article['article_key']),
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now(),
        ]);
    }

    $this->get(route('operations.learn.index'))
        ->assertRedirect(route('operations.learn.show', [
            'role' => 'owner',
            'article' => 'shop-margins-five-steps',
        ]));

    $this->get(route('operations.learn.show', ['role' => 'owner', 'article' => 'shop-margins-five-steps']))
        ->assertOk()
        ->assertSee('Five steps to healthier margins')
        ->assertSee('Raise posted labor rate')
        ->assertSee('Effective Labor Rate');

    $this->get(route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-rhythm']))
        ->assertOk()
        ->assertSee('Day Review before you leave');

    $this->get(route('operations.learn.show', ['role' => 'owner', 'article' => 'weekly-owner-review']))
        ->assertOk()
        ->assertSee('Weekly owner review')
        ->assertSee('Margin Health');

    $this->get(route('operations.learn.show', ['role' => 'owner', 'article' => 'quarterly-target-review']))
        ->assertOk()
        ->assertSee('Quarterly target review')
        ->assertSee('last_target_review');

    $this->get(route('operations.learn.show', ['role' => 'owner', 'article' => 'communications-setup']))
        ->assertOk()
        ->assertSee('Communications setup')
        ->assertSee('ARK Email')
        ->assertSee('Settings → Communications')
        ->assertSee('Ring-group');

});

test('advisors cannot open owner excellence guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.learn.show', ['role' => 'owner', 'article' => 'daily-kpis']))
        ->assertNotFound();

    $this->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'telephony-sip-setup']))
        ->assertNotFound();

    $this->get(route('operations.learn.show', ['role' => 'admin', 'article' => 'shop-overhead-setup']))
        ->assertNotFound();
});

test('learn ark appears in operations navigation for staff', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    foreach (\App\Ark\Operations\Learn\LearnArkCurriculum::requiredArticlesFor($technician) as $article) {
        \App\Ark\Operations\Learn\LearnCompletion::query()->create([
            'user_id' => $technician->id,
            'article_key' => $article['article_key'],
            'catalog_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::VERSION,
            'article_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::articleContentVersion($article['article_key']),
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now(),
        ]);
    }

    $this->actingAs($technician)
        ->followingRedirects()
        ->get(route('operations.learn.index'))
        ->assertOk()
        ->assertSee(\App\Support\Branding\Branding::learnName());
});

test('day review appears in operations navigation for admins only', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    foreach (\App\Ark\Operations\Learn\LearnArkCurriculum::requiredArticlesFor($admin) as $article) {
        \App\Ark\Operations\Learn\LearnCompletion::query()->create([
            'user_id' => $admin->id,
            'article_key' => $article['article_key'],
            'catalog_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::VERSION,
            'article_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::articleContentVersion($article['article_key']),
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now(),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('operations.business'))
        ->assertOk()
        ->assertSee('Day Review');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    foreach (\App\Ark\Operations\Learn\LearnArkCurriculum::requiredArticlesFor($advisor) as $article) {
        \App\Ark\Operations\Learn\LearnCompletion::query()->create([
            'user_id' => $advisor->id,
            'article_key' => $article['article_key'],
            'catalog_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::VERSION,
            'article_version' => \App\Ark\Operations\Learn\LearnArkCurriculum::articleContentVersion($article['article_key']),
            'active_seconds' => $article['min_active_seconds'],
            'completed_at' => now(),
        ]);
    }

    $this->actingAs($advisor)
        ->get(route('operations.index'))
        ->assertOk()
        ->assertDontSee('>Day Review<');
});
