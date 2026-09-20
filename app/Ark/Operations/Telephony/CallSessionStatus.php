<?php

namespace App\Ark\Operations\Telephony;

enum CallSessionStatus: string
{
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Completed = 'completed';
    case Missed = 'missed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ringing => 'Ringing',
            self::Answered => 'Answered',
            self::Completed => 'Completed',
            self::Missed => 'Missed',
            self::Failed => 'Failed',
        };
    }

    public function operationalLabel(): string
    {
        return match ($this) {
            self::Answered => 'Active',
            default => $this->label(),
        };
    }

    public static function fromTwilioStatus(?string $status): self
    {
        return match ($status) {
            'in-progress', 'answered' => self::Answered,
            'completed' => self::Completed,
            'busy', 'no-answer', 'canceled' => self::Missed,
            'failed' => self::Failed,
            'initiated', 'ringing' => self::Ringing,
            default => self::Ringing,
        };
    }

    public static function fromAsteriskEvent(string $event, bool $wasAnswered = false): self
    {
        return match ($event) {
            'ringing', 'call_started' => self::Ringing,
            'answered', 'call_answered' => self::Answered,
            'busy', 'no_answer', 'no-answer', 'missed' => self::Missed,
            'failed' => self::Failed,
            'ended', 'call_ended', 'hangup' => $wasAnswered ? self::Completed : self::Missed,
            default => self::Ringing,
        };
    }
}
