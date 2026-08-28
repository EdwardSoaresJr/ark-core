<?php

use App\Models\User;
use App\Ark\Platform\ProvisioningRequest;
use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('serves the ARK Cloud home funnel', function () {
    $this->get(route('cloud.home'))
        ->assertOk()
        ->assertSee('Run your shop with confidence', false)
        ->assertSee('Built for the floor', false)
        ->assertSee('Start Free Trial', false)
        ->assertSee('assets/cloud/product/repair-order-live.png', false)
        ->assertSee('Ready to get your shop online', false)
        ->assertSee('Help your first customer', false)
        ->assertDontSee('inside ARK', false);
});

it('creates a real User and owned Shop through the Cloud Funnel', function () {
    Notification::fake();

    $this->post(route('cloud.trial.shop.store'), [
        'shop_name' => 'Demo Auto Repair',
    ])->assertRedirect(route('cloud.trial.workspace'));

    expect(Shop::query()->count())->toBe(0);

    $this->post(route('cloud.trial.workspace.store'), [
        'slug' => 'lugsnplugs',
    ])->assertRedirect(route('cloud.trial.account'));

    $this->post(route('cloud.trial.account.store'), [
        'owner_name' => 'Edward',
        'email' => 'edward@demo-auto.test',
        'password' => 'password123',
    ])->assertRedirect(route('cloud.trial.provisioning'));

    $user = User::query()->where('email', 'edward@demo-auto.test')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('password123', $user->password))->toBeTrue()
        ->and($user->password_set_at)->not->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);

    $shop = $user->ownedShop;
    expect($shop)->not->toBeNull()
        ->and($shop->display_name)->toBe('Demo Auto Repair')
        ->and($shop->slug)->toBe('lugsnplugs')
        ->and($shop->owner_user_id)->toBe($user->id)
        ->and($shop->status)->toBe(ShopStatus::Prospect)
        ->and($shop->uuid)->not->toBeEmpty()
        ->and(Shop::query()->count())->toBe(1);

    expect(ProvisioningRequest::query()->count())->toBe(0);
    expect(Schema::hasTable('tenants') ? \Illuminate\Support\Facades\DB::table('tenants')->count() : 0)->toBe(0);

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get(route('cloud.trial.provisioning'))
        ->assertOk()
        ->assertSee('Let’s get your shop ready', false)
        ->assertSee('Free trial', false);

    $this->get(route('cloud.welcome'))
        ->assertOk()
        ->assertSee('Welcome to ARK, Edward', false)
        ->assertSee('Your workspace is waiting', false)
        ->assertSee('0 / 3 Complete', false);

    $this->get(route('cloud.dashboard'))
        ->assertOk()
        ->assertSee('Open Workspace', false)
        ->assertSee('lugsnplugs.arksms.com', false)
        ->assertSee('Demo Auto Repair', false);

    $this->assertAuthenticated();
});

it('logs in later and resumes from the real Shop', function () {
    Notification::fake();

    $this->post(route('cloud.trial.shop.store'), ['shop_name' => 'Mile High Motors']);
    $this->post(route('cloud.trial.workspace.store'), ['slug' => 'mile-high']);
    $this->post(route('cloud.trial.account.store'), [
        'owner_name' => 'Sarah',
        'email' => 'sarah@milehigh.test',
        'password' => 'password123',
    ]);
    $this->get(route('cloud.welcome'));

    $user = User::query()->where('email', 'sarah@milehigh.test')->first();
    expect($user?->ownedShop?->slug)->toBe('mile-high');

    Auth::logout();
    session()->flush();

    $this->post(route('cloud.login.store'), [
        'email' => 'sarah@milehigh.test',
        'password' => 'password123',
    ])->assertRedirect(route('cloud.dashboard'));

    $this->get(route('cloud.dashboard'))
        ->assertOk()
        ->assertSee('Mile High Motors', false)
        ->assertSee('mile-high.arksms.com', false);

    $this->assertAuthenticated();
    expect(Auth::user()->ownedShop->display_name)->toBe('Mile High Motors');
});

it('rejects invalid cloud login credentials', function () {
    $user = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $this->post(route('cloud.login.store'), [
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('offers forgot password from cloud login', function () {
    $this->get(route('cloud.login'))
        ->assertOk()
        ->assertSee('Forgot password?', false)
        ->assertSee('/app/forgot-password', false);
});

it('supports password reset for cloud accounts', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->post('/app/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('serves pricing features resources login and demo', function () {
    $this->get(route('cloud.pricing'))->assertOk()->assertSee('Multi-Location', false);
    $this->get(route('cloud.features'))->assertOk()->assertSee('What makes Tuesday easier', false);
    $this->get(route('cloud.resources'))->assertOk()->assertSee('Built for the floor', false);
    $this->get(route('cloud.login'))->assertOk()->assertSee('Sign in', false);
    $this->get(route('cloud.demo'))
        ->assertOk()
        ->assertSee('Ready to get your shop online', false)
        ->assertDontSee('become an ARK customer', false);
});

it('points Cloud URLs at the company host when configured', function () {
    config([
        'surfaces.company' => 'autorepairkeeper.com',
        'surfaces.company_www' => 'www.autorepairkeeper.com',
        'surfaces.app' => 'app.demo-auto.test',
    ]);

    expect(\App\Ark\Platform\Cloud\CloudUrls::usesCloudPrefix())->toBeFalse()
        ->and(\App\Ark\Platform\Cloud\CloudUrls::route('home'))->toBe('https://autorepairkeeper.com/')
        ->and(\App\Ark\Platform\Cloud\CloudUrls::route('features'))->toBe('https://autorepairkeeper.com/features')
        ->and(\App\Ark\Platform\Cloud\CloudUrls::route('trial.shop'))->toBe('https://autorepairkeeper.com/trial');
});
