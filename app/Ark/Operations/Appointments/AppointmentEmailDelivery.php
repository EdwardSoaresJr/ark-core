<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Installation\Mail\CustomerOutboundMail;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Operations\Settings\ShopSettings;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Customer appointment email via CustomerOutboundMail (Platform or installation SMTP).
 */
final class AppointmentEmailDelivery
{
    public function __construct(
        private readonly CustomerOutboundMail $mail,
    ) {}

    public function canSend(Appointment $appointment): bool
    {
        return $this->blockReason($appointment) === null;
    }

    public function blockReason(Appointment $appointment): ?string
    {
        if (! $this->mail->canSend()) {
            return "Email isn't configured yet. Configure Email in Settings.";
        }

        $email = AppointmentBookingIdentity::displayEmail($appointment);

        if (! filled($email)) {
            return 'No email address on this appointment.';
        }

        return null;
    }

    public function sendConfirmation(Appointment $appointment): void
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            throw new RuntimeException('Canceled appointments cannot send confirmation email.');
        }

        $this->send($appointment, AppointmentEmailCopy::confirmation($appointment), 'confirmation');
    }

    public function sendReminder(Appointment $appointment, string $type): void
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            throw new RuntimeException('Canceled appointments cannot send reminder email.');
        }

        $copy = match ($type) {
            SendAppointmentReminderSmsAction::TYPE_DAY_BEFORE => AppointmentEmailCopy::dayBeforeReminder($appointment),
            SendAppointmentReminderSmsAction::TYPE_HOURS_BEFORE => AppointmentEmailCopy::hoursBeforeReminder(
                $appointment,
                max(1, (int) ($appointment->reminder_hours_before ?? 1)),
            ),
            default => throw new RuntimeException('Unknown reminder type.'),
        };

        $this->send($appointment, $copy, $type);
    }

    public function sendCanceled(Appointment $appointment): void
    {
        $this->send($appointment, AppointmentEmailCopy::canceled($appointment), 'canceled');
    }

    public function sendRescheduled(Appointment $appointment, ?string $previousWhen = null): void
    {
        $this->send($appointment, AppointmentEmailCopy::rescheduled($appointment, $previousWhen), 'rescheduled');
    }

    /**
     * @param  array{subject: string, intro: string, lines: list<string>, kind: string}  $copy
     */
    private function send(Appointment $appointment, array $copy, string $idempotencySuffix): void
    {
        $reason = $this->blockReason($appointment);
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }

        $email = strtolower(trim((string) AppointmentBookingIdentity::displayEmail($appointment)));
        $shop = ShopMailBranding::shopName();

        $result = $this->mail->send(
            operation: TransactionalMailOperation::AppointmentSend->value,
            recipientEmail: $email,
            variables: [
                'shop_name' => $shop !== '' ? $shop : (string) (ShopSettings::current()->shop_name ?? 'Your shop'),
                'kind' => $copy['kind'],
                'subject' => $copy['subject'],
                'intro' => $copy['intro'],
                'lines' => $copy['lines'],
                'appointment_id' => (string) $appointment->getKey(),
            ],
            idempotencyKey: 'appointment-'.$appointment->getKey().'-'.$idempotencySuffix.'-'.Str::uuid(),
            domainObjectType: 'appointment',
            domainObjectId: (string) $appointment->getKey(),
        );

        if (! $result->ok()) {
            throw new RuntimeException($result->message ?? 'Email could not be sent.');
        }
    }
}
