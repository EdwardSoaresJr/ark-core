<?php

namespace App\Ark\Operations\Appointments;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class SendAppointmentConfirmationSmsAction
{
    public function __construct(
        private readonly AppointmentSmsDelivery $delivery,
        private readonly AppointmentEmailDelivery $email,
    ) {}

    public function execute(Appointment $appointment, User $actor): void
    {
        $smsError = null;

        try {
            $this->delivery->sendConfirmation($appointment, $actor);
        } catch (RuntimeException $exception) {
            $smsError = $exception;
        }

        $emailSent = false;
        if ($this->email->canSend($appointment)) {
            try {
                $this->email->sendConfirmation($appointment);
                $emailSent = true;
            } catch (Throwable $exception) {
                Log::warning('appointment_confirmation_email_failed', [
                    'appointment_id' => $appointment->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($smsError !== null && ! $emailSent) {
            throw $smsError;
        }
    }
}
