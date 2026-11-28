<?php

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Installation\Mail\InstallationMailConfiguration;
use App\Ark\Installation\Mail\InstallationMailTransportFactory;
use App\Ark\Operations\EmailVerification\EmailVerification;
use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use App\Ark\Operations\EmailVerification\EmailVerificationException;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Transport\TransportInterface;

beforeEach(function (): void {
    ShopSettings::current()->persistTrusted([
        'shop_name' => 'Auth Acceptance Shop',
        'installation_mail_mode' => InstallationMailConfiguration::MODE_SHOP,
        'installation_mail_enabled' => true,
        'installation_mail_mailer' => 'smtp',
        'installation_mail_host' => '127.0.0.1',
        'installation_mail_port' => 587,
        'installation_mail_from_address' => 'shop@example.test',
        'cloud_status' => 'disconnected',
        'ark_mail_from_email' => null,
    ]);
    config(['mail.default' => 'array', 'mail.from.address' => 'shop@example.test']);

    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->zeroOrMoreTimes()->andReturnNull();
    app()->instance(InstallationMailTransportFactory::class, new class($transport) extends InstallationMailTransportFactory
    {
        public function __construct(private TransportInterface $transport) {}

        public function make(array $config): TransportInterface
        {
            return $this->transport;
        }
    });
});

it('enforces booking identity expiry attempts and single-use consume without logging the plain code', function () {
    config([
        'email_verification.code_ttl_minutes' => 5,
        'email_verification.max_attempts' => 2,
        'email_verification.session_ttl_minutes' => 30,
    ]);

    $logged = '';
    Log::listen(function ($message) use (&$logged): void {
        $logged .= strtolower(json_encode($message));
    });

    $authority = app(EmailVerificationAuthority::class);
    $session = session()->driver();
    $authority->issue('codeuser@example.test', '127.0.0.1');

    $row = EmailVerification::query()->where('email', 'codeuser@example.test')->latest('id')->first();
    expect($row)->not->toBeNull()
        ->and($row->expires_at->isFuture())->toBeTrue()
        ->and($row->attempts_remaining)->toBe(2)
        ->and($row->code_hash)->not->toBe('483291');

    $row->forceFill([
        'code_hash' => EmailVerification::hashCode('483291'),
        'attempts_remaining' => 2,
    ])->save();

    expect(fn () => $authority->verify($session, 'codeuser@example.test', '000000'))
        ->toThrow(EmailVerificationException::class);
    expect($row->fresh()->attempts_remaining)->toBe(1);

    expect(fn () => $authority->verify($session, 'codeuser@example.test', '000000'))
        ->toThrow(EmailVerificationException::class);
    expect(fn () => $authority->verify($session, 'codeuser@example.test', '483291'))
        ->toThrow(EmailVerificationException::class);

    $authority->issue('codeuser2@example.test', '127.0.0.1');
    EmailVerification::query()->where('email', 'codeuser2@example.test')->latest('id')->first()->forceFill([
        'code_hash' => EmailVerification::hashCode('654321'),
        'attempts_remaining' => 5,
        'expires_at' => now()->addMinutes(5),
        'consumed_at' => null,
        'verified_at' => null,
    ])->save();

    expect($authority->verify($session, 'codeuser2@example.test', '654321'))->toBeTrue()
        ->and($authority->consumeVerifiedSession($session, 'codeuser2@example.test'))->toBeTrue()
        ->and($authority->consumeVerifiedSession($session, 'codeuser2@example.test'))->toBeFalse()
        ->and(EmailVerification::query()->where('email', 'codeuser2@example.test')->latest('id')->value('consumed_at'))
        ->not->toBeNull();

    expect($logged)->not->toContain('483291')
        ->and($logged)->not->toContain('654321');
});

it('binds staff verification to signed url identity and rejects bad hash and expired signatures', function () {
    Event::fake();
    $user = User::factory()->unverified()->create(['email' => 'staff-accept@example.test']);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );
    $this->actingAs($user)->get($url)->assertRedirect();
    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)->get($url);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $other = User::factory()->unverified()->create(['email' => 'other-staff@example.test']);
    $bad = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $other->id, 'hash' => sha1($user->email)],
    );
    $this->actingAs($other)->get($bad);
    expect($other->fresh()->hasVerifiedEmail())->toBeFalse();

    $fresh = User::factory()->unverified()->create(['email' => 'staff-expiry@example.test']);
    $expired = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(1),
        ['id' => $fresh->id, 'hash' => sha1($fresh->email)],
    );
    $this->actingAs($fresh)->get($expired);
    expect($fresh->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('keeps staff password recovery functional and settings-ready outbound without env edits', function () {
    expect(app(CustomerOutboundMail::class)->canSend())->toBeTrue()
        ->and(app(InstallationMailConfiguration::class)->mode())
        ->toBe(InstallationMailConfiguration::MODE_SHOP);

    $user = User::factory()->create(['email' => 'reset-accept@example.test']);
    $status = Password::broker()->sendResetLink(['email' => $user->email]);
    expect($status)->toBe(Password::RESET_LINK_SENT);
});
