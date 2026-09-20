<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\OwnerDailyDigestMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;


beforeEach(function () {
    ShopSettings::current()->persistTrusted([
        'learn_training_gate_enabled' => false,
    ]);
});

test('admins can open owner day review workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value));

    $this->get(route('operations.owner.day-review'))
        ->assertOk()
        ->assertSee('Day Review', false)
        ->assertSee('End of Day Report', false)
        ->assertSee('How effective is your shop at selling work?', false)
        ->assertSee('RO Summary', false)
        ->assertSee('Sales after Discounts', false)
        ->assertSee('Tomorrow\'s queue pressure', false)
        ->assertSee('Total ROs', false)
        ->assertSee('Effective Labor Rate', false)
        ->assertSee('Sales Posted', false)
        ->assertSee('Cash Collected', false);

    $this->get(route('operations.owner.bookend'))
        ->assertOk()
        ->assertSee('Day Review', false);
});

test('advisors cannot open owner day review workspace', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value));

    $this->get(route('operations.owner.day-review'))
        ->assertForbidden();
});

test('owner digest command emails active admins when enabled', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();

    User::factory()->create([
        'email' => 'owner@example.com',
        'is_active' => true,
    ])->assignRole(ArkRole::Admin->value);

    User::factory()->create([
        'email' => 'advisor@example.com',
        'is_active' => true,
    ])->assignRole(ArkRole::Advisor->value);

    $this->artisan('shop-excellence:owner-digest')
        ->assertSuccessful();

    Mail::assertSent(OwnerDailyDigestMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    Mail::assertNotSent(OwnerDailyDigestMail::class, fn ($mail) => $mail->hasTo('advisor@example.com'));
});

test('owner digest command fails when enabled but no admin recipients exist', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();

    User::factory()->create([
        'email' => 'advisor@example.com',
        'is_active' => true,
    ])->assignRole(ArkRole::Advisor->value);

    $this->artisan('shop-excellence:owner-digest')
        ->assertFailed();

    Mail::assertNothingSent();
});

test('owner digest command skips when disabled in shop excellence targets', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();

    ShopExcellenceTargets::persist([
        'aro_target_cents' => 75000,
        'parts_margin_target_percent' => 55,
        'labor_sales_target_percent' => 55,
        'parts_sales_target_percent' => 45,
        'owner_digest_enabled' => false,
        'owner_digest_time' => '18:00',
    ]);

    User::factory()->create([
        'email' => 'owner@example.com',
        'is_active' => true,
    ])->assignRole(ArkRole::Admin->value);

    $this->artisan('shop-excellence:owner-digest')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

test('owner digest includes sales posted cash and reconciliation summary', function () {
    $this->seed(ArkAuthorizationSeeder::class);

    $digest = app(\App\Ark\Operations\ShopExcellence\OwnerOperationalPulse::class)->dailyDigest();

    expect($digest)->toHaveKeys(['headlines', 'priorities', 'reconciliation', 'financial_url'])
        ->and(collect($digest['headlines'])->pluck('label')->all())->toContain('Sales Posted', 'Cash Collected')
        ->and($digest['reconciliation'])->toHaveKeys(['reconciles', 'sales_posted', 'cash_collected', 'reconciled']);
});

test('advisors can read client retention and financial literacy guides', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    config(['bookstack.cutover' => false]);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    completeRequiredLearnFor($advisor);
    $this->actingAs($advisor);

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'client-retention-growth']))
        ->assertOk()
        ->assertSee('Client attrition and growth')
        ->assertSee('always needs new clients');

    $this->get(route('operations.learn.show', ['role' => 'advisor', 'article' => 'financial-literacy-basics']))
        ->assertOk()
        ->assertSee('Effective labor rate')
        ->assertSee('parts matrix');
});
