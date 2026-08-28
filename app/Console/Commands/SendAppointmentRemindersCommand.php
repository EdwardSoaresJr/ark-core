<?php

namespace App\Console\Commands;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\SendAppointmentReminderSmsAction;
use App\Ark\Operations\OperationsFeatures;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send opted-in appointment reminder SMS that are due (day-before / hours-before).';

    public function handle(SendAppointmentReminderSmsAction $send): int
    {
        if (! OperationsFeatures::appointmentsEnabled()) {
            return self::SUCCESS;
        }

        $active = array_map(
            static fn (AppointmentStatus $status): string => $status->value,
            AppointmentStatus::activeToday(),
        );

        $query = Appointment::query()
            ->with(['customer', 'repairOrder', 'advisor', 'creator'])
            ->whereIn('status', $active)
            ->where(function ($builder): void {
                $builder->where(function ($day): void {
                    $day->where('reminder_day_before', true)
                        ->whereNull('reminder_day_before_sent_at');
                })->orWhere(function ($hours): void {
                    $hours->whereNotNull('reminder_hours_before')
                        ->whereNull('reminder_hours_before_sent_at');
                });
            })
            ->where('starts_at', '>', now()->subHour())
            ->orderBy('starts_at');

        $sent = 0;

        foreach ($query->cursor() as $appointment) {
            $actor = $this->actorFor($appointment);

            if ($actor === null) {
                continue;
            }

            if ($send->isDayBeforeDue($appointment)) {
                try {
                    $send->execute($appointment, SendAppointmentReminderSmsAction::TYPE_DAY_BEFORE, $actor);
                    $sent++;
                } catch (Throwable $exception) {
                    Log::warning('Appointment day-before reminder failed.', [
                        'appointment_id' => $appointment->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $appointment->refresh();

            if ($send->isHoursBeforeDue($appointment)) {
                try {
                    $send->execute($appointment, SendAppointmentReminderSmsAction::TYPE_HOURS_BEFORE, $actor);
                    $sent++;
                } catch (Throwable $exception) {
                    Log::warning('Appointment hours-before reminder failed.', [
                        'appointment_id' => $appointment->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($sent > 0) {
            $this->info("Sent {$sent} appointment reminder(s).");
        }

        return self::SUCCESS;
    }

    private function actorFor(Appointment $appointment): ?User
    {
        return $appointment->advisor
            ?? $appointment->creator
            ?? User::query()->orderBy('id')->first();
    }
}
