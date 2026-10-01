<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Mail\EmailIntent;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class StaffInvitationIssuer
{
    public const INVITE_VALID_DAYS = 7;

    public function __construct(private readonly ArkMailClient $mail) {}

    public function send(User $user): void
    {
        if (! $user->isActive()) {
            throw new RuntimeException('Cannot invite a disabled staff account.');
        }

        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $shopName = ShopSettings::current()->shop_name ?: config('app.name', 'ARK');
        $setupUrl = URL::temporarySignedRoute(
            'staff.invitation.accept',
            now()->addDays(self::INVITE_VALID_DAYS),
            ['user' => $user->id],
        );

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'employee.invitation',
            to: (string) $user->email,
            variables: [
                'shop_name' => $shopName,
                'employee_name' => (string) $user->name,
                'setup_url' => $setupUrl,
                'expires_in' => self::INVITE_VALID_DAYS.' days',
            ],
            idempotencyKey: 'employee-invitation-'.$user->id.'-'.Str::uuid(),
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.',
            );
        }
    }

    public static function placeholderPassword(): string
    {
        return Hash::make(Str::password(64));
    }
}
