<?php

namespace App\Ark\Operations\Appointments;

use App\Models\User;
use RuntimeException;

final class SendAppointmentConfirmationSmsAction
{
    public function __construct(
        private readonly AppointmentSmsDelivery $delivery,
    ) {}

    public function execute(Appointment $appointment, User $actor): void
    {
        $this->delivery->sendConfirmation($appointment, $actor);
    }
}
