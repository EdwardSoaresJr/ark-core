<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\MessageActionContract;
use App\Ark\Operations\Messaging\MessageActionKey;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;
use RuntimeException;

final class SendAppointmentConfirmationSmsAction
{
    public function __construct(
        private readonly SendOutboundMessageAction $sender,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function execute(Appointment $appointment, User $actor): void
    {
        $appointment->loadMissing('customer', 'repairOrder');

        if ($appointment->status === AppointmentStatus::Canceled) {
            throw new RuntimeException('Canceled appointments cannot send confirmation texts.');
        }

        if ($appointment->confirmation_sms_sent_at !== null) {
            throw new RuntimeException('Confirmation text was already sent for this appointment.');
        }

        $customer = $appointment->customer;

        if ($customer === null) {
            throw new RuntimeException('Appointment does not have a customer.');
        }

        $eligibility = CustomerSmsSendEligibility::for($customer, $this->credentials);

        if ($block = $eligibility->blockReason()) {
            throw new RuntimeException($block);
        }

        $this->sender->execute(
            customer: $customer,
            actor: $actor,
            body: AppointmentSmsCopy::confirmation($appointment),
            repairOrder: $appointment->repairOrder,
            metadata: MessageActionContract::metadata(
                MessageActionKey::AppointmentConfirmation,
                MessageActionContract::appointmentReplyMap(),
                $appointment->id,
                $appointment->starts_at?->copy()->addHours(4),
            ),
        );

        $appointment->forceFill([
            'confirmation_sms_sent_at' => now(),
        ])->save();
    }
}
