<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Messaging\OutboundDeliveryMode;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Mail\EmailIntent;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Staff handoff of the authenticated inspection walk URL.
 * Uses shop Twilio + Laravel mail - not device sms:/mailto: protocols.
 * Does not write customer ConversationMessage authority.
 */
final class SendInspectionWalkLinkAction
{
    public function __construct(
        private readonly OutboundSmsTransport $sms,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly ArkMailClient $mail,
    ) {}

    /**
     * @return array{status_label: string, sms_sent: bool, email_sent: bool}
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        User $recipient,
        OutboundDeliveryMode $delivery,
    ): array {
        $this->assertEligibleRecipient($recipient);

        $repairOrder->loadMissing('vehicle');
        $walkUrl = InspectionCaptureLinks::walkUrl($repairOrder);
        $roLabel = 'RO #'.$repairOrder->repair_order_id;
        $vehicle = trim((string) ($repairOrder->vehicle?->display_name ?? 'Vehicle'));
        $smsBody = "Vehicle inspection for {$vehicle} ({$roLabel}): {$walkUrl}";

        $smsSent = false;
        $emailSent = false;

        if ($delivery->includesSms()) {
            $this->sendSms($recipient, $smsBody);
            $smsSent = true;
        }

        if ($delivery->includesEmail()) {
            $this->sendEmail($recipient, $walkUrl, $vehicle, $roLabel);
            $emailSent = true;
        }

        $parts = [];
        if ($smsSent) {
            $parts[] = 'texted';
        }
        if ($emailSent) {
            $parts[] = 'emailed';
        }

        $statusLabel = 'Walk link '.implode(' and ', $parts).' to '.$recipient->name.'.';

        return [
            'status_label' => $statusLabel,
            'sms_sent' => $smsSent,
            'email_sent' => $emailSent,
        ];
    }

    private function assertEligibleRecipient(User $recipient): void
    {
        if (! $recipient->is_active) {
            throw new RuntimeException('That staff member is not active.');
        }

        if (! $recipient->hasAnyRole([
            ArkRole::Technician->value,
            ArkRole::Advisor->value,
            ArkRole::Admin->value,
        ])) {
            throw new RuntimeException('That staff member cannot receive inspection walk links.');
        }
    }

    private function sendSms(User $recipient, string $body): void
    {
        if (! $this->credentials->messagingConfigured()) {
            throw new RuntimeException('Outbound SMS is not configured.');
        }

        $rawPhone = $recipient->getRawOriginal('phone');
        if (! filled($rawPhone)) {
            throw new RuntimeException($recipient->name.' has no phone on file.');
        }

        $phone = PhoneNumber::normalize((string) $rawPhone);
        if ($phone === null || $phone === '') {
            throw new RuntimeException($recipient->name.' has an invalid phone number.');
        }

        $this->smsCapability->assertCapableOrFail($phone);
        $this->sms->send($phone, $body);
    }

    private function sendEmail(
        User $recipient,
        string $walkUrl,
        string $vehicle,
        string $roLabel,
    ): void {
        $email = trim((string) $recipient->email);
        if ($email === '') {
            throw new RuntimeException($recipient->name.' has no email on file.');
        }

        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'inspection.send',
            to: $email,
            variables: [
                'shop_name' => ShopMailBranding::shopName(),
                'recipient_name' => (string) $recipient->name,
                'vehicle' => $vehicle,
                'repair_order_label' => $roLabel,
                'walk_url' => $walkUrl,
            ],
            idempotencyKey: 'inspection-walk-'.Str::uuid(),
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null) ? $result['message'] : 'Email could not be sent.',
            );
        }
    }
}
