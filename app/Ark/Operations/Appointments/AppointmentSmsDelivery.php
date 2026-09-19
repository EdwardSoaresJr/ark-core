<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\MessageActionContract;
use App\Ark\Operations\Messaging\MessageActionKey;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Platform\Communications\ArkCommunicationsClient;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sends appointment SMS using linked Customer when present,
 * otherwise booking-snapshot phone via conversation contact surface.
 */
final class AppointmentSmsDelivery
{
    public function __construct(
        private readonly SendOutboundMessageAction $customerSender,
        private readonly ConversationResolver $conversations,
        private readonly ConversationRecorder $recorder,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly OutboundSmsTransport $transport,
    ) {}

    public function sendConfirmation(Appointment $appointment, User $actor): void
    {
        $appointment->loadMissing('customer', 'repairOrder');

        if ($appointment->status === AppointmentStatus::Canceled) {
            throw new RuntimeException('Canceled appointments cannot send confirmation texts.');
        }

        if ($appointment->confirmation_sms_sent_at !== null) {
            throw new RuntimeException('Confirmation text was already sent for this appointment.');
        }

        $this->send(
            $appointment,
            $actor,
            AppointmentSmsCopy::confirmation($appointment),
            MessageActionKey::AppointmentConfirmation,
        );

        $appointment->forceFill([
            'confirmation_sms_sent_at' => now(),
        ])->save();
    }

    public function sendReminder(Appointment $appointment, string $type, User $actor): void
    {
        $appointment->loadMissing('customer', 'repairOrder');

        if ($appointment->status === AppointmentStatus::Canceled) {
            throw new RuntimeException('Canceled appointments cannot send reminder texts.');
        }

        $body = match ($type) {
            SendAppointmentReminderSmsAction::TYPE_DAY_BEFORE => AppointmentSmsCopy::dayBeforeReminder($appointment),
            SendAppointmentReminderSmsAction::TYPE_HOURS_BEFORE => AppointmentSmsCopy::hoursBeforeReminder(
                $appointment,
                max(1, (int) ($appointment->reminder_hours_before ?? 1)),
            ),
            default => throw new RuntimeException('Unknown reminder type.'),
        };

        $this->send(
            $appointment,
            $actor,
            $body,
            MessageActionKey::AppointmentReminder,
        );

        $column = $type === SendAppointmentReminderSmsAction::TYPE_DAY_BEFORE
            ? 'reminder_day_before_sent_at'
            : 'reminder_hours_before_sent_at';

        $appointment->forceFill([
            $column => now(),
        ])->save();
    }

    public function canSend(Appointment $appointment): bool
    {
        return $this->blockReason($appointment) === null;
    }

    public function blockReason(Appointment $appointment): ?string
    {
        $appointment->loadMissing('customer');

        if ($appointment->customer !== null) {
            return CustomerSmsSendEligibility::for($appointment->customer, $this->credentials)->blockReason();
        }

        $phone = AppointmentBookingIdentity::displayPhone($appointment);
        if (! filled($phone)) {
            return 'No phone number on this appointment.';
        }

        if (! ManagedCommunicationsGate::platformSend() && ! $this->credentials->twilioConfigured()) {
            return 'ARK Texting is not connected.';
        }

        $normalized = PhoneNumber::normalize((string) $phone);
        if ($normalized === null) {
            return 'Appointment phone number is not valid.';
        }

        $capability = PhoneSmsCapability::findByNormalizedPhone($normalized);
        if ($capability !== null && ! $capability->sms_capable) {
            return $capability->blockReason();
        }

        return null;
    }

    private function send(
        Appointment $appointment,
        User $actor,
        string $body,
        MessageActionKey $actionKey,
    ): void {
        if ($block = $this->blockReason($appointment)) {
            throw new RuntimeException($block);
        }

        $metadata = MessageActionContract::metadata(
            $actionKey,
            MessageActionContract::appointmentReplyMap(),
            $appointment->id,
            $appointment->starts_at?->copy()->addHours(4),
        );

        if ($appointment->customer !== null) {
            $this->customerSender->execute(
                customer: $appointment->customer,
                actor: $actor,
                body: $body,
                repairOrder: $appointment->repairOrder,
                metadata: $metadata,
            );

            return;
        }

        $phone = (string) AppointmentBookingIdentity::displayPhone($appointment);
        $normalized = PhoneNumber::normalize($phone) ?? $phone;

        if (ManagedCommunicationsGate::platformSend()) {
            $platform = app(ArkCommunicationsClient::class)->sendConversationMessage(
                toPhone: $normalized,
                body: $body,
                idempotencyKey: 'core-appt-'.(string) Str::uuid(),
                domainObjectType: 'appointment',
                domainObjectId: (string) $appointment->id,
                coreActorUserId: (int) $actor->id,
            );

            if (! ($platform['ok'] ?? false)) {
                throw new RuntimeException(
                    (string) ($platform['message'] ?? 'ARK Communications could not send the message.'),
                );
            }

            if (! ManagedCommunicationsGate::coreMirrorEnabled()) {
                return;
            }

            $providerSid = (string) ($platform['provider_message_id'] ?? '');
            if ($providerSid === '') {
                $providerSid = 'platform:'.($platform['message_id'] ?? Str::uuid());
            }

            $conversation = $this->conversations->forPhone($normalized);
            $this->recorder->recordOutboundSmsToConversation(
                conversation: $conversation,
                actor: $actor,
                body: $body,
                providerMessageSid: $providerSid,
                repairOrder: $appointment->repairOrder,
                metadata: $metadata,
            );

            return;
        }

        $result = $this->transport->send($normalized, $body);
        $conversation = $this->conversations->forPhone($normalized);

        $this->recorder->recordOutboundSmsToConversation(
            conversation: $conversation,
            actor: $actor,
            body: $body,
            providerMessageSid: $result->messageId,
            repairOrder: $appointment->repairOrder,
            metadata: $metadata,
        );
    }
}
