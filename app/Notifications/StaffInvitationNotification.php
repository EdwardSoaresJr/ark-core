<?php

namespace App\Notifications;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Staff\StaffInvitationIssuer;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class StaffInvitationNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $shopName = ShopSettings::current()->shop_name ?: config('app.name', 'ARK-SMS');
        $setupUrl = URL::temporarySignedRoute(
            'staff.invitation.accept',
            now()->addDays(StaffInvitationIssuer::INVITE_VALID_DAYS),
            ['user' => $notifiable->id],
        );

        return (new MailMessage)
            ->subject($shopName.' — set up your ARK access')
            ->greeting('Hello '.$notifiable->name)
            ->line('You have been added to '.$shopName.' on ARK-SMS.')
            ->line('Use the secure link below to sign in and choose your own password. The link expires in '.StaffInvitationIssuer::INVITE_VALID_DAYS.' days.')
            ->action('Set up your account', $setupUrl)
            ->line('If you did not expect this email, contact your shop administrator.');
    }
}
