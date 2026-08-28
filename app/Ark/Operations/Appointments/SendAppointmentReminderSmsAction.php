<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\MessageActionContract;
use App\Ark\Operations\Messaging\MessageActionKey;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

final class SendAppointmentReminderSmsAction
{
    public const TYPE_DAY_BEFORE = 'day_before';

    public const TYPE_HOURS_BEFORE = 'hours_before';

    public function __construct(
        private readonly SendOutboundMessageAction $sender,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function execute(Appointment $appointment, string $type, User $actor): void
    {
        $appointment->loadMissing('customer', 'repairOrder');

        if ($appointment->status === AppointmentStatus::Canceled) {
            throw new RuntimeException('Canceled appointments cannot send reminder texts.');
        }

        $customer = $appointment->customer;

        if ($customer === null) {
            throw new RuntimeException('Appointment does not have a customer.');
        }

        $eligibility = CustomerSmsSendEligibility::for($customer, $this->credentials);

        if ($block = $eligibility->blockReason()) {
            throw new RuntimeException($block);
        }

        $body = match ($type) {
            self::TYPE_DAY_BEFORE => AppointmentSmsCopy::dayBeforeReminder($appointment),
            self::TYPE_HOURS_BEFORE => AppointmentSmsCopy::hoursBeforeReminder(
                $appointment,
                max(1, (int) ($appointment->reminder_hours_before ?? 1)),
            ),
            default => throw new RuntimeException('Unknown reminder type.'),
        };

        $this->sender->execute(
            customer: $customer,
            actor: $actor,
            body: $body,
            repairOrder: $appointment->repairOrder,
            metadata: MessageActionContract::metadata(
                MessageActionKey::AppointmentReminder,
                MessageActionContract::appointmentReplyMap(),
                $appointment->id,
                $appointment->starts_at?->copy()->addHours(4),
            ),
        );

        $column = $type === self::TYPE_DAY_BEFORE
            ? 'reminder_day_before_sent_at'
            : 'reminder_hours_before_sent_at';

        $appointment->forceFill([
            $column => now(),
        ])->save();
    }

    public function isDayBeforeDue(Appointment $appointment, ?Carbon $now = null): bool
    {
        if (! $appointment->reminder_day_before || $appointment->reminder_day_before_sent_at !== null) {
            return false;
        }

        $now ??= ShopDisplayTimezone::now();
        $starts = ShopDisplayTimezone::present($appointment->starts_at);
        $windowStart = $starts->copy()->subDay();
        $windowEnd = $windowStart->copy()->addMinutes(90);

        return $now->gte($windowStart) && $now->lt($windowEnd) && $now->lt($starts);
    }

    public function isHoursBeforeDue(Appointment $appointment, ?Carbon $now = null): bool
    {
        $hours = $appointment->reminder_hours_before;

        if ($hours === null || $hours < 1 || $appointment->reminder_hours_before_sent_at !== null) {
            return false;
        }

        $now ??= ShopDisplayTimezone::now();
        $starts = ShopDisplayTimezone::present($appointment->starts_at);
        $windowStart = $starts->copy()->subHours((int) $hours);
        $windowEnd = $windowStart->copy()->addMinutes(90);

        return $now->gte($windowStart) && $now->lt($windowEnd) && $now->lt($starts);
    }
}
